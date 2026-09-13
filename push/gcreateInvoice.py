# gcreateInvoice.py — create a formatted invoice tab in a Google Sheet.
#
# Arg: {sheet_id}|{base64_json}
# JSON fields: sheet_id, game, client, quote, payment, status,
#              tgt_start, tgt_end, start_date, end_date, notes,
#              my_name, my_phone, my_company, my_logo, my_address

import gspread
import sys, os, json, base64, socket, re
from datetime import datetime

socket.setdefaulttimeout(30)

credFileName = os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', 'credentials.json')

if not os.path.exists(credFileName):
    print(json.dumps({"error": "credentials.json not found"}))
    sys.exit(1)

try:
    sa = gspread.service_account(filename=credFileName)
except Exception as e:
    print(json.dumps({"error": f"Could not authenticate: {str(e)}"}))
    sys.exit(1)

raw = sys.argv[1] if len(sys.argv) > 1 else ''
parts = raw.split('|', 1)
if len(parts) < 2:
    print(json.dumps({"error": "Expected argument: sheet_id|base64_json"}))
    sys.exit(1)

sheet_id = parts[0].strip()
try:
    data = json.loads(base64.b64decode(parts[1]).decode('utf-8'))
except Exception as e:
    print(json.dumps({"error": f"Could not decode payload: {str(e)}"}))
    sys.exit(1)

game        = data.get('game',       '').strip()
client      = data.get('client',     '').strip()
quote_raw   = data.get('quote',      '').strip()
payment     = data.get('payment',    '').strip()
status      = data.get('status',     '').strip()
tgt_start   = data.get('tgt_start',  '').strip()
tgt_end     = data.get('tgt_end',    '').strip()
start_date  = data.get('start_date', '').strip()
end_date    = data.get('end_date',   '').strip()
notes       = data.get('notes',      '').strip()
my_name     = data.get('my_name',    '').strip()
my_phone    = data.get('my_phone',   '').strip()
my_company  = data.get('my_company', '').strip()
my_logo     = data.get('my_logo',    '').strip()
my_address  = data.get('my_address', '').strip()

# ── Helpers ──────────────────────────────────────────────────────────────
def fmt_date(s):
    """Format YYYY-MM-DD → M/D/YYYY, or return as-is."""
    if not s:
        return ''
    try:
        return datetime.strptime(s, '%Y-%m-%d').strftime('%-m/%-d/%Y')
    except Exception:
        return s

def parse_quote(s):
    """Return float or None."""
    try:
        return float(re.sub(r'[^\d.]', '', s))
    except Exception:
        return None

def rgb(r, g, b):
    return {'red': r/255, 'green': g/255, 'blue': b/255}

# Colours
DARK_NAVY  = rgb(26, 26, 46)    # #1a1a2e — company name
TEAL       = rgb(26, 95, 122)   # #1a5f7a — table header, total label
WHITE      = rgb(255,255,255)
LIGHT_GRAY = rgb(240,244,248)   # section backgrounds
MID_GRAY   = rgb(136,136,136)

# ── Open spreadsheet ─────────────────────────────────────────────────────
try:
    wb = sa.open_by_key(sheet_id)
except Exception as e:
    print(json.dumps({"error": f"Could not open spreadsheet: {str(e)}"}))
    sys.exit(1)

# ── Build tab name ────────────────────────────────────────────────────────
today_str = datetime.today().strftime('%Y-%m-%d')
base_name = f"Invoice – {game}" if game else "Invoice"
tab_name  = base_name

existing = {w.title for w in wb.worksheets()}
if tab_name in existing:
    # Append date to avoid collision
    tab_name = f"{base_name} {today_str}"
    counter = 2
    while tab_name in existing:
        tab_name = f"{base_name} {today_str} ({counter})"
        counter += 1

# ── Create the worksheet ──────────────────────────────────────────────────
try:
    ws = wb.add_worksheet(title=tab_name, rows=30, cols=8)
except Exception as e:
    print(json.dumps({"error": f"Could not create worksheet: {str(e)}"}))
    sys.exit(1)

sheet_gid = ws.id
COLS = 8   # A–H

# ── Build values ──────────────────────────────────────────────────────────
quote_val  = parse_quote(quote_raw)
quote_fmt  = ('$' + f'{quote_val:,.2f}') if quote_val is not None else (quote_raw or '—')

today_disp = datetime.today().strftime('%-m/%-d/%Y')

# Date range for the contract (prefer actual dates, fall back to target dates)
range_start = start_date or tgt_start
range_end   = end_date   or tgt_end
disp_start  = fmt_date(range_start)
disp_end    = fmt_date(range_end)
if disp_start and disp_end:
    date_range = f"{disp_start} – {disp_end}"
elif disp_start:
    date_range = f"From {disp_start}"
elif disp_end:
    date_range = f"To {disp_end}"
else:
    date_range = ''

# Invoice number: YYYYMMDD + game initials
initials = ''.join(w[0].upper() for w in re.split(r'\s+', game) if w)[:4]
invoice_num = today_str.replace('-', '') + ('-' + initials if initials else '')

# Address: split into up to 2 lines
addr_lines = [l.strip() for l in my_address.replace('\r\n', '\n').split('\n') if l.strip()]
addr_line1 = addr_lines[0] if len(addr_lines) > 0 else ''
addr_line2 = addr_lines[1] if len(addr_lines) > 1 else ''
addr_line3 = addr_lines[2] if len(addr_lines) > 2 else ''

# ─── Row layout (1-indexed):
# 1  — (empty top margin)
# 2  — (empty)
# 3  — Company name (A:F) | Logo image (G:H)
# 4  — Address line 1
# 5  — Address line 2 (if present)
# 6  — Phone
# 7  — (spacer)
# 8  — "Invoice" header (A:H)
# 9  — "Submitted on MM/DD/YYYY"
# 10 — (spacer)
# 11 — "Prepared for" | "Project" | "Estimate #" (labels)
# 12 — client name  | game + " Dev" | invoice_num
# 13 — (spacer)
# 14 — "Duration" (label, if date_range)
# 15 — date range value
# 16 — (spacer)
# 17 — separator (thick bottom border on row 16)
# 18 — Table header: Description | | Qty | Unit Price | Total
# 19 — Table data row
# 20 — Notes row (if notes)
# 21 — (spacer)
# 22 — "Subtotal" label (cols E:G) | amount (col H)
# 23 — (spacer)
# 24 — "Total Due" label (cols E:G, teal) | amount (col H, large teal)

# We'll set values row by row.
# Column mapping: A=0,B=1,C=2,D=3,E=4,F=5,G=6,H=7

def empty_row():
    return [''] * COLS

rows = []
rows.append(empty_row())                                       # row 1
rows.append(empty_row())                                       # row 2
r3 = empty_row(); r3[0] = my_company or my_name               # row 3 — company name
if my_logo:
    r3[6] = f'=IMAGE("{my_logo}",2)'                          # G3 — logo
rows.append(r3)
r4 = empty_row(); r4[0] = addr_line1; rows.append(r4)         # row 4
r5 = empty_row(); r5[0] = addr_line2; rows.append(r5)         # row 5
r6 = empty_row(); r6[0] = my_phone;   rows.append(r6)         # row 6
rows.append(empty_row())                                       # row 7
r8 = empty_row(); r8[0] = 'Invoice';  rows.append(r8)         # row 8
r9 = empty_row(); r9[0] = f'Submitted on {today_disp}'; rows.append(r9)  # row 9
rows.append(empty_row())                                       # row 10
# row 11 — labels
r11 = empty_row()
r11[0] = 'Prepared for'
r11[2] = 'Project'
r11[5] = 'Estimate #'
rows.append(r11)
# row 12 — values
r12 = empty_row()
r12[0] = client or '—'
r12[2] = (game + ' Dev') if game else '—'
r12[5] = invoice_num
rows.append(r12)
rows.append(empty_row())                                       # row 13
# row 14-15 — duration (only if we have a date range)
if date_range:
    r14 = empty_row(); r14[4] = 'Duration'; rows.append(r14)
    r15 = empty_row(); r15[4] = date_range; rows.append(r15)
    rows.append(empty_row())                                   # row 16
    DUR_ROWS = 3
else:
    rows.append(empty_row())                                   # row 14 placeholder
    DUR_ROWS = 1

# Current row index after duration section
BASE = len(rows) + 1   # 1-indexed sheet row number

# Table header row
r_thead = empty_row()
r_thead[0] = 'Description'
r_thead[5] = 'Qty'
r_thead[6] = 'Unit Price'
r_thead[7] = 'Total'
rows.append(r_thead)
ROW_THEAD = BASE      # 1-indexed

# Table data row
r_data = empty_row()
r_data[0] = f'{game} Dev' if game else 'Design services'
r_data[5] = '1'
r_data[6] = quote_fmt
r_data[7] = quote_fmt
rows.append(r_data)
ROW_DATA = BASE + 1

# Notes row (if any)
if notes:
    r_notes = empty_row(); r_notes[0] = notes; rows.append(r_notes)
    ROW_NOTES = BASE + 2
    NOTES_OFFSET = 1
else:
    rows.append(empty_row())
    NOTES_OFFSET = 1

# Spacer
rows.append(empty_row())

# Subtotal row
ROW_SUBTOTAL = len(rows) + 1
r_sub = empty_row(); r_sub[5] = 'Subtotal'; r_sub[7] = quote_fmt; rows.append(r_sub)

# Spacer
rows.append(empty_row())

# Total row
ROW_TOTAL = len(rows) + 1
r_tot = empty_row(); r_tot[5] = 'Total Due'; r_tot[7] = quote_fmt; rows.append(r_tot)

# ── Write all values at once ──────────────────────────────────────────────
try:
    ws.update(values=rows, range_name='A1', value_input_option='USER_ENTERED')
except Exception as e:
    try:
        wb.del_worksheet(ws)
    except Exception:
        pass
    print(json.dumps({"error": f"Could not write values: {str(e)}"}))
    sys.exit(1)

# ── Batch formatting ──────────────────────────────────────────────────────
def cell_range(r1, c1, r2, c2):
    """0-indexed startRowIndex/endRowIndex/startColumnIndex/endColumnIndex."""
    return {
        'sheetId':          sheet_gid,
        'startRowIndex':    r1,
        'endRowIndex':      r2,
        'startColumnIndex': c1,
        'endColumnIndex':   c2,
    }

def merge_req(r1, c1, r2, c2):
    return {'mergeCells': {'range': cell_range(r1, c1, r2, c2), 'mergeType': 'MERGE_ALL'}}

def format_req(r1, c1, r2, c2, fmt, fields):
    return {'repeatCell': {'range': cell_range(r1, c1, r2, c2), 'cell': {'userEnteredFormat': fmt}, 'fields': fields}}

def border_req(r1, c1, r2, c2, borders):
    return {'updateBorders': {'range': cell_range(r1, c1, r2, c2), **borders}}

def solid_border(width=1, color=None):
    c = color or rgb(200, 210, 218)
    return {'style': 'SOLID', 'width': width, 'color': c}

def row_height_req(row_0idx, px):
    return {'updateDimensionProperties': {
        'range': {'sheetId': sheet_gid, 'dimension': 'ROWS', 'startIndex': row_0idx, 'endIndex': row_0idx+1},
        'properties': {'pixelSize': px}, 'fields': 'pixelSize'
    }}

def col_width_req(c_0idx, px):
    return {'updateDimensionProperties': {
        'range': {'sheetId': sheet_gid, 'dimension': 'COLUMNS', 'startIndex': c_0idx, 'endIndex': c_0idx+1},
        'properties': {'pixelSize': px}, 'fields': 'pixelSize'
    }}

# Convenience: convert 1-indexed row to 0-indexed
def r0(row1): return row1 - 1

reqs = []

# ── Column widths ──
# A=desc wide, B-D small spacers, E-G labels/qty/price, H=amount
col_widths = [280, 40, 120, 60, 60, 60, 90, 100]
for ci, px in enumerate(col_widths):
    reqs.append(col_width_req(ci, px))

# ── Row heights ──
reqs.append(row_height_req(r0(1), 8))   # top margin
reqs.append(row_height_req(r0(2), 8))
reqs.append(row_height_req(r0(3), 48))  # company name
reqs.append(row_height_req(r0(8), 54))  # "Invoice" big
if my_logo:
    # Logo spans rows 3-6 → make them taller to show image
    for logo_r in [3, 4, 5, 6]:
        reqs.append(row_height_req(r0(logo_r), 42))

# ── Merges ──
# Company name A3:F3
reqs.append(merge_req(r0(3), 0, r0(3)+1, 6))
# Logo G3:H6
if my_logo:
    reqs.append(merge_req(r0(3), 6, r0(6)+1, 8))
# "Invoice" heading A8:H8
reqs.append(merge_req(r0(8), 0, r0(8)+1, 8))
# "Submitted on" A9:H9
reqs.append(merge_req(r0(9), 0, r0(9)+1, 8))
# "Prepared for" label A11:B11
reqs.append(merge_req(r0(11), 0, r0(11)+1, 2))
# "Project" label C11:E11
reqs.append(merge_req(r0(11), 2, r0(11)+1, 5))
# "Estimate #" label F11:H11
reqs.append(merge_req(r0(11), 5, r0(11)+1, 8))
# Client name A12:B12
reqs.append(merge_req(r0(12), 0, r0(12)+1, 2))
# Game name C12:E12
reqs.append(merge_req(r0(12), 2, r0(12)+1, 5))
# Estimate # value F12:H12
reqs.append(merge_req(r0(12), 5, r0(12)+1, 8))
# Description cell in table spans A:E
reqs.append(merge_req(r0(ROW_THEAD), 0, r0(ROW_THEAD)+1, 5))
reqs.append(merge_req(r0(ROW_DATA),  0, r0(ROW_DATA)+1,  5))
# Notes spans A:H
if notes:
    reqs.append(merge_req(r0(ROW_DATA+1), 0, r0(ROW_DATA+1)+1, 8))
# Subtotal label E:G
reqs.append(merge_req(r0(ROW_SUBTOTAL), 4, r0(ROW_SUBTOTAL)+1, 7))
# Total label E:G
reqs.append(merge_req(r0(ROW_TOTAL), 4, r0(ROW_TOTAL)+1, 7))

# ── Company name formatting ──
reqs.append(format_req(r0(3), 0, r0(3)+1, 6, {
    'textFormat': {'foregroundColor': DARK_NAVY, 'fontSize': 22, 'bold': True,
                   'fontFamily': 'Arial'},
    'verticalAlignment': 'MIDDLE',
}, 'userEnteredFormat.textFormat,userEnteredFormat.verticalAlignment'))

# ── Address / phone rows (4,5,6) ──
for ar in [4, 5, 6]:
    reqs.append(format_req(r0(ar), 0, r0(ar)+1, 6, {
        'textFormat': {'foregroundColor': MID_GRAY, 'fontSize': 9, 'fontFamily': 'Arial'},
    }, 'userEnteredFormat.textFormat'))

# ── "Invoice" heading ──
reqs.append(format_req(r0(8), 0, r0(8)+1, 8, {
    'textFormat': {'foregroundColor': DARK_NAVY, 'fontSize': 32, 'bold': True, 'fontFamily': 'Arial'},
    'verticalAlignment': 'MIDDLE',
}, 'userEnteredFormat.textFormat,userEnteredFormat.verticalAlignment'))

# ── "Submitted on" ──
reqs.append(format_req(r0(9), 0, r0(9)+1, 8, {
    'textFormat': {'foregroundColor': MID_GRAY, 'fontSize': 9, 'fontFamily': 'Arial'},
}, 'userEnteredFormat.textFormat'))

# ── Label row 11 ──
reqs.append(format_req(r0(11), 0, r0(11)+1, 8, {
    'textFormat': {'foregroundColor': MID_GRAY, 'fontSize': 8, 'bold': False, 'fontFamily': 'Arial'},
    'backgroundColor': LIGHT_GRAY,
}, 'userEnteredFormat.textFormat,userEnteredFormat.backgroundColor'))

# ── Value row 12 ──
reqs.append(format_req(r0(12), 0, r0(12)+1, 8, {
    'textFormat': {'foregroundColor': DARK_NAVY, 'fontSize': 10, 'bold': True, 'fontFamily': 'Arial'},
    'backgroundColor': LIGHT_GRAY,
}, 'userEnteredFormat.textFormat,userEnteredFormat.backgroundColor'))

# ── Duration label/value (if present) ──
if date_range:
    dur_label_row = 14
    dur_val_row   = 15
    reqs.append(merge_req(r0(dur_label_row), 4, r0(dur_label_row)+1, 8))
    reqs.append(merge_req(r0(dur_val_row),   4, r0(dur_val_row)+1,   8))
    reqs.append(format_req(r0(dur_label_row), 4, r0(dur_label_row)+1, 8, {
        'textFormat': {'foregroundColor': MID_GRAY, 'fontSize': 8, 'fontFamily': 'Arial'},
        'backgroundColor': LIGHT_GRAY,
    }, 'userEnteredFormat.textFormat,userEnteredFormat.backgroundColor'))
    reqs.append(format_req(r0(dur_val_row), 4, r0(dur_val_row)+1, 8, {
        'textFormat': {'foregroundColor': DARK_NAVY, 'fontSize': 10, 'bold': True, 'fontFamily': 'Arial'},
        'backgroundColor': LIGHT_GRAY,
    }, 'userEnteredFormat.textFormat,userEnteredFormat.backgroundColor'))

# ── Table header row ──
reqs.append(format_req(r0(ROW_THEAD), 0, r0(ROW_THEAD)+1, 8, {
    'textFormat': {'foregroundColor': WHITE, 'fontSize': 9, 'bold': True, 'fontFamily': 'Arial'},
    'backgroundColor': TEAL,
    'verticalAlignment': 'MIDDLE',
}, 'userEnteredFormat.textFormat,userEnteredFormat.backgroundColor,userEnteredFormat.verticalAlignment'))
reqs.append(row_height_req(r0(ROW_THEAD), 28))

# Align qty/price/total right in header
reqs.append(format_req(r0(ROW_THEAD), 5, r0(ROW_THEAD)+1, 8, {
    'horizontalAlignment': 'RIGHT',
}, 'userEnteredFormat.horizontalAlignment'))

# ── Table data row ──
reqs.append(format_req(r0(ROW_DATA), 0, r0(ROW_DATA)+1, 8, {
    'textFormat': {'fontSize': 10, 'fontFamily': 'Arial'},
}, 'userEnteredFormat.textFormat'))
reqs.append(format_req(r0(ROW_DATA), 5, r0(ROW_DATA)+1, 8, {
    'horizontalAlignment': 'RIGHT',
}, 'userEnteredFormat.horizontalAlignment'))
# Light bottom border on data row
reqs.append(border_req(r0(ROW_DATA), 0, r0(ROW_DATA)+1, 8, {
    'bottom': solid_border(1)
}))

# ── Notes row ──
if notes:
    reqs.append(format_req(r0(ROW_DATA+1), 0, r0(ROW_DATA+1)+1, 8, {
        'textFormat': {'foregroundColor': MID_GRAY, 'fontSize': 8, 'italic': True, 'fontFamily': 'Arial'},
        'wrapStrategy': 'WRAP',
    }, 'userEnteredFormat.textFormat,userEnteredFormat.wrapStrategy'))

# ── Subtotal row ──
reqs.append(format_req(r0(ROW_SUBTOTAL), 4, r0(ROW_SUBTOTAL)+1, 7, {
    'textFormat': {'foregroundColor': DARK_NAVY, 'fontSize': 9, 'fontFamily': 'Arial'},
    'horizontalAlignment': 'RIGHT',
}, 'userEnteredFormat.textFormat,userEnteredFormat.horizontalAlignment'))
reqs.append(format_req(r0(ROW_SUBTOTAL), 7, r0(ROW_SUBTOTAL)+1, 8, {
    'textFormat': {'foregroundColor': DARK_NAVY, 'fontSize': 9, 'fontFamily': 'Arial'},
    'horizontalAlignment': 'RIGHT',
}, 'userEnteredFormat.textFormat,userEnteredFormat.horizontalAlignment'))

# ── Total row ──
reqs.append(format_req(r0(ROW_TOTAL), 4, r0(ROW_TOTAL)+1, 7, {
    'textFormat': {'foregroundColor': TEAL, 'fontSize': 13, 'bold': True, 'fontFamily': 'Arial'},
    'horizontalAlignment': 'RIGHT',
}, 'userEnteredFormat.textFormat,userEnteredFormat.horizontalAlignment'))
reqs.append(format_req(r0(ROW_TOTAL), 7, r0(ROW_TOTAL)+1, 8, {
    'textFormat': {'foregroundColor': TEAL, 'fontSize': 18, 'bold': True, 'fontFamily': 'Arial'},
    'horizontalAlignment': 'RIGHT',
}, 'userEnteredFormat.textFormat,userEnteredFormat.horizontalAlignment'))
reqs.append(row_height_req(r0(ROW_TOTAL), 36))

# ── Thin separator above table header ──
reqs.append(border_req(r0(ROW_THEAD), 0, r0(ROW_THEAD)+1, 8, {
    'top': solid_border(2, TEAL)
}))

# ── Logo image row height ──
if my_logo:
    reqs.append(row_height_req(r0(3), 80))

# ── Execute all formatting requests ──────────────────────────────────────
try:
    wb.batch_update({'requests': reqs})
except Exception as e:
    # Non-fatal — sheet was created and values written; formatting failed
    pass

# ── Return spreadsheet URL ────────────────────────────────────────────────
spreadsheet_url = f"https://docs.google.com/spreadsheets/d/{sheet_id}/edit#gid={sheet_gid}"

print(json.dumps({
    "ok":       True,
    "tab":      tab_name,
    "gid":      sheet_gid,
    "url":      spreadsheet_url,
}))
