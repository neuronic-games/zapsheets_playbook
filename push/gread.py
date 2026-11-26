# START

# Packages Used
import gspread
import gretry  # retry temporary Google API errors (see gretry.py)
import sys, os, json, urllib.parse, socket
from pathlib import Path

# Prevent gspread/httplib2 from hanging indefinitely on slow API responses
socket.setdefaulttimeout(30)


# Credentials [Keys etc]
# Resolve credentials path relative to this script's location
credFileName = os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', 'credentials.json')

# Check credentials file exists before attempting to connect
if not os.path.exists(credFileName):
    print(json.dumps({"error": "credentials.json not found"}))
    sys.exit(1)

# Accessing Google service account using credentials
mServiceAccount = gspread.service_account(filename=credFileName)
# Storing Google Sheet Id passed
mGoogleSheetId = sys.argv[1].split('sheetname')[0]

# Open the sheet based on sheet id passed
mGoogleSheet = mServiceAccount.open_by_key(mGoogleSheetId)

# Checking if variable is None
if sys.argv[1].split('sheetname')[1] == "null":
    sheetName = mGoogleSheet.worksheets()[0].title
else :
    sheetName = sys.argv[1].split('sheetname')[1]

# Case-insensitive worksheet lookup
all_worksheets = mGoogleSheet.worksheets()
mSelectedWorkSheet = next((ws for ws in all_worksheets if ws.title == sheetName), None)
if mSelectedWorkSheet is None:
    # Try case-insensitive match
    mSelectedWorkSheet = next((ws for ws in all_worksheets if ws.title.lower() == sheetName.lower()), None)
if mSelectedWorkSheet is None:
    # Print the error to stdout so pushSheetUpdate.php can surface it in the sync log
    available = [ws.title for ws in all_worksheets]
    print(json.dumps({"error": f"Worksheet '{sheetName}' not found. Available: {available}"}))
    sys.exit(1)

# Creating .JSON files
if(sheetName == "settings"):
    path = "../sheets/" + mGoogleSheetId + "/settings.json"
elif (sheetName == "install"):
    path = "../sheets/" + mGoogleSheetId + "/install.json"
else:
    path = "../sheets/" + mGoogleSheetId + "/" + sheetName.lower() + ".json"

# Check for file exists (If not creates one)
if not os.path.exists(os.path.dirname(path)):
    os.makedirs(os.path.dirname(path))


# ── Hyperlink-aware cell reader ───────────────────────────────────────────────

def get_cell_text(cell):
    """
    Extract text from a Sheets API v4 cell object.
    Converts hyperlinks (both inline text-run links and whole-cell links)
    to [text](url) markdown so they survive JSON serialisation.
    Also recovers =IMAGE() formulas so they can be rendered by the dashboard.
    """
    text = cell.get('formattedValue', '')
    if not text:
        # =IMAGE() cells have no formattedValue — recover the formula so the
        # dashboard can render the image via obsHtml()
        formula = (cell.get('userEnteredValue') or {}).get('formulaValue', '')
        if formula and formula.strip().upper().startswith('=IMAGE('):
            return formula.strip()
        return ''

    # Inline text-run hyperlinks (e.g. "click [here](url) for more")
    runs = cell.get('textFormatRuns', [])
    has_link_runs = any(
        r.get('format', {}).get('link', {}).get('uri')
        for r in runs
    )

    if has_link_runs and runs:
        result = ''
        for i, run in enumerate(runs):
            start = run.get('startIndex', 0)
            end = (runs[i + 1].get('startIndex', len(text))
                   if i + 1 < len(runs) else len(text))
            chunk = text[start:end]
            uri = run.get('format', {}).get('link', {}).get('uri', '')
            result += f'[{chunk}]({uri})' if (uri and chunk) else chunk
        return result

    # Whole-cell hyperlink
    if cell.get('hyperlink'):
        return f'[{text}]({cell["hyperlink"]})'

    return text


def get_records_with_links(service_account_client, sheet_id, sheet_name):
    """
    Fetch sheet data via Sheets API v4 with includeGridData=true so that
    hyperlinks embedded in cells are preserved as [text](url) markdown.
    Raises on any failure; caller should fall back to get_all_values().
    """
    from google.auth.transport.requests import Request as GoogleAuthRequest
    import requests as requests_lib

    # gspread 5: client.auth — gspread 6: client.http_client.auth
    auth = getattr(service_account_client, 'auth', None) or service_account_client.http_client.auth
    if not auth.valid:
        auth.refresh(GoogleAuthRequest())

    url = (
        'https://sheets.googleapis.com/v4/spreadsheets/'
        + urllib.parse.quote(sheet_id, safe='')
        + '?includeGridData=true&ranges='
        + urllib.parse.quote(f"'{sheet_name}'", safe='')
    )

    resp = requests_lib.get(
        url,
        headers={'Authorization': f'Bearer {auth.token}'},
        timeout=30
    )
    resp.raise_for_status()
    data = resp.json()

    sheets = data.get('sheets', [])
    if not sheets:
        return []

    grid = sheets[0].get('data', [{}])[0]
    row_data = grid.get('rowData', [])
    if not row_data:
        return []

    # First row → column headers
    header_cells = row_data[0].get('values', [])
    headers = [get_cell_text(c) for c in header_cells]

    # DevBoard session tabs: also export cell notes (e.g. "TODO") as _notes {header: note}
    with_notes = sheet_name.lower().endswith('] dev')

    records = []
    del FMT[:]
    for row_no, row in enumerate(row_data[1:], start=2):
        cells = row.get('values', [])
        row_texts = [get_cell_text(c) for c in cells]

        # Skip entirely empty rows
        if not any(str(v).strip() for v in row_texts):
            continue

        record = {}
        for i, header in enumerate(headers):
            h = header.strip()
            if h:
                record[h] = row_texts[i] if i < len(row_texts) else ''
                if with_notes and i < len(cells) and cells[i].get('note'):
                    record.setdefault('_notes', {})[h] = cells[i]['note']
        if with_notes:
            record['_row'] = row_no   # sheet row number, used to update that row's note
        records.append(record)
        fm = {}
        for i, header in enumerate(headers):
            h = header.strip()
            if h and i < len(cells):
                f = get_cell_fmt(cells[i])
                if f: fm[h] = f
        FMT.append(fm or None)

    return records


# ── Formatting inside a cell (text runs): bold, italic, underline, strikethrough, colour ──
# Kept for CardBoard in a file of its own next to the records ({tab}.fmt.json), so the records stay
# the same for every app: [{field: {t: text, r: [[start, {b, i, u, s, c}], …]}} or null, …] —
# one entry per record. Only cells with formatting on part of their text (whole-cell formatting is
# the card element's own style), and not cells with links (their text is changed to [text](url)).

def _hex(c):
    rgb = c.get('rgbColor') if isinstance(c, dict) and 'rgbColor' in c else c
    if not isinstance(rgb, dict): return ''
    return '#%02x%02x%02x' % tuple(int(round(float(rgb.get(k, 0)) * 255)) for k in ('red', 'green', 'blue'))

def get_cell_fmt(cell):
    text = cell.get('formattedValue', '')
    runs = cell.get('textFormatRuns', [])
    if not text or not runs: return None
    if any(r.get('format', {}).get('link', {}).get('uri') for r in runs): return None
    out_runs, any_fmt = [], False
    for run in runs:
        f = run.get('format', {}) or {}
        d = {}
        if f.get('bold'): d['b'] = 1
        if f.get('italic'): d['i'] = 1
        if f.get('underline'): d['u'] = 1
        if f.get('strikethrough'): d['s'] = 1
        cs = f.get('foregroundColorStyle') or f.get('foregroundColor')
        if cs:
            col = _hex(cs)
            if col: d['c'] = col
        if d: any_fmt = True
        out_runs.append([int(run.get('startIndex', 0)), d])
    return {'t': text, 'r': out_runs} if any_fmt else None

FMT = []   # one entry per record (None when nothing in that row is formatted)

# ── Build records (prefer hyperlink-aware API; fall back to plain values) ─────

records = None

try:
    records = get_records_with_links(mServiceAccount, mGoogleSheetId, sheetName)
except Exception:
    pass  # fall through to get_all_values()

if records is None:
    # Fallback: use FORMULA render option so =IMAGE() cells return their formula
    # rather than an empty formattedValue string
    try:
        try:
            all_values = mSelectedWorkSheet.get_all_values(value_render_option='FORMULA')
        except TypeError:
            all_values = mSelectedWorkSheet.get_all_values()
    except Exception as e:
        err = json.dumps({"error": f"get_all_values failed for '{sheetName}': {type(e).__name__}: {str(e)}"})
        print(err)
        sys.exit(1)

    try:
        if not all_values:
            records = []
        else:
            headers = all_values[0]
            records = []
            for row in all_values[1:]:
                if not any(str(cell).strip() for cell in row):
                    continue
                record = {}
                for i, header in enumerate(headers):
                    if str(header).strip():
                        record[str(header).strip()] = str(row[i]) if i < len(row) else ''
                records.append(record)
    except Exception as e:
        err = json.dumps({"error": f"Record build failed for '{sheetName}': {type(e).__name__}: {str(e)}"})
        print(err)
        sys.exit(1)

try:
    json_data = json.dumps(records, ensure_ascii=False)
except Exception as e:
    err = json.dumps({"error": f"JSON serialise failed for '{sheetName}': {type(e).__name__}: {str(e)}"})
    print(err)
    sys.exit(1)

# Write to the JSON file
try:
    with open(path, 'w', encoding='utf8') as json_file:
        json_file.write(json_data)
except Exception as e:
    err = json.dumps({"error": f"File write failed for '{path}': {type(e).__name__}: {str(e)}"})
    print(err)
    sys.exit(1)

# The formatting inside cells, for CardBoard (see get_cell_fmt): next to the records, or removed when there is none
try:
    fmt_path = os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', 'sheets', mGoogleSheetId, os.path.basename(path)[:-5] + '.fmt.json')
    if len(FMT) == len(records):   # read through the Sheets API (the fallback knows nothing of formatting: left as it is)
        if any(FMT):
            with open(fmt_path, 'w', encoding='utf8') as f: f.write(json.dumps(FMT, ensure_ascii=False))
        elif os.path.exists(fmt_path):
            os.remove(fmt_path)
except Exception:
    pass

# Print the JSON to stdout so PHP can capture and relay it
print(json_data)

# DONE
