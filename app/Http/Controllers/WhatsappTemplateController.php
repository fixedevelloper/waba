<?php

namespace App\Http\Controllers;

use App\Models\ApiKey;
use App\Models\WhatsappTemplate;
use App\Services\WhatsappTemplateService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WhatsappTemplateController extends Controller
{
    protected $service;

    public function __construct(WhatsappTemplateService $service)
    {
        $this->service = $service;
    }

    // Créer un template
    public function store(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string|unique:whatsapp_templates,name',
                'category' => ['required', Rule::in(['TRANSACTIONAL','MARKETING','UTILITY'])],
                'body' => 'required|string',
                'language' => 'nullable|string|size:2'
            ]);
            $apy_key=ApiKey::query()->where('key',$request->header('X-API-KEY'))->first();
            $template = $this->service->createTemplate(
                $apy_key->user_id,
                $request->name,
                $request->category,
                $request->body,
                $request->language ?? 'fr'
            );

            return response()->json([
                'message' => 'Template créé avec succès et en attente d\'approbation',
                'template' => $template
            ]);
        }catch (\Exception $exception){

            return response()->json([
                'message' => 'Template n a pas ete cree',
                'error'=>$exception->getMessage()
            ]);
        }

    }

    // Lister les templates
    public function index(Request $request)
    {
        $apy_key=ApiKey::query()->where('key',$request->header('X-API-KEY'))->first();
        $templates = WhatsappTemplate::where('user_id', $apy_key->user_id)->get();
        return response()->json($templates);
    }

    // Tester un template
    public function test(Request $request)
    {
        $apy_key=ApiKey::query()->where('key',$request->header('X-API-KEY'))->first();
        $request->validate([
            'template_id' => 'required|exists:whatsapp_templates,id',
            'phone' => 'required|string',
            'variables' => 'nullable|array'
        ]);

        $template = WhatsappTemplate::findOrFail($request->template_id);

        if ($template->status !== 'approved') {
            return response()->json(['error'=>'Template non approuvé'], 403);
        }

        $res = $this->service->sendTemplate($request->phone, $template->name, $request->variables ?? []);

        return response()->json([
            'message' => 'Template envoyé en test',
            'response' => $res
        ]);
    }
}
