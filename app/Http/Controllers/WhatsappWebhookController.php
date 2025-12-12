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
        if ($session->isExpired() || $text=='cancel') {
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
        $input = trim(strtolower((string)($text ?? '')));
        switch ($session->step) {

            case 'start':
                $body = "Bienvenue sur Wetransfert cash 👋\nChoisissez :\n- Transfert\n- Retrait\n- Solde\nRépondez par le mot correspondant.";
                $this->send($session->wa_id, $body);
                $session->update(['step' => 'awaiting_choice']);
                break;

            case 'awaiting_choice':
                if (str_contains($input, 'trans')) {
                    $session->update(['transfer_mode' => $text, 'step' => 'waiting_email']);
                    return $this->send($session->wa_id, "Entrez votre *Email*.");
                } elseif (str_contains($input, 'solde')) {
                    $session->update(['step' => 'start']);
                    return $this->send($session->wa_id, "Fonction Solde non implémentée (exemple).");
                } else {
                    $this->send($session->wa_id, "Choix non reconnu. Tapez 'Transfert', 'Retrait' ou 'Solde'.");
                }
                break;

            case 'waiting_email':
                $session->update(['phone' => $text, 'step' => 'waiting_password']);
                return $this->send($session->wa_id, "Entrez votre *mot de passe*.");

            case 'waiting_password':
                $session->update(['password' => $text, 'step' => 'choose_mode']);
                $res = WhatsappChatService::loginApi($session, $session->phone);
                if (!isset($res['status']) || $res['status'] !== 'success') {
                    return;
                }
                $data = $res['data'];
                $session->user_id = $data['customer_id'];
                $session->token = $data['token'];
                $session->step = 'choose_mode'; // prochaine étape du flow
                $session->expires_at = now()->addMinutes(30); // expiration du token
                $session->save();

                return $this->send($session->wa_id, "Mode de transfert :\n1️⃣ Mobile Money\n2️⃣ Bank");

            /*            case 'main_menu':
                            if ($text == "1") {
                                $session->update(['step' => 'choose_mode']);
                                return $this->send($session->wa_id, "Mode de transfert :\n1️⃣ Mobile Money\n2️⃣ Bank");
                            }
                            return $this->send($session->wa_id, "Répondez par 1 ou 2.");*/

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

                $response = Http::withToken($session->token)
                    ->get(config('whatsapp.wtc_url') . "v2/cities/$iso2/codeiso");
                logger($response);
                if ($response->failed()) {
                    return $this->send($session->wa_id, "❌ Code pays invalide. Réessayez.");
                }

                $res = $response->json();



                $data = $res['data'];
                $list = "";
                foreach ($data as $city) {
                    $list .= "{$city['id']}. {$city['name']}\n";
                }
                $country_id=$data[0]['country_id'];
                $session->update([
                    'country' => $iso2,
                    'countryId' => $country_id,
                    'step' => 'select_city'
                ]);
                return $this->send($session->wa_id,
                    "📍 *Villes disponibles :*\n\n$list\n\nEntrez l’ID de la ville."
                );


            // ----------------------
            // 🔥 CHOIX DE LA VILLE
            // ----------------------

            case 'select_city':


                // Récupérer expéditeurs
                $res_senders = Http::withToken($session->token)
                    ->get(config('whatsapp.wtc_url') . "v2/all_senders/{$session->user_id}")->json();
                $senders = $res_senders['data'];
                $list = "";
                foreach ($senders as $s) {
                    $firstName = isset($s['first_name']) ? $s['first_name'] : '';
                    $lastName  = isset($s['last_name']) ? $s['last_name'] : '';
                    $id        = isset($s['id']) ? $s['id'] : '';

                    // N'afficher que si l'ID existe
                    if ($id !== '') {
                        $list .= "{$id}. {$firstName} {$lastName}\n";
                    }
                }


                $session->update([
                    'cityId' => $text,
                    'senders'=>$senders,
                    'step' => 'select_sender'
                ]);
                return $this->send($session->wa_id,
                    "👤 *Choisissez l’expéditeur :*\n\n$list\n\nEntrez l’ID."
                );


            // ----------------------
            // 🔥 CHOIX EXPÉDITEUR
            // ----------------------

            case 'select_sender':

                // Récupérer les bénéficiaires via API
                $resp_benef = Http::withToken($session->token)
                    ->get(config('whatsapp.wtc_url') . "v2/all_beneficiaries/{$session->user_id}")
                    ->json();

                $benef = $resp_benef['data'] ?? [];

                // Liste des bénéficiaires (affichage)
                $list = "";
                foreach ($benef as $s) {
                    $firstName = isset($s['first_name']) ? $s['first_name'] : '';
                    $lastName  = isset($s['last_name']) ? $s['last_name'] : '';
                    $id        = isset($s['id']) ? $s['id'] : '';

                    // N'afficher que si l'ID existe
                    if ($id !== '') {
                        $list .= "{$id}. {$firstName} {$lastName}\n";
                    }
                }


                // Trouver l'expéditeur choisi
                $selectedSender = collect($session->senders)
                    ->firstWhere('id', $text);

                if (!$selectedSender) {
                    return $this->send(
                        $session->wa_id,
                        "❌ *Expéditeur introuvable.*\nVeuillez entrer un ID valide."
                    );
                }

                // Mise à jour de la session
                $session->update([
                    'senderId' => $selectedSender['id'],
                    'sender_type' => $selectedSender['type'],
                    'beneficiaries' => $benef,   // stocké en JSON automatique
                    'step' => 'select_beneficiary'
                ]);

                return $this->send(
                    $session->wa_id,
                    "🧍 *Choisissez le bénéficiaire :*\n\n$list\n\nEntrez l’ID."
                );



            // ----------------------
            // 🔥 CHOIX BÉNÉFICIAIRE
            // ----------------------

            case 'select_beneficiary':

                // 🔍 1) Trouver le bénéficiaire choisi
                $selectedBeneficiary = collect($session->beneficiaries)
                    ->firstWhere('id', $text);

                if (!$selectedBeneficiary) {
                    return $this->send(
                        $session->wa_id,
                        "❌ *Bénéficiaire introuvable.*\nVeuillez entrer un ID valide."
                    );
                }

                // Récupération des types
                $beneficiaryType = $selectedBeneficiary['type'];
                $senderType = $session->sender_type;

                // 🔍 2) Récup relations dépendants des types
                $resp_relations = Http::withToken($session->token)
                    ->get(
                    config('whatsapp.wtc_url') .
                    "v2/wace_data?sender_type={$senderType}&beneficiary_type={$beneficiaryType}&service=relaction"
                )->json();

                $relations = $resp_relations['data'] ?? [];

                // Construction de la liste
                $list = "";
                foreach ($relations as $r) {
                    $list .= "{$r['id']}. {$r['name']}\n";
                }

                // 🔍 3) Mise à jour session
                $session->update([
                    'beneficiaryId' => $selectedBeneficiary['id'],
                    'beneficiary_type' => $beneficiaryType,
                    'relations' => $relations,
                    'step' => 'select_relaction'
                ]);

                return $this->send(
                    $session->wa_id,
                    "❤️ *Relation avec le bénéficiaire :*\n\n$list\n\nEntrez le numéro."
                );

            case 'select_relaction':

                $relations = json_decode($session->relations, true);
                // Vérifier relation choisie
                $selectedRelation = collect($relations)
                    ->firstWhere('id', (int)$text);

                if (!$selectedRelation) {
                    return $this->send($session->wa_id,
                        "❌ *Relation invalide.*\nVeuillez entrer un ID valide."
                    );
                }

                $session->update([
                    'relaction' => $selectedRelation['id'],
                    'step' => 'select_origin_fond'
                ]);

                // Appel API (URL corrigée)
                $url = config('whatsapp.wtc_url')
                    . "v2/wace_data?sender_type={$session->sender_type}"
                    . "&beneficiary_type={$session->beneficiary_type}"
                    . "&service=origin_fonds";

                $resp_origin = Http::withToken($session->token)
                    ->get($url)->json();
                $origins = $resp_origin['data'] ?? [];

                $list = "";
                foreach ($origins as $o) {
                    $list .= "{$o['id']}. {$o['name']}\n";
                }

                $session->update([
                    'origins' => $origins
                ]);

                return $this->send($session->wa_id,
                    "💵 *Origine des fonds :*\n\n$list\n\nEntrez le numéro."
                );



            case 'select_origin_fond':

                $origins = json_decode($session->origins, true);
                // Vérifier origin valide
                $selectedOrigin = collect($origins)->firstWhere('id', $text);
                if (!$selectedOrigin) {
                    return $this->send($session->wa_id,
                        "❌ *Origine invalide.*\nVeuillez entrer un ID valide."
                    );
                }

                $session->update([
                    'origin_fond' => $selectedOrigin['id'],
                    'step' => 'select_motif'
                ]);

                // API Motifs
                $url = config('whatsapp.wtc_url')
                    . "v2/wace_data?sender_type={$session->sender_type}"
                    . "&beneficiary_type={$session->beneficiary_type}"
                    . "&service=raison";

                $resp_motif = Http::withToken($session->token)
                    ->get($url)->json();
                $motifs = $resp_motif['data'] ?? [];

                $list = "";
                foreach ($motifs as $m) {
                    $list .= "{$m['id']}. {$m['name']}\n";
                }

                $session->update([
                    'motifs' => $motifs
                ]);

                return $this->send($session->wa_id,
                    "📝 *Motif du transfert :*\n\n$list\n\nEntrez le numéro."
                );



            case 'select_motif':
                $motifs = json_decode($session->motifs, true);
                $selectedMotif = collect($motifs)->firstWhere('id', $text);
                if (!$selectedMotif) {
                    return $this->send($session->wa_id,
                        "❌ *Motif invalide.*\nVeuillez entrer un ID valide."
                    );
                }

                $session->update([
                    'motif' => $selectedMotif['id'],
                    'step' => 'select_operator'
                ]);

                // ⚠ Correction du bug "=" → "==="
                $isMobile = ($session->transfer_mode === "mobile" || $session->transfer_mode == 1);

                $endpoint = $isMobile ? "operatorslists" : "banklists";

                $resp_operators = Http::withToken($session->token)
                    ->get(
                    config('whatsapp.wtc_url') . "v2/$endpoint/{$session->country_id}"
                )->json();

                $operators = $resp_operators['data'] ?? [];

                $list = "";
                foreach ($operators as $op) {
                    $list .= "{$op['id']}. {$op['name']}\n";
                }

                $session->update([
                    'operators' => $operators
                ]);

                return $this->send($session->wa_id,
                    "🏦 *Choisissez un opérateur / banque :*\n\n$list\n\nEntrez le numéro."
                );


            case 'select_operator':

                // Vérifier que l'utilisateur a entré un chiffre
                if (!ctype_digit($text)) {
                    return $this->send($session->wa_id,
                        "❌ Veuillez entrer un *numéro valide* correspondant à un opérateur."
                    );
                }

                $operatorId = (int) $text;

                // Récupérer la liste des opérateurs stockés
                $operators = collect($session->operators);

                // Vérifier si l'opérateur existe dans la liste
                $selectedOperator = $operators->firstWhere('id', $operatorId);

                if (!$selectedOperator) {
                    return $this->send($session->wa_id,
                        "❌ Aucun opérateur ne correspond à ce numéro.\nVeuillez réessayer."
                    );
                }

                // Mise à jour de la session
                $session->update([
                    'operator_id' => $operatorId,
                    'step' => 'enter_account_number'
                ]);

                return $this->send($session->wa_id,
                    "💰 Entrez le *numero de compe* ."
                );


            case 'enter_account_number':
                $session->update([
                    'accountNumber' => $text,
                    'step' => 'enter_amount'
                ]);
                return $this->send($session->wa_id,
                    "💰 Entrez le *montant* du transfert."
                );

                // ----------------------
                // 🔥 MONTANT + FRAIS
                // ----------------------
            case 'enter_amount':

                // Vérifier que le montant est un nombre valide
                if (!is_numeric($text) || $text <= 0) {
                    return $this->send($session->wa_id,
                        "❌ *Montant invalide.*\nVeuillez entrer un montant correct."
                    );
                }

                $amount = (float) $text;

                // Mise à jour du montant dans la session
                $session->amount = $amount;

                // Appel API des taux
                $res_fees = Http::withToken($session->token)
                    ->get(config('whatsapp.wtc_url') . "v2/tauxechanges/{$session->country_id}")->json();

                if (!isset($res_fees['data'])) {
                    return $this->send($session->wa_id,
                        "❌ *Erreur lors de la récupération des taux.*\nVeuillez réessayer."
                    );
                }

                $fees = $res_fees['data'];

                // Calcul du taux
                $resCalcul = $this->calculTaux(
                    $amount,
                    $fees['taux_xaf_usd'] ?? 0,
                    $fees['taux_country'] ?? 0,
                    $fees['rate'] ?? 0
                );

                // Sauvegarde du résultat
                $session->update([
                    'fees'        => $resCalcul['rate'],
                    'amount_send' => $resCalcul['amount_send'],
                    'step'        => 'preview'
                ]);

                // Récupérer quelques infos lisibles
                $beneficiary = collect($session->beneficiaries)
                    ->firstWhere('id', $session->beneficiaryId);

                $sender = collect($session->senders)
                    ->firstWhere('id', $session->senderId);

                $operator = collect($session->operators)
                    ->firstWhere('id', $session->operator_id);


                return $this->send($session->wa_id,
                    "📄 *Prévisualisation de votre transfert :*\n\n" .
                    "🌍 *Mode* : {$session->transfer_mode}\n" .
                    "🇨🇲 *Pays* : {$session->country}\n" .
                    "🏙️ *Ville ID* : {$session->cityId}\n\n" .
                    "🧑‍💼 *Expéditeur* : {$sender['firstname']} {$sender['lastname']}\n" .
                    "👤 *Bénéficiaire* : {$beneficiary['firstname']} {$beneficiary['lastname']}\n" .
                    "❤️ *Relation* : {$session->relaction}\n" .
                    "💵 *Origine des fonds* : {$session->origin_fond}\n" .
                    "📝 *Motif* : {$session->motif}\n" .
                    "🏦 *Opérateur* : {$operator['name']}\n\n" .
                    "💰 *Montant envoyé* : " . number_format($session->amount, 0, ',', ' ') . " XAF\n" .
                    "💸 *Frais* : " . number_format($session->fees, 0, ',', ' ') . " XAF\n" .
                    "➡️ *Montant final envoyé* : " . number_format($session->amount_send, 0, ',', ' ') . "\n\n" .
                    "Voulez-vous *confirmer* ? (oui / non)"
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
        $res = Http::timeout(30) // 30 secondes au lieu de 10
        ->withToken($token)->post(
            "https://graph.facebook.com/v19.0/" . config('whatsapp.phone_number_id') . "/messages",
            [
                "messaging_product" => "whatsapp",
                "to" => $to,
                "text" => ["body" => $text]
            ]
        )->json();
        Log::error('send_whassap', $res);
        return response('OK', 200);
    }

    private function calculTaux(float $amount, float $tauxXafUsd, float $tauxCountry, float $ratePercent): array
    {
        // Sécurité : impossible de diviser par zéro
        if ($amount <= 0 || $tauxXafUsd <= 0 || $tauxCountry <= 0) {
            return [
                'rate' => 0.0,
                'amount_send' => 0.0
            ];
        }

        // Convertir montant en USD → montant * 1 / taux
        $amountUsd = $amount / $tauxXafUsd;

        // Convertir USD vers la monnaie du pays
        $amountCountry = $amountUsd * $tauxCountry;

        // Frais : pourcentage du montant XAF
        $fees = ($ratePercent / 100) * $amount;

        return [
            'rate' => round($fees, 2),
            'amount_send' => round($amountCountry, 2)
        ];
    }


    private function executeTransfer(WhatsappSession $session)
    {
        $data =[
        'customer_id' => $session->user_id,
        'sender_id' => $session->senderId,
        'beneficiary_id' => $session->beneficiaryId,
        'amount' => $session->amount,
        'rate' => $session->rate,
        'acount_number' => $session->accountNumber,
        'origin_fond' => $session->origin_fond,
        'relaction' => $session->relaction,
        'motif' => $session->motif,
        'comment' => $session->comment,
        'bank_name' => $session->operator_name,
        'operator_id' => $session->operator_id,
        'wallet' => "WACEPAY",
        'type' => "B",
        'country_id' =>$session-> country_id,
        'city_id' => $session->city_id,
        'swiftCode' => $session->swiftCode,
        'ifscCode' => $session->swiftCode,
        'total_amount' => $session->amount_send
    ];
        if ($session->transfer_mode=='mobile'){
            $endpoint='mobile';
        }else{
            $endpoint='bank';
        }
        $res = Http::withToken($session->token)
            ->post(config('whatsapp.wtc_url') . "v2/transferts/$endpoint",$data)->json();
        return $res;

    }
}
