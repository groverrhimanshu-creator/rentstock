/* Pop-up messages (SweetAlert2): saved, deleted, cannot delete, and the "are you sure" question before a delete. */
(function () {
    var S = window.Swal;
    var theme = { confirmButtonColor: '#0A7EC2', cancelButtonColor: '#6B7385', width: 520 };

    var f = document.getElementById('flash');
    if (f) {
        var ok = f.getAttribute('data-ok'), bad = f.getAttribute('data-bad');
        if (bad) {
            if (S) { S.fire(Object.assign({}, theme, { icon: 'error', title: 'Cannot do that', text: bad, confirmButtonText: 'OK' })); } else { alert(bad); }
        } else if (ok) {
            if (S) { S.fire(Object.assign({}, theme, { icon: 'success', title: ok, timer: 2800, timerProgressBar: true, confirmButtonText: 'OK' })); }
            else { var t = document.createElement('div'); t.className = 'toast'; t.textContent = ok; document.body.appendChild(t); setTimeout(function () { t.remove(); }, 2600); }
        }
    }

    /* Any form with class "js-delete" asks first. data-blocked = the reason it cannot be deleted (shown at once), data-confirm = the question. */
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!form.classList || !form.classList.contains('js-delete')) { return; }
        e.preventDefault();
        var blocked = form.getAttribute('data-blocked'), question = form.getAttribute('data-confirm') || 'Delete this record? This cannot be undone.';
        if (blocked) {
            if (S) { S.fire(Object.assign({}, theme, { icon: 'warning', title: 'Cannot delete', text: blocked, confirmButtonText: 'OK' })); } else { alert(blocked); }
            return;
        }
        if (!S) { if (confirm(question)) { HTMLFormElement.prototype.submit.call(form); } return; }
        S.fire(Object.assign({}, theme, {
            icon: 'warning', title: 'Are you sure?', text: question, showCancelButton: true, reverseButtons: true, focusCancel: true,
            confirmButtonText: 'Yes, delete', cancelButtonText: 'Cancel', confirmButtonColor: '#D64545'
        })).then(function (r) { if (r.isConfirmed) { HTMLFormElement.prototype.submit.call(form); } });
    });
})();
