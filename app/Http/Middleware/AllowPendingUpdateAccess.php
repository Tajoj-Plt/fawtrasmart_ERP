<?php

namespace App\Http\Middleware;

use App\Services\Maintenance\SystemUpdateService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * بوّابة شاشة «تحديث النظام»: مفتوحةٌ للزائر ما دام هناك ترحيلٌ معلَّق.
 *
 * **لماذا يُسمح لغير المسجَّل أصلاً؟** لأن المطلوب أن يقود النظامُ إلى شاشة
 * التحديث قبل أي صفحة — **وشاشةُ الدخول من ضمنها**. ولو بقيت الشاشة خلف
 * `auth` لصارت الحلقة مغلقة: الزائر يُحوَّل من الدخول إلى التحديث، ويردّه
 * `auth` إلى الدخول، فلا تُفتَح صفحةٌ في النظام أبداً.
 *
 * **وحدود ما يستطيعه الزائر ضيّقة عمداً:** يرى الشاشة، ويشغّل **الترحيلات**
 * وحدها — وهي بنيويةٌ إلى الأمام فقط. وأمّا مهامّ البيانات («تشغيل» لكل
 * مهمّة، و«التحديث الشامل») فتتطلّب تسجيل الدخول، والمتحكّم يرفضها للزائر.
 * وطلبُ التشغيل محدود المعدّل (`throttle`) في ملفّ المسارات.
 *
 * **وتُغلَق الشاشة من تلقاء نفسها:** متى نُفِّذ آخر ترحيلٍ معلَّق عاد الشرط
 * إلى الكذب، فصارت الشاشة كسائر الشاشات — لا تُفتَح إلا بتسجيل الدخول.
 * فالنافذة مفتوحةٌ بقدر العطل ومدّتَه لا أكثر.
 */
class AllowPendingUpdateAccess
{
    public function __construct(protected SystemUpdateService $updates) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()) {
            return $next($request);
        }

        if ($this->updates->pendingMigrationCount() > 0) {
            return $next($request);
        }

        return $request->expectsJson()
            ? response()->json(['message' => 'Unauthenticated.'], 401)
            : redirect()->route('login');
    }
}
