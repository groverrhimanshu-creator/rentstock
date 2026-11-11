<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;

    protected $fillable = ['name', 'username', 'email', 'password', 'role', 'customer_id', 'active', 'last_login'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'active' => 'boolean',
            'last_login' => 'datetime',
        ];
    }

    public function roleLabel(): string
    {
        return ['admin' => 'Admin', 'subadmin' => 'SubAdmin', 'customer' => 'Customer'][$this->role] ?? $this->role;
    }
}
