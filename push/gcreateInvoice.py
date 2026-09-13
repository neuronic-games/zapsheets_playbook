# gcreateInvoice.py — create a formatted invoice tab in a Google Sheet.
#
# 8 columns A-H. Column A = left margin (empty, 25px).
# Content lives in columns B-H.
# Orange accent #e8623a. No gridlines. Tab: [{game}] invoice {num}.
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

game        = data.get('game',       '').strip()
client      = data.get('client',     '').strip()
quote_raw   = data.get('quote',      '').strip()
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
    """Prefix all non-formula strings with ' to prevent Sheets from
    auto-parsing phone numbers, dates, etc."""
    s = (str(v) if v is not None else '').strip()
    if not s:
        return ''
    if s.upper().startswith('=IMAGE('):
        return s
    return "'" + s

def fmt_date(s):
    """Convert date to MM/DD/YYYY string.
    Handles: YYYY-MM-DD, M/D/YYYY, Google Sheets serial numbers."""
    s = str(s).strip() if s else ''
    if not s:
        return ''
    # YYYY-MM-DD
    try:
        return datetime.strptime(s, '%Y-%m-%d').strftime('%m/%d/%Y')
    except ValueError:
        pass
    # Already MM/DD/YYYY or M/D/YYYY
    try:
        return datetime.strptime(s, '%m/%d/%Y').strftime('%m/%d/%Y')
    except ValueError:
        pass
    try:
        return datetime.strptime(s, '%#m/%#d/%Y').strftime('%m/%d/%Y')
    except ValueError:
        pass
    # Google Sheets date serial (days since 1899-12-30)
    try:
        serial = int(float(s))
        if 1000 <= serial <= 200000:
            d = date(1899, 12, 30) + timedelta(days=serial)
            return f"{d.month:02d}/{d.day:02d}/{d.year}"
    except (ValueError, TypeError):
        pass
    return s

def parse_quote(s):
    try:
        return float(re.sub(r'[^\d.]', '', s))
    except Exception:
        return None

def rgb(r, g, b):
    return {'red': r/255, 'green': g/255, 'blue': b/255}

# ── Palette ───────────────────────────────────────────────────────────────
ORANGE    = rgb(232, 98,  58)   # #e8623a
BLACK     = rgb(30,  30,  30)
GRAY_DARK = rgb(100, 100, 100)
GRAY_MED  = rgb(155, 155, 155)
ROW_BG    = rgb(245, 245, 245)  # light gray for data rows
WHITE     = rgb(255, 255, 255)
SEP       = rgb(200, 200, 200)  # separator line

# ── Derived values ────────────────────────────────────────────────────────
today_obj  = datetime.today()
today_disp = today_obj.strftime('%m/%d/%Y')
today_str  = today_obj.strftime('%Y-%m-%d')

quote_val = parse_quote(quote_raw)
quote_fmt = ('$' + f'{quote_val:,.2f}') if quote_val is not None else (quote_raw or '—')

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

# Invoice number: company initials + MMDD (e.g. TR0913)
comp = my_company or my_name
comp_initials = ''.join(w[0].upper() for w in re.split(r'\s+', comp) if w)[:2]
invoice_num = (comp_initials or 'INV') + today_obj.strftime('%m%d')

# Address lines (split on newline)
addr_lines = [l.strip() for l in my_address.replace('\r\n', '\n').split('\n') if l.strip()]
addr_line1 = addr_lines[0] if len(addr_lines) > 0 else ''
addr_line2 = addr_lines[1] if len(addr_lines) > 1 else ''

# ── Open spreadsheet ──────────────────────────────────────────────────────
try:
    wb = sa.open_by_key(sheet_id)
except Exception as e:
    print(json.dumps({"error": f"Could not open spreadsheet: {str(e)}"}))
    sys.exit(1)

# ── Tab name ──────────────────────────────────────────────────────────────
base_name = f"[{game}] invoice {invoice_num}" if game else f"invoice {invoice_num}"
tab_name  = base_name
existing  = {w.title for w in wb.worksheets()}
counter   = 2
while tab_name in existing:
    tab_name = f"{base_name} ({counter})"
    counter += 1

# ── Column layout ─────────────────────────────────────────────────────────
# A (0): left margin, EMPTY
# B (1): main wide column   — company name, description, "Prepared for"
# C (2): narrow spacer
# D (3): project/game column — "Project"
# E (4): qty column          — "Qty", part of "Estimate #"
# F (5): unit price column   — "Unit price"
# G (6): total column        — "Total price", subtotal amount
# H (7): right padding/total — logo right edge, total price right

TOTAL_COLS = 8  # A-H (0-7)

COL_W = {0: 25, 1: 250, 2: 20, 3: 105, 4: 65, 5: 90, 6: 90, 7: 55}
# Total ≈ 700px — fits letter page

# Named column aliases (0-indexed)
CA = 0  # margin
CB = 1  # B main
CC = 2  # C spacer
CD = 3  # D project
CE = 4  # E qty
CF = 5  # F unit
CG = 6  # G total/amount
CH = 7  # H right

# ── Row assignments (1-indexed) ───────────────────────────────────────────
R_TOP       = 1   # top margin (tiny)
R_COMPANY   = 2   # company name (B:D) | logo (E:H rows 2-5)
R_ADDR1     = 3
R_ADDR2     = 4
R_PHONE     = 5
R_SPACER1   = 6
R_INVOICE   = 7   # "Invoice" large heading
R_SUBMITTED = 8   # "Submitted on …"
R_SPACER2   = 9
R_LABELS    = 10  # Prepared for | Project | Estimate #
R_VALUES    = 11  # client name  | game Dev | invoice_num
R_SPACER3   = 12

if date_range:
    R_DUR_LABEL = 13
    R_DUR_VAL   = 14
    R_SPACER4   = 15
    R_THEAD     = 16
else:
    R_THEAD     = 13

R_DATA     = R_THEAD + 1
R_NOTESUB  = R_DATA  + 1   # notes (left) + subtotal (right) — SAME ROW
R_SPACER5  = R_NOTESUB + 1
R_TOTAL    = R_SPACER5 + 1

TOTAL_ROWS = R_TOTAL + 3

# ── Build value grid ──────────────────────────────────────────────────────
grid = [[''] * TOTAL_COLS for _ in range(TOTAL_ROWS)]

def sc(row1, col0, val):
    """Set cell (1-indexed row, 0-indexed col) with safe_str."""
    if 1 <= row1 <= len(grid) and 0 <= col0 < TOTAL_COLS:
        grid[row1 - 1][col0] = safe_str(val)

# Company name + logo
sc(R_COMPANY, CB, my_company or my_name)
if my_logo:
    grid[R_COMPANY - 1][CE] = f'=IMAGE("{my_logo}",1)'   # mode 1 = fit, preserve aspect ratio

# Address / phone
sc(R_ADDR1, CB, addr_line1)
sc(R_ADDR2, CB, addr_line2)
sc(R_PHONE, CB, my_phone)

# Invoice heading + submitted
sc(R_INVOICE,   CB, 'Invoice')
sc(R_SUBMITTED, CB, f'Submitted on {today_disp}')

# Info section
sc(R_LABELS, CB, 'Prepared for')
sc(R_LABELS, CD, 'Project')
sc(R_LABELS, CF, 'Estimate #')
sc(R_VALUES, CB, client or '—')
sc(R_VALUES, CD, (game + ' Dev') if game else '—')
sc(R_VALUES, CF, invoice_num)

# Duration
if date_range:
    sc(R_DUR_LABEL, CF, 'Duration')
    sc(R_DUR_VAL,   CF, date_range)

# Table header
sc(R_THEAD, CB, 'Description')
sc(R_THEAD, CE, 'Qty')
sc(R_THEAD, CF, 'Unit price')
sc(R_THEAD, CG, 'Total price')

# Data row
sc(R_DATA, CB, f'{game} Development' if game else 'Design services')
sc(R_DATA, CE, '1')
sc(R_DATA, CF, quote_fmt)
sc(R_DATA, CG, quote_fmt)

# Notes (left) + Subtotal (right) — same row
if notes:
    sc(R_NOTESUB, CB, notes)
sc(R_NOTESUB, CE, 'Subtotal')
sc(R_NOTESUB, CG, quote_fmt)

# Large total
sc(R_TOTAL, CG, quote_fmt)

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
    """0-indexed range (end exclusive)."""
    return {'sheetId': sheet_gid,
            'startRowIndex': r1, 'endRowIndex': r2,
            'startColumnIndex': c1, 'endColumnIndex': c2}

def merge(row1, c_start, c_end):
    """Merge a single row across columns c_start..c_end (0-indexed, end exclusive)."""
    return {'mergeCells': {
        'range': cr(r0(row1), c_start, r0(row1)+1, c_end),
        'mergeType': 'MERGE_ALL'
    }}

def merge_rows(row1_start, row1_end, c_start, c_end):
    """Merge multiple rows."""
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

def valign(row1_s, row1_e, c_s, c_e, align):
    return fmt(row1_s, row1_e, c_s, c_e,
               {'verticalAlignment': align}, 'userEnteredFormat.verticalAlignment')

# ── Build requests ────────────────────────────────────────────────────────
reqs = []

# 1. Hide gridlines and ensure no rows/columns are frozen
reqs.append({'updateSheetProperties': {
    'properties': {
        'sheetId': sheet_gid,
        'gridProperties': {
            'hideGridlines':    True,
            'frozenRowCount':   0,
            'frozenColumnCount': 0,
        }
    },
    'fields': 'gridProperties.hideGridlines,gridProperties.frozenRowCount,gridProperties.frozenColumnCount'
}})

# 2. Column widths
for ci, px in COL_W.items():
    reqs.append(col_w(ci, px))

# 3. Row heights
reqs.append(row_h(R_TOP,       6))
reqs.append(row_h(R_COMPANY,   42))
reqs.append(row_h(R_ADDR1,     18))
reqs.append(row_h(R_ADDR2,     18 if addr_line2 else 4))
reqs.append(row_h(R_PHONE,     20))
reqs.append(row_h(R_SPACER1,   18))
reqs.append(row_h(R_INVOICE,   60))
reqs.append(row_h(R_SUBMITTED, 22))
reqs.append(row_h(R_SPACER2,   16))
reqs.append(row_h(R_LABELS,    24))
reqs.append(row_h(R_VALUES,    28))
reqs.append(row_h(R_SPACER3,   14))
if date_range:
    reqs.append(row_h(R_DUR_LABEL, 22))
    reqs.append(row_h(R_DUR_VAL,   24))
    reqs.append(row_h(R_SPACER4,   16))
reqs.append(row_h(R_THEAD,    30))
reqs.append(row_h(R_DATA,     90))    # tall for wrapped multi-line description
reqs.append(row_h(R_NOTESUB,  28))
reqs.append(row_h(R_SPACER5,  12))
reqs.append(row_h(R_TOTAL,    46))

# 4. Merges
# Company name: B:D (CB to CD+1 = 1:4)
reqs.append(merge(R_COMPANY, CB, CD))
# Logo: E:H rows 2-5
if my_logo:
    reqs.append(merge_rows(R_COMPANY, R_PHONE, CE, TOTAL_COLS))
# "Invoice" heading: B:H
reqs.append(merge(R_INVOICE, CB, TOTAL_COLS))
# "Submitted on": B:H
reqs.append(merge(R_SUBMITTED, CB, TOTAL_COLS))
# Labels row
reqs.append(merge(R_LABELS, CB, CD))          # B:C "Prepared for"
reqs.append(merge(R_LABELS, CD, CF))          # D:E "Project"
reqs.append(merge(R_LABELS, CF, TOTAL_COLS))  # F:H "Estimate #"
# Values row
reqs.append(merge(R_VALUES, CB, CD))
reqs.append(merge(R_VALUES, CD, CF))
reqs.append(merge(R_VALUES, CF, TOTAL_COLS))
# Duration
if date_range:
    reqs.append(merge(R_DUR_LABEL, CF, TOTAL_COLS))
    reqs.append(merge(R_DUR_VAL,   CF, TOTAL_COLS))
# Table header
reqs.append(merge(R_THEAD, CB, CE))           # B:D "Description"
# Data row description
reqs.append(merge(R_DATA, CB, CE))            # B:D
# Notes + subtotal row
if notes:
    reqs.append(merge(R_NOTESUB, CB, CE))     # B:D notes (left)
reqs.append(merge(R_NOTESUB, CE, CG))         # E:F subtotal label
reqs.append(merge(R_NOTESUB, CG, TOTAL_COLS)) # G:H subtotal amount
# Total row
reqs.append(merge(R_TOTAL, CE, TOTAL_COLS))   # E:H large total

# 5. Text formatting

# Company name: orange, large bold
reqs.append(fmt(R_COMPANY, R_COMPANY, CB, CD, {
    'textFormat': tf(ORANGE, 20, bold=True),
    'verticalAlignment': 'MIDDLE',
}, 'userEnteredFormat.textFormat,userEnteredFormat.verticalAlignment'))

# Address lines: dark gray, small
for ar in [R_ADDR1, R_ADDR2, R_PHONE]:
    reqs.append(fmt(ar, ar, CB, CD if not my_logo else CF, {
        'textFormat': tf(GRAY_DARK, 9),
        'verticalAlignment': 'MIDDLE',
    }, 'userEnteredFormat.textFormat,userEnteredFormat.verticalAlignment'))

# "Invoice" heading: black, very large bold, bottom-aligned
reqs.append(fmt(R_INVOICE, R_INVOICE, CB, TOTAL_COLS, {
    'textFormat': tf(BLACK, 36, bold=True),
    'verticalAlignment': 'BOTTOM',
}, 'userEnteredFormat.textFormat,userEnteredFormat.verticalAlignment'))

# "Submitted on": dark gray, small bold
reqs.append(fmt(R_SUBMITTED, R_SUBMITTED, CB, TOTAL_COLS, {
    'textFormat': tf(GRAY_DARK, 10, bold=True),
}, 'userEnteredFormat.textFormat'))

# Labels (Prepared for / Project / Estimate #): bold black
reqs.append(fmt(R_LABELS, R_LABELS, CB, TOTAL_COLS, {
    'textFormat': tf(BLACK, 9, bold=True),
    'verticalAlignment': 'BOTTOM',
}, 'userEnteredFormat.textFormat,userEnteredFormat.verticalAlignment'))

# Values row: normal weight black
reqs.append(fmt(R_VALUES, R_VALUES, CB, TOTAL_COLS, {
    'textFormat': tf(BLACK, 10),
    'verticalAlignment': 'TOP',
}, 'userEnteredFormat.textFormat,userEnteredFormat.verticalAlignment'))

# Duration label: bold black; value: gray
if date_range:
    reqs.append(fmt(R_DUR_LABEL, R_DUR_LABEL, CF, TOTAL_COLS, {
        'textFormat': tf(BLACK, 10, bold=True),
        'verticalAlignment': 'BOTTOM',
    }, 'userEnteredFormat.textFormat,userEnteredFormat.verticalAlignment'))
    reqs.append(fmt(R_DUR_VAL, R_DUR_VAL, CF, TOTAL_COLS, {
        'textFormat': tf(GRAY_DARK, 10),
        'verticalAlignment': 'TOP',
    }, 'userEnteredFormat.textFormat,userEnteredFormat.verticalAlignment'))

# Table header: orange text, white bg, bold
reqs.append(fmt(R_THEAD, R_THEAD, CB, TOTAL_COLS, {
    'textFormat': tf(ORANGE, 9, bold=True),
    'backgroundColor': WHITE,
    'verticalAlignment': 'MIDDLE',
}, 'userEnteredFormat.textFormat,userEnteredFormat.backgroundColor,userEnteredFormat.verticalAlignment'))
# Right-align Qty / Unit / Total headers
reqs.append(halign(R_THEAD, R_THEAD, CE, TOTAL_COLS, 'RIGHT'))

# Data row: light gray bg, wrap, top-align text
reqs.append(fmt(R_DATA, R_DATA, CB, TOTAL_COLS, {
    'textFormat': tf(BLACK, 10),
    'backgroundColor': ROW_BG,
    'verticalAlignment': 'TOP',
    'wrapStrategy': 'WRAP',
}, 'userEnteredFormat.textFormat,userEnteredFormat.backgroundColor,userEnteredFormat.verticalAlignment,userEnteredFormat.wrapStrategy'))
# Right-align Qty / prices in data row
reqs.append(halign(R_DATA, R_DATA, CE, TOTAL_COLS, 'RIGHT'))
# Bottom border on data row
reqs.append(border_bottom(R_DATA, CB, TOTAL_COLS, SEP, 1))

# Notes (left of notes+subtotal row): italic gray
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

# Subtotal amount (G:H): bold black, right-aligned
reqs.append(fmt(R_NOTESUB, R_NOTESUB, CG, TOTAL_COLS, {
    'textFormat': tf(BLACK, 10, bold=True),
    'horizontalAlignment': 'RIGHT',
    'verticalAlignment': 'MIDDLE',
}, 'userEnteredFormat.textFormat,userEnteredFormat.horizontalAlignment,userEnteredFormat.verticalAlignment'))

# Large total (E:H): orange, large bold, right-aligned
reqs.append(fmt(R_TOTAL, R_TOTAL, CE, TOTAL_COLS, {
    'textFormat': tf(ORANGE, 28, bold=True),
    'horizontalAlignment': 'RIGHT',
    'verticalAlignment': 'MIDDLE',
}, 'userEnteredFormat.textFormat,userEnteredFormat.horizontalAlignment,userEnteredFormat.verticalAlignment'))

# Separator line: thin gray border on the row just above the table header
reqs.append(border_bottom(R_THEAD - 1, CB, TOTAL_COLS, SEP, 1))

# Execute
try:
    wb.batch_update({'requests': reqs})
except Exception:
    pass  # values are written even if formatting fails

spreadsheet_url = f"https://docs.google.com/spreadsheets/d/{sheet_id}/edit#gid={sheet_gid}"
print(json.dumps({"ok": True, "tab": tab_name, "gid": sheet_gid, "url": spreadsheet_url}))
