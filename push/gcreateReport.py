# gcreateReport.py — create a formatted .rtf playtest report from DevBoard session data.
#
# Arg: {sheet_id}|{base64_json}
# JSON keys: game, client, my_name, my_company, my_address, my_email, my_phone, ref_code
# Returns: { ok, b64, filename, title }  (b64 = base64-encoded .rtf bytes)
# No external dependencies beyond stdlib + gspread.

import sys, os, json, base64, socket, re, io, struct
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
ref_code   = data.get('ref_code',   '').strip()

if not game:
    print(json.dumps({"error": "game is required"}))
    sys.exit(1)

# ── Load session data (cache first, fall back to live sheet read) ─────────────
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
    sol    = str(row.get('Solutions',    '') or row.get('Thoughts', '') or row.get('Solution', '') or '').strip()

    if date or event:
        if event in ('Time', 'Material'):
            current = None
            continue
        label   = event + (' ' + people if people else '')
        current = {'date': date, 'testnum': label, 'location': obs, 'length': sol, 'testers': [], 'obs': []}
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

playtest_sessions = [s for s in sessions if s['obs'] or s.get('location')]

# ── RTF helpers ───────────────────────────────────────────────────────────────
IMAGE_RE = re.compile(r'=IMAGE\s*\(\s*["\']?(https?[^"\')\s]+)["\']?\s*\)', re.IGNORECASE)

# Color table indices (1-based)
# 1 = teal #1a5f7a, 2 = gray #666666, 3 = dark #111111, 4 = white #ffffff

def rtf_escape(s):
    """Escape a plain string for RTF."""
    out = []
    for ch in str(s or ''):
        cp = ord(ch)
        if ch == '\\':
            out.append('\\\\')
        elif ch == '{':
            out.append('\\{')
        elif ch == '}':
            out.append('\\}')
        elif cp < 128:
            out.append(ch)
        else:
            # Unicode escape
            signed = cp if cp < 32768 else cp - 65536
            out.append(f'\\u{signed}?')
    return ''.join(out)

def fetch_image(url):
    """Download image; returns (bytes, 'png'|'jpeg') or (None, None)."""
    try:
        import urllib.request
        req = urllib.request.Request(url, headers={'User-Agent': 'Mozilla/5.0'})
        with urllib.request.urlopen(req, timeout=10) as resp:
            data = resp.read()
            ct   = resp.headers.get('Content-Type', '')
        fmt = 'png' if 'png' in ct.lower() else 'jpeg'
        # Detect from magic bytes if Content-Type is vague
        if data[:8] == b'\x89PNG\r\n\x1a\n':
            fmt = 'png'
        elif data[:2] == b'\xff\xd8':
            fmt = 'jpeg'
        return data, fmt
    except Exception:
        return None, None

def image_dims(data, fmt):
    """Return (width_px, height_px) or (None, None)."""
    try:
        if fmt == 'png' and data[:8] == b'\x89PNG\r\n\x1a\n':
            w = struct.unpack('>I', data[16:20])[0]
            h = struct.unpack('>I', data[20:24])[0]
            return w, h
        if fmt == 'jpeg':
            i = 2
            while i < len(data) - 9:
                if data[i] != 0xFF:
                    break
                marker = data[i + 1]
                seg_len = struct.unpack('>H', data[i+2:i+4])[0]
                if marker in (0xC0, 0xC1, 0xC2):
                    h = struct.unpack('>H', data[i+5:i+7])[0]
                    w = struct.unpack('>H', data[i+7:i+9])[0]
                    return w, h
                i += 2 + seg_len
    except Exception:
        pass
    return None, None

def rtf_image_block(img_data, fmt, goal_width_twips=7200):
    """Return RTF \pict block string for an image."""
    w, h = image_dims(img_data, fmt)
    hex_data = img_data.hex()
    pict_type = '\\pngblip' if fmt == 'png' else '\\jpegblip'
    dim_str = ''
    if w and h:
        goal_h = int(goal_width_twips * h / w)
        dim_str = f'\\picw{w}\\pich{h}\\picwgoal{goal_width_twips}\\pichgoal{goal_h}'
    else:
        dim_str = f'\\picwgoal{goal_width_twips}'
    return '{\\pict' + pict_type + dim_str + '\n' + hex_data + '}'

def split_text_images(text):
    """Return list of str or {'img_url': url} segments."""
    segs, last = [], 0
    for m in IMAGE_RE.finditer(text):
        if m.start() > last:
            segs.append(text[last:m.start()])
        segs.append({'img_url': m.group(1)})
        last = m.end()
    if last < len(text):
        segs.append(text[last:])
    return segs

# ── Build RTF document ────────────────────────────────────────────────────────
today = datetime.today()
date_str = today.strftime('%b ') + str(today.day) + today.strftime(', %Y')

doc_title = f"{game} — Playtest Report"
if ref_code:
    doc_title += f" ({ref_code})"

lines = []

# RTF header
lines.append(r'{\rtf1\ansi\ansicpg1252\deff0\deflang1033')
lines.append(r'{\fonttbl{\f0\fswiss\fcharset0 Arial;}{\f1\fswiss\fcharset0 Helvetica;}}')
lines.append(r'{\colortbl ;\red26\green95\blue122;\red102\green102\blue102;\red17\green17\blue17;\red255\green255\blue255;}')
# Letter page, 1" margins left/right, 0.75" top/bottom
lines.append(r'\paperw12240\paperh15840\margl1440\margr1440\margt1080\margb1080')
lines.append(r'\widowctrl\hyphauto')

def para(content, bold=False, italic=False, color=3, size=20,
         sb=0, sa=60, li=0, align='', border_bottom=False):
    """Emit an RTF paragraph."""
    p = r'\pard'
    if align == 'center':
        p += r'\qc'
    p += f'\\sb{sb}\\sa{sa}'
    if li:
        p += f'\\li{li}'
    if border_bottom:
        p += r'\brdrb\brdrs\brdrw15\brdrcf1\brsp40'
    p += f'\\f0\\fs{size}\\cf{color} '
    if bold:
        p += r'\b '
    if italic:
        p += r'\i '
    p += content
    if bold:
        p += r'\b0'
    if italic:
        p += r'\i0'
    p += r'\par'
    return p

# ── Company header ────────────────────────────────────────────────────────────
header_text = my_company or my_name
if header_text:
    lines.append(para(rtf_escape(header_text), bold=True, color=1, size=40, sb=0, sa=80))

# Date
lines.append(
    r'\pard\sb0\sa40\f0\fs20\cf3 '
    r'\b Date:\b0  ' + rtf_escape(date_str) + r'\par'
)

# Address
if my_address:
    for addr_line in my_address.replace('\r\n', '\n').split('\n'):
        addr_line = addr_line.strip()
        if addr_line:
            lines.append(para(rtf_escape(addr_line), color=2, size=18, sb=0, sa=20))

# Prepared by
if my_name and my_company:
    lines.append(
        r'\pard\sb40\sa40\f0\fs20\cf3 '
        r'\b Prepared by:\b0  ' + rtf_escape(my_name) + r'\par'
    )

# Horizontal rule
lines.append(r'\pard\sb60\sa60\brdrb\brdrs\brdrw15\brdrcf1\brsp40\par')

# ── CLIENT / GAME table ───────────────────────────────────────────────────────
# Use a simple RTF table: two columns, ~3" and ~4.5"
lines.append(r'\pard\trowd\trgaph108\trleft-108')
lines.append(r'\clbrdrt\brdrw15\brdrs\brdrcf2\clbrdrb\brdrw15\brdrs\brdrcf2'
             r'\clbrdrl\brdrw15\brdrs\brdrcf2\clbrdrr\brdrw15\brdrs\brdrcf2'
             r'\cellx4320')   # 3 inches
lines.append(r'\clbrdrt\brdrw15\brdrs\brdrcf2\clbrdrb\brdrw15\brdrs\brdrcf2'
             r'\clbrdrl\brdrw15\brdrs\brdrcf2\clbrdrr\brdrw15\brdrs\brdrcf2'
             r'\cellx10800')  # 7.5 inches total
lines.append(
    r'\f0\fs18\cf1\b CLIENT\b0\cf3\fs20\line ' + rtf_escape(client or '—') + r'\cell'
)
lines.append(
    r'\f0\fs18\cf1\b GAME\b0\cf3\fs20\line ' + rtf_escape(game) + r'\cell'
)
lines.append(r'\row')

# Spacer
lines.append(r'\pard\sb60\par')

# ── TESTS heading ─────────────────────────────────────────────────────────────
lines.append(para('TESTS', bold=True, color=1, size=28, sb=60, sa=40))

if playtest_sessions:
    for s in playtest_sessions:
        # Session title
        heading = s['testnum']
        if s['date']:
            heading += f"  —  {s['date']}"
        lines.append(para(rtf_escape(heading), bold=True, color=3, size=24, sb=120, sa=40))

        # Testers
        if s['testers']:
            lines.append(
                r'\pard\sb0\sa40\f0\fs20\cf2 '
                r'\b Testers:\b0  \i ' + rtf_escape(', '.join(s['testers'])) + r'\i0\par'
            )

        # Meta: location / length
        meta_parts = []
        if s.get('location'):
            meta_parts.append(s['location'])
        if s.get('length'):
            meta_parts.append(s['length'])
        if meta_parts:
            lines.append(para(r'\i ' + rtf_escape(' · '.join(meta_parts)) + r'\i0',
                               color=2, size=18, sb=0, sa=60))

        # Observations
        for entry in s['obs']:
            obs_text = (entry.get('obs') or '').strip()
            sol_text = (entry.get('sol') or '').strip()

            if not obs_text and not sol_text:
                continue

            obs_segs = split_text_images(obs_text) if obs_text else []
            sol_segs = split_text_images(sol_text) if sol_text else []

            # Plain text parts of obs
            obs_plain  = ' '.join(seg.strip() for seg in obs_segs if isinstance(seg, str) and seg.strip())
            obs_imgs   = [seg['img_url'] for seg in obs_segs if isinstance(seg, dict)]

            # Plain text parts of sol
            sol_plain  = ' '.join(seg.strip() for seg in sol_segs if isinstance(seg, str) and seg.strip())
            sol_imgs   = [seg['img_url'] for seg in sol_segs if isinstance(seg, dict)]

            # Bullet: observation
            if obs_plain:
                lines.append(
                    r'\pard\sb20\sa20\li360\fi-180\f0\fs20\cf3 \bullet  ' +
                    rtf_escape(obs_plain) + r'\par'
                )

            # Sub-bullet: solution text
            if sol_plain:
                lines.append(
                    r'\pard\sb0\sa20\li720\fi-180\f0\fs20\cf2 \endash  \i ' +
                    rtf_escape(sol_plain) + r'\i0\par'
                )

            # Solution images
            for img_url in sol_imgs:
                img_data, fmt = fetch_image(img_url)
                if img_data:
                    lines.append(r'\pard\sb20\sa20\li720')
                    lines.append(rtf_image_block(img_data, fmt, goal_width_twips=5760))
                    lines.append(r'\par')

            # Observation images
            for img_url in obs_imgs:
                img_data, fmt = fetch_image(img_url)
                if img_data:
                    lines.append(r'\pard\sb20\sa40')
                    lines.append(rtf_image_block(img_data, fmt, goal_width_twips=7200))
                    lines.append(r'\par')

else:
    lines.append(para(r'\i No playtest sessions recorded yet.\i0', color=2, size=20, sb=40, sa=40))

# ── Footer ────────────────────────────────────────────────────────────────────
lines.append(r'\pard\sb80\sa60\brdrb\brdrs\brdrw15\brdrcf1\brsp40\par')

footer_parts = []
if my_company:
    footer_parts.append(my_company)
elif my_name:
    footer_parts.append(my_name)
if my_phone:
    footer_parts.append(my_phone)
if my_email:
    footer_parts.append(my_email)
if footer_parts:
    lines.append(para(r'\i ' + rtf_escape('  ·  '.join(footer_parts)) + r'\i0',
                       color=2, size=18, sb=0, sa=0))

lines.append('}')

rtf_content = '\n'.join(lines)
rtf_bytes   = rtf_content.encode('latin-1', errors='replace')
b64         = base64.b64encode(rtf_bytes).decode('ascii')

safe_game = re.sub(r'[^\w\s-]', '', game).strip().replace(' ', '_')
if ref_code:
    filename = f"{ref_code}_{safe_game}_Playtest_Report.rtf"
else:
    filename = f"{safe_game}_Playtest_Report.rtf"

print(json.dumps({"ok": True, "b64": b64, "filename": filename, "title": doc_title}))
sys.exit(0)
