# gretry.py — retry temporary Google Sheets API errors.
#
# `import gretry` right after `import gspread` in a script. It wraps gspread's
# HTTP request method so that temporary Google errors are retried with a short
# back-off (1s, 2s, 4s) instead of failing the whole save:
#   • reads (GET): retried on 429 / 500 / 502 / 503 / 504
#   • writes: retried only on 429 / 503 (Google didn't process the request),
#     and never for appends, so a retry can't create duplicate rows.
import time
import gspread

_READ_RETRY  = {429, 500, 502, 503, 504}
_WRITE_RETRY = {429, 503}
_DELAYS      = (1, 2, 4)


def _status(err):
    resp = getattr(err, 'response', None)
    try:
        return int(getattr(resp, 'status_code', 0) or 0)
    except (TypeError, ValueError):
        return 0


def _wrap(orig):
    def request(self, method, endpoint, *args, **kwargs):
        is_read = str(method).lower() == 'get'
        is_append = ':append' in str(endpoint)
        for attempt in range(len(_DELAYS) + 1):
            try:
                return orig(self, method, endpoint, *args, **kwargs)
            except gspread.exceptions.APIError as err:
                code = _status(err)
                retry = code in (_READ_RETRY if is_read else _WRITE_RETRY) and not is_append
                if not retry or attempt == len(_DELAYS):
                    raise
                time.sleep(_DELAYS[attempt])
    request._gretry = True
    return request


def _patch(cls):
    orig = getattr(cls, 'request', None)
    if orig and not getattr(orig, '_gretry', False):
        cls.request = _wrap(orig)


# gspread 5.x: Client.request; gspread 6.x: http_client.HTTPClient.request
_patch(gspread.Client)
try:
    from gspread import http_client as _hc
    _patch(_hc.HTTPClient)
except Exception:
    pass
