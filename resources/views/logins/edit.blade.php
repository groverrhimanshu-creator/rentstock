@extends('layouts.app')
@section('title', 'Edit Login')
@section('content')
<div class="pg"><h2>Edit Login<small>Customer logins</small></h2><a class="ab" href="{{ route('logins.index') }}">Back</a></div>
<form class="cd" data-validate method="post" action="{{ route('logins.update', $u->id) }}" autocomplete="off">
    @csrf @method('PUT')
    <div class="msg bad" data-errbox @if (!$errors->any()) hidden @endif>@if ($errors->any())<ul>@foreach ($errors->all() as $err)<li>{{ $err }}</li>@endforeach</ul>@endif</div>
    <div class="fg">
        <label class="f"><span>Username</span><input name="username" data-label="Username" data-req data-check="username" value="{{ old('username', $u->username) }}" maxlength="40"></label>
        <label class="f"><span>Contact name</span><input name="name" data-label="Contact name" data-req value="{{ old('name', $u->name) }}" maxlength="100"></label>
        <label class="f"><span>Customer</span>
            <select name="customer_id" data-label="Customer" data-req>@foreach ($customers as $c)<option value="{{ $c->id }}" @selected((string) old('customer_id', $u->customer_id) === (string) $c->id)>{{ $c->name }}</option>@endforeach</select>
        </label>
        <label class="f"><span>New password</span><input type="password" name="password" autocomplete="new-password" placeholder="Leave empty to keep the current password"></label>
        <label class="f"><span>Status</span><select name="active"><option value="1" @selected(old('active', $u->active ? '1' : '0') === '1')>Active</option><option value="0" @selected(old('active', $u->active ? '1' : '0') === '0')>Disabled</option></select></label>
    </div>
    <div class="rowx" style="margin-top:16px"><button class="ab solid">Save</button><a class="ab" href="{{ route('logins.index') }}">Cancel</a></div>
</form>
@endsection
