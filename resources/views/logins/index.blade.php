@extends('layouts.app')
@section('title', 'Customer logins')
@section('content')
<div class="pg"><h2>Customer logins<small>Each customer gets a login to see what they have on rent</small></h2><a class="ab" href="{{ route('logins.create') }}">+ Add Login</a></div>
<div class="cd">
    @include('partials.list-controls')
    <div class="tw">
        <table class="t">
            <thead><tr><th>Username</th><th>Contact name</th><th>Customer</th><th class="num">Items on rent</th><th>Status</th><th></th></tr></thead>
            <tbody>
                @forelse ($rows as $r)
                    <tr>
                        <td>{{ $r->username }}</td>
                        <td>{{ $r->name }}</td>
                        <td>{{ $r->customer }}</td>
                        <td class="num">{{ $r->on_rent }}</td>
                        <td><span class="pill {{ $r->active ? 'ok' : 'idle' }}">{{ $r->active ? 'Active' : 'Disabled' }}</span></td>
                        <td>
                            <div class="rowx" style="flex-wrap:nowrap">
                                <form method="post" action="{{ route('logins.toggle', $r->id) }}" style="margin:0">@csrf<button class="ab sm">{{ $r->active ? 'Disable' : 'Enable' }}</button></form>
                                <a class="ab sm" href="{{ route('logins.edit', $r->id) }}">Edit</a>
                                @include('partials.delete-btn', ['action' => route('logins.destroy', $r->id), 'what' => 'login', 'cls' => 'sm', 'confirm' => 'Delete the login ' . $r->username . '? The customer will no longer be able to sign in. This cannot be undone.'])
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td class="empty" colspan="6">Nothing to show</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @include('partials.pager')
</div>
@endsection
