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

# Per-game tabs are named [Game Name] so they're visually distinct from the
# structural tabs (Pitches, Games, People, Settings) in the spreadsheet.
bracketed = '[' + game_name + ']'

# Refuse if a tab already exists under either name (bracketed or plain)
existing = wb.worksheets()
match = next((w for w in existing if w.title == bracketed), None)
if match is None:
    match = next((w for w in existing if w.title == game_name), None)
if match is None:
    match = next((w for w in existing if w.title.lower() == bracketed.lower()), None)
if match is None:
    match = next((w for w in existing if w.title.lower() == game_name.lower()), None)
if match is not None:
    print(json.dumps({"error": "tab_exists", "name": match.title}))
    sys.exit(1)

# Create the worksheet with brackets
try:
    ws = wb.add_worksheet(title=bracketed, rows=60, cols=4)
except Exception as e:
    print(json.dumps({"error": "could not create worksheet: " + str(e)}))
    sys.exit(1)

# ── Pre-populate from games.json if available ─────────────────────────────────
game_info  = {}
games_path = os.path.join(os.path.dirname(os.path.abspath(__file__)),
                          '..', 'sheets', sheet_id, 'games.json')
if os.path.exists(games_path):
    try:
        with open(games_path, 'r', encoding='utf8') as _f:
            for _g in json.load(_f):
                if (_g.get('Name', '') == game_name or
                        _g.get('Name', '').lower() == game_name.lower()):
                    game_info = _g
                    break
    except Exception:
        pass

def gv(key):
    """Return a stripped value from game_info, or '' if absent/empty."""
    return (game_info.get(key) or '').strip()

# Collect non-empty designers
designers = [gv(f'Designer{i}') for i in range(1, 5)]
designers = [d for d in designers if d]
if not designers:
    designers = ['', '']       # keep two blank rows as placeholders

description     = gv('Summary') or gv('Description')
pitch_desc      = gv('Summary') if (gv('Description') and gv('Summary') != gv('Description')) else ''
rules_url       = gv('Rules URL')
play_url        = gv('Play URL')
video_url       = gv('Video URL')
sellsheet_url   = gv('Sellsheet URL')

# ── Build rows ─────────────────────────────────────────────────────────────────
# [col_A (field), col_B (value), col_C (extra)]
default_rows = [
    ['BggGameId',        '',           ''],
    ['ProductImage',     '',           ''],
    ['MinPlayers',       '',           ''],
    ['MaxPlayers',       '',           ''],
    ['MinPlaytime',      '',           ''],
    ['MaxPlaytime',      '',           ''],
    ['',                 '',           ''],
]

# One Designer row per name (or two blank placeholders)
for d in designers:
    default_rows.append(['Designer', d, ''])

default_rows += [
    ['',                 '',           ''],
    ['Price',            '',           ''],
    ['Stock',            '',           ''],
    ['Weight',           '',           ''],
    ['',                 '',           ''],
    ['BuyUrl',           'Shop URL',   'Shop name'],
    ['BuyUrl',           'Shop URL',   'Shop name'],
    ['',                 '',           ''],
    ['Review',           'Review text','Reviewer'],
    ['Review',           'Review text','Reviewer'],
    ['',                 '',           ''],
    ['Video',            video_url,    'Creator'],
    ['Video',            'Video URL',  'Creator'],
    ['',                 '',           ''],
    ['Component',        '',           ''],
    ['Component',        '',           ''],
    ['Component',        '',           ''],
    ['',                 '',           ''],
    ['PitchImageUrl',    sellsheet_url,''],
    ['PitchDescription', pitch_desc,   ''],
    ['Feature',          '',           ''],
    ['Feature',          '',           ''],
    ['Feature',          '',           ''],
]

try:
    ws.update(default_rows, 'A1')
except Exception as e:
    print(json.dumps({"error": "could not write default data: " + str(e)}))
    sys.exit(1)

print(json.dumps({"ok": True, "tab": bracketed}))
