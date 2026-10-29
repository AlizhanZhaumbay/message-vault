<?php

namespace App\Http\Middleware;

use App\Models\Message;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOwnerToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $message = $request->route('message');

        abort_unless(
            $message instanceof Message && $message->isOwnedBy($request->header('X-Owner-Token')),
            403,
            'Missing or invalid X-Owner-Token header.',
        );

        return $next($request);
    }
}
