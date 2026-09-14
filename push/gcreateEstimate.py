# gcreateEstimate.py — create a formatted estimate tab in a Google Sheet.
#
# Similar to gcreateInvoice.py but with:
#   - "Estimate" heading
#   - "Prepared for | Payable to | Estimate #" labels row
#   - "Project | Duration" second labels row
#   - Multi-line description built from num_tests + num_edits
#   - Optional discount line item
#
# Arg: {sheet_id}|{base64_json}

import gspread
import sys, os, json, base64, socket, re
from datetime import datetime, date, timedelta

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
    print(json.dumps({"error": "Expected: sheet_id|base64_json"}))
    sys.exit(1)

sheet_id = parts[0].strip()
try:
    data = json.loads(base64.b64decode(parts[1]).decode('utf-8'))
except Exception as e:
    print(json.dumps({"error": f"Bad payload: {str(e)}"}))
    sys.exit(1)

game          = data.get('game',          '').strip()
client        = data.get('client',        '').strip()
doc_id        = data.get('doc_id',        '').strip()   # pre-assigned ID from contracts sheet
num_tests     = int(data.get('num_tests',  2))
num_edits     = int(data.get('num_edits',  2))
qty_raw       = data.get('qty',           '1').strip()
unit_price_raw= data.get('unit_price',    '').strip()
duration      = data.get('duration',      '').strip()
discount_pct  = float(data.get('discount_pct',   0))
discount_lbl  = data.get('discount_label','').strip()
notes         = data.get('notes',         '').strip()
my_name       = data.get('my_name',       '').strip()
my_phone      = data.get('my_phone',      '').strip()
my_company    = data.get('my_company',    '').strip()
my_logo       = data.get('my_logo',       '').strip()
my_address    = data.get('my_address',    '').strip()

# ── Helpers ───────────────────────────────────────────────────────────────

def safe_str(v):
    s = (str(v) if v is not None else '').strip()
    if not s:
        return ''
    if s.upper().startswith('=IMAGE('):
        return s
    return "'" + s

def rgb(r, g, b):
    return {'red': r/255, 'green': g/255, 'blue': b/255}

# ── Palette ───────────────────────────────────────────────────────────────
ORANGE    = rgb(232, 98,  58)
BLACK     = rgb(30,  30,  30)
GRAY_DARK = rgb(100, 100, 100)
GRAY_MED  = rgb(155, 155, 155)
ROW_BG    = rgb(245, 245, 245)
WHITE     = rgb(255, 255, 255)
SEP       = rgb(200, 200, 200)
NAVY      = rgb(26,  52,  102)

# ── Derived values ────────────────────────────────────────────────────────
today_obj  = datetime.today()
today_disp = today_obj.strftime('%m/%d/%Y')

try:
    qty = float(qty_raw)
except (ValueError, TypeError):
    qty = 1.0

try:
    unit_price = float(re.sub(r'[^\d.]', '', unit_price_raw))
except Exception:
    unit_price = 0.0

subtotal = qty * unit_price

if discount_pct > 0 and subtotal > 0:
    discount_amount = round(subtotal * discount_pct / 100, 2)
else:
    discount_amount = 0.0

total = subtotal - discount_amount

def fmt_money(v):
    if v < 0:
        return f"-${abs(v):,.2f}"
    return f"${v:,.2f}"

subtotal_fmt = fmt_money(subtotal)
total_fmt    = fmt_money(total)
qty_disp     = str(int(qty)) if qty == int(qty) else str(qty)

# Estimate number: use doc_id if provided, else generate
comp = my_company or my_name
comp_initials = ''.join(w[0].upper() for w in re.split(r'\s+', comp) if w)[:2]
est_num_base = doc_id if doc_id else (comp_initials or 'EST') + today_obj.strftime('%m%d') + '-E'

# Address lines
addr_lines = [l.strip() for l in my_address.replace('\r\n', '\n').split('\n') if l.strip()]
addr_line1 = addr_lines[0] if len(addr_lines) > 0 else ''
addr_line2 = addr_lines[1] if len(addr_lines) > 1 else ''

# Payable-to display: initials if company known, else full name
payable_to = comp_initials if comp_initials else (my_name or my_company)

# Description for main line item
def pluralize(n, word):
    return f"{n} {word}{'s' if n != 1 else ''}"

desc_lines = []
if game:
    desc_lines.append(f"{game} Development")
else:
    desc_lines.append("Design Services")
desc_lines.append("Includes (but not limited to):")
if num_tests > 0:
    desc_lines.append(f"- {pluralize(num_tests, 'organized test')}")
    desc_lines.append("- Test reports")
if num_edits > 0:
    desc_lines.append(f"- {pluralize(num_edits, 'iteration')} of rules editing")
description = "\n".join(desc_lines)

# Line items list: [{'desc','qty','unit','total','is_discount'}]
line_items = []
line_items.append({
    'desc': description, 'qty': qty_disp,
    'unit': fmt_money(unit_price) if unit_price else '',
    'total': subtotal_fmt, 'is_discount': False,
})
if discount_amount > 0:
    disc_label = f"{discount_lbl or 'Discount'} ({int(discount_pct)}%)"
    line_items.append({
        'desc': disc_label, 'qty': '', 'unit': '',
        'total': fmt_money(-discount_amount), 'is_discount': True,
    })

# ── Open spreadsheet ──────────────────────────────────────────────────────
try:
    wb = sa.open_by_key(sheet_id)
except Exception as e:
    print(json.dumps({"error": f"Could not open spreadsheet: {str(e)}"}))
    sys.exit(1)

existing_titles = {w.title for w in wb.worksheets()}

# Resolve estimate number (avoid duplicate tab names)
est_num  = est_num_base
base_tab = f"[{game}] estimate {est_num}" if game else f"estimate {est_num}"
tab_name = base_tab
counter  = 2
while tab_name in existing_titles:
    est_num  = est_num_base + str(counter)
    tab_name = f"[{game}] estimate {est_num}" if game else f"estimate {est_num}"
    counter += 1

# ── Column layout ─────────────────────────────────────────────────────────
TOTAL_COLS  = 8   # A-H (0-7)
CONTENT_END = 7   # exclusive (content B-G; H is right margin)
COL_W = {0: 25, 1: 250, 2: 20, 3: 105, 4: 65, 5: 90, 6: 90, 7: 25}
CA = 0; CB = 1; CC = 2; CD = 3; CE = 4; CF = 5; CG = 6; CH = 7

# ── Row assignments (1-indexed) ───────────────────────────────────────────
R_TOP       = 1
R_COMPANY   = 2
R_ADDR1     = 3
R_ADDR2     = 4
R_PHONE     = 5
R_SPACER1   = 6
R_HEADING   = 7   # "Estimate"
R_SUBMITTED = 8
R_SPACER2   = 9
R_LABELS1   = 10  # Prepared for | Payable to | Estimate #
R_VALUES1   = 11
R_SPACER3   = 12
R_LABELS2   = 13  # (blank) | Project | Duration
R_VALUES2   = 14
R_SPACER4   = 15
R_THEAD     = 16

# Data rows start at 17
R_DATA_START = R_THEAD + 1
R_DATA_END   = R_DATA_START + len(line_items) - 1

R_NOTESUB = R_DATA_END + 1
R_SPACER5 = R_NOTESUB + 1
R_TOTAL   = R_SPACER5 + 1
TOTAL_ROWS = R_TOTAL + 3

# ── Build value grid ──────────────────────────────────────────────────────
grid = [[''] * TOTAL_COLS for _ in range(TOTAL_ROWS)]

def sc(row1, col0, val):
    if 1 <= row1 <= len(grid) and 0 <= col0 < TOTAL_COLS:
        grid[row1 - 1][col0] = safe_str(val)

# Company name + logo
sc(R_COMPANY, CB, my_company or my_name)
if my_logo:
    grid[R_COMPANY - 1][CE] = f'=IMAGE("{my_logo}",1)'

# Address / phone
sc(R_ADDR1, CB, addr_line1)
sc(R_ADDR2, CB, addr_line2)
sc(R_PHONE, CB, my_phone)

# Heading + submitted
sc(R_HEADING,   CB, 'Estimate')
sc(R_SUBMITTED, CB, f'Submitted on {today_disp}')

# Labels row 1
sc(R_LABELS1, CB, 'Prepared for')
sc(R_LABELS1, CD, 'Payable to')
sc(R_LABELS1, CF, 'Estimate #')
# Values row 1
sc(R_VALUES1, CB, client or '—')
sc(R_VALUES1, CD, payable_to)
sc(R_VALUES1, CF, est_num)

# Labels row 2
sc(R_LABELS2, CD, 'Project')
if duration:
    sc(R_LABELS2, CF, 'Duration')
# Values row 2
sc(R_VALUES2, CD, game or '—')
if duration:
    sc(R_VALUES2, CF, duration)

# Table header
sc(R_THEAD, CB, 'Description')
sc(R_THEAD, CE, 'Qty')
sc(R_THEAD, CF, 'Unit price')
sc(R_THEAD, CG, 'Total price')

# Data rows
for i, item in enumerate(line_items):
    row = R_DATA_START + i
    sc(row, CB, item['desc'])
    if item['qty']:  sc(row, CE, item['qty'])
    if item['unit']: sc(row, CF, item['unit'])
    sc(row, CG, item['total'])

# Notes + subtotal row
if notes:
    sc(R_NOTESUB, CB, f"({notes})")
sc(R_NOTESUB, CE, 'Subtotal')
sc(R_NOTESUB, CG, total_fmt)

# Large total
sc(R_TOTAL, CG, total_fmt)

# ── Create worksheet ──────────────────────────────────────────────────────
try:
    ws = wb.add_worksheet(title=tab_name, rows=TOTAL_ROWS + 4, cols=TOTAL_COLS)
except Exception as e:
    print(json.dumps({"error": f"Could not create worksheet: {str(e)}"}))
    sys.exit(1)

sheet_gid = ws.id

try:
    ws.update(values=grid, range_name='A1', value_input_option='USER_ENTERED')
except Exception as e:
    try: wb.del_worksheet(ws)
    except Exception: pass
    print(json.dumps({"error": f"Could not write values: {str(e)}"}))
    sys.exit(1)

# ── Formatting helpers ────────────────────────────────────────────────────
def r0(row1): return row1 - 1

def cr(r1, c1, r2, c2):
    return {'sheetId': sheet_gid,
            'startRowIndex': r1, 'endRowIndex': r2,
            'startColumnIndex': c1, 'endColumnIndex': c2}

def merge(row1, c_start, c_end):
    return {'mergeCells': {
        'range': cr(r0(row1), c_start, r0(row1)+1, c_end),
        'mergeType': 'MERGE_ALL'
    }}

def merge_rows(row1_start, row1_end, c_start, c_end):
    return {'mergeCells': {
        'range': cr(r0(row1_start), c_start, r0(row1_end)+1, c_end),
        'mergeType': 'MERGE_ALL'
    }}

def fmt(row1_start, row1_end, c_start, c_end, cell_fmt, fields):
    return {'repeatCell': {
        'range': cr(r0(row1_start), c_start, r0(row1_end)+1, c_end),
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

def border_bottom(row1, c_start, c_end, color=None, width=1):
    c = color or SEP
    return {'updateBorders': {
        'range': cr(r0(row1), c_start, r0(row1)+1, c_end),
        'bottom': {'style': 'SOLID', 'width': width, 'color': c}
    }}

def tf(color, size, bold=False, italic=False):
    return {'foregroundColor': color, 'fontSize': size,
            'bold': bold, 'italic': italic, 'fontFamily': 'Arial'}

def halign(row1_s, row1_e, c_s, c_e, align):
    return fmt(row1_s, row1_e, c_s, c_e,
               {'horizontalAlignment': align}, 'userEnteredFormat.horizontalAlignment')

# ── Build requests ────────────────────────────────────────────────────────
reqs = []

# 1. Hide gridlines, no frozen rows
reqs.append({'updateSheetProperties': {
    'properties': {
        'sheetId': sheet_gid,
        'gridProperties': {
            'hideGridlines':     True,
            'frozenRowCount':    0,
            'frozenColumnCount': 0,
        }
    },
    'fields': 'gridProperties.hideGridlines,gridProperties.frozenRowCount,gridProperties.frozenColumnCount'
}})

# 2. Column widths
for ci, px in COL_W.items():
    reqs.append(col_w(ci, px))

# 2b. Blue bar
reqs.append(fmt(R_TOP, R_TOP, CA, TOTAL_COLS, {
    'backgroundColor': NAVY,
}, 'userEnteredFormat.backgroundColor'))

# 3. Row heights
reqs.append(row_h(R_TOP,       10))
reqs.append(row_h(R_COMPANY,   72))
reqs.append(row_h(R_ADDR1,     24))
reqs.append(row_h(R_ADDR2,     24 if addr_line2 else 4))
reqs.append(row_h(R_PHONE,     24))
reqs.append(row_h(R_SPACER1,   18))
reqs.append(row_h(R_HEADING,   60))
reqs.append(row_h(R_SUBMITTED, 22))
reqs.append(row_h(R_SPACER2,   16))
reqs.append(row_h(R_LABELS1,   22))
reqs.append(row_h(R_VALUES1,   28))
reqs.append(row_h(R_SPACER3,   10))
reqs.append(row_h(R_LABELS2,   22))
reqs.append(row_h(R_VALUES2,   28))
reqs.append(row_h(R_SPACER4,   16))
reqs.append(row_h(R_THEAD,     30))
for i in range(len(line_items)):
    row = R_DATA_START + i
    # Main service row is taller (multi-line description); discount row is normal
    reqs.append(row_h(row, 90 if i == 0 else 28))
reqs.append(row_h(R_NOTESUB, 28))
reqs.append(row_h(R_SPACER5, 12))
reqs.append(row_h(R_TOTAL,   46))

# 4. Merges
# Company name
reqs.append(merge(R_COMPANY, CB, CD))
# Logo
if my_logo:
    reqs.append(merge_rows(R_COMPANY, R_PHONE, CE, CONTENT_END))
# Heading + submitted
reqs.append(merge(R_HEADING,   CB, CONTENT_END))
reqs.append(merge(R_SUBMITTED, CB, CONTENT_END))
# Labels row 1: Prepared for (B:C) | Payable to (D:E) | Estimate # (F:G)
reqs.append(merge(R_LABELS1, CB, CD))
reqs.append(merge(R_LABELS1, CD, CF))
reqs.append(merge(R_LABELS1, CF, CONTENT_END))
# Values row 1
reqs.append(merge(R_VALUES1, CB, CD))
reqs.append(merge(R_VALUES1, CD, CF))
reqs.append(merge(R_VALUES1, CF, CONTENT_END))
# Labels row 2: Project (D:E) | Duration (F:G)
reqs.append(merge(R_LABELS2, CD, CF))
reqs.append(merge(R_LABELS2, CF, CONTENT_END))
# Values row 2
reqs.append(merge(R_VALUES2, CD, CF))
reqs.append(merge(R_VALUES2, CF, CONTENT_END))
# Table header
reqs.append(merge(R_THEAD, CB, CE))
# Data rows
for i in range(len(line_items)):
    row = R_DATA_START + i
    reqs.append(merge(row, CB, CE))   # B:D description
# Notes + subtotal row
if notes:
    reqs.append(merge(R_NOTESUB, CB, CE))
reqs.append(merge(R_NOTESUB, CE, CG))         # E:F subtotal label
reqs.append(merge(R_NOTESUB, CG, CONTENT_END)) # G: subtotal amount
# Total row
reqs.append(merge(R_TOTAL, CE, CONTENT_END))

# 5. Text formatting

# Company name
reqs.append(fmt(R_COMPANY, R_COMPANY, CB, CD, {
    'textFormat': tf(ORANGE, 20, bold=True),
    'verticalAlignment': 'MIDDLE',
}, 'userEnteredFormat.textFormat,userEnteredFormat.verticalAlignment'))

# Address / phone
addr_end = CF if my_logo else CD
for ar in [R_ADDR1, R_ADDR2, R_PHONE]:
    reqs.append(fmt(ar, ar, CB, addr_end, {
        'textFormat': tf(GRAY_DARK, 9),
        'verticalAlignment': 'MIDDLE',
    }, 'userEnteredFormat.textFormat,userEnteredFormat.verticalAlignment'))

# Logo
if my_logo:
    reqs.append(fmt(R_COMPANY, R_PHONE, CE, CONTENT_END, {
        'horizontalAlignment': 'RIGHT',
        'verticalAlignment':   'MIDDLE',
    }, 'userEnteredFormat.horizontalAlignment,userEnteredFormat.verticalAlignment'))

# "Estimate" heading
reqs.append(fmt(R_HEADING, R_HEADING, CB, CONTENT_END, {
    'textFormat': tf(BLACK, 36, bold=True),
    'verticalAlignment': 'BOTTOM',
}, 'userEnteredFormat.textFormat,userEnteredFormat.verticalAlignment'))

# "Submitted on"
reqs.append(fmt(R_SUBMITTED, R_SUBMITTED, CB, CONTENT_END, {
    'textFormat': tf(GRAY_DARK, 10, bold=True),
}, 'userEnteredFormat.textFormat'))

# Labels row 1 (Prepared for / Payable to / Estimate #)
reqs.append(fmt(R_LABELS1, R_LABELS1, CB, CONTENT_END, {
    'textFormat': tf(BLACK, 9, bold=True),
    'verticalAlignment': 'BOTTOM',
}, 'userEnteredFormat.textFormat,userEnteredFormat.verticalAlignment'))

# Values row 1
reqs.append(fmt(R_VALUES1, R_VALUES1, CB, CONTENT_END, {
    'textFormat': tf(BLACK, 10),
    'verticalAlignment': 'TOP',
}, 'userEnteredFormat.textFormat,userEnteredFormat.verticalAlignment'))

# Labels row 2 (Project / Duration)
reqs.append(fmt(R_LABELS2, R_LABELS2, CD, CONTENT_END, {
    'textFormat': tf(BLACK, 9, bold=True),
    'verticalAlignment': 'BOTTOM',
}, 'userEnteredFormat.textFormat,userEnteredFormat.verticalAlignment'))

# Values row 2
reqs.append(fmt(R_VALUES2, R_VALUES2, CD, CONTENT_END, {
    'textFormat': tf(BLACK, 10),
    'verticalAlignment': 'TOP',
}, 'userEnteredFormat.textFormat,userEnteredFormat.verticalAlignment'))

# Table header: orange, bold, white bg
reqs.append(fmt(R_THEAD, R_THEAD, CB, CONTENT_END, {
    'textFormat': tf(ORANGE, 9, bold=True),
    'backgroundColor': WHITE,
    'verticalAlignment': 'MIDDLE',
}, 'userEnteredFormat.textFormat,userEnteredFormat.backgroundColor,userEnteredFormat.verticalAlignment'))
reqs.append(halign(R_THEAD, R_THEAD, CE, CONTENT_END, 'RIGHT'))

# Data rows
for i, item in enumerate(line_items):
    row = R_DATA_START + i
    bg = ROW_BG if i % 2 == 0 else WHITE
    reqs.append(fmt(row, row, CB, CONTENT_END, {
        'textFormat': tf(BLACK, 10),
        'backgroundColor': bg,
        'verticalAlignment': 'TOP',
        'wrapStrategy': 'WRAP',
    }, 'userEnteredFormat.textFormat,userEnteredFormat.backgroundColor,userEnteredFormat.verticalAlignment,userEnteredFormat.wrapStrategy'))
    reqs.append(halign(row, row, CE, CONTENT_END, 'RIGHT'))
    # Discount row: italic gray for description
    if item['is_discount']:
        reqs.append(fmt(row, row, CB, CE, {
            'textFormat': tf(GRAY_DARK, 10, italic=True),
        }, 'userEnteredFormat.textFormat'))

# Bottom border on last data row
reqs.append(border_bottom(R_DATA_END, CB, CONTENT_END, SEP, 1))

# Notes (italic gray, left)
if notes:
    reqs.append(fmt(R_NOTESUB, R_NOTESUB, CB, CE, {
        'textFormat': tf(GRAY_MED, 9, italic=True),
        'verticalAlignment': 'MIDDLE',
    }, 'userEnteredFormat.textFormat,userEnteredFormat.verticalAlignment'))

# Subtotal label (E:F): gray, right-aligned
reqs.append(fmt(R_NOTESUB, R_NOTESUB, CE, CG, {
    'textFormat': tf(GRAY_DARK, 10),
    'horizontalAlignment': 'RIGHT',
    'verticalAlignment': 'MIDDLE',
}, 'userEnteredFormat.textFormat,userEnteredFormat.horizontalAlignment,userEnteredFormat.verticalAlignment'))

# Subtotal amount (G): bold black, right-aligned
reqs.append(fmt(R_NOTESUB, R_NOTESUB, CG, CONTENT_END, {
    'textFormat': tf(BLACK, 10, bold=True),
    'horizontalAlignment': 'RIGHT',
    'verticalAlignment': 'MIDDLE',
}, 'userEnteredFormat.textFormat,userEnteredFormat.horizontalAlignment,userEnteredFormat.verticalAlignment'))

# Large total: orange, big, right-aligned
reqs.append(fmt(R_TOTAL, R_TOTAL, CE, CONTENT_END, {
    'textFormat': tf(ORANGE, 28, bold=True),
    'horizontalAlignment': 'RIGHT',
    'verticalAlignment': 'MIDDLE',
}, 'userEnteredFormat.textFormat,userEnteredFormat.horizontalAlignment,userEnteredFormat.verticalAlignment'))

# Separator above table header
reqs.append(border_bottom(R_THEAD - 1, CB, CONTENT_END, SEP, 1))

# Execute
try:
    wb.batch_update({'requests': reqs})
except Exception:
    pass  # values already written; formatting failure is non-fatal

spreadsheet_url = f"https://docs.google.com/spreadsheets/d/{sheet_id}/edit#gid={sheet_gid}"
print(json.dumps({
    "ok":           True,
    "tab":          tab_name,
    "gid":          sheet_gid,
    "url":          spreadsheet_url,
    "estimate_num": est_num,
    "amount":       round(total, 2),
}))
