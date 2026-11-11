@extends('layouts.app')
@section('title', 'Reports')
@section('content')
@use('App\Support\Tax')
@use('App\Support\Docs')
<div class="pg"><h2>Reports</h2><a class="ab orange" href="{{ route('reports.csv', ['tab' => $tab]) }}">Download CSV</a></div>
<div class="cd">
    <div class="tabsx">
        @foreach ($tabs as $i => $t)<a href="{{ route('reports.index', ['tab' => $i]) }}" aria-selected="{{ $i === $tab ? 'true' : 'false' }}">{{ $t }}</a>@endforeach
    </div>
    @if (!$data['rows'] && !empty($data['empty']))
        <div class="msg ok">{{ $data['empty'] }}</div>
    @else
        <form method="get" action="{{ route('reports.index') }}" class="lt"><input type="hidden" name="tab" value="{{ $tab }}">
            <label class="f"><span>Show</span><select name="n" onchange="this.form.submit()">@foreach (\App\Support\Pager::SIZES as $o)<option value="{{ $o }}" @selected($n === $o)>{{ $o }}</option>@endforeach</select></label>
        </form>
        <div class="tw"><table class="t">
            <thead><tr>@foreach ($data['cols'] as $c)<th @class(['num' => in_array($c[1], ['n', 'm'], true)])>{{ $c[0] }}</th>@endforeach</tr></thead>
            <tbody>
                @forelse ($rows as $r)
                    <tr>@foreach ($r as $i => $v)
                        @php($k = $data['cols'][$i][1])
                        @if ($k === 'n')<td class="num">{{ $v }}</td>
                        @elseif ($k === 'm')<td class="num">{{ Tax::money($v) }}</td>
                        @elseif ($k === 'd')<td>{{ Docs::d($v) }}</td>
                        @else<td @class(['bc' => $i === 0 && $data['cols'][0][0] === 'Barcode'])>{{ $v }}</td>@endif
                    @endforeach</tr>
                @empty
                    <tr><td class="empty" colspan="{{ count($data['cols']) }}">Nothing to show</td></tr>
                @endforelse
            </tbody>
        </table></div>
        @include('partials.pager')
    @endif
</div>
@endsection
