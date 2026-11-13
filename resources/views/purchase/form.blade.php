@extends('layouts.app')
@section('title', 'New purchase')
@section('content')
<div class="pg"><h2>New purchase<small>Choose for each row whether the item gets a barcode</small></h2><a class="ab" href="{{ route('purchase.index') }}">Back</a></div>
@if ($errors->any())<div class="msg bad">{{ $errors->first() }}</div>@endif
<form id="pf" method="post" action="{{ route('purchase.store') }}" autocomplete="off">
    @csrf
    <div class="cd"><h3>Purchase details</h3>
        <div class="fg">
            <label class="f"><span>Supplier</span><select name="supplier_id" id="supplier"><option value="">Select</option>@foreach ($suppliers as $s)<option value="{{ $s->id }}" data-state="{{ $s->state_code }}" @selected((string) old('supplier_id') === (string) $s->id)>{{ $s->name }}</option>@endforeach</select></label>
            <label class="f"><span>Supplier invoice no</span><input name="inv_no" value="{{ old('inv_no') }}" maxlength="60"></label>
            <label class="f"><span>Invoice date</span><input type="date" name="inv_date" value="{{ old('inv_date') }}"></label>
            <label class="f"><span>Type</span><select name="type" id="type">@foreach (['GST', 'Bill of supply'] as $o)<option @selected(old('type', 'GST') === $o)>{{ $o }}</option>@endforeach</select></label>
            <label class="f"><span>Payment mode</span><select name="pay_mode">@foreach (['Cash', 'Cheque/Online', 'Credit'] as $o)<option @selected(old('pay_mode', 'Cash') === $o)>{{ $o }}</option>@endforeach</select></label>
            <label class="f"><span>Store</span><select name="store_id">@foreach ($stores as $s)<option value="{{ $s->id }}" @selected((string) old('store_id') === (string) $s->id)>{{ $s->name }}</option>@endforeach</select></label>
        </div>
    </div>
    <div class="cd"><h3>Items</h3>
        <div class="tw"><table class="t">
            <thead><tr><th>Item</th><th>Config</th><th>Brand tag</th><th>Barcode?</th><th>Qty</th><th>Rate</th><th class="num">Amount</th><th>Barcodes</th><th></th></tr></thead>
            <tbody id="rows"></tbody>
        </table></div>
        <div class="rowx" style="margin-top:10px"><button type="button" class="ab sm" id="addline">+ Add another item</button><button type="button" class="ab sm" id="newitem">+ New item (not in the list)</button></div>
        <div class="cd" id="qi" hidden style="margin:12px 0 0;background:var(--a-bg,#f7f9fc)">
            <h3>New item</h3>
            <div class="msg bad" id="qi-err" hidden></div>
            <div class="fg">
                <label class="f"><span>Item name</span><input id="qi-name" maxlength="150" autocomplete="off"></label>
                <label class="f"><span>Brand</span><select id="qi-brand"><option value="">Select</option>@foreach ($brands as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach</select></label>
                <label class="f"><span>Or a new brand</span><input id="qi-brand-new" maxlength="100" placeholder="Type only if the brand is not in the list" autocomplete="off"></label>
                <label class="f"><span>Category</span><select id="qi-cat"><option value="">Select</option>@foreach ($categories as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select></label>
                <label class="f"><span>Or a new category</span><input id="qi-cat-new" maxlength="100" placeholder="Type only if the category is not in the list" autocomplete="off"></label>
                <label class="f"><span>Kind</span><select id="qi-kind"><option value="yes">Barcoded (one barcode per piece)</option><option value="qty">By quantity (no barcode)</option></select></label>
                <label class="f"><span>HSN code</span><input id="qi-hsn" value="8471" maxlength="8"></label>
                <label class="f"><span>GST %</span><input id="qi-gst" type="number" min="0" max="28" step="0.01" value="18"></label>
            </div>
            <div class="rowx" style="margin-top:10px"><button type="button" class="ab solid" id="qi-save">Add item</button><button type="button" class="ab" id="qi-cancel">Cancel</button></div>
            <p class="hint">Rent and sale price start at 0. Set them later under Masters &rarr; Item.</p>
        </div>
        <div class="totbox"><div><span>Taxable</span><span id="t-taxable">0.00</span></div><div><span>GST</span><span id="t-tax">0.00</span></div><div class="g"><b>Total</b><b id="t-total">0.00</b></div></div>
        <p class="hint" id="codesum" style="font-weight:600"></p>
        <div class="msg info">Brand tag: enter one for each piece of a "With barcode" row, separated by commas (for example ABC1234, ABC1235). When you save, one {{ \App\Support\Barcodes::prefix() }} barcode is made for every piece marked "With barcode". Rows marked "No barcode" only add to the stock count. The numbers shown above are the next ones in line; they are final once you press Save (if someone else saves a purchase first, you will see the final numbers on the purchase page).</div>
        <div class="rowx"><button class="ab solid">Save purchase</button></div>
    </div>
</form>
@php
    $pdata = [
        'companyState' => $companyState, 'prefix' => \App\Support\Barcodes::prefix(), 'nextCode' => \App\Support\Barcodes::peek(), 'quickUrl' => route('purchase.quick-item'),
        'items' => $items->map(function ($i) {
            return ['id' => $i->id, 'name' => $i->name, 'tracked' => (bool) $i->tracked, 'gst' => (float) $i->gst_percent];
        })->values()->all(),
        'lines' => old('lines') ? array_values(old('lines')) : [new \stdClass()],
    ];
@endphp
<script>window.PURCHASE = {!! json_encode($pdata, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!};</script>
<script src="{{ asset('assets/purchase.js') }}"></script>
@endsection
