@extends('layouts.app')
@section('title', 'Users & Permissions')
@section('content')
<div class="pg"><h2>Users &amp; Permissions<small>Staff logins and what each one may do. Customer logins are under Masters.</small></h2><a class="ab" href="{{ route('users.create') }}">+ Add User</a></div>
<div class="cd">
    @include('partials.list-controls')
    <div class="tw">
        <table class="t">
            <thead><tr><th>Username</th><th>Name</th><th>Role</th><th>Permissions</th><th>Last login</th><th>Status</th><th></th></tr></thead>
            <tbody>
                @forelse ($rows as $r)
                    <tr>
                        <td>{{ $r->username }}</td>
                        <td>{{ $r->name }}</td>
                        <td>{{ $r->roleLabel() }}</td>
                        <td>{{ \App\Support\Perm::summary($r) }}</td>
                        <td>{{ $r->last_login ? $r->last_login->format('d/m/Y H:i') : '-' }}</td>
                        <td><span class="pill {{ $r->active ? 'ok' : 'idle' }}">{{ $r->active ? 'Active' : 'Disabled' }}</span></td>
                        <td>
                            <div class="rowx" style="flex-wrap:nowrap">
                                <a class="ab sm solid" href="{{ route('users.edit', $r->id) }}">{{ $r->role === 'admin' ? 'Edit' : 'Permissions' }}</a>
                                @if ($r->id !== auth()->id())
                                    <form method="post" action="{{ route('users.toggle', $r->id) }}" style="margin:0">@csrf<button class="ab sm">{{ $r->active ? 'Disable' : 'Enable' }}</button></form>
                                    @include('partials.delete-btn', ['action' => route('users.destroy', $r->id), 'what' => 'user', 'cls' => 'sm', 'confirm' => 'Delete the login ' . $r->username . '? This person will no longer be able to sign in. This cannot be undone.'])
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td class="empty" colspan="7">Nothing to show</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @include('partials.pager')
</div>
@endsection
