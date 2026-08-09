# gimportpitches.py — batch-import pitches and people from a shared JSON export
# Arg: {sheet_id}|{base64_encoded_json}
# JSON: { "pitches": [...], "people": [...] }
# Returns: { "ok": true, "pitches_added": N, "people_added": M }

import gspread
import sys, os, json, base64

credFileName = os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', 'credentials.json')

def safe_str(v):
    """Prefix non-empty strings with ' to prevent Google Sheets formula interpretation."""
    s = (str(v) if v is not None else '').strip()
    return ("'" + s) if s else ''

if not os.path.exists(credFileName):
    print(json.dumps({"error": "credentials.json not found"}))
    sys.exit(1)

try:
    sa = gspread.service_account(filename=credFileName)
except Exception as e:
    print(json.dumps({"error": f"Could not authenticate: {str(e)}"}))
    sys.exit(1)

arg      = sys.argv[1] if len(sys.argv) > 1 else ''
pipe_idx = arg.index('|')
sheet_id = arg[:pipe_idx]
data     = json.loads(base64.b64decode(arg[pipe_idx + 1:]).decode('utf-8'))

pitches         = data.get('pitches',         [])
updated_pitches = data.get('updated_pitches', [])
people          = data.get('people',          [])
game_row        = data.get('game',            None)   # dict with game fields, or None

try:
    wb = sa.open_by_key(sheet_id)
except Exception as e:
    print(json.dumps({"error": f"Could not open spreadsheet: {str(e)}"}))
    sys.exit(1)

all_ws = wb.worksheets()

def find_ws(name):
    """Return worksheet by case-insensitive name, or None."""
    return next((w for w in all_ws if w.title.lower() == name.lower()), None)

def get_headers(ws):
    try:
        vals = ws.get_all_values()
        return vals[0] if vals else [], vals
    except Exception as e:
        return [], []

def last_data_row(all_values):
    last = 1
    for i, row in enumerate(all_values, start=1):
        if any(cell.strip() for cell in row):
            last = i
    return last

def append_rows(ws, headers, rows_data):
    """Append a list of dicts as rows aligned to headers."""
    all_values = None
    try:
        all_values = ws.get_all_values()
    except Exception as e:
        raise RuntimeError(f"Could not read sheet: {str(e)}")

    headers_lower = [h.strip().lower() for h in headers]
    next_row = last_data_row(all_values) + 1
    added = 0

    for row_dict in rows_data:
        new_row = []
        for h in headers:
            # Try exact match first, then case-insensitive
            v = row_dict.get(h.strip(), '')
            if not v:
                for k, kv in row_dict.items():
                    if k.strip().lower() == h.strip().lower():
                        v = kv
                        break
            new_row.append(safe_str(v))

        if not any(new_row):
            continue

        end_cell  = gspread.utils.rowcol_to_a1(next_row, len(new_row))
        range_str = f'A{next_row}:{end_cell}'
        try:
            ws.update([new_row], range_str, value_input_option='USER_ENTERED')
        except Exception as e:
            raise RuntimeError(f"Could not write row {next_row}: {str(e)}")
        next_row += 1
        added    += 1

    return added

pitches_added   = 0
pitches_updated = 0
people_added    = 0
game_added      = False

# ── Import game (if new) ───────────────────────────────────────────────────────
if game_row and (game_row.get('Name') or '').strip():
    ws_games = find_ws('games')
    if ws_games is None:
        print(json.dumps({"error": "Games worksheet not found"}))
        sys.exit(1)

    try:
        all_values = ws_games.get_all_values()
    except Exception as e:
        print(json.dumps({"error": f"Could not read Games sheet: {str(e)}"}))
        sys.exit(1)

    if not all_values:
        print(json.dumps({"error": "Games sheet is empty — no header row"}))
        sys.exit(1)

    headers = all_values[0]
    col     = {h.strip(): i for i, h in enumerate(headers)}

    def find_gcol(*variants):
        for v in variants:
            if v in col:
                return col[v]
        return -1

    def gv(key):
        return (game_row.get(key) or '').strip()

    num_cols = len(headers)
    new_grow = [''] * num_cols

    def set_gcol(value, *variants):
        idx = find_gcol(*variants)
        if idx >= 0:
            new_grow[idx] = safe_str(value)

    set_gcol(gv('Name'),           'Name')
    set_gcol(gv('Tagline'),        'Tagline', 'Tag Line', 'SubTitle', 'Subtitle')
    set_gcol(gv('Status'),         'Status')
    set_gcol(gv('Date Started'),   'Date Started',   'DateStarted',   'Start Date',      'StartDate')
    set_gcol(gv('Date Signed'),    'Date Signed',    'DateSigned',    'Signed Date',      'SignedDate')
    set_gcol(gv('Date Published'), 'Date Published', 'DatePublished', 'Published Date',   'PublishedDate')
    set_gcol(gv('Designer1'),      'Designer1', 'Designer 1')
    set_gcol(gv('Designer2'),      'Designer2', 'Designer 2')
    set_gcol(gv('Designer3'),      'Designer3', 'Designer 3')
    set_gcol(gv('Designer4'),      'Designer4', 'Designer 4')
    set_gcol(gv('Description') or gv('Summary'),               'Description', 'Summary')
    set_gcol(gv('Rules')     or gv('Rules URL'),               'Rules',     'Rules URL',     'RulesURL')
    set_gcol(gv('Play')      or gv('Play URL'),                'Play',      'Play URL',      'PlayURL')
    set_gcol(gv('Print')     or gv('Print URL'),               'Print',     'Print URL',     'PrintURL')
    set_gcol(gv('Sellsheet') or gv('Sellsheet URL'),           'Sellsheet', 'Sellsheet URL', 'SellsheetURL')
    set_gcol(gv('View')      or gv('View URL') or gv('BGG'),   'BGG',       'View URL',      'BGG / View URL', 'ViewURL', 'View')
    set_gcol(gv('Video')     or gv('Video URL'),               'Video',     'Video URL',     'VideoURL')

    next_row_idx = last_data_row(all_values) + 1
    end_cell     = gspread.utils.rowcol_to_a1(next_row_idx, len(new_grow))
    try:
        ws_games.update([new_grow], f'A{next_row_idx}:{end_cell}', value_input_option='USER_ENTERED')
        game_added = True
    except Exception as e:
        print(json.dumps({"error": f"Could not add game: {str(e)}"}))
        sys.exit(1)

# ── Import pitches ─────────────────────────────────────────────────────────────
if pitches:
    ws_pitches = find_ws('pitches')
    if ws_pitches is None:
        print(json.dumps({"error": "Pitches worksheet not found"}))
        sys.exit(1)
    try:
        headers_raw = ws_pitches.row_values(1)
        pitches_added = append_rows(ws_pitches, headers_raw, pitches)
    except Exception as e:
        print(json.dumps({"error": f"Pitches import failed: {str(e)}"}))
        sys.exit(1)

# ── Update existing pitches (Status / Notes) ──────────────────────────────────
if updated_pitches:
    ws_pitches = ws_pitches if 'ws_pitches' in dir() else find_ws('pitches')
    if ws_pitches is None:
        print(json.dumps({"error": "Pitches worksheet not found"}))
        sys.exit(1)
    try:
        all_values = ws_pitches.get_all_values()
    except Exception as e:
        print(json.dumps({"error": f"Could not read Pitches sheet for update: {str(e)}"}))
        sys.exit(1)

    if all_values:
        headers = all_values[0]
        hl = [h.strip().lower() for h in headers]

        def find_col(name):
            return hl.index(name) if name in hl else -1

        col_game    = find_col('game')
        col_pub     = find_col('publisher')
        col_contact = find_col('contact')
        col_date    = find_col('date')
        col_status  = find_col('status')
        col_notes   = find_col('notes')

        batch = []
        for r in updated_pitches:
            rg = (r.get('Game',      '') or '').strip().lower()
            rp = (r.get('Publisher', '') or '').strip().lower()
            rc = (r.get('Contact',   '') or '').strip().lower()
            rd = (r.get('Date',      '') or '').strip()
            new_status = (r.get('Status', '') or '').strip()
            new_notes  = (r.get('Notes',  '') or '').strip()

            for row_i, row in enumerate(all_values[1:], start=2):  # 1-based, skip header
                def cell(ci):
                    return (row[ci] if 0 <= ci < len(row) else '').strip().lstrip("'")

                if (cell(col_game).lower()    == rg and
                    cell(col_pub).lower()     == rp and
                    cell(col_contact).lower() == rc and
                    cell(col_date)            == rd):
                    if col_status >= 0 and new_status:
                        batch.append({
                            'range':  gspread.utils.rowcol_to_a1(row_i, col_status + 1),
                            'values': [[safe_str(new_status)]]
                        })
                    if col_notes >= 0:
                        batch.append({
                            'range':  gspread.utils.rowcol_to_a1(row_i, col_notes + 1),
                            'values': [[safe_str(new_notes)]]
                        })
                    pitches_updated += 1
                    break

        if batch:
            try:
                ws_pitches.batch_update(batch, value_input_option='USER_ENTERED')
            except Exception as e:
                print(json.dumps({"error": f"Could not update pitches: {str(e)}"}))
                sys.exit(1)

# ── Import people ──────────────────────────────────────────────────────────────
if people:
    ws_people = find_ws('people')
    if ws_people is None:
        print(json.dumps({"error": "People worksheet not found"}))
        sys.exit(1)
    try:
        headers_raw = ws_people.row_values(1)
        people_added = append_rows(ws_people, headers_raw, people)
    except Exception as e:
        print(json.dumps({"error": f"People import failed: {str(e)}"}))
        sys.exit(1)

print(json.dumps({
    "ok":              True,
    "game_added":      game_added,
    "pitches_added":   pitches_added,
    "pitches_updated": pitches_updated,
    "people_added":    people_added,
}))
