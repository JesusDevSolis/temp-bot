<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('bitrix_sessions', function (Blueprint $table) {
            $table->string('op_group_id')->nullable()->after('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('bitrix_sessions', function (Blueprint $table) {
            $table->dropColumn('op_group_id');
        });
    }
};