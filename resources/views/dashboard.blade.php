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
        ['warn', 'My tasks overdue', $c['tasks_overdue'], 'tasks', $c['tasks_overdue'] > 0 ? 'alert' : ''],
    ];
@endphp
<div class="kp">
    @foreach ($cards as [$icon, $label, $value, $screen, $cls])
        @php($need = str_starts_with($screen, 'inv:') ? 'stock.view' : ($screen === 'reports' ? 'reports.view' : $screen . '.view'))
        @if (\App\Support\Perm::has($need))
        <a class="kc {{ $cls }}" href="{{ str_starts_with($screen, 'inv:') ? route('stock.inventory', array_filter(['status' => substr($screen, 4)])) : ($screen === 'reports' ? route('reports.index', ['tab' => 2]) : route($screen . '.index')) }}">{!! \App\Support\Icons::get($icon) !!}<span>{{ $label }}</span><b class="plain">{{ $value }}</b></a>
        @else
        <span class="kc {{ $cls }}">{!! \App\Support\Icons::get($icon) !!}<span>{{ $label }}</span><b class="plain">{{ $value }}</b></span>
        @endif
    @endforeach
</div>
@if (\App\Support\Perm::has('tasks.view'))
<div class="cd" @if ($c['tasks_overdue'] > 0) style="border-color:#B3261E" @endif><h3>My tasks due<small style="margin-left:8px"><a href="{{ route('tasks.index') }}">All tasks</a></small></h3>
    @if (($c['team_overdue'] ?? 0) > 0)<p class="hint" style="color:#B3261E;font-weight:700">{{ $c['team_overdue'] }} task{{ $c['team_overdue'] === 1 ? '' : 's' }} of your team {{ $c['team_overdue'] === 1 ? 'is' : 'are' }} overdue. <a href="{{ route('tasks.index', ['scope' => 'all', 'f' => 'overdue']) }}">See who</a></p>@endif
    @forelse ($tasks as $tk)
        @php($late = $tk->due_date < now()->toDateString())
        <div class="rowx" style="justify-content:space-between;flex-wrap:wrap;gap:8px;padding:6px 0;border-top:1px solid var(--a-line)">
            <span><b>{{ $tk->title }}</b> · <span style="color:{{ $late ? '#B3261E' : 'inherit' }};font-weight:{{ $late ? 700 : 400 }}">{{ $late ? 'Overdue, was due ' : 'Due today ' }}{{ \Carbon\Carbon::parse($tk->due_date)->format('d/m/Y') }}</span></span>
            @if (\App\Support\Perm::has('tasks.edit'))
                <span class="rowx" style="gap:6px">
                    <form method="post" action="{{ route('tasks.done', $tk->id) }}" style="margin:0">@csrf<button class="ab sm solid">Done</button></form>
                    <form method="post" action="{{ route('tasks.reschedule', $tk->id) }}" style="margin:0;display:flex;gap:4px">@csrf<input type="date" name="due_date" value="{{ now()->addDay()->toDateString() }}" style="width:140px"><button class="ab sm">Move</button></form>
                </span>
            @endif
        </div>
    @empty
        <p class="hint">No task is due or overdue.</p>
    @endforelse
</div>
@endif
<div class="cols">
    <div class="cd"><h3>Returns due soon</h3>
        @forelse ($due as $d)
            <p class="hint">@if (\App\Support\Perm::has('stock.view'))<a class="bc" href="{{ route('stock.device', $d->code) }}">{{ $d->bc }}</a>@else<span class="bc">{{ $d->bc }}</span>@endif · {{ $d->model }} · {{ $d->customer }} · due {{ \Carbon\Carbon::parse($d->return_by)->format('d/m/Y') }}</p>
        @empty
            <p class="hint">Nothing due</p>
        @endforelse
    </div>
    <div class="cd"><h3>Latest rentals</h3>
        @forelse ($latest as $r)
            <{{ \App\Support\Perm::has('rental.view|sale.view') ? 'a' : 'div' }} class="lrow" @if (\App\Support\Perm::has('rental.view|sale.view')) href="{{ route('rental.show', $r->id) }}" @endif><span class="l"><b>{{ $r->no }}</b> · {{ $r->customer }}<br><small>{{ \Carbon\Carbon::parse($r->created_at)->format('d/m/Y') }}</small></span><span class="pill info">{{ $r->items }} items</span></{{ \App\Support\Perm::has('rental.view|sale.view') ? 'a' : 'div' }}>
        @empty
            <p class="hint">Nothing yet</p>
        @endforelse
    </div>
</div>
@endsection
