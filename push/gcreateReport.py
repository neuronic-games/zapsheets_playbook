# gcreateReport.py — create a formatted .rtf playtest report from DevBoard session data.
#
# Arg: {sheet_id}|{base64_json}
# JSON keys: game, client, my_name, my_company, my_address, my_email, my_phone, ref_code
# Returns: { ok, b64, filename, title }  (b64 = base64-encoded .rtf bytes)
# No external dependencies beyond stdlib + gspread.

import sys, os, json, base64, socket, re, struct
from datetime import datetime

socket.setdefaulttimeout(20)

CRED_FILE = os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', 'credentials.json')

# ── Parse args ───────────────────────────────────────────────────────────────
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

game       = data.get('game',       '').strip()
client     = data.get('client',     '').strip()
my_name    = data.get('my_name',    '').strip()
my_company = data.get('my_company', '').strip()
my_address = data.get('my_address', '').strip()
my_email   = data.get('my_email',   '').strip()
my_phone   = data.get('my_phone',   '').strip()
my_logo    = data.get('my_logo',    '').strip()
ref_code   = data.get('ref_code',   '').strip()

if not game:
    print(json.dumps({"error": "game is required"}))
    sys.exit(1)

# ── Load session data ─────────────────────────────────────────────────────────
import gspread

base_dir = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
dev_file = os.path.join(base_dir, 'sheets', sheet_id, f'[{game}] dev.json')

if os.path.exists(dev_file):
    with open(dev_file, 'r', encoding='utf-8') as f:
        rows = json.load(f)
elif not os.path.isdir(os.path.join(base_dir, 'sheets', sheet_id)):
    print(json.dumps({"error": f"Sheet directory not found for id: {sheet_id}"}))
    sys.exit(1)
else:
    if not os.path.exists(CRED_FILE):
        print(json.dumps({"error": "credentials.json not found"}))
        sys.exit(1)
    tab_name = f'[{game}] dev'
    try:
        sa = gspread.service_account(filename=CRED_FILE)
        wb = sa.open_by_key(sheet_id)
        ws = next((w for w in wb.worksheets() if w.title == tab_name), None)
        if ws is None:
            all_titles = [w.title for w in wb.worksheets()]
            print(json.dumps({"error": f"No dev tab found for: {game} (tabs: {all_titles})"}))
            sys.exit(1)
        all_vals = ws.get_all_values(value_render_option='FORMATTED_VALUE')
        rows = []
        if all_vals:
            headers = all_vals[0]
            rows = [dict(zip(headers, r)) for r in all_vals[1:]]
    except Exception as e:
        print(json.dumps({"error": f"Sheet read failed: {e}"}))
        sys.exit(1)

# ── Build sessions ────────────────────────────────────────────────────────────
sessions = []
current  = None

for row in rows:
    date   = str(row.get('Date',         '') or '').strip()
    event  = str(row.get('Event',        '') or '').strip()
    people = str(row.get('People',       '') or '').strip()
    obs    = str(row.get('Observations', '') or row.get('Observation', '') or '').strip()
    sol    = str(row.get('Solutions',    '') or row.get('Thoughts',    '') or row.get('Solution', '') or '').strip()

    if date or event:
        if event in ('Time', 'Material'):
            current = None
            continue
        label   = event + (' ' + people if people else '')
        current = {'date': date, 'testnum': label, 'event': event,
                   'location': obs, 'length': sol, 'testers': [], 'obs': []}
        sessions.append(current)
    elif current:
        if people:
            if people.startswith('[sub:') and people.endswith(']'):
                current['submittedBy'] = people[5:-1]
            else:
                tname = people.split('@')[0].strip() if '@' in people else people
                current['testers'].append(tname)
        elif obs or sol:
            current['obs'].append({'obs': obs, 'sol': sol})

# ── Counts for SCOPE ──────────────────────────────────────────────────────────
IMAGE_RE = re.compile(r'=IMAGE\s*\(\s*["\']?(https?[^"\')\s]+)["\']?\s*\)', re.IGNORECASE)

playtest_count = sum(1 for s in sessions
                     if 'rule' not in s['event'].lower()
                     and s['event'].lower() not in ('time', 'material'))
rules_count    = sum(1 for s in sessions
                     if 'rule' in s['event'].lower())

# Collect ALL obs/sol entries and ALL image URLs across all sessions
all_obs    = []   # {'obs': str, 'sol': str}
all_images = []   # image URL strings (preserve order)

for s in sessions:
    if 'rule' not in s['event'].lower():
        for entry in s['obs']:
            all_obs.append(entry)
            for m in IMAGE_RE.finditer(entry.get('obs', '')):
                url = m.group(1)
                if url not in all_images:
                    all_images.append(url)
            for m in IMAGE_RE.finditer(entry.get('sol', '')):
                url = m.group(1)
                if url not in all_images:
                    all_images.append(url)

# ── RTF helpers ───────────────────────────────────────────────────────────────
def rtf_escape(s):
    out = []
    for ch in str(s or ''):
        cp = ord(ch)
        if   ch == '\\': out.append('\\\\')
        elif ch == '{':  out.append('\\{')
        elif ch == '}':  out.append('\\}')
        elif ch == '\n': out.append('\\line ')
        elif cp < 128:   out.append(ch)
        else:
            signed = cp if cp < 32768 else cp - 65536
            out.append(f'\\u{signed}?')
    return ''.join(out)

def strip_images(text):
    """Return text with =IMAGE(...) removed."""
    return IMAGE_RE.sub('', text).strip()

def fetch_image(url):
    try:
        import urllib.request
        req = urllib.request.Request(url, headers={'User-Agent': 'Mozilla/5.0'})
        with urllib.request.urlopen(req, timeout=10) as resp:
            data = resp.read()
            ct   = resp.headers.get('Content-Type', '')
        fmt = 'png' if 'png' in ct.lower() else 'jpeg'
        if data[:8] == b'\x89PNG\r\n\x1a\n': fmt = 'png'
        elif data[:2] == b'\xff\xd8':         fmt = 'jpeg'
        return data, fmt
    except Exception:
        return None, None

def image_dims(data, fmt):
    try:
        if fmt == 'png' and data[:8] == b'\x89PNG\r\n\x1a\n':
            return struct.unpack('>II', data[16:24])
        if fmt == 'jpeg':
            i = 2
            while i < len(data) - 9:
                if data[i] != 0xFF: break
                marker  = data[i + 1]
                seg_len = struct.unpack('>H', data[i+2:i+4])[0]
                if marker in (0xC0, 0xC1, 0xC2):
                    h, w = struct.unpack('>HH', data[i+5:i+9])
                    return w, h
                i += 2 + seg_len
    except Exception:
        pass
    return None, None

def rtf_pict(img_data, fmt, goal_twips=7200):
    w, h = image_dims(img_data, fmt)
    tag  = '\\pngblip' if fmt == 'png' else '\\jpegblip'
    if w and h:
        gh = int(goal_twips * h / w)
        dims = f'\\picw{w}\\pich{h}\\picwgoal{goal_twips}\\pichgoal{gh}'
    else:
        dims = f'\\picwgoal{goal_twips}'
    return '{\\pict' + tag + dims + '\n' + img_data.hex() + '}'

# ── Compose RTF ───────────────────────────────────────────────────────────────
today    = datetime.today()
date_str = today.strftime('%b ') + str(today.day) + today.strftime(', %Y')

doc_title = f"{game} — Playtest Report"
if ref_code:
    doc_title += f" ({ref_code})"

L = []   # RTF lines

# ── RTF preamble ─────────────────────────────────────────────────────────────
L += [
    r'{\rtf1\ansi\ansicpg1252\deff0\deflang1033',
    r'{\fonttbl{\f0\fswiss\fcharset0 Arial;}}',
    # Color table: 1=teal, 2=gray, 3=dark, 4=lightgray bg
    r'{\colortbl ;\red26\green95\blue122;\red102\green102\blue102;\red17\green17\blue17;\red245\green245\blue245;}',
    r'\paperw12240\paperh15840\margl1440\margr1440\margt1080\margb1080',
    r'\widowctrl',
]

# ── Fetch logo image (if available) ──────────────────────────────────────────
logo_data, logo_fmt = (None, None)
if my_logo:
    logo_data, logo_fmt = fetch_image(my_logo)

# ── Header table: left=company/address, center=date/ref/prepared-by, right=logo
# Widths: 5400 (3.75") | 3600 (2.5") | 1800 (1.25") = 10800 total
L.append(r'\trowd\trgaph0\trleft0\trpaddl108\trpaddr108\trpaddt60\trpaddb60')
L.append(r'\clvertalt\cellx5400')   # left: company/address
L.append(r'\clvertalt\cellx9000')   # center: date/ref/prepared-by
L.append(r'\clvertalt\cellx10800')  # right: logo

# Left cell: company name + address
left = r'\pard\intbl\f0\fs40\b\cf1 ' + rtf_escape(my_company or my_name) + r'\b0\par'
if my_address:
    for line in my_address.replace('\r\n', '\n').split('\n'):
        line = line.strip()
        if line:
            left += r'\pard\intbl\f0\fs18\cf2 ' + rtf_escape(line) + r'\par'
if my_phone:
    left += r'\pard\intbl\f0\fs18\cf2 ' + rtf_escape(my_phone) + r'\par'
left += r'\cell'

# Center cell: date, ref, prepared by
center  = r'\pard\intbl\f0\fs20\cf3 \b Date:\b0  ' + rtf_escape(date_str) + r'\par'
if ref_code:
    center += r'\pard\intbl\f0\fs20\cf3 \b Ref:\b0  ' + rtf_escape(ref_code) + r'\par'
if my_name:
    center += r'\pard\intbl\f0\fs20\cf3 \b Prepared by:\b0  ' + rtf_escape(my_name) + r'\par'
center += r'\cell'

# Right cell: logo image or blank
if logo_data:
    logo_cell = r'\pard\intbl\qr ' + rtf_pict(logo_data, logo_fmt, goal_twips=1440) + r'\par\cell'
else:
    logo_cell = r'\pard\intbl\cell'

L += [left, center, logo_cell, r'\row']

# Spacer
L.append(r'\pard\sb80\par')

# ── Info table: CLIENT / GAME / SCOPE ─────────────────────────────────────────
# Thin black border on all sides; label cell = gray bg + teal bold; content = white
_BDR = r'\brdrw10\brdrs\brdrcf3'   # thin dark border
_ALL = (r'\clbrdrt' + _BDR + r'\clbrdrb' + _BDR +
        r'\clbrdrl' + _BDR + r'\clbrdrr' + _BDR)

def info_row(label, content_rtf):
    """One row: [gray label cell | white content cell]."""
    row = [r'\trowd\trgaph0\trleft0\trpaddl216\trpaddr216\trpaddt160\trpaddb160']
    # Label cell: gray background
    row.append(_ALL + r'\clshdng1000\clcbpat4\cellx1980')
    # Content cell: white background
    row.append(_ALL + r'\cellx10800')
    row.append(r'\pard\intbl\f0\fs20\b\cf1 ' + rtf_escape(label) + r'\b0\cell')
    row.append(r'\pard\intbl\f0\fs20\cf3 ' + content_rtf + r'\cell')
    row.append(r'\row')
    return '\n'.join(row)

def intbl_bullet(text, color=3, indent=360):
    """A single bullet paragraph inside a table cell."""
    return (r'\pard\intbl\li' + str(indent + 180) + r'\fi-180'
            r'\f0\fs20\cf' + str(color) + r' \bullet  ' + rtf_escape(text) + r'\par')

# SCOPE bullets
scope_lines = []
if playtest_count:
    scope_lines.append(str(playtest_count) + ' organized playtest' + ('' if playtest_count == 1 else 's'))
scope_lines.append('Playtest reports')
if rules_count:
    scope_lines.append(str(rules_count) + ' iteration' + ('' if rules_count == 1 else 's') + ' of rules editing')
scope_content = '\n'.join(intbl_bullet(s) for s in scope_lines)

L.append(info_row('CLIENT', rtf_escape(client or '—')))
L.append(info_row('GAME',   rtf_escape(game)))
L.append(info_row('SCOPE',  scope_content))

# Spacer
L.append(r'\pard\sb100\par')

# ── Section box helper ────────────────────────────────────────────────────────
# Matches the sample: gray header row (teal bold label) + white content row,
# all wrapped in a thin dark border.
def section_box(heading, bullet_items, images=None):
    """
    Two-row table: heading row (gray bg, teal bold) + content row (white, bullets).
    bullet_items: list of (obs_text, sol_text) tuples — plain strings.
    images: list of (img_data, fmt) pairs embedded after the table.
    """
    # Border style: thin dark line on all sides
    _bdr = r'\brdrw10\brdrs\brdrcf3'
    def cell_borders(extra=''):
        return (r'\clbrdrt' + _bdr + r'\clbrdrb' + _bdr +
                r'\clbrdrl' + _bdr + r'\clbrdrr' + _bdr + extra)

    out = []

    # Row 1: heading — gray background, teal bold text
    out.append(r'\trowd\trgaph0\trleft0\trpaddl216\trpaddr216\trpaddt120\trpaddb120')
    out.append(cell_borders(r'\clshdng1000\clcbpat4') + r'\cellx10800')
    out.append(r'\pard\intbl\f0\fs22\b\cf1 ' + rtf_escape(heading) + r'\b0\cell')
    out.append(r'\row')

    # Row 2: content — white background, bullets
    out.append(r'\trowd\trgaph0\trleft0\trpaddl216\trpaddr216\trpaddt120\trpaddb180')
    out.append(cell_borders() + r'\cellx10800')
    for obs_text, sol_text in bullet_items:
        if obs_text:
            out.append(r'\pard\intbl\li360\fi-180\f0\fs20\cf3 \bullet  ' +
                       rtf_escape(obs_text) + r'\par')
        if sol_text:
            out.append(r'\pard\intbl\li720\fi-180\f0\fs20\cf2 \endash  ' +
                       rtf_escape(sol_text) + r'\par')
    if not bullet_items:
        out.append(r'\pard\intbl\f0\fs20\cf2 \i No observations recorded.\i0\par')
    out.append(r'\cell\row')

    # Images after the table
    if images:
        for img_data, fmt in images:
            out.append(r'\pard\sb80\sa80 ' + rtf_pict(img_data, fmt, goal_twips=7200) + r'\par')

    return '\n'.join(out)

# ── Fetch images ─────────────────────────────────────────────────────────────
fetched_images = []
for url in all_images[:6]:   # cap at 6 images so the doc doesn't balloon
    img_data, fmt = fetch_image(url)
    if img_data:
        fetched_images.append((img_data, fmt))

# ── Build TESTS bullets (obs with images stripped) ───────────────────────────
tests_bullets = []
for entry in all_obs:
    obs_plain = strip_images(entry.get('obs', ''))
    sol_plain = strip_images(entry.get('sol', ''))
    if obs_plain or sol_plain:
        tests_bullets.append((obs_plain, sol_plain))

# Split images: first half between header and bullets, second half after bullets
mid       = len(fetched_images) // 2
imgs_mid  = fetched_images[:mid]
imgs_end  = fetched_images[mid:]

# Emit mid-page images before TESTS box (after info table)
if imgs_mid:
    for img_data, fmt in imgs_mid:
        L.append(r'\pard\sb40\sa40 ' + rtf_pict(img_data, fmt) + r'\par')
    L.append(r'\pard\sb40\par')

L.append(section_box('TESTS', tests_bullets, images=imgs_end))

# ── Footer ────────────────────────────────────────────────────────────────────
L.append(r'\pard\sb120\sa0\brdrb\brdrs\brdrw10\brdrcf2\brsp40\par')

footer_parts = []
if my_email:  footer_parts.append(my_email)
if my_phone:  footer_parts.append(my_phone)
if my_company or my_name:
    site = (my_company or my_name).lower().replace(' ', '') + '.com'
    # Use website if we don't have a better guess — just show the company/email
    pass

if footer_parts:
    L.append(r'\pard\sb40\sa0\qc\f0\fs18\cf2 ' +
             rtf_escape('  •  '.join(footer_parts)) + r'\par')

L.append('}')

rtf_bytes = '\n'.join(L).encode('latin-1', errors='replace')
b64       = base64.b64encode(rtf_bytes).decode('ascii')

safe_game = re.sub(r'[^\w\s-]', '', game).strip().replace(' ', '_')
filename  = (f"{ref_code}_{safe_game}" if ref_code else safe_game) + '_Playtest_Report.rtf'

print(json.dumps({"ok": True, "b64": b64, "filename": filename, "title": doc_title}))
sys.exit(0)
