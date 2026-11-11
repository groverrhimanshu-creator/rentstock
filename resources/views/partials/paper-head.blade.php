<div class="hd">
    <div style="display:flex;gap:12px;align-items:center">
        <img class="lg2" src="{{ asset('assets/logo.png') }}" alt="{{ $company->name }}">
        <div><b style="font-size:16px">{{ $company->name }}</b><br>{{ $company->line1 }}<br>{{ $company->line2 }}<br>GSTIN: {{ $company->gstin }} · PAN: {{ $company->pan }}<br>{{ $company->phone }} · {{ $company->email }}</div>
    </div>
    <div class="rt" style="text-align:right"><div style="font:700 20px/1.2 var(--f-body);color:#0A84C6">{{ $title }}</div><div style="font-size:12.5px;line-height:1.6;margin-top:4px">{!! $sub !!}</div></div>
</div>
