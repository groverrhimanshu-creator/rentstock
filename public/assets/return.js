/* New return: choose the customer, tick the items that came back, set reason, condition and notes for each. */
(function () {
    var C = window.RET, rows = [], $ = function (id) { return document.getElementById(id); };
    function esc(s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
    function err(m) { var b = $('clienterr'); b.textContent = m || ''; b.hidden = !m; }
    function d(ymd) { return ymd ? ymd.split('-').reverse().join('/') : ''; }
    function get(url) { return fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' }).then(function (r) { return r.json(); }); }

    function paint() {
        $('out-count').textContent = rows.length;
        $('rows').innerHTML = rows.length ? rows.map(function (x) {
            var on = !!x.on, dis = on ? '' : ' disabled', n = 'items[' + x.code + ']';
            return '<tr><td><input type="checkbox" data-sel="' + x.code + '"' + (on ? ' checked' : '') + '></td><td class="bc">' + esc(x.bc) + '</td><td>' + esc(x.model) + '</td><td>' + d(x.rented_at) + '</td>' +
                '<td><select name="' + n + '[reason]" data-f="reason" data-c="' + x.code + '"' + dis + '>' + C.reasons.map(function (r) { return '<option' + (x.reason === r ? ' selected' : '') + '>' + esc(r) + '</option>'; }).join('') + '</select></td>' +
                '<td><select name="' + n + '[cond]" data-f="cond" data-c="' + x.code + '"' + dis + '><option value="Good">Good</option><option value="Damaged"' + (x.cond === 'Damaged' ? ' selected' : '') + '>Damaged/Faulty</option></select></td>' +
                '<td><input class="cellw" name="' + n + '[note]" data-f="note" data-c="' + x.code + '" value="' + esc(x.note) + '" placeholder="' + (x.cond === 'Damaged' ? 'Notes (required)' : 'Notes') + '" maxlength="255"' + dis + (on && x.cond === 'Damaged' && !String(x.note || '').trim() ? ' style="border-color:#B3261E"' : '') + '></td></tr>';
        }).join('') : '<tr><td colspan="7" class="empty">Nothing is on rent with this customer.</td></tr>';
        $('n').textContent = rows.filter(function (x) { return x.on; }).length;
    }
    function find(c) { return rows.filter(function (x) { return String(x.code) === String(c); })[0]; }
    $('rows').addEventListener('change', function (e) {
        var t = e.target, c = t.getAttribute('data-sel');
        if (c != null) { find(c).on = t.checked; paint(); return; }
        var f = t.getAttribute('data-f');
        if (f) { find(t.getAttribute('data-c'))[f] = t.value; if (f === 'cond') { paint(); } }
    });
    $('rows').addEventListener('input', function (e) { var t = e.target; if (t.getAttribute('data-f') === 'note') { find(t.getAttribute('data-c')).note = t.value; } });
    $('selall').addEventListener('click', function () { rows.forEach(function (x) { x.on = true; }); paint(); });
    $('selnone').addEventListener('click', function () { rows.forEach(function (x) { x.on = false; }); paint(); });
    function tick() {
        var q = $('scan').value.trim().toUpperCase().replace(/\s+/g, ''), p = C.prefix.toUpperCase();
        if (q.indexOf(p) === 0) { q = q.slice(p.length); }
        var x = /^\d{4,9}$/.test(q) ? find(q) : null;
        if (!x) { err('This barcode is not on rent with this customer.'); return; }
        err(''); x.on = true; paint(); $('scan').value = ''; $('scan').focus();
    }
    $('scanbtn').addEventListener('click', tick);
    $('scan').addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); tick(); } });
    function load() {
        var cid = $('customer').value;
        $('box').hidden = !cid;
        if (!cid) { rows = []; return; }
        get(C.itemsUrl + '?customer_id=' + encodeURIComponent(cid)).then(function (r) {
            if ($('customer').value !== cid) { return; }
            rows = r.rows.map(function (x) { x.on = false; x.reason = C.reasons[0]; x.cond = 'Good'; x.note = ''; return x; });
            err(''); paint();
        });
    }
    $('customer').addEventListener('change', load);
    $('tf').addEventListener('submit', function (e) {
        if (!$('customer').value) { e.preventDefault(); err('Please choose a customer.'); return; }
        if (!rows.some(function (x) { return x.on; })) { e.preventDefault(); err('Tick at least one item.'); return; }
        var miss = rows.filter(function (x) { return x.on && x.cond === 'Damaged' && !String(x.note || '').trim(); });
        if (miss.length) {
            e.preventDefault();
            err('Please write a note for each Damaged/Faulty item: ' + miss.slice(0, 5).map(function (x) { return x.bc; }).join(', ') + (miss.length > 5 ? ' and more' : '') + '.');
            paint();
            var first = document.querySelector('input[data-f=note][data-c="' + miss[0].code + '"]'); if (first) { first.focus(); }
        }
    });
    load();
})();
