@extends('layouts.app')
@section('title', 'Edit invoice')
@section('content')
<div class="pg"><h2>Edit invoice {{ $s->no }}<small>Items and quantities cannot be changed after saving</small></h2><a class="ab" href="{{ route('sale.show', $s->id) }}">Back</a></div>
@if ($errors->any())<div class="msg bad">{{ $errors->first() }}</div>@endif
<form id="sef" method="post" action="{{ route('sale.update', $s->id) }}" autocomplete="off">
    @csrf @method('PUT')
    <div class="cd"><h3>Customer</h3>
        <div class="fg">
            <label class="f"><span>Customer</span><select name="customer_id">@foreach ($customers as $c)<option value="{{ $c->id }}" @selected((string) old('customer_id', $s->customer_id) === (string) $c->id)>{{ $c->name }}</option>@endforeach</select></label>
            <label class="f"><span>Type</span><select name="type">@foreach (['GST', 'Bill of supply'] as $o)<option @selected(old('type', $s->type) === $o)>{{ $o }}</option>@endforeach</select></label>
            <label class="f"><span>Payment mode</span><select name="pay_mode">@foreach (['Cash', 'Cheque/Online', 'Credit'] as $o)<option @selected(old('pay_mode', $s->pay_mode) === $o)>{{ $o }}</option>@endforeach</select></label>
            <label class="f"><span>Remark</span><input name="remark" value="{{ old('remark', $s->remark) }}" maxlength="255"></label>
        </div>
    </div>
    <div class="cd"><h3>Items</h3>
        <div class="tw"><table class="t">
            <thead><tr><th>Barcode</th><th>Item</th><th>Qty</th><th>Price</th></tr></thead>
            <tbody>
                @foreach ($lines as $l)
                    <tr><td class="bc">{{ $l->code ? \App\Support\Barcodes::text($l->code) : '-' }}</td><td>{{ $l->name }}</td><td>{{ $l->qty }}</td>
                        <td><input class="cell" type="number" min="0" step="0.01" name="prices[{{ $l->id }}]" value="{{ old('prices.' . $l->id, $l->price) }}"></td></tr>
                @endforeach
            </tbody>
        </table></div>
        <div class="msg info">The GST and total are worked out again when you save. To change an item or a quantity, delete this invoice (its items go back into stock) and make a new one.</div>
        <div class="rowx"><button class="ab solid">Save</button><a class="ab" href="{{ route('sale.show', $s->id) }}">Cancel</a></div>
    </div>
</form>
@endsection
