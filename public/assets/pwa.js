/* Mobile app (PWA): registers the service worker, shows an "Install app" button, opens the menu from the bottom bar. */
(function () {
    if ('serviceWorker' in navigator && (location.protocol === 'https:' || location.hostname === 'localhost')) {
        window.addEventListener('load', function () { navigator.serviceWorker.register('/sw.js').catch(function () {}); });
    }
    var more = document.getElementById('mmore'), sb = document.getElementById('sb');
    if (more && sb) {
        more.addEventListener('click', function (e) { e.stopPropagation(); sb.classList.toggle('collapsed'); });
        document.addEventListener('click', function (e) {
            if (window.innerWidth <= 760 && !sb.classList.contains('collapsed') && !sb.contains(e.target)) sb.classList.add('collapsed');
        });
    }
    var ev = null, tb = document.querySelector('.tb .sp');
    function btn() {
        if (document.getElementById('pwainst') || !tb) return;
        var b = document.createElement('button');
        b.id = 'pwainst'; b.type = 'button'; b.className = 'ab'; b.textContent = 'Install app';
        b.addEventListener('click', function () { if (!ev) return; ev.prompt(); ev.userChoice.finally(function () { ev = null; b.remove(); }); });
        tb.parentNode.insertBefore(b, tb.nextSibling);
    }
    window.addEventListener('beforeinstallprompt', function (e) { e.preventDefault(); ev = e; btn(); });
    window.addEventListener('appinstalled', function () { var b = document.getElementById('pwainst'); if (b) b.remove(); });
    // Offline notice
    function net() {
        var n = document.getElementById('pwaoff');
        if (navigator.onLine) { if (n) n.remove(); return; }
        if (n) return;
        n = document.createElement('div'); n.id = 'pwaoff'; n.textContent = 'You are offline. Changes cannot be saved until the connection is back.';
        document.body.appendChild(n);
    }
    window.addEventListener('online', net); window.addEventListener('offline', net); net();
})();
