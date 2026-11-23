@extends('layouts.app')
@section('title', 'Stock check')
@section('content')
<div class="pg"><h2>Stock check<small>Type a name, model, brand, category or brand tag to see how many are available</small></h2></div>
<div class="cd">
    <form data-list method="get" action="{{ route('stock.check') }}" class="lt">
        <label class="f"><span>Show</span><select name="n">@foreach (\App\Support\Pager::SIZES as $o)<option value="{{ $o }}" @selected($n === $o)>{{ $o }}</option>@endforeach</select></label>
        <label class="f"><span>&nbsp;</span><span><input type="checkbox" name="instock" value="1" @checked($onlyStock)> Only items in stock</span></label>
        <div class="sr"><label class="f"><span>Search name / model</span><input name="q" value="{{ $q }}" placeholder="for example: Dell 5420" autocomplete="off" autofocus></label></div>
    </form>
    <div class="tw"><table class="t">
        <thead><tr><th>Name / model</th><th>Brand</th><th>Category</th><th class="num">In stock</th><th class="num">On rent</th><th class="num">In repair</th><th class="num">Sold</th></tr></thead>
        <tbody>
            @forelse ($rows as $r)
                @php($go = $r->tracked ? route('stock.inventory', ['item' => $r->id]) : null)
                <tr @if ($go) class="clk" data-href="{{ $go }}" @endif>
                    <td>{{ $r->name }}@if (!$r->tracked) <span class="pill idle">by quantity</span>@endif</td><td>{{ $r->brand }}</td><td>{{ $r->category }}</td>
                    @if ($r->tracked)
                        <td class="num"><b>{{ $r->n_in }}</b></td><td class="num">{{ $r->n_rent }}</td><td class="num">{{ $r->n_rep }}</td><td class="num">{{ $r->n_sold }}</td>
                    @else
                        <td class="num"><b>{{ rtrim(rtrim(number_format((float) $r->qty, 2, '.', ''), '0'), '.') }}</b></td><td class="num">-</td><td class="num">-</td><td class="num">-</td>
                    @endif
                </tr>
            @empty
                <tr><td class="empty" colspan="7">No item matches</td></tr>
            @endforelse
        </tbody>
    </table></div>
    <p class="hint">Click a model to see every piece (barcode, brand tag, config, who has it).</p>
    @include('partials.pager')
</div>
@endsection
