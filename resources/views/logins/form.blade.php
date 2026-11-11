@extends('layouts.app')
@section('title', 'Add Login')
@section('content')
<div class="pg"><h2>Add Login<small>Customer logins</small></h2><a class="ab" href="{{ route('logins.index') }}">Back</a></div>
<form class="cd" data-validate method="post" action="{{ route('logins.store') }}" autocomplete="off">
    @csrf
    <div class="msg bad" data-errbox @if (!$errors->any()) hidden @endif>@if ($errors->any())<ul>@foreach ($errors->all() as $err)<li>{{ $err }}</li>@endforeach</ul>@endif</div>
    <div class="fg">
        <label class="f"><span>Username</span><input name="username" data-label="Username" data-req data-check="username" value="{{ old('username') }}" maxlength="40"></label>
        <label class="f"><span>Contact name</span><input name="name" data-label="Contact name" data-req value="{{ old('name') }}" maxlength="100"></label>
        <label class="f"><span>Customer</span>
            <select name="customer_id" data-label="Customer" data-req><option value="">Select</option>@foreach ($customers as $c)<option value="{{ $c->id }}" @selected((string) old('customer_id') === (string) $c->id)>{{ $c->name }}</option>@endforeach</select>
        </label>
        <label class="f"><span>Password</span><input type="password" name="password" data-label="Password" data-req data-check="password8" autocomplete="new-password"></label>
    </div>
    <div class="rowx" style="margin-top:16px"><button class="ab solid">Save</button><a class="ab" href="{{ route('logins.index') }}">Cancel</a></div>
</form>
@endsection
