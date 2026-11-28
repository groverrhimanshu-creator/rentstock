@extends('layouts.app')
@section('title', 'Reports')
@section('content')
@use('App\Support\Tax')
@use('App\Support\Docs')
@php($f = $data['filters'] ?? [])
<div class="pg"><h2>Reports</h2>
    <div class="rowx">
        @if ($tab === 4)<a class="ab" href="{{ route('reports.purchase.lines', array_merge(request()->query(), ['tab' => $tab])) }}">Download item details</a>@endif
        <a class="ab orange" href="{{ route('reports.csv', array_merge(request()->query(), ['tab' => $tab])) }}">Download CSV</a>
    </div>
</div>
<div class="cd">
    <h3 style="margin:0 0 10px">{{ $tabs[$tab] }}</h3>
    @if (!$data['rows'] && !empty($data['empty']))
        <div class="msg ok">{{ $data['empty'] }}</div>
    @else
        <form method="get" action="{{ route('reports.index') }}" class="lt" id="rf" style="flex-wrap:wrap;align-items:flex-end;gap:10px"><input type="hidden" name="tab" value="{{ $tab }}">
            @if ($tab === 1)
                <label class="f"><span>From (financial year start)</span><input type="date" name="from" value="{{ $f['from'] }}" readonly tabindex="-1" style="pointer-events:none;background:#eef1f4;color:#555"></label>
                <label class="f"><span>To</span><input type="date" name="to" value="{{ $f['to'] }}"></label>
                <label class="f"><span>Customer</span>
                    <select name="cust" onchange="this.form.submit()"><option value="">All customers</option>@foreach ($data['customers'] as $c)<option value="{{ $c->id }}" @selected((string) $f['cust'] === (string) $c->id)>{{ $c->name }}</option>@endforeach</select>
                </label>
                <label class="f"><span>Search by customer name</span><input type="search" name="q" value="{{ $f['q'] }}" placeholder="Type a name" maxlength="100"></label>
                <button class="ab sm">Search</button>
                <button class="ab sm solid">Show</button><a class="ab sm" href="{{ route('reports.index', ['tab' => 1]) }}">Reset</a>
            @elseif ($tab === 3)
                <label class="f"><span>From</span><input type="date" name="from" value="{{ $f['from'] }}"></label>
                <label class="f"><span>To</span><input type="date" name="to" value="{{ $f['to'] }}"></label>
                <label class="f"><span>Customer</span>
                    <select name="customer"><option value="">All customers</option>@foreach ($data['customers'] as $cu)<option value="{{ $cu->id }}" @selected((string) $f['customer'] === (string) $cu->id)>{{ $cu->name }}</option>@endforeach</select>
                </label>
                <label class="f"><span>Item</span><input type="search" name="item" value="{{ $f['item'] }}" list="dl-items" placeholder="Search item, press Enter" maxlength="100" autocomplete="off"></label>
                <label class="f"><span>Category</span><input type="search" name="category" value="{{ $f['category'] }}" list="dl-cats" placeholder="Search category, press Enter" maxlength="100" autocomplete="off"></label>
                <label class="f"><span>Show</span>
                    <select name="by"><option value="bill" @selected($f['by'] === 'bill')>Each invoice</option><option value="item" @selected($f['by'] === 'item')>Item-wise totals</option></select>
                </label>
                <label class="f"><span>Search invoice / customer</span><input type="search" name="q" value="{{ $f['q'] }}" placeholder="Type to search" maxlength="100"></label>
                <button class="ab sm solid">Show</button>
                <a class="ab sm" href="{{ route('reports.index', ['tab' => 3]) }}">Reset</a>
            @elseif ($tab === 4)
                <label class="f"><span>From</span><input type="date" name="from" value="{{ $f['from'] }}"></label>
                <label class="f"><span>To</span><input type="date" name="to" value="{{ $f['to'] }}"></label>
                <label class="f"><span>Supplier</span>
                    <select name="supplier"><option value="">All suppliers</option>@foreach ($data['suppliers'] as $s)<option value="{{ $s->id }}" @selected((string) $f['supplier'] === (string) $s->id)>{{ $s->name }}</option>@endforeach</select>
                </label>
                <label class="f"><span>Item</span><input type="search" name="item" value="{{ $f['item'] }}" list="dl-items" placeholder="Search item, press Enter" maxlength="100" autocomplete="off"></label>
                <label class="f"><span>Category</span><input type="search" name="category" value="{{ $f['category'] }}" list="dl-cats" placeholder="Search category, press Enter" maxlength="100" autocomplete="off"></label>
                <label class="f"><span>Show</span>
                    <select name="by"><option value="bill" @selected($f['by'] === 'bill')>Each purchase</option><option value="item" @selected($f['by'] === 'item')>Item-wise totals</option></select>
                </label>
                <button class="ab sm solid">Show</button>
                <a class="ab sm" href="{{ route('reports.index', ['tab' => 4]) }}">Reset</a>
            @endif
            <label class="f"><span>Rows per page</span><select name="n" onchange="this.form.submit()">@foreach (\App\Support\Pager::SIZES as $o)<option value="{{ $o }}" @selected($n === $o)>{{ $o }}</option>@endforeach</select></label>
        </form>
        @if (!empty($data['items']))<datalist id="dl-items">@foreach ($data['items'] as $it)<option value="{{ $it->name }}">@endforeach</datalist>@endif
        @if (!empty($data['categories']))<datalist id="dl-cats">@foreach ($data['categories'] as $ct)<option value="{{ $ct->name }}">@endforeach</datalist>@endif
        @if ($tab === 4 && ($f['by'] ?? 'bill') === 'bill' && \App\Support\Perm::has('purchase.edit'))
            <details class="cd" style="margin:10px 0" @if (!empty($f['supplier'])) open @endif><summary style="cursor:pointer;font-weight:600">Record a payment to a supplier</summary>
                @if (!empty($data['supDue']))<p class="hint">Bills of this supplier: INR {{ Tax::money($data['supDue']['billed']) }} · paid INR {{ Tax::money($data['supDue']['paid']) }} · <b>left to pay INR {{ Tax::money($data['supDue']['due']) }}</b>@if (($data['supDue']['advance'] ?? 0) > 0) · advance INR {{ Tax::money($data['supDue']['advance']) }}@endif</p>@endif
                <form method="post" action="{{ route('reports.purchase.pay') }}" class="fg" style="margin-top:8px">@csrf
                    <label class="f"><span>Supplier</span><select name="supplier_id" required><option value="">Select</option>@foreach ($data['suppliers'] as $s)<option value="{{ $s->id }}" @selected((string) $f['supplier'] === (string) $s->id)>{{ $s->name }}</option>@endforeach</select></label>
                    <label class="f"><span>Amount paid</span><input type="number" name="amount" min="0.01" step="0.01" required></label>
                    <label class="f"><span>Date</span><input type="date" name="paid_on" value="{{ date('Y-m-d') }}" required></label>
                    <label class="f"><span>Mode</span><select name="mode"><option>Cash</option><option>Cheque/Online</option><option>Credit</option></select></label>
                    <label class="f"><span>Note (optional)</span><input name="note" maxlength="255" placeholder="for example: cheque no."></label>
                    <div><button class="ab solid">Save payment</button></div>
                </form>
                <p class="hint">The amount is counted against the supplier's oldest unpaid bills first, and shown in Paid and Balance below.</p>
                @if (!empty($data['pays']) && count($data['pays']))
                    <div class="tw"><table class="t"><thead><tr><th>Date</th><th class="num">Amount</th><th>Mode</th><th>Note</th><th></th></tr></thead><tbody>
                        @foreach ($data['pays'] as $p)
                            <tr><td>{{ \Carbon\Carbon::parse($p->paid_on)->format('d/m/Y') }}</td><td class="num">{{ Tax::money($p->amount) }}</td><td>{{ $p->mode }}</td><td>{{ $p->note ?: '-' }}</td>
                                <td>@include('partials.delete-btn', ['action' => route('reports.purchase.pay.del', $p->id), 'what' => 'payment', 'cls' => 'sm', 'confirm' => 'Remove this payment?'])</td></tr>
                        @endforeach
                    </tbody></table></div>
                @endif
            </details>
        @endif
        @if (!empty($data['summary']))
            <div class="sumb" style="margin:10px 0">@foreach ($data['summary'] as $x)<div><span>{{ $x[0] }}</span><b>{{ $x[1] }}</b></div>@endforeach</div>
        @endif
        <div class="tw"><table class="t">
            <thead><tr>@foreach ($data['cols'] as $c)<th @class(['num' => in_array($c[1], ['n', 'm', 'c'], true)])>{{ $c[0] }}</th>@endforeach</tr></thead>
            <tbody>
                @forelse ($rows as $k => $r)
                    @php($id = $data['meta'][$first + $k] ?? null)
                    <tr>@foreach ($r as $i => $v)
                        @php($kind = $data['cols'][$i][1])
                        @if ($kind === 'n')<td class="num">{{ $v }}</td>
                        @elseif ($kind === 'c')
                            @if ($id && (int) $v > 0)
                                <td class="num"><button type="button" class="link js-rpop" title="Show the items"
                                    data-kind="{{ $tab === 1 ? 'onrent' : 'purchase' }}"
                                    data-url="{{ $tab === 1 ? route('reports.onrent', ['id' => $id, 'from' => $f['from'], 'to' => $f['to']]) : route('reports.purchase.one', $id) }}"
                                    data-export="{{ $tab === 1 ? route('reports.onrent.export', ['id' => $id, 'from' => $f['from'], 'to' => $f['to']]) : route('reports.purchase.lines', ['id' => $id]) }}">{{ $v }}</button></td>
                            @else<td class="num">{{ $v }}</td>@endif
                        @elseif ($kind === 'm')<td class="num">{{ Tax::money($v) }}</td>
                        @elseif ($kind === 'd')<td>{{ Docs::d($v) }}</td>
                        @else<td @class(['bc' => $i === 0 && $data['cols'][0][0] === 'Barcode'])>{{ $v }}</td>@endif
                    @endforeach</tr>
                @empty
                    <tr><td class="empty" colspan="{{ count($data['cols']) }}">Nothing to show</td></tr>
                @endforelse
            </tbody>
        </table></div>
        @if (!empty($data['note']))<p class="hint">{{ $data['note'] }}</p>@endif
        @include('partials.pager')
    @endif
</div>
<script src="{{ \App\Support\Assets::url('assets/reportpop.js') }}"></script>
@endsection
