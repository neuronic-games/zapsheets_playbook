/**
 * cardboard-sw.js — CardBoard designer offline support.
 * Registered by the designer with the scope {base}{sheetId}/cardboard, so it only looks after
 * the designer pages (and doesn't clash with the other apps' service workers).
 *
 * The sheet's data is kept by the page itself (IndexedDB) and unsaved changes in local storage;
 * this keeps what the page needs to open without a connection:
 *   The designer page (navigation)        → network first (fresh when online), the kept copy offline
 *   Our own files (fonts, icons, scripts) → kept copy at once, refreshed in the background
 *   Google Fonts                          → kept copy first
 *   Card pictures (any image)             → kept copy first, so cards show offline (the newest 400)
 * Requests that save (POST) and push/*.php data calls are never touched.
 */
var CACHE = 'cardboard-sw-v1', IMG = 'cardboard-img-v1', IMG_MAX = 400;

self.addEventListener('install', function() { self.skipWaiting(); });
self.addEventListener('activate', function(e) {
  e.waitUntil(caches.keys().then(function(keys) {
    return Promise.all(keys.filter(function(k) { return /^cardboard-/.test(k) && k !== CACHE && k !== IMG; }).map(function(k) { return caches.delete(k); }));
  }).then(function() { return self.clients.claim(); }));
});

function put(name, req, res) {
  if (!res || !(res.ok || res.type === 'opaque')) return;
  var copy = res.clone();
  caches.open(name).then(function(c) {
    return c.put(req, copy).then(function() {
      if (name !== IMG) return;
      return c.keys().then(function(ks) { if (ks.length > IMG_MAX) return Promise.all(ks.slice(0, ks.length - IMG_MAX).map(function(k) { return c.delete(k); })); });
    });
  }).catch(function() {});
}
function cacheFirst(name, req) {
  return caches.match(req).then(function(hit) {
    return hit || fetch(req).then(function(r) { put(name, req, r); return r; });
  });
}
function staleWhileRevalidate(req) {
  return caches.match(req).then(function(hit) {
    var net = fetch(req).then(function(r) { put(CACHE, req, r); return r; }).catch(function() { return hit; });
    return hit || net;
  });
}

self.addEventListener('fetch', function(e) {
  var req = e.request;
  if (req.method !== 'GET') return;
  var url = new URL(req.url), same = url.origin === self.location.origin;

  if (req.mode === 'navigate') {
    e.respondWith(fetch(req).then(function(r) { put(CACHE, req, r); return r; }).catch(function() {
      return caches.match(req, { ignoreSearch: true }).then(function(hit) { return hit || Response.error(); });
    }));
    return;
  }
  if (same && /\/push\//.test(url.pathname) && !/cardboardImage\.php$/.test(url.pathname)) return;   // data calls: always the network
  if (req.destination === 'image' || (same && /cardboardImage\.php$/.test(url.pathname))) { e.respondWith(cacheFirst(IMG, req).catch(function() { return Response.error(); })); return; }
  if (/(^|\.)fonts\.(googleapis|gstatic)\.com$/.test(url.hostname)) { e.respondWith(cacheFirst(CACHE, req).catch(function() { return Response.error(); })); return; }
  if (same && /\.(js|css|woff2?|ttf|png|jpe?g|gif|svg|webp|ico)$/i.test(url.pathname)) { e.respondWith(staleWhileRevalidate(req)); return; }
});
