# gadd.py — append a row to a Google Sheet worksheet
# Arg: {sheet_id}|{base64_encoded_json_row}

import gspread
import sys, os, json, base64

credFileName = os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', 'credentials.json')

def safe_str(v):
    """Prefix non-empty strings with ' to prevent Google Sheets formula interpretation."""
    s = (str(v) if v is not None else '').strip()
    return ("'" + s) if s else ''

if not os.path.exists(credFileName):
    print(json.dumps({"error": "credentials.json not found"}))
    sys.exit(1)

mServiceAccount = gspread.service_account(filename=credFileName)

# Parse arg: {sheet_id}|{base64_json}  OR  {sheet_id}|{sheet_name}|{base64_json}
arg   = sys.argv[1]
parts = arg.split('|', 2)
if len(parts) == 3:
    sheet_id, sheet_name, json_b64 = parts
else:
    sheet_id  = parts[0]
    sheet_name = 'pitches'
    json_b64  = parts[1]

row_data = json.loads(base64.b64decode(json_b64).decode('utf-8'))

# Open spreadsheet
try:
    mGoogleSheet = mServiceAccount.open_by_key(sheet_id)
except Exception as e:
    print(json.dumps({"error": f"Could not open spreadsheet: {str(e)}"}))
    sys.exit(1)

# Find the target worksheet (case-insensitive) — no silent fallback
all_worksheets = mGoogleSheet.worksheets()
ws = next((w for w in all_worksheets if w.title.lower() == sheet_name.lower()), None)
if ws is None:
    available = ', '.join(w.title for w in all_worksheets)
    print(json.dumps({"error": f"Worksheet '{sheet_name}' not found. Available tabs: {available}"}))
    sys.exit(1)

# Read all values to get headers and determine next available row
try:
    all_values = ws.get_all_values()
except Exception as e:
    print(json.dumps({"error": f"Could not read sheet: {str(e)}"}))
    sys.exit(1)

# Treat the sheet as empty if get_all_values() returns [] or only blank rows
_non_blank = [r for r in all_values if any(c.strip() for c in r)]
if not _non_blank:
    print(json.dumps({"error": "Sheet is empty — no header row found. Run setup first."}))
    sys.exit(1)

headers = all_values[0]

if not headers:
    print(json.dumps({"error": "Header row is empty"}))
    sys.exit(1)

# Build row list aligned to headers
new_row = [safe_str(row_data.get(h.strip(), '')) for h in headers]

# Delegate row-finding to the Sheets API via append_rows (OVERWRITE mode avoids
# INSERT_ROWS which can fail on protected sheets). The API appends after the last
# row that contains data in the table starting at A1.
try:
    ws.append_rows(
        [new_row],
        value_input_option='USER_ENTERED',
        insert_data_option='OVERWRITE',
        table_range='A1',
    )
    approx_row = len(all_values) + 1
except Exception as e:
    print(json.dumps({"error": f"Could not write row: {str(e)}"}))
    sys.exit(1)

non_empty = sum(1 for v in new_row if v)

# For the Pitches tab, extend dropdown validation on Game / Publisher / Contact
# to always cover the newly added row plus a buffer ahead of it.
if sheet_name.lower() == 'pitches':
    cover_to = approx_row + 500   # cover 500 rows beyond the new one
    # Each tuple: (col_index, condition_type, condition_values_list)
    PITCH_STATUS_VALUES = [
        'Pitched', 'Interested', 'Passed', 'Gone Cold', 'Signed', 'Published', 'Returned',
    ]
    # Pitches columns: Date(0), Game(1), Publisher(2), Contact(3), Event(4), Status(5)
    validation_cols = [
        (1, 'ONE_OF_RANGE', [{'userEnteredValue': '=Games!$A$2:$A$10000'}]),
        (2, 'ONE_OF_RANGE', [{'userEnteredValue': '=People!$C$2:$C$10000'}]),
        (3, 'ONE_OF_RANGE', [{'userEnteredValue': '=People!$A$2:$A$10000'}]),
        (5, 'ONE_OF_LIST',  [{'userEnteredValue': v} for v in PITCH_STATUS_VALUES]),
    ]
    # Clear any stale validation on the Date column (col 0).
    # Omitting 'rule' tells the Sheets API to delete the rule.
    vreqs = [
        {
            'setDataValidation': {
                'range': {
                    'sheetId': ws.id,
                    'startRowIndex': 1,
                    'endRowIndex': 10000,
                    'startColumnIndex': 0,
                    'endColumnIndex': 1,
                }
                # No 'rule' key = clears validation
            }
        }
    ]
    for col_idx, cond_type, cond_values in validation_cols:
        vreqs.append({
            'setDataValidation': {
                'range': {
                    'sheetId': ws.id,
                    'startRowIndex': 1,        # 0-indexed: skip header
                    'endRowIndex': cover_to,
                    'startColumnIndex': col_idx,
                    'endColumnIndex': col_idx + 1,
                },
                'rule': {
                    'condition': {
                        'type': cond_type,
                        'values': cond_values,
                    },
                    'showCustomUi': True,   # render as dropdown
                    'strict': False,        # warn but don't block free-text
                },
            }
        })
    try:
        mGoogleSheet.batch_update({'requests': vreqs})
    except Exception:
        pass  # non-fatal: validation is a nice-to-have

# For the Games tab, extend dropdown validation on Designer1–4 → People.Name.
elif sheet_name.lower() == 'games':
    cover_to = approx_row + 500
    designer_indices = [
        i for i, h in enumerate(headers)
        if h.strip().lower().startswith('designer')
    ]
    vreqs = []
    for col_idx in designer_indices:
        vreqs.append({
            'setDataValidation': {
                'range': {
                    'sheetId': ws.id,
                    'startRowIndex': 1,
                    'endRowIndex': cover_to,
                    'startColumnIndex': col_idx,
                    'endColumnIndex': col_idx + 1,
                },
                'rule': {
                    'condition': {
                        'type': 'ONE_OF_RANGE',
                        'values': [{'userEnteredValue': '=People!$A$2:$A$10000'}],
                    },
                    'showCustomUi': True,
                    'strict': False,
                },
            }
        })
    try:
        mGoogleSheet.batch_update({'requests': vreqs})
    except Exception:
        pass  # non-fatal

print(json.dumps({
    "ok": True,
    "sheet": ws.title,
    "row": approx_row,
    "headers": headers,
    "written": new_row,
    "non_empty_fields": non_empty
}))
