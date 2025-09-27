<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BitrixBotToggleController;
use App\Http\Controllers\BitrixInstanceController;
use App\Http\Controllers\BitrixInstanceSetupController;
use App\Http\Controllers\BitrixManualTransferController;
use App\Http\Controllers\BitrixOAuthController;
use App\Http\Controllers\BitrixWebhookController;
use App\Http\Controllers\BitrixMaintenanceController;
use App\Http\Controllers\BitrixBotUpdateController;
/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Aquí definimos las rutas para la versión v1.0.0 de nuestra integración
| Bitrix ↔ Ánima Bot. Todas responderán bajo el prefijo /api/v1.0.0.
|
*/

Route::prefix('v1.0.0')->group(function () {

    // [1] Ruta de instalación/autorización de la app en Bitrix
    // Soporta GET y POST según cómo Bitrix envíe el request.
    Route::match(['get', 'post'], 'bitrix/oauth/authorize', [
        BitrixOAuthController::class,
        'install'
    ]);

    // [1.1] Endpoint para desinstalación: Bitrix notificará aquí al eliminar la app
    Route::match(['get', 'post'], 'bitrix/oauth/uninstall', [
        BitrixOAuthController::class,
        'uninstall'
    ]);

    // [2] Ruta de callback de OAuth para recibir el authorization_code
    // e intercambiarlo por access_token y refresh_token.
    Route::get('bitrix/oauth/callback', [
        BitrixOAuthController::class,
        'callback'
    ]);

    // [3] Registrar o actualizar la configuración de una instancia Bitrix.
    // Se usa para guardar el portal, client_id, client_secret y auth_token.
    Route::post('bitrix/instance/setup', [
        BitrixInstanceSetupController::class, 
        'setup'
    ])->middleware('verify.bitrix');

    // [4] Webhook de entrada desde Bitrix hacia Ánima:
    // Recibe mensajes de usuario desde canales conectados (ej. Telegram).
    // Protegida por middleware de validación de firma o token.
    Route::post('webhook/bitrix/message', [
        BitrixWebhookController::class,
        'handle'
    ]);

    // [5] Activar o desactivar el bot para el portal actual.
    // Se usa para alternar entre modo bot y modo agente humano.
    Route::post('bitrix/bot-toggle', [
        BitrixBotToggleController::class, 
        'toggle'
    ])->middleware('verify.bitrix');

    // [6] Consultar el estado actual del bot (activo o inactivo).
    Route::get('bitrix/bot-status', [
        BitrixBotToggleController::class, 
        'status'
    ])->middleware('verify.bitrix');
    
    // [7] Realizar una transferencia manual desde el bot hacia un agente.
    Route::post('bitrix/manual-transfer', [
        BitrixManualTransferController::class,
        'transfer'
    ])->middleware('verify.bitrix');

    // [8] Endpoint para actualizar el `hash` de un portal Bitrix.
    // Usado cuando se desea cambiar dinámicamente el árbol conversacional.
    Route::post('bitrix/update-hash', [
        BitrixInstanceController::class, 
        'updateHash'
    ])->middleware('verify.bitrix');

    // [9] Cambiar el nombre del bot registrado en Bitrix
    Route::post('bitrix/bot-update-name', [
        BitrixBotUpdateController::class,
        'updateName'
    ])->middleware('verify.bitrix');

    // [10] Ruta para mantenimiento y limpieza de datos antiguos.
    // Protegida por una clave secreta en el query string.
    Route::post('bitrix/maintenance/reset', [
        BitrixMaintenanceController::class,
        'resetBitrixData'
    ]);

});
