/**
 * slides-sw.js  –  PitchBoard Slides service worker
 * Scope: /{id}/pitchboard/slides
 *
 * Install
 *   Pre-caches: page shell, games.json, every Pitching-status slide image.
 *   This means the app is fully offline after ONE online visit — the user
 *   does not need to manually swipe through every slide first.
 *
 * Activate
 *   Migrates images / fonts / shell from the previous cache version before
 *   deleting it, so a version bump never causes an offline outage.
 *
 * Fetch strategies
 *   Navigation (page)   → network-first, cached-shell fallback
 *   games.json          → network-first, cache fallback (query string stripped)
 *   Images              → stale-while-revalidate
 *   Fonts               → cache-first
 *   Everything else     → browser default
 */

var CACHE = 'pb-slides-v8';

// ── Utility: resolve Dropbox URL to a direct-download link ──
// Mirrors the resolveImage() logic in the slides page.
function resolvePhotoUrl(url) {
  if (!url) return '';
  url = url.trim();
  if (url.includes('dropbox.com')) {
    if (url.includes('dl=0'))       return url.replace('dl=0', 'dl=1');
    if (url.match(/[?&]dl=/))       return url;
    return url + (url.includes('?') ? '&' : '?') + 'dl=1';
  }
  return url;
}

// ── Utility: derive games.json URL from scope ──────────────
// Scope: https://host[/base]/{sheetId}/pitchboard/slides
function gamesJsonUrl() {
  var scope = self.registration.scope.replace(/\/$/, '');
  var m = scope.match(/^(https?:\/\/.+)\/([A-Za-z0-9_\-]+)\/pitchboard\/slides$/);
  if (!m) return null;
  return m[1] + '/sheets/' + m[2] + '/games.json';
}

// ── Utility: is this an image request? ────────────────────
function isImageRequest(req, url) {
  if (req.destination === 'image') return true;
  var host = url.hostname;
  if (host.includes('dropbox.com'))           return true;
  if (host.includes('drive.google.com'))      return true;
  if (host.includes('googleusercontent.com')) return true;
  if (host.includes('imgur.com'))             return true;
  if (host.includes('cloudinary.com'))        return true;
  if (url.pathname.includes('/cache/'))       return true;
  if (/\.(jpe?g|png|webp|gif|avif)(\?.*)?$/i.test(url.pathname)) return true;
  return false;
}

// ── Install ────────────────────────────────────────────────
self.addEventListener('install', function (e) {
  self.skipWaiting();

  e.waitUntil(
    caches.open(CACHE).then(function (cache) {

      var pageUrl  = self.registration.scope.replace(/\/$/, '');
      var gamesUrl = gamesJsonUrl();

      // 1. Pre-cache the app shell (page HTML).
      var pageTask = cache
        .add(new Request(pageUrl, { cache: 'no-cache' }))
        .catch(function () {});

      if (!gamesUrl) return pageTask;

      // 2. Fetch games.json, cache it, then cache every slide image.
      var dataTask = fetch(gamesUrl)
        .then(function (res) {
          if (!res.ok) return;

          // Clone before consuming so we can cache the raw response.
          var rawClone = res.clone();

          return res.json().then(function (games) {
            // Cache games.json under its canonical (query-free) URL.
            cache.put(gamesUrl, rawClone).catch(function () {});

            if (!Array.isArray(games)) return;

            // Collect image URLs for Pitching-status games.
            var imageUrls = [];
            games.forEach(function (g) {
              var status = (g['Status'] || '').trim().toLowerCase();
              if (status !== 'pitching') return;
              var url = resolvePhotoUrl(g['Photo URL'] || g['Image URL'] || '');
              if (url) imageUrls.push(url);
            });

            // Fetch + cache each image.
            // mode:'no-cors' is required for cross-origin hosts (Dropbox, etc.)
            // so the response comes back as an opaque blob that can be stored.
            return Promise.all(imageUrls.map(function (imgUrl) {
              return fetch(imgUrl, { mode: 'no-cors' })
                .then(function (imgRes) {
                  // Opaque responses (type === 'opaque') display fine in <img>.
                  if (imgRes.ok || imgRes.type === 'opaque') {
                    return cache.put(imgUrl, imgRes);
                  }
                })
                .catch(function () {});   // network failure — skip silently
            }));
          });
        })
        .catch(function () {});   // games.json unavailable — skip silently

      return Promise.all([pageTask, dataTask]);
    })
  );
});

// ── Activate ───────────────────────────────────────────────
// Migrate images, fonts, and the page shell from the previous cache
// version before deleting it, so a version bump never loses offline data.
self.addEventListener('activate', function (e) {
  e.waitUntil(
    caches.open(CACHE).then(function (newCache) {
      return caches.keys().then(function (keys) {
        var oldKeys = keys.filter(function (k) { return k !== CACHE; });
        return Promise.all(
          oldKeys.map(function (oldKey) {
            return caches.open(oldKey).then(function (oldCache) {
              return oldCache.keys().then(function (reqs) {
                return Promise.all(reqs.map(function (req) {
                  // Skip stale games.json entries that were stored with a
                  // ?v=timestamp key — they'd never be found by the new lookup.
                  if (req.url.includes('games.json') && req.url.includes('?')) return;

                  var u   = new URL(req.url);
                  var keep = isImageRequest(req, u)
                    || /\.(woff2?|ttf)$/i.test(u.pathname)
                    || u.pathname.endsWith('/pitchboard/slides');
                  if (!keep) return;

                  return oldCache.match(req).then(function (res) {
                    if (!res) return;
                    return newCache.match(req).then(function (existing) {
                      if (existing) return;          // don't overwrite newer entry
                      return newCache.put(req, res.clone()).catch(function () {});
                    });
                  });
                }));
              });
            }).then(function () {
              return caches.delete(oldKey);
            });
          })
        );
      });
    }).then(function () { return self.clients.claim(); })
  );
});

// ── Fetch ──────────────────────────────────────────────────
self.addEventListener('fetch', function (e) {
  if (e.request.method !== 'GET') return;

  var req  = e.request;
  var url  = new URL(req.url);
  var path = url.pathname;

  // ── Navigation (page shell) ────────────────────────────
  if (req.mode === 'navigate') {
    e.respondWith(
      fetch(req)
        .then(function (res) {
          if (res.ok) {
            caches.open(CACHE).then(function (c) { c.put(req, res.clone()); });
          }
          return res;
        })
        .catch(function () {
          return caches.match(req, { ignoreSearch: true })
            .then(function (cached) { return cached || Response.error(); });
        })
    );
    return;
  }

  // ── games.json ────────────────────────────────────────
  // The page appends ?v=<timestamp>; strip it so cache keys are stable.
  if (path.endsWith('/games.json')) {
    var gamesKey = req.url.split('?')[0];
    e.respondWith(
      fetch(req)
        .then(function (res) {
          if (res.ok) {
            caches.open(CACHE).then(function (c) { c.put(gamesKey, res.clone()); });
          }
          return res;
        })
        .catch(function () {
          return caches.match(gamesKey).then(function (cached) {
            return cached || new Response('[]', {
              status:  200,
              headers: { 'Content-Type': 'application/json' },
            });
          });
        })
    );
    return;
  }

  // ── Fonts ─────────────────────────────────────────────
  if (/\.(woff2?|ttf)(\?.*)?$/.test(path)) {
    e.respondWith(
      caches.match(req).then(function (cached) {
        if (cached) return cached;
        return fetch(req).then(function (res) {
          if (res.ok) caches.open(CACHE).then(function (c) { c.put(req, res.clone()); });
          return res;
        }).catch(function () { return new Response('', { status: 503 }); });
      })
    );
    return;
  }

  // ── Images: stale-while-revalidate ────────────────────
  // Serve from cache immediately; refresh in the background when online.
  // e.waitUntil() MUST be called synchronously from the event handler — calling
  // it inside a nested .then() violates the SW spec and iOS WebKit rejects it,
  // causing the background cache.put() to be killed before it finishes.
  if (isImageRequest(req, url)) {
    // Create a deferred resolver so we can call e.waitUntil() right now (sync)
    // and resolve it later once the background write is actually done.
    var resolveWait;
    e.waitUntil(new Promise(function (resolve) { resolveWait = resolve; }));

    e.respondWith(
      caches.open(CACHE).then(function (cache) {
        return cache.match(req).then(function (cached) {

          // Background refresh — try CORS mode first so Dropbox CDN returns a
          // transparent response (they serve Access-Control-Allow-Origin: *),
          // which avoids iOS opaque-response caching limits.  Fall back to the
          // original request mode if CORS is refused.
          var refresh = fetch(new Request(req.url, { mode: 'cors', credentials: 'omit' }))
            .then(function (res) {
              if (res && res.ok) {
                return cache.put(req.url, res.clone()).then(function () { return res; });
              }
              return null; // trigger fallback
            })
            .catch(function () { return null; })
            .then(function (corsRes) {
              if (corsRes) return corsRes;
              // CORS failed — try with the original request (no-cors / opaque)
              return fetch(req)
                .then(function (res) {
                  if (res && (res.ok || res.type === 'opaque')) {
                    return cache.put(req, res.clone()).then(function () { return res; });
                  }
                  return res;
                })
                .catch(function () { return null; });
            })
            .then(function (res) { resolveWait(); return res; });

          // Cache hit → return immediately; background refresh runs via waitUntil.
          if (cached) return cached;

          // Cache miss → wait for network.
          return refresh.then(function (res) {
            return res || new Response('', { status: 503 });
          });
        });
      })
    );
    return;
  }

  // ── App icons / PNG shell assets ───────────────────────
  if (/\.(png|svg|webp|gif|ico)(\?.*)?$/.test(path)) {
    e.respondWith(
      caches.match(req).then(function (cached) {
        if (cached) return cached;
        return fetch(req).then(function (res) {
          if (res.ok) caches.open(CACHE).then(function (c) { c.put(req, res.clone()); });
          return res;
        }).catch(function () { return new Response('', { status: 503 }); });
      })
    );
    return;
  }

  // Everything else → browser default
});

// ── Message: PRECACHE_IMAGES ───────────────────────────────
// Sent by the page after server-side caching assigns local URLs to images.
// Ensures server-cached paths are also stored in the SW cache for offline use.
// e.waitUntil() keeps the SW alive until all cache writes complete — without it,
// iOS can kill the SW before the async puts finish.
self.addEventListener('message', function (e) {
  if (!e.data || e.data.type !== 'PRECACHE_IMAGES') return;
  var urls = e.data.urls;
  if (!Array.isArray(urls)) return;

  e.waitUntil(
    caches.open(CACHE).then(function (cache) {
      return Promise.all(urls.map(function (url) {
        if (!url) return;
        return cache.match(url).then(function (existing) {
          if (existing) return;
          return fetch(url)
            .then(function (res) { if (res.ok) return cache.put(url, res); })
            .catch(function () {});
        });
      }));
    })
  );
});
