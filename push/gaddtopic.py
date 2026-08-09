# gaddtopic.py — add a new NoteBoard topic (game).
#
# Arg:  "{sheet_id}|{base64_encoded_json}"
# JSON: { "name": "Topic Name" }
#
# 1. Appends the name to the Games tab (col A).
# 2. Creates "[{name}] notes" tab with formatted, frozen headers.
# 3. Updates sheets/{sheet_id}/noteboard-index.json with hash → name.
#
# Returns: {"ok": true, "name": "...", "hash": "..."} or {"error": "..."}

import gspread
import sys, os, json, base64, hashlib

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

sheet_id   = arg[:pipe_idx].strip()
data       = json.loads(base64.b64decode(arg[pipe_idx + 1:]).decode('utf-8'))
topic_name = data.get('name', '').strip()

if not sheet_id or not topic_name:
    print(json.dumps({"error": "sheet_id and name are required"}))
    sys.exit(1)

try:
    wb = sa.open_by_key(sheet_id)
except Exception as e:
    print(json.dumps({"error": f"Could not open spreadsheet: {str(e)}"}))
    sys.exit(1)

ws_map = {w.title: w for w in wb.worksheets()}

# ── Append to Games tab ───────────────────────────────────────────────────────
games_ws = ws_map.get('Games') or next(
    (ws for t, ws in ws_map.items() if t.lower() == 'games'), None
)
if not games_ws:
    print(json.dumps({"error": "Games tab not found — run NoteBoard setup first"}))
    sys.exit(1)

# Check the topic doesn't already exist (case-insensitive)
existing_names = [r[0].strip() for r in games_ws.get_all_values()[1:] if r and r[0].strip()]
if any(n.lower() == topic_name.lower() for n in existing_names):
    print(json.dumps({"error": f"Topic '{topic_name}' already exists"}))
    sys.exit(1)

games_ws.append_row([topic_name], value_input_option='USER_ENTERED')

# ── Create [topic] notes tab ──────────────────────────────────────────────────
HEADERS  = ['Date', 'Name', 'Email', 'Note']
tab_name = f'[{topic_name}] notes'

if tab_name not in ws_map:
    try:
        notes_ws = wb.add_worksheet(title=tab_name, rows=100, cols=4)
        notes_ws.append_row(HEADERS, value_input_option='USER_ENTERED')
        notes_ws.freeze(rows=1)
        notes_ws.format('A1:D1', {
            'backgroundColor': {'red': 0.627, 'green': 0.424, 'blue': 0.024},
            'textFormat': {
                'bold': True,
                'foregroundColor': {'red': 1.0, 'green': 1.0, 'blue': 1.0},
            },
            'horizontalAlignment': 'LEFT',
        })
    except Exception as e:
        if 'already exists' not in str(e).lower():
            print(json.dumps({"error": f"Could not create notes tab: {str(e)}"}))
            sys.exit(1)

# ── Update noteboard-index.json ───────────────────────────────────────────────
hash_val = hashlib.md5(topic_name.encode('utf-8')).hexdigest()[:12]
out_dir  = os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', 'sheets', sheet_id)
idx_path = os.path.join(out_dir, 'noteboard-index.json')

try:
    os.makedirs(out_dir, exist_ok=True)
    index = {}
    if os.path.exists(idx_path):
        index = json.loads(open(idx_path, encoding='utf-8').read()) or {}
    index[hash_val] = topic_name
    with open(idx_path, 'w', encoding='utf-8') as f:
        json.dump(index, f, ensure_ascii=False)
except Exception as e:
    print(json.dumps({"error": f"Could not update noteboard-index.json: {str(e)}"}))
    sys.exit(1)

# Write an empty notes JSON so the list view shows the card immediately
safe_name  = topic_name.replace('/', '-').replace('\\', '-')
notes_path = os.path.join(out_dir, f'notes-{safe_name}-en.json')
if not os.path.exists(notes_path):
    try:
        with open(notes_path, 'w', encoding='utf-8') as f:
            json.dump([], f)
    except Exception:
        pass  # non-fatal

print(json.dumps({"ok": True, "name": topic_name, "hash": hash_val}))
