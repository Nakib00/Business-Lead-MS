<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class HandleCorsPreflightRequests
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Handle preflight OPTIONS requests
        if ($request->isMethod('OPTIONS')) {
            return response('', 200)
                ->header('Access-Control-Allow-Origin', $this->getAllowedOrigin($request))
                ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS')
                ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, Accept, X-Requested-With')
                ->header('Access-Control-Allow-Credentials', 'true')
                ->header('Access-Control-Max-Age', '86400');
        }

        return $next($request);
    }

    /**
     * Get the allowed origin based on the request origin
     *
     * @param  \Illuminate\Http\Request  $request
     * @return string
     */
    private function getAllowedOrigin(Request $request)
    {
        $origin = $request->header('Origin');
        $allowedOrigins = [
            'https://hub.desklago.com',
            'http://localhost:3000',
            'http://localhost:5173'
        ];

        if (in_array($origin, $allowedOrigins)) {
            return $origin;
        }

        return $allowedOrigins[0]; // Default to production origin
    }
}
