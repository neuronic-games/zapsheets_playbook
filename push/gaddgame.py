# gaddgame.py — append a new row to the Games sheet.
#
# Arg:  "{sheet_id}|{base64_encoded_json}"
# JSON: { name, tagline, description, status, availability, count, duration,
#         designer1-4, dateStarted, dateSigned, datePublished,
#         cover, image, video, view, sellsheet, rules, play, print }
# Returns: {"ok": true, "row": N} or {"error": "..."}

import gspread
import sys, os, json, base64, socket

socket.setdefaulttimeout(30)

credFileName = os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', 'credentials.json')
if not os.path.exists(credFileName):
    print(json.dumps({"error": "credentials.json not found"}))
    sys.exit(1)

try:
    sa = gspread.service_account(filename=credFileName)
except Exception as e:
    print(json.dumps({"error": "Could not authenticate: " + str(e)}))
    sys.exit(1)

arg = sys.argv[1] if len(sys.argv) > 1 else ''
if '|' not in arg:
    print(json.dumps({"error": "invalid argument — expected {sheet_id}|{base64_json}"}))
    sys.exit(1)

pipe_idx = arg.index('|')
sheet_id = arg[:pipe_idx]
data     = json.loads(base64.b64decode(arg[pipe_idx + 1:]).decode('utf-8'))

def gv(key): return (data.get(key) or '').strip()

name           = gv('name')
tagline        = gv('tagline')
description    = gv('description')
status         = gv('status')
availability   = gv('availability')
count          = gv('count')
duration       = gv('duration')
designer1      = gv('designer1')
designer2      = gv('designer2')
designer3      = gv('designer3')
designer4      = gv('designer4')
date_started   = gv('dateStarted')
date_signed    = gv('dateSigned')
date_published = gv('datePublished')
cover          = gv('cover')    # already =IMAGE("url") or ''
image          = gv('image')    # already =IMAGE("url") or ''
video          = gv('video')
view           = gv('view')
sellsheet      = gv('sellsheet')
rules          = gv('rules')
play           = gv('play')
print_url      = gv('print')

if not name:
    print(json.dumps({"error": "name is required"}))
    sys.exit(1)

try:
    wb = sa.open_by_key(sheet_id)
except Exception as e:
    print(json.dumps({"error": "Could not open spreadsheet: " + str(e)}))
    sys.exit(1)

ws = next((w for w in wb.worksheets() if w.title.lower() == 'games'), None)
if ws is None:
    print(json.dumps({"error": "Games worksheet not found"}))
    sys.exit(1)

try:
    headers = ws.row_values(1)
except Exception as e:
    print(json.dumps({"error": "Could not read headers: " + str(e)}))
    sys.exit(1)

if not headers:
    print(json.dumps({"error": "Games sheet has no headers"}))
    sys.exit(1)

# Duplicate name check
try:
    name_col_idx = next((i for i, h in enumerate(headers) if h.strip() == 'Name'), -1)
    if name_col_idx >= 0:
        existing = ws.col_values(name_col_idx + 1)[1:]
        for n in existing:
            if n.strip().lower() == name.lower():
                print(json.dumps({"error": "A game named '" + name + "' already exists."}))
                sys.exit(1)
except Exception:
    pass

def safe_str(v):
    """Prefix with ' to prevent formula interpretation, except =IMAGE() which passes through."""
    s = str(v).strip() if v else ''
    if not s: return ''
    if s.upper().startswith('=IMAGE('): return s
    return "'" + s

# Map header variants → value
field_map = {
    'Name':            name,
    'Summary':         tagline,
    'Tagline':         tagline,
    'Tag Line':        tagline,
    'Description':     description,
    'Status':          status,
    'Availability':    availability,
    'Count':           count,
    'Duration':        duration,
    'Designer1':       designer1,
    'Designer 1':      designer1,
    'Designer2':       designer2,
    'Designer 2':      designer2,
    'Designer3':       designer3,
    'Designer 3':      designer3,
    'Designer4':       designer4,
    'Designer 4':      designer4,
    'Date Started':    date_started,
    'DateStarted':     date_started,
    'Start Date':      date_started,
    'Date Signed':     date_signed,
    'DateSigned':      date_signed,
    'Date Published':  date_published,
    'DatePublished':   date_published,
    'Cover URL':       cover,
    'CoverURL':        cover,
    'Image URL':       image,
    'ImageURL':        image,
    'Image':           image,
    'Video URL':       video,
    'VideoURL':        video,
    'Video':           video,
    'Page URL':        view,
    'BGG / View URL':  view,
    'View URL':        view,
    'View':            view,
    'BGG':             view,
    'Sellsheet URL':   sellsheet,
    'Sellsheet':       sellsheet,
    'Rules URL':       rules,
    'Rules':           rules,
    'Play URL':        play,
    'Play':            play,
    'Print URL':       print_url,
    'Print':           print_url,
}

new_row = []
for h in headers:
    val = field_map.get(h.strip(), '')
    new_row.append(safe_str(val) if val else '')

try:
    ws.append_row(new_row, value_input_option='USER_ENTERED')
except Exception as e:
    print(json.dumps({"error": "Could not append row: " + str(e)}))
    sys.exit(1)

print(json.dumps({"ok": True, "name": name}))
