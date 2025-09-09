<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permission extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'group',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean'
    ];

    /**
     * Get the roles that belong to the permission
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_permissions');
    }

    /**
     * Get the users that have this permission directly
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_permissions');
    }

    /**
     * Scope for active permissions
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for permissions by group
     */
    public function scopeByGroup($query, string $group)
    {
        return $query->where('group', $group);
    }

    /**
     * Get permissions grouped by category
     */
    public static function getGrouped()
    {
        return static::active()
            ->orderBy('group')
            ->orderBy('name')
            ->get()
            ->groupBy('group');
    }

    /**
     * Common permission groups
     */
    public static function getGroups(): array
    {
        return [
            'users' => 'Gestión de Usuarios',
            'products' => 'Gestión de Productos',
            'categories' => 'Gestión de Categorías',
            'orders' => 'Gestión de Pedidos',
            'payments' => 'Gestión de Pagos',
            'shipping' => 'Gestión de Envíos',
            'reports' => 'Reportes y Análisis',
            'settings' => 'Configuraciones',
            'admin' => 'Administración'
        ];
    }
}