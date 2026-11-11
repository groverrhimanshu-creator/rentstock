@extends('layouts.app')
@section('title', 'Replacement')
@section('content')
@php($admin = auth()->user()->role === 'admin')
<div class="pg"><h2>Replacement<small>Swap a faulty item for a working one</small></h2><a class="ab" href="{{ route('replacement.create') }}">+ New Replacement</a></div>
<div class="cd">
    @include('partials.list-controls')
    <div class="tw"><table class="t">
        <thead><tr><th>Date</th><th>Type</th><th>Customer</th><th>Faulty</th><th>Replaced with</th><th>Credit note</th>@if ($admin)<th></th>@endif</tr></thead>
        <tbody>
            @forelse ($rows as $r)
                <tr><td>{{ \Carbon\Carbon::parse($r->created_at)->format('d/m/Y') }}</td><td>{{ $r->kind }}</td><td>{{ $r->customer }}</td><td><span class="bc">{{ $prefix . $r->old_code }}</span></td><td><span class="bc">{{ $prefix . $r->new_code }}</span></td><td>{{ $r->cn ?: '-' }}</td>
                    @if ($admin)<td>@include('partials.delete-btn', ['action' => route('replacement.destroy', $r->id), 'what' => 'replacement', 'cls' => 'sm', 'confirm' => 'Delete this replacement? Both items go back where they were. This cannot be undone.'])</td>@endif</tr>
            @empty
                <tr><td class="empty" colspan="{{ $admin ? 7 : 6 }}">Nothing to show</td></tr>
            @endforelse
        </tbody>
    </table></div>
    @include('partials.pager')
</div>
@endsection
