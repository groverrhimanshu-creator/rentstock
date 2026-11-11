@extends('layouts.app')
@section('title', 'Upgrade Assets')
@section('content')
@php($admin = auth()->user()->role === 'admin')
<div class="pg"><h2>Upgrade Assets<small>RAM, SSD and part replacements. The device keeps its barcode and every change is logged with its date</small></h2><div class="rowx"><a class="ab" href="{{ route('consumption.export', ['q' => $q]) }}">Export log</a><a class="ab" href="{{ route('consumption.create') }}">+ New Upgrade</a></div></div>
<div class="cd">
    @include('partials.list-controls')
    <div class="tw"><table class="t">
        <thead><tr><th>Date</th><th>Barcode</th><th>Device</th><th>Type</th><th>What changed</th><th>New config</th><th>Part from stock</th><th></th>@if ($admin)<th></th>@endif</tr></thead>
        <tbody>
            @forelse ($rows as $c)
                <tr><td>{{ \Carbon\Carbon::parse($c->created_at)->format('d/m/Y') }}</td><td><span class="bc">{{ $prefix . $c->code }}</span></td><td>{{ $c->model }}</td><td>{{ $c->part }}</td><td>{{ $c->from_val !== '' ? $c->from_val . ' to ' . $c->to_val : $c->to_val }}</td><td>{{ $c->new_cfg }}</td><td>{{ $c->used_name ?: '-' }}</td><td><div class="rowx"><a class="ab sm" href="{{ route('stock.device', $c->code) }}">Full log</a><a class="ab sm" href="{{ route('barcodes.label', ['code' => $c->code, 'back' => route('consumption.index')]) }}">Print label</a></div></td>
                    @if ($admin)<td>@include('partials.delete-btn', ['action' => route('consumption.destroy', $c->id), 'what' => 'upgrade', 'cls' => 'sm', 'confirm' => 'Delete this upgrade? The old config comes back and the part goes back into stock. This cannot be undone.'])</td>@endif</tr>
            @empty
                <tr><td class="empty" colspan="{{ $admin ? 9 : 8 }}">Nothing to show</td></tr>
            @endforelse
        </tbody>
    </table></div>
    @include('partials.pager')
</div>
@endsection
