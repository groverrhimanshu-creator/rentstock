<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Sign in</title>
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet" media="print" onload="this.media=\'all\'">
<noscript><link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet"></noscript>
<link rel="icon" type="image/png" href="{{ asset('assets/logo.png') }}">
<link rel="stylesheet" href="{{ asset('assets/app.css') }}">
<link rel="stylesheet" href="{{ asset('assets/theme.css') }}">
</head>
<body>
@php($brand = config('rentstock.brand'))
<div class="app">
    <div class="lg">
        <div class="lgc">
            <div class="lgp">
                <img class="bigl" src="{{ asset('assets/logo.png') }}" alt="">
                <div class="bars">@for ($i = 0; $i < 25; $i++)<i></i>@endfor</div>
                <h3>Rental stock, billing<br>and customer portal</h3>
                <p>Every laptop has a barcode. Every rental has a challan and an invoice.</p>
            </div>
            <form class="lgf" data-validate method="post" action="{{ route('login.attempt') }}" autocomplete="off">
                @csrf
                <div class="logo2"><img src="{{ asset('assets/logo.png') }}" alt=""></div>
                <h2>Sign in</h2>
                <div class="err" data-errbox @if (!$errors->any()) hidden @endif>@if ($errors->any()){{ $errors->first('login') ?: $errors->first() }}@endif</div>
                <label class="f"><span>Role</span>
                    <select name="role">
                        @foreach (array_intersect_key(['admin' => 'Admin', 'subadmin' => 'SubAdmin', 'customer' => 'Customer'], array_flip(\App\Http\Controllers\AuthController::roles())) as $k => $label)
                            <option value="{{ $k }}" @selected(old('role', 'admin') === $k)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="f"><span>Username</span><input name="username" data-label="Username" data-req value="{{ old('username') }}" autocomplete="off" autofocus></label>
                <label class="f"><span>Password</span><input type="password" name="password" data-label="Password" data-req></label>
                <button class="ab solid" type="submit">Login</button>
            </form>
        </div>
    </div>
</div>
<script src="{{ asset('assets/validate.js') }}"></script>
</body>
</html>
