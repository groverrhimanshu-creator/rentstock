@extends('layouts.app')
@section('title', 'New rental challan')
@section('content')
<div class="pg"><h2>New rental challan<small>Items go out on rent. A delivery challan is created.</small></h2><a class="ab" href="{{ route('rental.index') }}">Back</a></div>
@if ($errors->any())<div class="msg bad">{{ $errors->first() }}</div>@endif
<div class="msg bad" id="clienterr" hidden></div>
<form id="rf" method="post" action="{{ route('rental.store') }}" autocomplete="off" enctype="multipart/form-data">
    @csrf
    <div class="cd"><h3>Customer</h3>
        <div class="fg">
            <label class="f"><span>Customer</span><select name="customer_id" id="customer"><option value="">Select</option>@foreach ($customers as $c)<option value="{{ $c->id }}" @selected((string) old('customer_id') === (string) $c->id)>{{ $c->name }}</option>@endforeach</select></label>
            <label class="f"><span>Expected duration</span><select name="duration" id="duration" required><option value="">Select</option><option value="short" @selected(old('duration') === 'short')>Short term</option><option value="long" @selected(old('duration') === 'long')>Long term</option></select></label>
            <label class="f" id="retbox" hidden><span>Expected return date</span><input type="date" name="return_by" id="retdate" value="{{ old('return_by', $returnBy) }}"></label>
            <label class="f"><span>Type</span><select name="type">@foreach (['GST', 'Bill of supply'] as $o)<option @selected(old('type', 'GST') === $o)>{{ $o }}</option>@endforeach</select></label>
            <label class="f"><span>Payment mode</span><select name="pay_mode">@foreach (['Cash', 'Cheque/Online', 'Credit'] as $o)<option @selected(old('pay_mode', 'Cash') === $o)>{{ $o }}</option>@endforeach</select></label>
            <label class="f"><span>Remark</span><input name="remark" value="{{ old('remark') }}" maxlength="255"></label>
            @include('partials.dc-field')
        </div>
    </div>
    <div class="cd"><h3>Add items</h3>
        <div class="f sugwrap"><span>Scan or type a barcode</span>
            <div class="rowx"><input id="scan" placeholder="Scan, or type {{ $prefix }}6000… or a name such as HP screen. Separate several with commas" style="flex:1" autocomplete="off"><button type="button" class="ab" id="scanbtn">Add</button></div>
            <div id="sug"></div>
        </div>
        <div class="f" style="margin-top:12px"><span>Available in stock. Tick an item to add it, untick to remove it.</span>
            <div class="rowx">
                <select id="stockitem" style="flex:2;max-width:380px"><option value="">All items ({{ $stockItems->sum('n') }} in stock)</option>@foreach ($stockItems as $i)<option value="{{ $i->id }}">{{ $i->name }} · {{ $i->n }} in stock</option>@endforeach</select>
                <button type="button" class="ab sm" id="tickall">Tick all shown</button>
            </div>
        </div>
        <div class="tw" style="margin-top:8px;max-height:320px;overflow:auto"><table class="t"><thead><tr><th></th><th>Barcode</th><th>Item</th><th>Config</th><th class="num">Rent / month</th></tr></thead><tbody id="stock-body"></tbody></table></div>
        <p class="hint" id="stock-more"></p>
    </div>
    <div class="cd">
        <div class="rowx" style="justify-content:space-between;margin-bottom:10px"><h3 style="margin:0">Selected items (<span id="sel-count">0</span>)</h3><div class="rowx"><button type="button" class="ab sm danger" id="clear" hidden>Clear all</button></div></div>
        <div class="msg" id="sel-empty">No items selected yet.</div>
        <div class="tw" id="sel-table" hidden><table class="t"><thead><tr><th>Barcode</th><th>Item</th><th>HSN/SAC</th><th>Rent / month</th><th></th></tr></thead><tbody id="sel-body"></tbody></table></div>
        <div id="hidden"></div>
        <div class="msg info">Saving marks these items as on rent with this customer and creates the delivery challan. The customer sees them in their login.</div>
        <div class="rowx"><button class="ab solid">Save and create challan</button></div>
    </div>
</form>
@php
    $rdata = ['sac' => (string) config('rentstock.rent_sac', '997315'), 'prefix' => $prefix, 'stockUrl' => route('sale.stock'), 'lines' => old('lines') ? array_values(old('lines')) : []];
@endphp
<script>
(function () {
    var d = document.getElementById('duration'), b = document.getElementById('retbox'), i = document.getElementById('retdate');
    function sync() { var s = d.value === 'short'; b.hidden = !s; i.disabled = !s; i.required = s; }
    d.addEventListener('change', sync); sync();
})();
</script>
<script>window.RENT = {!! json_encode($rdata, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!};</script>
<script src="{{ \App\Support\Assets::url('assets/rental.js') }}"></script>
@endsection
