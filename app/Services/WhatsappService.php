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
        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => ['code' => 'fr'],
                'components' => [
                    [
                        'type' => 'body',
                        'parameters' => array_map(fn($v) => ['type'=>'text','text'=>$v], $variables)
                    ]
                ]
            ]
        ];
        logger($payload);
        return Http::timeout(30) // 30 secondes au lieu de 10
        ->withToken($token)->post($url, $payload)->json();
    }
}
