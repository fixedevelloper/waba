<?php

use App\Http\Controllers\ApiController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['api_key'])->group(function () {
    Route::post('/send-message', [ApiController::class, 'sendMessage']);
    Route::get('/messages', [ApiController::class, 'getMessages']);
    Route::get('/senders', [ApiController::class, 'getSenders']);
});

