"""
cachemedia.py — download all media URLs found in sheets/{id}/*.json
to a local sheets/{id}/cache/ directory.

Argument: the sheet ID (plain, not the sheetname-format)
Example:  python3 cachemedia.py 1MIuMgYC-9FTm...

Output (stdout):
  CACHED filename  (url)   — file already on disk, skipped
  OK     filename  (url)   — successfully downloaded
  FAIL   url       — reason — could not download
  summary line at the end

The caller (pushsite) reads stdout and relays it to the browser.
stderr is not used.
"""

import os, sys, json, hashlib, urllib.request, urllib.error
from pathlib import Path

# Extensions we will try to download
MEDIA_EXTS = frozenset([
    '.jpg', '.jpeg', '.png', '.gif', '.webp', '.svg', '.avif',
    '.mp4', '.webm', '.ogg', '.mov',
])

# Services we cannot cache (embed-only)
SKIP_HOSTS = ('youtube.com', 'youtu.be', 'vimeo.com')

# ── helpers ───────────────────────────────────────────────────────────────────

def is_cacheable(url: str) -> bool:
    if not isinstance(url, str) or not url.startswith('http'):
        return False
    low = url.lower()
    if any(h in low for h in SKIP_HOSTS):
        return False
    stripped = low.split('?')[0].split('#')[0]
    return any(stripped.endswith(ext) for ext in MEDIA_EXTS)

def url_filename(url: str) -> str:
    """Filename for the cached copy — matches what cachedImage() in the view page expects.

    Google Drive:  extract the file-ID segment → {id}.png
    Everything else: use the original filename from the URL path (query params stripped).
    Falls back to md5+ext if no usable name can be extracted.
    """
    # Google Drive: https://drive.google.com/file/d/{id}/...
    if 'drive.google.com' in url:
        try:
            parts = url.split('drive.google.com')[1].split('/')
            file_id = parts[3] if len(parts) > 3 else ''
            if file_id:
                return file_id + '.png'
        except Exception:
            pass

    # All other URLs: strip query string, take the last path segment
    clean = url.split('?')[0].split('#')[0]
    name  = clean.rstrip('/').rsplit('/', 1)[-1]
    if name:
        return name

    # Fallback: md5 hash (should be rare)
    ext = Path(clean).suffix.lower()
    if ext not in MEDIA_EXTS:
        ext = '.bin'
    return hashlib.md5(url.encode('utf-8')).hexdigest() + ext

def collect_urls(obj) -> set:
    """Recursively walk JSON and collect all cacheable URL strings."""
    found = set()
    if isinstance(obj, list):
        for item in obj:
            found |= collect_urls(item)
    elif isinstance(obj, dict):
        for v in obj.values():
            if isinstance(v, str):
                if is_cacheable(v):
                    found.add(v)
            else:
                found |= collect_urls(v)
    return found

def download(url: str, dest: Path) -> bool:
    """Download url → dest. Returns True on success."""
    req = urllib.request.Request(url, headers={
        'User-Agent': 'Mozilla/5.0 (compatible; ZapSheets/1.0)',
        'Accept': '*/*',
    })
    try:
        with urllib.request.urlopen(req, timeout=25) as r:
            data = r.read()
        dest.write_bytes(data)
        return True
    except Exception as exc:
        print(f'FAIL   {url[:70]}  — {exc}')
        return False

# ── main ──────────────────────────────────────────────────────────────────────

sheet_id = sys.argv[1].strip() if len(sys.argv) > 1 else ''
if not sheet_id:
    print('ERROR  missing sheet ID argument')
    sys.exit(1)

root      = Path(__file__).parent.parent          # project root
sheet_dir = root / 'sheets' / sheet_id
cache_dir = sheet_dir / 'cache'

if not sheet_dir.exists():
    print(f'ERROR  sheets/{sheet_id}/ not found')
    sys.exit(1)

cache_dir.mkdir(parents=True, exist_ok=True)

# Scan every JSON file in the sheet directory
all_urls: set = set()
for jf in sorted(sheet_dir.glob('*.json')):
    try:
        obj = json.loads(jf.read_text(encoding='utf-8'))
        found = collect_urls(obj)
        if found:
            all_urls |= found
    except Exception:
        pass  # malformed JSON — skip silently

if not all_urls:
    print('nothing to cache')
    sys.exit(0)

ok = fail = skipped = 0
for url in sorted(all_urls):
    fname = url_filename(url)
    dest  = cache_dir / fname
    label = f'{fname}  ({url[:60]}{"…" if len(url)>60 else ""})'
    if dest.exists():
        print(f'CACHED {label}')
        skipped += 1
    elif download(url, dest):
        print(f'OK     {label}')
        ok += 1
    else:
        fail += 1

print(f'media cache — {ok} downloaded, {skipped} already cached, {fail} failed')
