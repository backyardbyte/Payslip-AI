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
        Schema::create('telegram_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            $table->bigInteger('telegram_id')->unique();
            $table->string('username')->nullable();
            $table->string('first_name');
            $table->string('last_name')->nullable();
            $table->string('language_code', 10)->nullable();
            $table->boolean('is_premium')->default(false);
            $table->boolean('is_verified')->default(false);
            $table->boolean('is_bot')->default(false);
            
            // User preferences
            $table->string('language', 10)->default('ms');
            $table->string('timezone', 50)->default('Asia/Kuala_Lumpur');
            $table->boolean('notifications_enabled')->default(true);
            $table->boolean('auto_delete_files')->default(false);
            $table->json('preferred_koperasi')->nullable();
            
            // Conversation state
            $table->string('conversation_state')->default('none');
            $table->json('conversation_data')->nullable();
            
            // Activity tracking
            $table->timestamp('last_activity_at')->nullable();
            $table->integer('total_payslips_processed')->default(0);
            $table->integer('total_commands_used')->default(0);
            
            // Settings and permissions
            $table->json('settings')->nullable();
            $table->boolean('is_admin')->default(false);
            $table->boolean('is_active')->default(true);
            
            $table->timestamps();
            
            // Indexes
            $table->index(['telegram_id']);
            $table->index(['user_id']);
            $table->index(['language']);
            $table->index(['is_active']);
            $table->index(['is_admin']);
            $table->index(['last_activity_at']);
            $table->index(['notifications_enabled']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('telegram_users');
    }
};
