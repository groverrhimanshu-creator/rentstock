@extends('layouts.app')
@section('title', 'Dashboard')
@section('content')
<div class="pg"><h2>Dashboard<small>Welcome, {{ auth()->user()->name }}</small></h2></div>
@php
    $cards = [
        ['box', 'In stock', $c['in_stock'], 'inv:in_stock', ''],
        ['rent', 'On rent', $c['rented'], 'inv:rented', ''],
        ['warn', 'Overdue returns', $c['overdue'], 'reports', 'alert'],
        ['wrench', 'In repair', $c['repair'], 'inv:repair', ''],
        ['bars', 'Quantity items in stock', $c['qty'], 'inv:', ''],
        ['warn', 'Payments overdue', $c['pay_overdue'], 'payments', 'alert'],
    ];
@endphp
<div class="kp">
    @foreach ($cards as [$icon, $label, $value, $screen, $cls])
        <a class="kc {{ $cls }}" href="{{ str_starts_with($screen, 'inv:') ? route('stock.inventory', array_filter(['status' => substr($screen, 4)])) : ($screen === 'reports' ? route('reports.index', ['tab' => 2]) : route($screen . '.index')) }}">{!! \App\Support\Icons::get($icon) !!}<span>{{ $label }}</span><b class="plain">{{ $value }}</b></a>
    @endforeach
</div>
<div class="cols">
    <div class="cd"><h3>Returns due soon</h3>
        @forelse ($due as $d)
            <p class="hint"><a class="bc" href="{{ route('stock.device', $d->code) }}">{{ $d->bc }}</a> · {{ $d->model }} · {{ $d->customer }} · due {{ \Carbon\Carbon::parse($d->return_by)->format('d/m/Y') }}</p>
        @empty
            <p class="hint">Nothing due</p>
        @endforelse
    </div>
    <div class="cd"><h3>Latest rentals</h3>
        @forelse ($latest as $r)
            <a class="lrow" href="{{ route('rental.show', $r->id) }}"><span class="l"><b>{{ $r->no }}</b> · {{ $r->customer }}<br><small>{{ \Carbon\Carbon::parse($r->created_at)->format('d/m/Y') }}</small></span><span class="pill info">{{ $r->items }} items</span></a>
        @empty
            <p class="hint">Nothing yet</p>
        @endforelse
    </div>
</div>
@endsection
