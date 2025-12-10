<?php


namespace App\Services;

use App\Models\WhatsappToken;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsappTokenService
{
    public function getToken()
    {
        return WhatsappToken::latest()->first()->access_token;
    }

    public function refreshToken()
    {
        try {
            $response = Http::get("https://graph.facebook.com/v19.0/oauth/access_token", [
                'grant_type' => 'fb_exchange_token',
                'client_id' => env('META_APP_ID'),
                'client_secret' => env('META_APP_SECRET'),
                'fb_exchange_token' => env('WHATSAPP_TEMP_TOKEN'),
            ]);

            if ($response->failed()) {
                Log::error("WhatsApp token refresh failed", $response->json());
                return false;
            }

            $data = $response->json();

            WhatsappToken::create([
                'access_token' => $data['access_token'],
                'expires_at' => now()->addSeconds($data['expires_in']),
            ]);

            return $data['access_token'];

        } catch (\Exception $e) {
            Log::error("WhatsApp token refresh error: ".$e->getMessage());
            return false;
        }
    }
}
