<?php


namespace App\Services;


use App\Models\ChatbotSession;
use Illuminate\Support\Facades\Http;

class WetransfertChatService
{

    protected $phoneNumberId;
    protected $tokenID;
    protected $url;

    /**
     * WetransfertChatService constructor.
     */
    public function __construct()
    {
        $this->phoneNumberId = config('app.WHATSAPP_PHONE_NUMBER_ID');
        $this->tokenID = config('app.WHATSAPP_TOKEN');
        $this->url = "https://graph.facebook.com/v23.0/" .$this->phoneNumberId . "/messages";
    }

    public function starting($to, $message, ChatbotSession $chatbotSession, $interactiveId)
    {
        switch ($chatbotSession->service_step) {
            case 'stating':
                $this->sendMessage($to, "💸 Veuillez entrer le nom complet *Bénéficiaire*");
                $chatbotSession->service_step = 'enter_beneficiary';
                break;

            case 'enter_beneficiary':
                $this->sendMessage($to, "💸 Veuillez entrer le pays du *Bénéficiaire* (ex : CM, CI)");
                $chatbotSession->service_step = 'enter_beneficiary_country';
                $chatbotSession->data = array_merge($chatbotSession->data ?? [], [
                    'beneficiary_name' => $message
                ]);
                break;

            case 'enter_beneficiary_country':
                $this->sendMessage($to, "📞 Veuillez entrer le numéro de téléphone du *Bénéficiaire*");
                $chatbotSession->service_step = 'enter_beneficiary_phone';
                $chatbotSession->data = array_merge($chatbotSession->data ?? [], [
                    'beneficiary_country' => $message
                ]);
                break;

            // Ajoute les autres étapes ici...
        }
    }


    private function sendMessage($to, $text)
    {
        Http::withToken($this->tokenID)->post($this->url, [
            "messaging_product" => "whatsapp",
            "to" => $to,
            "type" => "text",
            "text" => ["body" => $text]
        ]);
    }
    private function sendMenuStart($to)
    {
        $response = Http::withToken($this->tokenID)->post($this->url, [
            "messaging_product" => "whatsapp",
            "to" => $to,
            "type" => "interactive",
            "interactive" => [
                "type" => "button",
                "body" => [
                    "text" => "👋 Bienvenue ! Que souhaitez-vous faire ?"
                ],
                "action" => [
                    "buttons" => [
                        [
                            "type" => "reply",
                            "reply" => [
                                "id" => "recharge",
                                "title" => "💳 Recharge votre compte"
                            ]
                        ],
                        [
                            "type" => "reply",
                            "reply" => [
                                "id" => "send_money",
                                "title" => "📤 Envoyer l'argent"
                            ]
                        ],
                        [
                            "type" => "reply",
                            "reply" => [
                                "id" => "return",
                                "title" => "📄 Retour"
                            ]
                        ]
                    ]
                ]
            ]
        ]);

        logger($response->json());
    }
}
