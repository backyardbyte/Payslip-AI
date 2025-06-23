<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class SystemCredentialsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get roles
        $superAdminRole = Role::where('name', 'super_admin')->first();
        $adminRole = Role::where('name', 'admin')->first();
        $managerRole = Role::where('name', 'manager')->first();
        $operatorRole = Role::where('name', 'operator')->first();
        $userRole = Role::where('name', 'user')->first();

        // System Credentials
        $systemUsers = [
            [
                'name' => 'System Administrator',
                'email' => 'admin@test.com',
                'password' => Hash::make('Password123'),
                'role_id' => $superAdminRole?->id,
                'is_active' => true,
                'email_verified_at' => now(),
            ],
            [
                'name' => 'System Manager',
                'email' => 'manager@test.com',
                'password' => Hash::make('Password123'),
                'role_id' => $adminRole?->id,
                'is_active' => true,
                'email_verified_at' => now(),
            ],
            [
                'name' => 'Operations Manager',
                'email' => 'operations@test.com',
                'password' => Hash::make('Password123'),
                'role_id' => $managerRole?->id,
                'is_active' => true,
                'email_verified_at' => now(),
            ],
            [
                'name' => 'System Operator',
                'email' => 'operator@test.com',
                'password' => Hash::make('Password123'),
                'role_id' => $operatorRole?->id,
                'is_active' => true,
                'email_verified_at' => now(),
            ],
            [
                'name' => 'Demo User',
                'email' => 'demo@test.com',
                'password' => Hash::make('Password123'),
                'role_id' => $userRole?->id,
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        ];

        // Update existing test user if exists
        $existingTestUser = User::where('email', 'test@example.com')->first();
        if ($existingTestUser) {
            $existingTestUser->update([
                'role_id' => $userRole?->id,
                'is_active' => true,
                'password' => Hash::make('password'), // Keep original password
            ]);
            $this->command->info('Updated existing test user with role assignment.');
        }

        // Create system users
        foreach ($systemUsers as $userData) {
            $existingUser = User::where('email', $userData['email'])->first();
            
            if (!$existingUser) {
                User::create($userData);
                $this->command->info("Created system user: {$userData['email']}");
            } else {
                $existingUser->update($userData);
                $this->command->info("Updated existing system user: {$userData['email']}");
            }
        }

        // Display credentials information
        $this->command->info('');
        $this->command->info('=== SYSTEM CREDENTIALS ===');
        $this->command->info('Super Admin: admin@test.com / Password123');
        $this->command->info('Admin: manager@test.com / Password123');
        $this->command->info('Manager: operations@test.com / Password123');
        $this->command->info('Operator: operator@test.com / Password123');
        $this->command->info('Demo User: demo@test.com / Password123');
        $this->command->info('Test User: test@example.com / password');
        $this->command->info('');
        $this->command->warn('⚠️  IMPORTANT: Change these default passwords in production!');
        $this->command->info('');
    }
} 