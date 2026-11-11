@extends('layouts.app')
@section('title', 'Return History')
@section('content')
<div class="pg"><h2>Return history<small>Items you have given back</small></h2></div>
<div class="cd">
    @include('partials.list-controls')
    <div class="tw"><table class="t"><thead><tr><th>Return no</th><th>Date</th><th class="num">Items</th><th>Reason</th></tr></thead><tbody>
        @forelse ($rows as $r)<tr><td>{{ $r->no }}</td><td>{{ \Carbon\Carbon::parse($r->created_at)->format('d/m/Y') }}</td><td class="num">{{ $r->items }}</td><td>{{ $r->reason }}</td></tr>@empty<tr><td class="empty" colspan="4">Nothing to show</td></tr>@endforelse
    </tbody></table></div>
    @include('partials.pager')
</div>
@endsection
