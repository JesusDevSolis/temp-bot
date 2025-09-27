<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bitrix_sessions', function (Blueprint $table) {
            $table->id();

            $table->string('user_id');         // ej: user-b11
            $table->string('chat_id');         // ej: 7
            $table->string('uid');             // UID real entregado por Ánima
            $table->unsignedBigInteger('current_node_id')->nullable();
            $table->unsignedBigInteger('next_node_id')->nullable();

            $table->timestamps();

            $table->unique(['user_id', 'chat_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bitrix_sessions');
    }
};
