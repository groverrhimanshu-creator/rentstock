<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>@yield('title')</title>
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet" media="print" onload="this.media=\'all\'">
<noscript><link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet"></noscript>
<link rel="icon" type="image/png" href="{{ asset('assets/logo.png') }}">
<link rel="stylesheet" href="{{ \App\Support\Assets::url('assets/app.css') }}">
<link rel="stylesheet" href="{{ \App\Support\Assets::url('assets/theme.css') }}">
</head>
<body>
@php
    $u = auth()->user();
    $menu = \App\Support\Menu::for($u->role);
    $cur = \App\Support\Menu::current();
    $initials = strtoupper(mb_substr(implode('', array_map(fn ($w) => mb_substr($w, 0, 1), preg_split('/\s+/', trim($u->name)))), 0, 2));
    $who = $u->role === 'customer'
        ? (string) \Illuminate\Support\Facades\DB::table('customers')->where('id', $u->customer_id)->value('name')
        : (string) \Illuminate\Support\Facades\DB::table('company_profile')->where('id', 1)->value('name');
    $brand = config('rentstock.brand');
@endphp
<div class="app">
    <aside class="sb" id="sb">
        <div class="logo"><img src="{{ asset('assets/logo.png') }}" alt=""></div>
        <nav>
            @foreach ($menu as $m)
                @if (isset($m['children']))
                    @php($open = collect($m['children'])->contains('id', $cur))
                    <button type="button" class="mi{{ $open ? ' openparent' : '' }}" data-tgl aria-expanded="{{ $open ? 'true' : 'false' }}">{!! \App\Support\Icons::get($m['icon'] ?? 'box') !!}<span>{{ $m['label'] }}</span><span class="chev">{!! \App\Support\Icons::get('chev') !!}</span></button>
                    <div class="sub-menu" @if (!$open) hidden @endif>
                        @foreach ($m['children'] as $c)
                            <a class="mi{{ $c['id'] === $cur ? ' cur' : '' }}" href="{{ $c['url'] }}">{{ $c['label'] }}</a>
                        @endforeach
                    </div>
                @else
                    <a class="mi{{ $m['id'] === $cur ? ' on' : '' }}" href="{{ $m['url'] }}">{!! \App\Support\Icons::get($m['icon'] ?? 'box') !!}<span>{{ $m['label'] }}</span></a>
                @endif
            @endforeach
        </nav>
    </aside>
    <div class="main">
        <header class="tb">
            <button class="tgl" type="button" id="tgl" aria-label="Menu">{!! \App\Support\Icons::get('menu') !!}</button>
            <div class="crumb2"><b>@yield('title')</b></div>
            <div class="sp"></div>
            <div class="who"><span class="nm">{{ $u->name }}<small>{{ $u->roleLabel() }}{{ trim($who) !== '' ? ' · ' . $who : '' }}</small></span></div>
            <div class="av">{{ $initials }}</div>
            <form method="post" action="{{ route('logout') }}" style="margin:0">@csrf<button class="out">Logout</button></form>
        </header>
        <div class="ct">
            @yield('content')
        </div>
    </div>
</div>
@if (session('ok') || session('bad'))<div id="flash" hidden data-ok="{{ session('ok') }}" data-bad="{{ session('bad') }}"></div>@endif
<script>
(function () {
    var sb = document.getElementById('sb');
    if (window.innerWidth <= 760) sb.classList.add('collapsed');
    document.getElementById('tgl').addEventListener('click', function () { sb.classList.toggle('collapsed'); });
    document.querySelectorAll('[data-tgl]').forEach(function (b) {
        b.addEventListener('click', function () {
            var menu = b.nextElementSibling, isOpen = b.getAttribute('aria-expanded') === 'true';
            b.setAttribute('aria-expanded', isOpen ? 'false' : 'true');
            b.classList.toggle('openparent', !isOpen);
            if (isOpen) menu.setAttribute('hidden', ''); else menu.removeAttribute('hidden');
        });
    });
    document.querySelectorAll('tr[data-href]').forEach(function (r) {
        r.addEventListener('click', function (e) { if (!e.target.closest('a,button,form')) location.href = r.getAttribute('data-href'); });
    });
    document.querySelectorAll('form[data-list]').forEach(function (f) {
        var s = f.querySelector('select'), i = f.querySelector('input[name=q]'), h;
        if (s) s.addEventListener('change', function () { f.submit(); });
        if (i) {
            i.addEventListener('input', function () { clearTimeout(h); h = setTimeout(function () { f.submit(); }, 500); });
            if (i.value) { i.focus(); i.setSelectionRange(i.value.length, i.value.length); }
        }
    });
    var t = document.getElementById('toast');
    if (t) setTimeout(function () { t.remove(); }, 2600);
})();
</script>
<script src="{{ \App\Support\Assets::url('assets/vendor/sweetalert2.all.min.js') }}"></script>
<script src="{{ \App\Support\Assets::url('assets/alerts.js') }}"></script>
<script src="{{ \App\Support\Assets::url('assets/validate.js') }}"></script>
</body>
</html>
