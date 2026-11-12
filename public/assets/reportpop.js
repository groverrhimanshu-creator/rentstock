/* Reports: a count that opens a pop-up with the items behind it (items on rent for a customer, lines of a purchase). */
(function () {
    function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
    function wrap(head, rows, none) {
        if (!rows.length) { return '<p class="assoc-none">' + esc(none) + '</p>'; }
        return '<div class="assoc-wrap"><table class="assoc"><thead><tr>' + head + '</tr></thead><tbody>' + rows.join('') + '</tbody></table></div>';
    }
    var kinds = {
        onrent: {
            title: function (d) { return 'Items on rent: ' + d.name + ' (' + d.items.length + ')'; },
            html: function (d) {
                return wrap('<th>#</th><th>Barcode</th><th>Item</th><th>Category</th><th>Brand tag</th><th>Config</th><th>Challan</th><th>On rent since</th><th>Return by / returned</th><th class="num">Rent / month</th>',
                    d.items.map(function (i, n) {
                        return '<tr><td>' + (n + 1) + '</td><td class="bc">' + esc(i.barcode) + '</td><td><b>' + esc(i.item) + '</b></td><td>' + esc(i.category) + '</td><td>' + esc(i.tag) + '</td><td>' + esc(i.config) + '</td><td>' + esc(i.challan) + '</td><td>' + esc(i.since) + '</td><td>' + esc(i.due) + '</td><td class="num">' + esc(i.rent) + '</td></tr>';
                    }), 'Nothing was on rent with this customer in these dates.');
            }
        },
        purchase: {
            title: function (d) { return 'Purchase ' + (d.inv_no || '(no invoice no)') + ' · ' + d.supplier + ' · ' + d.date + ' (' + d.items.length + ' lines)'; },
            html: function (d) {
                return wrap('<th>#</th><th>Item</th><th>Config</th><th class="num">Qty</th><th class="num">Rate</th><th class="num">Amount</th><th class="num">GST</th><th>Kind</th><th>Barcodes made</th>',
                    d.items.map(function (i, n) {
                        return '<tr><td>' + (n + 1) + '</td><td><b>' + esc(i.item) + '</b></td><td>' + esc(i.config) + '</td><td class="num">' + esc(i.qty) + '</td><td class="num">' + esc(i.rate) + '</td><td class="num">' + esc(i.amount) + '</td><td class="num">' + esc(i.gst) + '</td><td>' + esc(i.kind) + '</td><td class="bc">' + esc(i.barcodes) + '</td></tr>';
                    }), 'This purchase has no lines.');
            }
        }
    };
    document.addEventListener('click', function (e) {
        var b = e.target.closest('.js-rpop');
        if (!b) { return; }
        e.preventDefault();
        var S = window.Swal, k = kinds[b.getAttribute('data-kind')], exportUrl = b.getAttribute('data-export');
        if (!S || !k) { window.location = exportUrl; return; }
        b.disabled = true;
        fetch(b.getAttribute('data-url'), { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
            .then(function (r) { if (!r.ok) { throw new Error('x'); } return r.json(); })
            .then(function (d) {
                b.disabled = false;
                S.fire({
                    title: k.title(d), html: k.html(d), width: 1180, showCancelButton: true, reverseButtons: true, focusConfirm: false,
                    confirmButtonText: 'Export list', cancelButtonText: 'Close', showConfirmButton: d.items.length > 0,
                    confirmButtonColor: '#0090CF', cancelButtonColor: '#6B7385', customClass: { htmlContainer: 'assoc-html' },
                    preConfirm: function () { window.location = exportUrl; return false; }   // download, and keep the pop-up open
                });
            })
            .catch(function () { b.disabled = false; S.fire({ icon: 'error', title: 'Cannot load the items', text: 'Please try again.', confirmButtonText: 'OK' }); });
    });
})();
