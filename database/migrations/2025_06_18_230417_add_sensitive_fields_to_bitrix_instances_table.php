<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bitrix_instances', function (Blueprint $table) {
            $table->string('client_id')->nullable()->after('portal');
            $table->string('client_secret')->nullable()->after('client_id');
            $table->string('auth_token')->nullable()->after('client_secret');
        });
    }

    public function down(): void
    {
        Schema::table('bitrix_instances', function (Blueprint $table) {
            $table->dropColumn(['client_id', 'client_secret', 'auth_token']);
        });
    }
};
