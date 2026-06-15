/////////////////////////////////////////////////////////////////////////////////////
// Cache Name
let dyVersion = 0;
/////////////////////////////////////////////////////////////////////////////////////
let jasonPath = './'
/////////////////////////////////////////////////////////////////////////////////////
const CACHE_NAME = {name: 'playbookSW_v13'}
/////////////////////////////////////////////////////////////////////////////////////
// Assets container
let STATIC_ASSETS = []
/////////////////////////////////////////////////////////////////////////////////////
let clientUrl = ''
var sheet_Id = ''
let _client = ''
/////////////////////////////////////////////////////////////////////////////////////
// Get a URL object for the service worker script's location.
/**
 * URL function
 */
const swScriptUrl = new URL(self.location);
/////////////////////////////////////////////////////////////////////////////////////
/**
 * Get URL objects for each client's location.
 */
createCache(null)
function createCache(cacheVersion) {
    if(cacheVersion == null) {
        return
    }
    self.clients.matchAll({includeUncontrolled: true}).then(clients => {
        for (const client of clients) {
            clientUrl = new URL(client.url);
            _client = client;

            // Change your spreadsheet id here to get access
            // Dynamic
            sheet_Id = (getUrlVars(clientUrl.href)["id"]) ? getUrlVars(clientUrl.href)["id"].split('/')[0] : '1qFZqXwiEixdRzO1Ae57_ON9oKzoa-uBiUAOoMcGzoM4';
            
            STATIC_ASSETS = [
                clientUrl,

                // UI CSS
                './css/style.css?version=' + dyVersion,
               

                // UI Images
                './images/logo.png',
                './images/loadingScreen.png',
                './images/floristry_mobile_sym_no_conn.png',
                './images/logoZapsheets.webp',
                './images/logoIconScreen.webp',
                './images/sheet_icon.png',

                // JS Files
                './js/main/JSController.js?version=' + dyVersion,

                // Language JSON Files
                jasonPath + 'sheets/' + sheet_Id + '/settings.json?version=' + dyVersion,
                jasonPath + 'sheets/' + sheet_Id + '/steps-en.json?version=' + dyVersion,
                jasonPath + 'sheets/' + sheet_Id + '/menu-en.json?version=' + dyVersion,
                jasonPath + 'sheets/' + sheet_Id + '/faqs-en.json?version=' + dyVersion,
                jasonPath + 'sheets/' + sheet_Id + '/rules-en.json?version=' + dyVersion,
                jasonPath + 'sheets/' + sheet_Id + '/game-en.json?version=' + dyVersion,
                jasonPath + 'sheets/' + sheet_Id + '/bgg.json?version=' + dyVersion,
            ]
        }
    });
}
/////////////////////////////////////////////////////////////////////////////////////
// To precache the data
/**
 * preCache function
 */
async function preCache() {
    const cache = await caches.open(CACHE_NAME.name)
    return cache.addAll(STATIC_ASSETS)
}
/////////////////////////////////////////////////////////////////////////////////////
// To install the SW
/**
 * SW Install event 
 */
self.addEventListener('install', event => {
    console.log('sw1 installed');

    const selfUrl = new URL(self.location);
    dyVersion = selfUrl.searchParams.get('version');
    createCache(dyVersion)
    self.skipWaiting();

    event.waitUntil(preCache())
    // Exit early if we don't get the client.
    // Eg, if it closed.
    if (!_client) return;
    // Send a message to the client.
    _client.postMessage({
        message: "controller changed",
    });

    
})
/////////////////////////////////////////////////////////////////////////////////////
// To active the SW
/**
 * SW Activate event 
 */
self.addEventListener('activate', event => {
    console.log('sw1 activated');
    // clients.claim() makes this SW take control of all existing pages immediately,
    // so the old SW (which intercepts navigations) stops controlling open tabs.
    event.waitUntil(Promise.all([cleanUpCache(), clients.claim()]));
})
/////////////////////////////////////////////////////////////////////////////////////
// Function to fetch the data from the passes url from cache
/**
 * 
 * @param {*} event 
 * @returns 
 */
async function fetchAssets(event) {
    try {
        const response = await fetch(event.request)
        const clonedResponse = response.clone();
        const runtimeCache = await caches.open(CACHE_NAME.name);
        runtimeCache.put(event.request, response);
        // respond with the cloned network response
        return Promise.resolve(clonedResponse);
    } catch (error) {
        const cache = await caches.open(CACHE_NAME.name)
        const cachedResponse = await cache.match(event.request);
        if (cachedResponse) {
            return cachedResponse;
        }
        // Both network and cache failed — return a proper error response
        // rather than undefined (which causes chrome-error://chromewebdata/)
        return new Response('Network error and no cached version available.', {
            status: 503,
            statusText: 'Service Unavailable',
            headers: { 'Content-Type': 'text/plain' }
        });
    }
}
/////////////////////////////////////////////////////////////////////////////////////
// To fetch cached data
/**
 * SW Fetch event 
 */
self.addEventListener('fetch', event => {
    if (event.request.method !== 'GET') return;
    // Never intercept navigation requests — let the browser handle page loads directly.
    // This prevents the SW from breaking /push/, /sheets/, and any other pages.
    if (event.request.mode === 'navigate') return;
    var url = event.request.url;
    // Never intercept static library files — let the browser fetch them directly
    if (/\/(js|css|fonts|images)\//.test(url)) return;
    event.respondWith(fetchAssets(event));
})
/////////////////////////////////////////////////////////////////////////////////////
// To clean up previous genearated cache
/**
 * 
 * @param {*} params 
 * @returns 
 */
async function cleanUpCache(params) {
    const keys = await caches.keys();
    const keysToDelete = keys.map(key => {
        if(key !== CACHE_NAME.name) {
            return caches.delete(key)
        }
    })
    return Promise.all(keysToDelete)
}
/////////////////////////////////////////////////////////////////////////////////////
// Reading addressbars url to get spreadsheet Id
/**
 * 
 * @param {*} url 
 * @returns 
 */
function getUrlVars(url) {
    var vars = [], hash;
    var hashes = url.slice(url.indexOf('?') + 1).split('&');
    for (var i = 0; i < hashes.length; i++) {
    hash = hashes[i].split('=');
    vars.push(hash[0]);
    vars[hash[0]] = hash[1];
    }
    return vars;
}
/////////////////////////////////////////////////////////////////////////////////////
/**
 * 
 * @param {*} request 
 * @returns 
 */
async function cacheRequest (request) {
    // 1. Check if a cached response matches the outgoing request
    const cache = await caches.open(CACHE_NAME.name)
    const cachedResponse = await cache.match(request);

    // 2. If response has been cached before, return it
    if (cachedResponse) {
        return cachedResponse;
    }
};
/////////////////////////////////////////////////////////////////////////////////////
/**
 * listen to messages
 */
self.addEventListener('message', event => {
    event.waitUntil(cleanUpCache())
    setTimeout(function() {
    }, 300)
});
/////////////////////////////////////////////////////////////////////////////////////