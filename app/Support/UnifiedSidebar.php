<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * القائمة الجانبية الموحّدة — تفعيلٌ على مستوى الدور.
 *
 * الأصل في هذا النظام أن لكل وحدةٍ قائمتَها الجانبية: شاشات المشتريات
 * تعرض `purchases::layouts.sidebar`، وشاشات العملاء `client::…`، وهكذا.
 * فمن كان عمله داخل وحدةٍ واحدة رأى ما يخصّه فقط، ومن كان عمله عبر
 * الوحدات لزمه أن يعود إلى الترويسة كلّما أراد الانتقال.
 *
 * فصار الخيار للدور: عمود `roles.unified_sidebar`. مطفأً — وهو الافتراض —
 * لا يتبدّل شيء. مشتعلاً تُعرَض القائمة الجامعة `layouts.sidebar` في كل
 * شاشة، فينتقل صاحب الدور من أي صفحةٍ إلى أي صفحةٍ يملك صلاحيتها.
 *
 * **المصدر `users.role_id` لا أدوار Spatie**: فحص الصلاحيات نفسه يقرأ
 * هذا العمود (`User::hasRolePermission`)، فلو قرأنا هنا مصدراً آخر لأمكن
 * أن تُفعَّل القائمة لدورٍ غير الدور الذي تُشتقّ منه بنودها. ولمن كان
 * `role_id` عنده فارغاً يُرجَع إلى عمود `role` النصي — مرآةُ اسم الدور
 * التي تحفظها `RolesController::update`.
 *
 * لا كاش خارج الطلب: القيمة تُقرأ مرّةً واحدة لكل مستخدمٍ في الطلب
 * الواحد، فتبديل المفتاح في الإعدادات يسري على الطلب التالي مباشرةً بلا
 * مسح كاش — وهو ما يتوقّعه من يبدّله ثم يحدّث الصفحة.
 */
class UnifiedSidebar
{
    /** القائمة الجامعة — المسوَّرة بالصلاحيات بنداً بنداً. */
    public const UNIFIED_VIEW = 'layouts.sidebar';

    /** @var array<int|string, bool> */
    private static array $memo = [];

    /** هل الدور الأساسي للمستخدم الحالي مفعَّلٌ عليه التوحيد؟ */
    public static function enabled(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        $key = $user->getKey() ?? 'guest';

        if (array_key_exists($key, self::$memo)) {
            return self::$memo[$key];
        }

        return self::$memo[$key] = self::readFlag($user);
    }

    /**
     * القالب الذي يجب تضمينه: الجامع عند التفعيل، وقالب الوحدة عدا ذلك.
     *
     * تُستدعى من `master` كل وحدة بدل اسم قالبها المكتوب صراحةً.
     */
    public static function view(string $moduleView): string
    {
        return self::enabled() ? self::UNIFIED_VIEW : $moduleView;
    }

    /** لأجل الاختبارات ولمن بدّل دور المستخدم داخل الطلب نفسه. */
    public static function forget(): void
    {
        self::$memo = [];
    }

    private static function readFlag($user): bool
    {
        /*
            العمود يأتي بترحيلٍ جديد، والقالب يُعرَض قبل أن يُشغَّل الترحيل
            على النسخ المرفوعة بالـFTP (انظر شاشة «تحديث النظام»). فسقوط
            الاستعلام يعني «لا توحيد» لا صفحةَ خطأ.
        */
        try {
            $query = DB::table('roles');

            if (! empty($user->role_id)) {
                $query->where('id', $user->role_id);
            } elseif (! empty($user->role)) {
                $query->where('name', $user->role);
            } else {
                return false;
            }

            return (bool) $query->value('unified_sidebar');
        } catch (\Throwable) {
            return false;
        }
    }
}
