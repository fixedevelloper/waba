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
            'phone' => 'required',
            'message' => 'required'
        ]);

        // On enregistre l'interaction si c'est un client existant

        $apy_key=ApiKey::query()->where('key',$request->header('X-API-KEY'))->first();
        $response = $this->router->sendMessage($request->phone, $request->message, $apy_key->id);
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
