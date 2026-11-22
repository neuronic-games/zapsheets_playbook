# gcbadmin.py — CardBoard accounts, one row per person (by email) in the CardBoard Admin sheet.
#
# Arg: base64 JSON { "action": ..., "email": ..., ... }
#   user   — email, name?, sheet?, fields? → the person's row; a new person gets
#            a free week (Trial Start = now, Trial End = now + 7 days). Last Seen is updated.
#            Sheets lists the spreadsheets the person set up (they change them; everyone else only
#            views). A sheet is added only with claim (Connect on the CardBoard home page, or "Make it
#            mine" on a sheet nobody has), and only if no one else's Sheets has it. The answer says
#            whether someone else has it (taken).
#   update — email, fields: {Header: value} → changes those cells (adds the person if missing)
#   find   — field, value → the first row whose Header equals value (e.g. Stripe Customer)
#   redeem — email, code → a free code from the "Codes" tab: CardBoard unlocked for its Length
#            (added on top of any code time left). Each person can use each code once (their Users row
#            lists them in Codes Used); Max Uses
#            (blank = no limit) and Expires (blank = never) are optional.
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
HEADERS = ['Email', 'Name', 'Studio', 'Trial Start', 'Trial End', 'Status', 'Plan', 'Period End',
           'Stripe Customer', 'Stripe Subscription', 'Activated', 'Sheets', 'Created', 'Last Seen', 'Password Hash', 'Code Until', 'Codes Used']
TRIAL_DAYS = 7
CODES_TAB = 'Codes'   # free codes you hand out: each unlocks CardBoard for its Length, once per person
CODES_HEADERS = ['Code', 'Length', 'Max Uses', 'Used', 'Expires', 'Note']   # who used which: the Users tab's Codes Used

PERPETUAL = -1   # "Perpetual" (or forever / lifetime / unlimited): unlocked for good

def length_days(v):
    # "1 week", "2 weeks", "1 month", "1 year", "30 days", a plain number of days, or Perpetual
    import re
    if str(v or '').strip().lower() in ('perpetual', 'forever', 'lifetime', 'unlimited', 'permanent'): return PERPETUAL
    m = re.match(r'^\s*(\d+(?:\.\d+)?)?\s*(d|day|days|w|wk|week|weeks|m|mo|month|months|y|yr|year|years)?\s*$', str(v or '').strip().lower())
    if not m or not (m.group(1) or m.group(2)): return 0
    n = float(m.group(1) or 1); u = (m.group(2) or 'd')[0]
    return int(round(n * {'d': 1, 'w': 7, 'm': 30, 'y': 365}[u]))

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
    if not any(w.title.lower() == CODES_TAB.lower() for w in wb.worksheets()):   # ready for you to add codes
        wb.add_worksheet(title=CODES_TAB, rows=100, cols=len(CODES_HEADERS))
        wb.values_update(q(CODES_TAB) + '!A1', params={'valueInputOption': 'RAW'}, body={'values': [CODES_HEADERS]})
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
        if data.get('name') and not u.get('Name'): u['Name'] = str(data['name'])[:100]   # only a first name; Profile changes it after
        sheet = str(data.get('sheet') or '').strip()
        if sheet:
            have = [s.strip() for s in str(u.get('Sheets', '')).split(',') if s.strip()]
            si = hdr.index('Sheets') if 'Sheets' in hdr else -1
            taken = si >= 0 and any(k + 2 != n and sheet in [x.strip() for x in str(r[si] if si < len(r) else '').split(',')]
                                    for k, r in enumerate(rows))
            if sheet not in have and data.get('claim') and not taken:   # setting it up, and nobody has it yet
                have.append(sheet)
                u['Sheets'] = ','.join(have[-50:])
        for k, v in (data.get('fields') or {}).items():   # e.g. the password hash when signing up
            if k in hdr and k != 'Email': u[k] = '' if v is None else str(v)
        u['Last Seen'] = iso(now())
        write(n, u)
        out({'ok': True, 'user': u, 'taken': bool(sheet) and bool(taken) if sheet else False})

    if action == 'redeem':
        if not u: out({'error': 'Sign in first'})
        code = str(data.get('code') or '').strip()
        cws = next((w for w in wb.worksheets() if w.title.lower() == CODES_TAB.lower()), None)
        cv = wb.values_get(q(cws.title)).get('values', []) if cws else []
        ch = [h.strip() for h in (cv[0] if cv else [])]
        def ci(name): return ch.index(name) if name in ch else -1
        row_no, row = 0, None
        for k, r in enumerate(cv[1:]):
            if ci('Code') >= 0 and ci('Code') < len(r) and str(r[ci('Code')]).strip().lower() == code.lower() and code:
                row_no, row = k + 2, list(r) + [''] * (len(ch) - len(r)); break
        if not row: out({'ok': False, 'unknown': True})
        days = length_days(row[ci('Length')] if ci('Length') >= 0 else '')
        if days == 0: out({'error': 'That code isn’t set up yet — ask whoever gave it to you'})
        exp = str(row[ci('Expires')]).strip() if ci('Expires') >= 0 else ''
        if exp:   # typed as 2026-12-31, or shown by Sheets as 12/31/2026
            ed = None
            for fmt in ('%Y-%m-%d', '%m/%d/%Y', '%Y/%m/%d', '%d %b %Y', '%b %d, %Y'):
                try: ed = datetime.datetime.strptime(exp.split(' ')[0] if fmt in ('%Y-%m-%d', '%m/%d/%Y', '%Y/%m/%d') else exp, fmt); break
                except ValueError: pass
            if ed and ed + datetime.timedelta(days=1) < now(): out({'error': 'That code has expired'})
        # Once per person: the codes each person has used are listed in their Users row (Codes Used)
        mine = [c.strip().lower() for c in str(u.get('Codes Used', '')).split(',') if c.strip()]
        if code.lower() in mine: out({'error': 'You’ve already used that code'})
        used = str(row[ci('Used')]).strip() if ci('Used') >= 0 else ''
        used = int(used) if used.isdigit() else 0
        mx = str(row[ci('Max Uses')]).strip() if ci('Max Uses') >= 0 else ''
        if mx.isdigit() and used >= int(mx): out({'error': 'That code has been used up'})
        if ci('Used') >= 0: row[ci('Used')] = str(used + 1)
        wb.values_update(q(cws.title) + '!A' + str(row_no) + ':' + col(len(ch)) + str(row_no), params={'valueInputOption': 'RAW'}, body={'values': [row[:len(ch)]]})
        # The new time starts when the current access runs out: after any code time left, or after the free week
        start = now()
        for k in ('Code Until', 'Trial End'):
            try: cur = datetime.datetime.strptime(str(u.get(k, ''))[:19], '%Y-%m-%d %H:%M:%S')
            except ValueError: cur = None
            if cur and cur > start: start = cur
        if days == PERPETUAL: u['Activated'] = now().strftime('%Y-%m-%d')   # unlocked for good
        else: u['Code Until'] = iso(start + datetime.timedelta(days=days))
        u['Codes Used'] = ', '.join([c for c in str(u.get('Codes Used', '')).split(', ') if c] + [str(row[ci('Code')]).strip()])
        u['Last Seen'] = iso(now())
        write(n, u)
        out({'ok': True, 'user': u, 'days': days})

    if action == 'update':
        u = u or new_person(email)
        for k, v in (data.get('fields') or {}).items():
            if k in hdr and k != 'Email': u[k] = '' if v is None else str(v)
        write(n, u)
        out({'ok': True, 'user': u})

    out({'error': 'Unknown action'})

main()
