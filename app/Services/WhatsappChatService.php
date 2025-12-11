<?php


namespace App\Services;


use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsappChatService
{
    protected static function endpoint(): string
    {
        return "https://graph.facebook.com/v17.0/".env('WHATSAPP_PHONE_ID')."/messages";
    }

    protected static function token(): string { return env('WHATSAPP_TOKEN'); }


    public static function sendInteractiveMenu(string $to)
    {
        // Pour simplicité on envoie un text list-style. Tu peux remplacer par interactive list/buttons.
        $body = "Bienvenue sur MonService 👋\nChoisissez :\n- Transfert\n- Retrait\n- Solde\nRépondez par le mot correspondant.";
        return self::sendText($to, $body);
    }
}
