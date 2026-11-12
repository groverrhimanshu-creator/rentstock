@extends('layouts.app')
@section('title', 'Stock entries')
@section('content')
<div class="pg"><h2>Stock entries<small>Stock added directly or by import</small></h2><a class="ab" href="{{ route('stock.import') }}">Import Stock</a></div>
<div class="cd">
    @include('partials.list-controls')
    <div class="tw"><table class="t">
        <thead><tr><th>Entry no</th><th>Date</th><th>Store</th><th class="num">Items</th><th></th></tr></thead>
        <tbody>
            @forelse ($rows as $r)
                <tr><td>{{ $r->id }}</td><td>{{ \Carbon\Carbon::parse($r->created_at)->format('d/m/Y') }}</td><td>{{ $r->store }}</td><td class="num">{{ $r->piece_count }}</td>
                    <td><div class="rowx" style="flex-wrap:nowrap"><a class="ab sm" href="{{ route('stock.entry.export', $r->id) }}">Download</a>@if (auth()->user()->role === 'admin')<a class="ab sm" href="{{ route('stock.entry.edit', $r->id) }}">Edit</a>@include('partials.delete-btn', ['action' => route('stock.entry.destroy', $r->id), 'what' => 'stock entry', 'cls' => 'sm', 'confirm' => 'Delete stock entry ' . $r->id . '? All ' . $r->piece_count . ' items of this entry will be removed. This cannot be undone.'])@endif</div></td></tr>
            @empty
                <tr><td class="empty" colspan="5">Nothing to show</td></tr>
            @endforelse
        </tbody>
    </table></div>
    @include('partials.pager')
</div>
@endsection
