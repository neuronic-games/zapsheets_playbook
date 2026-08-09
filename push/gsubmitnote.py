# gsubmitnote.py — append a feedback note to a game's notes worksheet
#                  and refresh the local notes cache.
#
# Arg:  "{sheet_id}|{base64_encoded_json}"
# JSON: { "game": "Game Name", "name": "Reviewer", "email": "r@x.com", "note": "..." }
#
# Creates "[{game}] notes" tab with headers (Date|Name|Email|Note) if it doesn't exist.
# Appends a row, then writes sheets/{sheet_id}/notes-{safe_name}-en.json so the
# dashboard can show a "View Notes" button.
#
# Returns: {"ok": true} or {"error": "..."}

import gspread
import sys, os, json, base64
from datetime import datetime, timezone

credFileName = os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', 'credentials.json')
if not os.path.exists(credFileName):
    print(json.dumps({"error": "credentials.json not found"}))
    sys.exit(1)

try:
    sa = gspread.service_account(filename=credFileName)
except Exception as e:
    print(json.dumps({"error": f"Could not authenticate: {str(e)}"}))
    sys.exit(1)

arg      = sys.argv[1] if len(sys.argv) > 1 else ''
pipe_idx = arg.find('|')
if pipe_idx < 0:
    print(json.dumps({"error": "invalid argument — expected {sheet_id}|{base64_json}"}))
    sys.exit(1)

sheet_id = arg[:pipe_idx].strip()
data     = json.loads(base64.b64decode(arg[pipe_idx + 1:]).decode('utf-8'))

game_name = data.get('game', '').strip()
name      = data.get('name', '').strip()
email     = data.get('email', '').strip()
note      = data.get('note', '').strip()

if not sheet_id or not game_name or not note:
    print(json.dumps({"error": "sheet_id, game, and note are required"}))
    sys.exit(1)

try:
    wb = sa.open_by_key(sheet_id)
except Exception as e:
    print(json.dumps({"error": f"Could not open spreadsheet: {str(e)}"}))
    sys.exit(1)

tab_name = f'[{game_name}] notes'
all_ws   = {w.title: w for w in wb.worksheets()}
HEADERS  = ['Date', 'Name', 'Email', 'Note']

if tab_name in all_ws:
    ws = all_ws[tab_name]
else:
    try:
        ws = wb.add_worksheet(title=tab_name, rows=100, cols=4)
        ws.append_row(HEADERS, value_input_option='USER_ENTERED')
        # Format and freeze the header row
        ws.freeze(rows=1)
        ws.format('A1:D1', {
            'backgroundColor': {'red': 0.627, 'green': 0.424, 'blue': 0.024},
            'textFormat': {
                'bold': True,
                'foregroundColor': {'red': 1.0, 'green': 1.0, 'blue': 1.0},
            },
            'horizontalAlignment': 'LEFT',
        })
    except Exception as e:
        if 'already exists' in str(e).lower():
            ws = next((w for w in wb.worksheets() if w.title == tab_name), None)
            if not ws:
                print(json.dumps({"error": f"Could not find or create tab: {str(e)}"}))
                sys.exit(1)
        else:
            print(json.dumps({"error": f"Could not create tab: {str(e)}"}))
            sys.exit(1)

date_str = datetime.now(timezone.utc).strftime('%Y-%m-%d')

try:
    ws.append_row([date_str, name, email, note], value_input_option='USER_ENTERED')
except Exception as e:
    print(json.dumps({"error": f"Could not append note: {str(e)}"}))
    sys.exit(1)

# ── Refresh local notes cache ─────────────────────────────────────────────────
try:
    rows = ws.get_all_values()
    # Skip header row; build list of note dicts
    notes = []
    for row in rows[1:]:
        while len(row) < 4:
            row.append('')
        d, n, e, nt = row[0], row[1], row[2], row[3]
        if nt.strip():   # skip blank note rows
            notes.append({'date': d, 'name': n, 'email': e, 'note': nt})

    safe_name = game_name.replace('/', '-').replace('\\', '-')
    out_dir   = os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', 'sheets', sheet_id)
    os.makedirs(out_dir, exist_ok=True)
    cache_path = os.path.join(out_dir, f'notes-{safe_name}-en.json')
    with open(cache_path, 'w', encoding='utf-8') as f:
        json.dump(notes, f, ensure_ascii=False)
except Exception:
    pass  # cache write failure is non-fatal; note was already saved to the sheet

print(json.dumps({"ok": True}))
