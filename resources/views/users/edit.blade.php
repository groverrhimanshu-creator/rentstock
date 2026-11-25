@extends('layouts.app')
@section('title', 'Permissions')
@section('content')
@php
    $have = array_flip(old('perm', $granted));
    $cols = \App\Support\Perm::ACTIONS;
    $isAdmin = old('role', $u->role) === 'admin';
@endphp
<style>
.sw{position:relative;display:inline-block;width:40px;height:22px;vertical-align:middle}
.sw input{position:absolute;opacity:0;width:100%;height:100%;margin:0;cursor:pointer;z-index:1}
.sw i{position:absolute;inset:0;background:#C9D2E6;border-radius:22px;transition:background .15s}
.sw i:before{content:"";position:absolute;left:3px;top:3px;width:16px;height:16px;background:#fff;border-radius:50%;transition:transform .15s;box-shadow:0 1px 3px rgba(0,0,0,.3)}
.sw input:checked+i{background:#0090CF}.sw input:checked+i:before{transform:translateX(18px)}
.sw input:focus-visible+i{outline:2px solid #F59E0B;outline-offset:2px}
.pt td,.pt th{text-align:center}.pt td:first-child,.pt th:first-child{text-align:left}
.pt .na{color:#B8C0D6}
.pt.off{opacity:.45;pointer-events:none}
</style>
<div class="pg"><h2>{{ $u->role === 'admin' ? 'Edit user' : 'Permissions' }}<small>{{ $u->username }}</small></h2><a class="ab" href="{{ route('users.index') }}">Back</a></div>
<form class="cd" data-validate method="post" action="{{ route('users.update', $u->id) }}" autocomplete="off">
    @csrf @method('PUT')
    @if ($self)<input type="hidden" name="role" value="{{ $u->role }}">@endif
    <div class="msg bad" data-errbox @if (!$errors->any()) hidden @endif>@if ($errors->any())<ul>@foreach ($errors->all() as $err)<li>{{ $err }}</li>@endforeach</ul>@endif</div>
    <h3>Login</h3>
    <div class="fg">
        <label class="f"><span>Name</span><input name="name" data-label="Name" data-req value="{{ old('name', $u->name) }}" maxlength="100"></label>
        <label class="f"><span>Username</span><input name="username" data-label="Username" data-req data-check="username" value="{{ old('username', $u->username) }}" maxlength="40"></label>
        <label class="f"><span>New password</span><input type="password" name="password" autocomplete="new-password" placeholder="Leave empty to keep the current password"></label>
        <label class="f"><span>Role</span>
            <select name="role" id="role" @disabled($self)><option value="subadmin" @selected(old('role', $u->role) === 'subadmin')>SubAdmin</option><option value="admin" @selected(old('role', $u->role) === 'admin')>Admin</option></select>
        </label>
        <label class="f"><span>Status</span>
            <select name="active" @disabled($self)><option value="1" @selected(old('active', $u->active ? '1' : '0') === '1')>Active</option><option value="0" @selected(old('active', $u->active ? '1' : '0') === '0')>Disabled</option></select>
        </label>
    </div>
    @if ($self)<p class="hint">This is your own login, so its role and status cannot be changed here.</p>@endif

    <h3 style="margin-top:22px">What this person may do</h3>
    <div class="msg info" id="adminnote" @if (!$isAdmin) hidden @endif>An Admin can do everything, so there is nothing to switch on or off.</div>
    <div id="permbox" @if ($isAdmin) hidden @endif>
        <div class="rowx" style="margin-bottom:10px;align-items:flex-end;flex-wrap:wrap">
            <label class="f" style="min-width:300px"><span>Quick start</span>
                <select id="preset"><option value="">Choose a ready-made set, then adjust</option>@foreach ($presets as $k => $p)<option value="{{ $k }}">{{ $p[0] }}</option>@endforeach</select>
            </label>
            <span class="hint" style="margin:0 0 8px">Switching on Create, Edit or Delete also switches on View for that screen.</span>
        </div>
        <div class="tw"><table class="t pt" id="ptab">
            <thead><tr><th>Screen</th>@foreach ($cols as $a => $label)<th>{{ $label }}</th>@endforeach<th>All</th></tr></thead>
            <tbody>
                @foreach (\App\Support\Perm::MODULES as $m => $def)
                    <tr data-m="{{ $m }}">
                        <td><b>{{ $def[0] }}</b></td>
                        @foreach ($cols as $a => $label)
                            @if (isset($def[1][$a]) && !($m . '.' . $a === 'tasks.manage' && !\App\Support\Features::taskAssign()))
                                <td title="{{ $def[1][$a] }}"><label class="sw"><input type="checkbox" name="perm[]" value="{{ $m }}.{{ $a }}" data-a="{{ $a }}" aria-label="{{ $def[0] }}: {{ $def[1][$a] }}" @checked(isset($have[$m . '.' . $a]))><i></i></label></td>
                            @else
                                <td class="na">-</td>
                            @endif
                        @endforeach
                        <td><label class="sw"><input type="checkbox" class="rowall" aria-label="{{ $def[0] }}: all"><i></i></label></td>
                    </tr>
                @endforeach
            </tbody>
        </table></div>
        <p class="hint">Customer logins, Users &amp; Permissions and Company Profile are always for the Admin only.</p>
    </div>
    <div class="rowx" style="margin-top:16px"><button class="ab solid">Save</button><a class="ab" href="{{ route('users.index') }}">Cancel</a></div>
</form>
<script>
(function () {
    var P = {!! json_encode(collect($presets)->map(fn ($p) => $p[1])->all(), JSON_HEX_TAG | JSON_HEX_AMP) !!};
    var tab = document.getElementById('ptab'), role = document.getElementById('role');
    function boxes() { return [].slice.call(tab.querySelectorAll('input[name="perm[]"]')); }
    function row(m) { return [].slice.call(tab.querySelectorAll('tr[data-m="' + m + '"] input[name="perm[]"]')); }
    function sync() {
        [].slice.call(tab.querySelectorAll('tbody tr')).forEach(function (tr) {
            var b = [].slice.call(tr.querySelectorAll('input[name="perm[]"]'));
            tr.querySelector('.rowall').checked = b.length > 0 && b.every(function (x) { return x.checked; });
        });
    }
    tab.addEventListener('change', function (e) {
        var t = e.target, tr = t.closest('tr');
        if (!tr) { return; }
        if (t.classList.contains('rowall')) {
            row(tr.getAttribute('data-m')).forEach(function (x) { x.checked = t.checked; });
        } else if (t.name === 'perm[]') {
            var a = t.getAttribute('data-a'), v = tr.querySelector('input[data-a="view"]');
            if (a !== 'view' && t.checked && v) { v.checked = true; }
            if (a === 'view' && !t.checked) { row(tr.getAttribute('data-m')).forEach(function (x) { x.checked = false; }); }
        }
        sync();
    });
    document.getElementById('preset').addEventListener('change', function () {
        if (!this.value) { return; }
        var on = {}; (P[this.value] || []).forEach(function (k) { on[k] = true; });
        boxes().forEach(function (b) { b.checked = !!on[b.value]; });
        sync(); this.value = '';
    });
    function showRole() { var a = role.value === 'admin'; document.getElementById('permbox').hidden = a; document.getElementById('adminnote').hidden = !a; }
    role.addEventListener('change', showRole);
    sync();
})();
</script>
@endsection
