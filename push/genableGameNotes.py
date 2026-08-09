# genableGameNotes.py — enable notes collection for a single game.
#
# Args: "{sheet_id}" "{game_name}"
#
# 1. Registers the game in noteboard-index.json (no-op if already present).
# 2. Creates the "[{game}] notes" tab in Google Sheets with a formatted header.
# 3. Writes an empty local notes cache (notes-{safe}-en.json) so the dashboard
#    immediately shows "View Notes" instead of "Enable Notes".
#
# Returns JSON to stdout:
#   {"ok": true,  "hash": "…", "logs": [{"msg": "…", "type": "ok|info|error"}, …]}
#   {"error": "…", "logs": […]}

import gspread
import sys, os, json, hashlib

cred_path = os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', 'credentials.json')
if not os.path.exists(cred_path):
    print(json.dumps({"error": "credentials.json not found", "logs": []}))
    sys.exit(1)

try:
    sa = gspread.service_account(filename=cred_path)
except Exception as e:
    print(json.dumps({"error": f"Auth failed: {e}", "logs": []}))
    sys.exit(1)

sheet_id  = (sys.argv[1] if len(sys.argv) > 1 else '').strip()
game_name = (sys.argv[2] if len(sys.argv) > 2 else '').strip()

if not sheet_id or not game_name:
    print(json.dumps({"error": "sheet_id and game_name arguments required", "logs": []}))
    sys.exit(1)

logs = []
def log(msg, t='info'):
    logs.append({'msg': msg, 'type': t})

# ── Connect ───────────────────────────────────────────────────────────────────
log('Connecting to spreadsheet…')
try:
    wb = sa.open_by_key(sheet_id)
except Exception as e:
    print(json.dumps({"error": f"Could not open spreadsheet: {e}", "logs": logs}))
    sys.exit(1)
log('Connected.', 'ok')

# ── Local paths ───────────────────────────────────────────────────────────────
out_dir    = os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', 'sheets', sheet_id)
index_path = os.path.join(out_dir, 'noteboard-index.json')
os.makedirs(out_dir, exist_ok=True)

# ── Noteboard index ───────────────────────────────────────────────────────────
if os.path.exists(index_path):
    with open(index_path, 'r', encoding='utf-8') as f:
        index = json.load(f)
else:
    index = {}

existing_hash = next((h for h, n in index.items() if n == game_name), None)
if existing_hash:
    game_hash = existing_hash
    log(f'Game already registered (hash: {game_hash}).', 'info')
else:
    game_hash = hashlib.md5(game_name.encode('utf-8')).hexdigest()[:12]
    i = 12
    while game_hash in index and i <= 32:
        i += 1
        game_hash = hashlib.md5(game_name.encode('utf-8')).hexdigest()[:i]
    index[game_hash] = game_name
    log(f'Assigned hash {game_hash}.', 'ok')
    try:
        with open(index_path, 'w', encoding='utf-8') as f:
            json.dump(index, f, ensure_ascii=False)
        log('Saved noteboard-index.json.', 'ok')
    except Exception as e:
        print(json.dumps({"error": f"Could not write index: {e}", "logs": logs}))
        sys.exit(1)

# ── Create notes tab in Google Sheets ────────────────────────────────────────
tab_name = f'[{game_name}] notes'
log(f'Checking for "{tab_name}" tab…')
all_ws  = {w.title: w for w in wb.worksheets()}
HEADERS = ['Date', 'Name', 'Email', 'Note']

if tab_name in all_ws:
    log('Tab already exists.', 'info')
else:
    log(f'Creating tab…')
    try:
        ws = wb.add_worksheet(title=tab_name, rows=100, cols=4)
        ws.append_row(HEADERS, value_input_option='USER_ENTERED')
        ws.freeze(rows=1)
        ws.format('A1:D1', {
            'backgroundColor': {'red': 0.627, 'green': 0.424, 'blue': 0.024},
            'textFormat': {
                'bold': True,
                'foregroundColor': {'red': 1.0, 'green': 1.0, 'blue': 1.0},
            },
        })
        log('Tab created.', 'ok')
    except Exception as e:
        if 'already exists' in str(e).lower():
            log('Tab already exists.', 'info')
        else:
            print(json.dumps({"error": f"Could not create tab: {e}", "logs": logs}))
            sys.exit(1)

# ── Write empty local notes cache ────────────────────────────────────────────
safe_name  = game_name.replace('/', '-').replace('\\', '-')
cache_path = os.path.join(out_dir, f'notes-{safe_name}-en.json')
if not os.path.exists(cache_path):
    try:
        with open(cache_path, 'w', encoding='utf-8') as f:
            json.dump([], f)
        log('Created local notes cache.', 'ok')
    except Exception as e:
        log(f'Warning: could not write cache ({e}).', 'error')
else:
    log('Local notes cache already exists.', 'info')

log('Done!', 'ok')
print(json.dumps({"ok": True, "hash": game_hash, "logs": logs}))
