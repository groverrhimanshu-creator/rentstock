/* Edit challan: add items from stock. The server checks everything again on save. */
(function () {
    var C = window.CHE, added = [], $ = function (id) { return document.getElementById(id); }, timer;
    function esc(s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
    function err(m) { var b = $('clienterr'); b.textContent = m || ''; b.hidden = !m; }
    function get(url) { return fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' }).then(function (r) { return r.json(); }); }
    function has(code) { return added.some(function (a) { return a.code === code; }); }
    function render() {
        $('addbox').hidden = !added.length;
        $('addbody').innerHTML = added.map(function (a, i) {
            return '<tr><td class="bc">' + esc(a.bc) + '</td><td>' + esc(a.model) + '</td><td><input class="cell" type="number" min="0" step="0.01" name="add[' + i + '][rent]" data-i="' + i + '" value="' + esc(a.rent) + '"><input type="hidden" name="add[' + i + '][code]" value="' + a.code + '"></td><td><button type="button" class="ab sm danger" data-del="' + i + '">Remove</button></td></tr>';
        }).join('');
    }
    function add(r) { if (has(r.code)) { err('Already added.'); return false; } added.push({ code: r.code, bc: r.bc, model: r.model, rent: r.rent }); err(''); render(); return true; }
    function suggest() {
        var q = $('addq').value.trim(), box = $('sug');
        if (q.length < 2) { box.innerHTML = ''; return; }
        get(C.stockUrl + '?limit=8&q=' + encodeURIComponent(q) + '&exclude=' + added.map(function (a) { return a.code; }).join(',')).then(function (r) {
            if ($('addq').value.trim() !== q) { return; }
            box.__rows = r.rows;
            box.innerHTML = '<div class="sug">' + (r.rows.length ? r.rows.map(function (x) {
                return '<div class="sugrow" data-add="' + x.code + '"><span class="bc">' + esc(x.bc) + '</span><span class="sgt">' + esc(x.model) + ' · ' + esc(x.config) + '</span><button type="button" class="ab sm solid">Add</button></div>';
            }).join('') + (r.total > 8 ? '<div class="sugmore">' + (r.total - 8) + ' more. Keep typing to narrow it down.</div>' : '') : '<div class="sugmore">Nothing in stock matches "' + esc(q) + '".</div>') + '</div>';
        });
    }
    function done() { $('addq').value = ''; $('sug').innerHTML = ''; $('addq').focus(); }
    $('addq').addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(suggest, 200); });
    $('addq').addEventListener('keydown', function (e) {
        if (e.key !== 'Enter') { return; }
        e.preventDefault();
        var q = $('addq').value.trim();
        if (!q) { return; }
        get(C.stockUrl + '?code=' + encodeURIComponent(q)).then(function (r) {
            if (r.found) {
                if (r.status !== 'in_stock') { err(r.row.bc + ' is ' + r.status_label.toLowerCase() + ', not in stock.'); return; }
                if (add(r.row)) { done(); }
            } else { suggest(); }
        });
    });
    $('sug').addEventListener('click', function (e) {
        var row = e.target.closest('[data-add]');
        if (!row) { return; }
        var r = ($('sug').__rows || []).filter(function (x) { return String(x.code) === row.getAttribute('data-add'); })[0];
        if (r && add(r)) { done(); }
    });
    $('addbody').addEventListener('input', function (e) { var i = e.target.getAttribute('data-i'); if (i != null) { added[i].rent = e.target.value; } });
    $('addbody').addEventListener('click', function (e) { var i = e.target.getAttribute('data-del'); if (i != null) { added.splice(+i, 1); render(); } });
})();
