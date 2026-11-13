<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Barcode labels</title>
<style>
:root{--w:{{ $w }}mm;--h:{{ $h }}mm;--gap:{{ $gap }}mm;--s:{{ round(min($w / 50, $h / 25), 2) }};--dx:0mm;--dy:0mm}
body{margin:0;font-family:Arial,Helvetica,sans-serif;background:#f1f2f5}
.bar{position:sticky;top:0;z-index:2;background:#fff;border-bottom:1px solid #ddd;padding:10px 14px;display:flex;gap:10px 14px;align-items:center;flex-wrap:wrap;font-size:13px}
.bar button{padding:7px 16px;border:0;border-radius:4px;background:#F28C0F;color:#fff;cursor:pointer;font-size:13px}
.bar button.g{background:#fff;color:#0A7EC2;border:1px solid #cfd6e4}
.bar a{color:#0A7EC2}
.bar label{display:flex;flex-direction:column;font-size:11px;color:#555;gap:2px}
.bar input{width:64px;padding:4px 6px;border:1px solid #cfd6e4;border-radius:4px;font-size:13px}
.bar .help{flex-basis:100%;color:#555;font-size:12px}
.sheet{padding:14px}
.row{display:flex;gap:var(--gap);width:max-content;margin-bottom:10px;position:relative;left:var(--dx);top:var(--dy)}
.lab{background:#fff;color:#000;border:1px dashed #999;box-sizing:border-box;width:var(--w);height:var(--h);padding:calc(.8mm * var(--s)) calc(1.2mm * var(--s));text-align:center;overflow:hidden;display:flex;flex-direction:column;justify-content:center;align-items:center;flex:none}
.lab svg{display:block;width:calc(var(--w) - 6mm) !important;height:calc(6.6mm * var(--s)) !important}
.lab .t1{font:bold calc(6.2pt * var(--s)) Arial,sans-serif;letter-spacing:.04em;line-height:1.1;white-space:nowrap}
.lab .t2{font:bold calc(7pt * var(--s)) 'Courier New',monospace;line-height:1.1;letter-spacing:.02em;margin-top:.3mm}
.lab .t3{font:calc(5.2pt * var(--s)) Arial,sans-serif;line-height:1.1;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:100%}
.lab .t4{font:calc(5.2pt * var(--s)) Arial,sans-serif;line-height:1.12;max-width:100%;overflow:hidden;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;word-break:break-word}
.lab .t5{font:bold calc(5.6pt * var(--s)) Arial,sans-serif;line-height:1.12;max-width:100%;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
#pool{display:none}
@media print{body{background:#fff}.bar{display:none}.sheet{padding:0}.row{margin:0;page-break-after:always;break-after:page}.row:last-child{page-break-after:auto;break-after:auto}.lab{border:0}}
</style>
<style id="pagecss">@page{size:{{ $cols * $w + ($cols - 1) * $gap }}mm {{ $h }}mm;margin:0}</style></head><body>
<div class="bar">
    @if ($saved)<b style="color:#1E7A46">Saved.</b>@endif
    <button onclick="window.print()">Print {{ count($labels) }} {{ count($labels) === 1 ? 'label' : 'labels' }}</button>
    <label>Labels across<input id="o-cols" type="number" min="1" max="4" step="1" value="{{ $cols }}"></label>
    <label>Label width (mm)<input id="o-w" type="number" min="20" max="120" step="0.5" value="{{ $w }}"></label>
    <label>Label height (mm)<input id="o-h" type="number" min="15" max="100" step="0.5" value="{{ $h }}"></label>
    <label>Gap between (mm)<input id="o-gap" type="number" min="0" max="20" step="0.5" value="{{ $gap }}"></label>
    <label>Move right (mm)<input id="o-dx" type="number" min="-20" max="20" step="0.5" value="0"></label>
    <label>Move down (mm)<input id="o-dy" type="number" min="-20" max="20" step="0.5" value="0"></label>
    <button type="button" class="g" id="o-reset">Reset</button>
    @if ($back)<a href="{{ $back }}">Back</a>@else<a href="javascript:window.close()">Close</a>@endif
    <div class="help">Match these to your label roll (measure one label and the gap). Settings are remembered on this computer. In the print window choose your label printer, set the paper size to <b id="o-page"></b> mm, margins None, scale 100%.</div>
</div>
<div class="sheet" id="sheet"></div>
<div id="pool">
@foreach ($labels as $l)
    @php($text = $prefix . $l->code)
    <div class="lab">@if ($title !== '')<div class="t1">{{ $title }}</div>@endif{!! \App\Support\Code128::svg($text, round($w - 6, 1), round(6.6 * min($w / 50, $h / 25), 1)) !!}<div class="t2">{{ $text }}</div>@if ($l->brand_tag !== '')<div class="t3">Sr.No : {{ $l->brand_tag }}</div>@endif @if ($l->config !== '')<div class="t4">{{ $l->config }}</div>@endif<div class="t5">{{ $l->model }}</div></div>
@endforeach
</div>
<script>
(function () {
    var KEY = 'rs_label_v1', D = { cols: {{ $cols }}, w: {{ $w }}, h: {{ $h }}, gap: {{ $gap }}, dx: 0, dy: 0 };
    var ids = ['cols', 'w', 'h', 'gap', 'dx', 'dy'], v = {};
    function load() { var s = {}; try { s = JSON.parse(localStorage.getItem(KEY) || '{}') || {}; } catch (e) {} ids.forEach(function (k) { v[k] = typeof s[k] === 'number' ? s[k] : D[k]; }); }
    function save() { try { localStorage.setItem(KEY, JSON.stringify(v)); } catch (e) {} }
    function num(x, d, lo, hi) { x = parseFloat(x); if (isNaN(x)) { x = d; } return Math.min(hi, Math.max(lo, x)); }
    function apply() {
        v.cols = Math.round(num(v.cols, D.cols, 1, 4)); v.w = num(v.w, D.w, 20, 120); v.h = num(v.h, D.h, 15, 100); v.gap = num(v.gap, D.gap, 0, 20); v.dx = num(v.dx, 0, -20, 20); v.dy = num(v.dy, 0, -20, 20);
        var r = document.documentElement.style;
        r.setProperty('--w', v.w + 'mm'); r.setProperty('--h', v.h + 'mm'); r.setProperty('--gap', v.gap + 'mm'); r.setProperty('--dx', v.dx + 'mm'); r.setProperty('--dy', v.dy + 'mm');
        r.setProperty('--s', Math.round(Math.min(v.w / 50, v.h / 25) * 100) / 100);
        var pw = Math.round((v.cols * v.w + (v.cols - 1) * v.gap) * 10) / 10;
        document.getElementById('pagecss').textContent = '@page{size:' + pw + 'mm ' + v.h + 'mm;margin:0}';
        document.getElementById('o-page').textContent = pw + ' x ' + v.h;
        ids.forEach(function (k) { var e = document.getElementById('o-' + k); if (e !== document.activeElement) { e.value = v[k]; } });
        // arrange the labels in rows of "cols" labels; every row is one printed page
        var sheet = document.getElementById('sheet'), labs = document.querySelectorAll('#pool .lab'), row;
        sheet.innerHTML = '';
        labs.forEach(function (l, i) {
            if (i % v.cols === 0) { row = document.createElement('div'); row.className = 'row'; sheet.appendChild(row); }
            row.appendChild(l.cloneNode(true));
        });
    }
    ids.forEach(function (k) { document.getElementById('o-' + k).addEventListener('input', function (e) { v[k] = e.target.value; apply(); save(); }); });
    document.getElementById('o-reset').addEventListener('click', function () { v = {}; ids.forEach(function (k) { v[k] = D[k]; }); try { localStorage.removeItem(KEY); } catch (e) {} apply(); });
    load(); apply();
})();
</script>
</body></html>
