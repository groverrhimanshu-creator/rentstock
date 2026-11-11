@extends('layouts.app')
@section('title', $m['title'] . ' list')
@section('content')
<div class="pg"><h2>{{ $m['title'] }} list<small>Masters</small></h2><a class="ab" href="{{ route('masters.create', $key) }}">+ Add {{ $m['title'] }}</a></div>
<div class="cd">
    @include('partials.list-controls')
    <div class="tw">
        <table class="t">
            <thead><tr>@foreach ($m['cols'] as $c)<th @if (in_array($c[2] ?? '', ['num', 'money'], true))class="num"@endif>{{ $c[1] }}</th>@endforeach<th></th></tr></thead>
            <tbody>
                @forelse ($rows as $r)
                    <tr class="clk" data-href="{{ route('masters.edit', [$key, $r->id]) }}">
                        @foreach ($m['cols'] as $c)
                            @php($v = $r->{$c[0]} ?? '')
                            @if (($c[2] ?? '') === 'money')
                                <td class="num">{{ number_format((float) $v, 2) }}</td>
                            @elseif (($c[2] ?? '') === 'num')
                                <td class="num">{{ $v }}</td>
                            @elseif (($c[2] ?? '') === 'assoc')
                                <td><button type="button" class="link js-assoc" data-url="{{ route('masters.assoc', [$key, $r->id]) }}" data-export="{{ route('masters.assoc.export', [$key, $r->id]) }}" title="Show the items">{{ (int) $v }}</button></td>
                            @elseif (($c[2] ?? '') === 'kind')
                                <td>{{ $v ? 'Barcoded' : 'By quantity' }}</td>
                            @else
                                <td>{{ trim((string) $v) }}</td>
                            @endif
                        @endforeach
                        <td><div class="rowx" style="flex-wrap:nowrap"><a class="ab sm" href="{{ route('masters.edit', [$key, $r->id]) }}">Edit</a>@include('partials.delete-btn', ['action' => route('masters.destroy', [$key, $r->id]), 'what' => strtolower($m['title']), 'cls' => 'sm', 'blocked' => \App\Support\Deps::master($key, $r->id)])</div></td>
                    </tr>
                @empty
                    <tr><td class="empty" colspan="{{ count($m['cols']) + 1 }}">Nothing to show</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @include('partials.pager')
</div>
<script src="{{ asset('assets/assoc.js') }}"></script>
@endsection
