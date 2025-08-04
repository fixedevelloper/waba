<?php

namespace App\Http\Middleware;

use App\Helpers\api\Helpers;
use Closure;
use Exception;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Exceptions\JWTException;

class JwtMiddleware
{
    public function handle($request, Closure $next)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();

            if (!$user) {
                return Helpers::unauthorized(401,'Utilisateur non trouvé');
            }
        } catch (TokenExpiredException $e) {
            return Helpers::unauthorized(401,'Token expiré');
        } catch (TokenInvalidException $e) {
            return Helpers::unauthorized(401,'Token invalide');
        } catch (JWTException $e) {
            return Helpers::unauthorized(401,'Token non fourni');
        }

        return $next($request);
    }
}
