/* Checks a form in the browser before it is sent. The server checks the same rules again. */
(function () {
    var CHECKS = {
        gstin:    [/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/, 'is not a valid GSTIN (15 characters, like 03AAAAA0000A1Z5)'],
        phone:    [/^[0-9+\-\s()]{6,30}$/, 'can contain only digits, spaces, + and -'],
        email:    [/^[^\s@]+@[^\s@]+\.[^\s@]+$/, 'is not a valid email address'],
        digits:   [/^[0-9]+$/, 'must be digits only'],
        ifsc:     [/^[A-Z]{4}0[A-Z0-9]{6}$/, 'is not a valid IFSC (like HDFC0001234)'],
        pan:      [/^[A-Z]{5}[0-9]{4}[A-Z]$/, 'is not a valid PAN (like ABCDE1234F)'],
        prefix:   [/^[A-Z0-9]{1,10}$/, 'can have only capital letters and digits, up to 10'],
        username: [/^[A-Za-z0-9._-]{3,40}$/, 'must be 3 to 40 letters, digits, dots, dashes or underscores'],
        password8:[/^.{8,100}$/, 'must be at least 8 characters']
    };

    function problem(el) {
        if (el.disabled) return null;   // fields that are switched off (for example the optional customer login) are not checked
        var label = el.getAttribute('data-label') || el.name, v = (el.value || '').trim();
        if (el.hasAttribute('data-upper')) v = v.toUpperCase();
        if (v === '') return el.hasAttribute('data-req') ? 'Please fill in ' + label + '.' : null;
        var kind = el.getAttribute('data-check');
        if (kind && CHECKS[kind] && !CHECKS[kind][0].test(v)) return label + ' ' + CHECKS[kind][1] + '.';
        if (el.hasAttribute('data-num')) {
            var n = Number(v), lo = Number(el.getAttribute('data-min')), hi = Number(el.getAttribute('data-max'));
            if (isNaN(n)) return label + ' must be a number.';
            if (n < lo || n > hi) return label + ' must be between ' + lo + ' and ' + hi + '.';
        }
        return null;
    }

    document.querySelectorAll('form[data-validate]').forEach(function (form) {
        var box = form.querySelector('[data-errbox]');
        form.setAttribute('novalidate', '');
        form.addEventListener('submit', function (e) {
            var errs = [], first = null;
            form.querySelectorAll('[data-label]').forEach(function (el) {
                var msg = problem(el);
                el.removeAttribute('aria-invalid');
                if (msg) { errs.push(msg); el.setAttribute('aria-invalid', 'true'); el.style.borderColor = '#B3261E'; if (!first) first = el; }
                else el.style.borderColor = '';
            });
            if (!errs.length) { if (box) box.hidden = true; return; }
            e.preventDefault();
            if (box) {
                box.innerHTML = '';
                if (errs.length === 1) box.textContent = errs[0];
                else { var ul = document.createElement('ul'); ul.style.cssText = 'margin:0;padding-left:18px'; errs.forEach(function (m) { var li = document.createElement('li'); li.textContent = m; ul.appendChild(li); }); box.appendChild(ul); }
                box.hidden = false;
            }
            if (first) first.focus();
        });
        form.addEventListener('input', function (e) {
            if (e.target.hasAttribute && e.target.hasAttribute('data-label')) { e.target.style.borderColor = ''; e.target.removeAttribute('aria-invalid'); }
        });
    });
})();
