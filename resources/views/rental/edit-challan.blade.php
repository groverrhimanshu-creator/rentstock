@extends('layouts.app')
@section('title', 'Edit challan')
@section('content')
<div class="pg"><h2>Edit challan {{ $r->no }}<small>Change the details and the items</small></h2><a class="ab" href="{{ route('rental.show', $r->id) }}">Back</a></div>
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
            <thead><tr><th>Barcode</th><th>Item</th><th>Rent / month</th><th>Take off</th></tr></thead>
            <tbody>
                @foreach ($lines as $l)
                    <tr><td class="bc">{{ \App\Support\Barcodes::text($l->code) }}</td><td>{{ $l->name }}</td><td><input class="cell" type="number" min="0" step="0.01" name="rents[{{ $l->id }}]" value="{{ old('rents.' . $l->id, $l->rent) }}"></td>
                        <td>@if ($why[$l->id] ?? null)<small class="hint" title="{{ $why[$l->id] }}">Cannot: {{ \Illuminate\Support\Str::limit($why[$l->id], 70) }}</small>@else<label><input type="checkbox" name="remove[]" value="{{ $l->id }}" @checked(in_array((string) $l->id, array_map('strval', (array) old('remove', [])), true))> Remove</label>@endif</td></tr>
                @endforeach
            </tbody>
        </table></div>
        <div class="f sugwrap" style="margin-top:14px"><span>Add items from stock (scan or type a barcode, or a name such as HP screen)</span>
            <input id="addq" placeholder="{{ $prefix }}6000… or a name" autocomplete="off"><div id="sug"></div></div>
        <div class="tw" id="addbox" hidden style="margin-top:8px"><table class="t"><thead><tr><th>Barcode</th><th>Item</th><th>Rent / month</th><th></th></tr></thead><tbody id="addbody"></tbody></table></div>
        <div class="msg bad" id="clienterr" hidden></div>
        <div class="msg info">Ticked items go back into stock. Added items go out on this challan today. A new rent applies to rental invoices made after this. Invoices already made are not changed. An item that has come back, was replaced, or is already billed on a rental invoice cannot be taken off.</div>
        <div class="rowx"><button class="ab solid">Save</button><a class="ab" href="{{ route('rental.show', $r->id) }}">Cancel</a></div>
    </div>
</form>
<script>window.CHE = {!! json_encode(['stockUrl' => $stockUrl, 'prefix' => $prefix], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!};</script>
<script src="{{ \App\Support\Assets::url('assets/challanedit.js') }}"></script>
<script>
(function () {
    var d = document.getElementById('duration'), b = document.getElementById('retbox'), i = document.getElementById('retdate');
    function sync() { var s = d.value === 'short'; b.hidden = !s; i.disabled = !s; i.required = s; }
    d.addEventListener('change', sync); sync();
})();
</script>
@endsection
