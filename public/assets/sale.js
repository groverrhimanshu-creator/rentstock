/* New sale: scan or pick barcoded pieces, add quantity items, edit prices, see the totals. The server checks everything again on save. */
(function () {
    var C = window.SALE, lines = [], rentRows = [], mode = 'sale', $ = function (id) { return document.getElementById(id); };
    function money(n) { return (Number(n) || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
    function r2(x) { return Math.round(x * 100 + 1e-9) / 100; }
    function esc(s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
    function err(m) { var b = $('clienterr'); b.textContent = m || ''; b.hidden = !m; }
    function has(code) { return lines.some(function (l) { return l.code === code; }); }
    function codes() { return lines.filter(function (l) { return l.code; }).map(function (l) { return l.code; }); }
    function get(url) { return fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' }).then(function (r) { return r.json(); }); }

    function addDevice(d) {
        if (has(d.code)) { err('Already added.'); return false; }
        lines.push({ code: d.code, bc: d.bc, item_id: d.item_id, name: d.model, qty: 1, price: d.price });
        err(''); render(); return true;
    }

    /* ---- selected items and totals ---- */
    /* Rent rows of the same category and price are shown as one row with a quantity. */
    function rentGroups() {
        var order = [], map = {};
        rentRows.forEach(function (x) {
            if (!map[x.g]) { map[x.g] = { g: x.g, cat: x.cat || x.name, models: [], codes: [], price: x.price, n: 0 }; order.push(map[x.g]); }
            var g = map[x.g]; g.n++; g.codes.push(x.bc);
            if (g.models.indexOf(x.name) < 0) { g.models.push(x.name); }
        });
        return order;
    }
    function hsnOf(l) { return ((C.items[l.item_id] || {}).hsn) || ''; }
    function render() {
        var tb = $('sel-body'), box = $('hidden'), ri = mode === 'rentinv', groups = ri ? rentGroups() : [], rows = '';
        var total = lines.length + rentRows.length;
        $('sel-count').textContent = total;
        $('clear').hidden = !total;
        $('relist').hidden = !ri; if (!ri) { $('usedates').hidden = true; }
        $('sel-empty').textContent = ri ? ($('customer').value ? 'No items in the list. Use "List items on rent again" to bring them back.' : '') : 'No items selected yet.';
        $('sel-empty').hidden = total > 0 || (ri && !$('customer').value);
        $('sel-table').hidden = !total;
        $('sel-head').innerHTML = '<tr><th>Barcode</th><th>Item</th><th>HSN/SAC</th><th class="num">Qty</th><th>' + (ri ? 'Price (each)' : 'Price') + '</th><th></th></tr>';
        if (ri && groups.length) {
            if (lines.length) { rows += '<tr class="sec"><td colspan="6"><b>Rent</b></td></tr>'; }
            groups.forEach(function (g) {
                rows += '<tr><td class="bc" style="font-size:11px;max-width:220px;word-break:break-word">' + esc(g.codes.join(', ')) + '</td><td><b>' + esc(g.cat) + ' Rent</b><br><small>' + esc(g.models.join(', ')) + '</small></td><td>' + esc(C.rentSac) + '</td><td class="num">' + g.n + '</td>' +
                    '<td><input class="cell" type="number" min="0" step="0.01" data-g="' + esc(g.g) + '" value="' + esc(g.price) + '"></td>' +
                    '<td><button type="button" class="ab sm danger" data-delg="' + esc(g.g) + '">Remove</button></td></tr>';
            });
            if (lines.length) { rows += '<tr class="sec"><td colspan="6"><b>Sold on this invoice</b></td></tr>'; }
        }
        lines.forEach(function (l, i) {
            rows += '<tr><td class="bc">' + (l.code ? esc(l.bc) : '-') + '</td><td>' + esc(l.name) + '</td><td>' + esc(hsnOf(l)) + '</td><td class="num">' + l.qty + '</td>' +
                '<td><input class="cell" type="number" min="0" step="0.01" name="lines[' + i + '][price]" data-i="' + i + '" value="' + esc(l.price) + '"></td>' +
                '<td><button type="button" class="ab sm danger" data-del="' + i + '">Remove</button></td></tr>';
        });
        tb.innerHTML = rows;
        box.innerHTML = lines.map(function (l, i) {
            return (l.code ? '<input type="hidden" name="lines[' + i + '][code]" value="' + l.code + '">' : '') +
                '<input type="hidden" name="lines[' + i + '][item_id]" value="' + l.item_id + '"><input type="hidden" name="lines[' + i + '][qty]" value="' + l.qty + '">';
        }).join('') + (ri ? rentRows.map(function (x, i) {
            return '<input type="hidden" name="rent[' + i + '][code]" value="' + x.code + '"><input type="hidden" name="rent[' + i + '][price]" value="' + esc(x.price) + '">';
        }).join('') : '');
        totals(); syncTicks();
    }
    function totals() {
        var sel = $('customer'), opt = sel.options[sel.selectedIndex], type = $('type').value;
        var mode2 = !sel.value ? 'none' : (type !== 'GST' ? 'none' : (opt.getAttribute('data-state') === C.companyState ? 'intra' : 'inter'));
        var taxable = 0, cg = 0, sg = 0, ig = 0;
        lines.concat(mode === 'rentinv' ? rentRows.map(function (x) { return { item_id: x.item_id, qty: 1, price: x.price }; }) : []).forEach(function (l) {
            var t = r2(l.qty * (Number(l.price) || 0)), g = (C.items[l.item_id] || {}).gst || 0;
            taxable += t;
            if (mode2 === 'intra') { cg += r2(t * g / 200); sg += r2(t * g / 200); } else if (mode2 === 'inter') { ig += r2(t * g / 100); }
        });
        taxable = r2(taxable); cg = r2(cg); sg = r2(sg); ig = r2(ig);
        $('r-cg').hidden = $('r-sg').hidden = mode2 !== 'intra';
        $('r-ig').hidden = mode2 !== 'inter';
        $('t-taxable').textContent = money(taxable); $('t-cg').textContent = money(cg); $('t-sg').textContent = money(sg); $('t-ig').textContent = money(ig);
        $('t-total').textContent = money(taxable + cg + sg + ig);
    }

    $('sel-body').addEventListener('input', function (e) {
        var i = e.target.getAttribute('data-i'), g = e.target.getAttribute('data-g');
        if (i != null) { lines[i].price = e.target.value; }
        if (g != null) { rentRows.forEach(function (x) { if (x.g === g) { x.price = e.target.value; } }); box_sync(); }
        totals();
    });
    /* keep the hidden rent prices in step with what is typed, without redrawing the table (the cursor would jump) */
    function box_sync() {
        var inputs = $('hidden').querySelectorAll('input[name$="[price]"]');
        rentRows.forEach(function (x, i) { if (inputs[i]) { inputs[i].value = x.price; } });
    }
    $('sel-body').addEventListener('click', function (e) {
        var i = e.target.getAttribute('data-del'), g = e.target.getAttribute('data-delg');
        if (i != null) { lines.splice(+i, 1); err(''); render(); }
        if (g != null) { rentRows = rentRows.filter(function (x) { return x.g !== g; }); err(''); render(); }
    });
    $('clear').addEventListener('click', function () { lines = []; rentRows = []; err(''); render(); });
    $('customer').addEventListener('change', totals);
    $('type').addEventListener('change', totals);

    /* ---- scan or type ---- */
    var timer;
    function suggest() {
        var q = $('scan').value.trim(), box = $('sug');
        if (q.length < 2) { box.innerHTML = ''; return; }
        get(C.stockUrl + '?limit=8&q=' + encodeURIComponent(q) + '&exclude=' + codes().join(',')).then(function (r) {
            if ($('scan').value.trim() !== q) { return; }
            box.innerHTML = '<div class="sug">' + (r.rows.length ? r.rows.map(function (x) {
                return '<div class="sugrow"><span class="bc">' + esc(x.bc) + '</span><span class="sgt">' + esc(x.model) + ' · ' + esc(x.config) + '</span><button type="button" class="ab sm solid" data-add="' + x.code + '">Add</button></div>';
            }).join('') + (r.total > 8 ? '<div class="sugmore">' + (r.total - 8) + ' more. Keep typing to narrow it down.</div>' : '') : '<div class="sugmore">Nothing in stock matches "' + esc(q) + '".</div>') + '</div>';
            box.__rows = r.rows;
        });
    }
    $('scan').addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(suggest, 200); });
    $('scan').addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); scanAdd(); } });
    $('scanbtn').addEventListener('click', scanAdd);
    $('sug').addEventListener('click', function (e) {
        var c = e.target.getAttribute('data-add');
        if (c == null) { return; }
        var row = ($('sug').__rows || []).filter(function (x) { return String(x.code) === c; })[0];
        if (row && addDevice(row)) { $('scan').value = ''; $('sug').innerHTML = ''; $('scan').focus(); }
    });
    function scanAdd() {
        var q = $('scan').value.trim();
        if (!q) { err('Type a full barcode, or pick an item from the suggestions.'); return; }
        var looksLikeCode = new RegExp('^(' + C.prefix.replace(/[^A-Za-z0-9]/g, '') + ')?\\s*[0-9]{4,9}$', 'i').test(q);
        if (looksLikeCode) {
            get(C.stockUrl + '?code=' + encodeURIComponent(q)).then(function (r) {
                if (!r.found) { err('Barcode ' + q.toUpperCase() + ' is not in the system.'); return; }
                if (r.status !== 'in_stock') { err(r.row.bc + ' is ' + r.status_label.toLowerCase() + ', not in stock.'); return; }
                if (addDevice(r.row)) { $('scan').value = ''; $('sug').innerHTML = ''; $('scan').focus(); }
            });
            return;
        }
        get(C.stockUrl + '?limit=2&q=' + encodeURIComponent(q) + '&exclude=' + codes().join(',')).then(function (r) {
            if (q.length >= 2 && r.total === 1 && addDevice(r.rows[0])) { $('scan').value = ''; $('sug').innerHTML = ''; $('scan').focus(); }
            else { err('Type a full barcode, or pick an item from the suggestions.'); }
        });
    }

    /* ---- tick list ---- */
    var shown = [];
    function loadStock() {
        get(C.stockUrl + '?limit=40&item=' + encodeURIComponent($('stockitem').value)).then(function (r) {
            shown = r.rows;
            $('stock-body').innerHTML = r.rows.length ? r.rows.map(function (x) {
                return '<tr><td><input type="checkbox" data-tg="' + x.code + '"' + (has(x.code) ? ' checked' : '') + '></td><td class="bc">' + esc(x.bc) + '</td><td>' + esc(x.model) + '</td><td>' + esc(x.config) + '</td><td class="num">' + money(x.price) + '</td></tr>';
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
        else { shown.forEach(function (x) { if (!has(x.code)) { lines.push({ code: x.code, bc: x.bc, item_id: x.item_id, name: x.model, qty: 1, price: x.price }); } }); }
        err(''); render();
    });

    /* ---- quantity items ---- */
    $('qadd').addEventListener('click', function () {
        var id = $('qitem').value, n = parseInt($('qqty').value, 10), left = Number($('qitem').options[$('qitem').selectedIndex].getAttribute('data-left')) || 0;
        if (!id) { err('Choose a quantity item first.'); return; }
        if (!(n >= 1)) { err('Quantity must be a whole number of 1 or more.'); return; }
        var have = lines.filter(function (l) { return !l.code && String(l.item_id) === id; })[0];
        if ((have ? have.qty : 0) + n > left) { err('Only ' + left + ' left in stock.'); return; }
        if (have) { have.qty += n; } else { lines.push({ code: null, bc: '', item_id: +id, name: C.items[id].name, qty: n, price: C.items[id].price }); }
        err(''); render();
    });

    /* ---- Sale / Rental invoice ---- */
    var oldPrices = {};
    function setMode(m, keep) {
        mode = m;
        var ri = m === 'rentinv';
        document.querySelectorAll('.rentonly').forEach(function (x) { x.hidden = !ri; });
        $('pick-rent').hidden = !ri;
        $('pick-sale').hidden = false;
        $('sale-h').textContent = ri ? 'Sell items on this invoice' : 'Add items';
        $('sale-sub').hidden = !ri;
        $('ptitle').firstChild.nodeValue = ri ? 'New rental invoice' : 'New sale';
        $('psub').textContent = ri ? 'Items on rent with the customer are listed automatically. You can also sell items on the same invoice.' : 'Items are sold. An invoice is created.';
        $('save-btn').textContent = ri ? 'Save and create rental invoice' : 'Save and create invoice';
        $('save-note').textContent = ri ? 'Saving creates one invoice. Rented items stay with the customer. Any items you added to sell leave stock.' : 'Saving removes these items from stock and creates the invoice in your format.';
        $('rent-hint').hidden = !ri || !!$('customer').value;
        if (!keep) { lines = []; rentRows = []; err(''); }
        if (ri) { if (!keep && $('customer').value) { setPeriod(); } else { loadBill(); } } else { rentRows = []; render(); }
    }
    /* Bill from / Bill to start with the dates that fit the customer. They can still be changed. */
    function setPeriod() {
        var cid = $('customer').value;
        if (!cid) { loadBill(); return; }
        get(C.periodUrl + '?customer_id=' + encodeURIComponent(cid)).then(function (r) {
            if (mode !== 'rentinv' || $('customer').value !== cid) { return; }
            if (r.from) { $('pfrom').value = r.from; }
            if (r.to) { $('pto').value = r.to; }
            loadBill();
        });
    }
    var sug = null;
    function loadBill() {
        var cid = $('customer').value, from = $('pfrom').value, to = $('pto').value;
        $('rent-hint').hidden = !!cid;
        if (!cid || !from || !to) { rentRows = []; render(); return; }
        get(C.billUrl + '?customer_id=' + encodeURIComponent(cid) + '&from=' + encodeURIComponent(from) + '&to=' + encodeURIComponent(to) + '&full=' + ($('full').checked ? '1' : '0')).then(function (r) {
            if (mode !== 'rentinv' || $('customer').value !== cid) { return; }
            $('usedates').hidden = true; sug = null;
            if (r.error) { rentRows = []; err(r.error); render(); return; }
            err(r.rows.length ? '' : (r.note || ''));
            if (!r.rows.length && r.suggest) { sug = r.suggest; $('usedates').hidden = false; }
            rentRows = r.rows.map(function (x) {
                return { code: x.code, bc: x.bc, item_id: x.item_id, name: x.name, cat: x.cat, g: (x.cat || x.name) + '|' + x.price, price: oldPrices[x.code] != null ? oldPrices[x.code] : x.price };
            });
            oldPrices = {};
            render();
        });
    }
    $('mode').addEventListener('change', function () { setMode(this.value, false); });
    $('pfrom').addEventListener('change', function () { if (mode === 'rentinv') { loadBill(); } });
    $('pto').addEventListener('change', function () { if (mode === 'rentinv') { loadBill(); } });
    $('full').addEventListener('change', function () { if (mode === 'rentinv') { loadBill(); } });
    $('customer').addEventListener('change', function () { if (mode === 'rentinv') { setPeriod(); } });
    $('relist').addEventListener('click', loadBill);
    $('usedates').addEventListener('click', function () { if (sug) { $('pfrom').value = sug.from; $('pto').value = sug.to; loadBill(); } });

    $('sf').addEventListener('submit', function (e) {
        if (!$('customer').value) { e.preventDefault(); err('Please choose a customer.'); return; }
        if (mode === 'rentinv' && (!$('pfrom').value || !$('pto').value)) { e.preventDefault(); err('Choose the "Bill from" and "Bill to" dates.'); return; }
        if (mode === 'rentinv' && !rentRows.length) { e.preventDefault(); err('There are no rented items to bill. To invoice only items you sell, choose Sale at the top.'); return; }
        if (mode !== 'rentinv' && !lines.length) { e.preventDefault(); err('Add at least one item.'); }
    });

    (C.lines || []).forEach(function (l) {
        var it = C.items[l.item_id];
        if (!it) { return; }
        lines.push({ code: l.code ? +l.code : null, bc: l.code ? C.prefix + l.code : '', item_id: +l.item_id, name: it.name, qty: +l.qty || 1, price: l.price });
    });
    if (C.mode === 'rentinv') { (C.rent || []).forEach(function (l) { if (l.code) { oldPrices[+l.code] = l.price; } }); setMode('rentinv', true); } else { render(); }
    loadStock();
})();
