# gaccounts.py — GameBoard accounts, one row per person (by email) in the GameBoard Admin sheet.
#
# Arg: base64 JSON { "action": ..., "email": ..., ... }
#   user   — email, name?, sheet?, fields? → the person's row; a new person gets
#            a free week (Trial Start = now, Trial End = now + 7 days). Last Seen is updated.
#            Sheets lists the spreadsheets the person set up (they change them; everyone else only
#            views). A sheet is added only with claim (Connect on the CardBoard home page, or "Make it
#            mine" on a sheet nobody has), and only if no one else's Sheets has it. The answer says
#            whether someone else has it (taken).
#   update — email, fields: {Header: value} → changes those cells (adds the person if missing).
#            A Header written "+Header" adds the value to that cell's comma list instead (Stripe Subscription).
#   find   — field, value → the first row whose Header equals value (e.g. Stripe Customer)
#   redeem — email, code → a free code from the "Codes" tab: every app (or the code's App) unlocked for its Length
#            (added on top of any code time left). Each person can use each code once (their Users row
#            lists them in Codes Used); Max Uses
#            (blank = no limit) and Expires (blank = never) are optional.
#   waiting — → {ok, users: [{Email, Name, Created, Waiting, Apps}]}: the people in the Waiting Room (Status = waiting),
#            and those let in to only some apps (Apps: the apps they're in), oldest first
#   letin  — emails: [...], apps: [CardBoard, …] (none = every app) → each gets a free week of those apps, starting
#            now (Trial Apps), and Status = trial. → {ok, done: [{Email, Name, Apps: the apps new to them}], errors}
#
# The Waiting Room: while the "settings" tab has WaitingRoomOn = TRUE (key in column A, value in B), a new
# person who signs up gets Status = waiting and no free week yet; they can't sign in until let in (letin, or by
# changing their Status in the sheet — their free week of every app then starts the next time they sign in).
# Trial Apps: when it has anything, the free week is only for those apps, each with its own end —
# "DevBoard 2026-12-02 23:03:36, PitchBoard 2026-12-09 10:00:00" (the same way as App Codes); other apps stay closed.
# Answer: {ok, user: {Header: value, …}}
#
# The Admin sheet is the one PHP passes as "admin" (CARDBOARD_ADMIN_SHEET in .env), or the built-in ID below.
# It must be shared with the service account as an Editor. Rows live in its "Users" tab,
# which is created (with the headings) the first time.

import gspread
import gretry  # retry temporary Google API errors (see gretry.py)
import sys, os, re, json, base64, socket, datetime, warnings
warnings.filterwarnings('ignore')
socket.setdefaulttimeout(30)

CRED = os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', 'credentials.json')
ADMIN_SHEET = '1heNPe8GCggJVoUlGvDVwBt697LvD7vtQT5yDfsnV4M0'
TAB = 'Users'
HEADERS = ['Email', 'Name', 'Studio', 'Trial Start', 'Trial End', 'Status', 'Plan', 'Period End',
           'Stripe Customer', 'Stripe Subscription', 'Activated', 'Sheets', 'Created', 'Last Seen', 'Password Hash', 'Code Until', 'Codes Used',
           'CardBoard', 'DevBoard', 'PitchBoard', 'BillBoard', 'All Apps', 'App Codes', 'Image', 'Trial Apps']
# The app columns say which apps the person pays for: the date the paid time runs to (the Stripe webhook keeps
# it current; blank when cancelled). DevBoard's cheaper plan is written "2026-12-20 core". "Perpetual" (typed by
# you) unlocks that app for good. All Apps is the all-in-one plan: every app.
# App Codes: time from free codes made for one app — "DevBoard 2026-12-20, CardBoard Perpetual".
# (A code for every app goes in Code Until, or Activated when it's Perpetual.)
TRIAL_DAYS = 7
CODES_TAB = 'Codes'   # free codes you hand out: each unlocks CardBoard for its Length, once per person
CODES_HEADERS = ['Code', 'Length', 'Max Uses', 'Used', 'Expires', 'Note', 'App']   # who used which: the Users tab's Codes Used
# App: blank (or All) = every app; else the app it unlocks — CardBoard, DevBoard, PitchBoard or BillBoard
# (several, comma separated, are fine).
APP_NAMES = {'cardboard': 'CardBoard', 'cb': 'CardBoard', 'devboard': 'DevBoard', 'db': 'DevBoard',
             'pitchboard': 'PitchBoard', 'pb': 'PitchBoard', 'billboard': 'BillBoard', 'bb': 'BillBoard'}

def code_apps(v):
    # The App cell → [] for every app, else the app names; None when it names an app we don't know
    parts = [x.strip().lower().replace(' ', '') for x in str(v or '').split(',') if x.strip()]
    if not parts or any(x in ('all', 'allapps', 'every', 'everyapp') for x in parts): return []
    if any(x not in APP_NAMES for x in parts): return None
    return list(dict.fromkeys(APP_NAMES[x] for x in parts))

def app_codes(v):
    # "DevBoard 2026-12-20, CardBoard Perpetual" → {'DevBoard': '2026-12-20', 'CardBoard': 'Perpetual'}
    d = {}
    for part in str(v or '').split(','):
        bits = part.strip().split(None, 1)
        if len(bits) == 2 and bits[0] in APP_NAMES.values(): d[bits[0]] = bits[1].strip()
    return d

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
        out({'error': f'Could not open the GameBoard Admin sheet (share it with the service account as an Editor): {e}'})

    ws = next((w for w in wb.worksheets() if w.title.lower() == TAB.lower()), None)
    if ws is None:
        ws = wb.add_worksheet(title=TAB, rows=200, cols=len(HEADERS))
        wb.values_update(q(TAB) + '!A1', params={'valueInputOption': 'RAW'}, body={'values': [HEADERS]})
    cws0 = next((w for w in wb.worksheets() if w.title.lower() == CODES_TAB.lower()), None)
    if cws0 is None:   # ready for you to add codes
        wb.add_worksheet(title=CODES_TAB, rows=100, cols=len(CODES_HEADERS))
        wb.values_update(q(CODES_TAB) + '!A1', params={'valueInputOption': 'RAW'}, body={'values': [CODES_HEADERS]})
    else:              # an older Codes tab: add the headings this version needs (App)
        ch0 = [h.strip() for h in (wb.values_get(q(cws0.title) + '!1:1').get('values', [[]]) or [[]])[0]]
        if ch0 and any(h not in ch0 for h in CODES_HEADERS):
            wb.values_update(q(cws0.title) + '!A1', params={'valueInputOption': 'RAW'}, body={'values': [ch0 + [h for h in CODES_HEADERS if h not in ch0]]})
    vals = wb.values_get(q(ws.title)).get('values', [])
    hdr = [h.strip() for h in (vals[0] if vals else [])]
    missing = [h for h in HEADERS if h not in hdr]
    if missing:   # add any headings this version needs after the existing ones
        hdr = hdr + missing
        wb.values_update(q(ws.title) + '!A1', params={'valueInputOption': 'RAW'}, body={'values': [hdr]})
    rows = vals[1:] if vals else []
    # Image holds =IMAGE("link") (so the sheet shows the picture): read the formula and keep only the link
    if 'Image' in hdr and rows:
        ic = col(hdr.index('Image') + 1)
        fv = wb.values_get(q(ws.title) + '!' + ic + '2:' + ic, params={'valueRenderOption': 'FORMULA'}).get('values', [])
        ii = hdr.index('Image')
        for n, r in enumerate(rows):
            f = str(fv[n][0]) if n < len(fv) and fv[n] else ''
            m = re.match(r'^\s*=\s*IMAGE\(\s*"([^"]+)"', f, re.I)
            v = m.group(1) if m else f
            while len(r) <= ii: r.append('')
            r[ii] = v

    def rec(r): return {h: (r[i] if i < len(r) else '') for i, h in enumerate(hdr)}
    def find(field, value):
        if field not in hdr: return 0, None
        i = hdr.index(field); v = str(value).strip().lower()
        for n, r in enumerate(rows):
            if i < len(r) and str(r[i]).strip().lower() == v and v: return n + 2, rec(r)
        return 0, None
    def write(rowno, d):
        line = [d.get(h, '') for h in hdr]
        if rowno and 'Image' in hdr:
            # The row is written around the Image cell, which is only touched when the picture changed. (Writing the
            # whole row blanked it for a moment before its =IMAGE() formula went back in: another sign-in reading the
            # row just then saw no picture and wrote it back empty — the picture was lost.)
            ii = hdr.index('Image'); r = str(rowno)
            img = str(d.get('Image', '') or '').strip()
            was = str(rec(rows[rowno - 2]).get('Image', '') or '').strip() if 0 <= rowno - 2 < len(rows) else None
            data = []
            if ii > 0: data.append({'range': q(ws.title) + '!A' + r + ':' + col(ii) + r, 'values': [line[:ii]]})
            if ii < len(hdr) - 1: data.append({'range': q(ws.title) + '!' + col(ii + 2) + r + ':' + col(len(hdr)) + r, 'values': [line[ii + 1:]]})
            if data: wb.values_batch_update({'valueInputOption': 'RAW', 'data': data})
            if img != was:
                cell = q(ws.title) + '!' + col(ii + 1) + r
                if img and re.match(r'^https?://[^"\s]+$', img):
                    wb.values_update(cell, params={'valueInputOption': 'USER_ENTERED'}, body={'values': [['=IMAGE("' + img + '")']]})
                else:
                    wb.values_update(cell, params={'valueInputOption': 'RAW'}, body={'values': [[img]]})
            rows[rowno - 2] = list(line)
        elif rowno:
            wb.values_update(q(ws.title) + '!A' + str(rowno) + ':' + col(len(hdr)) + str(rowno), params={'valueInputOption': 'RAW'}, body={'values': [line]})
        else:
            wb.values_append(q(ws.title) + '!A1', params={'valueInputOption': 'RAW', 'insertDataOption': 'INSERT_ROWS'}, body={'values': [line]})
    def new_person(email, waiting=False):
        t = now()
        if waiting: return {'Email': email, 'Status': 'waiting', 'Created': iso(t)}   # the free week starts when let in
        return {'Email': email, 'Trial Start': iso(t), 'Trial End': iso(t + datetime.timedelta(days=TRIAL_DAYS)),
                'Status': 'trial', 'Created': iso(t)}
    def start_trial(u):
        t = now()
        u['Trial Start'] = iso(t); u['Trial End'] = iso(t + datetime.timedelta(days=TRIAL_DAYS))
        if str(u.get('Status', '')).strip().lower() in ('', 'waiting'): u['Status'] = 'trial'
    def waiting_room_on():
        # settings tab: WaitingRoomOn in column A, TRUE in column B
        sws = next((w for w in wb.worksheets() if w.title.lower() == 'settings'), None)
        if sws is None: return False
        for row in wb.values_get(q(sws.title) + '!A:B').get('values', []):
            if len(row) >= 2 and str(row[0]).strip().lower() == 'waitingroomon':
                return str(row[1]).strip().lower() in ('true', 'yes', 'on', '1', 'y')
        return False
    def is_waiting(u): return str((u or {}).get('Status', '')).strip().lower() == 'waiting'

    action = data.get('action')
    ALL4 = ['PitchBoard', 'DevBoard', 'CardBoard', 'BillBoard']
    if action == 'waiting':
        people = []
        for x in (rec(r) for r in rows):
            ta = app_codes(x.get('Trial Apps', ''))
            if x.get('Email') and (is_waiting(x) or (ta and any(a not in ta for a in ALL4))):
                people.append({'Email': x.get('Email', ''), 'Name': x.get('Name', ''), 'Created': x.get('Created', ''),
                               'Waiting': is_waiting(x), 'Apps': [a for a in ALL4 if a in ta]})
        people.sort(key=lambda x: x['Created'] or '9')
        out({'ok': True, 'users': people})
    if action == 'letin':
        apps = [APP_NAMES[a.strip().lower()] for a in (data.get('apps') or []) if a.strip().lower() in APP_NAMES] or ALL4
        done, errors = [], []
        for em in data.get('emails') or []:
            em = str(em).strip().lower()
            rn, x = find('Email', em)
            if not x: errors.append(em + ': no account'); continue
            have = app_codes(x.get('Trial Apps', ''))
            if not is_waiting(x) and not have: errors.append(em + ': already has every app'); continue
            t = now(); new = [a for a in apps if a not in have]
            for a in new: have[a] = iso(t + datetime.timedelta(days=TRIAL_DAYS))
            if not new: continue
            x['Trial Apps'] = ', '.join(a + ' ' + have[a] for a in ALL4 if a in have)
            if not str(x.get('Trial Start', '')).strip(): x['Trial Start'] = iso(t)
            x['Trial End'] = max(have.values())
            if is_waiting(x): x['Status'] = 'trial'
            write(rn, x)
            rows[rn - 2] = [x.get(h, '') for h in hdr]
            done.append({'Email': em, 'Name': x.get('Name', ''), 'Apps': new})
        out({'ok': True, 'done': done, 'errors': errors})
    if action == 'find':
        n, u = find(str(data.get('field') or ''), data.get('value') or '')
        out({'ok': True, 'user': u})

    email = str(data.get('email') or '').strip().lower()
    if not email or '@' not in email: out({'error': 'Missing email'})
    n, u = find('Email', email)

    if action == 'user':
        if not u: u = new_person(email, bool(data.get('signup')) and waiting_room_on())
        elif not is_waiting(u) and not str(u.get('Trial Start', '')).strip() and not str(u.get('Trial End', '')).strip() and not str(u.get('Trial Apps', '')).strip():
            start_trial(u)   # let in by changing Status in the sheet: the free week starts now
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
        if cws and ch and any(h not in ch for h in CODES_HEADERS):   # older Codes tabs: add the App heading
            ch = ch + [h for h in CODES_HEADERS if h not in ch]
            wb.values_update(q(cws.title) + '!A1', params={'valueInputOption': 'RAW'}, body={'values': [ch]})
        def ci(name): return ch.index(name) if name in ch else -1
        row_no, row = 0, None
        for k, r in enumerate(cv[1:]):
            if ci('Code') >= 0 and ci('Code') < len(r) and str(r[ci('Code')]).strip().lower() == code.lower() and code:
                row_no, row = k + 2, list(r) + [''] * (len(ch) - len(r)); break
        if not row: out({'ok': False, 'unknown': True})
        days = length_days(row[ci('Length')] if ci('Length') >= 0 else '')
        if days == 0: out({'error': 'That code isn’t set up yet — ask whoever gave it to you'})
        apps = code_apps(row[ci('App')] if 0 <= ci('App') < len(row) else '')
        if apps is None: out({'error': 'That code isn’t set up right (its App isn’t one of the four) — ask whoever gave it to you'})
        exp = str(row[ci('Expires')]).strip() if ci('Expires') >= 0 else ''
        if exp:   # typed as 2026-12-31, or shown by Sheets as 12/31/2026
            ed = None
            for fmt in ('%Y-%m-%d', '%m/%d/%Y', '%Y/%m/%d', '%d %b %Y', '%b %d, %Y'):
                try: ed = datetime.datetime.strptime(exp.split(' ')[0] if fmt in ('%Y-%m-%d', '%m/%d/%Y', '%Y/%m/%d') else exp, fmt); break
                except ValueError: pass
            if ed and ed + datetime.timedelta(days=1) < now(): out({'error': 'That code has expired'})
        # Once per person: the codes each person has used are listed in their Users row (Codes Used)
        mine = [c.strip().lower() for c in str(u.get('Codes Used', '')).split(',') if c.strip()]
        # A Perpetual code used before but not showing on the account (its Length was changed to Perpetual later,
        # or the row was edited): it's applied again, without counting another use
        again = False
        if code.lower() in mine:
            have_it = (str(u.get('Activated', '')).strip() != '') if not apps else \
                      all(app_codes(u.get('App Codes', '')).get(a, '').lower() == 'perpetual' for a in apps)
            if days != PERPETUAL or have_it: out({'error': 'You’ve already used that code'})
            again = True
        if not again:
            used = str(row[ci('Used')]).strip() if ci('Used') >= 0 else ''
            used = int(used) if used.isdigit() else 0
            mx = str(row[ci('Max Uses')]).strip() if ci('Max Uses') >= 0 else ''
            if mx.isdigit() and used >= int(mx): out({'error': 'That code has been used up'})
            if ci('Used') >= 0: row[ci('Used')] = str(used + 1)
            wb.values_update(q(cws.title) + '!A' + str(row_no) + ':' + col(len(ch)) + str(row_no), params={'valueInputOption': 'RAW'}, body={'values': [row[:len(ch)]]})
        # The new time starts when the current access runs out: after any code time left, or after the free week
        def when(v):
            try: return datetime.datetime.strptime(str(v or '')[:19], '%Y-%m-%d %H:%M:%S')
            except ValueError:
                try: return datetime.datetime.strptime(str(v or '')[:10], '%Y-%m-%d')
                except ValueError: return None
        def later(*vals):
            start = now()
            for v in vals:
                cur = when(v)
                if cur and cur > start: start = cur
            return start
        if not apps:   # every app
            if days == PERPETUAL: u['Activated'] = now().strftime('%Y-%m-%d')   # unlocked for good
            else: u['Code Until'] = iso(later(u.get('Code Until'), u.get('Trial End')) + datetime.timedelta(days=days))
        else:          # only the code's apps
            have = app_codes(u.get('App Codes', ''))
            for a in apps:
                if have.get(a, '').lower() == 'perpetual': continue
                have[a] = 'Perpetual' if days == PERPETUAL else iso(later(have.get(a), u.get('Code Until'), u.get('Trial End')) + datetime.timedelta(days=days))
            u['App Codes'] = ', '.join(k + ' ' + v for k, v in have.items())
        if not again: u['Codes Used'] = ', '.join([c for c in str(u.get('Codes Used', '')).split(', ') if c] + [str(row[ci('Code')]).strip()])
        u['Last Seen'] = iso(now())
        write(n, u)
        out({'ok': True, 'user': u, 'days': days, 'apps': apps})

    if action == 'rename':   # a new sign-in email for the same row (everything else stays: sheets, plans, codes)
        if not u: out({'error': 'There’s no account for that email'})
        new = str(data.get('new_email') or '').strip().lower()
        if not new or '@' not in new: out({'error': 'Missing the new email'})
        n2, u2 = find('Email', new)
        if u2: out({'error': 'That email already has a GameBoard account'})
        u['Email'] = new
        u['Last Seen'] = iso(now())
        write(n, u)
        out({'ok': True, 'user': u})

    if action == 'update':
        u = u or new_person(email)
        for k, v in (data.get('fields') or {}).items():
            if k.startswith('+') and k[1:] in hdr and v:   # add to a comma list, once
                have = [x.strip() for x in str(u.get(k[1:], '')).split(',') if x.strip()]
                if str(v) not in have: u[k[1:]] = ','.join(have + [str(v)])
            elif k in hdr and k != 'Email': u[k] = '' if v is None else str(v)
        write(n, u)
        out({'ok': True, 'user': u})

    out({'error': 'Unknown action'})

main()
