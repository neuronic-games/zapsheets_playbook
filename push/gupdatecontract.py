# gupdatecontract.py — update a contract row in the contracts sheet by Ref Number
# Arg: {sheet_id}|{base64_encoded_json}
# JSON keys: contract_id, game, client, target_start_date, target_end_date,
#            start_date, end_date, quote, printing, payment, notes,
#            discount_label, discount_pct
# Printing / Discount Label / Discount % columns are added at the end of the
# header row when a value needs them and they don't exist yet.
# Returns { ok, row, record } — record is the full updated row (header → value).

import gspread
import sys, os, json, base64
from gspread.utils import rowcol_to_a1

credFileName = os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', 'credentials.json')

if not os.path.exists(credFileName):
    print(json.dumps({"error": "credentials.json not found"}))
    sys.exit(1)

try:
    sa = gspread.service_account(filename=credFileName)
except Exception as e:
    print(json.dumps({"error": f"Could not authenticate: {str(e)}"}))
    sys.exit(1)

arg      = sys.argv[1]
pipe_idx = arg.index('|')
sheet_id = arg[:pipe_idx]
data     = json.loads(base64.b64decode(arg[pipe_idx + 1:]).decode('utf-8'))

contract_id       = str(data.get('contract_id',       '')).strip()
game              = data.get('game',              '').strip()
client            = data.get('client',            '').strip()
target_start_date = data.get('target_start_date', '').strip()
target_end_date   = data.get('target_end_date',   '').strip()
start_date        = data.get('start_date',        '').strip()
end_date          = data.get('end_date',          '').strip()
quote             = data.get('quote',             '').strip()
printing          = str(data.get('printing', '')).strip()
discount_label    = str(data.get('discount_label', '')).strip()
discount_pct      = str(data.get('discount_pct',   '')).strip()
try:
    if float(discount_pct or 0) == 0: discount_pct = ''
except ValueError:
    discount_pct = ''
payment           = data.get('payment',           '').strip()
notes             = data.get('notes',             '').strip()

if not contract_id:
    print(json.dumps({"error": "contract_id is required"}))
    sys.exit(1)

try:
    wb = sa.open_by_key(sheet_id)
except Exception as e:
    print(json.dumps({"error": f"Could not open spreadsheet: {str(e)}"}))
    sys.exit(1)

# Find contracts worksheet (case-insensitive)
all_ws = wb.worksheets()
ws = next((w for w in all_ws if w.title.lower() == 'contracts'), None)
if not ws:
    print(json.dumps({"error": "contracts sheet not found"}))
    sys.exit(1)

# Read all values to find the row
records = ws.get_all_values()
if not records:
    print(json.dumps({"error": "contracts sheet is empty"}))
    sys.exit(1)

headers = [h.strip() for h in records[0]]

# Map header names to column indices
def col(name):
    try:
        return headers.index(name)
    except ValueError:
        return -1

id_col = col('Ref Number')
if id_col < 0:
    print(json.dumps({"error": "Ref Number column not found in contracts sheet"}))
    sys.exit(1)

# Find the row matching contract_id (Ref Number value)
row_idx = None
for i, row in enumerate(records[1:], start=2):  # 1-based, row 1 is header
    if str(row[id_col]).strip() == contract_id:
        row_idx = i
        break

if row_idx is None:
    print(json.dumps({"error": f"Contract Ref Number {contract_id} not found"}))
    sys.exit(1)

# Add Printing / Discount headers when a value needs them and they're missing
for _name, _value in (('Printing', printing), ('Discount Label', discount_label), ('Discount %', discount_pct)):
    if not _value or col(_name) >= 0:
        continue
    try:
        new_idx = len(headers)
        if ws.col_count < new_idx + 1:
            ws.add_cols(new_idx + 1 - ws.col_count)
        ws.update_cell(1, new_idx + 1, _name)
        headers.append(_name)
    except Exception as e:
        print(json.dumps({"error": f"Could not add {_name} column: {str(e)}"}))
        sys.exit(1)

# Build update map: column name → value
FIELD_MAP = {
    'Game':              game,
    'Client':            client,
    'Target Start Date': target_start_date,
    'Target End Date':   target_end_date,
    'Start Date':        start_date,
    'End Date':          end_date,
    'Quote':             quote,
    'Printing':          printing,
    'Discount Label':    discount_label,
    'Discount %':        discount_pct,
    'Payment':           payment,
    'Notes':             notes,
}

try:
    updates = []
    for field, value in FIELD_MAP.items():
        c_idx = col(field)
        if c_idx >= 0:
            cell = rowcol_to_a1(row_idx, c_idx + 1)   # works past column Z
            updates.append({'range': cell, 'values': [[value]]})
    if updates:
        ws.batch_update(updates, value_input_option='USER_ENTERED')
    # Full row after the update, so the caller can regenerate the PDF
    old_row = records[row_idx - 1]
    record  = {h: (old_row[i] if i < len(old_row) else '') for i, h in enumerate(headers) if h}
    for field, value in FIELD_MAP.items():
        if col(field) >= 0:
            record[field] = value
    print(json.dumps({"ok": True, "row": row_idx, "record": record}))
except Exception as e:
    print(json.dumps({"error": f"Update failed: {str(e)}"}))
    sys.exit(1)
