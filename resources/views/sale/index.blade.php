@extends('layouts.app')
@section('title', 'Sale')
@section('content')
<div class="pg"><h2>Sale<small>Stock goes down and the invoice is made when you save</small></h2><a class="ab" href="{{ route('sale.create') }}">+ New Sale</a></div>
<div class="cd">
    @include('partials.list-controls')
    <div class="tw"><table class="t">
        <thead><tr><th>Invoice</th><th>Date</th><th>Customer</th><th class="num">Items</th><th class="num">Amount</th>@if (auth()->user()->role === 'admin')<th></th>@endif</tr></thead>
        <tbody>
            @forelse ($rows as $r)
                <tr class="clk" data-href="{{ route('sale.show', $r->id) }}"><td>{{ $r->no }}</td><td>{{ \Carbon\Carbon::parse($r->created_at)->format('d/m/Y') }}</td><td>{{ $r->customer }}</td><td class="num">{{ $r->items }}</td><td class="num">{{ \App\Support\Tax::money($r->total) }}</td>
                    @if (auth()->user()->role === 'admin')<td><div class="rowx" style="flex-wrap:nowrap"><a class="ab sm" href="{{ route('sale.edit', $r->id) }}">Edit</a>@include('partials.delete-btn', ['action' => route('sale.destroy', $r->id), 'what' => 'invoice', 'cls' => 'sm', 'confirm' => 'Delete invoice ' . $r->no . '? Its items go back into stock. This cannot be undone.'])</div></td>@endif</tr>
            @empty
                <tr><td class="empty" colspan="{{ auth()->user()->role === 'admin' ? 6 : 5 }}">Nothing to show</td></tr>
            @endforelse
        </tbody>
    </table></div>
    @include('partials.pager')
</div>
@endsection
