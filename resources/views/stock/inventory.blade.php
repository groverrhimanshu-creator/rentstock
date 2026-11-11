@extends('layouts.app')
@section('title', 'Inventory')
@section('content')
<div class="pg"><h2>Inventory<small>Every barcoded item and its status</small></h2></div>
<div class="cd">
    <div class="tabsx">
        @foreach (['' => 'All', 'in_stock' => 'In stock', 'rented' => 'On rent', 'repair' => 'In repair', 'sold' => 'Sold'] as $k => $label)
            <a href="{{ route('stock.inventory', array_filter(['status' => $k, 'n' => $n, 'q' => $q])) }}" aria-selected="{{ $status === $k ? 'true' : 'false' }}">{{ $label }}</a>
        @endforeach
    </div>
    <form data-list method="get" action="{{ route('stock.inventory') }}" class="lt">
        <input type="hidden" name="status" value="{{ $status }}">
        <label class="f"><span>Show</span><select name="n">@foreach (\App\Support\Pager::SIZES as $o)<option value="{{ $o }}" @selected($n === $o)>{{ $o }}</option>@endforeach</select></label>
        <div class="sr"><label class="f"><span>Search</span><input name="q" value="{{ $q }}" placeholder="Type to search" autocomplete="off"></label></div>
    </form>
    @php($prefix = \App\Support\Barcodes::prefix())
    <div class="tw"><table class="t">
        <thead><tr><th>Barcode</th><th>Model</th><th>Brand tag</th><th>Config</th><th>Status</th><th>With</th></tr></thead>
        <tbody>
            @forelse ($rows as $r)
                <tr class="clk" data-href="{{ route('stock.device', $r->code) }}">
                    <td class="bc">{{ $prefix . $r->code }}</td><td>{{ $r->model }}</td><td>{{ $r->brand_tag }}</td><td>{{ $r->config }}</td>
                    <td>{!! \App\Support\Stock::pill($r->status) !!}</td><td>{{ $r->customer ?? '' }}</td>
                </tr>
            @empty
                <tr><td class="empty" colspan="6">Nothing to show</td></tr>
            @endforelse
        </tbody>
    </table></div>
    @include('partials.pager')
</div>
<div class="cd"><h3>Items kept by quantity (no barcode)</h3>
    <div class="tw"><table class="t">
        <thead><tr><th>Item</th><th>Category</th><th class="num">In stock</th></tr></thead>
        <tbody>
            @forelse ($qtyRows as $r)
                <tr><td>{{ $r->name }}</td><td>{{ $r->category }}</td><td class="num">{{ $r->qty }}</td></tr>
            @empty
                <tr><td class="empty" colspan="3">Nothing to show</td></tr>
            @endforelse
        </tbody>
    </table></div>
</div>
@endsection
