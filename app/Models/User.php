<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Models\{Role, Permission, UserDetail};

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    /* ======================
     |  Relationships
     ====================== */

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_role')->withTimestamps();
    }

    public function directPermissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'user_permission')
            ->withPivot('allowed')
            ->withTimestamps();
    }

    public function detail()
    {
        return $this->hasOne(UserDetail::class);
    }

    /* ======================
     |  RBAC Helpers
     ====================== */

    public function hasRole(string $roleName): bool
    {
        return $this->roles()->where('name', $roleName)->exists();
    }

    public function hasPermission(string $permission): bool
    {
        // preload roles to avoid repeated queries
        $this->loadMissing('roles');

        // 1️⃣ Super Admin shortcut
        if ($this->roles->contains('name', 'Super Admin')) {
            return true;
        }

        // 2️⃣ User-level override
        $direct = $this->directPermissions()
            ->where('permissions.name', $permission)
            ->first();

        if ($direct) {
            return (bool) $direct->pivot->allowed;
        }

        // 3️⃣ Role permissions
        return Permission::where('name', $permission)
            ->whereHas('roles', function ($q) {
                $q->whereIn('roles.id', $this->roles->pluck('id'));
            })
            ->exists();
    }
}
