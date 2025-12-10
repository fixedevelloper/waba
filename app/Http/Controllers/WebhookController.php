<?php


namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Sender;
use App\Models\Message;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;


class WebhookController extends Controller
{
    public function verify(Request $request)
    {
// Verification for Meta webhook (GET)
        $mode = $request->query('hub_mode');
        $token = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');


        if ($mode && $token && $mode === 'subscribe' && $token === env('WHATSAPP_VERIFY_TOKEN')) {
            return response($challenge, 200);
        }
        return response('Forbidden', 403);
    }


    public function receive(Request $request)
    {
        $payload = $request->all();

        // Log pour debug
        Log::info('Webhook WhatsApp reçu', $payload);

        // Extraction sécurisée
        $entry = data_get($payload, 'entry.0.changes.0.value', []);
        $messages = data_get($entry, 'messages', []);
        $contacts = data_get($entry, 'contacts', []);

        foreach ($messages as $msg) {

            // Ne traiter que les messages texte
            if (!isset($msg['text']['body'])) {
                continue;
            }

            $phone = $msg['from'] ?? null;
            $text = data_get($msg, 'text.body');
            $waId = $msg['id'] ?? null;
            $contactName = data_get($contacts, '0.profile.name', 'Unknown');

            DB::transaction(function () use ($phone, $contactName, $msg, $waId, $text) {

                // Upsert sender
                $sender = Sender::firstOrCreate(
                    ['phone' => $phone],
                    ['first_seen' => now(), 'last_seen' => now(), 'name' => $contactName]
                );

                // Mettre à jour le last_seen et éventuellement le nom
                $updateData = ['last_seen' => now()];
                if ($contactName && $sender->name !== $contactName) {
                    $updateData['name'] = $contactName;
                }
                $sender->update($updateData);

                // Stocker le message
                Message::create([
                    'sender_id' => $sender->id,
                    'phone' => $phone,
                    'direction' => 'incoming',
                    'message' => $text,
                    'whatsapp_message_id' => $waId,
                    'raw' => $msg // Assurez-vous que la colonne 'raw' est castée en array/json
                ]);
            });
        }

        return response('EVENT_RECEIVED', 200);
    }
}
