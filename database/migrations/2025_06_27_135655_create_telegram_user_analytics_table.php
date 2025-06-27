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
        Schema::create('telegram_user_analytics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('telegram_user_id')->constrained()->onDelete('cascade');
            $table->string('event_type', 50);
            $table->json('event_data')->nullable();
            $table->string('session_id')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at');
            
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['telegram_user_id']);
            $table->index(['event_type']);
            $table->index(['occurred_at']);
            $table->index(['session_id']);
            $table->index(['telegram_user_id', 'event_type']);
            $table->index(['telegram_user_id', 'occurred_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('telegram_user_analytics');
    }
};
