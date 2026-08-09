# ginitnoteboard.py — initialise NoteBoard for a Google Spreadsheet.
#
# Works with both new sheets and existing PitchBoard sheets:
#   - New sheet: creates "Games" and "Settings" tabs if missing.
#   - Existing sheet: reads game names from the "Games" tab (col A, rows 2+).
#
# Writes sheets/{sheet_id}/noteboard-index.json  →  { hash: gameName, … }
# so that the public feedback form can resolve /{id}/noteboard/{hash} → game name.
#
# Does NOT create per-game notes tabs — those are created on first note submission.
#
# Argument:  "{sheet_id}"
# Returns:   {"ok": true, "games": [...], "tabs_created": [...]}
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


def ensure_tab(wb, ws_map, title, rows, freeze_row=False):
    """Return (worksheet, 'ok'|'created'). Handles stale cache / already-exists errors."""
    if title in ws_map:
        return ws_map[title], 'ok'
    for k, ws in ws_map.items():
        if k.lower() == title.lower():
            ws_map[title] = ws
            return ws, 'ok'
    try:
        num_cols = max(len(r) for r in rows) if rows else 2
        ws = wb.add_worksheet(title=title, rows=200, cols=num_cols)
        if rows:
            ws.update(rows, 'A1', value_input_option='RAW')
        if freeze_row:
            ws.freeze(rows=1)
        ws_map[title] = ws
        return ws, 'created'
    except Exception as e:
        if 'already exists' in str(e).lower():
            fresh = refresh_ws_map()
            ws_map.update(fresh)
            for k, ws in fresh.items():
                if k == title or k.lower() == title.lower():
                    ws_map[title] = ws
                    return ws, 'ok'
        raise


ws_map       = refresh_ws_map()
tabs_created = []

# ── Ensure base tabs exist (no-op on existing PitchBoard sheets) ──────────────
SETTINGS_ROWS = [['My Name', ''], ['My Email', '']]
GAMES_HEADERS = ['Name', 'Tagline', 'Status', 'Designer1', 'Designer2', 'Description']

for tab_name, rows, freeze_row in [
    ('Settings', SETTINGS_ROWS, False),
    ('Games',    [GAMES_HEADERS], True),
]:
    try:
        _, status = ensure_tab(wb, ws_map, tab_name, rows, freeze_row)
        if status == 'created':
            tabs_created.append(tab_name)
    except Exception as e:
        print(json.dumps({"error": f"Could not set up {tab_name} tab: {str(e)}"}))
        sys.exit(1)

# ── Read game names from the Games tab ───────────────────────────────────────
games_ws   = ws_map.get('Games') or next(
    (ws for t, ws in ws_map.items() if t.lower() == 'games'), None
)
all_values = games_ws.get_all_values() if games_ws else []
game_names = [row[0].strip() for row in all_values[1:] if row and row[0].strip()]

# ── Build noteboard-index.json (hash → game name) ────────────────────────────
# Per-game notes tabs are NOT created here; they are created on first submission.
noteboard_index = {
    hashlib.md5(name.encode('utf-8')).hexdigest()[:12]: name
    for name in game_names
}

out_dir    = os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', 'sheets', sheet_id)
index_path = os.path.join(out_dir, 'noteboard-index.json')
try:
    os.makedirs(out_dir, exist_ok=True)
    with open(index_path, 'w', encoding='utf-8') as f:
        json.dump(noteboard_index, f, ensure_ascii=False)
except Exception as e:
    print(json.dumps({"error": f"Could not write noteboard-index.json: {str(e)}"}))
    sys.exit(1)

games_list = [
    {'name': name, 'hash': hashlib.md5(name.encode('utf-8')).hexdigest()[:12]}
    for name in game_names
]

print(json.dumps({
    "ok":           True,
    "tabs_created": tabs_created,
    "games":        games_list,
}))
