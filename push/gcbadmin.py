# gcbadmin.py — CardBoard accounts, one row per person (by email) in the CardBoard Admin sheet.
#
# Arg: base64 JSON { "action": ..., "email": ..., ... }
#   user   — email, name (optional), sheet (optional) → the person's row; a new person gets
#            a free week (Trial Start = now, Trial End = now + 7 days). Last Seen is updated
#            and the sheet ID is added to Sheets.
#   update — email, fields: {Header: value} → changes those cells (adds the person if missing)
#   find   — field, value → the first row whose Header equals value (e.g. Stripe Customer)
# Answer: {ok, user: {Header: value, …}}
#
# The Admin sheet is the one PHP passes as "admin" (CARDBOARD_ADMIN_SHEET in .env), or the built-in ID below.
# It must be shared with the service account as an Editor. Rows live in its "Users" tab,
# which is created (with the headings) the first time.

import gspread
import gretry  # retry temporary Google API errors (see gretry.py)
import sys, os, json, base64, socket, datetime, warnings
warnings.filterwarnings('ignore')
socket.setdefaulttimeout(30)

CRED = os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', 'credentials.json')
ADMIN_SHEET = '1heNPe8GCggJVoUlGvDVwBt697LvD7vtQT5yDfsnV4M0'
TAB = 'Users'
HEADERS = ['Email', 'Name', 'Trial Start', 'Trial End', 'Status', 'Plan', 'Period End',
           'Stripe Customer', 'Stripe Subscription', 'Activated', 'Sheets', 'Created', 'Last Seen']
TRIAL_DAYS = 7

def out(d):
    print(json.dumps(d, ensure_ascii=False)); sys.exit(0)

def q(t): return "'" + t.replace("'", "''") + "'"

def now(): return datetime.datetime.utcnow().replace(microsecond=0)
def iso(d): return d.strftime('%Y-%m-%d %H:%M:%S')

def col(n):   # 1 → A
    s = ''
    while n: n, r = divmod(n - 1, 26); s = chr(65 + r) + s
    return s

def main():
    try:
        data = json.loads(base64.b64decode(sys.argv[1]).decode('utf-8'))
    except Exception as e:
        out({'error': f'Bad argument: {e}'})
    try:
        wb = gspread.service_account(filename=CRED).open_by_key(str(data.get('admin') or ADMIN_SHEET))
    except Exception as e:
        out({'error': f'Could not open the CardBoard Admin sheet (share it with the service account as an Editor): {e}'})

    ws = next((w for w in wb.worksheets() if w.title.lower() == TAB.lower()), None)
    if ws is None:
        ws = wb.add_worksheet(title=TAB, rows=200, cols=len(HEADERS))
        wb.values_update(q(TAB) + '!A1', params={'valueInputOption': 'RAW'}, body={'values': [HEADERS]})
    vals = wb.values_get(q(ws.title)).get('values', [])
    hdr = [h.strip() for h in (vals[0] if vals else [])]
    missing = [h for h in HEADERS if h not in hdr]
    if missing:   # add any headings this version needs after the existing ones
        hdr = hdr + missing
        wb.values_update(q(ws.title) + '!A1', params={'valueInputOption': 'RAW'}, body={'values': [hdr]})
    rows = vals[1:] if vals else []

    def rec(r): return {h: (r[i] if i < len(r) else '') for i, h in enumerate(hdr)}
    def find(field, value):
        if field not in hdr: return 0, None
        i = hdr.index(field); v = str(value).strip().lower()
        for n, r in enumerate(rows):
            if i < len(r) and str(r[i]).strip().lower() == v and v: return n + 2, rec(r)
        return 0, None
    def write(rowno, d):
        line = [d.get(h, '') for h in hdr]
        if rowno:
            wb.values_update(q(ws.title) + '!A' + str(rowno) + ':' + col(len(hdr)) + str(rowno), params={'valueInputOption': 'RAW'}, body={'values': [line]})
        else:
            wb.values_append(q(ws.title) + '!A1', params={'valueInputOption': 'RAW', 'insertDataOption': 'INSERT_ROWS'}, body={'values': [line]})
    def new_person(email):
        t = now()
        return {'Email': email, 'Trial Start': iso(t), 'Trial End': iso(t + datetime.timedelta(days=TRIAL_DAYS)),
                'Status': 'trial', 'Created': iso(t)}

    action = data.get('action')
    if action == 'find':
        n, u = find(str(data.get('field') or ''), data.get('value') or '')
        out({'ok': True, 'user': u})

    email = str(data.get('email') or '').strip().lower()
    if not email or '@' not in email: out({'error': 'Missing email'})
    n, u = find('Email', email)

    if action == 'user':
        u = u or new_person(email)
        if data.get('name'): u['Name'] = str(data['name'])[:100]
        sheet = str(data.get('sheet') or '').strip()
        if sheet:
            have = [s for s in str(u.get('Sheets', '')).split(',') if s.strip()]
            if sheet not in have: have.append(sheet)
            u['Sheets'] = ','.join(have[-50:])
        u['Last Seen'] = iso(now())
        write(n, u)
        out({'ok': True, 'user': u})

    if action == 'update':
        u = u or new_person(email)
        for k, v in (data.get('fields') or {}).items():
            if k in hdr and k != 'Email': u[k] = '' if v is None else str(v)
        write(n, u)
        out({'ok': True, 'user': u})

    out({'error': 'Unknown action'})

main()
