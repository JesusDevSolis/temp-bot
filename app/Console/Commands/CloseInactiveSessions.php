<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Console\Scheduling\Schedule;
use Symfony\Component\Console\Attribute\AsCommand;
use App\Models\BitrixSession;
use App\Services\Bitrix\BitrixSessionFinalizerService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

#[AsCommand(name: 'bitrix:close-inactive-sessions')]
class CloseInactiveSessions extends Command
{
    protected $description = 'Marca como cerradas las sesiones inactivas con más de 24 horas de antigüedad';

    public function handle(): void
    {
        $cutoff = Carbon::now()->subHours(24);

        $sessions = BitrixSession::where('status', 'active')
            ->where('created_at', '<', $cutoff)
            ->get();

        $cerradas = 0;

        foreach ($sessions as $session) {
            BitrixSessionFinalizerService::finalizarSesionYNotificar($session);
            $cerradas++;
        }

        Log::info('[BitrixSession] Sesiones cerradas por inactividad (24h)', [
            'total_cerradas' => $cerradas,
        ]);

        $this->info("Sesiones cerradas: $cerradas");
    }

    public function schedule(Schedule $schedule): void
    {
        $schedule->hourly(); 
    }
}
