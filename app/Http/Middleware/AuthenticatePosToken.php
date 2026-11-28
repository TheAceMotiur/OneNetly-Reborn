<?php

namespace App\Http\Middleware;

use App\Models\PosToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticatePosToken
{
    /**
     * Resolve the restaurant for the POS bearer token.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $plain = $request->bearerToken();

        abort_if($plain === null, 401, 'Missing POS API token.');

        $token = PosToken::findForToken($plain);

        abort_if($token === null, 401, 'Invalid POS API token.');

        $token->forceFill(['last_used_at' => now()])->save();

        $request->attributes->set('posToken', $token);

        return $next($request);
    }
}
