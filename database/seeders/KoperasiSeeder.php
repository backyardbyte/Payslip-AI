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
                'max_peratus_gaji_bersih' => 25, // Very strict - only eligible if percentage <= 25%
                'min_gaji_pokok' => 2500,
                'max_umur' => 55,
                'min_tenure_months' => 12,
            ],
            'is_active' => true,
            'created_at' => now()->subMonths(6),
            'updated_at' => now()->subDays(5),
        ]);

        Koperasi::create([
            'name' => 'Koperasi Maju Jaya',
            'rules' => [
                'max_peratus_gaji_bersih' => 50, // Moderate - eligible if percentage <= 50%
                'min_gaji_pokok' => 2000,
                'max_loan_amount' => 150000,
                'blacklist_check' => false,
            ],
            'is_active' => true,
            'created_at' => now()->subMonths(4),
            'updated_at' => now()->subDays(2),
        ]);

        Koperasi::create([
            'name' => 'Koperasi Harapan Bangsa',
            'rules' => [
                'max_peratus_gaji_bersih' => 30, // Strict - eligible if percentage <= 30%
                'min_gaji_pokok' => 3000,
                'max_umur' => 58,
                'min_working_years' => 2,
            ],
            'is_active' => false,
            'created_at' => now()->subMonths(8),
            'updated_at' => now()->subWeeks(3),
        ]);

        Koperasi::create([
            'name' => 'Koperasi Cergas Malaysia',
            'rules' => [
                'max_peratus_gaji_bersih' => 70, // Lenient - eligible if percentage <= 70%
                'min_gaji_pokok' => 2800,
                'max_debt_service_ratio' => 60,
                'require_guarantor' => true,
            ],
            'is_active' => true,
            'created_at' => now()->subMonths(3),
            'updated_at' => now()->subDays(1),
        ]);

        Koperasi::create([
            'name' => 'Koperasi Pekerja Kerajaan',
            'rules' => [
                'max_peratus_gaji_bersih' => 40, // Moderate-strict - eligible if percentage <= 40%
                'min_gaji_pokok' => 1800,
                'max_umur' => 60,
                'employment_type' => 'government',
                'max_loan_multiplier' => 12,
            ],
            'is_active' => true,
            'created_at' => now()->subMonths(2),
            'updated_at' => now(),
        ]);

        Koperasi::create([
            'name' => 'Koperasi Kredit Bersama',
            'rules' => [
                'max_peratus_gaji_bersih' => 15, // Very strict - eligible if percentage <= 15%
                'min_gaji_pokok' => 3500,
                'max_umur' => 50,
                'credit_score_min' => 650,
            ],
            'is_active' => false,
            'created_at' => now()->subYear(),
            'updated_at' => now()->subMonths(2),
        ]);
    }
}
