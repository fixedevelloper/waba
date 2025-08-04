<?php


namespace App\Http\Middleware;


namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;
use Illuminate\Http\Request;

class VerifyCsrfToken extends Middleware
{
    protected function shouldSkipCsrfProtection(Request $request): bool
    {
        return $request->is('webhook/whatsapp') || parent::shouldSkipCsrfProtection($request);
    }
}
