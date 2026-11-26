<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = ['shop_id', 'name', 'email', 'username', 'password', 'role', 'pin_hash', 'is_active', 'permissions'];
    protected $hidden = ['password', 'pin_hash', 'remember_token'];

    protected function casts(): array
    {
        return ['email_verified_at' => 'datetime', 'password' => 'hashed', 'is_active' => 'boolean', 'permissions'=>'array'];
    }

    public function accessRole() { return $this->belongsTo(AccessRole::class,'access_role_id'); }
    public function canAccess(string $permission): bool { return in_array($permission, $this->accessRole?->permissions ?? $this->permissions ?? [], true); }

    public function shop() { return $this->belongsTo(Shop::class); }
}
