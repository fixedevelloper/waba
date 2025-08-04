<?php


use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Middleware\JwtMiddleware;

Route::post('webhook', [JWTAuthController::class, 'register']);

