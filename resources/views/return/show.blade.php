@extends('layouts.app')
@section('title', 'Return ' . $r->no)
@section('content')
<div class="pg"><h2>Return {{ $r->no }}<small>{{ \Carbon\Carbon::parse($r->created_at)->format('d/m/Y') }}</small></h2><div class="rowx">
    @if (\App\Support\Perm::has('return.delete'))
        @include('partials.delete-btn', ['action' => route('return.destroy', $r->id), 'what' => 'return', 'confirm' => 'Delete return ' . $r->no . '? Its items go back on rent with the customer. This cannot be undone.'])
    @endif
    <a class="ab" href="{{ route('return.index') }}">Back</a></div></div>
<div class="paper">
    @include('partials.paper-head', ['title' => 'RETURN', 'sub' => 'No: ' . e($r->no) . '<br>Date: ' . \Carbon\Carbon::parse($r->created_at)->format('d/m/Y')])
    <div class="three">
        @include('partials.party', ['h' => 'Customer', 'name' => $r->cname, 'address' => $r->address, 'city' => $r->city, 'state' => $r->state, 'code' => $r->state_code, 'gstin' => $r->gstin, 'phone' => $r->phone])
        <div><h5>Reason</h5><p>{{ $r->reason ?: '-' }}</p></div>
        <div><h5>Entered by</h5><p>{{ $user ?: '-' }}</p></div>
    </div>
    <table>
        <thead><tr><th>#</th><th>Barcode</th><th>Item</th><th>Config</th><th>Brand tag</th><th>Condition</th><th>Reason</th><th>Note</th><th>Now</th></tr></thead>
        <tbody>
            @foreach ($lines as $i => $l)
                <tr><td>{{ $i + 1 }}</td><td><span class="bc">{{ $prefix . $l->code }}</span></td><td><b>{{ $l->model }}</b></td><td>{{ $l->config ?: '-' }}</td><td>{{ $l->brand_tag ?: '-' }}</td>
                    <td>{{ $l->cond === 'Damaged' ? 'Damaged/Faulty' : $l->cond }}</td><td>{{ $l->reason ?: '-' }}</td><td>{{ $l->note ?: '-' }}</td><td>{!! \App\Support\Stock::pill($l->status) !!}</td></tr>
            @endforeach
        </tbody>
    </table>
    @if ($r->notes)<p style="margin-top:10px"><b>Notes:</b> {{ $r->notes }}</p>@endif
    <div class="three" style="margin-top:14px"><div><h5>DC copy</h5>@include('partials.dc-cell', ['kind' => 'return', 'id' => $r->id, 'file' => $r->dc_file])</div></div>
</div>
@endsection
