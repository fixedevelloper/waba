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
        $session = WhatsappSession::find(27);
        if (!$session) {
            logger("Session non trouvée");
            return;
        }
        $beneficiary = [
            "customer_id" => 13,
            "type" => "P",
            "email" => "john.doe@gmail.com",
            "phone" => "+237699112233",
            "dateOfBirth" => "1990-05-15",
            "document_expired" => "2032-01-01",
            "countryIsoCode" => "CM",
            "document_number" => "A12345678",
            "document_id" => "Passport",

            "account_number" => "9876543210",
            "swift_code" => "ECOCCMCX",

            "first_name" => "John",
            "last_name" => "Doe"
        ];
        $beneficiaryB = [
            "customer_id" => 13,
            "type" => "B", // P ou B
            "email" => "contact@techsolutions.cm",
            "phone" => "+237677889900",
            "dateOfBirth" => null,
            "document_expired" => "2030-12-31",
            "countryIsoCode" => "CM",
            "document_number" => "RC123456",
            "document_id" => "RC",

            "account_number" => "1234567890",
            "ifsc_code" => null,
            "swift_code" => "ECOCCMCX",

            "business_name" => "Tech Solutions SARL",
            "business_type" => "IT Services",
            "register_business_date" => "2018-06-20"
        ];

        $senderB = [
            "customer_id" => "13",
            "type" => "B",
            "business_name" => "SmartPay SARL",
            "business_type" => "Fintech",
            "register_business_date" => "2019-06-01",
            "email" => "contact@smartpay.cm",
            "phone" => "+237677889900",
            "address" => "Bonanjo",
            "city" => "Douala",
            "countryIsoCode" => "CM",
            "document_number" => "RC789456",
            "document_id" => "RC",
            "document_expired" => "2035-12-31"
        ];

        $sender = [
            "customer_id" => "13",
            "type" => "P",
            "first_name" => "Jean",
            "last_name" => "Mbarga",
            "email" => "jean.mbarga@gmail.com",
            "phone" => "+237699112233",
            "address" => "Akwa Nord",
            "city" => "Douala",
            "occupation" => "Commerçant",
            "gender" => "M",
            "civility" => "M",
            "dateOfBirth" => "1992-04-10",
            "countryIsoCode" => "CM",
            "document_number" => "A12345678",
            "document_id" => "CNI",
            "document_expired" => "2032-01-01"
        ];

        $data = [
            // ======================
            // MONTANTS
            // ======================
            'amount'        => 1500,
            'rate'          => 25.00,
            'total_amount'  => 1525.00,

            // ======================
            // INFOS TRANSACTION
            // ======================
            'comment'        => 'Paiement fournisseur',
            'account_number' => '123456789012',
            'wallet'         => 'BANK',
            'origin_fond'    => 'Salaire',
            'motif'          => 'Achat marchandises',
            'relaction'      => 'Fournisseur',
            'bank_name'      => 'ECOBANK',
            'swiftCode'      => 'ECOCCMCX',

            // ======================
            // ENTITÉS LIÉES (IDS BDD)
            // ======================
            'country_id'  => 4,
            'city_id'     => 67,
            'operator_id' => 49,

            "sender" => $sender,

            "beneficiary"=> $beneficiary
        ];


        // Choix du endpoint selon le mode
        $endpoint = ($session->transfer_mode === 'mobile') ? 'mobile' : 'bank';

        // Appel API
        $response = Http::withToken($session->token)
            ->post(config('whatsapp.wtc_url') . "api/transactions/$endpoint", $data);
        logger($response);
        logger("Session mise à jour avec succès");
    }


}
