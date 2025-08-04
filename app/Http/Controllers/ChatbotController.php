<?php


namespace App\Http\Controllers;


use App\Models\ChatbotSession;
use App\Services\WetransfertChatService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ChatbotController extends Controller
{
    protected $wetransfertService;

    /**
     * ChatbotController constructor.
     * @param $wetransfertService
     */
    public function __construct(WetransfertChatService $wetransfertService)
    {
        $this->wetransfertService = $wetransfertService;
    }
    public function verify(Request $request)
    {
        $verifyToken = '1gXBHRVWa2kMkKufWJp1zvl3SES15hqAFqlgkKGqrROIEju6E2cyUe8mtUKm5YUY'; // doit être le même que celui entré sur Meta
        logger($request->input('hub.verify_token'));
        if ($request->input('hub_verify_token') === $verifyToken) {
            return response($request->input('hub_challenge'), 200);
        }

        return response('Invalid verify token', 403);
    }

    public function handle(Request $request)
    {
        // Tu reçois ici les messages ou statuts
        Log::info('WABA Webhook Received:', $request->all());

        return response('EVENT_RECEIVED', 200);
    }
    public function webhook(Request $request)
    {

        $entry = $request->input('entry')[0] ?? null;
        $changes = $entry['changes'][0]['value'] ?? null;
        $messages = $changes['messages'][0] ?? null;
        $contacts = $changes['contacts'][0] ?? null;

/*        if (!$messages) {
            return response()->json(['status' => 'no message']);
        }

        $from = $messages['from'];
        $msgType = $messages['type'];
        $interactiveData = $messages['interactive'] ?? null;
        $msgText = $msgType === 'text' ? strtolower(trim($messages['text']['body'])) : null;
        $name = $contacts['profile']['name'] ?? 'Client';

        $chatbotsession = ChatbotSession::firstOrCreate(['user_number' => $from,'is_delete'=>false]);
        $interactiveId = null;
        if ($msgType === 'interactive') {
            if ($interactiveData['type'] === 'button_reply') {
                $interactiveId = $interactiveData['button_reply']['id'];
            } elseif ($interactiveData['type'] === 'list_reply') {
                $interactiveId = $interactiveData['list_reply']['id'];
            }
        }
        if ($chatbotsession->staring_step = 'start') {
            if ($chatbotsession->staring_menu = 'welcome') {
                $salutations = ['bonjour', 'salut', 'hello', 'bonsoir', 'yo'];
                if (in_array($msgText, $salutations)) {
                    $this->sendWelcomeTemplate($from, $name);
                    $chatbotsession->staring_menu = 'waiting_menu';
                } else {
                    $this->sendMessage($from, "Bonjour 👋. Envoyez *bonjour* pour commencer.");
                }
            } else {
                $this->sendMenuInteractive($from);
                $chatbotsession->staring_step = 'service';
            }
        } else {
            if ($chatbotsession->service == 'wetransfertcash') {
                $this->wetransfertService->starting($from, $msgText, $chatbotsession, $interactiveId);
            }
        }

        $chatbotsession->save();*/
        return response()->json(['status' => 'ok']);
    }

    private function sendWelcomeTemplate($to, $name)
    {
        $phoneNumberId = config('app.WHATSAPP_PHONE_NUMBER_ID');
        $tokenID = config('app.WHATSAPP_TOKEN');
        $url = "https://graph.facebook.com/v23.0/" . $phoneNumberId . "/messages";
        $response = Http::withToken($tokenID)->post($url, [
            "messaging_product" => "whatsapp",
            "to" => $to,
            "type" => "template",
            "template" => [
                "name" => "welcome",
                "language" => ["code" => "fr"],
                "components" => [
                    [
                        "type" => "body",
                        "parameters" => [
                            ["type" => "text", "text" => $name],
                            ["type" => "text", "text" => "AGENSIC SOLUTION"]
                        ]
                    ]
                ]
            ]
        ]);
    }

    private function sendMessage($to, $text)
    {
        $phoneNumberId = config('app.WHATSAPP_PHONE_NUMBER_ID');
        $tokenID = config('app.WHATSAPP_TOKEN');
        $url = "https://graph.facebook.com/v23.0/" . $phoneNumberId . "/messages";
        Http::withToken($tokenID)->post($url, [
            "messaging_product" => "whatsapp",
            "to" => $to,
            "type" => "text",
            "text" => ["body" => $text]
        ]);
    }

    private function sendMenuInteractive($to)
    {
        $phoneNumberId = config('app.WHATSAPP_PHONE_NUMBER_ID');
        $tokenID = config('app.WHATSAPP_TOKEN');
        $url = "https://graph.facebook.com/v18.0/" . $phoneNumberId . "/messages";

        $response = Http::withToken($tokenID)->post($url, [
            "messaging_product" => "whatsapp",
            "to" => $to,
            "type" => "interactive",
            "interactive" => [
                "type" => "button",
                "body" => [
                    "text" => "👋 Bonjour *{{nom}}* ! Veuillez choisir un service ?"
                ],
                "action" => [
                    "buttons" => [
                        [
                            "type" => "reply",
                            "reply" => [
                                "id" => "wetransfertcach",
                                "title" => "💳 We transfert cash"
                            ]
                        ],
                        [
                            "type" => "reply",
                            "reply" => [
                                "id" => "filifilo",
                                "title" => "📤 Filifilo"
                            ]
                        ],
                        [
                            "type" => "reply",
                            "reply" => [
                                "id" => "facture",
                                "title" => "📄 Facture"
                            ]
                        ]
                    ]
                ]
            ]
        ]);
    }
}
