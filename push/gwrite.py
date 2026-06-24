# gwrite.py — writes PublishedOn and Version back to the Settings sheet
#
# Argument: "{sheetId}version{N}"
# Example:  "1MIuMg...version12"
#
# PublishedOn is stamped in the sheet's own configured timezone
# (File › Settings › Timezone in Google Sheets).

import gspread
import sys
import os
import re
from datetime import datetime

# ── Credentials ────────────────────────────────────────────────────────────────
credFileName = os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', 'credentials.json')

if not os.path.exists(credFileName):
    print('ERROR: credentials.json not found')
    sys.exit(1)

# ── Parse argument ─────────────────────────────────────────────────────────────
# Format: "{sheetId}version{N}"
arg = sys.argv[1] if len(sys.argv) > 1 else ''

m = re.match(r'^(.+?)version(\d+)$', arg)
if not m:
    print('ERROR: invalid argument format')
    sys.exit(1)

sheet_id    = m.group(1)
version_num = m.group(2)

# ── Open sheet ─────────────────────────────────────────────────────────────────
try:
    sa = gspread.service_account(filename=credFileName)
    wb = sa.open_by_key(sheet_id)
except Exception as e:
    print('ERROR: could not open sheet: ' + str(e))
    sys.exit(1)

# ── Timestamp in the sheet's own timezone ──────────────────────────────────────
# Fetch the spreadsheet's configured timezone (e.g. "America/New_York")
# and format the current time in that zone.
try:
    meta    = wb.fetch_sheet_metadata()
    tz_name = meta.get('properties', {}).get('timeZone', 'UTC')
except Exception:
    tz_name = 'UTC'

try:
    # zoneinfo is stdlib in Python 3.9+; fall back to UTC if unavailable
    from zoneinfo import ZoneInfo
    now = datetime.now(ZoneInfo(tz_name))
except Exception:
    try:
        import pytz
        now = datetime.now(pytz.timezone(tz_name))
    except Exception:
        now = datetime.utcnow()

# Format: "Jun 24, 2026 6:30 PM"  (no leading zero on day or hour)
try:
    published_on = now.strftime('%b %-d, %Y %-I:%M %p')
except ValueError:
    # Windows doesn't support %-d / %-I — fall back to zero-padded
    published_on = now.strftime('%b %d, %Y %I:%M %p').lstrip('0').replace(' 0', ' ')

# ── Find Settings worksheet (case-insensitive) ─────────────────────────────────
ws = None
for w in wb.worksheets():
    if w.title.lower() == 'settings':
        ws = w
        break

if ws is None:
    print('SKIP: Settings tab not found')
    sys.exit(0)

# ── Read existing rows to find PublishedOn / Version ──────────────────────────
try:
    values = ws.get_all_values()
except Exception as e:
    print('ERROR: could not read Settings: ' + str(e))
    sys.exit(1)

published_on_row = None
version_row      = None

for i, row in enumerate(values):
    label = row[0].strip() if row else ''
    if label == 'PublishedOn':
        published_on_row = i + 1   # 1-indexed for gspread
    elif label == 'Version':
        version_row = i + 1

# ── Write ──────────────────────────────────────────────────────────────────────
batch = []
if published_on_row:
    batch.append({'range': f'A{published_on_row}:B{published_on_row}',
                  'values': [['PublishedOn', published_on]]})
if version_row:
    batch.append({'range': f'A{version_row}:B{version_row}',
                  'values': [['Version', version_num]]})

try:
    if batch:
        ws.batch_update(batch)

    if not published_on_row:
        ws.append_row(['PublishedOn', published_on], value_input_option='USER_ENTERED')
    if not version_row:
        ws.append_row(['Version', version_num], value_input_option='USER_ENTERED')

except Exception as e:
    print('ERROR: could not write to Settings: ' + str(e))
    sys.exit(1)

print('Settings updated — v' + version_num + ' · ' + published_on + ' (' + tz_name + ')')
