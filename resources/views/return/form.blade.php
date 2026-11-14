@extends('layouts.app')
@section('title', 'New return')
@section('content')
<div class="pg"><h2>New return<small>Reason, condition and notes are set for each item</small></h2><a class="ab" href="{{ route('return.index') }}">Back</a></div>
@if ($errors->any())<div class="msg bad">{{ $errors->first() }}</div>@endif
<div class="msg bad" id="clienterr" hidden></div>
<form id="tf" method="post" action="{{ route('return.store') }}" autocomplete="off" enctype="multipart/form-data">
    @csrf
    <div class="cd"><h3>Customer</h3>
        <div class="fg"><label class="f"><span>Customer</span><select name="customer_id" id="customer"><option value="">Select</option>@foreach ($customers as $c)<option value="{{ $c->id }}" @selected((string) old('customer_id') === (string) $c->id)>{{ $c->name }} ({{ $c->on_rent }} on rent)</option>@endforeach</select></label>@include('partials.dc-field')</div>
    </div>
    <div class="cd" id="box" hidden>
        <h3>Items on rent (<span id="out-count">0</span>)</h3>
        <div class="rowx" style="margin-bottom:10px"><input id="scan" class="ctl" placeholder="Scan returned barcode" style="flex:1;max-width:320px" autocomplete="off"><button type="button" class="ab" id="scanbtn">Tick</button><button type="button" class="ab sm" id="selall">Tick all</button><button type="button" class="ab sm" id="selnone">Clear</button></div>
        <div class="tw" style="max-height:480px;overflow:auto"><table class="t"><thead><tr><th></th><th>Barcode</th><th>Item</th><th>Rented on</th><th>Reason</th><th>Condition</th><th>Notes</th></tr></thead><tbody id="rows"></tbody></table></div>
        <div class="msg info"><span id="n">0</span> selected. Good items go back to stock. Damaged/Faulty items go to repair, and they need a note.</div>
        <div class="rowx"><button class="ab solid">Save return</button></div>
    </div>
</form>
@php
    $tdata = ['itemsUrl' => route('return.items'), 'prefix' => $prefix, 'reasons' => $reasons];
@endphp
<script>window.RET = {!! json_encode($tdata, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!};</script>
<script src="{{ \App\Support\Assets::url('assets/return.js') }}"></script>
@endsection
