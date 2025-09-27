<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * Ruta "home" de la aplicación.
     * Se usa tras el login para redirigir al usuario en caso de crear interfaz.
     */
    public const HOME = '/dashboard';

    /**
     * Aquí puedes definir bindings de modelos, filtros de patrones.
     * Se ejecuta al arrancar el servicio de rutas.
     */
    public function boot(): void
    {
        // Limitación de peticiones, descomenta:
        // $this->configureRateLimiting();

        // 1) Registrar las rutas web (session, CSRF, views)
        // Carga de rutas web (routes/web.php)
        Route::middleware('web')
            ->group(base_path('routes/web.php'));

        // 2) Registrar las rutas API (stateless, throttle)
        // Carga de rutas API (routes/api.php)
        Route::prefix('api')
            ->middleware('api')
            ->group(base_path('routes/api.php'));
    }

    /**
     * Configura limitadores de tasa (throttling) para tu API.
     * Por ejemplo, 60 peticiones por minuto por usuario o IP.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request) {
            // Si el usuario está autenticado usa su id, si no la IP
            return Limit::perMinute(60)
                            ->by($request
                            ->user()?->id ?: $request->ip());
        });
    }
}
