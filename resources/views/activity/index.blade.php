@extends('layouts.app')
@section('title', 'Activity log')
@section('content')
<div class="pg"><h2>Activity log<small>Who did what, and when</small></h2>
    <a class="ab orange" href="{{ route('activity.csv', request()->query()) }}">Download CSV</a></div>
<div class="cd">
    <form method="get" action="{{ route('activity.index') }}" class="lt" style="flex-wrap:wrap;align-items:flex-end;gap:10px">
        <label class="f"><span>From</span><input type="date" name="from" value="{{ $from }}"></label>
        <label class="f"><span>To</span><input type="date" name="to" value="{{ $to }}"></label>
        <label class="f"><span>User</span><select name="user"><option value="">Everybody</option>@foreach ($users as $u)<option value="{{ $u->id }}" @selected($f['user'] === (int) $u->id)>{{ $u->name }} ({{ $u->username }})</option>@endforeach</select></label>
        <label class="f"><span>Action</span><select name="action"><option value="">All</option>@foreach ($actions as $a)<option value="{{ $a }}" @selected($f['action'] === $a)>{{ ucfirst($a) }}</option>@endforeach</select></label>
        <label class="f"><span>What</span><select name="subject"><option value="">All</option>@foreach ($subjects as $s)<option value="{{ $s }}" @selected($f['subject'] === $s)>{{ ucfirst($s) }}</option>@endforeach</select></label>
        <label class="f"><span>Search</span><input type="search" name="q" value="{{ $f['q'] }}" placeholder="number, customer, barcode..." maxlength="100"></label>
        <label class="f"><span>Rows per page</span><select name="n">@foreach (\App\Support\Pager::SIZES as $o)<option value="{{ $o }}" @selected($n === $o)>{{ $o }}</option>@endforeach</select></label>
        <button class="ab sm solid">Show</button>
        <a class="ab sm" href="{{ route('activity.index') }}">Last 7 days</a>
    </form>
    <div class="tw"><table class="t">
        <thead><tr><th>When</th><th>User</th><th>Action</th><th>What</th><th>Number</th><th>Details</th></tr></thead>
        <tbody>
            @forelse ($rows as $r)
                @php($cls = ['add' => 'ok', 'edit' => 'info', 'delete' => 'bad', 'login' => 'idle', 'logout' => 'idle', 'payment' => 'warn'][$r->action] ?? 'idle')
                <tr>
                    <td style="white-space:nowrap">{{ \Carbon\Carbon::parse($r->created_at)->format('d/m/Y H:i') }}</td>
                    <td><b>{{ $r->person ?: $r->username ?: '-' }}</b>@if ($r->username && $r->person)<br><small class="hint">{{ $r->username }}</small>@endif</td>
                    <td><span class="pill {{ $cls }}" @if ($r->action === 'delete') style="background:#B3261E;color:#fff" @endif>{{ ucfirst($r->action) }}</span></td>
                    <td>{{ ucfirst($r->subject) }}</td>
                    <td>{{ $r->subject_id }}</td>
                    <td>{{ $r->detail }}</td>
                </tr>
            @empty
                <tr><td class="empty" colspan="6">Nothing in this period</td></tr>
            @endforelse
        </tbody>
    </table></div>
    <p class="hint">Records every add, edit, delete, import, payment, task and sign-in made from now on, with the person who did it. Records made before this version show only edits and deletes.</p>
    @include('partials.pager')
</div>
@endsection
