@extends('layouts.app')
@section('title', 'Upgrade Assets')
@section('content')
<div class="pg"><h2>New upgrade<small>Upgrade Assets: RAM, SSD or a part replacement</small></h2><a class="ab" href="{{ route('consumption.index') }}">Back</a></div>
@if ($errors->any())<div class="msg bad">{{ $errors->first() }}</div>@endif
<div class="msg bad" id="clienterr" hidden></div>
<form id="uf" method="post" action="{{ route('consumption.store') }}" autocomplete="off">
    @csrf
    <input type="hidden" name="code" id="code" value="{{ old('code') }}">
    <div class="cd">
        <div class="fg"><div class="f"><span>Barcode of the device</span><div class="rowx"><input id="scan" placeholder="{{ $prefix }}600001" style="flex:1" value="{{ old('code') ? $prefix . old('code') : '' }}"><button type="button" class="ab" id="find">Find</button></div></div></div>
        <div id="found" hidden>
            <div class="msg info" id="cur"></div>
            <h3 style="margin:14px 0 8px">What was changed</h3>
            <div class="tw"><table class="t"><thead><tr><th>Type</th><th>Old value (from the device)</th><th>New value</th><th></th></tr></thead><tbody id="ups"></tbody></table></div>
            <div class="rowx" style="margin-top:10px"><button type="button" class="ab sm" id="add">+ Add another change</button></div>
            <div class="fg" style="margin-top:12px"><label class="f"><span>Config after upgrade</span><input id="after" readonly></label></div>
            <div class="msg">The barcode stays the same. For RAM and SSD the old value is filled in from the device record and the new value is chosen from the parts in stock (the config is updated and the part leaves stock). For Part replacement, type what was replaced, such as Battery or Keyboard; taking it from stock is optional and the config does not change. Every change is kept in the device history and in the configuration log of the item. To change several things on the same laptop, add a row for each.</div>
            <div class="rowx" style="margin-top:14px"><button class="ab solid">Save upgrade</button><button class="ab" name="print" value="1">Save and print label</button></div>
        </div>
    </div>
</form>
@php
    $udata = ['lookupUrl' => $lookupUrl, 'prefix' => $prefix, 'types' => $types, 'oldUps' => old('ups') ? array_values(old('ups')) : []];
@endphp
<script>window.UPG = {!! json_encode($udata, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!};</script>
<script src="{{ \App\Support\Assets::url('assets/consumption.js') }}"></script>
@endsection
