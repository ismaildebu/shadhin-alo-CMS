<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // Only frame-ancestors is sent: it does not restrict scripts or
        // styles, so the third-party ad code keeps working. Older browsers
        // that ignore CSP still honour X-Frame-Options.
        $response->headers->set('Content-Security-Policy', "frame-ancestors 'self'");
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');

        return $response;
    }
}
