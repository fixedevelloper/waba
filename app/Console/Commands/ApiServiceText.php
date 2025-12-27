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
        $session = WhatsappSession::find(56);
        if (!$session) {
            logger("Session non trouvée");
            return;
        }

        $this->executeTransferGuess($session);
        logger("Session mise à jour avec succès");
    }
    private function executeTransferGuess(WhatsappSession $session)
    {
        $sender      = json_decode($session->sender, true);
        $beneficiary = json_decode($session->beneficiary, true);

        $data = [
            "amount"        => $session->amount,
            "rate"          => $session->fees ?? 0,
            "total_amount"  => $session->amount + ($session->fees ?? 0),
            "comment"       => "Transfert WhatsApp",
            "origin_fond"   => $session->origin_fond_id,
            "motif"         => $session->motif_id,
            "relation"      => $session->relation_id,
            "country_id"    => $session->countryId,
            "city_id"       => $session->cityId,
            "operator_id"   => $session->operator_id,
            "account_number"=> $session->accountNumber,
            "swiftCode"     => $session->swiftCode,
            "ifscCode"      => $session->ifscCode,
            "wallet"        => $session->transfer_mode === 'mobile' ? 'MobileWallet' : 'BankWallet',

            "sender" => [
                "customer_id"          => 13,
                "type"          => "P",
                "firstname"     => $sender['first_name'],
                "lastname"      => $sender['last_name'],
                "email"         => $sender['email'],
                "phone"         => $sender['phone'],
                "address"       => $sender['address'],
                "dateOfBirth"   => $sender['birth_date'],
                "numeropiece"   => $sender['id_number'],
                "typeidentification" => $sender['id_type'],
                "expireddatepiece"   => $sender['id_expiry'],
                "country"       => $sender['country'],
                "city"          => $session->city,
                "gender"        => $sender['gender'],
                "civility"      => $sender['civility'],
                "occupation"   => $sender['occupation'],
            ],

            "beneficiary" => [
                "customer_id"          => 13,
                "type"              => "P",
                "firstname"         => $beneficiary['first_name'],
                "lastname"          => $beneficiary['last_name'],
                "email"             => $beneficiary['email'] ?? null,
                "phone"             => $beneficiary['phone'],
                "dateOfBirth"       => $beneficiary['birth_date'],
                "document_number"   => $beneficiary['id_number'],
                "document_id"       => $beneficiary['id_type'],
                "document_expired"  => $beneficiary['id_expiry'],
                "countryIsoCode"    => $beneficiary['country'],
                "account_number"    => $session->accountNumber,
                "ifsc_code"         => $session->ifscCode,
                "swift_code"        => $session->swiftCode,
            ]
        ];

        $endpoint = $session->transfer_mode === 'mobile' ? 'mobile' : 'bank';

        $response = Http::withToken($session->token)
            ->post(config('whatsapp.wtc_url') . "api/transactions/$endpoint", $data);

        if ($response->failed()) {
            logger()->error("Erreur transfert", [
                'session_id' => $session->id,
                'response'   => $response->json()
            ]);

           logger(
                $session->wa_id.
                "❌ *Transfert échoué*\nUne erreur est survenue. Veuillez réessayer plus tard."
            );
        }

        $res = $response->json();
        logger($res);
        // Exemple de champs retournés
        $transactionId = $res['data']['transaction_id'] ?? 'N/A';
        $status        = $res['data']['status'] ?? 'EN COURS';

        $session->update([
            'step'           => 'completed',
            'transaction_id' => $transactionId,
            'api_response'   => json_encode($res),
        ]);

        logger(
            $session->wa_id.
            "✅ *Transfert effectué avec succès*\n\n"
            . "🧾 Référence : *{$transactionId}*\n"
            . "💰 Montant : *{$session->amount} XAF*\n"
            . "💸 Frais : *{$session->fees} XAF*\n"
            . "📌 Statut : *{$status}*\n\n"
            . "Merci d’avoir utilisé *AGENSIC SOLUTION* 🙏"
        );
    }


}
