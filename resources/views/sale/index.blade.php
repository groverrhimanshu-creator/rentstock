@extends('layouts.app')
@section('title', 'Sale')
@section('content')
@php($rowact = \App\Support\Perm::has('sale.edit|sale.delete'))
<div class="pg"><h2>Sale<small>Stock goes down and the invoice is made when you save</small></h2>@if (\App\Support\Perm::has('sale.create'))<a class="ab" href="{{ route('sale.create') }}">+ New Sale</a>@endif</div>
<div class="cd">
    @include('partials.list-controls')
    <div class="tw"><table class="t">
        <thead><tr><th>Invoice</th><th>Date</th><th>Customer</th><th class="num">Items</th><th class="num">Amount</th>@if ($rowact)<th></th>@endif</tr></thead>
        <tbody>
            @forelse ($rows as $r)
                <tr class="clk" data-href="{{ route('sale.show', $r->id) }}"><td>{{ $r->no }}</td><td>{{ \Carbon\Carbon::parse($r->created_at)->format('d/m/Y') }}</td><td>{{ $r->customer }}</td><td class="num">{{ $r->items }}</td><td class="num">{{ \App\Support\Tax::money($r->total) }}</td>
                    @if ($rowact)<td><div class="rowx" style="flex-wrap:nowrap">@if (\App\Support\Perm::has('sale.edit'))<a class="ab sm" href="{{ route('sale.edit', $r->id) }}">Edit</a>@endif @if (\App\Support\Perm::has('sale.delete'))@include('partials.delete-btn', ['action' => route('sale.destroy', $r->id), 'what' => 'invoice', 'cls' => 'sm', 'confirm' => 'Delete invoice ' . $r->no . '? Its items go back into stock. This cannot be undone.'])@endif</div></td>@endif</tr>
            @empty
                <tr><td class="empty" colspan="{{ $rowact ? 6 : 5 }}">Nothing to show</td></tr>
            @endforelse
        </tbody>
    </table></div>
    @include('partials.pager')
</div>
@endsection
