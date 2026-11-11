@extends('layouts.app')
@section('title', 'Company profile')
@section('content')
<div class="pg"><h2>Company profile<small>These details print on every invoice and challan</small></h2></div>
<form class="cd" data-validate method="post" action="{{ route('company.update') }}" autocomplete="off">
    @csrf
    @method('PUT')
    @php
        $fields = [['name', 'Company name'], ['gstin', 'GSTIN'], ['pan', 'PAN'], ['phone', 'Phone'], ['email', 'Email'],
            ['line1', 'Address line 1'], ['line2', 'Address line 2'], ['bank', 'Bank'], ['acc', 'Account number'], ['ifsc', 'IFSC'], ['prefix', 'Document prefix']];
        $checks = ['gstin' => 'gstin', 'pan' => 'pan', 'email' => 'email', 'ifsc' => 'ifsc', 'prefix' => 'prefix'];
    @endphp
    <div class="msg bad" data-errbox @if (!$errors->any()) hidden @endif>@if ($errors->any())<ul>@foreach ($errors->all() as $err)<li>{{ $err }}</li>@endforeach</ul>@endif</div>
    <div class="fg">
        @foreach ($fields as [$name, $label])
            <label class="f"><span>{{ $label }}</span><input name="{{ $name }}" value="{{ old($name, $c[$name] ?? '') }}" data-label="{{ $label }}" @if ($name === 'name')data-req @endif @if (isset($checks[$name]))data-check="{{ $checks[$name] }}" @endif @if (in_array($name, ['gstin', 'pan', 'ifsc', 'prefix']))data-upper style="text-transform:uppercase"@endif></label>
        @endforeach
    </div>
    <div class="rowx" style="margin-top:16px"><button class="ab solid">Save profile</button></div>
</form>
@endsection
