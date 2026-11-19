/* Camera scan for the pages where items are picked by barcode (sale, rental, return, replacement, edit challan).
   - An input with data-camscan gets a "Camera" button. Each code read is put in the box and Enter is pressed for you, exactly as a hand scanner does,
     so the page adds the item the same way it does for a typed or hand-scanned barcode. The camera stays open for the next item.
   - A button with data-camscan-select="<select id>" picks that barcode in a drop-down list and closes. */
(function () {
    var me = document.currentScript, ZX = me && me.getAttribute('data-zxing');
    var CAM = '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="vertical-align:-3px;margin-right:5px"><path d="M4 7h3l2-2.5h6L17 7h3v12H4z"/><circle cx="12" cy="13" r="3.6"/></svg>';
    var css = document.createElement('style');
    css.textContent = '.csov{position:fixed;inset:0;z-index:9999;background:rgba(6,6,58,.72);display:flex;align-items:flex-end;justify-content:center}' +
        '.csbox{width:100%;max-width:540px;background:#fff;color:#0A0A3A;border-radius:18px 18px 0 0;padding:14px 14px calc(14px + env(safe-area-inset-bottom));box-shadow:0 -10px 40px rgba(0,0,0,.35);font:13px/1.45 system-ui,sans-serif}' +
        '@media (min-width:700px){.csov{align-items:center}.csbox{border-radius:18px}}' +
        '.csbox h4{margin:0 0 8px;font-size:15px}.csvid{position:relative;background:#000;border-radius:12px;overflow:hidden}' +
        '.csvid video{display:block;width:100%;max-height:46vh;object-fit:cover}' +
        '.csvid u{position:absolute;left:8%;right:8%;top:50%;height:2px;background:#F59E0B;box-shadow:0 0 8px #F59E0B}' +
        '.csst{margin:10px 0;padding:9px 12px;border-radius:10px;background:#E8ECF4;color:#3A4566;font-weight:600}' +
        '.csst.ok{background:#DFF3E7;color:#16794A}.csst.bad{background:#FBE3E0;color:#B3261E}' +
        '.csrow{display:flex;gap:8px;justify-content:flex-end}.csrow button{min-height:42px;padding:0 18px;border-radius:10px;border:1px solid #D5DCEB;background:#fff;color:#0A0A3A;font:700 14px system-ui,sans-serif;cursor:pointer}.csrow button.solid{background:#0090CF;border-color:#0090CF;color:#fff}.csn{flex:1;align-self:center;color:#6C789A;font-weight:600}';
    document.head.appendChild(css);

    function loadZX(cb) {
        if (window.ZXing) { cb(); return; }
        if (!ZX) { cb(); return; }
        var s = document.createElement('script'); s.src = ZX; s.onload = cb; s.onerror = cb; document.head.appendChild(s);
    }
    var detector = null;
    try { if ('BarcodeDetector' in window) { BarcodeDetector.getSupportedFormats().then(function (f) { if (f.indexOf('code_128') > -1) { detector = new BarcodeDetector({ formats: ['code_128', 'code_39', 'ean_13', 'qr_code'] }); } }).catch(function () {}); } } catch (e) {}

    function beep(ok) {
        try { if (navigator.vibrate) { navigator.vibrate(ok ? 60 : [40, 40, 40]); } } catch (e) {}
        try {
            var A = window.AudioContext || window.webkitAudioContext; if (!A) { return; }
            var c = new A(), o = c.createOscillator(), g = c.createGain(); o.connect(g); g.connect(c.destination);
            o.frequency.value = ok ? 880 : 220; g.gain.value = .06; o.start(); setTimeout(function () { o.stop(); c.close(); }, ok ? 110 : 220);
        } catch (e) {}
    }

    /* opts: { title, onCode(code) -> Promise<{ok,msg,close}>, count } */
    function open(opts) {
        var ov = document.createElement('div'); ov.className = 'csov'; ov.setAttribute('role', 'dialog'); ov.setAttribute('aria-modal', 'true');
        ov.innerHTML = '<div class="csbox"><h4></h4><div class="csvid"><video playsinline muted></video><u></u></div><div class="csst">Opening camera…</div><div class="csrow"><span class="csn"></span><button type="button" class="ab" data-t hidden>Torch</button><button type="button" class="ab solid" data-x>Done</button></div></div>';
        ov.querySelector('h4').textContent = opts.title || 'Scan barcode';
        document.body.appendChild(ov);
        var vid = ov.querySelector('video'), st = ov.querySelector('.csst'), nEl = ov.querySelector('.csn'), stream = null, timer = null, zx = null, last = '', lastAt = 0, busy = false, n = 0, closed = false;
        function say(m, k) { st.textContent = m; st.className = 'csst' + (k ? ' ' + k : ''); }
        function stop() {
            closed = true;
            if (timer) { clearInterval(timer); timer = null; }
            if (zx) { try { zx.reset(); } catch (e) {} zx = null; }
            if (stream) { stream.getTracks().forEach(function (t) { t.stop(); }); stream = null; }
            document.removeEventListener('keydown', onKey);
            ov.remove();
        }
        function onKey(e) { if (e.key === 'Escape') { stop(); } }
        document.addEventListener('keydown', onKey);
        ov.querySelector('[data-x]').addEventListener('click', stop);
        ov.addEventListener('click', function (e) { if (e.target === ov) { stop(); } });
        function got(code) {
            var now = Date.now();
            if (busy || closed || (code === last && now - lastAt < 3000)) { return; }
            last = code; lastAt = now; busy = true; say('Reading ' + code + '…');
            Promise.resolve(opts.onCode(code)).then(function (r) {
                if (closed) { return; }
                r = r || {}; beep(!!r.ok); say(r.msg || (r.ok ? 'Done' : 'Not added'), r.ok ? 'ok' : 'bad');
                if (r.ok) { n++; nEl.textContent = n + ' added'; }
                busy = false; if (r.ok && r.close) { setTimeout(stop, 700); }
            }).catch(function () { busy = false; say('Something went wrong. Try again.', 'bad'); });
        }
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) { say('The camera needs a secure (https) page and a browser that allows it. Type or hand-scan the barcode instead.', 'bad'); return; }
        navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: 'environment' }, width: { ideal: 1280 } }, audio: false }).then(function (s) {
            if (closed) { s.getTracks().forEach(function (t) { t.stop(); }); return; }
            stream = s; vid.srcObject = s; vid.play();
            say('Hold the barcode inside the frame.');
            var tr = s.getVideoTracks()[0], cap = tr.getCapabilities ? tr.getCapabilities() : {};
            if (cap.torch) { var tb = ov.querySelector('[data-t]'), on = false; tb.hidden = false; tb.onclick = function () { on = !on; tr.applyConstraints({ advanced: [{ torch: on }] }).catch(function () {}); }; }
            if (detector) {
                timer = setInterval(function () { if (vid.readyState < 2) { return; } detector.detect(vid).then(function (r) { if (r && r.length) { got(r[0].rawValue); } }).catch(function () {}); }, 250);
            } else {
                loadZX(function () {
                    if (closed) { return; }
                    if (!window.ZXing) { say('Scanner could not load. Type the barcode instead.', 'bad'); return; }
                    var h = new Map(); h.set(ZXing.DecodeHintType.POSSIBLE_FORMATS, [ZXing.BarcodeFormat.CODE_128, ZXing.BarcodeFormat.CODE_39, ZXing.BarcodeFormat.EAN_13, ZXing.BarcodeFormat.QR_CODE]);
                    zx = new ZXing.BrowserMultiFormatReader(h, 300);
                    zx.decodeFromStream(s, vid, function (r) { if (r) { got(r.getText()); } });
                });
            }
        }).catch(function (e) {
            say((e && e.name === 'NotAllowedError') ? 'Camera permission was blocked. Allow the camera for this site in the browser settings.' : 'Could not open the camera. Type the barcode instead.', 'bad');
        });
    }

    function wait(ms) { return new Promise(function (r) { setTimeout(r, ms); }); }

    /* An input: put the code in the box and press Enter, then see whether the page took it. */
    function viaInput(inp) {
        var errBox = document.getElementById(inp.getAttribute('data-camscan-err') || 'clienterr');
        return function (code) {
            if (errBox) { errBox.hidden = true; errBox.textContent = ''; }
            inp.value = code;
            inp.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', bubbles: true, cancelable: true }));
            var t0 = Date.now();
            return (function poll() {
                if (inp.value === '') { return Promise.resolve({ ok: true, msg: code + ' added' }); }
                if (errBox && !errBox.hidden && errBox.textContent) { var m = errBox.textContent; inp.value = ''; return Promise.resolve({ ok: false, msg: m }); }
                if (Date.now() - t0 > 3000) { inp.value = ''; return Promise.resolve({ ok: false, msg: code + ' was not added.' }); }
                return wait(150).then(poll);
            })();
        };
    }

    function digits(code) { var m = String(code).replace(/\s+/g, '').match(/(\d{4,9})$/); return m ? m[1] : ''; }

    /* A drop-down list: choose the option that is this barcode. */
    function viaSelect(sel) {
        return function (code) {
            var d = digits(code), o = null;
            if (!d) { return { ok: false, msg: code + ' is not a barcode of this system.' }; }
            if (sel.disabled) { return { ok: false, msg: 'Please choose the earlier fields first.' }; }
            Array.prototype.forEach.call(sel.options, function (x) { if (String(x.value) === String(parseInt(d, 10))) { o = x; } });
            if (!o) { return { ok: false, msg: code + ' is not in this list.' }; }
            sel.value = o.value; sel.dispatchEvent(new Event('change', { bubbles: true }));
            return { ok: true, close: true, msg: o.textContent.trim() + ' selected' };
        };
    }

    function btn(label, small) {
        var b = document.createElement('button'); b.type = 'button'; b.className = 'ab' + (small ? ' sm' : ''); b.setAttribute('data-camscan-btn', '');
        b.innerHTML = CAM + label; return b;
    }
    function init() {
        Array.prototype.forEach.call(document.querySelectorAll('input[data-camscan]'), function (inp) {
            var b = btn('Camera'); b.style.marginLeft = '8px'; b.style.marginTop = inp.closest('.rowx') ? '0' : '6px';
            b.addEventListener('click', function () { open({ title: inp.getAttribute('data-camscan') || 'Scan each item', onCode: viaInput(inp) }); });
            inp.insertAdjacentElement('afterend', b);
        });
        Array.prototype.forEach.call(document.querySelectorAll('[data-camscan-select]'), function (b) {
            var sel = document.getElementById(b.getAttribute('data-camscan-select'));
            if (!sel) { return; }
            b.innerHTML = CAM + b.textContent;
            b.addEventListener('click', function () { open({ title: b.getAttribute('data-title') || 'Scan the barcode', onCode: viaSelect(sel) }); });
        });
    }
    window.CamScan = { open: open };
    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', init); } else { init(); }
})();
