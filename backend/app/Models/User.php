<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'address',
        'city',
        'state',
        'postal_code',
        'country',
        'birth_date',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'birth_date' => 'date',
        ];
    }

    /**
     * Get the roles that belong to the user
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles');
    }

    /**
     * Get the permissions that belong to the user directly
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'user_permissions');
    }

    /**
     * Get the orders for the user
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Check if user has role
     */
    public function hasRole(string $role): bool
    {
        return $this->roles()->where('slug', $role)->exists();
    }

    /**
     * Check if user has permission (either directly or through roles)
     */
    public function hasPermission(string $permission): bool
    {
        // Check direct permissions
        if ($this->permissions()->where('slug', $permission)->exists()) {
            return true;
        }

        // Check permissions through roles
        return $this->roles()->whereHas('permissions', function ($query) use ($permission) {
            $query->where('slug', $permission);
        })->exists();
    }

    /**
     * Assign role to user
     */
    public function assignRole(Role $role): void
    {
        $this->roles()->syncWithoutDetaching([$role->id]);
    }

    /**
     * Remove role from user
     */
    public function removeRole(Role $role): void
    {
        $this->roles()->detach($role->id);
    }

    /**
     * Give permission to user
     */
    public function givePermission(Permission $permission): void
    {
        $this->permissions()->syncWithoutDetaching([$permission->id]);
    }

    /**
     * Revoke permission from user
     */
    public function revokePermission(Permission $permission): void
    {
        $this->permissions()->detach($permission->id);
    }

    /**
     * Check if user is admin
     */
    public function isAdmin(): bool
    {
        return $this->hasRole('admin') || $this->hasRole('super-admin');
    }

    /**
     * Check if user is customer
     */
    public function isCustomer(): bool
    {
        return $this->hasRole('customer');
    }

    /**
     * Scope for active users
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for admin users
     */
    public function scopeAdmins($query)
    {
        return $query->whereHas('roles', function ($q) {
            $q->whereIn('slug', ['admin', 'super-admin']);
        });
    }

    /**
     * Scope for customer users
     */
    public function scopeCustomers($query)
    {
        return $query->whereHas('roles', function ($q) {
            $q->where('slug', 'customer');
        });
    }
}