@extends('layouts.app')
@section('title', 'Reports')
@section('content')
@use('App\Support\Docs')
@php($d = $data['device'])
@php($tone = ['Purchase' => 'info', 'Stock entry' => 'idle', 'Sale' => 'ok', 'Rental' => 'info', 'Return' => 'warn', 'Replacement' => 'warn', 'Repair' => 'bad', 'Upgrade' => 'ok', 'Config' => 'idle'])
<div class="pg"><h2>Reports</h2>
    <div class="rowx">
        @if ($d)<a class="ab orange" href="{{ route('reports.csv', ['tab' => $tab, 'q' => $data['q']]) }}">Download CSV</a>@endif
    </div>
</div>
<div class="cd">
    <div class="tabsx">
        @foreach ($tabs as $i => $t)<a href="{{ route('reports.index', ['tab' => $i]) }}" aria-selected="{{ $i === $tab ? 'true' : 'false' }}">{{ $t }}</a>@endforeach
    </div>
    <form method="get" action="{{ route('reports.index') }}" class="lt" style="flex-wrap:wrap;align-items:flex-end;gap:10px"><input type="hidden" name="tab" value="{{ $tab }}">
        <label class="f" style="min-width:280px;flex:1;max-width:420px"><span>Barcode, or brand / service tag</span><input type="search" name="q" value="{{ $data['q'] }}" placeholder="for example {{ \App\Support\Barcodes::prefix() }}600001" maxlength="60" autocomplete="off" autofocus data-camscan="Scan the barcode" data-camscan-submit></label>
        <button class="ab sm solid">Show history</button>
    </form>

    @if ($data['msg'])<div class="msg bad" style="margin-top:12px">{{ $data['msg'] }}</div>@endif

    @if ($data['q'] === '')
        <p class="hint" style="margin-top:12px">Scan or type a barcode to see everything that has happened to that piece: purchase, sale, rental, return, replacement, repair and upgrade. Click any line to open that document.</p>
    @endif

    @if ($data['matches'])
        <p class="hint" style="margin-top:12px">More than one piece has this tag. Choose one.</p>
        <div class="tw"><table class="t"><thead><tr><th>Barcode</th><th>Item</th><th>Brand tag</th><th>Status</th></tr></thead><tbody>
            @foreach ($data['matches'] as $m)
                <tr data-href="{{ route('reports.index', ['tab' => $tab, 'q' => $m['bc']]) }}" style="cursor:pointer"><td class="bc"><a href="{{ route('reports.index', ['tab' => $tab, 'q' => $m['bc']]) }}">{{ $m['bc'] }}</a></td><td>{{ $m['model'] }}</td><td>{{ $m['tag'] }}</td><td>{{ $m['status'] }}</td></tr>
            @endforeach
        </tbody></table></div>
    @endif

    @if ($d)
        <div class="sumb" style="margin:14px 0 10px">
            <div><span>Barcode</span><b class="bc">{{ $d['bc'] }}</b></div>
            <div><span>Item</span><b>{{ $d['model'] }}</b></div>
            <div><span>Brand tag</span><b>{{ $d['tag'] !== '' ? $d['tag'] : '-' }}</b></div>
            <div><span>Config</span><b>{{ $d['config'] !== '' ? $d['config'] : '-' }}</b></div>
            <div><span>Now</span><b><span class="pill {{ $d['tone'] }}">{{ $d['status'] }}</span></b></div>
            @if ($d['customer'] !== '')<div><span>With</span><b>{{ $d['customer'] }}{{ $d['rent_no'] !== '' ? ' · ' . $d['rent_no'] : '' }}</b></div>@endif
            @if ($d['store'] !== '')<div><span>Store</span><b>{{ $d['store'] }}</b></div>@endif
        </div>
        <div class="rowx" style="margin-bottom:8px;flex-wrap:wrap" id="hchips">
            <button type="button" class="ab sm solid" data-t="">All ({{ count($data['events']) }})</button>
            @foreach (\App\Support\Reports::HIST_TYPES as $ty)
                @php($n = collect($data['events'])->where('type', $ty)->count())
                @if ($n)<button type="button" class="ab sm" data-t="{{ $ty }}">{{ $ty }} ({{ $n }})</button>@endif
            @endforeach
            <a class="ab sm" href="{{ $d['url'] }}">Open item page</a>
        </div>
        <div class="tw"><table class="t" id="htab">
            <thead><tr><th>Date</th><th>Type</th><th>What happened</th><th>Document</th><th>Details</th></tr></thead>
            <tbody>
                @forelse ($data['events'] as $e)
                    <tr data-type="{{ $e['type'] }}" @if ($e['url']) data-href="{{ $e['url'] }}" style="cursor:pointer" @endif>
                        <td>{{ Docs::d(substr($e['at'], 0, 10)) }}</td>
                        <td><span class="pill {{ $tone[$e['type']] ?? 'idle' }}">{{ $e['type'] }}</span></td>
                        <td>
                            @if ($e['url'])
                                <a href="{{ $e['url'] }}">{{ $e['title'] }}</a>
                            @else
                                {{ $e['title'] }}
                            @endif
                        </td>
                        <td>
                            @if ($e['doc'] !== '' && $e['url'])
                                <a href="{{ $e['url'] }}">{{ $e['doc'] }}</a>
                            @else
                                {{ $e['doc'] }}
                            @endif
                        </td>
                        <td>{{ $e['detail'] }}</td>
                    </tr>
                @empty
                    <tr><td class="empty" colspan="5">Nothing recorded for this piece yet.</td></tr>
                @endforelse
            </tbody>
        </table></div>
        <p class="hint">Click a line to open the purchase, sale, challan, return or replacement behind it. Oldest first.</p>
        @if ($data['log'])
            <details style="margin-top:10px"><summary style="cursor:pointer;font-weight:600">All history lines ({{ count($data['log']) }})</summary>
                <div class="tw" style="margin-top:8px"><table class="t"><thead><tr><th>Date</th><th>Note</th></tr></thead><tbody>
                    @foreach ($data['log'] as $l)<tr><td>{{ Docs::d(substr($l['at'], 0, 10)) }}</td><td>{{ $l['text'] }}</td></tr>@endforeach
                </tbody></table></div>
            </details>
        @endif
        <script>
        (function () {
            var chips = document.querySelectorAll('#hchips [data-t]'), rows = document.querySelectorAll('#htab tbody tr[data-type]');
            chips.forEach(function (b) {
                b.addEventListener('click', function () {
                    var t = b.getAttribute('data-t');
                    chips.forEach(function (x) { x.classList.toggle('solid', x === b); });
                    rows.forEach(function (r) { r.hidden = t !== '' && r.getAttribute('data-type') !== t; });
                });
            });
        })();
        </script>
    @endif
</div>
@endsection
