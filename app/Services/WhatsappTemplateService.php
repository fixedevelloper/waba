<?php

namespace App\Services;

use App\Models\WhatsappTemplate;
use Illuminate\Support\Facades\Http;

class WhatsappTemplateService
{
    protected $baseUrl;
    protected $phoneNumberId;

    public function __construct()
    {
        $this->baseUrl = "https://graph.facebook.com/v17.0/";
        $this->phoneNumberId = config('whatsapp.phone_number_id');
    }

    /**
     * Créer un template sur WhatsApp et stocker en base
     */
    public function createTemplate($userId, $name, $category, $body, $language = 'fr')
    {
        $token = app(WhatsappTokenService::class)->getToken();

        // Envoi à WhatsApp
        $res = Http::withToken($token)->post($this->baseUrl . $this->phoneNumberId . '/message_templates', [
            'name' => $name,
            'category' => strtoupper($category),
            'language' => $language,
            'components' => [
                ['type' => 'BODY', 'text' => $body]
            ]
        ])->json();

        // Stockage en base
        $status = $res['error'] ?? null ? 'rejected' : 'pending';
        return WhatsappTemplate::create([
            'user_id' => $userId,
            'name' => $name,
            'category' => $category,
            'body' => $body,
            'language' => $language,
            'status' => $status
        ]);
    }

    /**
     * Envoyer un template
     */
    public function sendTemplate($to, $templateName, $variables = [])
    {
        $token = app(WhatsappTokenService::class)->getToken();
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

        return Http::withToken($token)->post($this->baseUrl . $this->phoneNumberId . '/messages', $payload)->json();
    }
}
