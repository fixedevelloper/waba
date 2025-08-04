<?php

use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Exceptions\JWTException;

public function render($request, Throwable $exception)
{
    if ($exception instanceof TokenExpiredException) {
        return response()->json(['error' => 'Token expiré'], 401);
    }

    if ($exception instanceof TokenInvalidException) {
        return response()->json(['error' => 'Token invalide'], 401);
    }

    if ($exception instanceof JWTException) {
        return response()->json(['error' => 'Token absent ou non valide'], 401);
    }

    return parent::render($request, $exception);
}
