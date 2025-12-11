<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class WhatsappService
{
    protected $baseUrl;
    protected $phoneNumberId;

    public function __construct()
    {
        $this->baseUrl = "https://graph.facebook.com/v17.0/";
        $this->phoneNumberId = config('whatsapp.phone_number_id'); // à définir dans config/whatsapp.php
    }

    public function sendText($to, $text)
    {
        $url = $this->baseUrl . $this->phoneNumberId . '/messages';
        $token = app(WhatsappTokenService::class)->getToken();
        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'text',
            'text' => ['body' => $text]
        ];

        return Http::timeout(30) // 30 secondes au lieu de 10
        ->withToken($token)->post($url, $payload)->json();
    }



    public function sendTemplate($to, $templateName, $variables = [])
    {
        $token = app(WhatsappTokenService::class)->getToken();
        $url = $this->baseUrl . $this->phoneNumberId . '/messages';

        $variables=$variables[0];
        logger($variables);
        $formattedParams=[
            [
                'type' => 'text',
                'parameter_name' => 'account',
                'text' => $variables['account'],
            ],
            [
                'type' => 'text',
                'parameter_name' => 'expeditor',
                'text' => $variables['expeditor'],
            ],
            [
                'type' => 'text',
                'parameter_name' => 'beneficiary',
                'text' => $variables['beneficiary'],
            ],
            [
                'type' => 'text',
                'parameter_name' => 'amount',
                'text' => $variables['amount'],
            ],
            [
                'type' => 'text',
                'parameter_name' => 'country',
                'text' => $variables['country'],
            ],
        ];
        if ($templateName=='wt_tx_failed'){
            $formattedParams[]= [
                'type' => 'text',
                'parameter_name' => 'country_beneficiary',
                'text' => $variables['country_beneficiary'],
            ];
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'template',
            'template' => [
                'name' => $templateName, // Nom exact du template dans WhatsApp
                'language' => ['code' => 'fr'],
                'components' => [
                    [
                        'type' => 'body',
                        'parameters' => $formattedParams
                    ]
                ]
            ]
        ];

        logger($payload);

        return Http::timeout(30)
            ->withToken($token)
            ->post($url, $payload)
            ->json();
    }

}
