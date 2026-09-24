# gdelete_game.py — delete a game row, all its pitch entries, and the [Game] tab
# Arg: {sheet_id}|{base64_encoded_json}
# JSON keys: game

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

arg      = sys.argv[1]
pipe_idx = arg.index('|')
sheet_id = arg[:pipe_idx]
data     = json.loads(base64.b64decode(arg[pipe_idx + 1:]).decode('utf-8'))

game = data.get('game', '').strip()

try:
    mGoogleSheet = mServiceAccount.open_by_key(sheet_id)
except Exception as e:
    print(json.dumps({"error": f"Could not open spreadsheet: {str(e)}"}))
    sys.exit(1)

all_worksheets = mGoogleSheet.worksheets()

def get_ws(name):
    return next((w for w in all_worksheets if w.title.lower() == name.lower()), None)

def cell(row, headers_col, field):
    idx = headers_col.get(field, -1)
    return row[idx].strip() if 0 <= idx < len(row) else ''

results = {}

# ── 1. Delete the game row from the Games tab ─────────────────────────────────
ws_games = get_ws('Games')
if ws_games is None:
    print(json.dumps({"error": "Games worksheet not found"}))
    sys.exit(1)

try:
    all_values = ws_games.get_all_values()
except Exception as e:
    print(json.dumps({"error": f"Could not read Games sheet: {str(e)}"}))
    sys.exit(1)

if not all_values:
    print(json.dumps({"error": "Games sheet is empty"}))
    sys.exit(1)

headers = all_values[0]
col = {h.strip(): i for i, h in enumerate(headers)}

game_row = None
for i, row in enumerate(all_values[1:], start=2):
    if cell(row, col, 'Name') == game:
        game_row = i
        break

if game_row is None:
    print(json.dumps({"error": "Game row not found in Games sheet"}))
    sys.exit(1)

try:
    ws_games.delete_rows(game_row)
    results['games_row_deleted'] = game_row
except Exception as e:
    print(json.dumps({"error": f"Could not delete game row: {str(e)}"}))
    sys.exit(1)

# ── 2. Delete all pitch entries for the game from the Pitches tab ─────────────
ws_pitches = get_ws('Pitches')
if ws_pitches is not None:
    try:
        pitch_values = ws_pitches.get_all_values()
        if pitch_values:
            pitch_headers = pitch_values[0]
            pcol = {h.strip(): i for i, h in enumerate(pitch_headers)}
            rows_to_delete = [
                i for i, row in enumerate(pitch_values[1:], start=2)
                if cell(row, pcol, 'Game') == game
            ]
            # Delete from bottom up so row indices stay valid
            for r in sorted(rows_to_delete, reverse=True):
                ws_pitches.delete_rows(r)
            results['pitch_rows_deleted'] = len(rows_to_delete)
    except Exception as e:
        results['pitch_warning'] = f"Could not clean Pitches tab: {str(e)}"

# ── 3. Delete the [Game Name] worksheet tab if it exists ──────────────────────
bracketed = '[' + game + ']'
ws_game_tab = next(
    (w for w in all_worksheets if w.title == bracketed or w.title == game),
    None
)
if ws_game_tab is not None:
    try:
        mGoogleSheet.del_worksheet(ws_game_tab)
        results['tab_deleted'] = ws_game_tab.title
    except Exception as e:
        results['tab_warning'] = f"Could not delete game tab: {str(e)}"

results['ok'] = True
results['game'] = game
print(json.dumps(results))
