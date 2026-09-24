# gupdategame.py — update a game row in the games sheet
# Arg: {sheet_id}|{base64_encoded_json}
# JSON keys: orig_name, name, designer1-4, rules, play, print, sellsheet, view, video

import gspread
import sys, os, json, base64

credFileName = os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', 'credentials.json')

def safe_str(v):
    """Prefix non-empty strings with ' to prevent Google Sheets formula interpretation.
    Exception: =IMAGE() formulas are passed through so they render as images in the sheet."""
    s = (str(v) if v is not None else '').strip()
    if not s:
        return ''
    if s.upper().startswith('=IMAGE('):
        return s
    return "'" + s

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

orig_name  = data.get('orig_name',  '').strip()
new_name   = data.get('name',       '').strip() or orig_name
tagline        = data.get('tagline',        '').strip()
description    = data.get('description',    '').strip()
status         = data.get('status',         '').strip()
date_started   = data.get('date_started',   '').strip()
date_signed    = data.get('date_signed',    '').strip()
date_published = data.get('date_published', '').strip()
designer1      = data.get('designer1',      '').strip()
designer2  = data.get('designer2',  '').strip()
designer3  = data.get('designer3',  '').strip()
designer4  = data.get('designer4',  '').strip()
rules      = data.get('rules',      '').strip()
play       = data.get('play',       '').strip()
print_url  = data.get('print',      '').strip()
sellsheet  = data.get('sellsheet',  '').strip()
view       = data.get('view',       '').strip()
video      = data.get('video',      '').strip()
image      = data.get('image',      '').strip()

# Open spreadsheet
try:
    mGoogleSheet = mServiceAccount.open_by_key(sheet_id)
except Exception as e:
    print(json.dumps({"error": f"Could not open spreadsheet: {str(e)}"}))
    sys.exit(1)

# Find games worksheet (case-insensitive; try 'games' then 'Games')
all_worksheets = mGoogleSheet.worksheets()
ws = next((w for w in all_worksheets if w.title.lower() == 'games'), None)
if ws is None:
    print(json.dumps({"error": "Games worksheet not found"}))
    sys.exit(1)

# Read all values
try:
    all_values = ws.get_all_values()
except Exception as e:
    print(json.dumps({"error": f"Could not read sheet: {str(e)}"}))
    sys.exit(1)

if not all_values:
    print(json.dumps({"error": "Games sheet is empty"}))
    sys.exit(1)

headers = all_values[0]
col = {h.strip(): i for i, h in enumerate(headers)}

def find_col(*variants):
    """Return the 0-based column index of the first matching variant, or -1."""
    for v in variants:
        if v in col:
            return col[v]
    return -1

# Locate the row whose Name matches orig_name
name_col = find_col('Name')
if name_col < 0:
    print(json.dumps({"error": "No 'Name' column found in games sheet"}))
    sys.exit(1)

target_sheet_row = None
for i, row in enumerate(all_values[1:], start=2):
    cell_name = row[name_col].strip() if name_col < len(row) else ''
    if cell_name == orig_name:
        target_sheet_row = i
        break

if target_sheet_row is None:
    print(json.dumps({"error": f"Game '{orig_name}' not found in games sheet"}))
    sys.exit(1)

# Map of (field_variants, value) pairs to update
field_map = [
    (('Name',),                                                            new_name),
    (('Tagline', 'Tag Line', 'SubTitle', 'Subtitle'),                      tagline),
    (('Description',),                                                     description),
    (('Status',),                                                          status),
    (('Date Started', 'DateStarted', 'Start Date', 'StartDate'),          date_started),
    (('Date Signed',  'DateSigned',  'Signed Date', 'SignedDate'),         date_signed),
    (('Date Published','DatePublished','Published Date','PublishedDate'),   date_published),
    (('Designer1', 'Designer 1'),                                          designer1),
    (('Designer2', 'Designer 2'),                                          designer2),
    (('Designer3', 'Designer 3'),                                          designer3),
    (('Designer4', 'Designer 4'),                                          designer4),
    (('Rules',     'Rules URL',   'RulesURL'),                             rules),
    (('Play',      'Play URL',    'PlayURL'),                              play),
    (('Print',     'Print URL',   'PrintURL'),                             print_url),
    (('Sellsheet', 'Sellsheet URL', 'SellsheetURL'),                       sellsheet),
    (('BGG',       'View URL',    'BGG / View URL', 'ViewURL', 'View'),    view),
    (('Video',     'Video URL',   'VideoURL'),                             video),
    (('Image URL', 'ImageURL',    'Image'),                                image),
]

updates = []
for variants, value in field_map:
    idx = find_col(*variants)
    if idx >= 0:
        updates.append({
            'range':  gspread.utils.rowcol_to_a1(target_sheet_row, idx + 1),
            'values': [[safe_str(value)]]
        })

if not updates:
    print(json.dumps({"error": "No matching columns found in games sheet headers"}))
    sys.exit(1)

try:
    ws.batch_update(updates, value_input_option='USER_ENTERED')
except Exception as e:
    print(json.dumps({"error": f"Could not update cells: {str(e)}"}))
    sys.exit(1)

result = {"ok": True, "row": target_sheet_row, "updated": len(updates)}

# ── If name changed, propagate rename everywhere ───────────────────────────────
if new_name != orig_name:

    # 1. Update all Pitches rows where Game == orig_name
    ws_pitches = next((w for w in all_worksheets if w.title.lower() == 'pitches'), None)
    if ws_pitches is not None:
        try:
            pitch_values = ws_pitches.get_all_values()
            if pitch_values:
                ph = pitch_values[0]
                pcol = {h.strip(): i for i, h in enumerate(ph)}
                game_col = pcol.get('Game', -1)
                pitch_updates = []
                for i, row in enumerate(pitch_values[1:], start=2):
                    if game_col >= 0 and game_col < len(row) and row[game_col].strip() == orig_name:
                        pitch_updates.append({
                            'range':  gspread.utils.rowcol_to_a1(i, game_col + 1),
                            'values': [["'" + new_name]]
                        })
                if pitch_updates:
                    ws_pitches.batch_update(pitch_updates, value_input_option='USER_ENTERED')
                    result['pitch_rows_renamed'] = len(pitch_updates)
        except Exception as e:
            result['pitch_warning'] = f"Could not rename pitch rows: {str(e)}"

    # 2. Rename any worksheet tabs starting with [orig_name]
    old_prefix       = '[' + orig_name + ']'
    new_prefix       = '[' + new_name  + ']'
    old_prefix_lower = old_prefix.lower()
    renamed_tabs     = []
    tab_warnings     = []
    for ws_tab in all_worksheets:
        t = ws_tab.title
        tl = t.lower()
        if tl == orig_name.lower() or tl == old_prefix_lower or tl.startswith(old_prefix_lower + ' '):
            # Replace the prefix / plain name, preserve any suffix
            if tl == orig_name.lower():
                new_title = new_name
            elif tl == old_prefix_lower:
                new_title = new_prefix
            else:
                suffix    = t[len(old_prefix):]
                new_title = new_prefix + suffix
            try:
                ws_tab.update_title(new_title)
                renamed_tabs.append({'from': t, 'to': new_title})
            except Exception as e:
                tab_warnings.append(f"{t}: {str(e)}")
    if renamed_tabs:
        result['tabs_renamed'] = renamed_tabs
    if tab_warnings:
        result['tab_warnings'] = tab_warnings

print(json.dumps(result))
