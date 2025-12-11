<?php

use App\Http\Controllers\ApiController;
use App\Http\Controllers\WhatsappTemplateController;
use App\Http\Controllers\WhatsappWebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/webhook/whatsapp', [WhatsappWebhookController::class, 'handle']);

Route::middleware(['api_key'])->group(function () {
    Route::post('/send-message', [ApiController::class, 'sendMessage']);
    Route::get('/messages', [ApiController::class, 'getMessages']);
    Route::get('/senders', [ApiController::class, 'getSenders']);

    // Créer un template
    Route::post('/templates', [WhatsappTemplateController::class, 'store']);

    // Lister les templates de l'utilisateur
    Route::get('/templates', [WhatsappTemplateController::class, 'index']);

    // Tester l'envoi d'un template
    Route::post('/templates/test', [WhatsappTemplateController::class, 'test']);
});
