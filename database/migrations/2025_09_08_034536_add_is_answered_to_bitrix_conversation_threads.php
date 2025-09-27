<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('bitrix_conversation_threads', function (Blueprint $table) {
            $table->boolean('is_answered')->default(false)->after('ai_response');
        });
    }

    public function down()
    {
        Schema::table('bitrix_conversation_threads', function (Blueprint $table) {
            $table->dropColumn('is_answered');
        });
    }
};
