<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Barcode labels</title>
<style>
body{margin:0;font-family:Arial,Helvetica,sans-serif;background:#f1f2f5}
.bar{position:sticky;top:0;background:#fff;border-bottom:1px solid #ddd;padding:10px 14px;display:flex;gap:10px;align-items:center;font-size:13px}
.bar button{padding:7px 16px;border:0;border-radius:4px;background:#F28C0F;color:#fff;cursor:pointer;font-size:13px}
.bar a{color:#0A7EC2}
.sheet{display:flex;flex-wrap:wrap;gap:10px;padding:14px}
.lab{background:#fff;color:#000;border:1px dashed #999;box-sizing:border-box;width:{{ $w }}mm;height:{{ $h }}mm;padding:{{ round(.8 * $s, 2) }}mm {{ round(1.2 * $s, 2) }}mm;text-align:center;overflow:hidden;display:flex;flex-direction:column;justify-content:center;align-items:center}
.lab svg{display:block}
.lab .t1{font:bold {{ round(6.2 * $s, 1) }}pt Arial,sans-serif;letter-spacing:.04em;line-height:1.1;white-space:nowrap}
.lab .t2{font:bold {{ round(7 * $s, 1) }}pt 'Courier New',monospace;line-height:1.1;letter-spacing:.02em;margin-top:.3mm}
.lab .t3{font:{{ round(5.2 * $s, 1) }}pt Arial,sans-serif;line-height:1.1;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:100%}
.lab .t4{font:{{ round(5.2 * $s, 1) }}pt Arial,sans-serif;line-height:1.12;max-width:100%;overflow:hidden;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;word-break:break-word}
.lab .t5{font:bold {{ round(5.6 * $s, 1) }}pt Arial,sans-serif;line-height:1.12;max-width:100%;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
@page{size:{{ $w }}mm {{ $h }}mm;margin:0}
@media print{body{background:#fff}.bar{display:none}.sheet{display:block;padding:0}.lab{border:0;margin:0;page-break-after:always;break-after:page}}
</style></head><body>
<div class="bar">@if ($saved)<b style="color:#1E7A46">Saved.</b>@endif<button onclick="window.print()">Print {{ count($labels) }} {{ count($labels) === 1 ? 'label' : 'labels' }}</button><span>Label size {{ $w }} x {{ $h }} mm. In the print window choose your label printer, set margins to None and scale to 100%.</span>@if ($back)<a href="{{ $back }}">Back</a>@else<a href="javascript:window.close()">Close</a>@endif</div>
<div class="sheet">
@foreach ($labels as $l)
    @php($text = $prefix . $l->code)
    <div class="lab">@if ($title !== '')<div class="t1">{{ $title }}</div>@endif{!! \App\Support\Code128::svg($text, round($w - 9, 1), round(6.6 * $s, 1)) !!}<div class="t2">{{ $text }}</div>@if ($l->brand_tag !== '')<div class="t3">Sr.No : {{ $l->brand_tag }}</div>@endif @if ($l->config !== '')<div class="t4">{{ $l->config }}</div>@endif<div class="t5">{{ $l->model }}</div></div>
@endforeach
</div>
</body></html>
