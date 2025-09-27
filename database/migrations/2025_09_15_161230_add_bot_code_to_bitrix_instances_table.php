<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('bitrix_instances', function (Blueprint $table) {
            $table->string('bot_code')->nullable()->after('bot_id')->comment('Identificador único del bot registrado');
        });
    }

    public function down()
    {
        Schema::table('bitrix_instances', function (Blueprint $table) {
            $table->dropColumn('bot_code');
        });
    }
};
