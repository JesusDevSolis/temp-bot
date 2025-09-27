<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBitrixInstancesTable extends Migration
{
    /**
     * Run the migrations.
     * Este método crea la tabla `bitrix_instances` que almacena
     * las credenciales y configuración de cada portal de Bitrix24.
     */
    public function up()
    {
        Schema::create('bitrix_instances', function (Blueprint $table) {
            $table->id();   // Clave primaria auto‑incremental
            $table->string('portal')->unique()->comment('Dominio/identificador único del portal Bitrix'); //ej: b24-xyz.bitrix24.mx
            $table->string('access_token')->comment('Token de acceso OAuth'); //Se obtiene al instalar app en Bitrix
            $table->string('refresh_token')->nullable()->comment('Token para refrescar acceso');
            $table->unsignedBigInteger('bot_id')->nullable()->comment('ID del bot registrado en Bitrix');
            $table->dateTime('expires')->nullable()->comment('Fecha y hora de expiración del access_token');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     * Elimina la tabla `bitrix_instances` al hacer rollback.
     */
    public function down()
    {
        Schema::dropIfExists('bitrix_instances');
    }
}
