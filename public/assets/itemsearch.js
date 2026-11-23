/* Search bars instead of drop-downs on the purchase screens: Item (search by name, model, category or brand), and Category / Brand of a new item.
   The original <select> stays in the page (hidden) as the real field, so saving, totals and bulk import work as before. */
(function () {
    var META = {};
    [window.PURCHASE, window.PEDIT].forEach(function (c) { if (c && c.items) { c.items.forEach(function (i) { META[String(i.id)] = i; }); } });
    var SEL = 'select[data-f=item], select[name$="[item_id]"], #qi-cat, #qi-brand';
    var pop = null, cur = null;   // the one open suggestion list and the box it belongs to
    function norm(s) { return String(s || '').toLowerCase().replace(/\s+/g, ' ').trim(); }
    function esc(s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
    function closePop() { if (pop) { pop.remove(); pop = null; cur = null; } }
    window.addEventListener('scroll', function (e) { if (pop && !pop.contains(e.target)) { closePop(); } }, true);
    window.addEventListener('resize', closePop);

    function enhance(sel) {
        if (sel._combo) { return; }
        sel._combo = true;
        var isItem = sel.id !== 'qi-cat' && sel.id !== 'qi-brand', what = sel.id === 'qi-cat' ? 'category' : (sel.id === 'qi-brand' ? 'brand' : 'item / model / category');
        var box = document.createElement('input');
        box.type = 'text'; box.autocomplete = 'off'; box.placeholder = 'Search ' + what; box.setAttribute('aria-label', 'Search ' + what);
        box.className = sel.className || 'cellw'; box.style.width = '100%'; box.style.minWidth = '200px';
        sel.style.display = 'none';
        sel.parentNode.insertBefore(box, sel);
        var hi = 0, list = [];

        function chosenText() { var o = sel.options[sel.selectedIndex]; return sel.value && o ? o.text : ''; }
        function sync() { if (document.activeElement !== box) { box.value = chosenText(); } }
        function matches(q) {
            var terms = norm(q).split(' ').filter(Boolean), out = [];
            Array.prototype.forEach.call(sel.options, function (o) {
                if (o.value === '') { return; }
                var m = isItem ? META[o.value] : null, hay = norm(o.text + ' ' + (m ? (m.cat || '') + ' ' + (m.brand || '') : ''));
                if (terms.every(function (w) { return hay.indexOf(w) !== -1; })) { out.push({ v: o.value, t: o.text, m: m }); }
            });
            return out.slice(0, 80);
        }
        function pick(v) {
            sel.value = v; box.value = chosenText(); closePop();
            sel.dispatchEvent(new Event('change', { bubbles: true }));
        }
        function draw() {
            list = matches(box.value);
            if (!pop) {
                pop = document.createElement('div'); cur = box;
                pop.style.cssText = 'position:fixed;z-index:9999;background:var(--a-card,#fff);border:1px solid var(--a-line,#ccd);border-radius:8px;box-shadow:0 8px 24px rgba(0,0,0,.18);max-height:260px;overflow:auto;font-size:13px';
                document.body.appendChild(pop);
                pop.addEventListener('mousedown', function (e) { e.preventDefault(); });   // keep the focus in the box while clicking
                pop.addEventListener('click', function (e) { var r = e.target.closest('[data-v]'); if (r) { pick(r.getAttribute('data-v')); } });
            }
            var r = box.getBoundingClientRect(), below = window.innerHeight - r.bottom;
            pop.style.left = r.left + 'px'; pop.style.minWidth = Math.max(r.width, 280) + 'px';
            if (below < 180 && r.top > below) { pop.style.top = ''; pop.style.bottom = (window.innerHeight - r.top + 2) + 'px'; } else { pop.style.bottom = ''; pop.style.top = (r.bottom + 2) + 'px'; }
            hi = Math.min(hi, Math.max(list.length - 1, 0));
            pop.innerHTML = list.length ? list.map(function (x, i) {
                var sub = x.m ? [x.m.cat, x.m.brand].filter(Boolean).join(' · ') : '';
                return '<div data-v="' + esc(x.v) + '" data-i="' + i + '" style="padding:7px 10px;cursor:pointer;' + (i === hi ? 'background:rgba(10,126,194,.14)' : '') + '"><b>' + esc(x.t) + '</b>' + (sub ? '<br><span style="opacity:.65;font-size:12px">' + esc(sub) + '</span>' : '') + '</div>';
            }).join('') : '<div style="padding:8px 10px;opacity:.7">No ' + what.split(' ')[0] + ' matches</div>';
        }
        box.addEventListener('focus', function () { box.select(); hi = 0; draw(); });
        box.addEventListener('input', function () { hi = 0; draw(); });
        box.addEventListener('keydown', function (e) {
            if (e.key === 'ArrowDown' || e.key === 'ArrowUp') { e.preventDefault(); if (!pop) { draw(); } hi = Math.max(0, Math.min(list.length - 1, hi + (e.key === 'ArrowDown' ? 1 : -1))); draw(); var el = pop && pop.querySelector('[data-i="' + hi + '"]'); if (el) { el.scrollIntoView({ block: 'nearest' }); } }
            else if (e.key === 'Enter') { e.preventDefault(); if (pop && list[hi]) { pick(list[hi].v); } }
            else if (e.key === 'Escape') { closePop(); box.value = chosenText(); }
        });
        box.addEventListener('blur', function () {
            setTimeout(function () {
                if (cur === box) { closePop(); }
                if (!norm(box.value) && sel.value) { sel.value = ''; sel.dispatchEvent(new Event('change', { bubbles: true })); }   // box emptied: clear the choice
                box.value = chosenText();
            }, 120);
        });
        sync();
        setInterval(sync, 700);   // follows changes made by the page itself (a bulk import, a newly added item)
    }
    function scan(root) { if (root.querySelectorAll) { root.querySelectorAll(SEL).forEach(enhance); } }
    scan(document);
    new MutationObserver(function (list) {
        list.forEach(function (m) { m.addedNodes.forEach(function (n) { if (n.nodeType === 1) { if (n.matches && n.matches(SEL)) { enhance(n); } scan(n); } }); });
    }).observe(document.body, { childList: true, subtree: true });
})();
