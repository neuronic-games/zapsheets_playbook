# gcreategameboardtab.py — create a [GameName] tab for a GameBoard game page.
#
# If the tab already exists, returns {"ok": true, "tab": "...", "existed": true}.
# If created fresh, pre-populates from games.json values.
#
# Sheet format (no header row):
#   Col A = field name   (e.g. "Description", "Component", "FAQ")
#   Col B = primary value / question
#   Col C = secondary value / answer (for FAQ, Review, Video)
#
# Argument:  "{sheet_id}|{game_name}"
# Returns (stdout): {"ok": true, "tab": "...", "existed": bool} or {"error": "..."}

import gspread
import sys, os, json, socket

socket.setdefaulttimeout(30)

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

bracketed = '[' + game_name + ']'

# If tab already exists (case-insensitive), return early
existing = wb.worksheets()
match = next((w for w in existing
              if w.title == bracketed or w.title.lower() == bracketed.lower()), None)
if match is not None:
    print(json.dumps({"ok": True, "tab": match.title, "existed": True}))
    sys.exit(0)

# ── Pre-populate from games.json ──────────────────────────────────────────────
game_info  = {}
games_path = os.path.join(os.path.dirname(os.path.abspath(__file__)),
                          '..', 'sheets', sheet_id, 'games.json')
if os.path.exists(games_path):
    try:
        with open(games_path, 'r', encoding='utf8') as f:
            for g in json.load(f):
                if (g.get('Name', '') == game_name or
                        g.get('Name', '').lower() == game_name.lower()):
                    game_info = g
                    break
    except Exception:
        pass

def gv(key):
    return (game_info.get(key) or '').strip()

def strip_image(v):
    """Strip =IMAGE("url") wrapper to get plain URL."""
    import re
    m = re.match(r'=IMAGE\("([^"]*)"\)', v, re.IGNORECASE)
    return m.group(1) if m else v

description  = gv('Description')
summary      = gv('Summary')
cover_url    = strip_image(gv('Cover URL'))
image_url    = strip_image(gv('Image URL'))
video_url    = gv('Video URL')
rules_url    = gv('Rules URL')
play_url     = gv('Play URL')
sellsheet    = gv('Sellsheet URL')
page_url     = gv('Page URL')

designers = [gv(f'Designer{i}') for i in range(1, 5)]
designers = [d for d in designers if d]
if not designers:
    designers = ['', '']   # two blank placeholders

# ── Build rows ────────────────────────────────────────────────────────────────
# Each entry: [Col A (Name), Col B (Value), Col C (Value 1)]
rows = []

# Core text content
rows += [
    ['Description',  description,  ''],
    ['',             '',           ''],
]

# Player count / time (pre-populated blank for manual entry)
rows += [
    ['MinPlayers',   '',  ''],
    ['MaxPlayers',   '',  ''],
    ['MinPlaytime',  '',  ''],
    ['MaxPlaytime',  '',  ''],
    ['Weight',       '',  ''],
    ['Price',        '',  ''],
    ['',             '',  ''],
]

# Designers
for d in designers:
    rows.append(['Designer', d, ''])
rows.append(['', '', ''])

# Gallery images — cover first, then hero image, then blanks for more
for url in [cover_url, image_url]:
    rows.append(['ProductImage', url, ''])
rows += [
    ['ProductImage',  '',  ''],
    ['ProductImage',  '',  ''],
    ['Media',         '',  ''],
    ['Media',         '',  ''],
    ['',              '',  ''],
]

# Videos: col B = URL, col C = label
rows += [
    ['Video',  video_url,  'About the game'],
    ['Video',  '',         ''],
    ['Video',  '',         ''],
    ['',       '',         ''],
]

# Components (what's in the box)
rows += [
    ['Component',  '',  ''],
    ['Component',  '',  ''],
    ['Component',  '',  ''],
    ['Component',  '',  ''],
    ['',           '',  ''],
]

# Reviews: col B = reviewer name / source, col C = quote
rows += [
    ['Review',  '',  ''],
    ['Review',  '',  ''],
    ['Review',  '',  ''],
    ['',        '',  ''],
]

# FAQs: col B = question, col C = answer
rows += [
    ['FAQ',  '',  ''],
    ['FAQ',  '',  ''],
    ['FAQ',  '',  ''],
    ['',     '',  ''],
]

# Where to buy: col B = URL, col C = label
rows += [
    ['BuyUrl',  play_url,   'Play Online'],
    ['BuyUrl',  '',         ''],
    ['BuyUrl',  '',         ''],
    ['',        '',         ''],
]

# Resource links
rows += [
    ['Rules PDF Url',  rules_url,  ''],
    ['Sellsheet URL',  sellsheet,  ''],
    ['Page URL',       page_url,   ''],
]

# ── Create the worksheet ──────────────────────────────────────────────────────
try:
    ws = wb.add_worksheet(title=bracketed, rows=max(len(rows) + 10, 80), cols=4)
except Exception as e:
    print(json.dumps({"error": "could not create worksheet: " + str(e)}))
    sys.exit(1)

try:
    ws.update(rows, 'A1')
except Exception as e:
    print(json.dumps({"error": "could not write default data: " + str(e)}))
    sys.exit(1)

print(json.dumps({"ok": True, "tab": bracketed, "existed": False}))
sys.exit(0)
