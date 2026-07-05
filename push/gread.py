# START

# Packages Used
import gspread
import sys, os, json, urllib.parse
from pathlib import Path


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
    # Write an empty array so downstream JSON reads don't 404, and print nothing
    # so pushSheetUpdate.php skips the file_put_contents guard (empty trim check).
    # The JS error handler in getSheetLanguage will skip the sheet gracefully.
    available = [ws.title for ws in all_worksheets]
    sys.stderr.write(json.dumps({"error": f"Worksheet '{sheetName}' not found. Available: {available}"}) + "\n")
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
    """
    text = cell.get('formattedValue', '')
    if not text:
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

    auth = service_account_client.auth
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

    records = []
    for row in row_data[1:]:
        cells = row.get('values', [])
        row_texts = [get_cell_text(c) for c in cells]

        # Skip entirely empty rows
        if not any(v.strip() for v in row_texts):
            continue

        record = {}
        for i, header in enumerate(headers):
            h = header.strip()
            if h:
                record[h] = row_texts[i] if i < len(row_texts) else ''
        records.append(record)

    return records


# ── Build records (prefer hyperlink-aware API; fall back to plain values) ─────

records = None

try:
    records = get_records_with_links(mServiceAccount, mGoogleSheetId, sheetName)
except Exception:
    pass  # fall through to get_all_values()

if records is None:
    # Fallback: plain get_all_values() – no hyperlinks preserved
    try:
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
                if not any(cell.strip() for cell in row):
                    continue
                record = {}
                for i, header in enumerate(headers):
                    if header.strip():
                        record[header.strip()] = row[i] if i < len(row) else ''
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

# Print the JSON to stdout so PHP can capture and relay it
print(json_data)

# DONE
