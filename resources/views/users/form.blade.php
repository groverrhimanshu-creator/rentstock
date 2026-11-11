@extends('layouts.app')
@section('title', $user->exists ? 'Edit login' : 'Add login')
@section('content')
@php($self = $user->exists && $user->id === auth()->id())
<div class="head">
    <div><h1>{{ $user->exists ? 'Edit login' : 'Add login' }}</h1><p>Logins</p></div>
    <a class="btn" href="{{ route('users.index') }}">Back</a>
</div>
@if ($errors->any())
    <div class="msg bad"><ul>@foreach ($errors->all() as $err)<li>{{ $err }}</li>@endforeach</ul></div>
@endif
<div class="card">
    <form method="post" action="{{ $user->exists ? route('users.update', $user->id) : route('users.store') }}" class="fg" autocomplete="off">
        @csrf
        @if ($user->exists)@method('PUT')@endif
        <label class="f"><span>Full name <i>*</i></span><input name="name" value="{{ old('name', $user->name) }}" maxlength="100"></label>
        <label class="f"><span>Username <i>*</i></span><input name="username" value="{{ old('username', $user->username) }}" maxlength="40"></label>
        <label class="f"><span>Email</span><input name="email" value="{{ old('email', $user->email) }}" maxlength="150"></label>
        <label class="f"><span>Role <i>*</i></span>
            <select name="role" @if ($self)disabled @endif>
                @foreach (['admin' => 'Admin (everything)', 'staff' => 'Staff (day-to-day work)', 'customer' => 'Customer (sees only their own records)'] as $k => $label)
                    <option value="{{ $k }}" @selected(old('role', $user->role) === $k)>{{ $label }}</option>
                @endforeach
            </select>
            @if ($self)<input type="hidden" name="role" value="admin">@endif
        </label>
        <label class="f"><span>Customer (for customer logins)</span>
            <select name="customer_id" @if ($self)disabled @endif>
                <option value="">None</option>
                @foreach ($customers as $c)<option value="{{ $c->id }}" @selected((string) old('customer_id', $user->customer_id) === (string) $c->id)>{{ $c->name }}</option>@endforeach
            </select>
        </label>
        <label class="f"><span>{{ $user->exists ? 'New password (leave empty to keep the old one)' : 'Password' }} @if (!$user->exists)<i>*</i>@endif</span>
            <input type="password" name="password" autocomplete="new-password">
        </label>
        <label class="f chk full">
            <input type="checkbox" name="active" value="1" @checked(old('active', $user->exists ? $user->active : true)) @if ($self)disabled @endif>
            <span style="margin:0">Login is switched on</span>
            @if ($self)<input type="hidden" name="active" value="1">@endif
        </label>
        <div class="full"><button class="btn solid">Save</button> <a class="btn" href="{{ route('users.index') }}">Cancel</a></div>
    </form>
</div>
@endsection
