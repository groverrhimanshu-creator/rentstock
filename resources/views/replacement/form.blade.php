@extends('layouts.app')
@section('title', 'New replacement')
@section('content')
<div class="pg"><h2>New replacement</h2><a class="ab" href="{{ route('replacement.index') }}">Back</a></div>
@if ($errors->any())<div class="msg bad">{{ $errors->first() }}</div>@endif
<div class="msg bad" id="clienterr" hidden></div>
<form id="pf" method="post" action="{{ route('replacement.store') }}" autocomplete="off">
    @csrf
    <div class="cd">
        <div class="fg">
            <label class="f"><span>Type</span><select name="kind" id="kind">@foreach ($kinds as $k)<option @selected(old('kind', $kinds[0]) === $k)>{{ $k }}</option>@endforeach</select></label>
            <label class="f"><span>Customer</span><select name="customer_id" id="customer"><option value="">Select</option>@foreach ($customers as $c)<option value="{{ $c->id }}" @selected((string) old('customer_id') === (string) $c->id)>{{ $c->name }}</option>@endforeach</select></label>
            <label class="f"><span>Faulty item coming back</span><select name="old_code" id="old"><option value="">Select</option></select></label>
            <label class="f"><span>Replace with</span><select name="new_code" id="new" disabled><option value="">Choose the faulty item first</option></select></label>
            <label class="f"><span>Item condition</span><select name="fault" id="fault" required><option value="">Select</option>@foreach (\App\Support\Moves::FAULTS as $x)<option @selected(old('fault') === $x)>{{ $x }}</option>@endforeach</select></label>
            <label class="f"><span>Reason</span><input name="reason" id="reason" value="{{ old('reason') }}" maxlength="255" required placeholder="What is wrong with the item"></label>
        </div>
        <div class="f sugwrap full" id="searchbox" style="margin-top:12px" hidden><span>Not the same brand in stock? Search inside this category (brand, model or barcode)</span>
            <input id="repq" placeholder="for example: HP screen" autocomplete="off"><div id="sug"></div></div>
        <div id="note"></div>
        <div class="msg info" id="kindnote"></div>
        <div class="rowx"><button class="ab solid">Save replacement</button></div>
    </div>
</form>
@php
    $pdata = ['dataUrl' => $dataUrl, 'stockUrl' => $stockUrl, 'rental' => $kinds[0], 'oldSel' => (string) old('old_code'), 'newSel' => (string) old('new_code')];
@endphp
<script>window.REP = {!! json_encode($pdata, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!};</script>
<script src="{{ \App\Support\Assets::url('assets/replacement.js') }}"></script>
<script>document.getElementById('pf').addEventListener('submit', function (e) {
    var m = !document.getElementById('fault').value ? 'Choose whether the item is Damaged or Faulty.' : (!document.getElementById('reason').value.trim() ? 'Please enter the reason for the replacement.' : '');
    if (m) { e.preventDefault(); e.stopImmediatePropagation(); var b = document.getElementById('clienterr'); b.textContent = m; b.hidden = false; window.scrollTo(0, 0); }
}, true);</script>
@endsection
