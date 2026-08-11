<?php
/**
 * PulseBoard service worker — served as JavaScript.
 * Cache-first strategy: serve cached page instantly; refresh cache in background.
 * URL: /{sheet_id}/pulseboard/sw.js
 */
error_reporting(0);
header('Content-Type: application/javascript; charset=utf-8');
header('Cache-Control: no-store');
?>
const CACHE = 'pulseboard-v2';

// Canonical URL = the scope (/{id}/pulseboard/)
const SCOPE = self.registration.scope;

// ── Install: pre-cache the page right away ─────────────────────────────────
self.addEventListener('install', e => {
  self.skipWaiting();
  e.waitUntil(
    caches.open(CACHE).then(cache =>
      fetch(SCOPE, { credentials: 'same-origin', cache: 'no-store' })
        .then(res => { if (res.ok) return cache.put(SCOPE, res); })
        .catch(() => {})   // offline during install — cache will fill on first visit
    )
  );
});

// ── Activate: clear old cache versions ────────────────────────────────────
self.addEventListener('activate', e => {
  e.waitUntil(
    caches.keys()
      .then(keys => Promise.all(keys.filter(k => k !== CACHE).map(k => caches.delete(k))))
      .then(() => self.clients.claim())
  );
});

// ── Fetch: cache-first, background refresh ────────────────────────────────
// Serve the cached page immediately (works offline). Simultaneously fetch
// a fresh copy from the network and update the cache for next time.
self.addEventListener('fetch', e => {
  if (e.request.mode !== 'navigate') return;
  e.respondWith(cacheFirst(e.request));
});

async function cacheFirst(req) {
  const cache  = await caches.open(CACHE);
  const cached = await cache.match(req) || await cache.match(SCOPE);

  // Always refresh the cache in the background
  const refresh = fetch(req, { credentials: 'same-origin', cache: 'no-store' })
    .then(res => {
      if (res && res.ok) {
        cache.put(req, res.clone());
        cache.put(SCOPE, res.clone());
      }
      return res;
    })
    .catch(() => null);

  // Return cached copy right away if we have one; otherwise wait for network
  if (cached) return cached;

  const fresh = await refresh;
  if (fresh) return fresh;

  // Nothing cached and network failed
  return new Response(
    '<!DOCTYPE html><html><head><meta charset="UTF-8">' +
    '<meta name="viewport" content="width=device-width,initial-scale=1">' +
    '<title>PulseBoard – Offline</title>' +
    '<style>body{margin:0;background:#0f1923;color:#94a3b8;font-family:Arial,sans-serif;' +
    'display:flex;align-items:center;justify-content:center;min-height:100vh;' +
    'text-align:center;padding:2rem}' +
    'h2{color:#ef4444;margin:0 0 .75rem}p{margin:0;font-size:.9rem;line-height:1.6}</style>' +
    '</head><body><div>' +
    '<h2>Offline</h2>' +
    '<p>Open PulseBoard once while connected<br>to enable offline viewing.</p>' +
    '</div></body></html>',
    { status: 503, headers: { 'Content-Type': 'text/html; charset=utf-8' } }
  );
}
