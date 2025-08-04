<?php


use App\Http\Controllers\ChatbotController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Middleware\JwtMiddleware;

Route::post('webhook', [ChatbotController::class, 'webhook']);

Route::get('/webhook/whatsapp', [ChatbotController::class, 'verify']);
Route::post('/webhook/whatsapp', [ChatbotController::class, 'handle']);
