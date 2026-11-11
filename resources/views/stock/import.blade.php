@extends('layouts.app')
@section('title', 'Import stock')
@section('content')
<div class="pg"><h2>Import stock<small>Upload your existing stock sheet (CSV / Excel)</small></h2></div>
<div class="cd"><h3>Step 1. Choose the file</h3>
    <p class="hint">Columns: {{ $headers }}. One row per barcoded item, Quantity 1.</p>
    @if ($error)<div class="msg bad">{{ $error }}</div>@endif
    @if ($errors->any())<div class="msg bad">{{ $errors->first() }}</div>@endif
    <form method="post" action="{{ route('stock.import.read') }}" enctype="multipart/form-data" class="rowx" style="margin:10px 0">
        @csrf
        <label class="ab" style="cursor:pointer">Choose file<input type="file" name="file" accept=".csv,.txt,.xlsx" hidden onchange="this.form.submit()"></label>
        <button type="button" class="ab" onclick="document.getElementById('imptext').value=document.getElementById('impsample').value">Load sample file</button>
    </form>
    <textarea id="impsample" hidden>{{ $sample }}</textarea>
    <form id="checkform" method="post" action="{{ route('stock.import.check') }}">
        @csrf
        <label class="f"><span>File contents</span><textarea id="imptext" name="text" rows="7" class="mono">{{ $text }}</textarea></label>
        <div class="rowx" style="margin-top:10px"><button class="ab solid">Check file</button></div>
    </form>
</div>
@if ($report)
@php
    $problems = array_values(array_filter($report, fn ($r) => $r['err']));
    $bad = count($problems);
    $good = count($report) - $bad;
    $per = \App\Support\Pager::size($per);
    $pages = max(1, (int) ceil($bad / $per));
    $p = min(max(1, $page), $pages);
    $shown = array_slice($problems, ($p - 1) * $per, $per);
    $s = max(1, $p - 2);
    $e = min($pages, $s + 4);
@endphp
<div class="cd"><h3>Step 2. Check the result</h3>
    <div class="sumb"><div><span>Rows read</span><b>{{ count($report) }}</b></div><div><span>Ready to add</span><b>{{ $good }}</b></div><div><span>Need fixing</span><b>{{ $bad }}</b></div></div>
    @if ($bad)<div class="msg">{{ $bad }} row(s) have a problem and will be skipped. Fix them in the file and import again, or import the good rows now. Only the rows with a problem are listed below.</div>
    @else<div class="msg ok">All rows look good.</div>@endif
    @if ($bad)
    <form method="post" action="{{ route('stock.import.errors') }}" style="margin:0 0 12px">
        @csrf
        <textarea name="text" hidden>{{ $text }}</textarea>
        <button class="ab">Download error report ({{ $bad }} rows)</button>
        <span class="hint" style="margin-left:8px">A CSV with every row that has a problem and the reason. Fix the rows and import that file again.</span>
    </form>
    <div class="lt">
        <label class="f"><span>Show</span>
            <select name="per" form="checkform" onchange="document.getElementById('pg').value=1;document.getElementById('checkform').submit()">@foreach (\App\Support\Pager::SIZES as $o)<option value="{{ $o }}" @selected($per === $o)>{{ $o }}</option>@endforeach</select>
        </label>
    </div>
    <input type="hidden" name="page" id="pg" form="checkform" value="{{ $p }}">
    <div class="tw"><table class="t">
        <thead><tr><th>Line</th><th>Model</th><th>Barcode</th><th>Brand tag</th><th>Result</th></tr></thead>
        <tbody>
            @foreach ($shown as $r)
                <tr><td>{{ $r['line'] }}</td><td>{{ $r['model'] }}</td><td class="bc">{{ $r['code'] !== null ? \App\Support\Barcodes::text($r['code']) : $r['raw'] }}</td><td>{{ $r['tag'] }}</td>
                    <td><span class="pill bad">Problem</span> {{ implode('; ', $r['err']) }}</td></tr>
            @endforeach
        </tbody>
    </table></div>
    <div class="pgn">
        <span>Showing {{ ($p - 1) * $per + 1 }} to {{ min($bad, $p * $per) }} of {{ $bad }} problem rows</span>
        <div class="pb">
            @if ($p > 1)<button type="submit" form="checkform" name="page" value="{{ $p - 1 }}" class="pgbtn">Prev</button>@else<span class="dis">Prev</span>@endif
            @for ($i = $s; $i <= $e; $i++)<button type="submit" form="checkform" name="page" value="{{ $i }}" class="pgbtn {{ $i === $p ? 'cur' : '' }}">{{ $i }}</button>@endfor
            @if ($p < $pages)<button type="submit" form="checkform" name="page" value="{{ $p + 1 }}" class="pgbtn">Next</button>@else<span class="dis">Next</span>@endif
        </div>
    </div>
    @endif
    <form method="post" action="{{ route('stock.import.commit') }}" style="margin-top:12px">
        @csrf
        <textarea name="text" hidden>{{ $text }}</textarea>
        <button class="ab solid" @disabled(!$good)>Import {{ $good }} good rows</button>
    </form>
</div>
@endif
@endsection
