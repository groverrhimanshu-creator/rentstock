@extends('layouts.app')
@section('title', 'Return')
@section('content')
@php($admin = \App\Support\Perm::has('return.delete'))
<div class="pg"><h2>Return<small>Scan items as they come back</small></h2>@if (\App\Support\Perm::has('return.create'))<a class="ab" href="{{ route('return.create') }}">+ New Return</a>@endif</div>
<div class="cd">
    @include('partials.list-controls')
    <div class="tw"><table class="t">
        <thead><tr><th>Return no</th><th>Date</th><th>Customer</th><th class="num">Items</th><th>Reason</th><th>DC copy</th>@if ($admin)<th></th>@endif</tr></thead>
        <tbody>
            @forelse ($rows as $r)
                <tr class="clk" data-href="{{ route('return.show', $r->id) }}"><td>{{ $r->no }}</td><td>{{ \Carbon\Carbon::parse($r->created_at)->format('d/m/Y') }}</td><td>{{ $r->customer }}</td><td class="num">{{ $r->items }}</td><td>{{ $r->reason }}</td><td>@include('partials.dc-cell', ['kind' => 'return', 'id' => $r->id, 'file' => $r->dc_file])</td>
                    @if ($admin)<td>@include('partials.delete-btn', ['action' => route('return.destroy', $r->id), 'what' => 'return', 'cls' => 'sm', 'confirm' => 'Delete return ' . $r->no . '? Its items go back on rent with the customer. This cannot be undone.'])</td>@endif</tr>
            @empty
                <tr><td class="empty" colspan="{{ $admin ? 7 : 6 }}">Nothing to show</td></tr>
            @endforelse
        </tbody>
    </table></div>
    @include('partials.pager')
</div>
@endsection
