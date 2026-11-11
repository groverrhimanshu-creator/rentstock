@extends('layouts.app')
@section('title', 'Invoices & Challans')
@section('content')
<div class="pg"><h2>Invoices & challans<small>Documents for what you have rented or bought</small></h2></div>
<div class="cd"><h3>Rental documents</h3>
    <form method="get" action="{{ url()->current() }}" class="lt"><input type="hidden" name="n2" value="{{ $n2 }}"><input type="hidden" name="q2" value="{{ $q2 }}">
        <label class="f"><span>Show</span><select name="n1" onchange="this.form.submit()">@foreach (\App\Support\Pager::SIZES as $o)<option value="{{ $o }}" @selected($n1 === $o)>{{ $o }}</option>@endforeach</select></label>
        <div class="sr"><label class="f"><span>Search</span><input name="q1" value="{{ $q1 }}" placeholder="Type to search" autocomplete="off"></label><button class="ab">Search</button></div></form>
    <div class="tw"><table class="t"><thead><tr><th>Number</th><th>Type</th><th>Date</th><th class="num">Items</th></tr></thead><tbody>
        @forelse ($rentals as $r)<tr class="clk" data-href="{{ route('my.rental.show', $r->id) }}"><td>{{ $r->no }}</td><td>{{ $r->kind === 'invoice' ? 'Rental invoice' : 'Challan' }}</td><td>{{ \Carbon\Carbon::parse($r->created_at)->format('d/m/Y') }}</td><td class="num">{{ $r->items }}</td></tr>@empty<tr><td class="empty" colspan="4">Nothing to show</td></tr>@endforelse
    </tbody></table></div>
    @include('partials.pager', ['rows' => $rentals])
</div>
<div class="cd"><h3>Sale invoices</h3>
    <form method="get" action="{{ url()->current() }}" class="lt"><input type="hidden" name="n1" value="{{ $n1 }}"><input type="hidden" name="q1" value="{{ $q1 }}">
        <label class="f"><span>Show</span><select name="n2" onchange="this.form.submit()">@foreach (\App\Support\Pager::SIZES as $o)<option value="{{ $o }}" @selected($n2 === $o)>{{ $o }}</option>@endforeach</select></label>
        <div class="sr"><label class="f"><span>Search</span><input name="q2" value="{{ $q2 }}" placeholder="Type to search" autocomplete="off"></label><button class="ab">Search</button></div></form>
    <div class="tw"><table class="t"><thead><tr><th>Sale invoice</th><th>Date</th><th class="num">Items</th></tr></thead><tbody>
        @forelse ($sales as $s)<tr class="clk" data-href="{{ route('my.sale.show', $s->id) }}"><td>{{ $s->no }}</td><td>{{ \Carbon\Carbon::parse($s->created_at)->format('d/m/Y') }}</td><td class="num">{{ $s->items }}</td></tr>@empty<tr><td class="empty" colspan="3">Nothing to show</td></tr>@endforelse
    </tbody></table></div>
    @include('partials.pager', ['rows' => $sales])
</div>
@endsection
