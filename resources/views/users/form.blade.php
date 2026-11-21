@extends('layouts.app')
@section('title', 'Add User')
@section('content')
<div class="pg"><h2>Add User<small>Users &amp; Permissions</small></h2><a class="ab" href="{{ route('users.index') }}">Back</a></div>
<form class="cd" data-validate method="post" action="{{ route('users.store') }}" autocomplete="off">
    @csrf
    <div class="msg bad" data-errbox @if (!$errors->any()) hidden @endif>@if ($errors->any())<ul>@foreach ($errors->all() as $err)<li>{{ $err }}</li>@endforeach</ul>@endif</div>
    <div class="fg">
        <label class="f"><span>Name</span><input name="name" data-label="Name" data-req value="{{ old('name') }}" maxlength="100"></label>
        <label class="f"><span>Username</span><input name="username" data-label="Username" data-req data-check="username" value="{{ old('username') }}" maxlength="40"></label>
        <label class="f"><span>Password</span><input type="password" name="password" data-label="Password" data-req data-check="password8" autocomplete="new-password"></label>
        <label class="f"><span>Role</span>
            <select name="role" id="role"><option value="subadmin" @selected(old('role', 'subadmin') === 'subadmin')>SubAdmin (you choose what this person may do)</option><option value="admin" @selected(old('role') === 'admin')>Admin (full access to everything)</option></select>
        </label>
        <label class="f" id="presetbox"><span>Start with</span>
            <select name="preset">@foreach ($presets as $k => $p)<option value="{{ $k }}" @selected(old('preset', 'usual') === $k)>{{ $p[0] }}</option>@endforeach</select>
        </label>
    </div>
    <p class="hint">After saving you can switch each permission on or off one by one.</p>
    <div class="rowx" style="margin-top:16px"><button class="ab solid">Save and set permissions</button><a class="ab" href="{{ route('users.index') }}">Cancel</a></div>
</form>
<script>
(function () {
    var r = document.getElementById('role'), b = document.getElementById('presetbox');
    function s() { b.hidden = r.value === 'admin'; }
    r.addEventListener('change', s); s();
})();
</script>
@endsection
