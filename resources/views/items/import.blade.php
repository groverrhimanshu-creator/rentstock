@extends('layouts.app')
@section('title', 'Import items')
@section('content')
<div class="pg"><h2>Import items<small>Masters &rsaquo; Item &mdash; upload the item sheet (CSV / Excel)</small></h2><a class="ab" href="{{ route('masters.index', 'item') }}">Back to Item list</a></div>
<div class="cd"><h3>Step 1. Choose the file</h3>
    <p class="hint">Columns: {{ $headers }}. Other columns in the sheet are ignored. Missing categories and brands are created for you. Items that already exist are skipped.</p>
    <p class="hint"><b>Generate Barcode = Yes</b>: one barcode per piece. Otherwise the item is counted by quantity. <b>Maintain Stock</b>: Yes or No (for quantity items; barcoded items are always kept in stock).</p>
    @if ($error)<div class="msg bad">{{ $error }}</div>@endif
    @if ($errors->any())<div class="msg bad">{{ $errors->first() }}</div>@endif
    <form method="post" action="{{ route('items.import.read') }}" enctype="multipart/form-data" class="rowx" style="margin:10px 0">
        @csrf
        <label class="ab" style="cursor:pointer">Choose file<input type="file" name="file" accept=".csv,.txt,.xlsx" hidden onchange="if(this.files[0]){document.getElementById('fname').textContent='Selected file: '+this.files[0].name;}this.form.submit()"></label>
        <a class="ab" href="{{ route('items.import.sample') }}">Download sample file</a>
        <button type="button" class="ab" onclick="document.getElementById('imptext').value=document.getElementById('impsample').value">Load sample file</button>
        <span id="fname" class="hint" style="margin:0">{{ $fname !== '' ? 'Selected file: ' . $fname : 'No file selected' }}</span>
    </form>
    <textarea id="impsample" hidden>{{ $sample }}</textarea>
    <form id="checkform" method="post" action="{{ route('items.import.check') }}">
        @csrf
        <input type="hidden" name="fname" value="{{ $fname }}">
        <label class="f"><span>File contents</span><textarea id="imptext" name="text" rows="7" class="mono">{{ $text }}</textarea></label>
        <div class="rowx" style="margin-top:10px"><button class="ab solid">Check file</button></div>
    </form>
</div>
@if ($report)
@php
    $okc = count(array_filter($report, fn ($r) => $r['status'] === 'ok'));
    $skipc = count(array_filter($report, fn ($r) => $r['status'] === 'skip'));
    $badc = count(array_filter($report, fn ($r) => $r['status'] === 'err'));
    $list = array_values(array_filter($report, fn ($r) => $r['status'] !== 'ok'));
    $tot = count($list);
    $per = \App\Support\Pager::size($per);
    $pages = max(1, (int) ceil($tot / $per));
    $p = min(max(1, $page), $pages);
    $shown = array_slice($list, ($p - 1) * $per, $per);
    $s = max(1, $p - 2);
    $e = min($pages, $s + 4);
@endphp
<div class="cd"><h3>Step 2. Check the result</h3>
    <div class="sumb"><div><span>Rows read</span><b>{{ count($report) }}</b></div><div><span>New items to add</span><b>{{ $okc }}</b></div><div><span>Skipped (already there)</span><b>{{ $skipc }}</b></div><div><span>Need fixing</span><b>{{ $badc }}</b></div></div>
    @if ($okc && ($new['cat'] || $new['brand']))<div class="msg">This will also create {{ $new['cat'] }} new categories and {{ $new['brand'] }} new brands.</div>@endif
    @if ($badc)<div class="msg bad">{{ $badc }} row(s) have a problem and will be skipped. Fix them in the file and import again, or import the good rows now.</div>
    @elseif ($okc)<div class="msg ok">All rows look good.</div>
    @else<div class="msg">Nothing new to add.</div>@endif
    @if ($badc)
    <form method="post" action="{{ route('items.import.errors') }}" style="margin:0 0 12px">
        @csrf
        <input type="hidden" name="fname" value="{{ $fname }}">
        <textarea name="text" hidden>{{ $text }}</textarea>
        <button class="ab">Download error report ({{ $badc }} rows)</button>
    </form>
    @endif
    @if ($tot)
    <div class="lt">
        <label class="f"><span>Show</span>
            <select name="per" form="checkform" onchange="document.getElementById('pg').value=1;document.getElementById('checkform').submit()">@foreach (\App\Support\Pager::SIZES as $o)<option value="{{ $o }}" @selected($per === $o)>{{ $o }}</option>@endforeach</select>
        </label>
    </div>
    <input type="hidden" name="page" id="pg" form="checkform" value="{{ $p }}">
    <div class="tw"><table class="t">
        <thead><tr><th>Line</th><th>Item</th><th>Category</th><th>Brand</th><th>Result</th></tr></thead>
        <tbody>
            @foreach ($shown as $r)
                <tr><td>{{ $r['line'] }}</td><td>{{ $r['name'] }}</td><td>{{ $r['category'] }}</td><td>{{ $r['brand'] }}</td>
                    <td>@if ($r['status'] === 'err')<span class="pill bad">Problem</span> {{ implode('; ', $r['err']) }}@else<span class="pill">Skipped</span> {{ implode('; ', $r['notes']) }}@endif</td></tr>
            @endforeach
        </tbody>
    </table></div>
    <div class="pgn">
        <span>Showing {{ ($p - 1) * $per + 1 }} to {{ min($tot, $p * $per) }} of {{ $tot }} rows that are not added</span>
        <div class="pb">
            @if ($p > 1)<button type="submit" form="checkform" name="page" value="{{ $p - 1 }}" class="pgbtn">Prev</button>@else<span class="dis">Prev</span>@endif
            @for ($i = $s; $i <= $e; $i++)<button type="submit" form="checkform" name="page" value="{{ $i }}" class="pgbtn {{ $i === $p ? 'cur' : '' }}">{{ $i }}</button>@endfor
            @if ($p < $pages)<button type="submit" form="checkform" name="page" value="{{ $p + 1 }}" class="pgbtn">Next</button>@else<span class="dis">Next</span>@endif
        </div>
    </div>
    @endif
    <form method="post" action="{{ route('items.import.commit') }}" style="margin-top:12px">
        @csrf
        <input type="hidden" name="fname" value="{{ $fname }}">
        <textarea name="text" hidden>{{ $text }}</textarea>
        <button class="ab solid" @disabled(!$okc)>Import {{ $okc }} new items</button>
    </form>
</div>
@endif
@endsection
