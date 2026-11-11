@extends('layouts.app')
@section('title', 'Item details')
@section('content')
@if (!$d)
<div class="pg"><h2>Item details<small>Not found</small></h2><a class="ab" href="{{ route('stock.inventory') }}">Back</a></div>
<div class="cd"><p class="hint">No item with this barcode.</p></div>
@else
<div class="pg"><h2>{{ $d->model }}<small>{{ $text }}</small></h2><div class="rowx">
    <a class="ab" href="{{ route('barcodes.label', ['code' => $d->code, 'back' => route('stock.device', $d->code)]) }}">Print label</a>
    @if (auth()->user()->role === 'admin')
        <a class="ab" href="{{ route('stock.device.edit', $d->code) }}">Edit</a>
        @include('partials.delete-btn', ['action' => route('stock.device.destroy', $d->code), 'what' => 'item', 'blocked' => $blocked, 'confirm' => 'Delete ' . $text . '? The item and its history will be removed. This cannot be undone.'])
    @endif
    <a class="ab" href="{{ route('stock.inventory') }}">Back</a></div></div>
<div class="cols">
    <div class="cd"><h3>Details</h3>
        <div class="sumb">
            <div><span>Barcode</span><b class="bc">{{ $text }}</b></div>
            <div><span>Brand tag</span><b>{{ $d->brand_tag }}</b></div>
            <div><span>Config</span><b>{{ $d->config }}</b></div>
            <div><span>Status</span><b>{!! \App\Support\Stock::pill($d->status) !!}</b></div>
            @if ($d->customer)<div><span>With customer</span><b>{{ $d->customer }}</b></div>@endif
        </div>
        <div>{!! $svg !!}</div>
    </div>
    <div class="cd"><h3>History</h3>
        <ul class="tl">
            @foreach ($history as $h)
                <li><span class="when">{{ \Carbon\Carbon::parse($h->at)->format('d/m/Y H:i') }}</span><div>{{ $h->text }}</div></li>
            @endforeach
        </ul>
    </div>
</div>
<div class="cd"><h3>Configuration log<small style="font-weight:400;color:#6b7280"> · what it was when it came in, and every change since</small></h3>
    <div class="tw"><table class="t"><thead><tr><th>Date</th><th>What</th><th>Config before</th><th>Config after</th><th>Detail</th><th>By</th></tr></thead><tbody>
        @forelse ($cfglog as $g)
            <tr><td>{{ \Carbon\Carbon::parse($g->at)->format('d/m/Y H:i') }}</td><td><b>{{ \App\Support\ConfigLog::label($g->event) }}</b></td><td>{{ $g->old_config ?? '—' }}</td><td>{{ $g->new_config }}</td><td>{{ $g->note }}</td><td>{{ $g->user ?? '' }}</td></tr>
        @empty
            <tr><td colspan="6" class="empty">No configuration records yet.</td></tr>
        @endforelse
    </tbody></table></div>
</div>
@endif
@endsection
