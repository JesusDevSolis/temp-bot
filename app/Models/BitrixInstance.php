<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BitrixInstance extends Model
{
    use HasFactory;

    // Especifica la tabla asociada en la base de datos
    protected $table = 'bitrix_instances';

    /**
     * Los campos que se permiten asignar de forma masiva.
     * Con esto evitamos errores al usar updateOrCreate o create().
     */
    protected $fillable = [
        'portal',               // Dominio del portal de Bitrix (por ejemplo: b24-xyz.bitrix24.mx)
        'client_id',           // ID del cliente OAuth de la aplicación en Bitrix24
        'client_secret',       // Secreto del cliente OAuth de la aplicación en Bitrix24
        'auth_token',          // Token estático usado en webhooks de salida para validar llamadas
        'channel_id',
        'hash',
        'access_token',         // Token de acceso OAuth para llamar la API de Bitrix
        'refresh_token',        // Token para refrescar el access_token cuando expire
        'application_token',    // Token de la aplicación (ID de la app registrada en Bitrix)
        'bot_id',               // ID interno del bot registrado en Open Lines
        'expires',              // Fecha/hora de expiración del access_token
        'enabled',              // Activar o desactivar bot por canal
        'bot_code',
    ];

    /**
     * Casts: convierte automáticamente campos al obtenerlos o guardarlos.
     * Aquí indicamos que “expires” sea un objeto DateTime de Carbon.
     */

    protected $casts = [
        'expires' => 'datetime',
    ];
}
