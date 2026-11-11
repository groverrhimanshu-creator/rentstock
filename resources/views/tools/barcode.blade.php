@extends('layouts.app')
@section('title', 'Barcode check')
@push('head')
<style>
    @page { size: 50mm 25mm; margin: 0; }
    @media print {
        body { background: #fff; }
        .sb, .tb, .head, .noprint { display: none !important; }
        .app, .main, .ct { display: block; padding: 0; margin: 0; max-width: none; }
        .lblprev { border: 0; page-break-after: always; break-after: page; }
    }
</style>
@endpush
@section('content')
<div class="head"><div><h1>Barcode check</h1><p>Make sure the barcode and the label print correctly before any stock is entered</p></div></div>
<div class="card noprint">
    <form class="search" method="get" action="{{ route('barcodes.check') }}">
        <input name="text" value="{{ $text }}" placeholder="Letters and digits, for example {{ $next }}">
        <button class="btn">Show</button>
        <button class="btn solid" type="button" onclick="window.print()">Print this label</button>
    </form>
    <p class="hint">Next barcode the system will give out: <span class="bc">{{ $next }}</span>. In the print window choose the label printer, set Margins to None, Scale to 100% and turn Headers and footers off.</p>
</div>
<div class="lblprev">
    {!! $svg !!}
    <div class="num">{{ $text }}</div>
</div>
@endsection
