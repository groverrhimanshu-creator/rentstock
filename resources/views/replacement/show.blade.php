@extends('layouts.app')
@section('title', 'Replacement #' . $r->id)
@section('content')
<div class="pg"><h2>Replacement<small>{{ \Carbon\Carbon::parse($r->created_at)->format('d/m/Y') }} · {{ $r->kind }}</small></h2><div class="rowx">
    @if (\App\Support\Perm::has('replacement.delete'))
        @include('partials.delete-btn', ['action' => route('replacement.destroy', $r->id), 'what' => 'replacement', 'confirm' => 'Delete this replacement? Both items go back where they were. This cannot be undone.'])
    @endif
    <a class="ab" href="{{ route('replacement.index') }}">Back</a></div></div>
<div class="paper">
    @include('partials.paper-head', ['title' => 'REPLACEMENT', 'sub' => 'Date: ' . \Carbon\Carbon::parse($r->created_at)->format('d/m/Y') . '<br>Type: ' . e($r->kind) . ($r->cn ? '<br>Credit note: ' . e($r->cn) : '')])
    <div class="three">
        @include('partials.party', ['h' => 'Customer', 'name' => $r->cname, 'address' => $r->address, 'city' => $r->city, 'state' => $r->state, 'code' => $r->state_code, 'gstin' => $r->gstin, 'phone' => $r->phone])
        <div><h5>Condition</h5><p><b>{{ $r->fault ?: '-' }}</b></p></div>
        <div><h5>Entered by</h5><p>{{ $user ?: '-' }}</p></div>
    </div>
    <table>
        <thead><tr><th></th><th>Barcode</th><th>Item</th><th>Category</th><th>Config</th><th>Brand tag</th><th>Now</th></tr></thead>
        <tbody>
            @foreach ([['Faulty item (came back)', $old, $r->old_code], ['Replaced with', $new, $r->new_code]] as [$lab, $d, $code])
                <tr><td><b>{{ $lab }}</b></td><td><span class="bc">{{ $prefix . $code }}</span></td><td>{{ $d->model ?? '-' }}</td><td>{{ $d->cat ?? '-' }}</td><td>{{ $d->config ?? '-' }}</td><td>{{ $d->brand_tag ?? '-' }}</td>
                    <td>{{ $d ? (\App\Support\Stock::STATUS[$d->status][0] ?? $d->status) : '-' }}</td></tr>
            @endforeach
        </tbody>
    </table>
    <div class="three" style="margin-top:14px">
        <div><h5>Reason</h5><p>{{ $r->reason ?: '-' }}</p></div>
        @if ($r->rent !== null)<div><h5>Monthly rent of the new item</h5><p>{{ \App\Support\Tax::money($r->rent) }}</p></div>@endif
        <div><h5>DC copy</h5>@include('partials.dc-cell', ['kind' => 'replacement', 'id' => $r->id, 'file' => $r->dc_file])</div>
    </div>
</div>
@endsection
