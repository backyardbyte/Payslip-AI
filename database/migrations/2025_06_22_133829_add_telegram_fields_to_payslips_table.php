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
        Schema::table('payslips', function (Blueprint $table) {
            $table->string('original_filename')->nullable()->after('file_path');
            $table->string('source')->default('web')->after('extracted_data');
            $table->bigInteger('telegram_chat_id')->nullable()->after('source');
            
            $table->index('telegram_chat_id');
            $table->index('source');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payslips', function (Blueprint $table) {
            $table->dropIndex(['telegram_chat_id']);
            $table->dropIndex(['source']);
            $table->dropColumn(['original_filename', 'source', 'telegram_chat_id']);
        });
    }
};
