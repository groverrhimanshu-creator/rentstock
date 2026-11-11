@extends('layouts.app')
@section('title', 'Invoice ' . $s->no)
@section('content')
@php
    $mode = $s->tax_mode;
    $title = $s->type === 'GST' ? 'TAX INVOICE' : 'INVOICE';
@endphp
<div class="pg"><h2>Invoice {{ $s->no }}</h2><div class="rowx"><button type="button" class="ab orange" onclick="window.print()">Print</button>
    @if (auth()->user()->role === 'admin' && !$mine)
        <a class="ab" href="{{ route('sale.edit', $s->id) }}">Edit</a>
        @include('partials.delete-btn', ['action' => route('sale.destroy', $s->id), 'what' => 'invoice', 'blocked' => $blocked, 'confirm' => 'Delete invoice ' . $s->no . '? Its items go back into stock. This cannot be undone.'])
    @endif
    <a class="ab" href="{{ $mine ? route('myinv') : route('sale.index') }}">Back</a></div></div>
<div class="msg ok">Stock reduced for {{ $count }} items.</div>
<div class="paper">
    @include('partials.paper-head', ['title' => $title, 'sub' => 'No: ' . e($s->no) . '<br>Date: ' . \Carbon\Carbon::parse($s->created_at)->format('d/m/Y')])
    <div class="three">
        @include('partials.party', ['h' => 'Billed to', 'name' => $s->cname, 'address' => $s->address, 'city' => $s->city, 'state' => $s->state, 'code' => $s->state_code, 'gstin' => $s->gstin, 'phone' => $s->phone])
        @include('partials.party', ['h' => 'Shipped to', 'name' => $s->cname, 'address' => $s->address, 'city' => $s->city, 'state' => $s->state, 'code' => $s->state_code, 'gstin' => $s->gstin, 'phone' => $s->phone])
        <div><h5>Details</h5><p>Payment: {{ $s->pay_mode }}<br>Place of supply: {{ $s->state }}<br>Type: {{ $s->type === 'GST' ? 'GST invoice' : 'Bill of supply' }}@if ($s->remark)<br>Remark: {{ $s->remark }}@endif</p></div>
    </div>
    <table>
        <thead><tr><th>#</th><th>Description</th><th>HSN</th><th class="num">Qty</th><th class="num">Rate</th><th class="num">Amount</th>@if ($mode !== 'none')<th class="num">GST</th>@endif</tr></thead>
        <tbody>
            @foreach ($groups as $i => $g)
                <tr><td>{{ $i + 1 }}</td><td><b>{{ $g['name'] }}</b><br><span class="codes">{{ implode(', ', array_map(fn ($c) => \App\Support\Barcodes::text($c), $g['codes'])) }}</span></td><td>{{ $g['hsn'] }}</td>
                    <td class="num">{{ $g['qty'] }} Nos</td><td class="num">{{ \App\Support\Tax::money($g['price']) }}</td><td class="num">{{ \App\Support\Tax::money(\App\Support\Tax::r2($g['qty'] * $g['price'])) }}</td>
                    @if ($mode !== 'none')<td class="num">{{ rtrim(rtrim(number_format($g['gst'], 2, '.', ''), '0'), '.') }}%</td>@endif</tr>
            @endforeach
        </tbody>
    </table>
    <div class="bot">
        <div class="nt"><b>Amount in words</b><br>{{ \App\Support\Tax::words((float) $s->total) }}<br><br><b>Bank details</b><br>{{ $company->bank }} · A/c {{ $company->acc }} · IFSC {{ $company->ifsc }}<br><br><span style="font-size:11px">Goods once sold will not be taken back. Rented goods remain the property of the company until sold.</span></div>
        <div class="tt">
            <div><span>Taxable value</span><span>{{ \App\Support\Tax::money($s->taxable) }}</span></div>
            @if ($mode === 'intra')<div><span>CGST</span><span>{{ \App\Support\Tax::money($s->cgst) }}</span></div><div><span>SGST</span><span>{{ \App\Support\Tax::money($s->sgst) }}</span></div>
            @elseif ($mode === 'inter')<div><span>IGST</span><span>{{ \App\Support\Tax::money($s->igst) }}</span></div>@endif
            <div><b>Total</b><b>{{ \App\Support\Tax::money($s->total) }}</b></div>
        </div>
    </div>
    <div class="sign" style="display:flex;justify-content:space-between;gap:20px;margin-top:26px"><div>Customer signature</div><div style="text-align:right">For <b>{{ $company->name }}</b><br><br>Authorised signatory</div></div>
</div>
@endsection
