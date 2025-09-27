<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bitrix_menu_options', function (Blueprint $table) {
            $table->unsignedBigInteger('bitrix_session_id')->nullable()->after('id');
            $table->boolean('is_main_menu')->default(false)->after('bitrix_session_id');

            $table->foreign('bitrix_session_id')
                  ->references('id')
                  ->on('bitrix_sessions')
                  ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('bitrix_menu_options', function (Blueprint $table) {
            $table->dropForeign(['bitrix_session_id']);
            $table->dropColumn(['bitrix_session_id', 'is_main_menu']);
        });
    }
};
