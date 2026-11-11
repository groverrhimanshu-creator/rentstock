@extends('layouts.app')
@section('title', 'Print Barcodes')
@section('content')
<div class="pg"><h2>Print barcodes<small>Labels for stickers, and the history of any single barcode</small></h2></div>
<div class="cd">
    <div class="tabsx">
        <a href="{{ route('barcodes.index') }}" aria-selected="{{ $tab === 0 ? 'true' : 'false' }}">Print labels</a>
        <a href="{{ route('barcodes.index', ['tab' => 1]) }}" aria-selected="{{ $tab === 1 ? 'true' : 'false' }}">Find one barcode</a>
    </div>
@if ($tab === 0)
    <form method="get" action="{{ route('barcodes.index') }}" class="rowx" style="margin-bottom:10px;align-items:flex-end">
        <label class="f" style="width:240px"><span>Filter by item</span><select name="item" onchange="this.form.submit()"><option value="">All items</option>@foreach ($items as $i)<option value="{{ $i->id }}" @selected($item === (int) $i->id)>{{ $i->name }}</option>@endforeach</select></label>
        <label class="f" style="width:240px"><span>Search</span><input name="q" value="{{ $q }}" placeholder="Barcode, model or brand" autocomplete="off"></label>
        <button class="ab">Search</button>
    </form>
    <form id="lf" method="post" action="{{ route('barcodes.labels') }}" target="_blank">
        @csrf
        <div class="rowx" style="margin-bottom:10px"><button type="button" class="ab sm" id="tick20">Tick first 20</button><button type="button" class="ab sm" id="clr">Clear</button><button class="ab solid" id="gen">Make labels (0)</button></div>
        <div class="tw"><table class="t"><thead><tr><th></th><th>Barcode</th><th>Model</th><th>Brand tag</th></tr></thead><tbody>
            @forelse ($rows as $x)
                <tr><td><input type="checkbox" name="codes[]" value="{{ $x->code }}"></td><td class="bc">{{ $prefix . $x->code }}</td><td>{{ $x->model }}</td><td>{{ $x->brand_tag }}</td></tr>
            @empty
                <tr><td colspan="4" class="empty">No stock matches.</td></tr>
            @endforelse
        </tbody></table></div>
    </form>
    @if ($total > 40)<p class="hint">Showing the first 40 of {{ $total }}. Use the search box to narrow it down.</p>@endif
    <script>
    (function () {
        var f = document.getElementById('lf'), g = document.getElementById('gen');
        function boxes() { return [].slice.call(f.querySelectorAll('input[name="codes[]"]')); }
        function n() { var c = boxes().filter(function (b) { return b.checked; }).length; g.textContent = 'Make labels (' + c + ')'; return c; }
        f.addEventListener('change', n);
        document.getElementById('tick20').addEventListener('click', function () { boxes().forEach(function (b, i) { b.checked = i < 20; }); n(); });
        document.getElementById('clr').addEventListener('click', function () { boxes().forEach(function (b) { b.checked = false; }); n(); });
        f.addEventListener('submit', function (e) { if (!n()) { e.preventDefault(); alert('Tick at least one item to make labels.'); } });
    })();
    </script>
@else
    <form method="get" action="{{ route('barcodes.index') }}" class="rowx" style="margin-bottom:12px;align-items:flex-end">
        <input type="hidden" name="tab" value="1">
        <label class="f" style="flex:1;max-width:420px"><span>Search by barcode</span><input name="q" value="{{ $q }}" placeholder="{{ $prefix }}600001, model, brand or customer" autocomplete="off"></label>
        <button class="ab">Search</button>
    </form>
    @if (!$one)
        @if (mb_strlen($q) < 2)
            <div class="msg">Type or scan a barcode (for example {{ $prefix }}600001), or a model, brand or customer name.</div>
        @else
            <form id="lf2" method="post" action="{{ route('barcodes.labels') }}" target="_blank">
                @csrf
                <div class="rowx" style="margin-bottom:10px"><button type="button" class="ab sm" id="tickall2">Tick all shown</button><button type="button" class="ab sm" id="clr2">Clear</button><button class="ab solid" id="gen2">Print selected labels (0)</button></div>
                <div class="tw"><table class="t"><thead><tr><th></th><th>Barcode</th><th>Item</th><th>Config</th><th>Status</th><th></th></tr></thead><tbody>
                    @forelse ($rows ?? [] as $x)
                        <tr><td><input type="checkbox" name="codes[]" value="{{ $x->code }}"></td><td class="bc">{{ $prefix . $x->code }}</td><td>{{ $x->model }}</td><td>{{ $x->config }}</td><td>{!! \App\Support\Stock::pill($x->status) !!}{{ $x->customer ? ' · ' . $x->customer : '' }}</td>
                            <td><div class="rowx"><a class="ab sm" href="{{ route('barcodes.index', ['tab' => 1, 'q' => $q, 'one' => $x->code]) }}">History</a>
                                <a class="ab sm" target="_blank" href="{{ route('barcodes.label', ['code' => $x->code]) }}">Print label</a></div></td></tr>
                    @empty
                        <tr><td colspan="6" class="empty">Nothing found.</td></tr>
                    @endforelse
                </tbody></table></div>
            </form>
            <script>
            (function () {
                var f = document.getElementById('lf2'), g = document.getElementById('gen2');
                function boxes() { return [].slice.call(f.querySelectorAll('input[name="codes[]"]')); }
                function n() { var c = boxes().filter(function (b) { return b.checked; }).length; g.textContent = 'Print selected labels (' + c + ')'; return c; }
                f.addEventListener('change', n);
                document.getElementById('tickall2').addEventListener('click', function () { var all = boxes().every(function (b) { return b.checked; }); boxes().forEach(function (b) { b.checked = !all; }); n(); });
                document.getElementById('clr2').addEventListener('click', function () { boxes().forEach(function (b) { b.checked = false; }); n(); });
                f.addEventListener('submit', function (e) { if (!n()) { e.preventDefault(); alert('Tick at least one item to print its label.'); } });
            })();
            </script>
            @if (($total ?? 0) > 50)<p class="hint">Showing the first 50 of {{ $total }}. Type more to narrow it down.</p>@endif
        @endif
    @else
        <div class="rowx" style="margin-bottom:10px;justify-content:space-between"><h3 style="margin:0"><span class="bc">{{ $prefix . $one->code }}</span> · {{ $one->model }}</h3>
            <div class="rowx"><form method="post" action="{{ route('barcodes.labels') }}" target="_blank" style="margin:0">@csrf<input type="hidden" name="codes[]" value="{{ $one->code }}"><button class="ab orange">Print this label</button></form><a class="ab sm" href="{{ route('barcodes.index', ['tab' => 1]) }}">Search again</a></div></div>
        <div class="msg info">{{ $one->config }} · {{ \App\Support\Stock::STATUS[$one->status][0] ?? $one->status }}{{ $one->customer ? ' · with ' . $one->customer : '' }}{{ $one->brand_tag ? ' · tag ' . $one->brand_tag : '' }}</div>
        <div class="tw"><table class="t"><thead><tr><th>Date</th><th>What happened</th></tr></thead><tbody>
            @foreach ($history as $e)<tr><td>{{ \Carbon\Carbon::parse($e->at)->format('d/m/Y H:i') }}</td><td>{{ $e->text }}</td></tr>@endforeach
        </tbody></table></div>
        <h3 style="margin:16px 0 8px">Configuration log</h3>
        <div class="tw"><table class="t"><thead><tr><th>Date</th><th>What</th><th>Config before</th><th>Config after</th><th>Detail</th></tr></thead><tbody>
            @foreach ($cfglog as $g)<tr><td>{{ \Carbon\Carbon::parse($g->at)->format('d/m/Y H:i') }}</td><td><b>{{ \App\Support\ConfigLog::label($g->event) }}</b></td><td>{{ $g->old_config ?? '—' }}</td><td>{{ $g->new_config }}</td><td>{{ $g->note }}</td></tr>@endforeach
        </tbody></table></div>
    @endif
@endif
</div>
@endsection
