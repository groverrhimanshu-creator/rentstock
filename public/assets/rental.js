/* New rental challan: scan or pick pieces from stock, set the rent of each. The server checks everything again on save. */
(function () {
    var C = window.RENT, lines = [], shown = [], $ = function (id) { return document.getElementById(id); };
    function money(n) { return (Number(n) || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
    function esc(s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
    function err(m) { var b = $('clienterr'); b.textContent = m || ''; b.hidden = !m; }
    function has(code) { return lines.some(function (l) { return l.code === code; }); }
    function codes() { return lines.map(function (l) { return l.code; }); }
    function get(url) { return fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' }).then(function (r) { return r.json(); }); }

    function addDevice(d) {
        if (has(d.code)) { err('Already added.'); return false; }
        lines.push({ code: d.code, bc: d.bc, name: d.model, rent: d.rent });
        err(''); render(); return true;
    }
    function render() {
        $('sel-count').textContent = lines.length;
        $('clear').hidden = !lines.length;
        $('sel-empty').hidden = lines.length > 0;
        $('sel-table').hidden = !lines.length;
        $('sel-body').innerHTML = lines.map(function (l, i) {
            return '<tr><td class="bc">' + esc(l.bc) + '</td><td>' + esc(l.name) + '</td><td>' + esc(C.sac) + '</td>' +
                '<td><input class="cell" type="number" min="0" step="0.01" name="lines[' + i + '][rent]" data-i="' + i + '" value="' + esc(l.rent) + '"></td>' +
                '<td><button type="button" class="ab sm danger" data-del="' + i + '">Remove</button></td></tr>';
        }).join('');
        $('hidden').innerHTML = lines.map(function (l, i) { return '<input type="hidden" name="lines[' + i + '][code]" value="' + l.code + '">'; }).join('');
        syncTicks();
    }
    $('sel-body').addEventListener('input', function (e) { var i = e.target.getAttribute('data-i'); if (i != null) { lines[i].rent = e.target.value; } });
    $('sel-body').addEventListener('click', function (e) { var i = e.target.getAttribute('data-del'); if (i != null) { lines.splice(+i, 1); err(''); render(); } });
    $('clear').addEventListener('click', function () { lines = []; err(''); render(); });


    /* ---- several at once: barcodes and/or names separated by commas ---- */
    function terms(s) { return s.split(/[,;\n]+/).map(function (t) { return t.trim(); }).filter(Boolean); }
    var codeRe = new RegExp('^(' + C.prefix.replace(/[^A-Za-z0-9]/g, '') + ')?\\s*[0-9]{4,9}$', 'i');
    function fetchTerm(t, skip) {
        if (codeRe.test(t)) {
            return get(C.stockUrl + '?code=' + encodeURIComponent(t)).then(function (r) {
                if (!r.found) { return { rows: [], miss: t.toUpperCase() + ' is not in the system' }; }
                if (r.status !== 'in_stock') { return { rows: [], miss: r.row.bc + ' is ' + r.status_label.toLowerCase() }; }
                return { rows: has(r.row.code) ? [] : [r.row] };
            });
        }
        return get(C.stockUrl + '?limit=40&q=' + encodeURIComponent(t) + '&exclude=' + skip).then(function (r) {
            return { rows: r.rows, miss: r.rows.length ? '' : 'Nothing in stock matches "' + t + '"', more: r.total > r.rows.length ? r.total - r.rows.length : 0 };
        });
    }
    function fetchMany(ts) {
        var skip = codes().join(',');
        return Promise.all(ts.map(function (t) { return fetchTerm(t, skip); })).then(function (res) {
            var seen = {}, rows = [], miss = [], more = 0;
            res.forEach(function (x) {
                x.rows.forEach(function (r) { if (!seen[r.code]) { seen[r.code] = 1; rows.push(r); } });
                if (x.miss) { miss.push(x.miss); }
                more += x.more || 0;
            });
            return { rows: rows, miss: miss, more: more };
        });
    }
    function showMany(r, box) {
        var h = '<div class="sug">';
        if (r.rows.length) { h += '<div class="sugrow sugall" data-addall="1"><span class="sgt"><b>Add all ' + r.rows.length + ' items</b></span><button type="button" class="ab sm solid">Add all</button></div>'; }
        h += r.rows.map(function (x) {
            return '<div class="sugrow" data-add="' + x.code + '"><span class="bc">' + esc(x.bc) + '</span><span class="sgt">' + esc(x.model) + ' · ' + esc(x.config) + '</span><button type="button" class="ab sm solid">Add</button></div>';
        }).join('');
        if (r.more) { h += '<div class="sugmore">' + r.more + ' more match. Make the name more specific to see them.</div>'; }
        h += r.miss.map(function (m) { return '<div class="sugmore">' + esc(m) + '</div>'; }).join('');
        box.innerHTML = h + '</div>';
        box.__rows = r.rows;
    }
    function addAll(rows) {
        var n = 0;
        rows.forEach(function (x) { if (!has(x.code)) { lines.push({ code: x.code, bc: x.bc, name: x.model, rent: x.rent }); n++; } });
        err(''); render();
        return n;
    }

    var timer;
    function suggest() {
        var q = $('scan').value.trim(), box = $('sug');
        if (q.length < 2) { box.innerHTML = ''; return; }
        var ts = terms(q);
        if (ts.length > 1) { fetchMany(ts).then(function (r) { if ($('scan').value.trim() === q) { showMany(r, box); } }); return; }
        get(C.stockUrl + '?limit=8&q=' + encodeURIComponent(q) + '&exclude=' + codes().join(',')).then(function (r) {
            if ($('scan').value.trim() !== q) { return; }
            box.innerHTML = '<div class="sug">' + (r.rows.length ? r.rows.map(function (x) {
                return '<div class="sugrow" data-add="' + x.code + '"><span class="bc">' + esc(x.bc) + '</span><span class="sgt">' + esc(x.model) + ' · ' + esc(x.config) + '</span><button type="button" class="ab sm solid">Add</button></div>';
            }).join('') + (r.total > 8 ? '<div class="sugmore">' + (r.total - 8) + ' more. Keep typing to narrow it down.</div>' : '') : '<div class="sugmore">Nothing in stock matches "' + esc(q) + '".</div>') + '</div>';
            box.__rows = r.rows;
        });
    }
    $('scan').addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(suggest, 200); });
    $('scan').addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); scanAdd(); } });
    $('scanbtn').addEventListener('click', scanAdd);
    $('sug').addEventListener('click', function (e) {
        if (e.target.closest('[data-addall]')) { if (addAll($('sug').__rows || [])) { $('scan').value = ''; $('sug').innerHTML = ''; $('scan').focus(); } return; }
        var r0 = e.target.closest('[data-add]');   // anywhere on the row
        if (!r0) { return; }
        var c = r0.getAttribute('data-add');
        var row = ($('sug').__rows || []).filter(function (x) { return String(x.code) === c; })[0];
        if (row && addDevice(row)) { $('scan').value = ''; $('sug').innerHTML = ''; $('scan').focus(); }
    });
    function done() { $('scan').value = ''; $('sug').innerHTML = ''; $('scan').focus(); }
    function scanAdd() {
        var q = $('scan').value.trim();
        if (!q) { err('Type a full barcode, or pick an item from the suggestions.'); return; }
        var ts = terms(q);
        if (ts.length > 1) {   // several barcodes: add them now. Names: show what matches so the right ones can be picked.
            fetchMany(ts).then(function (r) {
                if (ts.every(function (t) { return codeRe.test(t); })) {
                    addAll(r.rows); $('scan').value = ''; $('sug').innerHTML = ''; $('scan').focus();
                    err(r.miss.length ? r.miss.join('. ') + '.' : '');
                } else { showMany(r, $('sug')); }
            });
            return;
        }
        var looksLikeCode = new RegExp('^(' + C.prefix.replace(/[^A-Za-z0-9]/g, '') + ')?\\s*[0-9]{4,9}$', 'i').test(q);
        if (looksLikeCode) {
            get(C.stockUrl + '?code=' + encodeURIComponent(q)).then(function (r) {
                if (!r.found) { err('Barcode ' + q.toUpperCase() + ' is not in the system.'); return; }
                if (r.status !== 'in_stock') { err(r.row.bc + ' is ' + r.status_label.toLowerCase() + ', not in stock.'); return; }
                if (addDevice(r.row)) { done(); }
            });
            return;
        }
        get(C.stockUrl + '?limit=2&q=' + encodeURIComponent(q) + '&exclude=' + codes().join(',')).then(function (r) {
            if (q.length >= 2 && r.total === 1 && addDevice(r.rows[0])) { done(); } else { err('Type a full barcode, or pick an item from the suggestions.'); }
        });
    }

    function loadStock() {
        get(C.stockUrl + '?limit=40&item=' + encodeURIComponent($('stockitem').value)).then(function (r) {
            shown = r.rows;
            $('stock-body').innerHTML = r.rows.length ? r.rows.map(function (x) {
                return '<tr><td><input type="checkbox" data-tg="' + x.code + '"' + (has(x.code) ? ' checked' : '') + '></td><td class="bc">' + esc(x.bc) + '</td><td>' + esc(x.model) + '</td><td>' + esc(x.config) + '</td><td class="num">' + money(x.rent) + '</td></tr>';
            }).join('') : '<tr><td colspan="5" class="empty">Nothing in stock.</td></tr>';
            $('stock-more').textContent = r.total > 40 ? 'Showing the first 40 of ' + r.total + '. Choose an item above or type in the search box to narrow it down.' : '';
            syncTicks();
        });
    }
    function syncTicks() {
        var all = shown.length > 0 && shown.every(function (x) { return has(x.code); });
        $('tickall').textContent = all ? 'Untick all shown' : 'Tick all shown';
        document.querySelectorAll('[data-tg]').forEach(function (cb) { cb.checked = has(+cb.getAttribute('data-tg')); });
    }
    $('stockitem').addEventListener('change', loadStock);
    $('stock-body').addEventListener('change', function (e) {
        var c = e.target.getAttribute('data-tg');
        if (c == null) { return; }
        var x = shown.filter(function (r) { return String(r.code) === c; })[0];
        if (e.target.checked) { addDevice(x); } else { lines = lines.filter(function (l) { return String(l.code) !== c; }); render(); }
    });
    $('tickall').addEventListener('click', function () {
        var all = shown.length > 0 && shown.every(function (x) { return has(x.code); });
        if (all) { var s = shown.map(function (x) { return x.code; }); lines = lines.filter(function (l) { return s.indexOf(l.code) < 0; }); }
        else { shown.forEach(function (x) { if (!has(x.code)) { lines.push({ code: x.code, bc: x.bc, name: x.model, rent: x.rent }); } }); }
        err(''); render();
    });

    $('rf').addEventListener('submit', function (e) {
        if (!$('customer').value) { e.preventDefault(); err('Please choose a customer.'); return; }
        if (!lines.length) { e.preventDefault(); err('Add at least one item.'); }
    });

    (C.lines || []).forEach(function (l) { if (l.code) { lines.push({ code: +l.code, bc: C.prefix + l.code, name: '', rent: l.rent }); } });
    render(); loadStock();
})();
