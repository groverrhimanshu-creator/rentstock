@extends('layouts.app')
@section('title', 'Purchase ' . $p->id)
@section('content')
<div class="pg"><h2>Purchase {{ $p->id }}</h2><div class="rowx">
    @if (auth()->user()->role === 'admin')
        <a class="ab" href="{{ route('purchase.edit', $p->id) }}">Edit</a>
        @include('partials.delete-btn', ['action' => route('purchase.destroy', $p->id), 'what' => 'purchase', 'blocked' => $blocked, 'confirm' => 'Delete purchase ' . $p->id . '? Its barcoded items and quantities will be removed from stock. This cannot be undone.'])
    @endif
    <a class="ab" href="{{ route('purchase.index') }}">Back</a></div></div>
<div class="msg ok">Stock updated: {{ $barcoded }} new barcoded items and {{ $qtyAdded }} quantity items added.</div>
<div class="paper">
    @include('partials.paper-head', ['title' => 'PURCHASE', 'sub' => 'No: ' . $p->id . '<br>Date: ' . \Carbon\Carbon::parse($p->created_at)->format('d/m/Y') . '<br>Supplier inv: ' . e($p->inv_no)])
    <div class="three">
        @include('partials.party', ['h' => 'Supplier', 'name' => $p->sname, 'address' => $p->address, 'city' => $p->city, 'state' => $p->state, 'code' => $p->state_code, 'gstin' => $p->gstin, 'phone' => $p->phone])
        <div><h5>Store</h5><p>{{ $p->store }}</p></div>
        <div><h5>Payment</h5><p>{{ $p->pay_mode }}</p></div>
    </div>
    <table>
        <thead><tr><th>#</th><th>Item</th><th class="num">Qty</th><th class="num">Rate</th><th class="num">Amount</th><th>New barcodes</th></tr></thead>
        <tbody>
            @foreach ($lines as $i => $l)
                <tr><td>{{ $i + 1 }}</td><td><b>{{ $l->name }}</b></td><td class="num">{{ $l->qty }}</td><td class="num">{{ \App\Support\Tax::money($l->rate) }}</td><td class="num">{{ \App\Support\Tax::money($l->qty * $l->rate) }}</td>
                    <td><span class="codes">{{ isset($codes[$l->id]) ? $codes[$l->id]->map(fn ($c) => \App\Support\Barcodes::text($c->code))->implode(', ') : 'Quantity item' }}</span></td></tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection
