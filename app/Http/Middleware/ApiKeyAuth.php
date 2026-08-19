<?php

namespace App\Http\Middleware;

use App\Models\ApiClient;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiKeyAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header('X-Api-Key');

        if (! $key) {
            return response()->json(['message' => 'Falta el encabezado X-Api-Key.'], 401);
        }

        $client = ApiClient::findByPlainTextKey($key);

        if (! $client) {
            return response()->json(['message' => 'API key inválida o inactiva.'], 401);
        }

        $client->update(['last_used_at' => now()]);

        $request->attributes->set('api_client', $client);

        return $next($request);
    }
}
