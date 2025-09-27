<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('bitrix_conversation_threads', function (Blueprint $table) {
            $table->id();
            $table->uuid('uid'); // Para vincularlo al usuario
            $table->unsignedBigInteger('bitrix_session_id'); // Para rastrear la sesión
            $table->unsignedBigInteger('node_id')->nullable(); // Nodo donde ocurrió la pregunta
            $table->text('user_message'); // Pregunta del usuario
            $table->text('ai_response')->nullable(); // Respuesta del modelo IA
            $table->string('thread_id')->nullable(); // ID del hilo generado por la IA
            $table->timestamps();

            $table->index(['uid', 'bitrix_session_id']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('bitrix_conversation_threads');
    }
};