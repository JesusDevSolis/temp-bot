<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('bitrix_menu_options', function (Blueprint $table) {
            $table->id();
            $table->uuid('uid')->index();
            $table->json('options');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bitrix_menu_options');
    }
};

