@if (config('rentstock.pwa'))
<script src="{{ \App\Support\Assets::url('assets/pwa.js') }}"></script>
<script src="{{ \App\Support\Assets::url('assets/camscan.js') }}" data-zxing="{{ \App\Support\Assets::url('assets/vendor/zxing.min.js') }}"></script>
@endif
