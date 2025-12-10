<?php


namespace App\Services;

use App\Services\ContactService;

use App\Models\MessageLog;

class MessageRouterService
{
    protected $whatsapp;
    protected $contactService;

    public function __construct(WhatsappService $whatsapp, ContactService $contactService)
    {
        $this->whatsapp = $whatsapp;
        $this->contactService = $contactService;
    }
    public function sendMessageTemplate($phone, $message,$templateId, $apiKeyId = null)
    {
        $type = $this->contactService->canSendSessionMessage($phone) ? 'text' : 'template';
        $response = null;
        $error = null;

        try {

                $response = $this->whatsapp->sendTemplate($phone, $templateId, [$message]);

        } catch (\Exception $e) {
            $error = $e->getMessage();
            logger($error);
        }

        MessageLog::create([
            'api_key_id' => $apiKeyId,
            'phone' => $phone,
            'type' => $type,
            'status' => $error ? 'failed' : 'sent',
            'response' => $response,
            'error' => $error
        ]);

        return $response;
    }
    public function sendMessage($phone, $message, $apiKeyId = null)
    {
        $type = $this->contactService->canSendSessionMessage($phone) ? 'text' : 'template';
        $response = null;
        $error = null;

        try {
            if ($type === 'text') {
                $response = $this->whatsapp->sendTemplate($phone, 'generic_message', [$message]);
               // $response = $this->whatsapp->sendText($phone, $message);
            } else {
                $response = $this->whatsapp->sendTemplate($phone, 'generic_message', [$message]);
            }
        } catch (\Exception $e) {
            $error = $e->getMessage();
            logger($error);
        }

        MessageLog::create([
            'api_key_id' => $apiKeyId,
            'phone' => $phone,
            'type' => $type,
            'status' => $error ? 'failed' : 'sent',
            'response' => $response,
            'error' => $error
        ]);

        return $response;
    }
}
