@extends('layouts.app')
@section('title', 'New replacement')
@section('content')
<div class="pg"><h2>New replacement</h2><a class="ab" href="{{ route('replacement.index') }}">Back</a></div>
@if ($errors->any())<div class="msg bad">{{ $errors->first() }}</div>@endif
<div class="msg bad" id="clienterr" hidden></div>
<form id="pf" method="post" action="{{ route('replacement.store') }}" autocomplete="off" enctype="multipart/form-data">
    @csrf
    <div class="cd">
        <div class="fg">
            <label class="f"><span>Type</span><select name="kind" id="kind">@foreach ($kinds as $k)<option @selected(old('kind', $kinds[0]) === $k)>{{ $k }}</option>@endforeach</select></label>
            <label class="f"><span>Customer</span><select name="customer_id" id="customer"><option value="">Select</option>@foreach ($customers as $c)<option value="{{ $c->id }}" @selected((string) old('customer_id') === (string) $c->id)>{{ $c->name }}</option>@endforeach</select></label>
            @include('partials.dc-field')
            <label class="f"><span id="reasonlab">Reason</span><input name="reason" id="reason" value="{{ old('reason') }}" maxlength="255" required placeholder="What is wrong with the item"></label>
        </div>
        <div class="msg info" id="kindnote" style="margin-top:12px"></div>
        <h3 style="margin-top:14px">Items (<span id="rcount">1</span>)</h3>
        <div class="tw"><table class="t" id="rtab"><thead><tr><th>#</th><th>Item coming back</th><th>Replace with / give instead</th><th id="condh">Condition</th><th></th></tr></thead><tbody id="rrows"></tbody></table></div>
        <div class="rowx" style="margin-top:8px"><button type="button" class="ab sm" id="addrow">+ Add another item</button></div>
        <div id="note"></div>
        <div class="rowx" style="margin-top:12px"><button class="ab solid">Save</button></div>
    </div>
</form>
@php
    $pdata = ['dataUrl' => $dataUrl, 'stockUrl' => $stockUrl, 'rental' => $kinds[0], 'sale' => $kinds[1], 'upgrade' => $kinds[2], 'faults' => \App\Support\Moves::FAULTS];
@endphp
<script>window.REP = {!! json_encode($pdata, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!};</script>
<script src="{{ \App\Support\Assets::url('assets/replacement.js') }}"></script>
@endsection
