/* Upgrade Assets: find the device by barcode, then RAM / SSD from stock, or type the part that was replaced. The server works the config out again on save. */
(function () {
    var C = window.UPG, dev = null, parts = {}, ups = [], $ = function (id) { return document.getElementById(id); };
    function esc(s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
    function err(m) { var b = $('clienterr'); b.textContent = m || ''; b.hidden = !m; }
    function get(url) { return fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' }).then(function (r) { return r.json(); }); }

    /* the same rules as the server: the first segment like 8gb is the RAM, the next one is the storage */
    function segs(cfg) { var s = String(cfg || '').split('/'), ri = -1; for (var i = 0; i < s.length; i++) { if (/^\s*\d+\s*gb\s*$/i.test(s[i])) { ri = i; break; } } return { s: s, ri: ri }; }
    function partOld(cfg, p) { var g = segs(cfg); if (p === 'RAM') { return g.ri >= 0 ? g.s[g.ri].trim() : ''; } if (p === 'Storage') { return g.ri >= 0 && g.s[g.ri + 1] != null ? g.s[g.ri + 1].trim() : ''; } return ''; }
    function partNew(cfg, p, v) {
        var g = segs(cfg); if (g.ri < 0) { return cfg; }
        if (p === 'RAM') { g.s[g.ri] = v + 'gb'; }
        else if (p === 'Storage') { if (g.s[g.ri + 1] == null) { return cfg; } var o = g.s[g.ri + 1].trim(); g.s[g.ri + 1] = /^\d+/.test(o) ? o.replace(/^\d+/, String(v)) : String(v); }
        return g.s.join('/');
    }
    function item(part, id) { return (parts[part] || []).filter(function (x) { return String(x.id) === String(id); })[0]; }
    function label(p) { return C.types.filter(function (t) { return t.key === p; })[0].label; }

    function paint() {
        var cur = dev.config, rows = '', after = dev.config;
        ups.forEach(function (u, i) {
            var old = partOld(cur, u.part), it = item(u.part, u.id), nxt = cur;
            if (it && it.val) { nxt = partNew(cur, u.part, it.val); }
            var typeSel = '<select data-i="' + i + '" data-f="part">' + C.types.map(function (t) { return '<option value="' + t.key + '"' + (u.part === t.key ? ' selected' : '') + '>' + esc(t.label) + '</option>'; }).join('') + '</select>';
            var hidden = '<input type="hidden" name="ups[' + i + '][part]" value="' + u.part + '">';
            var del = '<td>' + (ups.length > 1 ? '<button type="button" class="ab sm danger" data-del="' + i + '">Remove</button>' : '') + '</td>';
            if (u.part === 'Part') {
                rows += '<tr><td>' + typeSel + '</td><td><input class="cell" style="text-align:left" readonly value="-"></td>' +
                    '<td><input class="cell" style="text-align:left;width:100%" maxlength="60" name="ups[' + i + '][text]" data-i="' + i + '" data-f="text" placeholder="Which part was replaced? e.g. Battery, Keyboard" value="' + esc(u.text || '') + '">' +
                    '<select name="ups[' + i + '][item_id]" data-i="' + i + '" data-f="id" style="margin-top:6px"><option value="">Not taken from stock</option>' + (parts.Part || []).map(function (x) { return '<option value="' + x.id + '"' + (String(u.id) === String(x.id) ? ' selected' : '') + '>Take from stock: ' + esc(x.label) + ' · ' + x.qty + ' in stock</option>'; }).join('') + '</select>' + hidden + '</td>' + del + '</tr>';
            } else {
                rows += '<tr><td>' + typeSel + '</td><td><input class="cell" style="text-align:left" readonly value="' + esc(old || 'Not recorded') + '"></td>' +
                    '<td><select name="ups[' + i + '][item_id]" data-i="' + i + '" data-f="id"><option value="">Select from stock</option>' + (parts[u.part] || []).map(function (x) { return '<option value="' + x.id + '"' + (String(u.id) === String(x.id) ? ' selected' : '') + '>' + esc(x.label) + ' · ' + x.qty + ' in stock</option>'; }).join('') + '</select>' + hidden + '</td>' + del + '</tr>';
            }
            cur = nxt; after = nxt;
        });
        $('ups').innerHTML = rows; $('after').value = after;
        $('add').hidden = ups.length >= 10;
    }
    function find() {
        var q = $('scan').value.trim();
        if (!q) { err('Type or scan the barcode of the device.'); return; }
        get(C.lookupUrl + '?code=' + encodeURIComponent(q)).then(function (r) {
            if (!r.found) { err('Barcode ' + q.toUpperCase() + ' is not in the system.'); $('found').hidden = true; dev = null; return; }
            err(''); dev = r.device; parts = r.parts; $('code').value = dev.code;
            $('cur').innerHTML = 'Currently: <b>' + esc(dev.model) + '</b> · ' + esc(dev.config) + ' · ' + esc(dev.status_label) + (dev.customer ? ' · with ' + esc(dev.customer) : '');
            $('found').hidden = false;
            if (!ups.length) { ups = [{ part: 'RAM', id: '', text: '' }]; }
            paint();
        });
    }
    $('find').addEventListener('click', function () { ups = []; find(); });
    $('scan').addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); ups = []; find(); } });
    $('ups').addEventListener('change', function (e) {
        var i = e.target.getAttribute('data-i'), f = e.target.getAttribute('data-f');
        if (i == null) { return; }
        if (f === 'text') { ups[i].text = e.target.value; return; }   // typing: do not redraw, the cursor would jump
        if (f === 'part') { ups[i].part = e.target.value; ups[i].id = ''; } else { ups[i].id = e.target.value; }
        paint();
    });
    $('ups').addEventListener('input', function (e) { if (e.target.getAttribute('data-f') === 'text') { ups[e.target.getAttribute('data-i')].text = e.target.value; } });
    $('ups').addEventListener('click', function (e) { var i = e.target.getAttribute('data-del'); if (i != null) { ups.splice(+i, 1); paint(); } });
    $('add').addEventListener('click', function () {
        var used = ups.map(function (u) { return u.part; }), next = C.types.filter(function (t) { return t.key === 'Part' || used.indexOf(t.key) < 0; })[0];
        if (next) { ups.push({ part: next.key, id: '', text: '' }); paint(); }
    });
    $('uf').addEventListener('submit', function (e) {
        if (!dev) { e.preventDefault(); err('Find the device first.'); return; }
        var seen = {};
        for (var i = 0; i < ups.length; i++) {
            if (ups[i].part === 'Part') {
                if (!String(ups[i].text || '').trim()) { e.preventDefault(); err('Write which part was replaced (for example Battery or Keyboard).'); return; }
                continue;
            }
            if (!ups[i].id) { e.preventDefault(); err('Choose the new RAM or SSD from stock in every row.'); return; }
            if (seen[ups[i].part]) { e.preventDefault(); err('RAM and SSD can each be changed only once in one upgrade.'); return; }
            seen[ups[i].part] = 1;
        }
    });
    if ($('code').value) { ups = (C.oldUps || []).map(function (u) { return { part: u.part, id: u.item_id, text: u.text || '' }; }); find(); }
})();
