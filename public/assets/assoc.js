/* Associated items pop-up on the Brand and Category lists: shows the items and exports them. */
(function () {
    function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
    function table(d) {
        if (!d.items.length) { return '<p class="assoc-none">No items use this ' + esc(d.title.toLowerCase()) + ' yet.</p>'; }
        var rows = d.items.map(function (i, n) {
            return '<tr><td>' + (n + 1) + '</td><td><b>' + esc(i.name) + '</b></td><td>' + esc(i.brand) + '</td><td>' + esc(i.category) + '</td><td class="bc">' + (i.barcode ? esc(i.barcode) : '-') + '</td><td>' + esc(i.tag) + '</td><td>' + esc(i.config) + '</td><td>' + esc(i.status) + '</td><td>' + esc(i.with) + '</td><td>' + esc(i.kind) + '</td><td class="num">' + esc(i.rent) + '</td><td class="num">' + esc(i.sale) + '</td></tr>';
        }).join('');
        return '<div class="assoc-wrap"><table class="assoc"><thead><tr><th>#</th><th>Item</th><th>Brand</th><th>Category</th><th>Barcode</th><th>Brand tag</th><th>Config</th><th>Status</th><th>With</th><th>Kind</th><th class="num">Rent / month</th><th class="num">Sale price</th></tr></thead><tbody>' + rows + '</tbody></table></div>';
    }
    document.addEventListener('click', function (e) {
        var b = e.target.closest('.js-assoc');
        if (!b) { return; }
        e.preventDefault();
        var S = window.Swal;   // looked up now: the library loads after this file
        var exportUrl = b.getAttribute('data-export');
        if (!S) { window.location = exportUrl; return; }
        b.disabled = true;
        fetch(b.getAttribute('data-url'), { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
            .then(function (r) { if (!r.ok) { throw new Error('x'); } return r.json(); })
            .then(function (d) {
                b.disabled = false;
                S.fire({
                    title: 'Items with ' + d.title.toLowerCase() + ' "' + d.name + '" (' + d.items.length + ')',
                    html: table(d), width: 1180, showCancelButton: true, reverseButtons: true, focusConfirm: false,
                    confirmButtonText: 'Export list', cancelButtonText: 'Close', showConfirmButton: d.items.length > 0,
                    confirmButtonColor: '#0090CF', cancelButtonColor: '#6B7385', customClass: { htmlContainer: 'assoc-html' },
                    preConfirm: function () { window.location = exportUrl; return false; }   // download, and keep the pop-up open
                });
            })
            .catch(function () { b.disabled = false; S.fire({ icon: 'error', title: 'Cannot load the items', text: 'Please try again.', confirmButtonText: 'OK' }); });
    });
})();
