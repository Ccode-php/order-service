<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class VerifyToken
{
    public function handle(Request $request, Closure $next)
    {
        $header = $request->header('Authorization');

        if (!$header || !str_starts_with($header, 'Bearer ')) {
            return response()->json(['error' => 'Token required'], 401);
        }

        $token = str_replace('Bearer ', '', $header);

        try {
            $publicKey = file_get_contents(env('PUBLIC_KEY'));

            $decoded = JWT::decode($token, new Key($publicKey, 'RS256'));

            /**
             * MUHIM JOY ✅
             * Token ichidagi user ma’lumotini request’ga qo‘shamiz
             */
            $request->merge([
                'auth_user' => [
                    'id'    => $decoded->sub ?? null,
                    'email' => $decoded->email ?? null,
                    'name'  => $decoded->name ?? null,
                ]
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Invalid token'
            ], 401);
        }

        return $next($request);
    }
}


