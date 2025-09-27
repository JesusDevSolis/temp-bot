<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bitrix_sessions', function (Blueprint $table) {
            $table->string('dialog_id')->nullable()->after('uid');
        });
    }

    public function down(): void
    {
        Schema::table('bitrix_sessions', function (Blueprint $table) {
            $table->dropColumn('dialog_id');
        });
    }
};