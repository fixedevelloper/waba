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
        if ($session->isExpired() || $text=='cancel'|| $text=='menu') {
            $session->update([
                'step' => 'start',
                'token' => null,
                'user_id' => null
            ]);
        }

        if ($session->step == 'start') {

            $body = "👋 *Bienvenue sur Wetransfert Cash*\n\n"
                . "Veuillez choisir une option :\n"
                . "1️⃣ - Mode *Invite*\n"
                . "2️⃣ - Mode *Client*\n"
                . "3️⃣ - *Calculer le taux*\n\n"
                . "Répondez par *1*, *2* ou *3*.";

            $this->send($session->wa_id, $body);
            $session->update([
                'step' => 'awaiting_init',
                'mode_step' => 'none',
            ]);
            return;
        }
        if ($session->step == 'awaiting_init' && $session->mode_step == 'none') {

            switch ($text) {

                case "1":
                    $session->update([
                        'step' => 'awaiting_traitement',
                        'mode_step' => 'guess'
                    ]);
                    return $this->processInviteStep($session, $text);

                case "2":
                    $session->update([
                        'step' => 'awaiting_traitement',
                        'mode_step' => 'customer'
                    ]);
                    return $this->processClientStep($session, $text);

                case "3":
                    $session->update([
                        'step' => 'awaiting_traitement',
                        'mode_step' => 'calcul_rate'
                    ]);
                    return $this->processRateCalculator($session, $text);

                default:
                    return $this->send($session->wa_id,
                        "❌ *Option invalide.*\nRépondez uniquement par 1️⃣, 2️⃣ ou 3️⃣."
                    );
            }
        }
        if ($session->mode_step == 'guess') {
            return $this->processInviteStep($session, $text);

        } elseif ($session->mode_step == 'customer') {
            return $this->processClientStep($session, $text);

        } elseif ($session->mode_step == 'calcul_rate') {
            return $this->processRateCalculator($session, $text);
        }

// Si jamais un step non prévu arrive → fallback sécurité
        return $this->send($session->wa_id,
            "❌ Une erreur est survenue. Session réinitialisée."
        );




        // DISPATCH AUTOMATIQUE DES ÉTAPES

    }


    /**
     * 🔥 LOGIQUE GLOBALE DU CHATBOT
     * @param WhatsappSession $session
     * @param $text
     * @return ResponseFactory
     */
    private function processClientStep(WhatsappSession $session, $text)
    {
        $input = trim(strtolower((string)($text ?? '')));
        switch ($session->step) {

            case 'awaiting_traitement':
                $body = "👋\nChoisissez :\n- Transfert\n- Retrait\n- Solde\nRépondez par le mot correspondant.";
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
                    config('whatsapp.wtc_url') . "v2/$endpoint/{$session->countryId}"
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
                $resoperators = json_decode($session->operators, true);
                // Récupérer la liste des opérateurs stockés
                $operators = collect($resoperators);

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

                // Convertir le montant en float
                $amount = floatval($text);

                // Mise à jour du montant dans la session
                $session->amount = $amount;

                // Appel API des taux
                $res_fees = Http::withToken($session->token)
                    ->get(config('whatsapp.wtc_url') . "v2/tauxechanges/{$session->countryId}")->json();

                // Vérification de la réponse de l'API
                if (isset($res_fees['data'])) {
                    $fees = $res_fees['data'];
                } else {
                    return $this->send($session->wa_id,
                        "❌ *Erreur lors de la récupération des taux.*\nVeuillez réessayer."
                    );
                }

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

                // Récupérer les informations lisibles (expéditeur, bénéficiaire, opérateur)
                $beneficiary = collect($session->beneficiaries)->firstWhere('id', $session->beneficiaryId);
                $sender = collect($session->senders)->firstWhere('id', $session->senderId);
                $operator = collect($session->operators)->firstWhere('id', $session->operator_id);

                // Vérifications si les données sont disponibles
                if (!$beneficiary || !$sender || !$operator) {
                    return $this->send($session->wa_id,
                        "❌ *Erreur* : Impossible de trouver toutes les informations nécessaires pour le transfert."
                    );
                }

                // Prévisualisation de la transaction
                return $this->send($session->wa_id,
                    "📄 *Prévisualisation de votre transfert :*\n\n" .
                    "🌍 *Mode* : {$session->transfer_mode}\n" .
                    "🇨🇲 *Pays* : {$session->country}\n" .
                    "🏙️ *Ville ID* : {$session->cityId}\n\n" .
                    "🧑‍💼 *Expéditeur* : {$sender['first_name']} {$sender['last_name']}\n" .
                    "👤 *Bénéficiaire* : {$beneficiary['first_name']} {$beneficiary['last_name']}\n" .
                    "❤️ *Relation* : {$session->relaction}\n" .
                    "💵 *Origine des fonds* : {$session->origin_fond}\n" .
                    "📝 *Motif* : {$session->motif}\n" .
                    "🏦 *Opérateur* : {$operator['name']}\n\n" .
                    "💰 *Montant envoyé* : " . number_format($session->amount, 0, ',', ' ') . " XAF\n" .
                    "💸 *Frais* : " . number_format($session->fees, 0, ',', ' ') . " XAF\n" .
                    "➡️ *Montant final envoyé* : " . number_format($session->amount_send, 0, ',', ' ') . "\n\n"
                );

            case 'preview':
                // Confirmer le transfert
                $session->update([
                    'step' => 'send'
                ]);
                return $this->send($session->wa_id,
                    "Voulez-vous *confirmer* ? (oui / non)"
                );

            case 'send':
                if (strtolower($text) !== "oui") {
                    $session->update(['step' => 'start']);
                    return $this->send($session->wa_id, "❌ Transfert annulé.");
                }
                $res=$this->executeTransfer($session);
                return $this->send($session->wa_id,$res['message']);

        }
    }

    private function processInviteStep(WhatsappSession $session, $text)
    {
        $text = trim($text);

        switch ($session->step) {

            // ----------------------
            // CHOIX DU MODE
            // ----------------------
            case 'awaiting_traitement':

                $session->update(['step' => 'choose_mode']);

                return $this->send($session->wa_id,
                    "Mode de transfert :\n"
                    . "1️⃣ Mobile Money\n"
                    . "2️⃣ Bank"
                );

            case 'choose_mode':

                if (!in_array($text, ['1', '2'])) {
                    return $this->send($session->wa_id,
                        "❌ Choix invalide.\nRépondez par 1️⃣ ou 2️⃣."
                    );
                }

                $mode = $text === "1" ? "mobile" : "bank";

                $session->update([
                    'transfer_mode' => $mode,
                    'step' => 'enter_country'
                ]);

                return $this->send($session->wa_id,
                    "🌍 Entrez le *code ISO2* du pays (ex : CM, CI, SN)."
                );

            // ----------------------
            // CHOIX DU PAYS
            // ----------------------
            case 'enter_country':

                $iso2 = strtoupper($text);

                $response = Http::get(
                    config('whatsapp.wtc_url') . "api/cities/$iso2/codeiso"
                );

                if ($response->failed()) {
                    return $this->send($session->wa_id,
                        "❌ Code pays invalide. Réessayez."
                    );
                }

                $res = $response->json();

                if (!isset($res['data']) || empty($res['data'])) {
                    return $this->send($session->wa_id,
                        "❌ Aucun résultat pour ce pays."
                    );
                }

                $cities = $res['data'];
                $countryId = $cities[0]['country_id'] ?? null;

                $list = "";
                foreach ($cities as $city) {
                    $list .= "{$city['id']}. {$city['name']}\n";
                }

                $session->update([
                    'country'   => $iso2,
                    'countryId' => $countryId,
                    'cities'    => $cities,
                    'step'      => 'select_city'
                ]);

                return $this->send($session->wa_id,
                    "📍 *Villes disponibles :*\n\n$list\n\nEntrez l’ID de la ville."
                );

            // ----------------------
            // CHOIX VILLE
            // ----------------------
            case 'select_city':

                $city = collect(json_decode($session->cities,true))
                    ->firstWhere('id', (int)$text);

                if (!$city) {
                    return $this->send($session->wa_id,
                        "❌ Ville invalide. Entrez un ID valide."
                    );
                }

                $session->update([
                    'cityId' => $city['id'],
                    'step'   => 'guess_enter_sender'
                ]);

                return $this->send($session->wa_id,
                    "👤 *Informations Expéditeur*\n\n"
                    . "Format obligatoire :\n"
                    . "1-Nom;\nPrénom;\nCodePays;\nEmail;\nTéléphone;\nAdresse;\nProfession;\nDateNaissance(YYYY-MM-DD);\nSexe(M/F);\nCivilité;\nTypePièce;\nNuméroPièce;\nDateExpiration"
                );

            // ----------------------
            // SAISIE EXPÉDITEUR
            // ----------------------
            case 'guess_enter_sender':

                $parts = array_map('trim', explode(';', $text));

                if (count($parts) < 13) {
                    return $this->send($session->wa_id,
                        "❌ Format invalide.\nVeuillez respecter exactement le format demandé."
                    );
                }

                $senderData = [
                    'first_name' => $parts[0],
                    'last_name'  => $parts[1],
                    'country'    => $parts[2],
                    'email'      => $parts[3],
                    'phone'      => $parts[4],
                    'address'    => $parts[5],
                    'occupation' => $parts[6],
                    'birth_date' => $parts[7],
                    'gender'     => $parts[8],
                    'civility'   => $parts[9],
                    'id_type'    => $parts[10],
                    'id_number'  => $parts[11],
                    'id_expiry'  => $parts[12],
                ];

                $session->update([
                    'sender' => $senderData,
                    'step'   => 'guess_enter_beneficiary'
                ]);

                return $this->send($session->wa_id,
                    "👥 *Entrez maintenant les informations du bénéficiaire* (même format)."
                );
            // ----------------------

            case 'guess_enter_beneficiary':

                $parts = array_map('trim', explode(';', $text));

                if (count($parts) < 9) {
                    return $this->send($session->wa_id,
                        "❌ Format invalide.\n"
                        . "Nom;\nPrénom;\nCodePays;\nTéléphone;\nAdresse;\nProfession;\nDateNaissance;\nSexe;\nCivilité"
                    );
                }

                $beneficiary = [
                    'first_name' => $parts[0],
                    'last_name'  => $parts[1],
                    'country'    => $parts[2],
                    'phone'      => $parts[3],
                    'address'    => $parts[4],
                    'occupation' => $parts[5],
                    'birth_date' => $parts[6],
                    'gender'     => $parts[7],
                    'civility'   => $parts[8],
                ];

                $isMobile = ($session->transfer_mode === "mobile");
                $endpoint = $isMobile ? "operatorslists" : "banklists";

                $resp_operators = Http::get(
                    config('whatsapp.wtc_url') . "api/$endpoint/{$session->countryId}"
                )->json();

                $operators = $resp_operators['data'] ?? [];

                if (empty($operators)) {
                    return $this->send($session->wa_id, "❌ Aucun opérateur ou banque disponible pour ce pays.");
                }

                $list = "";
                foreach ($operators as $op) {
                    $list .= "{$op['id']}. {$op['name']}\n";
                }

                $session->update([
                    'beneficiary' => $beneficiary,
                    'operators'   => $operators,
                    'step'        => 'enter_operator'
                ]);

                return $this->send(
                    $session->wa_id,
                    $isMobile
                        ? "📱 *Opérateurs Mobile Money disponibles :*\n$list\nEntrez le numéro correspondant à l'opérateur choisi."
                        : "🏦 *Banques disponibles :*\n$list\nEntrez le numéro correspondant à la banque choisie."
                );


            case 'enter_operator':

                if (!ctype_digit($text)) {
                    return $this->send($session->wa_id, "❌ Veuillez entrer un numéro valide correspondant à un opérateur/banque.");
                }

                $operators = collect(
                    is_string($session->operators) ? json_decode($session->operators, true) : $session->operators
                );

                $selected = $operators->firstWhere('id', (int)$text);

                if (!$selected) {
                    return $this->send($session->wa_id, "❌ Aucun opérateur ou banque ne correspond à ce numéro.");
                }

                $session->update([
                    'operator_id'   => $selected['id'],
                    'operator_name' => $selected['name'],
                    'step'          => $session->transfer_mode === 'bank' ? 'enter_bank_details' : 'enter_mobile_details'
                ]);

                return $this->send(
                    $session->wa_id,
                    $session->transfer_mode === 'bank'
                        ? "🏦 Entrez le numéro de compte et le SWIFT/IFSC séparés par un `;` (Ex: 123456789;BNCCMCMX)"
                        : "📱 Entrez le numéro Mobile Money du bénéficiaire."
                );


            case 'enter_bank_details':

                $parts = array_map('trim', explode(';', $text));

                if (count($parts) < 2) {
                    return $this->send($session->wa_id,
                        "❌ Format invalide.\nNuméroCompte;SWIFT/IFSC"
                    );
                }

                $session->update([
                    'accountNumber' => $parts[0],
                    'swiftCode'     => $parts[1],
                    'step'          => 'enter_amount'
                ]);

                return $this->send($session->wa_id,
                    "💰 Entrez le *montant à envoyer* (XAF)."
                );

            case 'enter_mobile_details':

                if (!preg_match('/^[0-9]{9,15}$/', $text)) {
                    return $this->send($session->wa_id,
                        "❌ Numéro invalide. Réessayez."
                    );
                }

                $session->update([
                    'wallet_number' => $text,
                    'step'          => 'enter_amount'
                ]);

                return $this->send($session->wa_id,
                    "💰 Entrez le *montant à envoyer* (XAF)."
                );

            case 'enter_amount':

                if (!is_numeric($text) || $text <= 0) {
                    return $this->send($session->wa_id,
                        "❌ *Montant invalide.*"
                    );
                }

                $amount = (float) $text;

                $res = Http::get(
                    config('whatsapp.wtc_url') . "api/tauxechanges/{$session->countryId}"
                )->json();

                if (!isset($res['data'])) {
                    return $this->send($session->wa_id,
                        "❌ Erreur lors du calcul des frais."
                    );
                }

                $fees = $res['data'];

                $calcul = $this->calculTaux(
                    $amount,
                    $fees['taux_xaf_usd'] ?? 0,
                    $fees['taux_country'] ?? 0,
                    $fees['rate'] ?? 0
                );

                $session->update([
                    'amount'       => $amount,
                    'fees'         => $calcul['rate'],
                    'amount_send'  => $calcul['amount_send'],
                    'step'         => 'enter_token' // ⬅️ IMPORTANT
                ]);

                // 👁️ ON AFFICHE LE PREVIEW AVANT LE TOKEN
                return $this->sendPreview($session);




            case 'enter_token':

                $token = trim($text);

                $response = Http::get(
                    config('whatsapp.wtc_url') . "api/verify-token/$token"
                );

                if ($response->failed()) {
                    return $this->send($session->wa_id,
                        "❌ Token invalide ou expiré."
                    );
                }

                $data = $response->json()['data'];

                // 🔒 Vérification du montant
                if ((float)$data['amount'] !== (float)$session->amount) {
                    return $this->send($session->wa_id,
                        "❌ Le montant ne correspond pas à la souscription web."
                    );
                }

                $session->update([
                    'web_transaction_id' => $data['id'],
                    'step' => 'preview_confirm'
                ]);

                return $this->send($session->wa_id,
                    "✅ Token validé.\n\nConfirmez-vous le transfert ?\nRépondez par *oui* ou *non*."
                );

            case 'preview_confirm':

                $reply = strtolower(trim($text));

                if (!in_array($reply, ['oui', 'non'])) {
                    return $this->send($session->wa_id,
                        "❓ Répondez uniquement par *oui* ou *non*."
                    );
                }

                if ($reply === 'non') {
                    $session->update(['step' => 'start']);
                    return $this->send($session->wa_id,
                        "❌ Transfert annulé."
                    );
                }

                $session->update(['step' => 'send']);
                return $this->executeTransferGuess($session);

        }
    }

    private function processRateCalculator($session, $text)
    {
        switch ($session->step) {
            case 'awaiting_traitement':
                $session->update(['step' => 'enter_country_rate']);
                return $this->send($session->wa_id,
                    "🌍 *Calculateur de taux*\n\n"
                    . "Veuillez entrer le *code ISO2 du pays* destinataire (ex : CI, SN, US, FR)."
                );
            case 'enter_country_rate':

                $iso2 = strtoupper(trim($text));

                // API pour vérifier le pays via la route cities/<iso>/code (existe déjà dans ton système)
                $res = Http::withToken($session->token)
                    ->get(config('whatsapp.wtc_url') . "api/cities/$iso2/codeiso");

                // Si l’API retourne une erreur, pays invalide
                if ($res->failed()) {
                    return $this->send($session->wa_id,
                        "❌ *Code pays invalide.*\nVeuillez entrer un code comme: CI, SN, BE, FR, US."
                    );
                }
                $data=$res['data'];
                $country_id=$data[0]['country_id'];
                // Pays valide
                $session->update([
                    'countryId' => $country_id,
                    'step'         => 'enter_amount_rate'
                ]);

                return $this->send($session->wa_id,
                    "💵 Entrez maintenant le *montant en XAF* à transférer."
                );
            case 'enter_amount_rate':

                if (!is_numeric($text) || $text <= 0) {
                    return $this->send($session->wa_id,
                        "❌ *Montant invalide.*\nVeuillez entrer un nombre supérieur à 0."
                    );
                }

                $amount = (float) $text;

                // Appel API taux
                $countryIso = $session->countryId;

                $resFees = Http::get(config('whatsapp.wtc_url') . "api/tauxechanges/$countryIso");

                if ($resFees->failed()) {
                    return $this->send($session->wa_id,
                        "❌ Impossible de récupérer les taux. Réessayez plus tard."
                    );
                }

                $fees = $resFees->json()['data'];

                // Calcul taux
                $result = $this->calculTaux(
                    $amount,
                    $fees['taux_xaf_usd'] ?? 0,
                    $fees['taux_country'] ?? 0,
                    $fees['rate'] ?? 0
                );

                $session->update([
                    'amount'       => $amount,
                    'fees'         => $result['rate'],
                    'amount_send'  => $result['amount_send'],
                    'step'         => 'start' // retour au début
                ]);

                return $this->send($session->wa_id,
                    "📊 *Résultat du calcul :*\n\n"
                    . "💰 *Montant en XAF* : " . number_format($amount, 0, ',', ' ') . " XAF\n"
                    . "💸 *Frais* : " . number_format($result['rate'], 0, ',', ' ') . " XAF\n"
                    . "➡️ *Montant reçu* : " . number_format($result['amount_send'], 2, ',', ' ') . " {$countryIso}\n\n"
                    . "Tapez *menu* pour revenir au début."
                );

        }

    }
    private function sendPreview(WhatsappSession $session)
    {
        $sender = json_decode($session->sender,true);
        $benef  =json_decode( $session->beneficiary,true);
        logger('preview calll');
        $modeInfo = $session->transfer_mode === 'bank'
            ? "🏦 *Banque* : {$session->bank_name}\n"
            . "🔢 *Compte* : {$session->accountNumber}\n"
            . "🌐 *SWIFT* : {$session->swiftCode}\n"
            : "📱 *Mobile* : {$session->wallet_number}\n";

        $body =
            "📄 *PRÉVISUALISATION DU TRANSFERT*\n\n"
            . "👤 *Expéditeur*\n"
            . "{$sender['first_name']} {$sender['last_name']}\n\n"

            . "👥 *Bénéficiaire*\n"
            . "{$benef['first_name']} {$benef['last_name']}\n\n"

            . "🌍 *Pays* : {$session->country}\n"
            . "🏙️ *Ville ID* : {$session->city}\n\n"

            . "💳 *Mode* : " . strtoupper($session->transfer_mode) . "\n"
            . $modeInfo . "\n"

            . "💰 *Montant envoyé* : "
            . number_format($session->amount, 0, ',', ' ')
            . " XAF\n"

            . "💸 *Frais* : "
            . number_format($session->fees, 0, ',', ' ')
            . " XAF\n"

            . "➡️ *Montant reçu* : "
            . number_format($session->amount_send, 0, ',', ' ')
            . "\n\n"

            . "✅ Confirmez-vous ce transfert ?\n"
            . "Répondez par *oui* ou *non*";

        return $this->send($session->wa_id, $body);
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
        // Préparer les données
        $data = [
            'customer_id'    => $session->user_id,
            'sender_id'      => $session->senderId,
            'beneficiary_id' => $session->beneficiaryId,
            'amount'         => $session->amount,
            'rate'           => $session->fees ?? 0,
            'account_number' => $session->accountNumber ?? null,
            'origin_fond'    => $session->origin_fond,
            'relaction'      => $session->relaction,
            'motif'          => $session->motif,
            'comment'        => $session->comment ?? null,
            'bank_name'      => $session->operators['name'] ?? null,
            'operator_id'    => $session->operator_id,
            'wallet'         => "WACEPAY",
            'type'           => "B",
            'country_id'     => $session->countryId,
            'city_id'        => $session->cityId ?? null,
            'swiftCode'      => $session->swiftCode ?? null,
            'ifscCode'       => $session->ifscCode ?? null,
            'total_amount'   => $session->amount_send
        ];

        // Choix du endpoint selon le mode
        $endpoint = ($session->transfer_mode === 'mobile') ? 'mobile' : 'bank';

        // Appel API
        $response = Http::withToken($session->token)
            ->post(config('whatsapp.wtc_url') . "v2/transferts/$endpoint", $data);

        // Vérifier succès
        if ($response->failed()) {
            logger("Erreur transfert : ", $response->json());
            return [
                'status'  => 'error',
                'message' => 'Impossible de réaliser le transfert. Réessayez plus tard.'
            ];
        }

        // Retour JSON de l’API
        $res = $response->json();

        // Mettre à jour la session
        $session->update(['step' => 'completed']);

        return $res;
    }
    private function executeTransferGuess(WhatsappSession $session)
    {
        // Préparer les données
        $data = [
            'customer_id'    => $session->user_id,
            'sender_id'      => $session->senderId,
            'beneficiary_id' => $session->beneficiaryId,
            'amount'         => $session->amount,
            'rate'           => $session->fees ?? 0,
            'account_number' => $session->accountNumber ?? null,
            'origin_fond'    => $session->origin_fond,
            'relaction'      => $session->relaction,
            'motif'          => $session->motif,
            'comment'        => $session->comment ?? null,
            'bank_name'      => $session->operators['name'] ?? null,
            'operator_id'    => $session->operator_id,
            'wallet'         => "WACEPAY",
            'type'           => "B",
            'country_id'     => $session->countryId,
            'city_id'        => $session->cityId ?? null,
            'swiftCode'      => $session->swiftCode ?? null,
            'ifscCode'       => $session->ifscCode ?? null,
            'total_amount'   => $session->amount_send
        ];

        // Choix du endpoint selon le mode
        $endpoint = ($session->transfer_mode === 'mobile') ? 'mobile' : 'bank';

        // Appel API
        $response = Http::withToken($session->token)
            ->post(config('whatsapp.wtc_url') . "v2/transferts/$endpoint", $data);

        // Vérifier succès
        if ($response->failed()) {
            logger("Erreur transfert : ", $response->json());
            return [
                'status'  => 'error',
                'message' => 'Impossible de réaliser le transfert. Réessayez plus tard.'
            ];
        }

        // Retour JSON de l’API
        $res = $response->json();

        // Mettre à jour la session
        $session->update(['step' => 'completed']);

        return $res;
    }

}
