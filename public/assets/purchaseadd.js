/* Edit purchase: add more items to a saved purchase. The server checks everything again on save. */
(function () {
    var C = window.PEDIT, rows = document.getElementById('addrows'), box = document.getElementById('addbox'), next = 0;
    function esc(s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
    function item(id) { for (var i = 0; i < C.items.length; i++) { if (String(C.items[i].id) === String(id)) { return C.items[i]; } } return null; }
    function count(tr) { return tr.querySelector('[data-f=tags]').value.split(/[,;\n]+/).filter(function (x) { return x.trim() !== ''; }).length; }
    function note(tr) {
        var bc = tr.querySelector('[data-f=bc]').value === 'yes', q = Math.floor(Number(tr.querySelector('[data-f=qty]').value) || 0), n = count(tr), t = tr.querySelector('[data-f=tagn]');
        t.textContent = bc ? n + ' of ' + q + ' tags' : (n ? n + ' tags (note)' : '');
        t.style.color = bc && n !== q ? '#B3261E' : '';
    }
    document.getElementById('addnew').addEventListener('click', function () {
        var i = next++, tr = document.createElement('tr');
        tr.innerHTML =
            '<td><select name="add[' + i + '][item_id]" data-f="item"><option value="">Select item</option>' + C.items.map(function (x) { return '<option value="' + x.id + '">' + esc(x.name) + '</option>'; }).join('') + '</select></td>' +
            '<td><input class="cellw" name="add[' + i + '][config]" maxlength="150" placeholder="Config"></td>' +
            '<td><input class="cell" name="add[' + i + '][hsn]" data-f="hsn" maxlength="10" inputmode="numeric" placeholder="HSN"></td>' +
            '<td><select name="add[' + i + '][bc]" data-f="bc"><option value="yes">With barcode</option><option value="no">No barcode (count only)</option></select></td>' +
            '<td><input class="cell" type="number" min="1" max="5000" step="1" name="add[' + i + '][qty]" data-f="qty" value="1"></td>' +
            '<td><textarea class="cellw" rows="2" style="min-width:180px" name="add[' + i + '][tags]" data-f="tags" placeholder="One tag per piece, separated by commas or new lines"></textarea><small class="hint" data-f="tagn"></small></td>' +
            '<td><input class="cell" type="number" min="0" step="0.01" name="add[' + i + '][rate]" value=""></td>' +
            '<td><button type="button" class="ab sm danger" data-f="del">Remove</button></td>';
        rows.appendChild(tr); box.hidden = false; note(tr);
    });
    rows.addEventListener('change', function (e) {
        var tr = e.target.closest('tr');
        if (e.target.getAttribute('data-f') === 'item') {
            var it = item(e.target.value);
            if (it) { tr.querySelector('[data-f=bc]').value = it.tracked ? 'yes' : 'no'; tr.querySelector('[data-f=hsn]').value = it.hsn || ''; var cfg = tr.querySelector('input[name$="[config]"]'); if (!cfg.value) { cfg.value = it.name; } }
        }
        note(tr);
    });
    rows.addEventListener('input', function (e) { note(e.target.closest('tr')); });
    rows.addEventListener('click', function (e) { if (e.target.getAttribute('data-f') === 'del') { e.target.closest('tr').remove(); box.hidden = !rows.children.length; } });
    document.querySelector('form[action*="/purchase/"]').addEventListener('submit', function (e) {
        var bad = '';
        rows.querySelectorAll('tr').forEach(function (tr) {
            var it = item(tr.querySelector('[data-f=item]').value), q = Math.floor(Number(tr.querySelector('[data-f=qty]').value) || 0);
            if (!bad && it && q > 0 && tr.querySelector('[data-f=bc]').value === 'yes' && count(tr) !== q) { bad = it.name + ' needs ' + q + ' brand tag' + (q === 1 ? '' : 's') + ' (one for each piece, separated by commas). You entered ' + count(tr) + '.'; }
        });
        if (bad) { e.preventDefault(); var b = document.getElementById('clienterr'); b.textContent = bad; b.hidden = false; }
    });
})();
