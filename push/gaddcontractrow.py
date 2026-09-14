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
import sys, os, json, base64

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

game    = data.get('game',    '').strip()
client  = data.get('client',  '').strip()
quote   = data.get('quote',   '').strip()
payment = data.get('payment', '').strip() or 'Estimate'
notes   = data.get('notes',   '').strip()

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

id_col      = col('ID')
game_col    = col('Game')
client_col  = col('Client')
quote_col   = col('Quote')
payment_col = col('Payment')
notes_col   = col('Notes')

# Build new row matching the header width
num_cols = len(headers)
new_row  = [''] * num_cols
if game_col    >= 0: new_row[game_col]    = game
if client_col  >= 0: new_row[client_col]  = client
if quote_col   >= 0: new_row[quote_col]   = quote
if payment_col >= 0: new_row[payment_col] = payment
if notes_col   >= 0: new_row[notes_col]   = notes
# Leave ID blank — let any existing formula in the sheet fill it

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
    # Fallback: use the 1-based row index (row 2 = first data row → ID 1)
    contract_id = str(new_row_idx - 1)

print(json.dumps({"ok": True, "contract_id": contract_id, "row": new_row_idx}))
