@extends('layouts.app')
@section('title', 'New sale')
@section('content')
<div class="pg"><h2 id="ptitle">New sale<small id="psub">Items are sold. An invoice is created.</small></h2><a class="ab" href="{{ route('sale.index') }}">Back</a></div>
@if ($errors->any())<div class="msg bad">{{ $errors->first() }}</div>@endif
<div class="msg bad" id="clienterr" hidden></div>
<form id="sf" method="post" action="{{ route('sale.store') }}" autocomplete="off">
    @csrf
    <div class="cd"><h3>Customer</h3>
        <div class="fg">
            <label class="f"><span>Customer</span><select name="customer_id" id="customer"><option value="">Select</option>@foreach ($customers as $c)<option value="{{ $c->id }}" data-state="{{ $c->state_code }}" @selected((string) old('customer_id') === (string) $c->id)>{{ $c->name }}</option>@endforeach</select></label>
            <label class="f"><span>Sale / Rent</span><select name="mode" id="mode"><option value="sale">Sale (items added by you, invoice)</option><option value="rentinv" @selected(old('mode') === 'rentinv')>Rental invoice (items listed automatically)</option></select></label>
            <label class="f rentonly" hidden><span>Bill from</span><input type="date" name="period_from" id="pfrom" value="{{ old('period_from', $billFrom) }}"></label>
            <label class="f rentonly" hidden><span>Bill to</span><input type="date" name="period_to" id="pto" value="{{ old('period_to', $billTo) }}"></label>
            <label class="f rentonly" hidden style="flex-direction:row;align-items:center;gap:8px;align-self:end"><input type="checkbox" name="full" id="full" value="1" @checked(old('mode') ? old('full') : true) style="width:auto"><span style="margin:0">Charge the full rent for these dates</span></label>
            <label class="f"><span>Type</span><select name="type" id="type">@foreach (['GST', 'Bill of supply'] as $o)<option @selected(old('type', 'GST') === $o)>{{ $o }}</option>@endforeach</select></label>
            <label class="f"><span>Payment mode</span><select name="pay_mode">@foreach (['Cash', 'Cheque/Online', 'Credit'] as $o)<option @selected(old('pay_mode', 'Cash') === $o)>{{ $o }}</option>@endforeach</select></label>
            <label class="f"><span>Remark</span><input name="remark" value="{{ old('remark') }}" maxlength="255"></label>
        </div>
    </div>
    <div class="cd" id="pick-rent" hidden><h3>Items on rent</h3>
        <div class="msg" id="rent-hint">Choose a customer and their items on rent will be listed here.</div>
        <div class="msg info">Everything on rent with this customer is listed below, one row per category and rent (two laptops at the same rent show as Qty 2). "Bill from" and "Bill to" fill in by themselves when you choose the customer; change them if you and the customer agreed on other dates. With "Charge the full rent for these dates" ticked, items given before or during the period are charged the full rent from the first day of "Bill from" (days before it are not charged). Items already returned are charged only up to the day they came back. You can change a price, remove a row, or clear the list.</div>
    </div>
    <div class="cd" id="pick-sale"><h3 id="sale-h">Add items</h3>
        <div class="msg info" id="sale-sub" hidden>Optional. Items you add here are sold to the customer on this same invoice and leave stock.</div>
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
        <div class="tw" style="margin-top:8px;max-height:320px;overflow:auto"><table class="t"><thead><tr><th></th><th>Barcode</th><th>Item</th><th>Config</th><th class="num">Price</th></tr></thead><tbody id="stock-body"></tbody></table></div>
        <p class="hint" id="stock-more"></p>
        <div class="f" style="margin-top:14px"><span>Quantity item (no barcode)</span>
            <div class="rowx">
                <select id="qitem" style="flex:2;max-width:380px"><option value="">Select</option>@foreach ($qtyItems as $i)<option value="{{ $i->id }}" data-left="{{ $i->qty }}" @if ($i->qty === null)data-unl="1"@endif>{{ $i->name }}@if ($i->qty !== null) · {{ $i->qty }} left @else · not counted in stock @endif</option>@endforeach</select>
                <input class="cell" type="number" min="1" id="qqty" value="1" style="width:70px"><button type="button" class="ab" id="qadd">Add</button>
            </div>
        </div>
    </div>
    <div class="cd">
        <div class="rowx" style="justify-content:space-between;margin-bottom:10px"><h3 style="margin:0">Selected items (<span id="sel-count">0</span>)</h3><div class="rowx"><button type="button" class="ab sm" id="usedates" hidden>Use these dates</button><button type="button" class="ab sm" id="relist" hidden>List items on rent again</button><button type="button" class="ab sm danger" id="clear" hidden>Clear all</button></div></div>
        <div class="msg" id="sel-empty">No items selected yet.</div>
        <div class="tw" id="sel-table" hidden><table class="t"><thead id="sel-head"><tr><th>Barcode</th><th>Item</th><th>Qty</th><th>Price</th><th></th></tr></thead><tbody id="sel-body"></tbody></table></div>
        <div id="hidden"></div>
        <div class="totbox"><div><span>Taxable</span><span id="t-taxable">0.00</span></div><div id="r-cg" hidden><span>CGST</span><span id="t-cg">0.00</span></div><div id="r-sg" hidden><span>SGST</span><span id="t-sg">0.00</span></div><div id="r-ig" hidden><span>IGST</span><span id="t-ig">0.00</span></div><div class="g"><b>Total</b><b id="t-total">0.00</b></div></div>
        <div class="msg info" id="save-note">Saving removes these items from stock and creates the invoice in your format.</div>
        <div class="rowx"><button class="ab solid" id="save-btn">Save and create invoice</button></div>
    </div>
</form>
@php
    $sdata = [
        'companyState' => $companyState, 'prefix' => $prefix, 'stockUrl' => route('sale.stock'),
        'items' => $items->map(function ($i) {
            return ['name' => $i->name, 'gst' => (float) $i->gst_percent, 'price' => (float) $i->sale_price, 'hsn' => (string) $i->hsn];
        })->all(),
        'billUrl' => $billUrl, 'periodUrl' => $periodUrl, 'rentSac' => $rentSac, 'mode' => old('mode', 'sale'), 'lines' => old('lines') ? array_values(old('lines')) : [], 'rent' => old('rent') ? array_values(old('rent')) : [],
    ];
@endphp
<script>window.SALE = {!! json_encode($sdata, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!};</script>
<script src="{{ asset('assets/sale.js') }}"></script>
@endsection
