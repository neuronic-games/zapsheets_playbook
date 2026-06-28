#!/usr/bin/env python3
"""
fetchbgg.py — fetch BoardGameGeek data for each game that has a BggGameId.

For each game in games.json:
  1. Reads the per-game tab JSON (e.g. "scurry up!.json") for a BggGameId row.
  2. Calls BGG XML API v2: /thing?id={bggId}&stats=1
  3. Saves parsed data to sheets/{sheetId}/bgg-{bggId}.json.

Cached copies younger than 24 h are reused without a new request.

Usage (run from push/ directory):
  python3 fetchbgg.py <sheetId>
"""

import json
import os
import sys
import time
import xml.etree.ElementTree as ET
from urllib.request import urlopen, Request
from urllib.error import URLError, HTTPError

BGG_THING_URL  = 'https://boardgamegeek.com/xmlapi2/thing?id={id}&stats=1&type=boardgame'
BGG_BEARER     = 'a4e2e2f3-a6fa-4eea-83b3-503508dfe06e'
CACHE_SECONDS  = 86400   # 24 hours
REQUEST_DELAY  = 2       # seconds between BGG requests (rate-limit courtesy)
RETRY_DELAY    = 8       # seconds to wait on 202 / 429


# ── HTTP fetch ────────────────────────────────────────────────────────────────

def bgg_fetch(bgg_id, retries=4):
    url = BGG_THING_URL.format(id=bgg_id)
    for attempt in range(retries):
        try:
            req = Request(url, headers={
                'User-Agent':    'Zapsheets/1.0',
                'Authorization': 'Bearer ' + BGG_BEARER,
                'Accept':        'application/xml',
            })
            resp = urlopen(req, timeout=25)
            return resp.read()
        except HTTPError as e:
            if e.code in (202, 429):
                # BGG not ready yet or rate-limited — wait and retry
                time.sleep(RETRY_DELAY * (attempt + 1))
                continue
            print(f'  HTTP {e.code}')
            return None
        except URLError as e:
            print(f'  URLError: {e.reason}')
            return None
    return None


# ── XML parser ────────────────────────────────────────────────────────────────

def parse_bgg_xml(xml_bytes):
    try:
        root = ET.fromstring(xml_bytes)
    except ET.ParseError as e:
        print(f'  XML parse error: {e}')
        return None

    item = root.find('item')
    if item is None:
        return None

    def attr_val(tag):
        el = item.find(tag)
        if el is None:
            return ''
        return el.get('value', (el.text or '')).strip()

    def text_val(tag):
        return (item.findtext(tag) or '').strip()

    def all_links(link_type):
        return [
            {'id': l.get('id', ''), 'value': l.get('value', '')}
            for l in item.findall('link')
            if l.get('type') == link_type
        ]

    # Names
    names = [
        {'type': n.get('type', ''), 'sortindex': n.get('sortindex', ''), 'value': n.get('value', '')}
        for n in item.findall('name')
    ]
    primary_name = next((n['value'] for n in names if n['type'] == 'primary'), '')

    # Ratings / stats
    ratings_el = item.find('statistics/ratings')
    def rval(tag):
        if ratings_el is None:
            return ''
        el = ratings_el.find(tag)
        if el is None:
            return ''
        return el.get('value', (el.text or '')).strip()

    bgg_rank = ''
    if ratings_el is not None:
        for rank_el in ratings_el.findall('ranks/rank'):
            if rank_el.get('name') == 'boardgame':
                bgg_rank = rank_el.get('value', '')
                break

    return {
        'id':            item.get('id', ''),
        'type':          item.get('type', ''),
        'name':          primary_name,
        'names':         names,
        'yearpublished': attr_val('yearpublished'),
        'minplayers':    attr_val('minplayers'),
        'maxplayers':    attr_val('maxplayers'),
        'minplaytime':   attr_val('minplaytime'),
        'maxplaytime':   attr_val('maxplaytime'),
        'minage':        attr_val('minage'),
        'description':   text_val('description'),
        'image':         text_val('image'),
        'thumbnail':     text_val('thumbnail'),
        'mechanics':     all_links('boardgamemechanic'),
        'categories':    all_links('boardgamecategory'),
        'designers':     all_links('boardgamedesigner'),
        'artists':       all_links('boardgameartist'),
        'publishers':    all_links('boardgamepublisher'),
        'families':      all_links('boardgamefamily'),
        'implementations': all_links('boardgameimplementation'),
        'stats': {
            'usersrated':    rval('usersrated'),
            'average':       rval('average'),
            'bayesaverage':  rval('bayesaverage'),
            'stddev':        rval('stddev'),
            'numowned':      rval('owned'),
            'numwanting':    rval('wanting'),
            'numwishing':    rval('wishing'),
            'numweights':    rval('numweights'),
            'averageweight': rval('averageweight'),
            'rank':          bgg_rank,
        },
        'fetched_at': int(time.time()),
    }


# ── Main ──────────────────────────────────────────────────────────────────────

def main():
    if len(sys.argv) < 2:
        print('ERROR: usage: fetchbgg.py <sheetId>')
        sys.exit(1)

    sheet_id  = sys.argv[1]
    sheet_dir = os.path.join('..', 'sheets', sheet_id)

    if not os.path.isdir(sheet_dir):
        print(f'ERROR: sheet dir not found: {sheet_dir}')
        sys.exit(1)

    games_file = os.path.join(sheet_dir, 'games.json')
    if not os.path.exists(games_file):
        print('SKIP: games.json not found')
        sys.exit(0)

    with open(games_file, encoding='utf-8') as f:
        games = json.load(f)

    if not isinstance(games, list):
        print('SKIP: games.json is not a list')
        sys.exit(0)

    seen_ids = set()   # avoid fetching the same BGG id twice
    fetched  = 0
    cached   = 0
    skipped  = 0

    for game in games:
        name = (game.get('Name') or '').strip()
        if not name:
            continue

        # Read per-game tab JSON
        tab_file = os.path.join(sheet_dir, name.lower() + '.json')
        if not os.path.exists(tab_file):
            continue

        with open(tab_file, encoding='utf-8') as f:
            tab = json.load(f)

        # Find BggGameId row
        bgg_id = ''
        for row in tab:
            if isinstance(row, dict) and (row.get('Name') or '').strip().lower() == 'bgggameid':
                bgg_id = (row.get('Value') or '').strip()
                if bgg_id:
                    break

        if not bgg_id:
            print(f'SKIP: {name} — no BggGameId')
            skipped += 1
            continue

        if bgg_id in seen_ids:
            continue
        seen_ids.add(bgg_id)

        out_file = os.path.join(sheet_dir, f'bgg-{bgg_id}.json')

        # Serve from cache if fresh
        if os.path.exists(out_file):
            age = time.time() - os.path.getmtime(out_file)
            if age < CACHE_SECONDS:
                print(f'CACHED: {name} (BGG {bgg_id})')
                cached += 1
                continue

        print(f'Fetching BGG {bgg_id} for "{name}"…', end=' ', flush=True)
        xml_bytes = bgg_fetch(bgg_id)
        if not xml_bytes:
            print('FAIL (no response)')
            continue

        data = parse_bgg_xml(xml_bytes)
        if not data:
            print('FAIL (parse error)')
            continue

        with open(out_file, 'w', encoding='utf-8') as f:
            json.dump(data, f, ensure_ascii=False, indent=2)

        print('OK')
        fetched += 1
        time.sleep(REQUEST_DELAY)

    print(f'BGG: {fetched} fetched, {cached} cached, {skipped} skipped (no id)')


if __name__ == '__main__':
    main()
