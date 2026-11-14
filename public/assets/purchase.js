/* New purchase: add and remove item rows, and show the totals while typing. The server checks everything again on save. */
(function () {
    var C = window.PURCHASE, rows = document.getElementById('rows'), next = 0;
    function money(n) { return (Number(n) || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
    function r2(x) { return Math.round(x * 100 + 1e-9) / 100; }
    function esc(s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
    function item(id) { for (var i = 0; i < C.items.length; i++) { if (String(C.items[i].id) === String(id)) { return C.items[i]; } } return null; }

    function range(a, z) { var r = []; for (var x = a; x <= z; x++) { r.push(C.prefix + x); } return r; }

    function addRow(v) {
        v = v || {};
        var i = next++, it = item(v.item_id);
        var bc = v.bc || (it ? (it.tracked ? 'yes' : 'no') : 'yes');
        var tr = document.createElement('tr');
        tr.innerHTML =
            '<td><select name="lines[' + i + '][item_id]" data-f="item"><option value="">Select item</option>' + C.items.map(function (x) { return '<option value="' + x.id + '"' + (String(x.id) === String(v.item_id) ? ' selected' : '') + '>' + esc(x.name) + '</option>'; }).join('') + '</select></td>' +
            '<td><input class="cellw" name="lines[' + i + '][config]" value="' + esc(v.config) + '" placeholder="Config" maxlength="150"></td>' +
            '<td><input class="cell" name="lines[' + i + '][hsn]" data-f="hsn" value="' + esc(v.hsn) + '" maxlength="10" inputmode="numeric" placeholder="HSN"></td>' +
            '<td><textarea class="cellw" rows="3" style="min-width:190px" name="lines[' + i + '][tags]" data-f="tags" placeholder="One tag per piece. Type or paste, separated by commas or new lines" autocomplete="off">' + esc(v.tags) + '</textarea><small class="hint" data-f="tagn"></small><br><button type="button" class="ab sm" data-f="tagfile">Import tags from file</button></td>' +
            '<td><select name="lines[' + i + '][bc]" data-f="bc"><option value="yes"' + (bc === 'yes' ? ' selected' : '') + '>With barcode</option><option value="no"' + (bc === 'no' ? ' selected' : '') + '>No barcode (count only)</option></select></td>' +
            '<td><input class="cell" type="number" min="1" step="1" name="lines[' + i + '][qty]" data-f="qty" value="' + esc(v.qty == null ? 1 : v.qty) + '"></td>' +
            '<td><input class="cell" type="number" min="0" step="0.01" name="lines[' + i + '][rate]" data-f="rate" value="' + esc(v.rate) + '"></td>' +
            '<td class="num" data-f="amt">0.00</td>' +
            '<td><span class="codes" data-f="codes"></span></td>' +
            '<td><button type="button" class="ab sm danger" data-f="del">Remove</button></td>';
        rows.appendChild(tr);
        refresh();
    }

    function refresh() {
        var sup = document.getElementById('supplier'), opt = sup.options[sup.selectedIndex];
        var type = document.getElementById('type').value;
        var mode = !sup.value ? 'none' : (type !== 'GST' ? 'none' : (opt.getAttribute('data-state') === C.companyState ? 'intra' : 'inter'));
        var taxable = 0, tax = 0, trs = rows.querySelectorAll('tr');
        var code = Number(C.nextCode) || 0, first = 0, last = 0, total = 0;   // barcodes are given out in row order
        trs.forEach(function (tr) {
            var it = item(tr.querySelector('[data-f=item]').value);
            var q = Number(tr.querySelector('[data-f=qty]').value) || 0, r = Number(tr.querySelector('[data-f=rate]').value) || 0;
            var t = r2(q * r);
            tr.querySelector('[data-f=amt]').textContent = money(t);
            taxable += t;
            if (mode === 'intra') { tax += 2 * r2(t * (it ? it.gst : 0) / 200); }
            else if (mode === 'inter') { tax += r2(t * (it ? it.gst : 0) / 100); }
            tr.querySelector('[data-f=del]').style.display = trs.length > 1 ? '' : 'none';
            // brand tags: one for each barcoded piece
            var tg = tr.querySelector('[data-f=tags]'), tn = tr.querySelector('[data-f=tagn]'), bcy = tr.querySelector('[data-f=bc]').value === 'yes';
            var tcount = tg.value.split(/[,;\n]+/).filter(function (x) { return x.trim() !== ''; }).length;
            tg.disabled = false;   // always typeable; for a "No barcode" row the tags are kept as a note on the line
            tn.textContent = bcy && it ? tcount + ' of ' + Math.floor(q) + ' tags' : (tcount ? tcount + ' tags (note)' : '');
            tn.style.color = bcy && it && tcount !== Math.floor(q) ? '#B3261E' : '';
            // the barcodes this row will get when the purchase is saved
            var cell = tr.querySelector('[data-f=codes]'), n = Math.floor(q);
            if (it && tr.querySelector('[data-f=bc]').value === 'yes' && n > 0 && code) {
                var a = code, z = code + n - 1; code += n; total += n; if (!first) { first = a; } last = z;
                cell.textContent = n <= 4 ? range(a, z).join(', ') : C.prefix + a + ' to ' + C.prefix + z + ' (' + n + ')';
            } else { cell.textContent = it ? (tr.querySelector('[data-f=bc]').value === 'yes' ? '' : 'No barcode') : ''; }
        });
        var sum = document.getElementById('codesum');
        if (sum) { sum.textContent = total ? 'Barcodes that will be made: ' + C.prefix + first + ' to ' + C.prefix + last + ' (' + total + ' pieces)' : 'No barcodes will be made yet.'; }
        taxable = r2(taxable); tax = r2(tax);
        document.getElementById('t-taxable').textContent = money(taxable);
        document.getElementById('t-tax').textContent = money(tax);
        document.getElementById('t-total').textContent = money(taxable + tax);
    }

    rows.addEventListener('change', function (e) {
        if (e.target.getAttribute('data-f') === 'item') {
            // Choosing an item sets "With barcode" or "No barcode" and the config, like the prototype.
            var tr = e.target.closest('tr'), it = item(e.target.value);
            if (it) {
                tr.querySelector('[data-f=bc]').value = it.tracked ? 'yes' : 'no';
                var cfg = tr.querySelector('input[name$="[config]"]');
                if (!cfg.value) { cfg.value = it.name; }
                var h = tr.querySelector('[data-f=hsn]');
                if (h) { h.value = it.hsn || ''; }   // starts as the item's HSN, can be corrected on this purchase
            }
        }
        refresh();
    });
    rows.addEventListener('input', refresh);
    rows.addEventListener('click', function (e) {
        if (e.target.getAttribute('data-f') === 'del') { e.target.closest('tr').remove(); refresh(); }
    });
    document.getElementById('pf').addEventListener('submit', function (e) {
        var bad = '';
        rows.querySelectorAll('tr').forEach(function (tr) {
            var it = item(tr.querySelector('[data-f=item]').value), q = Math.floor(Number(tr.querySelector('[data-f=qty]').value) || 0);
            if (!bad && it && q > 0 && tr.querySelector('[data-f=bc]').value === 'yes') {
                var n = tr.querySelector('[data-f=tags]').value.split(/[,;\n]+/).filter(function (x) { return x.trim() !== ''; }).length;
                if (n !== q) { bad = it.name + ' needs ' + q + ' brand tag' + (q === 1 ? '' : 's') + ' (one for each piece, separated by commas). You entered ' + n + '.'; }
            }
        });
        if (bad) { e.preventDefault(); alert(bad); }
    });
    /* ---- bulk: tags for one row from a file, or many rows from a file ---- */
    var pick = document.getElementById('pickfile'), pickMode = null, pickRow = null;
    function uploadTo(file, done) {
        var fd = new FormData(), tok = document.querySelector('#pf input[name=_token]');
        fd.append('_token', tok ? tok.value : ''); fd.append('file', file);
        fetch(C.importUrl, { method: 'POST', body: fd, headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
            .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
            .then(function (x) { if (!x.ok || !x.j.ok) { alert((x.j && (x.j.error || x.j.message)) || 'The file could not be read.'); return; } done(x.j); })
            .catch(function () { alert('The file could not be read. Check your connection and try again.'); });
    }
    function emptyRow(tr) { return !tr.querySelector('[data-f=item]').value && !tr.querySelector('[data-f=tags]').value.trim(); }
    if (pick) {
        pick.addEventListener('change', function () {
            var f = pick.files[0]; pick.value = '';
            if (!f) { return; }
            uploadTo(f, function (j) {
                if (pickMode === 'rows') {
                    rows.querySelectorAll('tr').forEach(function (tr) { if (emptyRow(tr)) { tr.remove(); } });
                    j.lines.forEach(function (l) { addRow(l); });
                    var m = j.rows + ' pieces read into ' + j.lines.length + ' row' + (j.lines.length === 1 ? '' : 's') + '.';
                    if (j.unknown_count) { m += '\n\nNot in the Item master, skipped (' + j.unknown_count + '): ' + j.unknown.join(', ') + (j.unknown_count > j.unknown.length ? ' ...' : '') + '\nAdd them under Masters > Item and import again.'; }
                    if (j.missing_tags) { m += '\n\n' + j.missing_tags + ' barcoded piece' + (j.missing_tags === 1 ? ' has' : 's have') + ' no brand tag. Fill the tags before saving.'; }
                    alert(m);
                    refresh();
                } else if (pickRow) {
                    // tags only: every tag of the file (first column "Brand Tag", or the first column)
                    var tags = j.lines.reduce(function (a, l) { return a.concat(l.tags ? l.tags.split(', ') : []); }, []);
                    if (!tags.length) { alert('No brand tags were found in the file. The file needs a "Brand Tag" column and a "Model/Product" column.'); return; }
                    pickRow.querySelector('[data-f=tags]').value = tags.join(', ');
                    if (pickRow.querySelector('[data-f=bc]').value === 'yes') { pickRow.querySelector('[data-f=qty]').value = tags.length; }
                    refresh();
                }
            });
        });
    }
    rows.addEventListener('click', function (e) {
        if (e.target.getAttribute('data-f') === 'tagfile') { pickMode = 'tags'; pickRow = e.target.closest('tr'); pick.click(); }
    });
    var bulk = document.getElementById('bulkrows');
    if (bulk) { bulk.addEventListener('click', function () { pickMode = 'rows'; pickRow = null; pick.click(); }); }
    document.getElementById('addline').addEventListener('click', function () { addRow(); });
    document.getElementById('supplier').addEventListener('change', refresh);
    document.getElementById('type').addEventListener('change', refresh);
    /* ---- add a new item (and brand or category) without leaving the purchase ---- */
    var qi = document.getElementById('qi');
    function qerr(m) { var b = document.getElementById('qi-err'); b.textContent = m || ''; b.hidden = !m; }
    function qv(id) { return document.getElementById(id).value; }
    document.getElementById('newitem').addEventListener('click', function () { qi.hidden = false; qerr(''); document.getElementById('qi-name').focus(); });
    document.getElementById('qi-cancel').addEventListener('click', function () { qi.hidden = true; });
    document.getElementById('qi-save').addEventListener('click', function () {
        if (!qv('qi-name').trim()) { qerr('Please fill in Item name.'); return; }
        var fd = new FormData(document.getElementById('pf')), body = new FormData();
        body.append('_token', fd.get('_token'));
        body.append('name', qv('qi-name')); body.append('brand_id', qv('qi-brand')); body.append('brand_new', qv('qi-brand-new'));
        body.append('category_id', qv('qi-cat')); body.append('category_new', qv('qi-cat-new')); body.append('kind', qv('qi-kind'));
        body.append('hsn', qv('qi-hsn')); body.append('gst', qv('qi-gst'));
        fetch(C.quickUrl, { method: 'POST', body: body, headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
            .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
            .then(function (x) {
                if (!x.ok || !x.j.ok) { qerr((x.j && (x.j.error || x.j.message)) || 'The item could not be added.'); return; }
                var it = x.j.item;
                C.items.push(it);
                rows.querySelectorAll('[data-f=item]').forEach(function (sel) { var o = document.createElement('option'); o.value = it.id; o.textContent = it.name; sel.appendChild(o); });
                var target = null;
                rows.querySelectorAll('tr').forEach(function (tr) { if (!target && !tr.querySelector('[data-f=item]').value) { target = tr; } });
                if (!target) { addRow(); target = rows.lastElementChild; }
                var s = target.querySelector('[data-f=item]');
                s.value = it.id; s.dispatchEvent(new Event('change', { bubbles: true }));
                ['qi-name', 'qi-brand-new', 'qi-cat-new'].forEach(function (id) { document.getElementById(id).value = ''; });
                document.getElementById('qi-brand').value = ''; document.getElementById('qi-cat').value = '';
                qi.hidden = true;
            })
            .catch(function () { qerr('The item could not be added. Check your connection and try again.'); });
    });

    C.lines.forEach(function (l) { addRow(l); });
})();
