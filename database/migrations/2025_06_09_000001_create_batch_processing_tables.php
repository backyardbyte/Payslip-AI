<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Create batch_operations table
        Schema::create('batch_operations', function (Blueprint $table) {
            $table->id();
            $table->string('batch_id')->unique();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->enum('status', ['pending', 'processing', 'completed', 'failed', 'cancelled'])->default('pending');
            $table->integer('total_files')->default(0);
            $table->integer('processed_files')->default(0);
            $table->integer('successful_files')->default(0);
            $table->integer('failed_files')->default(0);
            $table->json('settings')->nullable(); // Processing settings (parallel/sequential, priority, etc.)
            $table->json('metadata')->nullable(); // Additional metadata
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            
            $table->index(['user_id', 'status']);
            $table->index(['status', 'created_at']);
        });

        // Create batch_schedules table for scheduled batch processing
        Schema::create('batch_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('cron_expression');
            $table->json('settings'); // Batch processing settings
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->timestamps();
            
            $table->index(['is_active', 'next_run_at']);
        });

        // Add batch-related columns to payslips table
        Schema::table('payslips', function (Blueprint $table) {
            $table->string('batch_id')->nullable()->after('user_id');
            $table->integer('processing_priority')->default(0)->after('status');
            $table->timestamp('processing_started_at')->nullable()->after('processing_priority');
            $table->timestamp('processing_completed_at')->nullable()->after('processing_started_at');
            $table->text('processing_error')->nullable()->after('processing_completed_at');
            
            $table->index(['batch_id']);
            $table->index(['processing_priority', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payslips', function (Blueprint $table) {
            $table->dropIndex(['batch_id']);
            $table->dropIndex(['processing_priority', 'created_at']);
            $table->dropColumn([
                'batch_id',
                'processing_priority',
                'processing_started_at',
                'processing_completed_at',
                'processing_error'
            ]);
        });
        
        Schema::dropIfExists('batch_schedules');
        Schema::dropIfExists('batch_operations');
    }
}; 