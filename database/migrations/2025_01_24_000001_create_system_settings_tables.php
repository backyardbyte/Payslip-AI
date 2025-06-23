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
        // Settings categories table
        Schema::create('setting_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('display_name', 200);
            $table->text('description')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index(['is_active', 'sort_order']);
        });

        // Setting definitions table
        Schema::create('setting_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->string('display_name', 200);
            $table->text('description')->nullable();
            $table->string('category', 100);
            $table->enum('type', ['string', 'integer', 'boolean', 'float', 'json', 'text', 'email', 'url', 'select']);
            $table->text('default_value')->nullable();
            $table->json('validation_rules')->nullable();
            $table->json('options')->nullable(); // For select types
            $table->integer('sort_order')->default(0);
            $table->boolean('is_system')->default(false);
            $table->boolean('is_encrypted')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->foreign('category')->references('name')->on('setting_categories')->onDelete('cascade');
            $table->index(['category', 'is_active', 'sort_order']);
        });

        // System settings table
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->text('value')->nullable();
            $table->string('environment', 50)->default('production');
            $table->timestamps();
            
            $table->foreign('key')->references('key')->on('setting_definitions')->onDelete('cascade');
            $table->index(['environment', 'key']);
        });

        // Setting history table for audit trail
        Schema::create('setting_history', function (Blueprint $table) {
            $table->id();
            $table->string('setting_key', 100);
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->unsignedBigInteger('changed_by')->nullable();
            $table->string('environment', 50)->default('production');
            $table->json('metadata')->nullable(); // IP, user agent, etc.
            $table->timestamps();
            
            $table->foreign('setting_key')->references('key')->on('setting_definitions')->onDelete('cascade');
            $table->foreign('changed_by')->references('id')->on('users')->onDelete('set null');
            $table->index(['setting_key', 'created_at']);
            $table->index(['changed_by', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('setting_history');
        Schema::dropIfExists('system_settings');
        Schema::dropIfExists('setting_definitions');
        Schema::dropIfExists('setting_categories');
    }
}; 