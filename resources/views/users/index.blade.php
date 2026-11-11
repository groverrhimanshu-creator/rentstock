@extends('layouts.app')
@section('title', 'Logins')
@section('content')
<div class="head">
    <div><h1>Logins</h1><p>Who can sign in</p></div>
    <a class="btn solid" href="{{ route('users.create') }}">+ Add login</a>
</div>
<div class="card">
    <div class="tw">
        <table class="t">
            <thead><tr><th>Username</th><th>Name</th><th>Role</th><th>Customer</th><th>Status</th><th>Last login</th><th></th></tr></thead>
            <tbody>
                @foreach ($users as $r)
                    <tr>
                        <td>{{ $r->username }}</td>
                        <td>{{ $r->name }}</td>
                        <td>{{ ucfirst($r->role) }}</td>
                        <td>{{ $r->customer }}</td>
                        <td><span class="pill {{ $r->active ? 'ok' : 'bad' }}">{{ $r->active ? 'Active' : 'Off' }}</span></td>
                        <td>{{ $r->last_login ? \Illuminate\Support\Carbon::parse($r->last_login)->format('d M Y, h:i A') : 'Never' }}</td>
                        <td class="act"><a class="btn sm" href="{{ route('users.edit', $r->id) }}">Edit</a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
