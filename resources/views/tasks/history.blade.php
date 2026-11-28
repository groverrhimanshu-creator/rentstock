@extends('layouts.app')
@section('title', 'Task history')
@section('content')
@php($lab = ['added' => 'Added', 'done' => 'Done', 'moved' => 'Moved to another date', 'reopened' => 'Opened again', 'edited' => 'Edited'])
@php($tone = ['added' => 'info', 'done' => 'ok', 'moved' => 'warn', 'reopened' => 'idle', 'edited' => 'idle'])
<div class="pg"><h2>Task history<small>What was done with each task, by whom, and when</small></h2><a class="ab" href="{{ route('tasks.index') }}">Back to tasks</a></div>
<div class="cd">
    <form method="get" action="{{ route('tasks.history') }}" class="lt" style="flex-wrap:wrap;align-items:flex-end;gap:10px">
        <label class="f"><span>Person</span><select name="who"><option value="">Everybody</option>@foreach ($staff as $s)<option value="{{ $s->id }}" @selected($who === (int) $s->id)>{{ $s->name }}</option>@endforeach</select></label>
        <label class="f"><span>Action</span><select name="a"><option value="">All</option>@foreach ($lab as $k => $v)<option value="{{ $k }}" @selected($act === $k)>{{ $v }}</option>@endforeach</select></label>
        <label class="f"><span>From</span><input type="date" name="from" value="{{ $from }}"></label>
        <label class="f"><span>To</span><input type="date" name="to" value="{{ $to }}"></label>
        <label class="f"><span>Task</span><input type="search" name="q" value="{{ $q }}" placeholder="Type part of the task"></label>
        <button class="ab sm solid">Show</button><a class="ab sm" href="{{ route('tasks.history') }}">Reset</a>
    </form>
    <div class="tw"><table class="t">
        <thead><tr><th>When</th><th>Task</th><th>For</th><th>What happened</th><th>By</th><th>Note / reason</th></tr></thead>
        <tbody>
            @forelse ($rows as $e)
                <tr><td>{{ \Carbon\Carbon::parse($e->created_at)->format('d/m/Y H:i') }}</td><td><b>{{ $e->title }}</b></td><td>{{ $e->for_name ?? '-' }}</td>
                    <td><span class="pill {{ $tone[$e->action] ?? 'idle' }}">{{ $lab[$e->action] ?? $e->action }}</span>
                        @if ($e->action === 'moved')<br><small class="hint">{{ \Carbon\Carbon::parse($e->from_date)->format('d/m/Y') }} &rarr; {{ \Carbon\Carbon::parse($e->to_date)->format('d/m/Y') }}</small>
                        @elseif ($e->action === 'added' && $e->to_date)<br><small class="hint">due {{ \Carbon\Carbon::parse($e->to_date)->format('d/m/Y') }}</small>@endif</td>
                    <td>{{ $e->by_name ?? '-' }}</td><td>{{ $e->note ?: '-' }}</td></tr>
            @empty
                <tr><td class="empty" colspan="6">Nothing recorded yet. Actions are recorded from now on.</td></tr>
            @endforelse
        </tbody>
    </table></div>
    @include('partials.pager')
</div>
@endsection
