<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Koperasi;

class KoperasiSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        Koperasi::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        Koperasi::create([
            'name' => 'Koperasi Sejahtera Berhad',
            'rules' => [
                'min_peratus_gaji_bersih' => 20, // Need at least 70% take-home
            ],
            'is_active' => true,
            'created_at' => now()->subMonths(6),
            'updated_at' => now()->subDays(5),
        ]);

        Koperasi::create([
            'name' => 'Koperasi Maju Jaya',
            'rules' => [
                'min_peratus_gaji_bersih' => 30, // Need at least 60% take-home
            ],
            'is_active' => true,
            'created_at' => now()->subMonths(4),
            'updated_at' => now()->subDays(2),
        ]);

        Koperasi::create([
            'name' => 'Koperasi Harapan Bangsa',
            'rules' => [
                'min_peratus_gaji_bersih' => 25, // Need at least 65% take-home
            ],
            'is_active' => false,
            'created_at' => now()->subMonths(8),
            'updated_at' => now()->subWeeks(3),
        ]);

        Koperasi::create([
            'name' => 'Koperasi Cergas Malaysia',
            'rules' => [
                'min_peratus_gaji_bersih' => 40, // Need at least 50% take-home
            ],
            'is_active' => true,
            'created_at' => now()->subMonths(3),
            'updated_at' => now()->subDays(1),
        ]);

        Koperasi::create([
            'name' => 'Koperasi Pekerja Kerajaan',
            'rules' => [
                'min_peratus_gaji_bersih' => 15, // Need at least 75% take-home - Conservative for government workers
            ],
            'is_active' => true,
            'created_at' => now()->subMonths(2),
            'updated_at' => now(),
        ]);

        Koperasi::create([
            'name' => 'Koperasi Kredit Bersama',
            'rules' => [
                'min_peratus_gaji_bersih' => 10, // Need at least 80% take-home - Very conservative
            ],
            'is_active' => false,
            'created_at' => now()->subYear(),
            'updated_at' => now()->subMonths(2),
        ]);

        // Add more realistic Malaysian koperasi examples
        Koperasi::create([
            'name' => 'Koperasi Guru Malaysia',
            'rules' => [
                'min_peratus_gaji_bersih' => 12, // Teachers typically have stable income
            ],
            'is_active' => true,
            'created_at' => now()->subMonths(5),
            'updated_at' => now()->subDays(10),
        ]);

        Koperasi::create([
            'name' => 'Koperasi Polis Malaysia',
            'rules' => [
                'min_peratus_gaji_bersih' => 25, // Police officers have good job security
            ],
            'is_active' => true,
            'created_at' => now()->subMonths(7),
            'updated_at' => now()->subDays(3),
        ]);

        Koperasi::create([
            'name' => 'Koperasi Tenaga Nasional',
            'rules' => [
                'min_peratus_gaji_bersih' => 20, // Utilities workers
            ],
            'is_active' => true,
            'created_at' => now()->subMonths(3),
            'updated_at' => now()->subDays(7),
        ]);

        Koperasi::create([
            'name' => 'Koperasi Bank Islam Malaysia',
            'rules' => [
                'min_peratus_gaji_bersih' => 15, // Banking sector - conservative
            ],
            'is_active' => true,
            'created_at' => now()->subMonths(4),
            'updated_at' => now()->subDays(1),
        ]);

        Koperasi::create([
            'name' => 'Koperasi Kesihatan Malaysia',
            'rules' => [
                'min_peratus_gaji_bersih' => 28, // Healthcare workers
            ],
            'is_active' => true,
            'created_at' => now()->subMonths(6),
            'updated_at' => now()->subDays(5),
        ]);

        Koperasi::create([
            'name' => 'Koperasi Petronas Berhad',
            'rules' => [
                'min_peratus_gaji_bersih' => 15, // Oil & gas sector - higher income
            ],
            'is_active' => true,
            'created_at' => now()->subMonths(8),
            'updated_at' => now()->subDays(2),
        ]);

        Koperasi::create([
            'name' => 'Koperasi Universiti Malaysia',
            'rules' => [
                'min_peratus_gaji_bersih' => 22, // University staff
            ],
            'is_active' => true,
            'created_at' => now()->subMonths(2),
            'updated_at' => now()->subDays(8),
        ]);

        Koperasi::create([
            'name' => 'Koperasi Pos Malaysia',
            'rules' => [
                'min_peratus_gaji_bersih' => 32, // Postal service workers
            ],
            'is_active' => true,
            'created_at' => now()->subMonths(9),
            'updated_at' => now()->subDays(4),
        ]);

        Koperasi::create([
            'name' => 'Koperasi Telekom Malaysia',
            'rules' => [
                'min_peratus_gaji_bersih' => 18, // Telecommunications sector
            ],
            'is_active' => true,
            'created_at' => now()->subMonths(5),
            'updated_at' => now()->subDays(6),
        ]);

        Koperasi::create([
            'name' => 'Koperasi Angkatan Tentera Malaysia',
            'rules' => [
                'min_peratus_gaji_bersih' => 20, // Military personnel
            ],
            'is_active' => true,
            'created_at' => now()->subMonths(10),
            'updated_at' => now()->subDays(12),
        ]);

        // Inactive koperasi for testing
        Koperasi::create([
            'name' => 'Koperasi Pembangunan Lama',
            'rules' => [
                'min_peratus_gaji_bersih' => 15, // Very strict requirements
            ],
            'is_active' => false,
            'created_at' => now()->subYears(2),
            'updated_at' => now()->subMonths(6),
        ]);

        // Add some with different percentage requirements for variety
        Koperasi::create([
            'name' => 'Koperasi Rakyat Malaysia',
            'rules' => [
                'min_peratus_gaji_bersih' => 25, // Most lenient - for lower income groups
            ],
            'is_active' => true,
            'created_at' => now()->subMonths(11),
            'updated_at' => now()->subDays(9),
        ]);

        Koperasi::create([
            'name' => 'Koperasi Swasta Berhad',
            'rules' => [
                'min_peratus_gaji_bersih' => 18, // Private sector - higher requirements
            ],
            'is_active' => true,
            'created_at' => now()->subMonths(1),
            'updated_at' => now()->subDays(14),
        ]);

        Koperasi::create([
            'name' => 'Koperasi Mudah Lulus',
            'rules' => [
                'min_peratus_gaji_bersih' => 11, // Easiest to qualify
            ],
            'is_active' => true,
            'created_at' => now()->subMonths(12),
            'updated_at' => now()->subDays(11),
        ]);
    }
}
