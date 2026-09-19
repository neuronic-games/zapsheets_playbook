# gcompendium.py — Read the Cardboard Edison Compendium Google Sheet and write
# /data/publishers.json for use across all PitchBoards.
#
# Arg: {sheet_id}
#
# Columns used (first 24, 0-based):
#   0  Publisher                    → publisher
#   1  Logo                         → logo
#   2  Country                      → country
#   3  Accepting Submissions?        → accepting_submissions
#   4  Catalog Size                  → catalog_size
#   5  Planned Use of Crowdfunding   → crowdfunding
#   6  Profile Updated               → profile_updated
#   7  Website                       → website
#   8  Categories of Interest        → categories
#   9  Conventions Regularly Attended→ conventions
#  10  Interested In                 → looking_for
#  11  Representative Games          → rep_games
#  12  Preferred Method of Contact   → contact_method
#  13  Contact Info                  → contact_info
#  14  BGG                           → bgg
#  15  Facebook                      → facebook
#  16  Twitter                       → twitter
#  17  Bluesky                       → bluesky
#  18  YouTube                       → youtube
#  19  Twitch                        → twitch
#  20  Discord                       → discord
#  21  Instagram                     → instagram
#  22  Other social media            → other_social
#  23  Admin Contact                 → admin_contact

import gspread
import sys, os, json, socket

socket.setdefaulttimeout(60)

credFileName = os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', 'credentials.json')
dataDir      = os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', 'data')
outFile      = os.path.join(dataDir, 'publishers.json')

FIELD_KEYS = [
    'publisher', 'logo', 'country', 'accepting_submissions', 'catalog_size',
    'crowdfunding', 'profile_updated', 'website', 'categories',
    'conventions', 'looking_for', 'rep_games', 'contact_method', 'contact_info',
    'bgg', 'facebook', 'twitter', 'bluesky', 'youtube', 'twitch',
    'discord', 'instagram', 'other_social', 'admin_contact',
]

def out(status, msg, **extra):
    data = {'status': status, 'msg': msg}
    data.update(extra)
    print(json.dumps(data), flush=True)

if not os.path.exists(credFileName):
    out('error', 'credentials.json not found')
    sys.exit(1)

sheet_id = (sys.argv[1] if len(sys.argv) > 1 else '').strip()
if not sheet_id:
    out('error', 'No sheet ID provided')
    sys.exit(1)

out('info', 'Connecting to Google Sheets…')

try:
    sa = gspread.service_account(filename=credFileName)
except Exception as e:
    out('error', f'Authentication failed: {e}')
    sys.exit(1)

try:
    wb = sa.open_by_key(sheet_id)
except Exception as e:
    out('error', f'Could not open spreadsheet: {e}')
    sys.exit(1)

out('info', f'Opened: {wb.title}')

try:
    ws = wb.sheet1
    out('info', 'Reading all rows…')
    all_values = ws.get_all_values()
except Exception as e:
    out('error', f'Could not read sheet: {e}')
    sys.exit(1)

if len(all_values) < 2:
    out('error', 'Sheet appears empty (no data rows)')
    sys.exit(1)

# Skip the header row; use first 24 columns only
data_rows = all_values[1:]
publishers = []
skipped    = 0

out('info', f'Processing {len(data_rows)} rows…')

for row in data_rows:
    # Pad row to at least 24 columns
    padded = (row + [''] * 24)[:24]
    name = padded[0].strip()
    if not name:
        skipped += 1
        continue
    pub = {key: padded[i].strip() for i, key in enumerate(FIELD_KEYS)}
    publishers.append(pub)

out('info', f'{len(publishers)} publishers found ({skipped} blank rows skipped)')

# Ensure output directory exists
os.makedirs(dataDir, exist_ok=True)

out('info', f'Writing {outFile}…')
try:
    with open(outFile, 'w', encoding='utf-8') as f:
        json.dump(publishers, f, ensure_ascii=False, indent=2)
except Exception as e:
    out('error', f'Could not write publishers.json: {e}')
    sys.exit(1)

size_kb = round(os.path.getsize(outFile) / 1024, 1)
out('ok', f'✓  {len(publishers)} publishers written to data/publishers.json ({size_kb} KB)', count=len(publishers))
