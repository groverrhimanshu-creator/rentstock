@extends('layouts.app')
@section('title', 'Rental')
@section('content')
@php($admin = auth()->user()->role === 'admin')
<div class="pg"><h2>Rental<small>Challans for items sent out. Rental invoices are made from Sale (choose Rental invoice).</small></h2><a class="ab" href="{{ route('rental.create') }}">+ New Challan</a></div>
<div class="cd">
    @include('partials.list-controls')
    <div class="tw"><table class="t">
        <thead><tr><th>Number</th><th>Type</th><th>Date</th><th>Customer</th><th class="num">Items</th><th class="num">Still out</th>@if ($admin)<th></th>@endif</tr></thead>
        <tbody>
            @forelse ($rows as $r)
                <tr class="clk" data-href="{{ route('rental.show', $r->id) }}"><td>{{ $r->no }}</td><td>{{ $r->kind === 'invoice' ? 'Rental invoice' : 'Challan' }}</td><td>{{ \Carbon\Carbon::parse($r->created_at)->format('d/m/Y') }}</td><td>{{ $r->customer }}</td><td class="num">{{ $r->items }}</td><td class="num">{{ $r->kind === 'invoice' ? '-' : $r->still }}</td>
                    @if ($admin)<td><div class="rowx" style="flex-wrap:nowrap"><a class="ab sm" href="{{ route('rental.edit', $r->id) }}">Edit</a>@include('partials.delete-btn', ['action' => route('rental.destroy', $r->id), 'what' => $r->kind === 'invoice' ? 'rental invoice' : 'challan', 'cls' => 'sm', 'confirm' => $r->kind === 'invoice' ? 'Delete rental invoice ' . $r->no . '? This cannot be undone.' : 'Delete challan ' . $r->no . '? Its items go back into stock. This cannot be undone.'])</div></td>@endif</tr>
            @empty
                <tr><td class="empty" colspan="{{ $admin ? 7 : 6 }}">Nothing to show</td></tr>
            @endforelse
        </tbody>
    </table></div>
    @include('partials.pager')
</div>
@endsection
