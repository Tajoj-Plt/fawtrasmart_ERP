<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * قنواتُ العمل المسموحة للدور — اليومَ قناةٌ واحدة: «التطبيق وحده».
 *
 * `roles.mobile_only` مطفأً (الافتراض) لا يتبدّل شيء. مشتعلاً يُمنع أصحابُ
 * الدور من **الموقع** وحده، وتبقى واجهةُ التطبيق مفتوحةً لهم كما هي.
 *
 * **المصدر `users.role_id` لا أدوار Spatie** — للسبب نفسِه الذي في
 * {@see UnifiedSidebar}: فحصُ الصلاحيات يقرأ هذا العمود، فقراءةُ مصدرٍ آخر
 * هنا تعني أن يُحجَب دورٌ غيرُ الدور الذي تُشتقّ منه صلاحياتُ الشاشة. ولمن
 * كان `role_id` عنده فارغاً يُرجَع إلى عمود `role` النصّي — مرآةُ اسم الدور
 * التي تحفظها `RolesController`.
 *
 * والقراءةُ في `try/catch`: العمودُ يأتي بترحيلٍ جديد، وصفحةٌ قد تُعرَض قبل
 * أن يُشغَّل الترحيلُ على نسخةٍ مرفوعةٍ بالـFTP. وسقوطُ الاستعلام يعني «لا
 * حجب» لا صفحةَ خطأ — فالمنعُ قرارٌ لا يُتّخذ بالصدفة.
 *
 * ولا كاش خارج الطلب: القيمةُ تُقرأ مرّةً لكلّ مستخدمٍ في الطلب الواحد،
 * فتبديلُ المفتاح يسري على الطلب التالي بلا مسحِ كاش.
 */
class RoleAccess
{
    /** الرسالةُ الواحدة — تُقال عند رفض الدخول وعند إغلاق جلسةٍ قائمة. */
    public const MOBILE_ONLY_NOTICE = 'هذا الحساب يعمل من تطبيق الجوّال فقط، ولا يدخل الموقع. افتح التطبيق وسجّل دخولك منه.';

    /** @var array<int|string, bool> */
    private static array $memo = [];

    /** أدورُ هذا المستخدم محصورٌ في التطبيق؟ */
    public static function mobileOnly($user = null): bool
    {
        $user = $user ?: auth()->user();

        if (! $user) {
            return false;
        }

        $key = $user->getKey() ?? 'guest';

        if (array_key_exists($key, self::$memo)) {
            return self::$memo[$key];
        }

        return self::$memo[$key] = self::readFlag($user);
    }

    /** لأجل الاختبارات ولمن بدّل دور المستخدم داخل الطلب نفسه. */
    public static function forget(): void
    {
        self::$memo = [];
    }

    private static function readFlag($user): bool
    {
        try {
            $query = DB::table('roles');

            if (! empty($user->role_id)) {
                $query->where('id', $user->role_id);
            } elseif (! empty($user->role)) {
                $query->where('name', $user->role);
            } else {
                return false;
            }

            return (bool) $query->value('mobile_only');
        } catch (\Throwable) {
            return false;
        }
    }
}
