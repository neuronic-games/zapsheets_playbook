# ginitsheet.py — initialise a Google Spreadsheet for PitchBoard
#
# Creates required worksheets (Pitches, Games, People, Settings) with the
# correct column headers if they don't already exist.  Existing tabs are
# left completely untouched.  Never deletes any worksheet.
#
# Arg: {sheet_id}
#
# NOTE: the spreadsheet must already be shared with the service-account email
# before calling this script.

import gspread
import sys, os, json

credFileName = os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', 'credentials.json')

if not os.path.exists(credFileName):
    print(json.dumps({"error": "credentials.json not found"}))
    sys.exit(1)

try:
    sa = gspread.service_account(filename=credFileName)
except Exception as e:
    print(json.dumps({"error": f"Could not authenticate: {str(e)}"}))
    sys.exit(1)

sheet_id = sys.argv[1].strip() if len(sys.argv) > 1 else ''
if not sheet_id:
    print(json.dumps({"error": "Sheet ID is required"}))
    sys.exit(1)

try:
    wb = sa.open_by_key(sheet_id)
except Exception as e:
    print(json.dumps({"error": f"Could not open spreadsheet: {str(e)}"}))
    sys.exit(1)

# ── Tab definitions ────────────────────────────────────────────────────────────
#
# Each entry is a list of rows to write starting at A1.
# For Pitches / Games / People: first row = headers, rest = data.
# For Settings: no header row — just the label/value pairs.

# Order matters: validation rules reference other tabs, so create dependencies first.
# People has no deps → Settings has no deps → Games references People → Pitches references Games + People.
TABS = {
    'People': [
        ['Name', 'Email', 'Company', 'Role', 'Notes'],
    ],
    'Settings': [
        ['My Name',     ''],
        ['My Email',    ''],
        ['My Phone',    ''],
        ['PublishedOn', ''],
        ['Version',     ''],
    ],
    'Games': [
        ['Name', 'Tagline', 'Status',
         'Date Started', 'Date Signed', 'Date Published',
         'Designer1', 'Designer2', 'Designer3', 'Designer4',
         'Description', 'Rules', 'Play', 'Print', 'Sellsheet', 'BGG', 'Video'],
    ],
    'Pitches': [
        ['Date', 'Game', 'Publisher', 'Contact', 'Event', 'Status', 'Notes'],
    ],
}

results = {}

# Build map of currently existing worksheets (by title)
existing = {w.title: w for w in wb.worksheets()}

# ── Create missing required worksheets only ────────────────────────────────────
# If a tab already exists we leave it completely untouched — never clear or
# overwrite it.  We never delete any worksheet so that game-specific tabs
# (e.g. [Monopoly]) created by gcreategametab.py are preserved.
for tab_name, rows in TABS.items():
    try:
        if tab_name in existing:
            # Tab exists — data is intact, nothing to do.
            ws = existing[tab_name]
            results[tab_name] = 'ok'
        else:
            num_cols = max(len(r) for r in rows) if rows else 2
            ws = wb.add_worksheet(title=tab_name, rows=200, cols=num_cols)
            if rows:
                end_cell = gspread.utils.rowcol_to_a1(len(rows), max(len(r) for r in rows))
                ws.update(rows, f'A1:{end_cell}', value_input_option='RAW')
            # Freeze and format the header row on tabs that have one (not Settings)
            if tab_name != 'Settings':
                ws.freeze(rows=1)
                header_end = gspread.utils.rowcol_to_a1(1, num_cols)
                ws.format(f'A1:{header_end}', {
                    'backgroundColor': {'red': 0.627, 'green': 0.424, 'blue': 0.024},  # #a06d08
                    'textFormat': {
                        'bold': True,
                        'foregroundColor': {'red': 1.0, 'green': 1.0, 'blue': 1.0},
                    },
                    'horizontalAlignment': 'LEFT',
                })
            results[tab_name] = 'created'

        # Pitches tab: apply dropdown validation regardless of whether the tab
        # was just created or already existed — setDataValidation is idempotent.
        # Pitches columns: Date(A/0), Game(B/1), Publisher(C/2), Contact(D/3), Event(E/4), Status(F/5)
        if tab_name == 'Pitches':
            PITCH_STATUS_VALUES = [
                'Pitched', 'Interested', 'Passed', 'Gone Cold', 'Signed', 'Published', 'Returned',
            ]
            # Each tuple: (col_index, condition_type, condition_values_list)
            validation_cols = [
                (1, 'ONE_OF_RANGE', [{'userEnteredValue': '=Games!$A$2:$A$10000'}]),
                (2, 'ONE_OF_RANGE', [{'userEnteredValue': '=People!$C$2:$C$10000'}]),
                (3, 'ONE_OF_RANGE', [{'userEnteredValue': '=People!$A$2:$A$10000'}]),
                (5, 'ONE_OF_LIST',  [{'userEnteredValue': v} for v in PITCH_STATUS_VALUES]),
            ]
            requests = [
                # Clear any stale validation on the Date column (col 0).
                # Omitting the 'rule' key tells the Sheets API to delete the rule.
                {
                    'setDataValidation': {
                        'range': {
                            'sheetId': ws.id,
                            'startRowIndex': 1,
                            'endRowIndex': 10000,
                            'startColumnIndex': 0,
                            'endColumnIndex': 1,
                        }
                    }
                },
            ]
            for col_idx, cond_type, cond_values in validation_cols:
                requests.append({
                    'setDataValidation': {
                        'range': {
                            'sheetId': ws.id,
                            'startRowIndex': 1,
                            'endRowIndex': 10000,
                            'startColumnIndex': col_idx,
                            'endColumnIndex': col_idx + 1,
                        },
                        'rule': {
                            'condition': {
                                'type': cond_type,
                                'values': cond_values,
                            },
                            'showCustomUi': True,
                            'strict': False,
                        },
                    }
                })

            # Conditional formatting for Status column — colour each value to match
            # the badge colours used in the PitchBoard UI.
            # Only added on newly created tabs; existing tabs keep their existing rules.
            if results.get(tab_name) == 'created':
                PITCH_STATUS_COLORS = [
                    # (value,       bg_rgb_0_1,                     fg_rgb_0_1)
                    ('Pitched',     (0.886, 0.910, 0.941), (0.278, 0.333, 0.412)),  # #e2e8f0 / #475569
                    ('Interested',  (0.863, 0.988, 0.906), (0.086, 0.396, 0.204)),  # #dcfce7 / #166534
                    ('Passed',      (0.996, 0.886, 0.886), (0.600, 0.106, 0.106)),  # #fee2e2 / #991b1b
                    ('Gone Cold',   (0.859, 0.894, 0.996), (0.118, 0.251, 0.686)),  # #dbeafe / #1e40af
                    ('Signed',      (0.929, 0.914, 0.996), (0.357, 0.129, 0.714)),  # #ede9fe / #5b21b6
                    ('Published',   (0.878, 0.949, 0.996), (0.027, 0.349, 0.522)),  # #e0f2fe / #075985
                    ('Returned',    (1.000, 0.969, 0.929), (0.761, 0.255, 0.047)),  # #fff7ed / #c2410c
                ]
                for i, (status, bg, fg) in enumerate(PITCH_STATUS_COLORS):
                    requests.append({
                        'addConditionalFormatRule': {
                            'rule': {
                                'ranges': [{
                                    'sheetId': ws.id,
                                    'startRowIndex': 1,
                                    'endRowIndex': 10000,
                                    'startColumnIndex': 5,
                                    'endColumnIndex': 6,
                                }],
                                'booleanRule': {
                                    'condition': {
                                        'type': 'TEXT_EQ',
                                        'values': [{'userEnteredValue': status}],
                                    },
                                    'format': {
                                        'backgroundColor': {'red': bg[0], 'green': bg[1], 'blue': bg[2]},
                                        'textFormat': {
                                            'foregroundColor': {'red': fg[0], 'green': fg[1], 'blue': fg[2]},
                                            'bold': True,
                                        },
                                    },
                                },
                            },
                            'index': i,
                        }
                    })

            wb.batch_update({'requests': requests})

        # Games tab: Designer1–4 validate against People.Name — idempotent.
        if tab_name == 'Games':
            games_headers = TABS['Games'][0]
            designer_indices = [
                i for i, h in enumerate(games_headers)
                if h.lower().startswith('designer')
            ]
            requests = []
            for col_idx in designer_indices:
                requests.append({
                    'setDataValidation': {
                        'range': {
                            'sheetId': ws.id,
                            'startRowIndex': 1,
                            'endRowIndex': 10000,
                            'startColumnIndex': col_idx,
                            'endColumnIndex': col_idx + 1,
                        },
                        'rule': {
                            'condition': {
                                'type': 'ONE_OF_RANGE',
                                'values': [{'userEnteredValue': '=People!$A$2:$A$10000'}],
                            },
                            'showCustomUi': True,
                            'strict': False,
                        },
                    }
                })
            if requests:
                wb.batch_update({'requests': requests})

    except Exception as e:
        results[tab_name] = f'error: {str(e)}'

tab_results = {k: v for k, v in results.items()}
all_ok = all(v in ('ok', 'created') for v in tab_results.values())

print(json.dumps({
    "ok":    all_ok,
    "tabs":  tab_results,
    "title": wb.title,
}))
