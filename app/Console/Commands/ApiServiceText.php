<?php

namespace App\Console\Commands;

use App\Models\WhatsappSession;
use App\Services\WhatsappChatService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class ApiServiceText extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:api-service-text';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $session = WhatsappSession::find(18);
        if (!$session) {
            logger("Session non trouvée");
            return;
        }

/*        $email = 'rodriguembah13@gmail.com';
        $res = WhatsappChatService::loginApi($session, $email);

        logger($res);

        if (!isset($res['status']) || $res['status'] !== 'success') {
            logger("Erreur de login API");
            return;
        }

        // On récupère les informations API
        $data = $res['data'];

        // Mise à jour de la session
        $session->user_id     = $data['customer_id'];
        $session->token       = $data['token'];
        $session->step        = 'choose_mode'; // prochaine étape du flow
        $session->expires_at  = now()->addMinutes(30); // expiration du token
        $session->save();*/
        // ⚠ Correction du bug "=" → "==="
        $isMobile = ($session->transfer_mode === "mobile" || $session->transfer_mode == 1);

        $endpoint = $isMobile ? "operatorslists" : "banklists";
logger($session->countryId);
        $resp_operators = Http::withToken($session->token)
            ->get(
                config('whatsapp.wtc_url') . "v2/$endpoint/{$session->countryId}"
            )->json();
        logger($resp_operators);
        logger("Session mise à jour avec succès");
    }


}
