<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BitrixMaintenanceController extends Controller
{
    public function resetBitrixData(Request $request): JsonResponse
    {
        try {
            $token = $request->header('X-Maintenance-Token');
            if ($token !== env('BITRIX_MAINTENANCE_TOKEN')) {
                return response()->json(['message' => 'No autorizado'], 401);
            }
            
            DB::statement('SET FOREIGN_KEY_CHECKS = 0');

            // Borrar registros manualmente por si hay restricciones
            DB::table('bitrix_sessions')->delete();

            // Truncar otras tablas relacionadas
            DB::table('bitrix_user_inputs')->truncate();
            DB::table('bitrix_menu_options')->truncate();
            DB::table('bitrix_webhook_logs')->truncate();
            DB::table('bitrix_conversation_threads')->truncate();

            DB::statement('SET FOREIGN_KEY_CHECKS = 1');

            // Analizar tablas
            $tables = [
                'bitrix_sessions',
                'bitrix_menu_options',
                'bitrix_user_inputs',
                'bitrix_webhook_logs',
                'bitrix_conversation_threads',
                'bitrix_instances',
            ];

            foreach ($tables as $table) {
                DB::statement("ANALYZE TABLE $table");
            }

            Log::info('[BitrixMaintenance] Limpieza de datos y análisis completado');

            return response()->json([
                'message' => 'Datos de Bitrix limpiados y analizados correctamente.',
                'status' => 'success',
            ]);
        } catch (\Throwable $e) {
            Log::error('[BitrixMaintenance] Error al limpiar datos', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Error al limpiar los datos.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}