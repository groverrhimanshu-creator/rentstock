@extends('layouts.app')
@section('title', 'Edit stock entry')
@section('content')
<div class="pg"><h2>Edit stock entry {{ $e->id }}<small>{{ $e->piece_count }} items, added {{ \Carbon\Carbon::parse($e->created_at)->format('d/m/Y') }}</small></h2><a class="ab" href="{{ route('stock.entries') }}">Back</a></div>
<form class="cd" method="post" action="{{ route('stock.entry.update', $e->id) }}" autocomplete="off">
    @csrf @method('PUT')
    @if ($errors->any())<div class="msg bad">{{ $errors->first() }}</div>@endif
    <div class="fg">
        <label class="f"><span>Store</span><select name="store_id">@foreach ($stores as $s)<option value="{{ $s->id }}" @selected((string) old('store_id', $e->store_id) === (string) $s->id)>{{ $s->name }}</option>@endforeach</select></label>
    </div>
    <p class="hint">Items of this entry that are still in stock move to the store you choose. To change the items themselves, delete the entry (only possible while none of them is sold or on rent) and import again.</p>
    <div class="rowx" style="margin-top:16px"><button class="ab solid">Save</button><a class="ab" href="{{ route('stock.entries') }}">Cancel</a></div>
</form>
@endsection
