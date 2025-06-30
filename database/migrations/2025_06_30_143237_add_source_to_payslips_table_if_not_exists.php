<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Check if the source column doesn't exist before adding it
        if (!Schema::hasColumn('payslips', 'source')) {
            Schema::table('payslips', function (Blueprint $table) {
                $table->string('source', 20)->default('web')->after('status');
                $table->index('source');
            });
        }
        
        // Update existing records to have proper source values
        DB::table('payslips')
            ->whereNull('source')
            ->orWhere('source', '')
            ->update(['source' => 'web']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payslips', function (Blueprint $table) {
            $table->dropIndex(['source']);
            $table->dropColumn('source');
        });
    }
};
