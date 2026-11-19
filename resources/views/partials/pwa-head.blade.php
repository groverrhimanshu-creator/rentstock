@if (config('rentstock.pwa'))
<link rel="manifest" href="{{ route('pwa.manifest') }}">
<meta name="theme-color" content="#06063A">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="{{ config('rentstock.pwa_short', implode(' ', config('rentstock.brand'))) }}">
<link rel="apple-touch-icon" href="{{ asset('icons/apple-touch-icon.png') }}">
<link rel="stylesheet" href="{{ \App\Support\Assets::url('assets/pwa.css') }}">
@endif
