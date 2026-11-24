# gfixcontractrefs.py — give contracts with an old-style Ref Number (a plain number, or none) a standard one:
# 8 uppercase letters and digits, as new contracts get (see gaddcontractrow.py). Notes that point to an old
# number ("Invoice from estimate #7") follow it.
# Arg: {sheet_id}
# Returns { ok, changed: [{row, old, new}] }

import gspread
import gretry  # retry temporary Google API errors (see gretry.py)
import sys, os, json, re, hashlib, time
from gspread.utils import rowcol_to_a1

cred = os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', 'credentials.json')
try:
    wb = gspread.service_account(filename=cred).open_by_key(sys.argv[1].strip())
except Exception as e:
    print(json.dumps({"error": f"Could not open spreadsheet: {e}"})); sys.exit(0)

ws = next((w for w in wb.worksheets() if w.title.strip().lower() == 'contracts'), None)
if not ws:
    print(json.dumps({"ok": True, "changed": []})); sys.exit(0)
rows = ws.get_all_values()
if not rows:
    print(json.dumps({"ok": True, "changed": []})); sys.exit(0)
hdr = [h.strip() for h in rows[0]]
if 'Ref Number' not in hdr:
    print(json.dumps({"error": "Ref Number column not found in contracts sheet"})); sys.exit(0)
rc = hdr.index('Ref Number')
nc = hdr.index('Notes') if 'Notes' in hdr else -1
gc = hdr.index('Game') if 'Game' in hdr else -1
cc = hdr.index('Client') if 'Client' in hdr else -1
GOOD = re.compile(r'^[0-9A-Z]{8}$')
ALPHA = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ'
cell = lambda r, i: (r[i] if 0 <= i < len(r) else '').strip()
taken = {cell(r, rc) for r in rows[1:]}

def new_code(seed):
    while True:
        n = int(hashlib.sha256(f"{time.time_ns()}-{seed}".encode()).hexdigest()[:12], 16)
        s = ''
        while n: s = ALPHA[n % 36] + s; n //= 36
        s = s.zfill(8)[:8]
        if s not in taken and not s.isdigit() and not re.match(r'^\d+E\d+$', s): taken.add(s); return s

changed, updates = [], []
for i, r in enumerate(rows[1:], start=2):
    old = cell(r, rc).lstrip("'")
    if GOOD.match(old) or not any(x.strip() for x in r): continue
    if not old and not (cell(r, gc) or cell(r, cc)): continue
    new = new_code(f"{i}-{cell(r, gc)}-{cell(r, cc)}")
    changed.append({"row": i, "old": old, "new": new})
    updates.append({"range": rowcol_to_a1(i, rc + 1), "values": [[new]]})

# Notes that name an old number ("… #7") point to the new code
olds = {c['old']: c['new'] for c in changed if c['old']}
if olds and nc >= 0:
    for i, r in enumerate(rows[1:], start=2):
        v = cell(r, nc)
        nv = re.sub(r'#(\w+)\b', lambda m: '#' + olds.get(m.group(1), m.group(1)), v)
        if nv != v: updates.append({"range": rowcol_to_a1(i, nc + 1), "values": [[nv]]})

if updates:
    ws.batch_update(updates, value_input_option='RAW')
print(json.dumps({"ok": True, "changed": changed}))
