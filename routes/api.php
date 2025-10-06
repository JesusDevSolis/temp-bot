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
use App\Http\Controllers\BitrixMarketController;
use App\Http\Controllers\WebhookController;

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
    Route::match(['get', 'post', 'head'], 'bitrix/oauth/authorize', [
        BitrixOAuthController::class,
        'install'
    ]);

    // [1.1] Endpoint para desinstalación
    Route::match(['get', 'post', 'head'], 'bitrix/oauth/uninstall', [
        BitrixOAuthController::class,
        'uninstall'
    ]);

    // [2] Callback OAuth
    Route::match(['get', 'post', 'head'], 'bitrix/oauth/callback', [
        BitrixOAuthController::class,
        'callback'
    ]);

    // [3] Setup instancia Bitrix
    Route::post('bitrix/instance/setup', [
        BitrixInstanceSetupController::class, 
        'setup'
    ])->middleware('verify.bitrix');

    // [4] Webhook de entrada desde Bitrix
    Route::post('webhook/bitrix/message', [
        BitrixWebhookController::class,
        'handle'
    ]);

    // [5] Toggle bot
    Route::post('bitrix/bot-toggle', [
        BitrixBotToggleController::class, 
        'toggle'
    ])->middleware('verify.bitrix');

    // [6] Estado bot
    Route::get('bitrix/bot-status', [
        BitrixBotToggleController::class, 
        'status'
    ])->middleware('verify.bitrix');
    
    // [7] Transferencia manual
    Route::post('bitrix/manual-transfer', [
        BitrixManualTransferController::class,
        'transfer'
    ])->middleware('verify.bitrix');

    // [8] Update hash
    Route::post('bitrix/update-hash', [
        BitrixInstanceController::class, 
        'updateHash'
    ])->middleware('verify.bitrix');

    // [9] Update nombre bot
    Route::post('bitrix/bot-update-name', [
        BitrixBotUpdateController::class,
        'updateName'
    ])->middleware('verify.bitrix');

    // [10] Mantenimiento
    Route::post('bitrix/maintenance/reset', [
        BitrixMaintenanceController::class,
        'resetBitrixData'
    ]);

    // 🔹 Rutas Bitrix Market (agregamos HEAD aquí también)
    Route::match(['get', 'post', 'head'], 'bitrix/install', [BitrixMarketController::class, 'install']);
    Route::match(['get', 'post', 'head'], 'bitrix/uninstall', [BitrixMarketController::class, 'uninstall']);  
    Route::match(['get', 'post', 'head'], 'bitrix/app', [BitrixMarketController::class, 'app']);

    // 🔹 Webhooks auxiliares
    Route::post('/webhook/config', [WebhookController::class, 'saveConfig']);
    Route::get('/webhook/config', [WebhookController::class, 'getConfig']);
    Route::post('/webhook/update-bot-name', [WebhookController::class, 'updateBotName']);
    Route::post('/webhook/uninstall', [WebhookController::class, 'handleUninstall']);

});
