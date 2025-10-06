<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BitrixMarketController;

Route::get('/', function () {
    return view('welcome');
});

// Página de configuración post-instalación del Market
Route::get('/setup', [BitrixMarketController::class, 'showSetupPage']);
Route::post('/setup', [BitrixMarketController::class, 'saveSetupConfig']);


Route::get('/bitrix/setup-help', function () {
    return view('setup-help');
});
