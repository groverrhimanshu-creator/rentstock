/* Edit purchase: live GST %, amount and Taxable / GST / Total, the same working as the New purchase screen. Display only; the server works out everything again on save. */
(function () {
    var C = window.PEDIT, form = document.querySelector('form[action*="/purchase/"]');
    if (!C || !form) { return; }
    function r2(x) { return Math.round((x + Number.EPSILON) * 100) / 100; }
    function money(x) { return Number(x).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
    function item(id) { for (var i = 0; i < C.items.length; i++) { if (String(C.items[i].id) === String(id)) { return C.items[i]; } } return null; }
    function calc() {
        var type = document.getElementById('type').value, sup = document.getElementById('supplier'), st = sup.options[sup.selectedIndex] ? sup.options[sup.selectedIndex].getAttribute('data-state') : '';
        var mode = type !== 'GST' ? 'none' : (!st || !C.companyState ? 'unknown' : (st === C.companyState ? 'intra' : 'inter'));
        var taxable = 0, tax = 0;
        function add(it, q, r) {
            var t = r2(q * r); taxable += t;
            if (mode === 'intra') { tax += 2 * r2(t * (it ? it.gst : 0) / 200); } else if (mode === 'inter' || mode === 'unknown') { tax += r2(t * (it ? it.gst : 0) / 100); }
            return t;
        }
        form.querySelectorAll('tr[data-line]').forEach(function (tr) {
            var it = item(tr.querySelector('select[name$="[item_id]"]').value), q = Math.floor(Number(tr.querySelector('input[name$="[qty]"]').value) || 0), r = Number(tr.querySelector('input[name$="[rate]"]').value) || 0;
            var gone = tr.querySelector('input[name="remove[]"]'); gone = gone && gone.checked;
            tr.querySelector('[data-f=gstp]').textContent = it ? (mode === 'none' ? '0' : String(it.gst)) : '-';
            tr.querySelector('[data-f=amt]').textContent = gone ? 'removed' : money(r2(q * r));
            tr.style.opacity = gone ? '.5' : '';
            if (!gone) { add(it, q, r); }
        });
        var rows = document.getElementById('addrows');
        if (rows) { rows.querySelectorAll('tr').forEach(function (tr) {
            var it = item(tr.querySelector('[data-f=item]').value), q = Math.floor(Number(tr.querySelector('[data-f=qty]').value) || 0), r = Number(tr.querySelector('input[name$="[rate]"]').value) || 0;
            if (it) { add(it, q, r); }
        }); }
        taxable = r2(taxable); tax = r2(tax);
        document.getElementById('t-taxable').textContent = money(taxable);
        document.getElementById('t-taxlabel').textContent = mode === 'intra' ? 'GST (CGST + SGST)' : (mode === 'inter' ? 'GST (IGST)' : 'GST');
        document.getElementById('t-tax').textContent = money(tax);
        document.getElementById('t-total').textContent = money(taxable + tax);
        document.getElementById('t-note').textContent = mode === 'none' ? 'Bill of supply: no GST is charged.' : (mode === 'unknown' ? 'The supplier or company state is not set, so GST is shown at the full item rate.' : '');
    }
    form.addEventListener('input', calc); form.addEventListener('change', calc); form.addEventListener('click', function () { setTimeout(calc, 0); });
    calc();
})();
