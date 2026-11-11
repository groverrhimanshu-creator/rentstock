@extends('layouts.app')
@section('title', 'My Rentals')
@section('content')
@use('App\Support\Tax')
@use('App\Support\Docs')
@use('App\Support\Icons')
<div class="pg"><h2>Welcome, {{ $name }}<small>What you currently have on rent</small></h2></div>
<div class="kp">
    <div class="kc">{!! Icons::get('rent') !!}<span>Items on rent</span><b class="plain">{{ $count }}</b></div>
    <div class="kc">{!! Icons::get('box') !!}<span>Different models</span><b class="plain">{{ $byModel->count() }}</b></div>
    <div class="kc{{ $past ? ' alert' : '' }}">{!! Icons::get('warn') !!}<span>Past return date</span><b class="plain">{{ $past }}</b></div>
    <div class="kc{{ $dues ? ' alert' : '' }}">{!! Icons::get('rent') !!}<span>Payment due</span><b class="plain">{{ Tax::money($dueTotal) }}</b></div>
</div>
@if ($dues)
<div class="cd"><h3>Unpaid invoices</h3>
    @foreach ($dues as $x)
        <a class="lrow" href="{{ $x->kind === 'rental' ? route('my.rental.show', $x->id) : route('my.sale.show', $x->id) }}"><span class="l"><b>{{ $x->no }}</b><br>Due {{ Docs::d($x->due) }}</span><span class="pill {{ $x->late > 0 ? 'bad' : 'info' }}">{{ Tax::money($x->bal) }}{{ $x->late > 0 ? ' · overdue' : '' }}</span></a>
    @endforeach
</div>
@endif
<div class="cols"><div class="cd"><h3>By model</h3>
    @forelse ($byModel as $m)<div class="lrow"><span class="l"><b>{{ $m->name }}</b></span><span class="pill info">{{ $m->n }}</span></div>@empty<p class="hint">Nothing on rent right now.</p>@endforelse
</div></div>
<div class="cd"><h3>All items with you</h3>
    @include('partials.list-controls')
    <div class="tw"><table class="t">
        <thead><tr><th>Barcode</th><th>Item</th><th>Config</th><th>Since</th><th>Return by</th><th>Challan</th></tr></thead>
        <tbody>
            @forelse ($rows as $d)
                <tr><td><span class="bc">{{ $prefix . $d->code }}</span></td><td>{{ $d->model }}</td><td>{{ $d->config }}</td><td>{{ Docs::d($d->rented_at) }}</td><td>{{ $d->return_by ? Docs::d($d->return_by) : '-' }}</td><td>{{ $d->rent_no }}</td></tr>
            @empty
                <tr><td class="empty" colspan="6">Nothing to show</td></tr>
            @endforelse
        </tbody>
    </table></div>
    @include('partials.pager')
</div>
@endsection
