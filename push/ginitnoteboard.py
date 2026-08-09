# ginitnoteboard.py — initialise NoteBoard for a Google Spreadsheet.
#
# Creates a "notes" tab (the default notes sheet) if it does not exist,
# registers it in noteboard-index.json, and writes an empty local cache.
# No "Games" sheet is required — notes can exist without games.
#
# Argument:  "{sheet_id}"
# Returns:   {"ok": true, "hash": "...", "tabs_created": [...]}
#         or {"error": "…"}

import gspread
import sys, os, json, hashlib

credFileName = os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', 'credentials.json')
if not os.path.exists(credFileName):
    print(json.dumps({"error": "credentials.json not found"}))
    sys.exit(1)

try:
    sa = gspread.service_account(filename=credFileName)
except Exception as e:
    print(json.dumps({"error": f"Could not authenticate: {str(e)}"}))
    sys.exit(1)

sheet_id = (sys.argv[1] if len(sys.argv) > 1 else '').strip()
if not sheet_id:
    print(json.dumps({"error": "sheet_id argument required"}))
    sys.exit(1)

try:
    wb = sa.open_by_key(sheet_id)
except Exception as e:
    print(json.dumps({"error": f"Could not open spreadsheet: {str(e)}"}))
    sys.exit(1)


def refresh_ws_map():
    return {w.title: w for w in wb.worksheets()}


def _apply_column_formatting(wb, ws):
    """Set column widths (Date/Name/Email/Note) and wrap the Note column."""
    try:
        sid = ws.id
        wb.batch_update({'requests': [
            {'updateDimensionProperties': {'range': {'sheetId': sid, 'dimension': 'COLUMNS', 'startIndex': 0, 'endIndex': 1}, 'properties': {'pixelSize': 170}, 'fields': 'pixelSize'}},
            {'updateDimensionProperties': {'range': {'sheetId': sid, 'dimension': 'COLUMNS', 'startIndex': 1, 'endIndex': 2}, 'properties': {'pixelSize': 160}, 'fields': 'pixelSize'}},
            {'updateDimensionProperties': {'range': {'sheetId': sid, 'dimension': 'COLUMNS', 'startIndex': 2, 'endIndex': 3}, 'properties': {'pixelSize': 220}, 'fields': 'pixelSize'}},
            {'updateDimensionProperties': {'range': {'sheetId': sid, 'dimension': 'COLUMNS', 'startIndex': 3, 'endIndex': 4}, 'properties': {'pixelSize': 420}, 'fields': 'pixelSize'}},
        ]})
        ws.format('D3:D1000', {'wrapStrategy': 'WRAP'})
    except Exception:
        pass  # cosmetic — ignore failures


tabs_created = []
ws_map = refresh_ws_map()

# ── Ensure "notes" tab exists ─────────────────────────────────────────────────
NOTES_HEADERS = ['Date', 'Name', 'Email', 'Note']
TAB_NAME   = 'notes'
topic_name = 'notes'

existing = ws_map.get(TAB_NAME) or next(
    (ws for t, ws in ws_map.items() if t.lower() == TAB_NAME), None
)

if existing is None:
    try:
        ws = wb.add_worksheet(title=TAB_NAME, rows=200, cols=4)
        ws.update([['Name', topic_name], NOTES_HEADERS], 'A1',
                  value_input_option='USER_ENTERED')
        ws.freeze(rows=2)
        ws.format('A2:D2', {
            'backgroundColor': {'red': 0.102, 'green': 0.102, 'blue': 0.180},
            'textFormat': {
                'bold': True,
                'foregroundColor': {'red': 1.0, 'green': 1.0, 'blue': 1.0},
            },
        })
        _apply_column_formatting(wb, ws)
        tabs_created.append(TAB_NAME)
        # Position "notes" before "Settings" if Settings already exists
        try:
            all_ws = wb.worksheets()
            settings_idx = next(
                (i for i, w in enumerate(all_ws) if w.title.lower() == 'settings'), None
            )
            if settings_idx is not None:
                ws.update_index(settings_idx)
        except Exception:
            pass  # positioning is cosmetic — ignore failures
    except Exception as e:
        if 'already exists' not in str(e).lower():
            print(json.dumps({"error": f"Could not create 'notes' tab: {str(e)}"}))
            sys.exit(1)

# ── Local paths ───────────────────────────────────────────────────────────────
out_dir    = os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', 'sheets', sheet_id)
index_path = os.path.join(out_dir, 'noteboard-index.json')
os.makedirs(out_dir, exist_ok=True)

# ── Load or create noteboard-index.json ──────────────────────────────────────
if os.path.exists(index_path):
    with open(index_path, 'r', encoding='utf-8') as f:
        nb_index = json.load(f) or {}
else:
    nb_index = {}

# Register "notes" topic if not already present
topic_name = 'notes'
existing_hash = next((h for h, n in nb_index.items() if n == topic_name), None)

if existing_hash:
    notes_hash = existing_hash
else:
    notes_hash = hashlib.md5(topic_name.encode('utf-8')).hexdigest()[:12]
    i = 12
    while notes_hash in nb_index and i <= 32:
        i += 1
        notes_hash = hashlib.md5(topic_name.encode('utf-8')).hexdigest()[:i]
    nb_index[notes_hash] = topic_name
    with open(index_path, 'w', encoding='utf-8') as f:
        json.dump(nb_index, f, ensure_ascii=False)

# ── Write empty local cache if not present ────────────────────────────────────
cache_path = os.path.join(out_dir, f'notes-{topic_name}-en.json')
if not os.path.exists(cache_path):
    with open(cache_path, 'w', encoding='utf-8') as f:
        json.dump({'topic': topic_name, 'notes': []}, f)

print(json.dumps({
    "ok":           True,
    "hash":         notes_hash,
    "tabs_created": tabs_created,
}))
