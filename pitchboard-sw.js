/**
 * pitchboard-sw.js
 * Service worker for PitchBoard dashboard offline support.
 * Registered with a per-sheet scope so it doesn't clash with other SWs.
 *
 * Strategy:
 *   Navigation (the HTML page)  → network-first, cache fallback
 *   Fonts (local woff2/ttf)     → cache-first
 *   Everything else             → browser default (data comes from localStorage)
 */

var CACHE = 'pitchboard-sw-v1';

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

  // ── Dashboard page (navigation) ────────────────────────
  // Network-first: serve fresh page when online; fall back to
  // cached shell when offline so localStorage data can load.
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
          return caches.match(e.request, { ignoreSearch: true })
            .then(function(cached){
              return cached || caches.match('/__pb_shell__')
                .then(function(shell){ return shell || Response.error(); });
            });
        })
    );
    return;
  }

  // ── Fonts (local woff2/ttf) ────────────────────────────
  if(/\.(woff2?|ttf)(\?.*)?$/.test(url)){
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
          return new Response('', { status: 503 });
        });
      })
    );
    return;
  }

  // Everything else (JSON data handled by localStorage, push/* needs network)
  // — fall through to browser default.
});
