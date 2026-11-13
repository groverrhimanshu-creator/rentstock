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
    <div class="tabsx">
        @foreach ($tabs as $i => $t)<a href="{{ route('reports.index', ['tab' => $i]) }}" aria-selected="{{ $i === $tab ? 'true' : 'false' }}">{{ $t }}</a>@endforeach
    </div>
    @if (!$data['rows'] && !empty($data['empty']))
        <div class="msg ok">{{ $data['empty'] }}</div>
    @else
        <form method="get" action="{{ route('reports.index') }}" class="lt" id="rf" style="flex-wrap:wrap;align-items:flex-end;gap:10px"><input type="hidden" name="tab" value="{{ $tab }}">
            @if ($tab === 1)
                <label class="f"><span>From</span><input type="date" name="from" value="{{ $f['from'] }}"></label>
                <label class="f"><span>To</span><input type="date" name="to" value="{{ $f['to'] }}"></label>
                <label class="f"><span>Customer</span>
                    <select name="cust" onchange="this.form.submit()"><option value="">All customers</option>@foreach ($data['customers'] as $c)<option value="{{ $c->id }}" @selected((string) $f['cust'] === (string) $c->id)>{{ $c->name }}</option>@endforeach</select>
                </label>
                <label class="f"><span>Search by customer name</span><input type="search" name="q" value="{{ $f['q'] }}" placeholder="Type a name" maxlength="100"></label>
                <button class="ab sm">Search</button>
                <button class="ab sm solid">Show</button><a class="ab sm" href="{{ route('reports.index', ['tab' => 1]) }}">Last month</a>
            @elseif ($tab === 4)
                <label class="f"><span>From</span><input type="date" name="from" value="{{ $f['from'] }}"></label>
                <label class="f"><span>To</span><input type="date" name="to" value="{{ $f['to'] }}"></label>
                <label class="f"><span>Supplier</span>
                    <select name="supplier"><option value="">All suppliers</option>@foreach ($data['suppliers'] as $s)<option value="{{ $s->id }}" @selected((string) $f['supplier'] === (string) $s->id)>{{ $s->name }}</option>@endforeach</select>
                </label>
                <button class="ab sm solid">Show</button>
                <a class="ab sm" href="{{ route('reports.index', ['tab' => 4]) }}">Last month</a>
            @endif
            <label class="f"><span>Rows per page</span><select name="n" onchange="this.form.submit()">@foreach (\App\Support\Pager::SIZES as $o)<option value="{{ $o }}" @selected($n === $o)>{{ $o }}</option>@endforeach</select></label>
        </form>
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
