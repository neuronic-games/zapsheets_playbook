# gupdatebio.py — update or insert a row in the bios sheet
# Bios columns: Email | Image | Description | Skills | Location | Phone | Discord
# Row is matched by Email. If no match, a new row is appended.
#
# Arg: {sheet_id}|{base64_encoded_json}
# JSON: { email, image, description, skills, location, phone, discord }

import gspread
import sys, os, json, base64

credFileName = os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', 'credentials.json')

def safe_str(v):
    """Prefix non-empty strings with ' to prevent Google Sheets formula interpretation.
    Exception: =IMAGE() formulas are passed through."""
    s = (str(v) if v is not None else '').strip()
    if not s:
        return ''
    if s.upper().startswith('=IMAGE('):
        return s
    return "'" + s

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

email       = data.get('email',       '').strip()
image       = data.get('image',       '').strip()
description = data.get('description', '').strip()
skills      = data.get('skills',      '').strip()
location    = data.get('location',    '').strip()
phone       = data.get('phone',       '').strip()
discord     = data.get('discord',     '').strip()
payment     = data.get('payment',     '').strip()
notes       = data.get('notes',       '').strip()

if not email:
    print(json.dumps({"error": "Email is required"}))
    sys.exit(1)

# Wrap image URL in =IMAGE() formula
if image and not image.upper().startswith('=IMAGE('):
    image = f'=IMAGE("{image}")'

try:
    wb = sa.open_by_key(sheet_id)
except Exception as e:
    print(json.dumps({"error": f"Could not open spreadsheet: {str(e)}"}))
    sys.exit(1)

all_ws = wb.worksheets()
ws = next((w for w in all_ws if w.title.lower() == 'bios'), None)
if ws is None:
    print(json.dumps({"error": "Bios worksheet not found"}))
    sys.exit(1)

try:
    all_values = ws.get_all_values()
except Exception as e:
    print(json.dumps({"error": f"Could not read bios sheet: {str(e)}"}))
    sys.exit(1)

if not all_values:
    print(json.dumps({"error": "Bios sheet is empty"}))
    sys.exit(1)

headers = all_values[0]
col = {h.strip(): i for i, h in enumerate(headers)}

def find_col(*variants):
    for v in variants:
        if v in col:
            return col[v]
    return -1

email_col = find_col('Email')
if email_col < 0:
    print(json.dumps({"error": "No 'Email' column found in bios sheet"}))
    sys.exit(1)

# Find existing row for this email (strip leading apostrophe from safe_str)
target_row = None
for i, row in enumerate(all_values[1:], start=2):
    cell_email = row[email_col].lstrip("'").strip() if email_col < len(row) else ''
    if cell_email.lower() == email.lower():
        target_row = i
        break

# Map of (column_variants, value) for writing
field_map = [
    (('Email',),       email),
    (('Image',),       image),          # =IMAGE("url") or ''
    (('Description',), safe_str(description)),
    (('Skills',),      safe_str(skills)),
    (('Location',),    safe_str(location)),
    (('Phone',),       safe_str(phone)),
    (('Discord',),     safe_str(discord)),
    (('Payment',),     safe_str(payment)),
    (('Notes',),       safe_str(notes)),
]

def resize_row(ws, row_1based, height_px=120):
    """Set the pixel height of a single sheet row."""
    try:
        ws.spreadsheet.batch_update({
            "requests": [{
                "updateDimensionProperties": {
                    "range": {
                        "sheetId":    ws.id,
                        "dimension":  "ROWS",
                        "startIndex": row_1based - 1,  # 0-based
                        "endIndex":   row_1based,
                    },
                    "properties": {"pixelSize": height_px},
                    "fields": "pixelSize",
                }
            }]
        })
    except Exception:
        pass  # row resize is best-effort; don't fail the whole update

if target_row is not None:
    updates = []
    for variants, value in field_map:
        idx = find_col(*variants)
        if idx >= 0:
            updates.append({
                'range':  gspread.utils.rowcol_to_a1(target_row, idx + 1),
                'values': [[value]]
            })
    try:
        ws.batch_update(updates, value_input_option='USER_ENTERED')
        if image:
            resize_row(ws, target_row)
        print(json.dumps({"ok": True, "row": target_row, "action": "updated"}))
    except Exception as e:
        print(json.dumps({"error": f"Could not update row: {str(e)}"}))
        sys.exit(1)
else:
    # Append new row in header column order
    new_row = []
    for h in headers:
        h_strip = h.strip()
        if h_strip == 'Email':         new_row.append(email)
        elif h_strip == 'Image':       new_row.append(image)
        elif h_strip == 'Description': new_row.append(safe_str(description))
        elif h_strip == 'Skills':      new_row.append(safe_str(skills))
        elif h_strip == 'Location':    new_row.append(safe_str(location))
        elif h_strip == 'Phone':       new_row.append(safe_str(phone))
        elif h_strip == 'Discord':     new_row.append(safe_str(discord))
        elif h_strip == 'Payment':     new_row.append(safe_str(payment))
        elif h_strip == 'Notes':       new_row.append(safe_str(notes))
        else:                          new_row.append('')
    try:
        new_row_index = len(all_values) + 1  # 1-based index of the appended row
        ws.append_row(new_row, value_input_option='USER_ENTERED')
        if image:
            resize_row(ws, new_row_index)
        print(json.dumps({"ok": True, "action": "inserted"}))
    except Exception as e:
        print(json.dumps({"error": f"Could not insert row: {str(e)}"}))
        sys.exit(1)
