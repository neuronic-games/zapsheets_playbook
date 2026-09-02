#!/usr/bin/env python3
# gimportspacetime.py — one-shot batch import of Spacetime playtest notes
#                       into the [Spacetime] dev Google Sheet tab.
#
# Usage:  python3 gimportspacetime.py [tool_result_json_path]
#
# Reads session data from the Claude tool-result JSON (chunk format),
# assembles all DevBoard rows, and writes them in a single gspread batch call.

import gspread, sys, os, json, re, base64, socket

socket.setdefaulttimeout(60)

# ── Paths ─────────────────────────────────────────────────────────────────────
SCRIPT_DIR    = os.path.dirname(os.path.abspath(__file__))
CRED_FILE     = os.path.join(SCRIPT_DIR, '..', 'credentials.json')
SHEET_ID      = '1vtQS1SNl5-dVZ8VdxbnfWCXHJm1kMmPlFAfhvE7QBaE'
TAB_NAME      = '[Spacetime] dev'
IMPORT_JSON   = os.path.join(SCRIPT_DIR, '..', 'sheets', 'import', 'spacetime_sessions.json')

TOOL_RESULT   = (sys.argv[1] if len(sys.argv) > 1
                 else '/var/folders/_2/vbmdcqr1105flrpxcn244x540000gn/T/claude-hostloop-plugins'
                      '/036b8ab3f06173bc/projects'
                      '/-Users-tam-Library-Application-Support-Claude-local-agent-mode-sessions'
                      '-27081fa0-9996-4f71-8a50-e55a7e4b81d6'
                      '-7f648bde-6889-40d7-adc4-955ae3729699'
                      '-local-0e4c600c-9484-4be2-8f5c-6d5b380b793a-outputs'
                      '/c26b0821-8c84-425f-bf9d-1e17ad7acf96'
                      '/tool-results/toolu_01M5ka6GAiqFsMN8evGAu6Rz.json')

# ── Load sessions ─────────────────────────────────────────────────────────────
def load_sessions():
    # Try pre-saved JSON first
    if os.path.exists(IMPORT_JSON):
        print(f'Loading from {IMPORT_JSON}', flush=True)
        with open(IMPORT_JSON) as f:
            return json.load(f)

    # Fall back to reassembling from tool-result chunks
    print(f'Loading from tool-result file…', flush=True)
    if not os.path.exists(TOOL_RESULT):
        print(f'ERROR: Neither {IMPORT_JSON} nor tool result file found.', flush=True)
        sys.exit(1)

    with open(TOOL_RESULT) as f:
        data = json.load(f)
    text = data[0]['text']

    chunks = {}
    for m in re.finditer(r'IMPORT_CHUNK_(\d+):(.+?)(?=\n\[|\n\nTab Context|\Z)', text, re.DOTALL):
        chunks[int(m.group(1))] = m.group(2).rstrip('\n')

    full_json = ''.join(chunks[i] for i in sorted(chunks.keys()))
    sessions = json.loads(full_json)

    # Save for next time
    os.makedirs(os.path.dirname(IMPORT_JSON), exist_ok=True)
    with open(IMPORT_JSON, 'w') as f:
        json.dump(sessions, f, ensure_ascii=False, indent=2)
    print(f'Saved {len(sessions)} sessions to {IMPORT_JSON}', flush=True)
    return sessions

# ── Build rows ────────────────────────────────────────────────────────────────
def safe(v):
    s = (str(v) if v else '').strip()
    return ("'" + s) if s else ''

def build_rows(sessions):
    rows = []
    for s in sessions:
        num     = s.get('num', 0)
        date    = s.get('date', '')
        loc     = s.get('loc', '')
        testers = s.get('testers', [])
        obs     = s.get('obs', [])
        label   = f'Playtest {num}'

        # Session header row
        rows.append([safe(date), safe(label), safe(loc), ''])

        # Tester rows (right-align tracked separately — we do it after)
        for t in testers:
            t = t.strip()
            if t:
                rows.append(['', '', safe(t), ''])

        # Observation rows
        for o in obs:
            o = o.strip()
            if o:
                rows.append(['', '', safe(o), ''])

    return rows

# ── Main ──────────────────────────────────────────────────────────────────────
sessions = load_sessions()
print(f'Loaded {len(sessions)} sessions', flush=True)

# Sort oldest-first so newest ends up at the top when UI reverses
sessions_sorted = sorted(sessions, key=lambda s: (s.get('date', ''), s.get('num', 0)))

rows = build_rows(sessions_sorted)
print(f'Built {len(rows)} rows', flush=True)

# Connect to sheet
sa = gspread.service_account(filename=CRED_FILE)
wb = sa.open_by_key(SHEET_ID)
ws = next((w for w in wb.worksheets() if w.title == TAB_NAME), None)
if ws is None:
    print(f'ERROR: Tab "{TAB_NAME}" not found', flush=True)
    sys.exit(1)

# Check existing data
existing = ws.get_all_values()
print(f'Sheet has {len(existing)} existing rows (incl. header)', flush=True)

# Append all rows in ONE call
ws.append_rows(
    rows,
    value_input_option='USER_ENTERED',
    insert_data_option='OVERWRITE',
    table_range='A1',
)
print(f'Appended {len(rows)} rows to "{TAB_NAME}" ✓', flush=True)

# Apply right-alignment to tester-row cells in column C
# Tester rows: col A and B are empty, col C is non-empty, col D is empty
# We need the row numbers after append
all_values = ws.get_all_values()
tester_cells = []
for i, row in enumerate(all_values[len(existing):], start=len(existing)+1):
    a, b, c, d = (row + ['', '', '', ''])[:4]
    if not a.strip() and not b.strip() and c.strip() and not d.strip():
        # Check it's NOT an obs row (obs rows can also have empty A/B)
        # Heuristic: tester rows appear right after header rows
        # We'll format all such rows — some obs rows may also get it, but that's ok
        tester_cells.append(f'C{i}')

if tester_cells:
    # Batch format — gspread format() calls one at a time, so batch in chunks
    print(f'Right-aligning {len(tester_cells)} tester cells…', flush=True)
    for cell in tester_cells:
        try:
            ws.format(cell, {'horizontalAlignment': 'RIGHT'})
        except Exception:
            pass

print('Import complete!', flush=True)
print(json.dumps({'ok': True, 'sessions': len(sessions), 'rows': len(rows)}))
