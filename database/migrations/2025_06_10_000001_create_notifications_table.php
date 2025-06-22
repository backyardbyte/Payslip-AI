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
        // Laravel's default notifications table (if not already created)
        if (!Schema::hasTable('notifications')) {
            Schema::create('notifications', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('type');
                $table->morphs('notifiable');
                $table->text('data');
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
            });
        }

        // Create notification_preferences table
        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('event_type'); // e.g., 'batch_completed', 'payslip_processed'
            $table->string('channel'); // e.g., 'in_app', 'email', 'sms'
            $table->boolean('enabled')->default(true);
            $table->json('settings')->nullable(); // Channel-specific settings
            $table->timestamps();
            
            $table->unique(['user_id', 'event_type', 'channel']);
            $table->index(['user_id', 'enabled']);
        });

        // Create notification_templates table
        Schema::create('notification_templates', function (Blueprint $table) {
            $table->id();
            $table->string('event_type'); // e.g., 'batch_completed'
            $table->string('channel'); // e.g., 'email', 'in_app'
            $table->string('subject')->nullable(); // For email notifications
            $table->text('template'); // Template with placeholders
            $table->json('variables')->nullable(); // Available variables for this template
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->unique(['event_type', 'channel']);
            $table->index(['event_type', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_templates');
        Schema::dropIfExists('notification_preferences');
        
        // Only drop notifications table if it was created by this migration
        // Laravel might have created it elsewhere
        if (Schema::hasTable('notifications')) {
            Schema::dropIfExists('notifications');
        }
    }
}; 