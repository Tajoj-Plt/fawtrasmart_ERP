<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware اختياري للتحقق من العميل
 * إذا كان العميل مسجل دخوله، يتم تحميل بياناته
 * إذا لم يكن مسجل، يمرر الطلب للضيف
 */
class OptionalAuthenticateClient
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // محاولة تحميل العميل إذا كان مسجل دخوله
        if (Auth::guard('client')->check()) {
            $client = Auth::guard('client')->user();
            
            // التحقق من أن العميل هو عميل متجر إلكتروني
            if ($client && $client->is_store_client) {
                // تعيين العميل في الـ request للاستخدام في الـ controller
                $request->merge(['authenticated_client' => $client]);
            }
        }

        return $next($request);
    }
}
