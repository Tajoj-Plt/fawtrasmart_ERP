<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class NoCacheHeaders
{
    /**
     * Handle an incoming request.
     * منع التخزين المؤقت للصفحات الديناميكية
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // تطبيق headers منع الكاش فقط على الصفحات HTML
        if ($response->headers->has('Content-Type') &&
            str_contains($response->headers->get('Content-Type'), 'text/html')) {
            $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate, max-age=0');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', 'Sat, 01 Jan 2000 00:00:00 GMT');
        }

        return $response;
    }
}
