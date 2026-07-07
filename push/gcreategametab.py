# gcreategametab.py — create a new game tab with default fields.
#
# Sheet format (no header row):
#   Col A  = field name  (e.g. "Title", "Designer")
#   Col B  = primary value
#   Col C  = secondary value (for BuyUrl / Review / Video)
#
# Argument:  "{sheet_id}|{game_name}"
# Returns (stdout): {"ok": true, "tab": "..."} or {"error": "..."}

import gspread
import sys, os, json

credFileName = os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', 'credentials.json')
if not os.path.exists(credFileName):
    print(json.dumps({"error": "credentials.json not found"}))
    sys.exit(1)

arg = sys.argv[1] if len(sys.argv) > 1 else ''
if '|' not in arg:
    print(json.dumps({"error": "invalid argument — expected {sheet_id}|{game_name}"}))
    sys.exit(1)

sheet_id, game_name = arg.split('|', 1)
game_name = game_name.strip()

if not sheet_id or not game_name:
    print(json.dumps({"error": "sheet_id and game_name must not be empty"}))
    sys.exit(1)

try:
    sa = gspread.service_account(filename=credFileName)
    wb = sa.open_by_key(sheet_id)
except Exception as e:
    print(json.dumps({"error": "could not open sheet: " + str(e)}))
    sys.exit(1)

# Refuse if tab already exists
existing = wb.worksheets()
match = next((w for w in existing if w.title == game_name), None)
if match is None:
    match = next((w for w in existing if w.title.lower() == game_name.lower()), None)
if match is not None:
    print(json.dumps({"error": "tab_exists", "name": match.title}))
    sys.exit(1)

# Create the worksheet
try:
    ws = wb.add_worksheet(title=game_name, rows=60, cols=4)
except Exception as e:
    print(json.dumps({"error": "could not create worksheet: " + str(e)}))
    sys.exit(1)

# Default rows — [col_A (field), col_B (value), col_C (extra)]
default_rows = [
    ['Title',            game_name, ''],
    ['SubTitle',         '',        ''],
    ['BggGameId',        '',        ''],
    ['Description',      '',        ''],
    ['ProductImage',     '',        ''],
    ['MinPlayers',       '',        ''],
    ['MaxPlayers',       '',        ''],
    ['MinPlaytime',      '',        ''],
    ['MaxPlaytime',      '',        ''],
    ['',                 '',        ''],
    ['Designer',         '',        ''],
    ['Designer',         '',        ''],
    ['',                 '',        ''],
    ['Price',            '',        ''],
    ['Stock',            '',        ''],
    ['Weight',           '',        ''],
    ['',                 '',        ''],
    ['BuyUrl',           'Shop URL',        'Shop name'],   # col B = URL, col C = display text
    ['BuyUrl',           'Shop URL',        'Shop name'],   # col B = URL, col C = display text
    ['',                 '',        ''],
    ['Review',           'Review text',        'Reviewer'],   # col B = text, col C = reviewer name
    ['Review',           'Review text',        'Reviewer'],   # col B = text, col C = reviewer name
    ['',                 '',        ''],
    ['Video',            'Video URL',        'Creator'],   # col B = URL,  col C = creator name
    ['Video',            'Video URL',        'Creator'],   # col B = URL,  col C = creator name
    ['',                 '',        ''],
    ['Component',        '',        ''],
    ['Component',        '',        ''],
    ['Component',        '',        ''],
    ['',                 '',        ''],
    ['PitchImageUrl',    '',        ''],
    ['PitchDescription', '',        ''],
    ['Feature',          '',        ''],
    ['Feature',          '',        ''],
    ['Feature',          '',        ''],
]

try:
    ws.update(range_name='A1', values=default_rows)
except Exception as e:
    print(json.dumps({"error": "could not write default data: " + str(e)}))
    sys.exit(1)

print(json.dumps({"ok": True, "tab": game_name}))
