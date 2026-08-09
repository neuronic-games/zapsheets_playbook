# gaddtopic.py — add a new NoteBoard topic.
#
# Arg:  "{sheet_id}|{base64_encoded_json}"
# JSON: { "name": "Topic Name" }
#
# 1. Checks whether [topic] notes tab already exists in the spreadsheet.
# 2. Creates the tab with a 2-row header (topic row + header row).
# 3. Updates sheets/{sheet_id}/noteboard-index.json with hash → name.
# 4. Writes an empty local cache so the list shows the card immediately.
#
# Returns: {"ok": true, "name": "...", "hash": "..."} or {"error": "..."}

import gspread
import sys, os, json, base64, hashlib, re

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

all_ws  = wb.worksheets()
ws_map  = {w.title: w for w in all_ws}

# ── Check the notes tab doesn't already exist in the spreadsheet ──────────────
tab_name = f'[{topic_name}] notes'
if tab_name in ws_map:
    print(json.dumps({"error": f"Topic '{topic_name}' already exists"}))
    sys.exit(1)

# ── Create [topic] notes tab with 2-row header ────────────────────────────────
HEADERS = ['Date', 'Name', 'Email', 'Note']
try:
    notes_ws = wb.add_worksheet(title=tab_name, rows=100, cols=4)
    notes_ws.update([['Name', topic_name], HEADERS], 'A1',
                    value_input_option='USER_ENTERED')
    notes_ws.freeze(rows=2)
    notes_ws.format('A2:D2', {
        'backgroundColor': {'red': 0.627, 'green': 0.424, 'blue': 0.024},
        'textFormat': {
            'bold': True,
            'foregroundColor': {'red': 1.0, 'green': 1.0, 'blue': 1.0},
        },
        'horizontalAlignment': 'LEFT',
    })
except Exception as e:
    if 'already exists' in str(e).lower():
        print(json.dumps({"error": f"Topic '{topic_name}' already exists"}))
    else:
        print(json.dumps({"error": f"Could not create notes tab: {str(e)}"}))
    sys.exit(1)

# ── Update noteboard-index.json ───────────────────────────────────────────────
out_dir  = os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', 'sheets', sheet_id)
idx_path = os.path.join(out_dir, 'noteboard-index.json')

try:
    os.makedirs(out_dir, exist_ok=True)
    index = {}
    if os.path.exists(idx_path):
        index = json.loads(open(idx_path, encoding='utf-8').read()) or {}

    # Reuse existing hash if this name was registered before
    hash_val = next((h for h, n in index.items() if n == topic_name), None)
    if not hash_val:
        hash_val = hashlib.md5(topic_name.encode('utf-8')).hexdigest()[:12]
        i = 12
        while hash_val in index and i <= 32:
            i += 1
            hash_val = hashlib.md5(topic_name.encode('utf-8')).hexdigest()[:i]

    index[hash_val] = topic_name
    with open(idx_path, 'w', encoding='utf-8') as f:
        json.dump(index, f, ensure_ascii=False)
except Exception as e:
    print(json.dumps({"error": f"Could not update noteboard-index.json: {str(e)}"}))
    sys.exit(1)

# ── Write empty local cache ───────────────────────────────────────────────────
safe_name  = re.sub(r'[/\\]', '-', topic_name)
notes_path = os.path.join(out_dir, f'notes-{safe_name}-en.json')
try:
    with open(notes_path, 'w', encoding='utf-8') as f:
        json.dump({'topic': topic_name, 'notes': []}, f, ensure_ascii=False)
except Exception:
    pass  # non-fatal

print(json.dumps({"ok": True, "name": topic_name, "hash": hash_val}))
