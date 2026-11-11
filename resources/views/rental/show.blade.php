@extends('layouts.app')
@section('title', ($r->kind === 'invoice' ? 'Rental invoice ' : 'Challan ') . $r->no)
@section('content')
@use('App\Support\Tax')
@use('App\Support\Barcodes')
@use('App\Support\Docs')
@php
    $invoice = $r->kind === 'invoice';
    $mode = $r->tax_mode;
    $admin = auth()->user()->role === 'admin' && !$mine;
    $back = $mine ? route('myinv') : route('rental.index');
@endphp
<div class="pg"><h2>{{ $invoice ? 'Rental invoice ' : 'Challan ' }}{{ $r->no }}<small>{{ $r->cname }}</small></h2><div class="rowx"><button type="button" class="ab orange" onclick="window.print()">Print</button>
    @if ($admin)
        <a class="ab" href="{{ route('rental.edit', $r->id) }}">Edit</a>
        @include('partials.delete-btn', ['action' => route('rental.destroy', $r->id), 'what' => $invoice ? 'rental invoice' : 'challan', 'blocked' => $blocked, 'confirm' => $invoice ? 'Delete rental invoice ' . $r->no . '? This cannot be undone.' : 'Delete challan ' . $r->no . '? Its items go back into stock. This cannot be undone.'])
    @endif
    <a class="ab" href="{{ $back }}">Back</a></div></div>
@if (!$mine)<div class="msg ok">{{ $invoice ? $count . ' rented items billed' . ($soldCount ? ' and ' . $soldCount . ' sold ' . ($soldCount === 1 ? 'item' : 'items') . ' added (taken out of stock)' : '. Stock is unchanged') . '.' : $count . ' items are now on rent with ' . $r->cname . '.' }}</div>@endif
@if ($invoice)
<div class="paper">
    @include('partials.paper-head', ['title' => $r->type === 'GST' ? 'TAX INVOICE' : 'INVOICE', 'sub' => 'Rental invoice · No: ' . e($r->no) . '<br>Date: ' . Docs::d($r->created_at) . '<br>Rent for: ' . Docs::d($r->period_from) . ' to ' . Docs::d($r->period_to)])
    <div class="three">
        @include('partials.party', ['h' => 'Billed to', 'name' => $r->cname, 'address' => $r->address, 'city' => $r->city, 'state' => $r->state, 'code' => $r->state_code, 'gstin' => $r->gstin, 'phone' => $r->phone])
        @include('partials.party', ['h' => 'Shipped to', 'name' => $r->cname, 'address' => $r->address, 'city' => $r->city, 'state' => $r->state, 'code' => $r->state_code, 'gstin' => $r->gstin, 'phone' => $r->phone])
        <div><h5>Details</h5><p>Payment: {{ $r->pay_mode }}<br>Place of supply: {{ $r->state }}<br>Type: {{ $r->type === 'GST' ? 'GST invoice' : 'Bill of supply' }}@if ($r->remark)<br>Remark: {{ $r->remark }}@endif</p></div>
    </div>
    <table>
        <thead><tr><th>#</th><th>Description</th><th>HSN</th><th class="num">Qty</th><th class="num">Rate</th><th class="num">Amount</th>@if ($mode !== 'none')<th class="num">GST</th>@endif</tr></thead>
        <tbody>
            @foreach ($groups as $i => $g)
                <tr><td>{{ $i + 1 }}</td><td><b>{{ $g['cat'] }} Rent</b></td><td>{{ $g['hsn'] }}</td>
                    <td class="num">{{ $g['qty'] }} Nos</td><td class="num">{{ Tax::money($g['price']) }}</td><td class="num">{{ Tax::money(Tax::r2($g['qty'] * $g['price'])) }}</td>
                    @if ($mode !== 'none')<td class="num">{{ rtrim(rtrim(number_format($g['gst'], 2, '.', ''), '0'), '.') }}%</td>@endif</tr>
            @endforeach
            @foreach ($sales as $j => $g)
                <tr><td>{{ count($groups) + $j + 1 }}</td><td><b>{{ $g['name'] }}</b></td><td>{{ $g['hsn'] }}</td>
                    <td class="num">{{ $g['qty'] }} Nos</td><td class="num">{{ Tax::money($g['price']) }}</td><td class="num">{{ Tax::money(Tax::r2($g['qty'] * $g['price'])) }}</td>
                    @if ($mode !== 'none')<td class="num">{{ rtrim(rtrim(number_format($g['gst'], 2, '.', ''), '0'), '.') }}%</td>@endif</tr>
            @endforeach
        </tbody>
    </table>
    <div class="bot">
        <div class="nt"><b>Amount in words</b><br>{{ Tax::words((float) $r->total) }}<br><br><b>Bank details</b><br>{{ $company->bank }} · A/c {{ $company->acc }} · IFSC {{ $company->ifsc }}<br><br><span style="font-size:11px">Goods once sold will not be taken back. Rented goods remain the property of the company until sold.</span></div>
        <div class="tt">
            <div><span>Taxable value</span><span>{{ Tax::money($r->taxable) }}</span></div>
            @if ($mode === 'intra')<div><span>CGST</span><span>{{ Tax::money($r->cgst) }}</span></div><div><span>SGST</span><span>{{ Tax::money($r->sgst) }}</span></div>
            @elseif ($mode === 'inter')<div><span>IGST</span><span>{{ Tax::money($r->igst) }}</span></div>@endif
            <div><b>Total</b><b>{{ Tax::money($r->total) }}</b></div>
        </div>
    </div>
    <div class="sign" style="display:flex;justify-content:space-between;gap:20px;margin-top:26px"><div>Customer signature</div><div style="text-align:right">For <b>{{ $company->name }}</b><br><br>Authorised signatory</div></div>
</div>
@else
<div class="paper">
    @include('partials.paper-head', ['title' => 'DELIVERY CHALLAN', 'sub' => 'Ch No: ' . e(substr($r->no, 3)) . '<br>Date: ' . Docs::d($r->created_at)])
    <div class="three">
        @include('partials.party', ['h' => 'Delivered to', 'name' => $r->cname, 'address' => $r->address, 'city' => $r->city, 'state' => $r->state, 'code' => $r->state_code, 'gstin' => $r->gstin, 'phone' => $r->phone])
        <div><h5>Purpose</h5><p>Goods given on <b>rent</b>.<br>Not for sale. Ownership remains with {{ $company->name }}.</p></div>
        <div><h5>Expected return</h5><p>{{ $r->return_by ? 'Short term · ' . Docs::d($r->return_by) : 'Long term' }}</p></div>
    </div>
    <table>
        <thead><tr><th>#</th><th>Item</th><th>HSN/SAC</th><th class="num">Qty</th><th>Barcodes</th></tr></thead>
        <tbody>
            @foreach ($groups as $i => $g)
                <tr><td>{{ $i + 1 }}</td><td><b>{{ $g['name'] }}</b></td><td>{{ $g['hsn'] }}</td><td class="num">{{ $g['qty'] }} Nos</td><td><span class="codes">{{ implode(', ', array_map(fn ($c) => Barcodes::text($c), $g['codes'])) }}</span></td></tr>
            @endforeach
            <tr class="tot"><td></td><td><b>Total items</b></td><td></td><td class="num"><b>{{ $count }}</b></td><td></td></tr>
        </tbody>
    </table>
    <div class="sign" style="display:flex;justify-content:space-between;gap:20px;margin-top:26px"><div>Received in good condition<br><br>Customer signature &amp; stamp</div><div style="text-align:right">For <b>{{ $company->name }}</b><br><br>Authorised signatory</div></div>
</div>
@endif
@endsection
