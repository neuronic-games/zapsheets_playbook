#!/usr/bin/env python3
"""
gupdatenotetopic.py — update the display name (cell B1) of a notes tab
and refresh the local cache file.

Args: <sheet_id> <key> <new_topic>
  key       — internal key from noteboard-index.json (e.g. "notes" or "Dim Sum A-Go-Go")
  new_topic — new display name to write into cell B1 and the cache

Returns JSON: {"ok": true} or {"error": "…"}
"""

import sys, os, json, re

_here     = os.path.dirname(os.path.abspath(__file__))
cred_path = os.path.join(_here, '..', 'credentials.json')

def fail(msg):
    print(json.dumps({'ok': False, 'error': msg}))
    sys.exit(1)

sheet_id  = (sys.argv[1] if len(sys.argv) > 1 else '').strip()
key       = (sys.argv[2] if len(sys.argv) > 2 else '').strip()
new_topic = (sys.argv[3] if len(sys.argv) > 3 else '').strip()

if not sheet_id or not key or not new_topic:
    fail('sheet_id, key, and topic are required')

if not os.path.exists(cred_path):
    fail('credentials.json not found')

try:
    import gspread
    sa = gspread.service_account(filename=cred_path)
except ImportError:
    fail('gspread not installed')
except Exception as e:
    fail(f'Auth failed: {e}')

try:
    wb = sa.open_by_key(sheet_id)
except Exception as e:
    fail(f'Could not open spreadsheet: {e}')

# Determine tab name from key
tab_name = 'notes' if key.lower() == 'notes' else f'[{key}] notes'

ws_map = {ws.title: ws for ws in wb.worksheets()}
ws = ws_map.get(tab_name)
if ws is None:
    fail(f'Tab "{tab_name}" not found in spreadsheet')

# Update cell B1 with the new display name
try:
    ws.update(range_name='B1', values=[[new_topic]])
except Exception as e:
    fail(f'Could not update cell B1: {e}')

# Update the local cache file
safe_name  = re.sub(r'[/\\]', '-', key)
cache_path = os.path.join(_here, '..', 'sheets', sheet_id, f'notes-{safe_name}-en.json')

if os.path.exists(cache_path):
    try:
        with open(cache_path, 'r', encoding='utf-8') as f:
            data = json.load(f)
        if isinstance(data, dict):
            data['topic'] = new_topic
        else:
            data = {'topic': new_topic, 'notes': data}
        with open(cache_path, 'w', encoding='utf-8') as f:
            json.dump(data, f, ensure_ascii=False)
    except Exception as e:
        fail(f'Could not update cache file: {e}')

print(json.dumps({'ok': True}))
