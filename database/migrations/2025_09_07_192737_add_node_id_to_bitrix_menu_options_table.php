<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('bitrix_menu_options', function (Blueprint $table) {
            $table->unsignedBigInteger('node_id')->nullable()->after('bitrix_session_id')->comment('ID del nodo relacionado al menú');
        });
    }

    public function down(): void
    {
        Schema::table('bitrix_menu_options', function (Blueprint $table) {
            $table->dropColumn('node_id');
        });
    }
};