/**
 * fitboard-sw.js
 * Service worker for FitBoard offline support.
 * Registered with a per-sheet scope so it doesn't clash with sw_playbook.js.
 *
 * Strategy:
 *   Navigation (the HTML page)  → network-first, cache fallback
 *   Fonts (local + Google)      → cache-first
 *   Everything else             → browser default (no interception)
 */

var CACHE = 'fitboard-sw-v1';

// ── Install ────────────────────────────────────────────────
self.addEventListener('install', function(){
  self.skipWaiting();
});

// ── Activate ───────────────────────────────────────────────
self.addEventListener('activate', function(e){
  e.waitUntil(
    caches.keys().then(function(keys){
      return Promise.all(
        keys
          .filter(function(k){ return k !== CACHE; })
          .map(function(k){ return caches.delete(k); })
      );
    }).then(function(){ return self.clients.claim(); })
  );
});

// ── Fetch ──────────────────────────────────────────────────
self.addEventListener('fetch', function(e){
  if(e.request.method !== 'GET') return;

  var url = e.request.url;

  // ── Fitboard page (navigation) ─────────────────────────
  // Network-first: always try to get a fresh page; fall back to
  // the cached copy when offline.
  if(e.request.mode === 'navigate'){
    e.respondWith(
      fetch(e.request)
        .then(function(r){
          if(r.ok){
            var clone = r.clone();
            caches.open(CACHE).then(function(c){ c.put(e.request, clone); });
          }
          return r;
        })
        .catch(function(){
          return caches.match(e.request).then(function(cached){
            return cached || new Response(
              '<!DOCTYPE html><html><head><meta charset="UTF-8">'
              + '<meta name="viewport" content="width=device-width,initial-scale=1">'
              + '<title>FitBoard — Offline</title>'
              + '<style>body{background:#0f0f14;color:#fff;font-family:sans-serif;'
              + 'display:flex;align-items:center;justify-content:center;height:100vh;margin:0;text-align:center}'
              + 'h2{margin-bottom:.5rem}p{color:rgba(255,255,255,.6)}</style></head>'
              + '<body><div><h2>You\'re offline</h2>'
              + '<p>Open FitBoard once while connected<br>to make it available offline.</p></div></body></html>',
              { headers:{ 'Content-Type':'text/html; charset=UTF-8' } }
            );
          });
        })
    );
    return;
  }

  // ── Fonts: local woff2/ttf and Google Fonts ────────────
  // Cache-first: fonts never change, always serve from cache once stored.
  if(/\.(woff2?|ttf)(\?.*)?$/.test(url) ||
     /fonts\.(googleapis|gstatic)\.com/.test(url)){
    e.respondWith(
      caches.match(e.request).then(function(cached){
        if(cached) return cached;
        return fetch(e.request).then(function(r){
          if(r.ok){
            var clone = r.clone();
            caches.open(CACHE).then(function(c){ c.put(e.request, clone); });
          }
          return r;
        }).catch(function(){
          return new Response('', { status:503 });
        });
      })
    );
    return;
  }

  // Everything else (week.json handled by localStorage, save/sync need network)
  // — fall through to browser default.
});
