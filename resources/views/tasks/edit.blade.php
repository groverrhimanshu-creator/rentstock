@extends('layouts.app')
@section('title', 'Edit task')
@section('content')
<div class="pg"><h2>Edit task</h2><a class="ab" href="{{ route('tasks.index') }}">Back</a></div>
@if ($errors->any())<div class="msg bad">{{ $errors->first() }}</div>@endif
<div class="cd">
    <form method="post" action="{{ route('tasks.update', $t->id) }}" class="fg" autocomplete="off">@csrf @method('PUT')
        <label class="f" style="grid-column:span 2"><span>Task</span><input name="title" value="{{ old('title', $t->title) }}" maxlength="200" required></label>
        <label class="f"><span>Due date</span><input type="date" name="due_date" value="{{ old('due_date', $t->due_date) }}" required></label>
        @if ($staff->count())
            <label class="f"><span>Given to</span><select name="assigned_to">@foreach ($staff as $s)<option value="{{ $s->id }}" @selected((int) old('assigned_to', $t->user_id) === (int) $s->id)>{{ $s->name }} - {{ $s->role === 'admin' ? 'Admin' : 'SubAdmin' }}</option>@endforeach</select></label>
        @endif
        <label class="f" style="grid-column:1/-1"><span>Notes</span><input name="notes" value="{{ old('notes', $t->notes) }}" maxlength="1000"></label>
        <div class="rowx"><button class="ab solid">Save</button><a class="ab" href="{{ route('tasks.index') }}">Cancel</a></div>
    </form>
</div>
@endsection
