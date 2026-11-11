/* New replacement: the faulty item, then a working one from stock of the same category only (same model first), or search inside that category. */
(function () {
    var C = window.REP, $ = function (id) { return document.getElementById(id); }, data = { olds: [], info: null, same: [], cat: [], catTotal: 0 }, picked = null, timer;
    function esc(s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
    function err(m) { var b = $('clienterr'); b.textContent = m || ''; b.hidden = !m; }
    function get(url) { return fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' }).then(function (r) { return r.json(); }); }
    function sold() { return $('kind').value !== C.rental; }
    function opt(x, sel) { return '<option value="' + x.code + '"' + (String(sel) === String(x.code) ? ' selected' : '') + '>' + esc(x.label) + '</option>'; }

    function paint(keepNew) {
        var oldv = $('old').value, newv = keepNew != null ? keepNew : $('new').value;
        $('kindnote').textContent = sold()
            ? 'The faulty item returns to stock (repair) and a credit note is made. The new item is sold to the customer.'
            : 'The faulty item goes to repair. The new item is rented to the same customer at the same rent. The customer sees the change in their login.';
        $('old').innerHTML = '<option value="">Select</option>' + data.olds.map(function (x) { return opt(x, oldv); }).join('');
        var ni = data.info, sel = $('new');
        if (!ni) {
            sel.disabled = true; sel.innerHTML = '<option value="">Choose the faulty item first</option>';
            $('searchbox').hidden = true; $('note').innerHTML = ''; return;
        }
        sel.disabled = false;
        var inList = data.same.concat(data.cat).some(function (x) { return String(x.code) === String(newv); });
        var html = '<option value="">Select</option>';
        if (data.same.length) { html += '<optgroup label="Same model (' + data.same.length + ' in stock)">' + data.same.map(function (x) { return opt(x, newv); }).join('') + '</optgroup>'; }
        if (data.cat.length) { html += '<optgroup label="Same category, other model or brand (' + data.catTotal + ' in stock)">' + data.cat.map(function (x) { return opt(x, newv); }).join('') + '</optgroup>'; }
        if (picked && !inList) { html += '<optgroup label="Picked from search">' + opt(picked, newv) + '</optgroup>'; }
        sel.innerHTML = html;
        if (newv && !sel.value && picked && String(picked.code) === String(newv)) { sel.value = String(newv); }
        $('searchbox').hidden = false;
        $('note').innerHTML = data.same.length
            ? '<div class="msg info">' + data.same.length + ' of the same model (' + esc(ni.model) + ') are in stock. Other items of the same category are listed after them.</div>'
            : '<div class="msg">No ' + esc(ni.model) + ' in stock. Other items of the same category (' + esc(ni.cat) + ') are listed instead, or search this category below.</div>';
    }
    function load(keepOld, keepNew) {
        var cid = $('customer').value, old = keepOld != null ? keepOld : $('old').value;
        if (!cid) { data = { olds: [], info: null, same: [], cat: [], catTotal: 0 }; picked = null; paint(''); return; }
        get(C.dataUrl + '?kind=' + encodeURIComponent($('kind').value) + '&customer_id=' + encodeURIComponent(cid) + '&old=' + encodeURIComponent(old || '')).then(function (r) {
            if ($('customer').value !== cid) { return; }
            data = r;
            if (old && !r.olds.some(function (x) { return String(x.code) === String(old); })) { old = ''; }
            $('old').innerHTML = '<option value="">Select</option>' + r.olds.map(function (x) { return opt(x, old); }).join('');
            if (!old) { data.info = null; }
            paint(keepNew != null ? keepNew : '');
        });
    }
    $('kind').addEventListener('change', function () { picked = null; load(''); });
    $('customer').addEventListener('change', function () { picked = null; load(''); });
    $('old').addEventListener('change', function () { picked = null; $('repq').value = ''; $('sug').innerHTML = ''; load($('old').value, ''); });

    $('repq').addEventListener('input', function () {
        clearTimeout(timer);
        timer = setTimeout(function () {
            var q = $('repq').value.trim(), box = $('sug');
            if (q.length < 2) { box.innerHTML = ''; return; }
            get(C.stockUrl + '?limit=8&q=' + encodeURIComponent(q) + '&category=' + encodeURIComponent((data.info && data.info.category_id) || '') + '&exclude=' + encodeURIComponent($('old').value)).then(function (r) {
                if ($('repq').value.trim() !== q) { return; }
                box.innerHTML = '<div class="sug">' + (r.rows.length ? r.rows.map(function (x) {
                    return '<div class="sugrow"><span class="bc">' + esc(x.bc) + '</span><span class="sgt">' + esc(x.model) + ' · ' + esc(x.brand) + ' · ' + esc(x.config) + '</span><button type="button" class="ab sm solid" data-use="' + x.code + '">' + (String($('new').value) === String(x.code) ? 'Selected' : 'Use this') + '</button></div>';
                }).join('') + (r.total > 8 ? '<div class="sugmore">' + (r.total - 8) + ' more. Keep typing to narrow it down.</div>' : '') : '<div class="sugmore">Nothing in this category matches "' + esc(q) + '".</div>') + '</div>';
                box.__rows = r.rows;
            });
        }, 200);
    });
    $('sug').addEventListener('click', function (e) {
        var c = e.target.getAttribute('data-use');
        if (c == null) { return; }
        var x = ($('sug').__rows || []).filter(function (r) { return String(r.code) === c; })[0];
        if (!x) { return; }
        picked = { code: x.code, label: x.bc + ' · ' + x.model + ' · ' + x.config };
        paint(String(x.code)); $('sug').innerHTML = ''; $('repq').value = '';
    });
    $('pf').addEventListener('submit', function (e) {
        if (!$('customer').value) { e.preventDefault(); err('Please choose a customer.'); return; }
        if (!$('old').value || !$('new').value) { e.preventDefault(); err('Choose the faulty item and its replacement.'); }
    });
    paint('');
    if ($('customer').value) { load(C.oldSel, C.newSel); }
})();
