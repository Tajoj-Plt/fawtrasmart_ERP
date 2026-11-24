<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware للتحقق من مصادقة العميل
 * يستخدم guard خاص بالعملاء منفصل عن المستخدمين العاديين
 */
class AuthenticateClient
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // التحقق من وجود توكن صالح للعميل
        if (!Auth::guard('client')->check()) {
            return response()->json([
                'success' => false,
                'message' => 'غير مصرح لك بالوصول. يرجى تسجيل الدخول.',
                'error' => 'Unauthenticated',
            ], 401);
        }

        // التحقق من أن العميل نشط (غير محذوف)
        $client = Auth::guard('client')->user();
        
        if (!$client) {
            return response()->json([
                'success' => false,
                'message' => 'حساب العميل غير موجود.',
                'error' => 'Client not found',
            ], 401);
        }

        // التحقق من أن العميل هو عميل متجر إلكتروني
        if (!$client->is_store_client) {
            return response()->json([
                'success' => false,
                'message' => 'هذا الحساب غير مسجل كعميل متجر إلكتروني.',
                'error' => 'Not a store client',
            ], 403);
        }

        return $next($request);
    }
}
