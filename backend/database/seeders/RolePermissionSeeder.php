<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions
        $permissions = [
            // User management
            'view users',
            'create users',
            'edit users',
            'delete users',
            
            // Product management
            'view products',
            'create products',
            'edit products',
            'delete products',
            'manage inventory',
            
            // Category management
            'view categories',
            'create categories',
            'edit categories',
            'delete categories',
            
            // Order management
            'view orders',
            'create orders',
            'edit orders',
            'delete orders',
            'process orders',
            'cancel orders',
            
            // Coupon management
            'view coupons',
            'create coupons',
            'edit coupons',
            'delete coupons',
            
            // Review management
            'view reviews',
            'moderate reviews',
            'delete reviews',
            
            // Settings management
            'view settings',
            'edit settings',
            
            // Reports
            'view reports',
            'export reports',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Create roles and assign permissions
        
        // Super Admin role - has all permissions
        $superAdminRole = Role::firstOrCreate(['name' => 'super-admin']);
        $superAdminRole->syncPermissions(Permission::all());
        
        // Admin role - has most permissions except user management
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $adminRole->syncPermissions([
            'view products', 'create products', 'edit products', 'delete products', 'manage inventory',
            'view categories', 'create categories', 'edit categories', 'delete categories',
            'view orders', 'create orders', 'edit orders', 'process orders', 'cancel orders',
            'view coupons', 'create coupons', 'edit coupons', 'delete coupons',
            'view reviews', 'moderate reviews', 'delete reviews',
            'view settings', 'edit settings',
            'view reports', 'export reports',
        ]);
        
        // Manager role - can manage products, orders, and view reports
        $managerRole = Role::firstOrCreate(['name' => 'manager']);
        $managerRole->syncPermissions([
            'view products', 'create products', 'edit products', 'manage inventory',
            'view categories', 'create categories', 'edit categories',
            'view orders', 'edit orders', 'process orders',
            'view coupons', 'create coupons', 'edit coupons',
            'view reviews', 'moderate reviews',
            'view reports',
        ]);
        
        // Customer role - basic customer permissions
        $customerRole = Role::firstOrCreate(['name' => 'customer']);
        $customerRole->syncPermissions([
            'view products',
            'view categories',
            'create orders',
            'view orders', // only their own orders
        ]);
        
        // Create default super admin user
        $superAdmin = User::firstOrCreate(
            ['email' => 'admin@ecommerce.com'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'is_active' => true,
            ]
        );
        
        if (!$superAdmin->hasRole('super-admin')) {
            $superAdmin->assignRole('super-admin');
        }
        
        // Create default admin user
        $admin = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'is_active' => true,
            ]
        );
        
        if (!$admin->hasRole('admin')) {
            $admin->assignRole('admin');
        }
    }
}