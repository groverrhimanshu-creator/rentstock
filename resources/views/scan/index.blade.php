@extends('layouts.app')
@section('title', 'Scan barcode')
@section('content')
<div class="pg"><h2>Scan barcode<small>Point the camera at a label, type the number, or use a Bluetooth scanner</small></h2></div>
<div class="cols">
    <div class="cd">
        <div class="scanbox" id="scanbox" hidden><video id="scanvid" playsinline muted></video><div class="scanline"></div></div>
        <p class="hint" id="scanmsg">Camera is off.</p>
        <div class="rowx" style="flex-wrap:wrap">
            <button type="button" class="ab solid" id="scango">Start camera</button>
            <button type="button" class="ab" id="scanstop" hidden>Stop camera</button>
            <button type="button" class="ab" id="scantorch" hidden>Torch</button>
        </div>
        <form id="scanform" style="margin-top:12px" autocomplete="off">
            <label class="f"><span>Or type / scan the barcode here</span><input id="scancode" inputmode="text" placeholder="for example KPI600001" autocapitalize="characters"></label>
            <button class="ab solid" type="submit">Find</button>
        </form>
    </div>
    <div class="cd"><h3>Result</h3>
        <div id="scanres"><p class="hint">Nothing scanned yet.</p></div>
        <h3 style="margin-top:16px">Recent</h3>
        <div id="scanhist"><p class="hint">None yet.</p></div>
    </div>
</div>
<script>window.SCAN_URL = @json(route('scan.lookup')); window.SCAN_TOKEN = @json(csrf_token());</script>
<script src="{{ \App\Support\Assets::url('assets/vendor/zxing.min.js') }}"></script>
<script src="{{ \App\Support\Assets::url('assets/scan.js') }}"></script>
@endsection
