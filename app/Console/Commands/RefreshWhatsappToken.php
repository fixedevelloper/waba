<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\WhatsappTokenService;

class RefreshWhatsappToken extends Command
{
    protected $signature = 'whatsapp:refresh-token';
    protected $description = 'Refresh WhatsApp long-lived token automatically';

    public function handle(WhatsappTokenService $service)
    {
        $newToken = $service->refreshToken();
        $this->info($newToken);
        if ($newToken) {
            $this->info("New WhatsApp token successfully generated.");
        } else {
            $this->error("Token refresh failed.");
        }
    }
}

