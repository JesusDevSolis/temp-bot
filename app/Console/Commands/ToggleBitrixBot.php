<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\BitrixInstance;

class ToggleBitrixBot extends Command
{
    protected $signature = 'bitrix:toggle-bot 
                            {--enable : Activa el bot} 
                            {--disable : Desactiva el bot}';

    protected $description = 'Activa o desactiva el bot para un portal específico';

    public function handle()
    {
        $portal = config('services.bitrix.portal_url');
        $enable = $this->option('enable');
        $disable = $this->option('disable');

        if (!$enable && !$disable) {
            $this->error('Debes usar --enable o --disable.');
            return;
        }

        $instance = BitrixInstance::where('portal', $portal)->first();

        if (!$instance) {
            $this->error("No se encontró portal: $portal");
            return;
        }

        $instance->update([
            'enabled' => $enable ? true : false
        ]);

        $estado = $enable ? 'ACTIVADO' : 'DESACTIVADO';
        $this->info("Bot $estado para el portal: $portal");
    }
}
