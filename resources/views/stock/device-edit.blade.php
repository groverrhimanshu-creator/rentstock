@extends('layouts.app')
@section('title', 'Edit item')
@section('content')
<div class="pg"><h2>Edit item<small>{{ $d->model }} · {{ $text }}</small></h2><a class="ab" href="{{ route('stock.device', $d->code) }}">Back</a></div>
<form class="cd" data-validate method="post" action="{{ route('stock.device.update', $d->code) }}" autocomplete="off">
    @csrf @method('PUT')
    <div class="msg bad" data-errbox @if (!$errors->any()) hidden @endif>@if ($errors->any())<ul>@foreach ($errors->all() as $err)<li>{{ $err }}</li>@endforeach</ul>@endif</div>
    <div class="fg">
        <label class="f"><span>Brand tag</span><input name="brand_tag" data-label="Brand tag" data-req value="{{ old('brand_tag', $d->brand_tag) }}" maxlength="60"></label>
        <label class="f"><span>Config</span><input name="config" value="{{ old('config', $d->config) }}" maxlength="255"></label>
        <label class="f"><span>Store</span><select name="store_id">@foreach ($stores as $s)<option value="{{ $s->id }}" @selected((string) old('store_id', $d->store_id) === (string) $s->id)>{{ $s->name }}</option>@endforeach</select></label>
    </div>
    <p class="hint">The barcode and the model cannot be changed here.</p>
    <div class="rowx" style="margin-top:16px"><button class="ab solid">Save</button><button class="ab" name="print" value="1">Save and print label</button><a class="ab" href="{{ route('stock.device', $d->code) }}">Cancel</a></div>
</form>
@endsection
