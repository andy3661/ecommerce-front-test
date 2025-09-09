<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;
use App\Models\Permission;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create permissions
        $permissions = [
            // Users management
            ['name' => 'Ver Usuarios', 'slug' => 'users.view', 'group' => 'users'],
            ['name' => 'Crear Usuarios', 'slug' => 'users.create', 'group' => 'users'],
            ['name' => 'Editar Usuarios', 'slug' => 'users.edit', 'group' => 'users'],
            ['name' => 'Eliminar Usuarios', 'slug' => 'users.delete', 'group' => 'users'],
            ['name' => 'Exportar Usuarios', 'slug' => 'users.export', 'group' => 'users'],
            
            // Products management
            ['name' => 'Ver Productos', 'slug' => 'products.view', 'group' => 'products'],
            ['name' => 'Crear Productos', 'slug' => 'products.create', 'group' => 'products'],
            ['name' => 'Editar Productos', 'slug' => 'products.edit', 'group' => 'products'],
            ['name' => 'Eliminar Productos', 'slug' => 'products.delete', 'group' => 'products'],
            ['name' => 'Importar Productos', 'slug' => 'products.import', 'group' => 'products'],
            ['name' => 'Exportar Productos', 'slug' => 'products.export', 'group' => 'products'],
            
            // Categories management
            ['name' => 'Ver Categorías', 'slug' => 'categories.view', 'group' => 'categories'],
            ['name' => 'Crear Categorías', 'slug' => 'categories.create', 'group' => 'categories'],
            ['name' => 'Editar Categorías', 'slug' => 'categories.edit', 'group' => 'categories'],
            ['name' => 'Eliminar Categorías', 'slug' => 'categories.delete', 'group' => 'categories'],
            
            // Orders management
            ['name' => 'Ver Pedidos', 'slug' => 'orders.view', 'group' => 'orders'],
            ['name' => 'Crear Pedidos', 'slug' => 'orders.create', 'group' => 'orders'],
            ['name' => 'Editar Pedidos', 'slug' => 'orders.edit', 'group' => 'orders'],
            ['name' => 'Eliminar Pedidos', 'slug' => 'orders.delete', 'group' => 'orders'],
            ['name' => 'Cambiar Estado Pedidos', 'slug' => 'orders.status', 'group' => 'orders'],
            ['name' => 'Exportar Pedidos', 'slug' => 'orders.export', 'group' => 'orders'],
            
            // Payments management
            ['name' => 'Ver Pagos', 'slug' => 'payments.view', 'group' => 'payments'],
            ['name' => 'Procesar Reembolsos', 'slug' => 'payments.refund', 'group' => 'payments'],
            ['name' => 'Ver Transacciones', 'slug' => 'payments.transactions', 'group' => 'payments'],
            
            // Shipping management
            ['name' => 'Ver Envíos', 'slug' => 'shipping.view', 'group' => 'shipping'],
            ['name' => 'Crear Etiquetas', 'slug' => 'shipping.labels', 'group' => 'shipping'],
            ['name' => 'Rastrear Envíos', 'slug' => 'shipping.track', 'group' => 'shipping'],
            
            // Reports
            ['name' => 'Ver Reportes', 'slug' => 'reports.view', 'group' => 'reports'],
            ['name' => 'Exportar Reportes', 'slug' => 'reports.export', 'group' => 'reports'],
            ['name' => 'Análisis Avanzado', 'slug' => 'reports.analytics', 'group' => 'reports'],
            
            // Settings
            ['name' => 'Ver Configuraciones', 'slug' => 'settings.view', 'group' => 'settings'],
            ['name' => 'Editar Configuraciones', 'slug' => 'settings.edit', 'group' => 'settings'],
            
            // Admin
            ['name' => 'Acceso Panel Admin', 'slug' => 'admin.access', 'group' => 'admin'],
            ['name' => 'Gestionar Roles', 'slug' => 'admin.roles', 'group' => 'admin'],
            ['name' => 'Gestionar Permisos', 'slug' => 'admin.permissions', 'group' => 'admin'],
            ['name' => 'Ver Sistema', 'slug' => 'admin.system', 'group' => 'admin'],
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(
                ['slug' => $permission['slug']],
                $permission
            );
        }

        // Create roles
        $roles = [
            [
                'name' => 'Super Administrador',
                'slug' => 'super-admin',
                'description' => 'Acceso completo al sistema'
            ],
            [
                'name' => 'Administrador',
                'slug' => 'admin',
                'description' => 'Acceso administrativo general'
            ],
            [
                'name' => 'Gerente',
                'slug' => 'manager',
                'description' => 'Gestión de productos y pedidos'
            ],
            [
                'name' => 'Vendedor',
                'slug' => 'seller',
                'description' => 'Gestión básica de productos y pedidos'
            ],
            [
                'name' => 'Cliente',
                'slug' => 'customer',
                'description' => 'Usuario cliente del ecommerce'
            ]
        ];

        foreach ($roles as $roleData) {
            $role = Role::firstOrCreate(
                ['slug' => $roleData['slug']],
                $roleData
            );

            // Assign permissions to roles
            if ($roleData['slug'] === 'super-admin') {
                // Super admin gets all permissions
                $role->permissions()->sync(Permission::all()->pluck('id'));
            } elseif ($roleData['slug'] === 'admin') {
                // Admin gets most permissions except system management
                $adminPermissions = Permission::whereNotIn('slug', [
                    'admin.roles',
                    'admin.permissions',
                    'admin.system'
                ])->pluck('id');
                $role->permissions()->sync($adminPermissions);
            } elseif ($roleData['slug'] === 'manager') {
                // Manager gets product, order, and report permissions
                $managerPermissions = Permission::whereIn('group', [
                    'products',
                    'categories',
                    'orders',
                    'shipping',
                    'reports'
                ])->pluck('id');
                $role->permissions()->sync($managerPermissions);
            } elseif ($roleData['slug'] === 'seller') {
                // Seller gets basic product and order view permissions
                $sellerPermissions = Permission::whereIn('slug', [
                    'admin.access',
                    'products.view',
                    'products.edit',
                    'categories.view',
                    'orders.view',
                    'orders.status'
                ])->pluck('id');
                $role->permissions()->sync($sellerPermissions);
            }
            // Customer role gets no admin permissions
        }

        $this->command->info('Roles and permissions seeded successfully!');
    }
}