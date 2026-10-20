# gupdategametab.py — update Component, Review, and FAQ rows in a [GameName] tab.
#
# Arg:  "{sheet_id}|{base64_encoded_json}"
# JSON: {
#   "game":       "Lair",
#   "components": ["50 Cards", "1 Board"],
#   "reviews":    [{"attr": "BoardGameGeek", "quote": "Amazing!"}],
#   "faqs":       [{"q": "How many players?", "a": "2-5 players"}]
# }
# Returns: {"ok": true, "rows_written": N} or {"error": "..."}
#
# Strategy: read all rows, replace blocks of Component/Review/FAQ rows
# with new content (preserving all other rows), then rewrite the sheet.

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

game_name  = data.get('game', '').strip()
components = [str(c).strip() for c in data.get('components', []) if str(c).strip()]
reviews    = data.get('reviews', [])
videos     = data.get('videos', [])
faqs       = data.get('faqs', [])
artists    = [str(a).strip() for a in data.get('artists', []) if str(a).strip()]
buy_urls   = data.get('buy_urls', [])

if not sheet_id or not game_name:
    print(json.dumps({"error": "sheet_id and game are required"}))
    sys.exit(1)

try:
    wb = sa.open_by_key(sheet_id)
except Exception as e:
    print(json.dumps({"error": "Could not open spreadsheet: " + str(e)}))
    sys.exit(1)

# Find the [GameName] tab (case-insensitive)
bracketed = '[' + game_name + ']'
ws = None
for w in wb.worksheets():
    if w.title == bracketed or w.title.lower() == bracketed.lower():
        ws = w
        break
if ws is None:
    print(json.dumps({"error": "Tab '" + bracketed + "' not found"}))
    sys.exit(1)

try:
    all_rows = ws.get_all_values()
except Exception as e:
    print(json.dumps({"error": "Could not read tab: " + str(e)}))
    sys.exit(1)

# Build the replacement content for each block type.
# Each list must have at least one placeholder row so the section stays present.
BLOCK_TYPES = ('Component', 'Review', 'Video', 'FAQ', 'Artist', 'BuyUrl')
block_content = {
    'Component': [['Component', c, ''] for c in components] or [['Component', '', '']],
    'Review':    [['Review', str(r.get('attr','')), str(r.get('quote',''))] for r in reviews if isinstance(r, dict)] or [['Review', '', '']],
    'Video':     [['Video', str(v.get('url','')), str(v.get('name',''))] for v in videos if isinstance(v, dict)] or [['Video', '', '']],
    'FAQ':       [['FAQ', str(f.get('q','')), str(f.get('a',''))] for f in faqs if isinstance(f, dict)] or [['FAQ', '', '']],
    'Artist':    [['Artist', a, ''] for a in artists] or [['Artist', '', '']],
    'BuyUrl':    [['BuyUrl', str(b.get('url','')), str(b.get('label',''))] for b in buy_urls if isinstance(b, dict) and b.get('url','')] or [['BuyUrl', '', '']],
}

# Rebuild rows: keep non-Component/Review/FAQ rows as-is,
# replace each block with new_rows the first time it appears.
new_rows = []
blocks_written = set()

i = 0
while i < len(all_rows):
    row = all_rows[i]
    # Normalise to exactly 3 columns
    padded = (list(row) + ['', '', ''])[:3]
    row_type = padded[0].strip()

    if row_type in BLOCK_TYPES:
        # Consume all consecutive rows of this block type
        while i < len(all_rows) and (all_rows[i] + [''])[:1][0].strip() == row_type:
            i += 1
        # Write replacement content (only once per block type)
        if row_type not in blocks_written:
            new_rows.extend(block_content[row_type])
            blocks_written.add(row_type)
        # (if the same type appears again in the sheet it gets dropped — unusual but safe)
    else:
        new_rows.append(padded)
        i += 1

# Any block type missing from the original tab gets appended at the end
for btype in BLOCK_TYPES:
    if btype not in blocks_written:
        new_rows.append(['', '', ''])
        new_rows.extend(block_content[btype])

# Rewrite the sheet: clear first, then write back all rows
try:
    ws.clear()
    if new_rows:
        try:
            ws.update(range_name='A1', values=new_rows, value_input_option='USER_ENTERED')
        except TypeError:
            # Older gspread: positional (range, values)
            ws.update('A1', new_rows, value_input_option='USER_ENTERED')
except Exception as e:
    print(json.dumps({"error": "Could not write tab: " + str(e)}))
    sys.exit(1)

print(json.dumps({"ok": True, "rows_written": len(new_rows)}))
