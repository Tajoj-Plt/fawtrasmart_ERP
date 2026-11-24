<?php

namespace Modules\Purchases\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Purchases\Services\PurchaseSettings;
use Symfony\Component\HttpFoundation\Response;

/**
 * يمنع الوصول إلى شاشات يحكمها خيارٌ في «خيارات المشتريات».
 *
 * نظير `EnsureModuleEnabled` لكن على مستوى الخيار لا الوحدة: تلك تُطفئ
 * وحدةً كاملة من «شاشة التطبيقات»، وهذه تُطفئ باباً واحداً داخل وحدة عاملة
 * — الإشعار المدين مثلاً.
 *
 * يُستعمل بالاسم الكامل على مجموعة المسارات:
 *
 *     Route::middleware(EnsurePurchaseSettingEnabled::class.':enable_debit_notice')
 *
 * إخفاء الرابط من القائمة وحده ليس منعاً: من يحفظ الرابط يفتحه، فالمنع
 * هنا لا في القالب.
 */
class EnsurePurchaseSettingEnabled
{
    public function handle(Request $request, Closure $next, string $setting): Response
    {
        if (PurchaseSettings::enabled($setting)) {
            return $next($request);
        }

        $message = __('purchase_settings.messages.'.$setting.'_disabled');

        // مفتاحٌ بلا رسالة خاصّة يخرج باسمه حرفياً من `__()`، وهو أسوأ من
        // رسالة عامّة — فتلك تُفهَم وهذا لا يُفهَم
        if ($message === 'purchase_settings.messages.'.$setting.'_disabled') {
            $message = __('purchase_settings.messages.setting_disabled');
        }

        if ($request->expectsJson()) {
            return response()->json(['success' => false, 'message' => $message], 403);
        }

        return redirect()->route('purchases.dashboard')->with('error', $message);
    }
}
