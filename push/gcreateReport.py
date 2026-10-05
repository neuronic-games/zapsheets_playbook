# gcreateReport.py — create a playtest report Markdown file from DevBoard session data.
#
# Arg: {sheet_id}|{base64_json}
# JSON keys: game, client, my_name, my_company, my_address, my_email, my_phone, ref_code
# Returns: { ok, content, filename, title }

import sys, os, json, base64, socket
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
    # No cached JSON — read directly from the Google Sheet
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
        if not all_vals:
            rows = []
        else:
            headers = all_vals[0]
            rows = [dict(zip(headers, r)) for r in all_vals[1:]]
    except Exception as e:
        print(json.dumps({"error": f"Sheet read failed: {e}"}))
        sys.exit(1)

# ── Build sessions (mirrors buildSessions() in devboard-common.js) ───────────
sessions = []
current  = None

for row in rows:
    date   = str(row.get('Date',         '') or '').strip()
    event  = str(row.get('Event',        '') or '').strip()
    people = str(row.get('People',       '') or '').strip()
    obs    = str(row.get('Observations', '') or row.get('Observation', '') or '').strip()
    sol    = str(row.get('Solutions',    '') or row.get('Thoughts', '') or row.get('Solution', '') or '').strip()

    if date or event:
        # Skip T&M rows
        if event in ('Time', 'Material'):
            current = None
            continue
        label   = event + (' ' + people if people else '')
        current = {
            'date':     date,
            'testnum':  label,
            'event':    event,
            'location': obs,
            'length':   sol,
            'testers':  [],
            'obs':      [],
        }
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

# Only include sessions that have observations
playtest_sessions = [s for s in sessions if s['obs'] or s.get('location')]

# ── Build Markdown ────────────────────────────────────────────────────────────
def md(s):
    """Escape characters that would break Markdown structure."""
    return str(s or '').replace('\\', '\\\\').replace('`', '\\`')

today     = datetime.today().strftime('%B %d, %Y')
doc_title = f"{game} — Playtest Report"
if ref_code:
    doc_title += f" ({ref_code})"

header = my_company or my_name

lines = []
if header:
    lines.append(f'# {md(header)}')
    lines.append('')

lines.append(f'**Date:** {today}')
if my_address:
    for addr_line in my_address.replace('\r\n', '\n').split('\n'):
        if addr_line.strip():
            lines.append(addr_line.strip())
if my_name and my_company:
    lines.append(f'**Prepared by:** {md(my_name)}')
lines.append('')
lines.append('---')
lines.append('')

# CLIENT / GAME block
lines.append(f'**CLIENT:** {md(client or "—")}  ')
lines.append(f'**GAME:** {md(game)}')
lines.append('')
lines.append('---')
lines.append('')

# TESTS
if playtest_sessions:
    lines.append('## TESTS')
    lines.append('')
    for s in playtest_sessions:
        heading = md(s['testnum'])
        if s['date']:
            heading += f' — {md(s["date"])}'
        lines.append(f'### {heading}')
        lines.append('')

        if s['testers']:
            lines.append(f'*Testers: {md(", ".join(s["testers"]))}*')
            lines.append('')

        meta_parts = []
        if s.get('location'):
            meta_parts.append(s['location'])
        if s.get('length'):
            meta_parts.append(s['length'])
        if meta_parts:
            lines.append(f'*{md(" · ".join(meta_parts))}*')
            lines.append('')

        if s['obs']:
            for entry in s['obs']:
                if entry.get('obs'):
                    lines.append(f'- {md(entry["obs"])}')
                    if entry.get('sol'):
                        lines.append(f'  - {md(entry["sol"])}')
            lines.append('')
else:
    lines.append('*No playtest sessions recorded yet.*')
    lines.append('')

# Footer
lines.append('---')
lines.append('')
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
    lines.append(f'*{md("  ·  ".join(footer_parts))}*')

md_content = '\n'.join(lines)

# ── Safe filename ─────────────────────────────────────────────────────────────
import re
safe_game = re.sub(r'[^\w\s-]', '', game).strip().replace(' ', '_')
filename  = f"{safe_game}_Playtest_Report.md"

print(json.dumps({"ok": True, "content": md_content, "filename": filename, "title": doc_title}))
sys.exit(0)
