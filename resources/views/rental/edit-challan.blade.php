@extends('layouts.app')
@section('title', 'Edit challan')
@section('content')
<div class="pg"><h2>Edit challan {{ $r->no }}<small>Items cannot be changed after saving</small></h2><a class="ab" href="{{ route('rental.show', $r->id) }}">Back</a></div>
@if ($errors->any())<div class="msg bad">{{ $errors->first() }}</div>@endif
<form method="post" action="{{ route('rental.update', $r->id) }}" autocomplete="off">
    @csrf @method('PUT')
    <div class="cd"><h3>Details</h3>
        <div class="fg">
            <label class="f"><span>Customer</span><input value="{{ $customer }}" readonly></label>
            @php($dur = old('duration', $r->return_by ? 'short' : 'long'))
            <label class="f"><span>Expected duration</span><select name="duration" id="duration"><option value="short" @selected($dur === 'short')>Short term</option><option value="long" @selected($dur === 'long')>Long term</option></select></label>
            <label class="f" id="retbox"><span>Expected return date</span><input type="date" name="return_by" id="retdate" value="{{ old('return_by', $r->return_by ?: date('Y-m-d', strtotime('+30 days'))) }}"></label>
            <label class="f"><span>Type</span><select name="type">@foreach (['GST', 'Bill of supply'] as $o)<option @selected(old('type', $r->type) === $o)>{{ $o }}</option>@endforeach</select></label>
            <label class="f"><span>Payment mode</span><select name="pay_mode">@foreach (['Cash', 'Cheque/Online', 'Credit'] as $o)<option @selected(old('pay_mode', $r->pay_mode) === $o)>{{ $o }}</option>@endforeach</select></label>
            <label class="f"><span>Remark</span><input name="remark" value="{{ old('remark', $r->remark) }}" maxlength="255"></label>
        </div>
    </div>
    <div class="cd"><h3>Items</h3>
        <div class="tw"><table class="t">
            <thead><tr><th>Barcode</th><th>Item</th><th>Rent / month</th></tr></thead>
            <tbody>
                @foreach ($lines as $l)
                    <tr><td class="bc">{{ \App\Support\Barcodes::text($l->code) }}</td><td>{{ $l->name }}</td><td><input class="cell" type="number" min="0" step="0.01" name="rents[{{ $l->id }}]" value="{{ old('rents.' . $l->id, $l->rent) }}"></td></tr>
                @endforeach
            </tbody>
        </table></div>
        <div class="msg info">A new rent applies to rental invoices made after this. Invoices already made are not changed. To change the items, delete this challan (its items go back into stock) and make a new one.</div>
        <div class="rowx"><button class="ab solid">Save</button><a class="ab" href="{{ route('rental.show', $r->id) }}">Cancel</a></div>
    </div>
</form>
<script>
(function () {
    var d = document.getElementById('duration'), b = document.getElementById('retbox'), i = document.getElementById('retdate');
    function sync() { var s = d.value === 'short'; b.hidden = !s; i.disabled = !s; i.required = s; }
    d.addEventListener('change', sync); sync();
})();
</script>
@endsection
