<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('bitrix_sessions', function (Blueprint $table) {
            $table->string('portal')->nullable()->after('chat_id');
        });
    }

    public function down()
    {
        Schema::table('bitrix_sessions', function (Blueprint $table) {
            $table->dropColumn('portal');
        });
    }

};
