# gaddgame.py — append a new game row to the games sheet
# Arg: {sheet_id}|{base64_encoded_json}
# JSON keys: name, designer1-4, rules, play, print, sellsheet, view, video

import gspread
import sys, os, json, base64

credFileName = os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', 'credentials.json')

if not os.path.exists(credFileName):
    print(json.dumps({"error": "credentials.json not found"}))
    sys.exit(1)

try:
    mServiceAccount = gspread.service_account(filename=credFileName)
except Exception as e:
    print(json.dumps({"error": f"Could not authenticate: {str(e)}"}))
    sys.exit(1)

# Parse arg: {sheet_id}|{base64_json}
arg      = sys.argv[1]
pipe_idx = arg.index('|')
sheet_id = arg[:pipe_idx]
data     = json.loads(base64.b64decode(arg[pipe_idx + 1:]).decode('utf-8'))

name       = data.get('name',      '').strip()
tagline        = data.get('tagline',        '').strip()
status         = data.get('status',         '').strip()
date_started   = data.get('date_started',   '').strip()
date_signed    = data.get('date_signed',    '').strip()
date_published = data.get('date_published', '').strip()
designer1      = data.get('designer1',      '').strip()
designer2  = data.get('designer2', '').strip()
designer3  = data.get('designer3', '').strip()
designer4  = data.get('designer4', '').strip()
rules      = data.get('rules',     '').strip()
play       = data.get('play',      '').strip()
print_url  = data.get('print',     '').strip()
sellsheet  = data.get('sellsheet', '').strip()
view       = data.get('view',      '').strip()
video      = data.get('video',     '').strip()

if not name:
    print(json.dumps({"error": "Game name is required"}))
    sys.exit(1)

# Open spreadsheet
try:
    mGoogleSheet = mServiceAccount.open_by_key(sheet_id)
except Exception as e:
    print(json.dumps({"error": f"Could not open spreadsheet: {str(e)}"}))
    sys.exit(1)

# Find games worksheet (case-insensitive)
all_worksheets = mGoogleSheet.worksheets()
ws = next((w for w in all_worksheets if w.title.lower() == 'games'), None)
if ws is None:
    print(json.dumps({"error": "Games worksheet not found"}))
    sys.exit(1)

# Read header row to determine column positions
try:
    all_values = ws.get_all_values()
except Exception as e:
    print(json.dumps({"error": f"Could not read sheet: {str(e)}"}))
    sys.exit(1)

if not all_values:
    print(json.dumps({"error": "Games sheet is empty (no header row)"}))
    sys.exit(1)

headers = all_values[0]
col = {h.strip(): i for i, h in enumerate(headers)}

def find_col(*variants):
    for v in variants:
        if v in col:
            return col[v]
    return -1

# Build a new row aligned to the header
num_cols = len(headers)
new_row  = [''] * num_cols

def set_col(value, *variants):
    idx = find_col(*variants)
    if idx >= 0:
        new_row[idx] = value

set_col(name,      'Name')
set_col(tagline,        'Tagline', 'Tag Line', 'SubTitle', 'Subtitle')
set_col(status,         'Status')
set_col(date_started,   'Date Started',   'DateStarted',   'Start Date',      'StartDate')
set_col(date_signed,    'Date Signed',    'DateSigned',    'Signed Date',      'SignedDate')
set_col(date_published, 'Date Published', 'DatePublished', 'Published Date',   'PublishedDate')
set_col(designer1,      'Designer1', 'Designer 1')
set_col(designer2, 'Designer2', 'Designer 2')
set_col(designer3, 'Designer3', 'Designer 3')
set_col(designer4, 'Designer4', 'Designer 4')
set_col(rules,     'Rules',     'Rules URL',    'RulesURL')
set_col(play,      'Play',      'Play URL',     'PlayURL')
set_col(print_url, 'Print',     'Print URL',    'PrintURL')
set_col(sellsheet, 'Sellsheet', 'Sellsheet URL','SellsheetURL')
set_col(view,      'BGG',       'View URL',     'BGG / View URL', 'ViewURL', 'View')
set_col(video,     'Video',     'Video URL',    'VideoURL')

# If 'Name' column wasn't found, just put the name in column 0
if find_col('Name') < 0:
    new_row[0] = name

try:
    ws.append_row(new_row, value_input_option='USER_ENTERED')
    new_sheet_row = len(all_values) + 1   # approximate row number
    print(json.dumps({"ok": True, "row": new_sheet_row}))
except Exception as e:
    print(json.dumps({"error": f"Could not append row: {str(e)}"}))
    sys.exit(1)
