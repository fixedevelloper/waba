<?php


namespace App\Services;

use Illuminate\Support\Facades\Http;


class WhatsAppService2
{
    protected $baseUrl;
    protected $phoneNumberId;
    protected $token;
    protected $version;


    public function __construct()
    {
        $this->version = env('WHATSAPP_API_VERSION', 'v17.0');
        $this->phoneNumberId = env('WHATSAPP_PHONE_NUMBER_ID');
        $this->token = env('WHATSAPP_TOKEN');
        $this->baseUrl = "https://graph.facebook.com/{$this->version}/";
    }


    protected function getToken()
    {
        return app(WhatsappTokenService::class)->getToken();
    }

    public function sendText($to, $text)
    {
        $url = $this->baseUrl . $this->phoneNumberId . '/messages';
        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'text',
            'text' => ['body' => $text]
        ];

        return Http::withToken($this->getToken())->post($url, $payload)->json();
    }

    public function sendTemplate($to, $templateName, $variables = [])
    {
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

        return Http::withToken($this->getToken())->post($url, $payload)->json();
    }

}
