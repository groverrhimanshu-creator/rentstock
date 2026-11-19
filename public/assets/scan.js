/* Scan page: camera (BarcodeDetector where the phone has it, otherwise ZXing), typed number, or Bluetooth scanner. */
(function () {
    var $ = function (id) { return document.getElementById(id); };
    var vid = $('scanvid'), box = $('scanbox'), msg = $('scanmsg'), res = $('scanres'), hist = $('scanhist');
    var stream = null, timer = null, zx = null, last = '', lastAt = 0, detector = null, recent = [];
    try { recent = JSON.parse(localStorage.getItem('rs_scan') || '[]'); } catch (e) { recent = []; }

    function esc(s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
    function card(i) {
        var l = [];
        if (i.customer) l.push('Customer: ' + esc(i.customer) + (i.rent_no ? ' · ' + esc(i.rent_no) : ''));
        if (i.store) l.push('Store: ' + esc(i.store));
        if (i.config) l.push('Config: ' + esc(i.config));
        if (i.tag) l.push('Brand tag: ' + esc(i.tag));
        return '<a class="sr" href="' + esc(i.url) + '"><b>' + esc(i.model) + '</b><small>' + esc(i.text) + ' · <span class="pill ' + esc(i.tone) + '">' + esc(i.status) + '</span></small>' +
            (l.length ? '<small>' + l.join('<br>') + '</small>' : '') + '<small style="margin-top:6px;color:var(--a-link)">Open details →</small></a>';
    }
    function drawHist() {
        hist.innerHTML = recent.length ? recent.map(function (r) { return '<a class="sr" href="' + esc(r.url) + '"><b>' + esc(r.text) + ' · ' + esc(r.model) + '</b><small>' + esc(r.status) + '</small></a>'; }).join('') : '<p class="hint">None yet.</p>';
    }
    function find(code) {
        code = String(code || '').trim();
        if (!code) return;
        res.innerHTML = '<p class="hint">Looking up ' + esc(code) + '…</p>';
        var fd = new FormData(); fd.append('code', code); fd.append('_token', window.SCAN_TOKEN);
        fetch(window.SCAN_URL, { method: 'POST', body: fd, headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
            .then(function (r) { if (!r.ok) throw new Error(r.status); return r.json(); })
            .then(function (j) {
                if (!j.ok) { res.innerHTML = '<div class="sr bad"><b>' + esc(j.msg) + '</b></div>'; return; }
                res.innerHTML = card(j.item);
                recent = [j.item].concat(recent.filter(function (r) { return r.text !== j.item.text; })).slice(0, 8);
                try { localStorage.setItem('rs_scan', JSON.stringify(recent)); } catch (e) {}
                drawHist();
                if (navigator.vibrate) navigator.vibrate(60);
            })
            .catch(function () { res.innerHTML = '<div class="sr bad"><b>Could not reach the server. Check the internet and scan again.</b></div>'; });
    }
    function got(code) {
        var now = Date.now();
        if (code === last && now - lastAt < 2500) return;
        last = code; lastAt = now; $('scancode').value = code; find(code);
    }
    function stop() {
        if (timer) { clearInterval(timer); timer = null; }
        if (zx) { try { zx.reset(); } catch (e) {} zx = null; }
        if (stream) { stream.getTracks().forEach(function (t) { t.stop(); }); stream = null; }
        vid.srcObject = null; box.hidden = true;
        $('scango').hidden = false; $('scanstop').hidden = true; $('scantorch').hidden = true; msg.textContent = 'Camera is off.';
    }
    function start() {
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) { msg.textContent = 'This browser cannot open the camera here. Type the barcode instead.'; return; }
        msg.textContent = 'Opening camera…';
        navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: 'environment' }, width: { ideal: 1280 } }, audio: false }).then(function (s) {
            stream = s; vid.srcObject = s; box.hidden = false; vid.play();
            $('scango').hidden = true; $('scanstop').hidden = false; msg.textContent = 'Hold the barcode inside the frame.';
            var tr = s.getVideoTracks()[0], cap = tr.getCapabilities ? tr.getCapabilities() : {};
            if (cap.torch) { var tb = $('scantorch'), on = false; tb.hidden = false; tb.onclick = function () { on = !on; tr.applyConstraints({ advanced: [{ torch: on }] }).catch(function () {}); }; }
            if (detector) {
                timer = setInterval(function () {
                    if (vid.readyState < 2) return;
                    detector.detect(vid).then(function (r) { if (r && r.length) got(r[0].rawValue); }).catch(function () {});
                }, 250);
            } else if (window.ZXing) {
                var hints = new Map(); hints.set(ZXing.DecodeHintType.POSSIBLE_FORMATS, [ZXing.BarcodeFormat.CODE_128, ZXing.BarcodeFormat.CODE_39, ZXing.BarcodeFormat.EAN_13, ZXing.BarcodeFormat.QR_CODE]);
                zx = new ZXing.BrowserMultiFormatReader(hints, 300);
                zx.decodeFromStream(s, vid, function (r) { if (r) got(r.getText()); });
            } else { msg.textContent = 'Scanner is not available. Type the barcode instead.'; }
        }).catch(function (e) {
            msg.textContent = (e && e.name === 'NotAllowedError') ? 'Camera permission was blocked. Allow the camera for this site in the browser settings.' : 'Could not open the camera. Type the barcode instead.';
        });
    }
    if ('BarcodeDetector' in window) {
        try { BarcodeDetector.getSupportedFormats().then(function (f) { if (f.indexOf('code_128') > -1) detector = new BarcodeDetector({ formats: ['code_128', 'code_39', 'ean_13', 'qr_code'] }); }).catch(function () {}); } catch (e) {}
    }
    $('scango').addEventListener('click', start);
    $('scanstop').addEventListener('click', stop);
    $('scanform').addEventListener('submit', function (e) { e.preventDefault(); find($('scancode').value); $('scancode').select(); });
    document.addEventListener('visibilitychange', function () { if (document.hidden) stop(); });
    window.addEventListener('pagehide', stop);
    drawHist();
    if (window.innerWidth > 760) $('scancode').focus();
})();
