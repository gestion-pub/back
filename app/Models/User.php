<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
    ];

    /**
     * The attributes that should be appended to the model's array form.
     *
     * @var array
     */
    protected $appends = ['permissions'];

    public function getPermissionsAttribute()
    {
        // Fallback for legacy 'admin' role
        $legacyRole = strtolower($this->role);
        if ($legacyRole === 'admin' || $legacyRole === 'administrateur') {
            return \App\Models\Permission::pluck('slug')->toArray();
        }

        return $this->roles()->with('permissions')->get()
            ->pluck('permissions')->flatten()
            ->pluck('slug')->unique()->values()->toArray();
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public function clients()
    {
        return $this->hasMany(Client::class, 'create_by');
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'role_user', 'user_id', 'role_id');
    }

    public function hasPermission($permissionSlug)
    {
        // Fallback for legacy 'role' column 
        // We check for both 'admin' and 'administrateur' to be safe
        $legacyRole = strtolower($this->role);
        if ($legacyRole === 'admin' || $legacyRole === 'administrateur') {
            return true;
        }

        return $this->roles()->whereHas('permissions', function ($query) use ($permissionSlug) {
            $query->where('slug', $permissionSlug);
        })->exists();
    }
}
