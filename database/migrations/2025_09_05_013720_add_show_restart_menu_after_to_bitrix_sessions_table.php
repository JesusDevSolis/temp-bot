<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('bitrix_sessions', function (Blueprint $table) {
            $table->boolean('show_restart_menu_after')->default(false)->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('bitrix_sessions', function (Blueprint $table) {
            $table->dropColumn('show_restart_menu_after');
        });
    }
};
