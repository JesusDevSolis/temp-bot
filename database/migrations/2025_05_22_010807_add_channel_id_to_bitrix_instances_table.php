<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bitrix_instances', function (Blueprint $table) {
            $table->string('channel_id')->nullable()->after('portal');
        });
    }

    public function down(): void
    {
        Schema::table('bitrix_instances', function (Blueprint $table) {
            $table->dropColumn('channel_id');
        });
    }
};
