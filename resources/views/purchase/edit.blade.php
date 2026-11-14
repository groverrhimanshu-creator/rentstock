@extends('layouts.app')
@section('title', 'Edit purchase')
@section('content')
<div class="pg"><h2>Edit purchase {{ $p->id }}<small>Items and quantities cannot be changed after saving</small></h2><a class="ab" href="{{ route('purchase.show', $p->id) }}">Back</a></div>
@if ($errors->any())<div class="msg bad">{{ $errors->first() }}</div>@endif
<form method="post" action="{{ route('purchase.update', $p->id) }}" autocomplete="off" enctype="multipart/form-data">
    @csrf @method('PUT')
    <div class="cd"><h3>Purchase details</h3>
        <div class="fg">
            <label class="f"><span>Supplier</span><select name="supplier_id">@foreach ($suppliers as $s)<option value="{{ $s->id }}" @selected((string) old('supplier_id', $p->supplier_id) === (string) $s->id)>{{ $s->name }}</option>@endforeach</select></label>
            <label class="f"><span>Supplier invoice no</span><input name="inv_no" value="{{ old('inv_no', $p->inv_no) }}" maxlength="60"></label>
            <label class="f"><span>Invoice date</span><input type="date" name="inv_date" value="{{ old('inv_date', $p->inv_date) }}"></label>
            <label class="f"><span>Type</span><select name="type">@foreach (['GST', 'Bill of supply'] as $o)<option @selected(old('type', $p->type) === $o)>{{ $o }}</option>@endforeach</select></label>
            <label class="f"><span>Payment mode</span><select name="pay_mode">@foreach (['Cash', 'Cheque/Online', 'Credit'] as $o)<option @selected(old('pay_mode', $p->pay_mode) === $o)>{{ $o }}</option>@endforeach</select></label>
            <label class="f"><span>Supplier invoice @if ($p->invoice_file)(attached: <a href="{{ route('purchase.invoice', $p->id) }}" target="_blank">{{ $p->invoice_file_name ?: 'view' }}</a>)@endif</span><input type="file" name="invoice_file" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png">
                @if ($p->invoice_file)<small><label><input type="checkbox" name="remove_invoice" value="1"> Remove the attached invoice</label> &middot; choosing a file replaces it</small>@endif</label>
            <label class="f"><span>Store</span><select name="store_id">@foreach ($stores as $s)<option value="{{ $s->id }}" @selected((string) old('store_id', $p->store_id) === (string) $s->id)>{{ $s->name }}</option>@endforeach</select></label>
        </div>
    </div>
    <div class="cd"><h3>Items</h3>
        <div class="tw"><table class="t">
            <thead><tr><th>Item</th><th>Config</th><th>HSN</th><th>Barcode?</th><th class="num">Qty</th><th>New brand tags</th><th>Rate</th><th>Barcodes</th></tr></thead>
            <tbody>
                @foreach ($lines as $l)
                    <tr><td><select name="lines[{{ $l->id }}][item_id]">@foreach ($items as $i)@if (!$l->barcoded || $i->tracked)<option value="{{ $i->id }}" @selected((string) old('lines.' . $l->id . '.item_id', $l->item_id) === (string) $i->id)>{{ $i->name }}</option>@endif @endforeach</select></td>
                        <td><input class="cellw" name="lines[{{ $l->id }}][config]" value="{{ old('lines.' . $l->id . '.config', $l->config) }}" maxlength="150"></td>
                        <td><input class="cell" name="lines[{{ $l->id }}][hsn]" value="{{ old('lines.' . $l->id . '.hsn', $l->hsn ?: $l->item_hsn) }}" maxlength="10" inputmode="numeric"></td>
                        <td>{{ $l->barcoded ? 'With barcode' : 'No barcode (count only)' }}</td>
                        <td><input class="cell" type="number" min="1" max="5000" step="1" name="lines[{{ $l->id }}][qty]" value="{{ old('lines.' . $l->id . '.qty', $l->qty) }}"></td>
                        <td>@if ($l->barcoded)<input class="cellw" name="lines[{{ $l->id }}][tags]" value="{{ old('lines.' . $l->id . '.tags') }}" placeholder="Only if you raise the quantity" autocomplete="off">@else - @endif</td>
                        <td><input class="cell" type="number" min="0" step="0.01" name="lines[{{ $l->id }}][rate]" value="{{ old('lines.' . $l->id . '.rate', $l->rate) }}"></td>
                        <td><span class="codes">{{ isset($codes[$l->id]) ? $codes[$l->id]->map(fn ($c) => \App\Support\Barcodes::text($c->code))->implode(', ') : ($l->barcoded ? '' : 'Quantity item') }}</span></td></tr>
                @endforeach
            </tbody>
        </table></div>
        <div class="msg info">You can change the item, config, HSN code, rate and quantity. A barcoded item or quantity can be changed only while its pieces are still in stock and untouched (not sold, rented, repaired or changed). To raise the quantity of a barcoded item, enter one brand tag for each new piece, separated by commas; lowering it removes the newest untouched pieces.</div>
        <div class="rowx"><button class="ab solid">Save</button><a class="ab" href="{{ route('purchase.show', $p->id) }}">Cancel</a></div>
    </div>
</form>
@endsection
