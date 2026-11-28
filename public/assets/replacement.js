/* New replacement: one or many items. Each row has the item coming back (searchable) and the item to give (searchable, same category only).
   Type "Upgrade / swap" is for a working item that is swapped for a better one: it goes straight back to stock. */
(function () {
    var C = window.REP, $ = function (id) { return document.getElementById(id); }, olds = [], rows = [], uid = 0, timers = {};
    function esc(s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
    function err(m) { var b = $('clienterr'); b.textContent = m || ''; b.hidden = !m; if (m) { window.scrollTo(0, 0); } }
    function get(url) { return fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' }).then(function (r) { return r.json(); }); }
    function sold() { return $('kind').value === C.sale; }
    function upg() { return $('kind').value === C.upgrade; }
    function opt(x, sel) { return '<option value="' + x.code + '"' + (String(sel) === String(x.code) ? ' selected' : '') + '>' + esc(x.label) + '</option>'; }
    function words(q) { q = (q || '').trim().toLowerCase(); return q ? q.split(/\s+/) : []; }
    function match(label, w) { label = label.toLowerCase(); return w.every(function (t) { return label.indexOf(t) >= 0; }); }

    function usedOld(except) { return rows.filter(function (r) { return r !== except && r.old; }).map(function (r) { return String(r.old); }); }
    function usedNew(except) { return rows.filter(function (r) { return r !== except && r.new; }).map(function (r) { return String(r.new); }); }

    function fillOld(r) {
        var w = words(r.oldq.value), taken = usedOld(r), sel = r.oldSel.value || r.old;
        var list = olds.filter(function (x) { return String(x.code) === String(sel) || (taken.indexOf(String(x.code)) < 0 && match(x.label, w)); });
        r.oldSel.innerHTML = '<option value="">' + (w.length ? list.length + ' found' : 'Select') + '</option>' + list.map(function (x) { return opt(x, sel); }).join('');
        if (w.length && list.length === 1 && !sel) { r.oldSel.value = String(list[0].code); changedOld(r); }
    }
    function fillNew(r) {
        var sel = r.newSel;
        if (!r.info) { sel.disabled = true; r.newq.disabled = true; sel.innerHTML = '<option value="">Choose the item coming back first</option>'; r.note.textContent = ''; return; }
        sel.disabled = false; r.newq.disabled = false;
        var w = words(r.newq.value), taken = usedNew(r), cur = r.new;
        var ok = function (x) { return String(x.code) === String(cur) || (taken.indexOf(String(x.code)) < 0 && match(x.label, w)); };
        var same = r.same.filter(ok), cat = r.cat.filter(ok), extra = (r.extra || []).filter(function (x) { return ok(x) && !same.concat(cat).some(function (y) { return y.code === x.code; }); });
        var html = '<option value="">' + (w.length ? same.length + cat.length + extra.length + ' found' : 'Select') + '</option>';
        if (same.length) { html += '<optgroup label="Same model (' + same.length + ')">' + same.map(function (x) { return opt(x, cur); }).join('') + '</optgroup>'; }
        if (cat.length) { html += '<optgroup label="Same category, other model or brand (' + cat.length + ')">' + cat.map(function (x) { return opt(x, cur); }).join('') + '</optgroup>'; }
        if (extra.length) { html += '<optgroup label="Search result">' + extra.map(function (x) { return opt(x, cur); }).join('') + '</optgroup>'; }
        sel.innerHTML = html;
        if (cur) { sel.value = String(cur); }
        r.note.textContent = r.same.length ? r.same.length + ' of the same model in stock.' : 'No ' + r.info.model + ' in stock, other items of the same category (' + r.info.cat + ') are listed.';
    }
    function refillAll() { rows.forEach(function (r) { fillOld(r); fillNew(r); }); }

    function changedOld(r) {
        r.old = r.oldSel.value; r.new = ''; r.info = null; r.same = []; r.cat = []; r.extra = [];
        if (!r.old) { refillAll(); return; }
        var cid = $('customer').value;
        get(C.dataUrl + '?kind=' + encodeURIComponent(sold() ? C.sale : C.rental) + '&customer_id=' + encodeURIComponent(cid) + '&old=' + encodeURIComponent(r.old)).then(function (d) {
            if (r.oldSel.value !== r.old) { return; }
            r.info = d.info; r.same = d.same || []; r.cat = d.cat || [];
            refillAll();
        });
        refillAll();
    }
    function searchExtra(r) {
        clearTimeout(timers[r.id]);
        timers[r.id] = setTimeout(function () {
            var q = r.newq.value.trim();
            if (!r.info || q.length < 2) { r.extra = []; fillNew(r); return; }
            get(C.stockUrl + '?limit=40&q=' + encodeURIComponent(q) + '&category=' + encodeURIComponent(r.info.category_id || '') + '&exclude=' + encodeURIComponent(r.old)).then(function (d) {
                if (r.newq.value.trim() !== q) { return; }
                r.extra = (d.rows || []).map(function (x) { return { code: x.code, label: x.bc + ' · ' + x.model + ' · ' + x.config }; });
                fillNew(r);
                if (!r.extra.length && !r.same.concat(r.cat).some(function (x) { return match(x.label, words(q)); })) { r.note.textContent = 'Nothing in this category matches "' + q + '". It may be in another category (a replacement must be of the same category) or not in stock.'; }
            });
        }, 250);
    }

    function addRow() {
        var r = { id: ++uid, old: '', new: '', info: null, same: [], cat: [], extra: [] }, i = rows.length;
        var tr = document.createElement('tr');
        tr.innerHTML = '<td class="rn"></td>' +
            '<td><input type="search" class="ctl oldq" placeholder="Search: barcode, model or brand tag" style="margin-bottom:6px"><select class="olds"><option value="">Select</option></select> <button type="button" class="ab sm scan-old">Scan</button></td>' +
            '<td><input type="search" class="ctl newq" placeholder="Search: barcode, brand or model" style="margin-bottom:6px" disabled><select class="news" disabled><option value="">Choose the item coming back first</option></select> <button type="button" class="ab sm scan-new">Scan</button><div class="hint nnote"></div></td>' +
            '<td class="cond"><select class="fault"><option value="">Select</option>' + C.faults.map(function (f) { return '<option>' + esc(f) + '</option>'; }).join('') + '</select></td>' +
            '<td><button type="button" class="ab sm danger rm">Remove</button></td>';
        r.tr = tr; r.oldq = tr.querySelector('.oldq'); r.oldSel = tr.querySelector('.olds'); r.newq = tr.querySelector('.newq'); r.newSel = tr.querySelector('.news'); r.fault = tr.querySelector('.fault'); r.note = tr.querySelector('.nnote');
        function scan(btn, title, inp, sel, refill) {
            btn.addEventListener('click', function () {
                if (!window.CamScan) { return; }
                window.CamScan.open({ title: title, onCode: function (code) {
                    var d = String(code).replace(/\D/g, ''), o = null;
                    if (!d) { return { ok: false, msg: code + ' is not a barcode of this system.' }; }
                    if (sel.disabled) { return { ok: false, msg: 'Please choose the item coming back first.' }; }
                    inp.value = ''; refill();
                    Array.prototype.forEach.call(sel.options, function (x) { if (String(x.value) === String(parseInt(d, 10))) { o = x; } });
                    if (!o) { return { ok: false, msg: code + ' is not in this list.' }; }
                    sel.value = o.value; sel.dispatchEvent(new Event('change'));
                    return { ok: true, close: true, msg: o.textContent.trim() + ' selected' };
                } });
            });
        }
        scan(tr.querySelector('.scan-old'), 'Scan the item coming back', r.oldq, r.oldSel, function () { fillOld(r); });
        scan(tr.querySelector('.scan-new'), 'Scan the item to give', r.newq, r.newSel, function () { fillNew(r); });
        r.oldq.addEventListener('input', function () { fillOld(r); });
        r.oldq.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); } });
        r.oldSel.addEventListener('change', function () { changedOld(r); });
        r.newq.addEventListener('input', function () { fillNew(r); searchExtra(r); });
        r.newq.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); } });
        r.newSel.addEventListener('change', function () { r.new = r.newSel.value; refillAll(); });
        r.fault.addEventListener('change', function () { r.faultv = r.fault.value; });
        tr.querySelector('.rm').addEventListener('click', function () { rows.splice(rows.indexOf(r), 1); tr.remove(); number(); refillAll(); });
        rows.push(r); $('rrows').appendChild(tr); number(); fillOld(r); fillNew(r);
        return r;
    }
    function number() {
        rows.forEach(function (r, i) {
            r.tr.querySelector('.rn').textContent = i + 1;
            r.oldSel.name = 'rows[' + i + '][old_code]'; r.newSel.name = 'rows[' + i + '][new_code]'; r.fault.name = 'rows[' + i + '][fault]';
            r.tr.querySelector('.rm').style.display = rows.length > 1 ? '' : 'none';
        });
        $('rcount').textContent = rows.length;
        $('addrow').hidden = rows.length >= 50;
    }
    function kindUi() {
        var u = upg();
        $('kindnote').textContent = u
            ? 'Upgrade / swap: the item is NOT faulty. The customer gets a better item in its place at the same rent, and the old item goes straight back to stock (not to repair).'
            : (sold() ? 'The faulty item returns to stock (repair) and a credit note is made. The new item is sold to the customer.'
                : 'The faulty item goes to repair. The new item is rented to the same customer at the same rent. The customer sees the change in their login.');
        $('reasonlab').textContent = u ? 'Reason for the upgrade' : 'Reason';
        $('reason').placeholder = u ? 'For example: upgraded to i7' : 'What is wrong with the item';
        $('condh').hidden = u;
        rows.forEach(function (r) { r.tr.querySelector('.cond').hidden = u; r.fault.required = !u; });
    }
    function load() {
        var cid = $('customer').value;
        olds = [];
        rows.forEach(function (r) { r.tr.remove(); }); rows = [];
        if (!cid) { addRow(); kindUi(); return; }
        get(C.dataUrl + '?kind=' + encodeURIComponent(sold() ? C.sale : C.rental) + '&customer_id=' + encodeURIComponent(cid)).then(function (d) {
            if ($('customer').value !== cid) { return; }
            olds = d.olds || [];
            addRow(); kindUi();
        });
    }
    $('kind').addEventListener('change', function () { load(); });
    $('customer').addEventListener('change', load);
    $('addrow').addEventListener('click', function () { addRow(); kindUi(); });
    $('pf').addEventListener('submit', function (e) {
        var m = '';
        if (!$('customer').value) { m = 'Please choose a customer.'; }
        else if (!$('reason').value.trim()) { m = upg() ? 'Please enter the reason for the upgrade.' : 'Please enter the reason for the replacement.'; }
        else {
            var used = rows.filter(function (r) { return r.oldSel.value || r.newSel.value; });
            if (!used.length) { m = 'Choose the item coming back and the item to give.'; }
            used.some(function (r, i) {
                if (!r.oldSel.value || !r.newSel.value) { m = 'Row ' + (rows.indexOf(r) + 1) + ': choose the item coming back and the item to give.'; return true; }
                if (!upg() && !r.fault.value) { m = 'Row ' + (rows.indexOf(r) + 1) + ': choose whether the item is Damaged or Faulty.'; return true; }
                return false;
            });
        }
        if (m) { e.preventDefault(); e.stopImmediatePropagation(); err(m); }
    });
    addRow(); kindUi();
    if ($('customer').value) { load(); }
})();
