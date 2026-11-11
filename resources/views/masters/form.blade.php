@extends('layouts.app')
@section('title', ($id ? 'Edit ' : 'Add ') . $m['title'])
@section('content')
<div class="pg"><h2>{{ $id ? 'Edit' : 'Add' }} {{ $m['title'] }}<small>Masters</small></h2><a class="ab" href="{{ route('masters.index', $key) }}">Back</a></div>
<form class="cd" data-validate method="post" action="{{ $id ? route('masters.update', [$key, $id]) : route('masters.store', $key) }}" autocomplete="off">
    @csrf
    @if ($id)@method('PUT')@endif
    <div class="msg bad" data-errbox @if (!$errors->any()) hidden @endif>@if ($errors->any())<ul>@foreach ($errors->all() as $err)<li>{{ $err }}</li>@endforeach</ul>@endif</div>
    <div class="fg">
        @foreach ($m['fields'] as $f)
            @php
                $name = $f['name'];
                $v = (string) old($name, $values[$name] ?? '');
            @endphp
            <label class="f"><span>{{ $f['label'] }}</span>
                @if ($f['type'] === 'select')
                    <select name="{{ $name }}"><option value="">Select</option>@foreach ($f['options'] as $o)<option value="{{ $o }}" @selected($v === $o)>{{ $o }}</option>@endforeach</select>
                @elseif ($f['type'] === 'state')
                    <select name="{{ $name }}"><option value="">Select</option>@foreach (\App\Support\Masters::states() as $s => $code)<option value="{{ $s }}" @selected($v === $s)>{{ $s }}</option>@endforeach</select>
                @elseif ($f['type'] === 'lookup')
                    <select name="{{ $name }}"><option value="">Select</option>@foreach ($lookups[$name] as $o)<option value="{{ $o->id }}" @selected($v === (string) $o->id)>{{ $o->name }}</option>@endforeach</select>
                @elseif ($f['type'] === 'yesno')
                    <select name="{{ $name }}">
                        <option value="1" @selected($v === '1')>{{ $f['yes'] }}</option>
                        <option value="0" @selected($v === '0')>{{ $f['no'] }}</option>
                    </select>
                @else
                    <input name="{{ $name }}" value="{{ $v }}" data-label="{{ $f['label'] }}" @if (!empty($f['req']) || $f['type'] === 'number')data-req @endif @if (!empty($f['check']))data-check="{{ $f['check'] }}" @endif @if (!empty($f['upper']))data-upper style="text-transform:uppercase" @endif @if ($f['type'] === 'number')data-num data-min="{{ $f['min'] ?? 0 }}" data-max="{{ $f['max'] ?? 99999999 }}" inputmode="decimal"@else maxlength="{{ $f['max'] }}"@endif>
                @endif
            </label>
        @endforeach
    </div>
    @if ($key === 'customer')
        @php($makeLogin = (bool) old('make_login'))
        <div class="loginbox" style="margin-top:18px">
            <h4 style="margin:0 0 8px">Customer login</h4>
            @if ($login)
                <p class="hint" style="margin:0">This customer already has a login: <b>{{ $login->username }}</b>. You can change it or disable it under <a class="link" href="{{ route('logins.edit', $login->id) }}">Customer Logins</a>.</p>
            @else
                <label class="chk"><input type="checkbox" name="make_login" value="1" id="make_login" @checked($makeLogin)> Create a login for this customer, so they can see what they have on rent</label>
                <div class="fg" id="loginfields" style="margin-top:12px" @if (!$makeLogin) hidden @endif>
                    <label class="f"><span>Login username</span><input name="login_username" data-label="Login username" data-req data-check="username" value="{{ old('login_username') }}" maxlength="40" autocomplete="off" @disabled(!$makeLogin)></label>
                    <label class="f"><span>Login password</span><input type="password" name="login_password" data-label="Login password" data-req data-check="password8" autocomplete="new-password" @disabled(!$makeLogin)></label>
                </div>
                <p class="hint" id="loginhint" @if (!$makeLogin) hidden @endif>The username uses small letters, digits, dots, dashes or underscores. The password needs at least 8 characters. The contact person's name is used as the login's name.</p>
                <script>
                (function () {
                    var c = document.getElementById('make_login'), box = document.getElementById('loginfields'), hint = document.getElementById('loginhint');
                    function sync() {
                        box.hidden = hint.hidden = !c.checked;
                        box.querySelectorAll('input').forEach(function (i) { i.disabled = !c.checked; });
                    }
                    c.addEventListener('change', sync); sync();
                })();
                </script>
            @endif
        </div>
    @endif
    <div class="rowx" style="margin-top:16px"><button class="ab solid">Save</button><a class="ab" href="{{ route('masters.index', $key) }}">Cancel</a>@if ($id)<button type="submit" form="delform" class="ab danger" style="margin-left:auto">Delete</button>@endif</div>
</form>
@if ($id)
<form id="delform" class="js-delete" method="post" action="{{ route('masters.destroy', [$key, $id]) }}" data-blocked="{{ $blocked }}" data-confirm="{{ 'Delete this ' . strtolower($m['title']) . '? This cannot be undone.' }}">@csrf @method('DELETE')</form>
@endif
@endsection
