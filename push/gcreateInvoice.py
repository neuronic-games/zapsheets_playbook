# gcreateInvoice.py — create a formatted invoice tab in a Google Sheet.
#
# Matches the Tabletop Refinery invoice layout:
#   - Orange accent (#e8623a) for company name, table header text, totals
#   - No gridlines
#   - Tab name: [{game}] invoice {invoice_num}
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

# ── Helpers ───────────────────────────────────────────────────────────────

def safe_str(v):
    """Prefix non-empty strings with ' to prevent Google Sheets from
    interpreting phone numbers, dates, or other values as formulas/numbers.
    =IMAGE() formulas are passed through unchanged."""
    s = (str(v) if v is not None else '').strip()
    if not s:
        return ''
    if s.upper().startswith('=IMAGE('):
        return s   # keep formula as-is
    return "'" + s

def fmt_date(s):
    """Format YYYY-MM-DD → M/D/YYYY."""
    if not s:
        return ''
    try:
        return datetime.strptime(s, '%Y-%m-%d').strftime('%-m/%-d/%Y')
    except Exception:
        return s

def parse_quote(s):
    try:
        return float(re.sub(r'[^\d.]', '', s))
    except Exception:
        return None

def rgb(r, g, b):
    return {'red': r/255, 'green': g/255, 'blue': b/255}

# ── Palette (matches screenshot) ──────────────────────────────────────────
ORANGE     = rgb(232, 98, 58)    # #e8623a — company, headers, totals
BLACK      = rgb(30, 30, 30)     # near-black for body text
GRAY_DARK  = rgb(100, 100, 100)  # submitted-on line, notes
GRAY_MED   = rgb(160, 160, 160)  # address/phone
ROW_BG     = rgb(245, 245, 245)  # light gray for data rows / label section
WHITE      = rgb(255, 255, 255)
SEP_COLOR  = rgb(200, 200, 200)  # separator line color

# ── Derived values ────────────────────────────────────────────────────────
today_disp = datetime.today().strftime('%-m/%-d/%Y')
today_str  = datetime.today().strftime('%Y-%m-%d')

quote_val  = parse_quote(quote_raw)
quote_fmt  = ('$' + f'{quote_val:,.2f}') if quote_val is not None else (quote_raw or '—')

# Date range: prefer actual dates, fall back to target dates
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

# Invoice number: company initials + YYYYMMDD
comp_initials = ''.join(w[0].upper() for w in re.split(r'\s+', my_company or my_name) if w)[:2]
invoice_num = (comp_initials or 'INV') + today_str.replace('-', '')

# Address lines
addr_lines = [l.strip() for l in my_address.replace('\r\n', '\n').split('\n') if l.strip()]
addr_line1 = addr_lines[0] if len(addr_lines) > 0 else ''
addr_line2 = addr_lines[1] if len(addr_lines) > 1 else ''

# ── Open spreadsheet ──────────────────────────────────────────────────────
try:
    wb = sa.open_by_key(sheet_id)
except Exception as e:
    print(json.dumps({"error": f"Could not open spreadsheet: {str(e)}"}))
    sys.exit(1)

# ── Tab name: [{game}] invoice {invoice_num} ──────────────────────────────
base_name = f"[{game}] invoice {invoice_num}" if game else f"invoice {invoice_num}"
tab_name  = base_name
existing  = {w.title for w in wb.worksheets()}
counter   = 2
while tab_name in existing:
    tab_name = f"{base_name} ({counter})"
    counter += 1

# ── Layout constants ──────────────────────────────────────────────────────
# 8 columns (A–H), ~30 rows
COLS = 8
ROWS = 32

# Row assignments (1-indexed)
R_MARGIN1   = 1
R_MARGIN2   = 2
R_COMPANY   = 3   # company name (A:F) | logo (G:H, spans 3-6)
R_ADDR1     = 4
R_ADDR2     = 5
R_PHONE     = 6
R_SPACER1   = 7
R_INVOICE   = 8   # "Invoice" large heading
R_SUBMITTED = 9   # "Submitted on..."
R_SPACER2   = 10
R_LABELS    = 11  # Prepared for | Project | Estimate #
R_VALUES    = 12  # client name  | game Dev | invoice num
R_SPACER3   = 13

# Duration rows (if present)
if date_range:
    R_DUR_LABEL = 14
    R_DUR_VAL   = 15
    R_SPACER4   = 16
    R_SEPARATOR = 16   # bottom border goes on this row
    R_THEAD     = 17
else:
    R_SEPARATOR = 13
    R_THEAD     = 14

R_DATA      = R_THEAD + 1
R_NOTES     = R_DATA  + 1 if notes else None
R_SPACER5   = R_DATA  + (2 if notes else 1)
R_SUBTOTAL  = R_SPACER5 + 1
R_SPACER6   = R_SUBTOTAL + 1
R_TOTAL     = R_SPACER6 + 1

TOTAL_ROWS  = R_TOTAL + 2

# ── Build values array ────────────────────────────────────────────────────
def erow():
    return [''] * COLS

grid = []
for _ in range(TOTAL_ROWS):
    grid.append(erow())

def set_cell(row1, col0, val):
    if 1 <= row1 <= len(grid) and 0 <= col0 < COLS:
        grid[row1 - 1][col0] = safe_str(val)

# Company / logo
set_cell(R_COMPANY, 0, my_company or my_name)
if my_logo:
    set_cell(R_COMPANY, 6, f'=IMAGE("{my_logo}",2)')

# Address / phone
set_cell(R_ADDR1, 0, addr_line1)
set_cell(R_ADDR2, 0, addr_line2)
set_cell(R_PHONE, 0, my_phone)

# Invoice heading
set_cell(R_INVOICE, 0, 'Invoice')
set_cell(R_SUBMITTED, 0, f'Submitted on {today_disp}')

# Prepared for / Project / Estimate #
set_cell(R_LABELS, 0, 'Prepared for')
set_cell(R_LABELS, 3, 'Project')
set_cell(R_LABELS, 5, 'Estimate #')
set_cell(R_VALUES, 0, client or '—')
set_cell(R_VALUES, 3, (game + ' Dev') if game else '—')
set_cell(R_VALUES, 5, invoice_num)

# Duration
if date_range:
    set_cell(R_DUR_LABEL, 5, 'Duration')
    set_cell(R_DUR_VAL,   5, date_range)

# Table header
set_cell(R_THEAD, 0, 'Description')
set_cell(R_THEAD, 4, 'Qty')
set_cell(R_THEAD, 5, 'Unit price')
set_cell(R_THEAD, 6, 'Total price')

# Data row
set_cell(R_DATA, 0, f'{game} Development' if game else 'Design services')
set_cell(R_DATA, 4, '1')
set_cell(R_DATA, 5, quote_fmt)
set_cell(R_DATA, 6, quote_fmt)

# Notes
if notes and R_NOTES:
    set_cell(R_NOTES, 0, notes)

# Subtotal / Total
set_cell(R_SUBTOTAL, 4, 'Subtotal')
set_cell(R_SUBTOTAL, 6, quote_fmt)
set_cell(R_TOTAL,    6, quote_fmt)

# ── Create worksheet ──────────────────────────────────────────────────────
try:
    ws = wb.add_worksheet(title=tab_name, rows=TOTAL_ROWS + 4, cols=COLS)
except Exception as e:
    print(json.dumps({"error": f"Could not create worksheet: {str(e)}"}))
    sys.exit(1)

sheet_gid = ws.id

# ── Write values ──────────────────────────────────────────────────────────
try:
    ws.update(values=grid, range_name='A1', value_input_option='USER_ENTERED')
except Exception as e:
    try:
        wb.del_worksheet(ws)
    except Exception:
        pass
    print(json.dumps({"error": f"Could not write values: {str(e)}"}))
    sys.exit(1)

# ── Formatting helpers ────────────────────────────────────────────────────

def cr(r1, c1, r2, c2):
    """Cell range (all 0-indexed, end exclusive)."""
    return {'sheetId': sheet_gid, 'startRowIndex': r1, 'endRowIndex': r2,
            'startColumnIndex': c1, 'endColumnIndex': c2}

def r0(row1):
    """1-indexed row → 0-indexed."""
    return row1 - 1

def merge(r1, c1, r2, c2):
    return {'mergeCells': {'range': cr(r0(r1), c1, r0(r1)+1, c2), 'mergeType': 'MERGE_ALL'}}

def merge_rows(r1, c1, r2, c2):
    """Merge across multiple rows."""
    return {'mergeCells': {'range': cr(r0(r1), c1, r0(r2)+1, c2), 'mergeType': 'MERGE_ALL'}}

def fmt(r1, c1, r2, c2, cell_fmt, fields):
    return {'repeatCell': {
        'range': cr(r0(r1), c1, r0(r2)+1, c2),
        'cell': {'userEnteredFormat': cell_fmt},
        'fields': fields
    }}

def row_h(row1, px):
    return {'updateDimensionProperties': {
        'range': {'sheetId': sheet_gid, 'dimension': 'ROWS',
                  'startIndex': r0(row1), 'endIndex': r0(row1)+1},
        'properties': {'pixelSize': px}, 'fields': 'pixelSize'
    }}

def col_w(c0, px):
    return {'updateDimensionProperties': {
        'range': {'sheetId': sheet_gid, 'dimension': 'COLUMNS',
                  'startIndex': c0, 'endIndex': c0+1},
        'properties': {'pixelSize': px}, 'fields': 'pixelSize'
    }}

def border_bottom(row1, c1, c2, color=None, width=1):
    c = color or SEP_COLOR
    return {'updateBorders': {
        'range': cr(r0(row1), c1, r0(row1)+1, c2),
        'bottom': {'style': 'SOLID', 'width': width, 'color': c}
    }}

def text_fmt(color, size, bold=False, italic=False, family='Arial'):
    return {'foregroundColor': color, 'fontSize': size, 'bold': bold,
            'italic': italic, 'fontFamily': family}

reqs = []

# ── 1. Hide gridlines ──────────────────────────────────────────────────────
reqs.append({'updateSheetProperties': {
    'properties': {'sheetId': sheet_gid, 'gridProperties': {'hideGridlines': True}},
    'fields': 'gridProperties.hideGridlines'
}})

# ── 2. Column widths ──────────────────────────────────────────────────────
# A=wide desc, B tiny, C tiny, D project, E qty, F-G price, H total
col_widths = [270, 20, 20, 140, 60, 90, 90, 90]
for ci, px in enumerate(col_widths):
    reqs.append(col_w(ci, px))

# ── 3. Row heights ────────────────────────────────────────────────────────
reqs.append(row_h(R_MARGIN1, 10))
reqs.append(row_h(R_MARGIN2, 10))
reqs.append(row_h(R_COMPANY, 40 if not my_logo else 60))
reqs.append(row_h(R_ADDR1, 18))
reqs.append(row_h(R_ADDR2, 18 if addr_line2 else 4))
reqs.append(row_h(R_PHONE, 18))
reqs.append(row_h(R_SPACER1, 20))
reqs.append(row_h(R_INVOICE, 60))
reqs.append(row_h(R_SUBMITTED, 20))
reqs.append(row_h(R_SPACER2, 16))
reqs.append(row_h(R_LABELS, 24))
reqs.append(row_h(R_VALUES, 26))
reqs.append(row_h(R_SPACER3, 14))
if date_range:
    reqs.append(row_h(R_DUR_LABEL, 22))
    reqs.append(row_h(R_DUR_VAL, 24))
    reqs.append(row_h(R_SPACER4, 14))
reqs.append(row_h(R_THEAD, 30))
reqs.append(row_h(R_DATA, 80))   # taller for wrapped text
if notes and R_NOTES:
    reqs.append(row_h(R_NOTES, 24))
reqs.append(row_h(R_SPACER5, 14))
reqs.append(row_h(R_SUBTOTAL, 26))
reqs.append(row_h(R_SPACER6, 12))
reqs.append(row_h(R_TOTAL, 44))

# ── 4. Merges ─────────────────────────────────────────────────────────────
# Company name: A:F (cols 0-5)
reqs.append(merge(R_COMPANY, 0, R_COMPANY, 6))
# Logo: G:H spanning rows 3-6 (cols 6-7)
if my_logo:
    reqs.append(merge_rows(R_COMPANY, 6, R_PHONE, 8))
# "Invoice" heading: A:H
reqs.append(merge(R_INVOICE, 0, R_INVOICE, COLS))
# "Submitted on": A:H
reqs.append(merge(R_SUBMITTED, 0, R_SUBMITTED, COLS))
# "Prepared for" label: A:C (0-3)
reqs.append(merge(R_LABELS, 0, R_LABELS, 3))
# "Project" label: D:E (3-5)
reqs.append(merge(R_LABELS, 3, R_LABELS, 5))
# "Estimate #" label: F:H (5-8)
reqs.append(merge(R_LABELS, 5, R_LABELS, COLS))
# Values row
reqs.append(merge(R_VALUES, 0, R_VALUES, 3))
reqs.append(merge(R_VALUES, 3, R_VALUES, 5))
reqs.append(merge(R_VALUES, 5, R_VALUES, COLS))
# Duration (if present)
if date_range:
    reqs.append(merge(R_DUR_LABEL, 5, R_DUR_LABEL, COLS))
    reqs.append(merge(R_DUR_VAL,   5, R_DUR_VAL,   COLS))
# Table header description: A:D (0-4)
reqs.append(merge(R_THEAD, 0, R_THEAD, 4))
# Data row description: A:D (0-4)
reqs.append(merge(R_DATA, 0, R_DATA, 4))
# Notes row: A:H
if notes and R_NOTES:
    reqs.append(merge(R_NOTES, 0, R_NOTES, COLS))
# Subtotal label: E:F (4-6)
reqs.append(merge(R_SUBTOTAL, 4, R_SUBTOTAL, 6))
# Total amount: E:H (spans for large right-aligned text)
reqs.append(merge(R_TOTAL, 4, R_TOTAL, COLS))

# ── 5. Cell formatting ────────────────────────────────────────────────────

# Company name — orange, large bold
reqs.append(fmt(R_COMPANY, 0, R_COMPANY, 6, {
    'textFormat': text_fmt(ORANGE, 22, bold=True),
    'verticalAlignment': 'MIDDLE',
}, 'userEnteredFormat.textFormat,userEnteredFormat.verticalAlignment'))

# Address lines — medium gray, small
for row in [R_ADDR1, R_ADDR2, R_PHONE]:
    reqs.append(fmt(row, 0, row, 6, {
        'textFormat': text_fmt(GRAY_DARK, 9),
        'verticalAlignment': 'MIDDLE',
    }, 'userEnteredFormat.textFormat,userEnteredFormat.verticalAlignment'))

# "Invoice" — black, very large bold
reqs.append(fmt(R_INVOICE, 0, R_INVOICE, COLS, {
    'textFormat': text_fmt(BLACK, 36, bold=True),
    'verticalAlignment': 'BOTTOM',
}, 'userEnteredFormat.textFormat,userEnteredFormat.verticalAlignment'))

# "Submitted on" — dark gray
reqs.append(fmt(R_SUBMITTED, 0, R_SUBMITTED, COLS, {
    'textFormat': text_fmt(GRAY_DARK, 10, bold=True),
}, 'userEnteredFormat.textFormat'))

# "Prepared for / Project / Estimate #" labels — bold black, white bg
reqs.append(fmt(R_LABELS, 0, R_LABELS, COLS, {
    'textFormat': text_fmt(BLACK, 9, bold=True),
    'verticalAlignment': 'BOTTOM',
}, 'userEnteredFormat.textFormat,userEnteredFormat.verticalAlignment'))

# Values row — normal weight, white bg
reqs.append(fmt(R_VALUES, 0, R_VALUES, COLS, {
    'textFormat': text_fmt(BLACK, 10),
    'verticalAlignment': 'TOP',
}, 'userEnteredFormat.textFormat,userEnteredFormat.verticalAlignment'))

# Duration label — bold black (no background)
if date_range:
    reqs.append(fmt(R_DUR_LABEL, 5, R_DUR_LABEL, COLS, {
        'textFormat': text_fmt(BLACK, 10, bold=True),
        'horizontalAlignment': 'LEFT',
        'verticalAlignment': 'BOTTOM',
    }, 'userEnteredFormat.textFormat,userEnteredFormat.horizontalAlignment,userEnteredFormat.verticalAlignment'))
    reqs.append(fmt(R_DUR_VAL, 5, R_DUR_VAL, COLS, {
        'textFormat': text_fmt(GRAY_DARK, 10),
        'horizontalAlignment': 'LEFT',
        'verticalAlignment': 'TOP',
    }, 'userEnteredFormat.textFormat,userEnteredFormat.horizontalAlignment,userEnteredFormat.verticalAlignment'))

# Table header row — orange text, white bg (no fill), bold
reqs.append(fmt(R_THEAD, 0, R_THEAD, COLS, {
    'textFormat': text_fmt(ORANGE, 9, bold=True),
    'backgroundColor': WHITE,
    'verticalAlignment': 'MIDDLE',
}, 'userEnteredFormat.textFormat,userEnteredFormat.backgroundColor,userEnteredFormat.verticalAlignment'))
# Right-align qty / price cols in header
reqs.append(fmt(R_THEAD, 4, R_THEAD, COLS, {
    'horizontalAlignment': 'RIGHT',
}, 'userEnteredFormat.horizontalAlignment'))

# Data row — light gray bg, wrap
reqs.append(fmt(R_DATA, 0, R_DATA, COLS, {
    'textFormat': text_fmt(BLACK, 10),
    'backgroundColor': ROW_BG,
    'verticalAlignment': 'TOP',
    'wrapStrategy': 'WRAP',
}, 'userEnteredFormat.textFormat,userEnteredFormat.backgroundColor,userEnteredFormat.verticalAlignment,userEnteredFormat.wrapStrategy'))
# Right-align qty/price in data row
reqs.append(fmt(R_DATA, 4, R_DATA, COLS, {
    'horizontalAlignment': 'RIGHT',
}, 'userEnteredFormat.horizontalAlignment'))
# Bottom border on data row
reqs.append(border_bottom(R_DATA, 0, COLS, SEP_COLOR, 1))

# Notes — italic, gray
if notes and R_NOTES:
    reqs.append(fmt(R_NOTES, 0, R_NOTES, COLS, {
        'textFormat': text_fmt(GRAY_DARK, 9, italic=True),
        'wrapStrategy': 'WRAP',
    }, 'userEnteredFormat.textFormat,userEnteredFormat.wrapStrategy'))

# Subtotal label + amount (white bg, right-aligned)
reqs.append(fmt(R_SUBTOTAL, 4, R_SUBTOTAL, 6, {
    'textFormat': text_fmt(GRAY_DARK, 10),
    'horizontalAlignment': 'RIGHT',
    'verticalAlignment': 'MIDDLE',
}, 'userEnteredFormat.textFormat,userEnteredFormat.horizontalAlignment,userEnteredFormat.verticalAlignment'))
reqs.append(fmt(R_SUBTOTAL, 6, R_SUBTOTAL, COLS, {
    'textFormat': text_fmt(BLACK, 10, bold=True),
    'horizontalAlignment': 'RIGHT',
    'verticalAlignment': 'MIDDLE',
}, 'userEnteredFormat.textFormat,userEnteredFormat.horizontalAlignment,userEnteredFormat.verticalAlignment'))

# Total amount — large orange, right-aligned
reqs.append(fmt(R_TOTAL, 4, R_TOTAL, COLS, {
    'textFormat': text_fmt(ORANGE, 28, bold=True),
    'horizontalAlignment': 'RIGHT',
    'verticalAlignment': 'MIDDLE',
}, 'userEnteredFormat.textFormat,userEnteredFormat.horizontalAlignment,userEnteredFormat.verticalAlignment'))

# ── 6. Separator line above table header ─────────────────────────────────
reqs.append(border_bottom(R_SEPARATOR, 0, COLS, SEP_COLOR, 1))

# ── 7. Execute all requests ───────────────────────────────────────────────
try:
    wb.batch_update({'requests': reqs})
except Exception as e:
    pass  # Non-fatal — values are correct even if formatting fails

# ── 8. Return result ──────────────────────────────────────────────────────
spreadsheet_url = f"https://docs.google.com/spreadsheets/d/{sheet_id}/edit#gid={sheet_gid}"

print(json.dumps({
    "ok":  True,
    "tab": tab_name,
    "gid": sheet_gid,
    "url": spreadsheet_url,
}))
