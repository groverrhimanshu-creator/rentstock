@extends('layouts.app')
@section('title', 'Edit rental invoice')
@section('content')
<div class="pg"><h2>Edit rental invoice {{ $r->no }}<small>Items cannot be changed after saving</small></h2><a class="ab" href="{{ route('rental.show', $r->id) }}">Back</a></div>
@if ($errors->any())<div class="msg bad">{{ $errors->first() }}</div>@endif
<form method="post" action="{{ route('rental.update', $r->id) }}" autocomplete="off">
    @csrf @method('PUT')
    <div class="cd"><h3>Details</h3>
        <div class="fg">
            <label class="f"><span>Customer</span><input value="{{ $customer }}" readonly></label>
            <label class="f"><span>Rent for</span><input value="{{ \App\Support\Docs::d($r->period_from) }} to {{ \App\Support\Docs::d($r->period_to) }}" readonly></label>
            <label class="f"><span>Type</span><select name="type">@foreach (['GST', 'Bill of supply'] as $o)<option @selected(old('type', $r->type) === $o)>{{ $o }}</option>@endforeach</select></label>
            <label class="f"><span>Payment mode</span><select name="pay_mode">@foreach (['Cash', 'Cheque/Online', 'Credit'] as $o)<option @selected(old('pay_mode', $r->pay_mode) === $o)>{{ $o }}</option>@endforeach</select></label>
            <label class="f"><span>Remark</span><input name="remark" value="{{ old('remark', $r->remark) }}" maxlength="255"></label>
        </div>
    </div>
    <div class="cd"><h3>Items</h3>
        <div class="tw"><table class="t">
            <thead><tr><th>Barcode</th><th>Item</th><th>HSN/SAC</th><th>Type</th><th class="num">Qty</th><th>Amount (each)</th></tr></thead>
            <tbody>
                @foreach ($lines as $l)
                    <tr><td class="bc">{{ $l->code ? \App\Support\Barcodes::text($l->code) : '-' }}</td><td>{{ $l->name }}</td><td>{{ $l->hsn }}</td><td>{{ $l->line_kind === 'sale' ? 'Sold' : 'Rent' }}</td><td class="num">{{ $l->qty }}</td>
                        <td><input class="cell" type="number" min="0" step="0.01" name="prices[{{ $l->id }}]" value="{{ old('prices.' . $l->id, $l->price) }}"></td></tr>
                @endforeach
            </tbody>
        </table></div>
        <div class="msg info">The GST and total are worked out again when you save.</div>
        <div class="rowx"><button class="ab solid">Save</button><a class="ab" href="{{ route('rental.show', $r->id) }}">Cancel</a></div>
    </div>
</form>
@endsection
