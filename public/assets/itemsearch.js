/* Search box above every Item drop-down of the purchase screens: type part of a name / model and the list narrows to the matches.
   The drop-down stays the real field (so saving and the totals work as before); this only filters its choices. */
(function () {
    var SEL = 'select[data-f=item], select[name$="[item_id]"]';
    function norm(s) { return String(s || '').toLowerCase().replace(/\s+/g, ' ').trim(); }
    function fire(el) { el.dispatchEvent(new Event('change', { bubbles: true })); }
    function enhance(sel) {
        if (sel._srch) { return; }
        sel._srch = true;
        var all = Array.prototype.map.call(sel.options, function (o) { return [o.value, o.text]; });
        var box = document.createElement('input');
        box.type = 'search'; box.className = 'cellw'; box.placeholder = 'Search item / model'; box.autocomplete = 'off'; box.setAttribute('aria-label', 'Search item');
        box.style.cssText = 'display:block;width:100%;margin-bottom:4px';
        var note = document.createElement('small'); note.className = 'hint'; note.style.display = 'block';
        sel.parentNode.insertBefore(box, sel); sel.parentNode.insertBefore(note, sel.nextSibling);
        function show(q) {
            var terms = norm(q).split(' ').filter(Boolean), cur = sel.value, out = [];
            all.forEach(function (o) {
                if (o[0] === '') { return; }
                var t = norm(o[1]);
                if (terms.every(function (w) { return t.indexOf(w) !== -1; })) { out.push(o); }
            });
            var keep = all.filter(function (o) { return o[0] === cur && o[0] !== ''; })[0];
            sel.innerHTML = '';
            var ph = all.filter(function (o) { return o[0] === ''; })[0] || ['', 'Select item'];
            sel.add(new Option(ph[1], ph[0]));
            if (keep && !out.some(function (o) { return o[0] === keep[0]; })) { out.unshift(keep); }
            out.forEach(function (o) { sel.add(new Option(o[1], o[0])); });
            sel.value = cur;
            note.textContent = terms.length ? (out.length ? out.length + ' found' : 'No item matches') : '';
            return out;
        }
        box.addEventListener('input', function () {
            var out = show(box.value);
            if (norm(box.value) && out.length === 1 && sel.value !== out[0][0]) { sel.value = out[0][0]; fire(sel); }   // one match: pick it
        });
        box.addEventListener('keydown', function (e) {
            if (e.key !== 'Enter') { return; }
            e.preventDefault();
            var out = show(box.value);
            if (out.length) { sel.value = out[0][0]; fire(sel); box.value = ''; show(''); sel.value = out[0][0]; }
        });
        sel.addEventListener('change', function () { if (sel.value) { box.value = ''; show(''); sel.value = sel.value; note.textContent = ''; } });
    }
    function scan(root) { (root.querySelectorAll ? root.querySelectorAll(SEL) : []).forEach(enhance); }
    scan(document);
    new MutationObserver(function (list) {
        list.forEach(function (m) { m.addedNodes.forEach(function (n) { if (n.nodeType === 1) { if (n.matches && n.matches(SEL)) { enhance(n); } scan(n); } }); });
    }).observe(document.body, { childList: true, subtree: true });
})();
