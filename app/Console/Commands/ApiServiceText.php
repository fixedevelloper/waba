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
        $session = WhatsappSession::find(31);
        if (!$session) {
            logger("Session non trouvée");
            return;
        }

        $this->executeTransferGuess($session);
        logger("Session mise à jour avec succès");
    }
    private function executeTransferGuess(WhatsappSession $session)
    {
        $sender = json_decode($session->sender,true);
        $beneficiary  =json_decode( $session->beneficiary,true);

        $data = [
            "amount" => $session->amount,
            "rate" => $session->fees ?? 0,
            "total_amount" => $session->amount,
            "comment" => "Paiement facture",
            "account_number" => $session->accountNumber ?? null,
            "wallet" => "BankWallet",
            "origin_fond" => $session->origin_fond,
            "motif" => $session->motif,
            "relaction" => $session->relaction,
            'country_id'     => $session->countryId,
            'city_id'        => $session->cityId ?? null,
            "operator_id" => $session->operator_id,
            "bank_name" => "Banque Centrale",
            'swiftCode'      => $session->swiftCode ?? null,
            'ifscCode'       => $session->ifscCode ?? null,

            "sender" => [
                "customer_id" => 13,
                "type" => "P",
                "firstname" => $sender['first_name'] ,
                "lastname" => $sender['last_name'],
                "email" => $sender['email'],
                "address" => $sender['address'],
                "dateOfBirth" =>$sender['birth_date'],
                "expireddatepiece" => $sender['id_expiry'],
                "typeidentification" => $sender['id_type'],
                "numeropiece" => $sender['id_number'],
                "country" => $sender['country'],
                "civility" => $sender['civility'],
                "gender" => $sender['gender'],
                "city" => $session->city ,
                "occupation" => $sender['occupation'],
                "phone" => $sender['phone']
            ],

            "beneficiary" => [
                "customer_id" => 13,
                "type" => "P", // P ou B
                "email" => $beneficiary['email'],
                "phone" => $beneficiary['phone'],
                "dateOfBirth" => $beneficiary['birth_date'],
                "document_expired" => $beneficiary['id_expiry'],
                "countryIsoCode" => $beneficiary['country'],
                "document_number" => $beneficiary['id_number'],
                "document_id" => $beneficiary['id_type'],

                "account_number" => $session->accontNumber,
                "ifsc_code" => $session->ifsc_code,
                "swift_code" => $session->swift_code,

                "first_name" => $beneficiary['first_name'],
                "last_name" => $beneficiary['last_name']
            ]
        ];


        // Choix du endpoint selon le mode
        $endpoint = ($session->transfer_mode === 'mobile') ? 'mobile' : 'bank';

        // Appel API
        $response = Http::withToken($session->token)
            ->post(config('whatsapp.wtc_url') . "api/transactions/$endpoint", $data);

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
