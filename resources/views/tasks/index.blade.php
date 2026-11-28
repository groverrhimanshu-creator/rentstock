@extends('layouts.app')
@section('title', 'Tasks')
@section('content')
@php($can = fn ($a) => \App\Support\Perm::has('tasks.' . $a))
@php($team_on = \App\Support\Features::taskAssign())
@php($qs = ['scope' => $scope, 'who' => $who ?: null])
<div class="pg"><h2>Tasks<small>A task still open after its due date shows in red, here and on the dashboard</small></h2>@if ($manager || \App\Support\Perm::isAdmin())<a class="ab" href="{{ route('tasks.history') }}">Task history report</a>@endif</div>
@if ($errors->any())<div class="msg bad">{{ $errors->first() }}</div>@endif
@if ($can('create'))
<div class="cd"><h3>Add a task</h3>
    <form method="post" action="{{ route('tasks.store') }}" class="fg" autocomplete="off">@csrf
        <label class="f" style="grid-column:span 2"><span>Task</span><input name="title" value="{{ old('title') }}" maxlength="200" placeholder="for example: send the quotation to KPI" required></label>
        <label class="f"><span>Due date</span><input type="date" name="due_date" value="{{ old('due_date', $today) }}" required></label>
        @if ($manager)
            <label class="f"><span>Give it to</span><select name="assigned_to">@foreach ($staff as $s)<option value="{{ $s->id }}" @selected((int) old('assigned_to', $me) === (int) $s->id)>{{ $s->name }}{{ $s->id === $me ? ' (me)' : '' }} - {{ $s->role === 'admin' ? 'Admin' : 'SubAdmin' }}</option>@endforeach</select></label>
        @endif
        <label class="f" style="grid-column:1/-1"><span>Notes (optional)</span><input name="notes" value="{{ old('notes') }}" maxlength="1000"></label>
        <div><button class="ab solid">Add task</button></div>
    </form>
</div>
@endif
@if ($manager)
<div class="cd"><h3>Who has what</h3>
    <div class="tw"><table class="t">
        <thead><tr><th>Person</th><th class="num">Open</th><th class="num">Overdue</th><th class="num">Due today</th><th class="num">Done</th></tr></thead>
        <tbody>
            @foreach ($team as $m)
                <tr class="clk" data-href="{{ route('tasks.index', ['scope' => 'all', 'who' => $m->id]) }}">
                    <td><b>{{ $m->name }}</b> <small class="hint">{{ $m->role === 'admin' ? 'Admin' : 'SubAdmin' }}{{ $m->id === $me ? ' (me)' : '' }}</small></td>
                    <td class="num">{{ $m->n_open }}</td>
                    <td class="num" @if ($m->n_late > 0) style="color:#B3261E;font-weight:700" @endif>{{ $m->n_late }}</td>
                    <td class="num">{{ $m->n_today }}</td><td class="num">{{ $m->n_done }}</td>
                </tr>
            @endforeach
        </tbody>
    </table></div>
    <p class="hint">Click a person to see their tasks.</p>
</div>
@endif
<div class="cd">
    @if ($team_on)
    <div class="tabsx">
        <a href="{{ route('tasks.index') }}" aria-selected="{{ $scope === 'mine' ? 'true' : 'false' }}">Given to me</a>
        <a href="{{ route('tasks.index', ['scope' => 'given']) }}" aria-selected="{{ $scope === 'given' ? 'true' : 'false' }}">Given by me</a>
        @if ($manager)<a href="{{ route('tasks.index', ['scope' => 'all']) }}" aria-selected="{{ $scope === 'all' ? 'true' : 'false' }}">Everybody</a>@endif
    </div>
    @endif
    @if ($who)<p class="hint">Showing the tasks of <b>{{ optional($team->firstWhere('id', $who))->name }}</b>. <a href="{{ route('tasks.index', ['scope' => 'all']) }}">Show everybody</a></p>@endif
    <div class="tabsx">
        @foreach (['open' => 'Open', 'overdue' => 'Overdue', 'today' => 'Due today', 'done' => 'Done'] as $k => $label)
            <a href="{{ route('tasks.index', array_filter($qs + ['f' => $k])) }}" aria-selected="{{ $f === $k ? 'true' : 'false' }}">{{ $label }} ({{ $n[$k] }})</a>
        @endforeach
    </div>
    <div class="tw"><table class="t">
        <thead><tr><th>Task</th>@if ($team_on)<th>For</th><th>Given by</th>@endif<th>Due</th><th>Status</th><th></th></tr></thead>
        <tbody>
            @forelse ($rows as $r)
                @php($late = $r->status === 'open' && $r->due_date < $today)
                @php($days = (int) \Carbon\Carbon::parse($r->due_date)->diffInDays(\Carbon\Carbon::parse($today)))
                @php($mineOrBoss = $manager || (int) $r->created_by === $me)
                <tr @if ($late) style="background:rgba(179,38,30,.07)" @endif>
                    <td><b>{{ $r->title }}</b>@if ($r->notes)<br><small class="hint">{{ $r->notes }}</small>@endif @if ($r->moved > 0)<br><small class="hint">moved {{ $r->moved }} time{{ $r->moved === 1 ? '' : 's' }}</small>@endif</td>
                    @if ($team_on)<td>{{ (int) $r->user_id === $me ? 'Me' : $r->assignee }}</td>
                    <td>{{ (int) $r->created_by === $me ? 'Me' : ($r->giver ?? '-') }}</td>@endif
                    <td>{{ \Carbon\Carbon::parse($r->due_date)->format('d/m/Y') }}</td>
                    <td>
                        @if ($r->status === 'done')<span class="pill ok">Done {{ \Carbon\Carbon::parse($r->done_at)->format('d/m/Y') }}</span>
                        @elseif ($late)<span class="pill bad" style="background:#B3261E;color:#fff">Overdue by {{ $days }} day{{ $days === 1 ? '' : 's' }}</span>
                        @elseif ($r->due_date === $today)<span class="pill warn">Due today</span>
                        @else<span class="pill info">In {{ $days }} day{{ $days === 1 ? '' : 's' }}</span>@endif
                    </td>
                    <td>
                        <div class="rowx" style="flex-wrap:wrap;gap:6px">
                            @if ($can('edit'))
                                @if ($r->status === 'open')
                                    <form method="post" action="{{ route('tasks.done', $r->id) }}" style="margin:0;display:flex;gap:4px">@csrf<input name="note" maxlength="500" placeholder="Note (required)" required style="width:150px"><button class="ab sm solid">Done</button></form>
                                    <form method="post" action="{{ route('tasks.reschedule', $r->id) }}" style="margin:0;display:flex;gap:4px">@csrf<input type="date" name="due_date" value="{{ \Carbon\Carbon::parse(max($r->due_date, $today))->addDay()->toDateString() }}" style="width:140px"><input name="note" maxlength="500" placeholder="Reason (required)" required style="width:160px"><button class="ab sm">Move</button></form>
                                    @if ($mineOrBoss)<a class="ab sm" href="{{ route('tasks.edit', $r->id) }}">Edit</a>@endif
                                @else
                                    <form method="post" action="{{ route('tasks.reopen', $r->id) }}" style="margin:0">@csrf<button class="ab sm">Open again</button></form>
                                @endif
                            @endif
                            @if ($can('delete') && $mineOrBoss)@include('partials.delete-btn', ['action' => route('tasks.destroy', $r->id), 'what' => 'task', 'cls' => 'sm'])@endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td class="empty" colspan="{{ $team_on ? 6 : 4 }}">{{ $f === 'done' ? 'Nothing done yet' : 'No tasks here' }}</td></tr>
            @endforelse
        </tbody>
    </table></div>
    @include('partials.pager')
</div>
@endsection
