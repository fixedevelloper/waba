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
        $session = WhatsappSession::find(1);
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
        $res_senders = Http::withToken($session->token)
            ->get(config('whatsapp.wtc_url') . "v2/all_senders/{$session->user_id}")->json();
        $senders = $res_senders['data'];
        $list = "";
        foreach ($senders as $s) {
            logger($s['first_name']);
            $list .= "{$s['id']}. {$s['first_name']} {$s['last_name']}\n";
        }
        logger($list);
        logger("Session mise à jour avec succès");
    }


}
