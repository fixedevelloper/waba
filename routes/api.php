<?php


use App\Http\Controllers\ChatbotController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('webhook', [ChatbotController::class, 'webhook']);

Route::get('/webhook/whatsapp', [ChatbotController::class, 'webhook']);
Route::post('/webhook/whatsapp', [ChatbotController::class, 'webhook']);
