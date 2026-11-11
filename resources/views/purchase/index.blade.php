@extends('layouts.app')
@section('title', 'Purchase')
@section('content')
<div class="pg"><h2>Purchase<small>Stock is added automatically when you save</small></h2><a class="ab" href="{{ route('purchase.create') }}">+ New Purchase</a></div>
<div class="cd">
    @include('partials.list-controls')
    <div class="tw"><table class="t">
        <thead><tr><th>No</th><th>Date</th><th>Supplier</th><th>Supplier inv</th><th class="num">Items added</th>@if (auth()->user()->role === 'admin')<th></th>@endif</tr></thead>
        <tbody>
            @forelse ($rows as $r)
                <tr class="clk" data-href="{{ route('purchase.show', $r->id) }}"><td>{{ $r->id }}</td><td>{{ \Carbon\Carbon::parse($r->created_at)->format('d/m/Y') }}</td><td>{{ $r->supplier }}</td><td>{{ $r->inv_no }}</td><td class="num">{{ $r->items }}</td>
                    @if (auth()->user()->role === 'admin')<td><div class="rowx" style="flex-wrap:nowrap"><a class="ab sm" href="{{ route('purchase.edit', $r->id) }}">Edit</a>@include('partials.delete-btn', ['action' => route('purchase.destroy', $r->id), 'what' => 'purchase', 'cls' => 'sm', 'confirm' => 'Delete purchase ' . $r->id . '? Its barcoded items and quantities will be removed from stock. This cannot be undone.'])</div></td>@endif</tr>
            @empty
                <tr><td class="empty" colspan="{{ auth()->user()->role === 'admin' ? 6 : 5 }}">Nothing to show</td></tr>
            @endforelse
        </tbody>
    </table></div>
    @include('partials.pager')
</div>
@endsection
