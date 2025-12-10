<?php


namespace App\Http\Controllers;


use App\Models\ApiKey;
use App\Services\ContactService;
use App\Services\MessageRouterService;
use Illuminate\Http\Request;
use App\Models\Sender;
use App\Models\Message;


class ApiController extends Controller
{
    protected $router;
    protected $contact;

    public function __construct(MessageRouterService $router, ContactService $contact)
    {
        $this->router = $router;
        $this->contact = $contact;
    }

    public function sendMessage(Request $request)
    {
        $request->validate([
            'phone'     => 'required|string',
            'template'  => 'required|string',
            'variables' => 'required|array'
        ]);

        // Vérification de la clé API
        $apiKey = ApiKey::where('key', $request->header('X-API-KEY'))->first();
        if (!$apiKey) {
            return response()->json([
                'error' => true,
                'message' => 'Invalid API Key'
            ], 401);
        }

        // Récupération des variables
        $variables = $request->get('variables'); // array

        // Envoi du template
        $response = $this->router->sendMessageTemplate(
            $request->phone,
            $variables,               // <-- ici : array
            $request->template,
            $apiKey->id
        );

        // Enregistrement de l'interaction du contact
        $this->contact->registerInteraction($request->phone);

        return response()->json($response);
    }




    public function getMessages(Request $request)
    {
        $phone = $request->query('phone');
        $query = Message::query();
        if ($phone) $query->where('phone', $phone);
        $data = $query->orderBy('created_at','desc')->paginate(50);
        return response()->json($data);
    }


    public function getSenders()
    {
        return response()->json(Sender::orderBy('last_seen','desc')->paginate(50));
    }
}
