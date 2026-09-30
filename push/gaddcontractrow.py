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
import sys, os, json, base64, secrets

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

id_col       = col('ID')
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

# Determine next sequential ID
next_id = 1
if id_col >= 0:
    for row in existing[1:]:
        cell = str(row[id_col]).strip() if id_col < len(row) else ''
        try:
            next_id = max(next_id, int(float(cell)) + 1)
        except (ValueError, TypeError):
            pass

# Generate a unique random Ref Number and persist it to a registry file
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

ref_code = ''
for _ in range(30):
    candidate = secrets.token_hex(6)   # 12 lowercase hex chars
    if candidate not in registry:
        ref_code = candidate
        break
if not ref_code:
    ref_code = secrets.token_hex(16)   # extreme fallback (32 chars)

registry.append(ref_code)
os.makedirs(os.path.dirname(reg_path), exist_ok=True)
try:
    with open(reg_path, 'w', encoding='utf-8') as _f:
        json.dump(registry, _f)
except Exception:
    pass   # non-fatal; sheet still gets the code

# Build new row matching the header width
num_cols = len(headers)
new_row  = [''] * num_cols
if id_col       >= 0: new_row[id_col]       = str(next_id)
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

# Re-read to get the appended row (and any formula-computed ID)
try:
    all_values = ws.get_all_values()  # FORMATTED_VALUE by default
except Exception as e:
    print(json.dumps({"error": f"Could not re-read after append: {str(e)}"}))
    sys.exit(1)

new_row_idx  = len(all_values)       # 1-based sheet row index of new row
last_row     = all_values[-1] if all_values else []

# Get the ID from the sheet (formula result) or fall back to row index
contract_id = ''
if id_col >= 0 and id_col < len(last_row):
    contract_id = str(last_row[id_col]).strip()
if not contract_id:
    contract_id = str(next_id)

print(json.dumps({"ok": True, "contract_id": contract_id, "row": new_row_idx, "ref_code": ref_code}))
