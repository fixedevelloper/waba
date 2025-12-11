<?php

namespace App\Http\Controllers;

use App\Models\WhatsappSession;
use App\Services\WhatsappChatService;
use App\Services\WhatsappTokenService;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsappWebhookController extends Controller
{
    public function verify(Request $request)
    {
        $mode = $request->query('hub_mode');
        $token = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        Log::info('Webhook verify request', $request->all());
        if ($mode === 'subscribe' && $token === env('WHATSAPP_VERIFY_TOKEN')) {
            return response($challenge, 200);
        }

        // Log tentative d'accès
        Log::warning('Webhook WhatsApp: Token invalide', [
            'received_token' => $token,
            'expected_token' => env('WHATSAPP_VERIFY_TOKEN')
        ]);

        return response('Invalid verify token', 403);
    }

    public function handle(Request $request)
    {
        // 🔥 Extraction propre du message
        $entry = $request->input('entry')[0] ?? null;
        if (!$entry) return response('NO_ENTRY', 200);

        $changes = $entry['changes'][0] ?? null;
        if (!$changes) return response('NO_CHANGE', 200);

        $value = $changes['value'] ?? null;
        if (!$value) return response('NO_VALUE', 200);

        $message = $value['messages'][0] ?? null;
        if (!$message) return response('NO_MESSAGE', 200);

        $waId = $message['from'];       // ex: 237690000000
        $text = $message['text']['body'] ?? null;

        // Création ou récupération de la session
        $session = WhatsappSession::firstOrCreate(
            ['wa_id' => $waId],
            ['step' => 'start']
        );

        // Si session expirée → reset
        if ($session->isExpired()) {
            $session->update([
                'step' => 'start',
                'token' => null,
                'user_id' => null
            ]);
        }

        // DISPATCH AUTOMATIQUE DES ÉTAPES
        return $this->processStep($session, $text);
    }


    /**
     * 🔥 LOGIQUE GLOBALE DU CHATBOT
     * @param WhatsappSession $session
     * @param $text
     * @return ResponseFactory
     */
    private function processStep(WhatsappSession $session, $text)
    {
        $input = trim(strtolower((string) ($text ?? '')));
        switch ($session->step) {

            case 'menu':
                $body = "Bienvenue sur MonService 👋\nChoisissez :\n- Transfert\n- Retrait\n- Solde\nRépondez par le mot correspondant.";
                $this->send($session->wa_id, $body);
                $session->update(['step' => 'awaiting_choice']);
                break;

            case 'awaiting_choice':
                if (str_contains($input, 'trans')) {
                    $session->update(['email' => $text, 'step' => 'waiting_password']);
                    return $this->send($session->wa_id, "Entrez votre *mot de passe*.");
                } elseif (str_contains($input, 'solde')) {
                    $session->update(['step' => 'menu']);
                    return  $this->send($session->wa_id, "Fonction Solde non implémentée (exemple).");
                } else {
                    $this->send($session->wa_id, "Choix non reconnu. Tapez 'Transfert', 'Retrait' ou 'Solde'.");
                }
                break;

            case 'waiting_email':
                $session->update(['email' => $text, 'step' => 'waiting_password']);
                return $this->send($session->wa_id, "Entrez votre *mot de passe*.");

            case 'waiting_password':
                return $this->loginTransfertApi($session, $text);

            case 'main_menu':
                if ($text == "1") {
                    $session->update(['step' => 'choose_mode']);
                    return $this->send($session->wa_id, "Mode de transfert :\n1️⃣ Mobile Money\n2️⃣ Bank");
                }
                return $this->send($session->wa_id, "Répondez par 1 ou 2.");

            case 'choose_mode':
                $mode = $text == "1" ? "mobile" : "bank";
                $session->update([
                    'transfer_mode' => $mode,
                    'step' => 'enter_country'
                ]);
                return $this->send($session->wa_id, "Entrez le *code ISO2* du pays (ex : CM, CI, SN).");


            // ---------------------
            // 🔥 ÉTAPE : CHOIX PAYS
            // ---------------------

            case 'enter_country':
                $iso2 = strtoupper($text);

                // API: Toutes les villes du pays
                $cities = Http::withToken($session->token)
                    ->get(env("API_TRANSFERT")."/countries/$iso2/cities");

                if ($cities->failed()) {
                    return $this->send($session->wa_id, "❌ Code pays invalide. Réessayez.");
                }

                $session->update([
                    'country' => $iso2,
                    'step' => 'select_city'
                ]);

                $list = "";
                foreach ($cities->json() as $city) {
                    $list .= "{$city['id']}. {$city['name']}\n";
                }

                return $this->send($session->wa_id,
                    "📍 *Villes disponibles :*\n\n$list\n\nEntrez l’ID de la ville."
                );


            // ----------------------
            // 🔥 CHOIX DE LA VILLE
            // ----------------------

            case 'select_city':
                $session->update([
                    'cityId' => $text,
                    'step' => 'select_sender'
                ]);

                // Récupérer expéditeurs
                $senders = Http::withToken($session->token)
                    ->get(env("API_TRANSFERT")."/senders?user_id={$session->user_id}");

                $list = "";
                foreach ($senders->json() as $s) {
                    $list .= "{$s['id']}. {$s['firstname']} {$s['lastname']}\n";
                }

                return $this->send($session->wa_id,
                    "👤 *Choisissez l’expéditeur :*\n\n$list\n\nEntrez l’ID."
                );


            // ----------------------
            // 🔥 CHOIX EXPÉDITEUR
            // ----------------------

            case 'select_sender':
                $session->update([
                    'senderId' => $text,
                    'step' => 'select_beneficiary'
                ]);

                // Récupérer bénéficiaires
                $benef = Http::withToken($session->token)
                    ->get(env("API_TRANSFERT")."/beneficiaries?user_id={$session->user_id}");

                $list = "";
                foreach ($benef->json() as $b) {
                    $list .= "{$b['id']}. {$b['firstname']} {$b['lastname']}\n";
                }

                return $this->send($session->wa_id,
                    "🧍 *Choisissez le bénéficiaire :*\n\n$list\n\nEntrez l’ID."
                );


            // ----------------------
            // 🔥 CHOIX BÉNÉFICIAIRE
            // ----------------------

            case 'select_beneficiary':
                $session->update([
                    'beneficiaryId' => $text,
                    'step' => 'select_relaction'
                ]);

                // Récup relations
                $relations = Http::get(env("API_TRANSFERT")."/relations")->json();

                $list = "";
                foreach ($relations as $r) {
                    $list .= "{$r['code']}. {$r['label']}\n";
                }

                return $this->send($session->wa_id,
                    "❤️ *Relation avec le bénéficiaire :*\n\n$list\n\nEntrez le code."
                );


            case 'select_relaction':
                $session->update([
                    'relaction' => $text,
                    'step' => 'select_origin_fond'
                ]);

                // Origine de fonds
                $origins = Http::get(env("API_TRANSFERT")."/origins")->json();

                $list = "";
                foreach ($origins as $o) {
                    $list .= "{$o['code']}. {$o['label']}\n";
                }

                return $this->send($session->wa_id,
                    "💵 *Origine des fonds :*\n\n$list\n\nEntrez le code."
                );


            case 'select_origin_fond':
                $session->update([
                    'origin_fond' => $text,
                    'step' => 'select_motif'
                ]);

                // Motifs
                $motifs = Http::get(env("API_TRANSFERT")."/motifs")->json();

                $list = "";
                foreach ($motifs as $m) {
                    $list .= "{$m['code']}. {$m['label']}\n";
                }

                return $this->send($session->wa_id,
                    "📝 *Motif du transfert :*\n\n$list\n\nEntrez le code."
                );


            case 'select_motif':
                $session->update([
                    'motif' => $text,
                    'step' => 'enter_amount'
                ]);

                return $this->send($session->wa_id,
                    "💰 Entrez le *montant* du transfert."
                );


            // ----------------------
            // 🔥 MONTANT + FRAIS
            // ----------------------

            case 'enter_amount':
                $session->update(['amount' => $text]);

                // API frais
                $fees = Http::withToken($session->token)->post(
                    env("API_TRANSFERT")."/fees",
                    [
                        "amount"  => $session->amount,
                        "country" => $session->country,
                        "mode"    => $session->transfer_mode
                    ]
                )->json();

                $session->fees = $fees["fees"] ?? 0;

                $session->step = "preview";
                $session->save();

                return $this->send($session->wa_id,
                    "📄 *Prévisualisation :*\n\n".
                    "Mode : {$session->transfer_mode}\n".
                    "Pays : {$session->country}\n".
                    "Ville ID : {$session->cityId}\n".
                    "Expéditeur ID : {$session->senderId}\n".
                    "Bénéficiaire ID : {$session->beneficiaryId}\n".
                    "Relation : {$session->relaction}\n".
                    "Origine funds : {$session->origin_fond}\n".
                    "Motif : {$session->motif}\n".
                    "Montant : {$session->amount}\n".
                    "Frais : {$session->fees}\n\n".
                    "Confirmer ? (oui / non)"
                );


            // ----------------------
            // 🔥 CONFIRM TRANSFER
            // ----------------------

            case 'preview':
                if (strtolower($text) !== "oui") {
                    $session->update(['step' => 'main_menu']);
                    return $this->send($session->wa_id, "❌ Transfert annulé.");
                }

                return $this->executeTransfer($session);
        }
    }


    /**
     * 🔥 Méthode pour envoyer un message WhatsApp Cloud API
     * @param $to
     * @param $text
     * @return ResponseFactory
     */
    private function send($to, $text)
    {
        $token = app(WhatsappTokenService::class)->getToken();
      $res=  Http::timeout(30) // 30 secondes au lieu de 10
        ->withToken($token)->post(
            "https://graph.facebook.com/v19.0/".config('whatsapp.phone_number_id')."/messages",
            [
                "messaging_product" => "whatsapp",
                "to" => $to,
                "text" => ["body" => $text]
            ]
        )->json();
Log::error('send_whassap',$res);
        return response('OK', 200);
    }
}
