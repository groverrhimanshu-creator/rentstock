/* RentStock service worker. Keeps the app shell (styles, scripts, icons) so it opens fast and shows an offline page.
   Pages and data are NEVER cached: they always come from the server, so nobody sees old or someone else's information. */
var V = 'rs-v1', FALLBACK = '/offline';
self.addEventListener('install', function (e) {
    e.waitUntil(caches.open(V).then(function (c) { return c.add(FALLBACK); }).then(function () { return self.skipWaiting(); }));
});
self.addEventListener('activate', function (e) {
    e.waitUntil(caches.keys().then(function (ks) {
        return Promise.all(ks.filter(function (k) { return k !== V; }).map(function (k) { return caches.delete(k); }));
    }).then(function () { return self.clients.claim(); }));
});
self.addEventListener('fetch', function (e) {
    var r = e.request, u = new URL(r.url);
    if (r.method !== 'GET' || u.origin !== location.origin) return;
    if (r.mode === 'navigate') {
        e.respondWith(fetch(r).catch(function () { return caches.match(FALLBACK); }));
        return;
    }
    if (u.pathname.indexOf('/assets/') === 0 || u.pathname.indexOf('/icons/') === 0) {
        e.respondWith(caches.open(V).then(function (c) {
            return c.match(r).then(function (hit) {
                var net = fetch(r).then(function (res) { if (res.ok) c.put(r, res.clone()); return res; }).catch(function () { return hit; });
                return hit || net;
            });
        }));
    }
});
