# gcreateReport.py — create a playtest report Google Doc from DevBoard session data.
#
# Arg: {sheet_id}|{base64_json}
# JSON keys: game, client, my_name, my_company, my_address, my_email, my_phone
# Returns: { ok, doc_id, doc_url, title }

import sys, os, json, base64, socket
from datetime import datetime
import html as html_lib

socket.setdefaulttimeout(20)

CRED_FILE = os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', 'credentials.json')
SCOPES    = ['https://www.googleapis.com/auth/drive',
             'https://spreadsheets.google.com/feeds']

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

# ── Load session data ────────────────────────────────────────────────────────
base_dir = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
dev_file = os.path.join(base_dir, 'sheets', sheet_id, f'[{game}] dev.json')
if not os.path.exists(dev_file):
    print(json.dumps({"error": f"Dev data not found for: {game}"}))
    sys.exit(1)

with open(dev_file, 'r', encoding='utf-8') as f:
    rows = json.load(f)

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

# ── Build HTML ───────────────────────────────────────────────────────────────
def esc(s):
    return html_lib.escape(str(s or ''))

today     = datetime.today().strftime('%B %d, %Y')
doc_title = f"{game} — Playtest Report"
if ref_code:
    doc_title += f" ({ref_code})"

header    = my_company or my_name

lines = ['<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body>']
lines.append(f'<h1 style="color:#1a5f7a">{esc(header)}</h1>')
lines.append(f'<p><strong>Date:</strong> {esc(today)}</p>')
if my_address:
    for addr_line in my_address.replace('\r\n', '\n').split('\n'):
        if addr_line.strip():
            lines.append(f'<p style="margin:0">{esc(addr_line.strip())}</p>')
if my_name and my_company:
    lines.append(f'<p><strong>Prepared by:</strong> {esc(my_name)}</p>')
lines.append('<hr>')

# CLIENT / GAME block
lines.append('<table border="0" cellpadding="8" cellspacing="0" '
             'style="border-collapse:collapse;margin-bottom:1em">')
lines.append('<tr>')
lines.append(f'<td style="border:1px solid #ccc;vertical-align:top;min-width:120px">'
             f'<strong>CLIENT</strong><br>{esc(client or "—")}</td>')
lines.append(f'<td style="border:1px solid #ccc;vertical-align:top;min-width:200px">'
             f'<strong>GAME</strong><br>{esc(game)}</td>')
lines.append('</tr></table>')

# TESTS
if playtest_sessions:
    lines.append('<h2>TESTS</h2>')
    for s in playtest_sessions:
        lines.append(f'<h3>{esc(s["testnum"])}')
        if s['date']:
            lines.append(f' — {esc(s["date"])}')
        lines.append('</h3>')

        if s['testers']:
            lines.append(f'<p><em>Testers: {esc(", ".join(s["testers"]))}</em></p>')

        meta_parts = []
        if s.get('location'):
            meta_parts.append(s['location'])
        if s.get('length'):
            meta_parts.append(s['length'])
        if meta_parts:
            lines.append(f'<p><em>{esc(" · ".join(meta_parts))}</em></p>')

        if s['obs']:
            lines.append('<ul>')
            for entry in s['obs']:
                if entry.get('obs'):
                    lines.append(f'<li>{esc(entry["obs"])}')
                    if entry.get('sol'):
                        lines.append(f'<ul><li>{esc(entry["sol"])}</li></ul>')
                    lines.append('</li>')
            lines.append('</ul>')
else:
    lines.append('<p><em>No playtest sessions recorded yet.</em></p>')

# Footer
lines.append('<hr>')
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
    lines.append(f'<p style="color:#666">{esc("  ·  ".join(footer_parts))}</p>')

lines.append('</body></html>')
html_content = '\n'.join(lines)

# ── Create Google Doc via Drive API (multipart upload: HTML → Google Doc) ────
if not os.path.exists(CRED_FILE):
    print(json.dumps({"error": "credentials.json not found"}))
    sys.exit(1)

try:
    from google.oauth2 import service_account as sa_module
    from google.auth.transport.requests import Request as GRequest
    import requests as req_lib
except ImportError as e:
    print(json.dumps({"error": f"Missing library: {e}"}))
    sys.exit(1)

try:
    creds = sa_module.Credentials.from_service_account_file(CRED_FILE, scopes=SCOPES)
    creds.refresh(GRequest())
    token = creds.token
except Exception as e:
    print(json.dumps({"error": f"Auth failed: {e}"}))
    sys.exit(1)

boundary   = 'zap_rpt_bdry_x7k'
meta_json  = json.dumps({"name": doc_title,
                          "mimeType": "application/vnd.google-apps.document"})
body_parts = [
    f'--{boundary}\r\nContent-Type: application/json; charset=UTF-8\r\n\r\n{meta_json}\r\n',
    f'--{boundary}\r\nContent-Type: text/html; charset=UTF-8\r\n\r\n{html_content}\r\n',
    f'--{boundary}--',
]
body = ''.join(body_parts).encode('utf-8')

try:
    resp = req_lib.post(
        'https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart',
        headers={
            'Authorization': f'Bearer {token}',
            'Content-Type':  f'multipart/related; boundary={boundary}',
        },
        data=body,
        timeout=30,
    )
except Exception as e:
    print(json.dumps({"error": f"Network error: {e}"}))
    sys.exit(1)

if not resp.ok:
    print(json.dumps({"error": f"Drive API {resp.status_code}: {resp.text[:300]}"}))
    sys.exit(1)

result  = resp.json()
doc_id  = result.get('id', '')
doc_url = f"https://docs.google.com/document/d/{doc_id}/edit"

print(json.dumps({
    "ok":      True,
    "doc_id":  doc_id,
    "doc_url": doc_url,
    "title":   doc_title,
    "sessions": len(playtest_sessions),
}))
