<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        
        // Clear existing data
        DB::table('user_permissions')->truncate();
        DB::table('role_permissions')->truncate();
        Permission::truncate();
        Role::truncate();
        
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // Create Permissions
        $permissions = [
            // Payslip Management
            ['name' => 'payslip.view', 'display_name' => 'View Payslips', 'description' => 'View payslip records', 'category' => 'payslip'],
            ['name' => 'payslip.create', 'display_name' => 'Upload Payslips', 'description' => 'Upload new payslip files', 'category' => 'payslip'],
            ['name' => 'payslip.update', 'display_name' => 'Update Payslips', 'description' => 'Edit payslip information', 'category' => 'payslip'],
            ['name' => 'payslip.delete', 'display_name' => 'Delete Payslips', 'description' => 'Delete payslip records', 'category' => 'payslip'],
            ['name' => 'payslip.view_all', 'display_name' => 'View All Payslips', 'description' => 'View payslips from all users', 'category' => 'payslip'],
            ['name' => 'payslip.process', 'display_name' => 'Process Payslips', 'description' => 'Manually trigger payslip processing', 'category' => 'payslip'],
            
            // Koperasi Management
            ['name' => 'koperasi.view', 'display_name' => 'View Koperasi', 'description' => 'View koperasi information', 'category' => 'koperasi'],
            ['name' => 'koperasi.create', 'display_name' => 'Create Koperasi', 'description' => 'Create new koperasi entries', 'category' => 'koperasi'],
            ['name' => 'koperasi.update', 'display_name' => 'Update Koperasi', 'description' => 'Edit koperasi information', 'category' => 'koperasi'],
            ['name' => 'koperasi.delete', 'display_name' => 'Delete Koperasi', 'description' => 'Delete koperasi entries', 'category' => 'koperasi'],
            ['name' => 'koperasi.manage_rules', 'display_name' => 'Manage Koperasi Rules', 'description' => 'Modify koperasi eligibility rules', 'category' => 'koperasi'],
            
            // User Management
            ['name' => 'user.view', 'display_name' => 'View Users', 'description' => 'View user accounts', 'category' => 'user'],
            ['name' => 'user.create', 'display_name' => 'Create Users', 'description' => 'Create new user accounts', 'category' => 'user'],
            ['name' => 'user.update', 'display_name' => 'Update Users', 'description' => 'Edit user information', 'category' => 'user'],
            ['name' => 'user.delete', 'display_name' => 'Delete Users', 'description' => 'Delete user accounts', 'category' => 'user'],
            ['name' => 'user.activate', 'display_name' => 'Activate/Deactivate Users', 'description' => 'Enable or disable user accounts', 'category' => 'user'],
            ['name' => 'user.assign_roles', 'display_name' => 'Assign User Roles', 'description' => 'Assign roles to users', 'category' => 'user'],
            
            // Role & Permission Management
            ['name' => 'role.view', 'display_name' => 'View Roles', 'description' => 'View system roles', 'category' => 'role'],
            ['name' => 'role.create', 'display_name' => 'Create Roles', 'description' => 'Create new roles', 'category' => 'role'],
            ['name' => 'role.update', 'display_name' => 'Update Roles', 'description' => 'Edit role information', 'category' => 'role'],
            ['name' => 'role.delete', 'display_name' => 'Delete Roles', 'description' => 'Delete roles', 'category' => 'role'],
            ['name' => 'permission.view', 'display_name' => 'View Permissions', 'description' => 'View system permissions', 'category' => 'role'],
            ['name' => 'permission.assign', 'display_name' => 'Assign Permissions', 'description' => 'Assign permissions to roles', 'category' => 'role'],
            
            // System Management
            ['name' => 'system.view_health', 'display_name' => 'View System Health', 'description' => 'View system health information', 'category' => 'system'],
            ['name' => 'system.view_statistics', 'display_name' => 'View Statistics', 'description' => 'View system statistics', 'category' => 'system'],
            ['name' => 'system.clear_cache', 'display_name' => 'Clear Cache', 'description' => 'Clear system cache', 'category' => 'system'],
            ['name' => 'system.optimize_database', 'display_name' => 'Optimize Database', 'description' => 'Run database optimization', 'category' => 'system'],
            ['name' => 'system.cleanup', 'display_name' => 'System Cleanup', 'description' => 'Run system cleanup operations', 'category' => 'system'],
            ['name' => 'system.clear_logs', 'display_name' => 'Clear Logs', 'description' => 'Clear system logs', 'category' => 'system'],
            ['name' => 'system.manage_settings', 'display_name' => 'Manage Settings', 'description' => 'Manage system settings', 'category' => 'system'],
            
            // Analytics & Reports
            ['name' => 'analytics.view', 'display_name' => 'View Analytics', 'description' => 'View analytics dashboard', 'category' => 'analytics'],
            ['name' => 'analytics.export', 'display_name' => 'Export Analytics', 'description' => 'Export analytics data', 'category' => 'analytics'],
            ['name' => 'report.generate', 'display_name' => 'Generate Reports', 'description' => 'Generate system reports', 'category' => 'analytics'],
            
            // Queue Management
            ['name' => 'queue.view', 'display_name' => 'View Queue', 'description' => 'View processing queue', 'category' => 'queue'],
            ['name' => 'queue.manage', 'display_name' => 'Manage Queue', 'description' => 'Manage processing queue', 'category' => 'queue'],
            ['name' => 'queue.clear', 'display_name' => 'Clear Queue', 'description' => 'Clear processing queue', 'category' => 'queue'],
        ];

        foreach ($permissions as $permission) {
            Permission::create($permission);
        }

        // Create Roles
        $roles = [
            [
                'name' => 'super_admin',
                'display_name' => 'Super Administrator',
                'description' => 'Full system access with all permissions',
                'permissions' => Permission::all()->pluck('name')->toArray()
            ],
            [
                'name' => 'admin',
                'display_name' => 'Administrator',
                'description' => 'System administrator with most permissions',
                'permissions' => [
                    'payslip.view', 'payslip.create', 'payslip.update', 'payslip.delete', 'payslip.view_all', 'payslip.process',
                    'koperasi.view', 'koperasi.create', 'koperasi.update', 'koperasi.delete', 'koperasi.manage_rules',
                    'user.view', 'user.create', 'user.update', 'user.activate', 'user.assign_roles',
                    'system.view_health', 'system.view_statistics', 'system.clear_cache', 'system.cleanup', 'system.clear_logs',
                    'analytics.view', 'analytics.export', 'report.generate',
                    'queue.view', 'queue.manage', 'queue.clear'
                ]
            ],
            [
                'name' => 'manager',
                'display_name' => 'Manager',
                'description' => 'Manager with oversight permissions',
                'permissions' => [
                    'payslip.view', 'payslip.view_all', 'payslip.process',
                    'koperasi.view',
                    'user.view',
                    'system.view_health', 'system.view_statistics',
                    'analytics.view', 'report.generate',
                    'queue.view'
                ]
            ],
            [
                'name' => 'operator',
                'display_name' => 'Operator',
                'description' => 'System operator with processing permissions',
                'permissions' => [
                    'payslip.view', 'payslip.create', 'payslip.update', 'payslip.process',
                    'koperasi.view',
                    'system.view_health',
                    'queue.view', 'queue.manage'
                ]
            ],
            [
                'name' => 'user',
                'display_name' => 'Regular User',
                'description' => 'Standard user with basic permissions',
                'permissions' => [
                    'payslip.view', 'payslip.create', 'payslip.update', 'payslip.delete',
                    'koperasi.view'
                ]
            ]
        ];

        foreach ($roles as $roleData) {
            $permissions = $roleData['permissions'];
            unset($roleData['permissions']);
            
            $role = Role::create($roleData);
            
            // Assign permissions to role
            $permissionIds = Permission::whereIn('name', $permissions)->pluck('id')->toArray();
            $role->permissions()->sync($permissionIds);
        }

        $this->command->info('Roles and permissions seeded successfully!');
    }
} 