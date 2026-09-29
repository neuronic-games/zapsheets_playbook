# gcreateDocPdf.py — generate an invoice or estimate as a PDF file on the server.
#
# Saves to:   sheets/{sheet_id}/files/contracts/{sha1(type+num)[:12]}.pdf
# Updates:    Contracts Google Sheet "Files" column with the file URL
#
# Arg: {sheet_id}|{base64_json}
# JSON keys:
#   game, client, doc_id, doc_type ("Invoice"|"Estimate"), estimate_num
#   quote, notes, num_tests, num_edits, qty, unit_price, discount_pct, discount_label
#   my_name, my_phone, my_company, my_logo, my_address, my_payment
#   tgt_start (due date / target start), base_url
# Returns: { ok, file, url, hash }

import sys, os, json, base64, hashlib, urllib.request, tempfile, re, socket
from datetime import datetime, date, timedelta
from io import BytesIO

socket.setdefaulttimeout(20)

try:
    import reportlab  # noqa: F401
except ImportError:
    import subprocess, site as _site
    subprocess.run(
        [sys.executable, '-m', 'pip', 'install', 'reportlab', '--quiet'],
        check=True
    )

from reportlab.pdfgen import canvas
from reportlab.lib.pagesizes import letter
from reportlab.lib.utils import ImageReader
from reportlab.lib.colors import HexColor, white, black

import gspread

CRED_FILE = os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', 'credentials.json')

# ── Parse args ────────────────────────────────────────────────────────────────
raw = sys.argv[1] if len(sys.argv) > 1 else ''
parts = raw.split('|', 1)
if len(parts) < 2:
    print(json.dumps({"error": "Expected: sheet_id|base64_json"}))
    sys.exit(1)

sheet_id = parts[0].strip()
try:
    data = json.loads(base64.b64decode(parts[1]).decode('utf-8'))
except Exception as e:
    print(json.dumps({"error": f"Bad payload: {e}"}))
    sys.exit(1)

game          = data.get('game',          '').strip()
client        = data.get('client',        '').strip()
doc_id        = data.get('doc_id',        '').strip()
doc_type      = data.get('doc_type',      'Invoice').strip()   # "Invoice" or "Estimate"
estimate_num  = data.get('estimate_num',  '').strip()
quote_raw     = data.get('quote',         '').strip()
notes         = data.get('notes',         '').strip()
num_tests     = int(data.get('num_tests',  0) or 0)
num_edits     = int(data.get('num_edits',  0) or 0)
qty_raw       = data.get('qty',           '1').strip()
unit_price_raw= data.get('unit_price',    '').strip()
discount_pct  = float(data.get('discount_pct',  0) or 0)
discount_lbl  = data.get('discount_label','').strip()
scope_of_work = data.get('scope_of_work', '').strip()
description   = data.get('description',   '').strip()
my_name       = data.get('my_name',       '').strip()
my_phone      = data.get('my_phone',      '').strip()
my_company    = data.get('my_company',    '').strip()
my_logo       = data.get('my_logo',       '').strip()
my_address    = data.get('my_address',    '').strip()
my_payment    = data.get('my_payment',    '').strip()
tgt_start     = data.get('tgt_start',     '').strip()
tgt_end       = data.get('tgt_end',       '').strip()
duration      = data.get('duration',      '').strip()
base_url      = data.get('base_url',      '').strip()

# ── Helpers ────────────────────────────────────────────────────────────────────
def parse_money(s):
    try:
        return float(re.sub(r'[^\d.]', '', str(s)))
    except Exception:
        return None

def fmt_money(v):
    if v is None:
        return '—'
    if v < 0:
        return f'-${abs(v):,.2f}'
    return f'${v:,.2f}'

def fmt_date(s):
    s = str(s).strip() if s else ''
    if not s:
        return ''
    for fmt in ('%Y-%m-%d', '%m/%d/%Y'):
        try:
            return datetime.strptime(s, fmt).strftime('%m/%d/%Y')
        except ValueError:
            pass
    try:
        serial = int(float(s))
        if 1000 <= serial <= 200000:
            d = date(1899, 12, 30) + timedelta(days=serial)
            return f'{d.month:02d}/{d.day:02d}/{d.year}'
    except (ValueError, TypeError):
        pass
    return s

def pluralize(n, word):
    return f"{n} {word}{'s' if n != 1 else ''}"

def wrap_text(c_obj, text, font, size, max_width):
    """Split text into lines that fit within max_width, respecting existing newlines."""
    c_obj.setFont(font, size)
    result = []
    for paragraph in text.replace('\r\n', '\n').split('\n'):
        words = paragraph.split(' ')
        line = ''
        for word in words:
            test = (line + ' ' + word).strip()
            if c_obj.stringWidth(test, font, size) <= max_width:
                line = test
            else:
                if line:
                    result.append(line)
                line = word
        result.append(line)  # last line (may be empty for blank paragraphs)
    return result

today_obj  = datetime.today()
today_disp = today_obj.strftime('%m/%d/%Y')

doc_num = doc_id or today_obj.strftime('%Y%m%d')
due_date     = fmt_date(tgt_start)
due_date_end = fmt_date(tgt_end)
addr_lines = [l.strip() for l in my_address.replace('\r\n', '\n').split('\n') if l.strip()]

# Build single-line timeline string for the info section
timeline_parts = []
if due_date and due_date_end:
    timeline_parts.append(f'{due_date} – {due_date_end}')
elif due_date:
    timeline_parts.append(due_date)
elif due_date_end:
    timeline_parts.append(due_date_end)
if duration and duration not in timeline_parts:
    timeline_parts.append(f'({duration})')
timeline_str = '  '.join(timeline_parts) if timeline_parts else '—'

# ── Line items ─────────────────────────────────────────────────────────────────
line_items = []
disc_amt    = 0.0
subtotal_val = 0.0

if doc_type == 'Estimate':
    try:
        qty = float(qty_raw)
    except (ValueError, TypeError):
        qty = 1.0
    unit_price   = parse_money(unit_price_raw) or 0.0
    subtotal_val = qty * unit_price
    disc_amt     = round(subtotal_val * discount_pct / 100, 2) if discount_pct > 0 else 0.0
    total_val    = subtotal_val - disc_amt
    qty_disp     = str(int(qty)) if qty == int(qty) else str(qty)

    # Build estimate description — prefer scope_of_work if provided
    if scope_of_work:
        desc_lines = [l for l in scope_of_work.replace('\r\n', '\n').split('\n') if l.strip()]
    else:
        desc_lines = [f"{game} Development" if game else "Design Services",
                      "Includes (but not limited to):"]
        if num_tests > 0:
            desc_lines.append(f"- {pluralize(num_tests, 'organized test')}")
            desc_lines.append("- Test reports")
        if num_edits > 0:
            desc_lines.append(f"- {pluralize(num_edits, 'iteration')} of rules editing")

    # Discount row is handled in the totals section, not in line_items
    line_items.append({
        'desc': desc_lines, 'qty': qty_disp,
        'unit': fmt_money(unit_price) if unit_price else '',
        'total': fmt_money(subtotal_val),
    })
    quote_val    = total_val
    quote_fmt    = fmt_money(total_val)
    subtotal_fmt = fmt_money(subtotal_val)

else:  # Invoice
    quote_val    = parse_money(quote_raw)
    quote_fmt    = fmt_money(quote_val)
    subtotal_val = quote_val or 0.0
    subtotal_fmt = quote_fmt

    # Use explicit description if provided, else fall back to game name
    if description:
        desc_lines = [l for l in description.replace('\r\n', '\n').split('\n') if l.strip()]
    else:
        desc_lines = [game] if game else ['Design services']
    line_items.append({
        'desc': desc_lines, 'qty': '1',
        'unit': quote_fmt,
        'total': quote_fmt,
    })

# ── File path ──────────────────────────────────────────────────────────────────
file_key  = f"{doc_type}-{doc_num}"
file_hash = hashlib.sha1(file_key.encode()).hexdigest()[:12]
file_name = f"{file_hash}.pdf"

base_dir  = os.path.join(os.path.dirname(os.path.abspath(__file__)), '..')
files_dir = os.path.join(base_dir, 'sheets', sheet_id, 'files', 'contracts')
os.makedirs(files_dir, exist_ok=True)
file_path = os.path.join(files_dir, file_name)

rel_path = f"sheets/{sheet_id}/files/contracts/{file_name}"
file_url = (base_url.rstrip('/') + '/' + rel_path) if base_url else rel_path

# ── Colors ─────────────────────────────────────────────────────────────────────
ORANGE     = HexColor('#E8623A')
DARK       = HexColor('#1E1E1E')
MID_GRAY   = HexColor('#646464')
LIGHT_GRAY = HexColor('#F5F5F5')
SEP_GRAY   = HexColor('#CCCCCC')

# ── Page layout ────────────────────────────────────────────────────────────────
PAGE_W, PAGE_H = letter      # 612 × 792 pt
ML = 50                      # left margin
MR = 50                      # right margin
CW = PAGE_W - ML - MR        # content width = 512

def yt(from_top):
    """Reportlab y from top-of-page offset (pt)."""
    return PAGE_H - from_top

# ── Download logo ──────────────────────────────────────────────────────────────
logo_tmp = None
if my_logo:
    try:
        tf = tempfile.NamedTemporaryFile(suffix='.img', delete=False)
        tf.close()
        urllib.request.urlretrieve(my_logo, tf.name)
        logo_tmp = tf.name
    except Exception:
        logo_tmp = None

# ── Generate PDF ───────────────────────────────────────────────────────────────
buf = BytesIO()
c = canvas.Canvas(buf, pagesize=letter)
c.setTitle(f"{doc_type} {doc_num}")

# ── Orange top bar ──────────────────────────────────────────────────────────────
c.setFillColor(ORANGE)
c.rect(0, yt(8), PAGE_W, 8, stroke=0, fill=1)

# ── Logo (top-right) ────────────────────────────────────────────────────────────
LOGO_W = 90
LOGO_H = 80
LOGO_X = PAGE_W - MR - LOGO_W
LOGO_Y = yt(28 + LOGO_H)     # top of logo at from_top=28
if logo_tmp:
    try:
        c.drawImage(logo_tmp, LOGO_X, LOGO_Y, width=LOGO_W, height=LOGO_H,
                    preserveAspectRatio=True, anchor='nw', mask='auto')
    except Exception:
        pass

# ── Company header (left) ───────────────────────────────────────────────────────
c.setFont('Helvetica-Bold', 22)
c.setFillColor(ORANGE)
c.drawString(ML, yt(32), my_company or my_name)

c.setFont('Helvetica', 9)
c.setFillColor(MID_GRAY)
y_a = yt(46)
for line in addr_lines[:3]:
    c.drawString(ML, y_a, line)
    y_a -= 13
if my_phone:
    c.drawString(ML, y_a, my_phone)

# ── Document heading ────────────────────────────────────────────────────────────
c.setFont('Helvetica-Bold', 36)
c.setFillColor(DARK)
c.drawString(ML, yt(118), doc_type)

c.setFont('Helvetica', 10)
c.setFillColor(MID_GRAY)
c.drawString(ML, yt(143), f'Submitted on {today_disp}')

# ── Info section ────────────────────────────────────────────────────────────────
Y_LBL = yt(175)
Y_VAL = yt(190)

col_prep = ML
col_due  = ML + 195
col_num  = ML + 360

c.setFont('Helvetica-Bold', 9)
c.setFillColor(DARK)
c.drawString(col_prep, Y_LBL, 'Prepared for')
c.drawString(col_due,  Y_LBL, 'Due Date')
c.drawString(col_num,  Y_LBL, f'{doc_type} #')

c.setFont('Helvetica', 10)
c.setFillColor(DARK)
c.drawString(col_prep, Y_VAL, client or '—')

c.drawString(col_due, Y_VAL, timeline_str or '—')

if estimate_num:
    c.drawString(col_num, Y_VAL, f'{doc_num} (Est. {estimate_num})')
else:
    c.drawString(col_num, Y_VAL, doc_num)

# ── Separator ───────────────────────────────────────────────────────────────────
SEP_Y = yt(222)
c.setStrokeColor(SEP_GRAY)
c.setLineWidth(0.5)
c.line(ML, SEP_Y, PAGE_W - MR, SEP_Y)

# ── Table ───────────────────────────────────────────────────────────────────────
CELL_PAD  = 12   # horizontal padding inside table cells
T_LEFT    = ML
T_TOTAL_R = PAGE_W - MR       # right edge for right-aligned totals

# Estimate: 2 columns (Description | Total price)
# Invoice:  4 columns (Description | Qty | Unit price | Total price)
is_estimate = (doc_type == 'Estimate')
T_QTY   = ML + 310
T_UNIT  = ML + 370

HEADER_H  = 24
ROW_INNER = 13   # pt per text line
ROW_PAD   = 12   # vertical padding inside data rows

cur_y = yt(237)   # top of table header

# Header row
c.setFillColor(ORANGE)
c.rect(T_LEFT, cur_y - HEADER_H, CW, HEADER_H, stroke=0, fill=1)

c.setFont('Helvetica-Bold', 9)
c.setFillColor(white)
hy = cur_y - HEADER_H + 8
c.drawString(T_LEFT + CELL_PAD, hy, 'Description')
if not is_estimate:
    c.drawString(T_QTY,  hy, 'Qty')
    c.drawString(T_UNIT, hy, 'Unit price')
c.drawRightString(T_TOTAL_R - CELL_PAD, hy, 'Price')

cur_y -= HEADER_H

# Data rows
for item in line_items:
    desc_lines_item = item['desc']
    row_h = ROW_PAD + len(desc_lines_item) * ROW_INNER

    c.setFillColor(LIGHT_GRAY)
    c.rect(T_LEFT, cur_y - row_h, CW, row_h, stroke=0, fill=1)

    c.setFont('Helvetica', 9)
    c.setFillColor(DARK)
    dl_y = cur_y - ROW_PAD//2 - ROW_INNER + 2
    for dl in desc_lines_item:
        c.drawString(T_LEFT + CELL_PAD, dl_y, dl)
        dl_y -= ROW_INNER

    num_y = cur_y - ROW_PAD//2 - ROW_INNER + 2
    if not is_estimate:
        if item.get('qty'):
            c.drawString(T_QTY, num_y, item['qty'])
        if item.get('unit'):
            c.drawString(T_UNIT, num_y, item['unit'])
    c.drawRightString(T_TOTAL_R - CELL_PAD, num_y, item['total'])

    c.setStrokeColor(SEP_GRAY)
    c.setLineWidth(0.3)
    c.line(T_LEFT, cur_y - row_h, PAGE_W - MR, cur_y - row_h)

    cur_y -= row_h

# ── Totals section ─────────────────────────────────────────────────────────────
SUB_H       = 22
LABEL_X     = T_TOTAL_R - CELL_PAD - 130   # label left-edge, well clear of numbers

# Subtotal row
sub_y = cur_y - SUB_H
c.setFont('Helvetica', 9)
c.setFillColor(MID_GRAY)
c.drawString(LABEL_X, sub_y + 7, 'Subtotal')
c.setFont('Helvetica-Bold', 9)
c.setFillColor(DARK)
c.drawRightString(T_TOTAL_R - CELL_PAD, sub_y + 7, subtotal_fmt)
cur_y = sub_y

# Discount row (only if discount applied)
if disc_amt > 0:
    disc_row_y = cur_y - SUB_H
    disc_lbl = f"{discount_lbl or 'Discount'} ({int(discount_pct)}%)"
    c.setFont('Helvetica', 9)
    c.setFillColor(MID_GRAY)
    c.drawString(LABEL_X, disc_row_y + 7, disc_lbl)
    c.setFont('Helvetica-Bold', 9)
    c.setFillColor(DARK)
    c.drawRightString(T_TOTAL_R - CELL_PAD, disc_row_y + 7, fmt_money(-disc_amt))
    cur_y = disc_row_y

# Bottom border (no separate Total row — shown with large number below)
c.setStrokeColor(SEP_GRAY)
c.setLineWidth(0.5)
c.line(T_LEFT, cur_y - 2, PAGE_W - MR, cur_y - 2)

# ── Notes section ──────────────────────────────────────────────────────────────
if notes:
    notes_top = cur_y - 18
    c.setFont('Helvetica-Bold', 9)
    c.setFillColor(DARK)
    c.drawString(ML, notes_top, 'Notes')
    note_y = notes_top - 14
    note_line_h = 13
    for nl in wrap_text(c, notes, 'Helvetica', 9, CW):
        c.setFont('Helvetica', 9)
        c.setFillColor(MID_GRAY)
        if nl.strip():
            c.drawString(ML, note_y, nl)
            note_y -= note_line_h
        else:
            note_y -= note_line_h // 2   # blank line = half-height gap
    cur_y = note_y - 8

# ── Payment section + large total ──────────────────────────────────────────────
PAY_TOP = cur_y - 18

PAY_KEYWORDS = ('check', 'ach', 'zelle', 'wire', 'paypal', 'venmo')
pay_y = PAY_TOP - 12

if my_payment:
    pay_lines = my_payment.replace('\r\n', '\n').split('\n')
    for raw_line in pay_lines:
        stripped = raw_line.strip()
        if not stripped:
            pay_y -= 5
            continue
        is_label = any(stripped.lower().startswith(kw) for kw in PAY_KEYWORDS)
        if is_label:
            if pay_y < PAY_TOP - 12:
                pay_y -= 5
            c.setFont('Helvetica-Bold', 9)
            c.setFillColor(ORANGE)
        else:
            c.setFont('Helvetica', 9)
            c.setFillColor(DARK)
        c.drawString(ML, pay_y, stripped)
        pay_y -= 13

# Large total — "Total" label immediately left of big number
LARGE_TOTAL_Y = PAY_TOP - 40 if my_payment else cur_y - 35
num_str   = quote_fmt
num_width = c.stringWidth(num_str, 'Helvetica-Bold', 28)
num_right = T_TOTAL_R - CELL_PAD
num_left  = num_right - num_width
lbl_str   = 'Total  '
lbl_width = c.stringWidth(lbl_str, 'Helvetica-Bold', 18)
c.setFont('Helvetica-Bold', 18)
c.setFillColor(MID_GRAY)
c.drawString(num_left - lbl_width, LARGE_TOTAL_Y + 4, lbl_str)
c.setFont('Helvetica-Bold', 28)
c.setFillColor(ORANGE)
c.drawRightString(num_right, LARGE_TOTAL_Y, num_str)

# ── Save PDF ───────────────────────────────────────────────────────────────────
c.save()
pdf_bytes = buf.getvalue()
with open(file_path, 'wb') as f:
    f.write(pdf_bytes)

# Cleanup temp logo
if logo_tmp:
    try:
        os.unlink(logo_tmp)
    except Exception:
        pass

# ── Update Contracts sheet "Files" column ──────────────────────────────────────
sheet_error = None
if doc_id and os.path.exists(CRED_FILE):
    try:
        sa = gspread.service_account(filename=CRED_FILE)
        wb = sa.open_by_key(sheet_id)
        all_ws = wb.worksheets()
        ws = next((w for w in all_ws if w.title.lower() == 'contracts'), None)
        if ws:
            rows = ws.get_all_values()
            if rows:
                headers = [h.strip() for h in rows[0]]
                # Add "Files" header if missing
                if 'Files' not in headers:
                    files_col_idx = len(headers)
                    ws.update_cell(1, files_col_idx + 1, 'Files')
                    headers.append('Files')
                else:
                    files_col_idx = headers.index('Files')
                id_col = headers.index('ID') if 'ID' in headers else -1
                # Find matching row
                target_row = -1
                for i, row in enumerate(rows[1:], start=2):
                    row_id = str(row[id_col]).strip() if 0 <= id_col < len(row) else ''
                    if row_id == str(doc_id):
                        target_row = i
                        break
                if target_row > 0:
                    ws.update_cell(target_row, files_col_idx + 1, file_url)
    except Exception as e:
        sheet_error = str(e)

out = {"ok": True, "file": file_path, "url": file_url, "hash": file_hash,
       "doc_num": doc_num,
       "_debug_tgt_start": tgt_start, "_debug_due_date": due_date,
       "_debug_duration": duration, "_debug_timeline": timeline_str}
if sheet_error:
    out["sheet_error"] = sheet_error
print(json.dumps(out))
