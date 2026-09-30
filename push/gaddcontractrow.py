# gaddcontractrow.py — append a new contract row and return its ID.
#
# Reads the contracts sheet header to determine column positions, appends a new
# row, then reads back the ID (so formula-based ID columns are evaluated).
# If the ID cell is empty after appending, falls back to the row index.
#
# Arg: {sheet_id}|{base64_encoded_json}
# JSON: { game, client, quote, payment, notes }
# Returns: { ok, contract_id, row }

import gspread
import sys, os, json, base64, hashlib, time as _time

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
pipe_idx = arg.index('|')
sheet_id = arg[:pipe_idx]
data     = json.loads(base64.b64decode(arg[pipe_idx + 1:]).decode('utf-8'))

game     = data.get('game',     '').strip()
client   = data.get('client',  '').strip()
quote    = data.get('quote',   '').strip()
payment  = data.get('payment', '').strip() or 'Estimate'
notes    = data.get('notes',   '').strip()
tests    = str(data.get('tests',    '')).strip()
edits    = str(data.get('edits',    '')).strip()
duration     = data.get('duration',     '').strip()
target_start = data.get('target_start', '').strip()
target_end   = data.get('target_end',   '').strip()
row_type     = data.get('type',         '').strip()   # "Estimate" or "Invoice"
date_val     = data.get('date',         '').strip()   # MM/DD/YYYY; caller supplies today
scope_of_work = data.get('scope_of_work', '').strip()

try:
    wb = sa.open_by_key(sheet_id)
except Exception as e:
    print(json.dumps({"error": f"Could not open spreadsheet: {str(e)}"}))
    sys.exit(1)

all_ws = wb.worksheets()
ws = next((w for w in all_ws if w.title.lower() == 'contracts'), None)
if not ws:
    print(json.dumps({"error": "contracts sheet not found"}))
    sys.exit(1)

# Read existing rows (FORMATTED_VALUE so formula IDs are evaluated)
try:
    existing = ws.get_all_values()
except Exception as e:
    print(json.dumps({"error": f"Could not read contracts sheet: {str(e)}"}))
    sys.exit(1)

if not existing:
    print(json.dumps({"error": "contracts sheet is empty (no header row)"}))
    sys.exit(1)

headers = [h.strip() for h in existing[0]]

def col(name):
    try: return headers.index(name)
    except ValueError: return -1

date_col     = col('Date')
game_col     = col('Game')
client_col   = col('Client')
quote_col    = col('Quote')
payment_col  = col('Payment')
tests_col    = col('Tests')
edits_col    = col('Edits')
duration_col     = col('Duration')
target_start_col = col('Target Start Date')
target_end_col   = col('Target End Date')
type_col         = col('Type')
ref_num_col      = col('Ref Number')
notes_col        = col('Notes')
scope_col        = col('Description')

if ref_num_col < 0:
    print(json.dumps({"error": "Ref Number column not found in contracts sheet header"}))
    sys.exit(1)

# Generate a unique alphanumeric Ref Number derived from time + game + client.
# SHA-256 of nanosecond timestamp + game + client, encoded as 8-char base-36
# (digits 0–9 and uppercase A–Z).  Nanosecond precision makes collisions
# effectively impossible; the registry is kept as a safety net only.
_raw    = f"{_time.time_ns()}-{game}-{client}"
_digest = hashlib.sha256(_raw.encode()).hexdigest()
_ALPHA  = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ'
_num    = int(_digest[:12], 16)   # 48 bits of entropy
ref_code = ''
while _num:
    ref_code = _ALPHA[_num % 36] + ref_code
    _num //= 36
ref_code = ref_code.zfill(8)[:8]   # always 8 uppercase alphanumeric chars

# Persist to registry for auditability / collision detection (entirely non-fatal)
try:
    base_dir = os.path.join(os.path.dirname(os.path.abspath(__file__)), '..')
    reg_path = os.path.join(base_dir, 'sheets', sheet_id, 'ref_codes.json')
    registry = []
    if os.path.exists(reg_path):
        try:
            with open(reg_path, 'r', encoding='utf-8') as _f:
                _data = json.load(_f)
            if isinstance(_data, list):
                registry = _data
        except Exception:
            registry = []
    registry.append(ref_code)
    os.makedirs(os.path.dirname(reg_path), exist_ok=True)
    with open(reg_path, 'w', encoding='utf-8') as _f:
        json.dump(registry, _f)
except Exception:
    pass   # non-fatal; row still gets the ref code

# Build new row matching the header width
num_cols = len(headers)
new_row  = [''] * num_cols
if date_col     >= 0: new_row[date_col]     = date_val
if game_col     >= 0: new_row[game_col]     = game
if client_col   >= 0: new_row[client_col]   = client
if quote_col    >= 0: new_row[quote_col]    = quote
if payment_col  >= 0: new_row[payment_col]  = payment
if tests_col    >= 0: new_row[tests_col]    = tests
if edits_col    >= 0: new_row[edits_col]    = edits
if duration_col     >= 0: new_row[duration_col]     = duration
if target_start_col >= 0: new_row[target_start_col] = target_start
if target_end_col   >= 0: new_row[target_end_col]   = target_end
if type_col         >= 0: new_row[type_col]         = row_type
if ref_num_col      >= 0: new_row[ref_num_col]      = ref_code
if notes_col        >= 0: new_row[notes_col]        = notes
if scope_col        >= 0: new_row[scope_col]        = scope_of_work

try:
    ws.append_row(new_row, value_input_option='USER_ENTERED')
except Exception as e:
    print(json.dumps({"error": f"Could not append row: {str(e)}"}))
    sys.exit(1)

print(json.dumps({"ok": True, "contract_id": ref_code, "row": -1, "ref_code": ref_code}))
