@extends('layouts.app')
@section('title', 'Payments & Reminders')
@section('content')
@use('App\Support\Tax')
@use('App\Support\Docs')
@use('App\Support\Icons')
<div class="pg"><h2>Payments & reminders<small>Unpaid invoices and automatic email reminders</small></h2>
    <form method="post" action="{{ route('payments.remind-all') }}" style="margin:0">@csrf<button class="ab orange">Send reminders to all overdue ({{ $over }})</button></form></div>
<div class="kp">
    <div class="kc">{!! Icons::get('rent') !!}<span>Total outstanding</span><b class="plain">{{ Tax::money($total) }}</b></div>
    <div class="kc alert">{!! Icons::get('warn') !!}<span>Overdue invoices</span><b class="plain" style="background:none;color:#C0392B">{{ $over }}</b></div>
    <div class="kc">{!! Icons::get('bars') !!}<span>Reminders sent</span><b class="plain">{{ $sent }}</b></div>
</div>
<div class="cd"><h3>Test the email settings</h3>
    <form method="post" action="{{ route('payments.test-mail') }}" class="rowx" style="align-items:flex-end">
        @csrf
        <label class="f" style="width:320px;max-width:100%"><span>Send a sample reminder to</span><input type="email" name="to" placeholder="you@example.com" value="{{ old('to') }}" autocomplete="off"></label>
        <button class="ab">Send test email</button>
    </form>
    <p class="hint">Current mail setting: <b>{{ config('mail.default') }}</b>. {{ config('mail.default') === 'log' ? 'With "log" nothing is emailed; the text is written to storage\logs\laravel.log.' : 'The test goes out through your SMTP settings in the .env file.' }} The test is not added to the reminder log.</p>
</div>
<div class="cd"><h3>Automatic reminders</h3>
    <form method="post" action="{{ route('payments.settings') }}" class="rowx" style="align-items:flex-end">
        @csrf
        <input type="hidden" name="on" id="on" value="{{ $on ? '1' : '0' }}">
        <button type="button" class="ab {{ $on ? 'solid' : '' }}" id="onbtn">{{ $on ? 'On' : 'Off' }}</button>
        <label class="f" style="width:200px"><span>Send again every</span><select name="every">@foreach ($everyOptions as $n)<option value="{{ $n }}" @selected($every === $n)>{{ $n }} days</option>@endforeach</select></label>
        <button class="ab">Save</button>
    </form>
    <p class="hint">While an invoice is unpaid, the customer gets an email at the address on file, starting on the due date and then every {{ $every }} days until it is paid. Invoices are due {{ \App\Support\Dues::DUE_DAYS }} days after the invoice date.</p>
</div>
<script>document.getElementById('onbtn').addEventListener('click', function () { var i = document.getElementById('on'); i.value = i.value === '1' ? '0' : '1'; this.textContent = i.value === '1' ? 'On' : 'Off'; this.classList.toggle('solid', i.value === '1'); });</script>
<div class="cd"><h3>Unpaid invoices</h3>
    <form method="get" action="{{ route('payments.index') }}" class="lt">
        <label class="f"><span>Show</span><select name="n" onchange="this.form.submit()">@foreach (\App\Support\Pager::SIZES as $o)<option value="{{ $o }}" @selected($n === $o)>{{ $o }}</option>@endforeach</select></label>
    </form>
    <div class="tw"><table class="t">
        <thead><tr><th>Invoice</th><th>Customer</th><th>Due date</th><th class="num">Balance</th><th>Status</th><th>Last reminder</th><th>Next reminder</th><th></th></tr></thead>
        <tbody>
            @forelse ($list as $x)
                <tr><td>{{ $x->no }}<br><small>{{ $x->kind === 'rental' ? 'Rental' : 'Sale' }}</small></td><td>{{ $x->customer }}</td><td>{{ Docs::d($x->due) }}</td><td class="num">{{ Tax::money($x->bal) }}</td>
                    <td>@if ($x->late > 0)<span class="pill bad">Overdue {{ $x->late }} days</span>@else<span class="pill info">Due in {{ -$x->late }} days</span>@endif</td>
                    <td>{{ $x->last ? Docs::d($x->last) : 'None yet' }}</td><td>{{ $on ? Docs::d($x->next) : 'Off' }}</td>
                    <td><div class="rowx" style="flex-wrap:nowrap">
                        <form method="post" action="{{ route('payments.remind', [$x->kind, $x->id]) }}" style="margin:0">@csrf<button class="ab sm">Remind</button></form>
                        <form method="post" action="{{ route('payments.paid', [$x->kind, $x->id]) }}" style="margin:0">@csrf<button class="ab sm">Paid</button></form></div></td></tr>
            @empty
                <tr><td class="empty" colspan="8">Everything is paid.</td></tr>
            @endforelse
        </tbody>
    </table></div>
    @if ($list->total() > 0)@include('partials.pager', ['rows' => $list])@endif
</div>
<div class="cols">
    <div class="cd"><h3>Reminder log</h3><div class="tw"><table class="t"><thead><tr><th>Sent</th><th>Customer</th><th>Email</th><th>Subject</th></tr></thead><tbody>
        @forelse ($log as $r)<tr><td>{{ \Carbon\Carbon::parse($r->created_at)->format('d/m/Y H:i') }}</td><td>{{ $r->customer }}</td><td>{{ $r->to_email }}</td><td>{{ $r->subject }}</td></tr>@empty<tr><td class="empty" colspan="4">None yet</td></tr>@endforelse
    </tbody></table></div></div>
    @if ($last)<div class="cd"><h3>Latest email (preview)</h3><p class="hint">To: {{ $last->to_email }}<br>Subject: {{ $last->subject }}</p><div class="paper" style="white-space:pre-wrap;padding:14px;font-size:12.5px">{{ $last->body }}</div></div>@endif
</div>
@endsection
