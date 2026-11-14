@extends('layouts.app')
@section('title', 'Import brands')
@section('content')
<div class="pg"><h2>Import brands<small>Masters &rsaquo; Brand &mdash; upload many brands at once (CSV / Excel)</small></h2><a class="ab" href="{{ route('masters.index', 'brand') }}">Back to Brand list</a></div>
<div class="cd"><h3>Step 1. Choose the file</h3>
    <p class="hint">Columns: {{ $headers }}. Only Brand Name is needed. Brands that already exist are skipped.</p>
    @if ($error)<div class="msg bad">{{ $error }}</div>@endif
    @if ($errors->any())<div class="msg bad">{{ $errors->first() }}</div>@endif
    <form method="post" action="{{ route('brands.import.read') }}" enctype="multipart/form-data" class="rowx" style="margin:10px 0">
        @csrf
        <label class="ab" style="cursor:pointer">Choose file<input type="file" name="file" accept=".csv,.txt,.xlsx" hidden onchange="if(this.files[0]){document.getElementById('fname').textContent='Selected file: '+this.files[0].name;}this.form.submit()"></label>
        <a class="ab" href="{{ route('brands.import.sample') }}">Download sample file</a>
        <span id="fname" class="hint" style="margin:0">{{ $fname !== '' ? 'Selected file: ' . $fname : 'No file selected' }}</span>
    </form>
</div>
@if ($report)
@php
    $okc = count(array_filter($report, fn ($r) => $r['status'] === 'ok'));
    $skipc = count(array_filter($report, fn ($r) => $r['status'] === 'skip'));
    $badc = count(array_filter($report, fn ($r) => $r['status'] === 'err'));
    $list = array_slice(array_values(array_filter($report, fn ($r) => $r['status'] !== 'ok')), 0, 200);
@endphp
<div class="cd"><h3>Step 2. Check the result</h3>
    <div class="sumb"><div><span>Rows read</span><b>{{ count($report) }}</b></div><div><span>New brands to add</span><b>{{ $okc }}</b></div><div><span>Skipped (already there)</span><b>{{ $skipc }}</b></div><div><span>Need fixing</span><b>{{ $badc }}</b></div></div>
    @if ($list)
        <div class="tw" style="margin-top:10px;max-height:320px;overflow:auto"><table class="t"><thead><tr><th>Line</th><th>Brand</th><th>Result</th></tr></thead><tbody>
            @foreach ($list as $r)<tr><td>{{ $r['line'] }}</td><td>{{ $r['name'] }}</td><td>{{ $r['status'] === 'err' ? 'Fix: ' : 'Skipped: ' }}{{ $r['why'] }}</td></tr>@endforeach
        </tbody></table></div>
        @if (count($report) - $okc > 200)<p class="hint">Showing the first 200 skipped or wrong rows.</p>@endif
    @endif
    @if ($okc)
        <form method="post" action="{{ route('brands.import.commit') }}" style="margin-top:12px">@csrf
            <textarea name="text" hidden>{{ $text }}</textarea>
            <button class="ab solid">Import {{ $okc }} {{ $okc === 1 ? 'brand' : 'brands' }}</button>
            @if ($badc)<span class="hint">Rows that need fixing are left out. Fix them in the file and import again.</span>@endif
        </form>
    @else
        <div class="msg bad" style="margin-top:12px">Nothing new to import.</div>
    @endif
</div>
@endif
@endsection
