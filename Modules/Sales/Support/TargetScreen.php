<?php

namespace Modules\Sales\Support;

use App\Models\CollectionsTarget;
use App\Models\CollectionsTargetAchievement;
use App\Models\CollectionsTargetCommissionTier;
use App\Models\SalesTarget;
use App\Models\SalesTargetAchievement;
use App\Models\SalesTargetCommissionTier;
use App\Models\TargetSetting;

/**
 * وصفُ شاشة الأهداف — أهدافُ المبيعات وأهدافُ التحصيلات في مكانٍ واحد.
 *
 * **لماذا؟**
 *
 * الشاشتان توأمان تامّان: نفس الحقول، ونفس مستويات العمولة، ونفس دورة
 * الإسناد والتقرير والتسوية. ولا يفترقان إلّا في ثلاثة أشياء: الجدول الذي
 * يُقرأ منه، ومصدرُ احتساب الإنجاز، وأسماءُ المسارات.
 *
 * ورغم ذلك كانت هناك **عشرُ** قوالب مكرّرة حرفياً — `targets/*.blade.php`
 * و`targets/collections/*.blade.php` — يتباعد ما فيها مع أول تعديل يُجرى
 * على إحداهما دون الأخرى. وكان `create.blade.php` في المبيعات ٢٣٤٨٠ بايت
 * ونظيره في التحصيلات ٢٣٥٠٢: نسختان من ملفٍ واحد بفارق اثنين وعشرين بايتاً.
 *
 * صار الفرقُ بينهما بياناً هنا، والقوالب واحدةً تُمرَّر إليها هذه الخريطة.
 * إضافةُ نوعِ هدفٍ ثالث لاحقاً — «أهداف الزيارات» مثلاً — مدخلٌ واحد في
 * `MAP` لا عشرةُ قوالب.
 *
 * **لماذا `mode` نصّاً لا صنفاً؟** لأنّ المسارات تمرّره في الوسائط وتقرؤه
 * القوالب، والنصّ أهون في الاثنين — والتحقّق منه في `config()` يمنع أن
 * يتسرّب مفتاحٌ غير معروف.
 *
 * @see \Modules\Sales\Http\Controllers\SalesTargetController
 * @see \Modules\Sales\Http\Controllers\CollectionsTargetController
 */
class TargetScreen
{
    public const SALES = 'sales';

    public const COLLECTIONS = 'collections';

    /**
     * ما يفترق فيه النوعان — ولا شيء غيره.
     *
     * `lang` بادئةُ النصوص في `sales_targets.php`؛ و`settings_type` نوعُ
     * الإعدادات في `target_settings`؛ و`routes` أسماءُ المسارات كما هي
     * مسجّلة في `Modules/Sales/routes/web.php`.
     */
    private const MAP = [
        self::SALES => [
            'lang' => 'sales_targets.sales',
            'icon' => 'target',
            'settings_type' => TargetSetting::TYPE_SALES,
            'model' => SalesTarget::class,
            'achievement' => SalesTargetAchievement::class,
            'tier' => SalesTargetCommissionTier::class,
            'foreign_key' => 'sales_target_id',
            'log_module' => 'SalesTargets',
            'guide' => 'sales-targets',

            // أعمدةُ التفصيل التي تسبق «الإنجاز» في التقرير. هي وحدها ما
            // كان يفرّق قالبَي النتائج، فصارت بياناً هنا وقالباً واحداً هناك.
            //   key    مفتاح الصفّ في `$results`
            //   total  مفتاح الإجمالي في `$summary`
            //   text   بادئة النصّ في `sales_targets.report.cards|table`
            //   minus  أيُعرَض بإشارة سالبة؟ (ما يُخصم من الإجمالي)
            'metrics' => [
                ['key' => 'gross_sales', 'total' => 'total_gross_sales', 'text' => 'gross',
                    'icon' => 'shopping-cart', 'tone' => 'info', 'minus' => false, 'hint' => 'gross_hint'],
                ['key' => 'returns', 'total' => 'total_returns', 'text' => 'returns',
                    'icon' => 'rotate-ccw', 'tone' => 'danger', 'minus' => true, 'hint' => null],
                ['key' => 'credit_notes', 'total' => 'total_credit_notes', 'text' => 'credit_notes',
                    'icon' => 'file-minus', 'tone' => 'warning', 'minus' => true, 'hint' => null],
            ],

            // عمود الإنجاز نفسه — «صافي المبيعات» هنا و«إجمالي التحصيل» هناك
            'achieved' => ['text' => 'net', 'hint' => 'net_hint'],
            'routes' => [
                'index' => 'sales-targets.index',
                'create' => 'sales-targets.create',
                'store' => 'sales-targets.store',
                'show' => 'sales-targets.show',
                'edit' => 'sales-targets.edit',
                'update' => 'sales-targets.update',
                'destroy' => 'sales-targets.destroy',
                'report' => 'sales-targets.report',
                'generate' => 'sales-targets.generate-report',
                'settle' => 'sales-targets.settle',
                'settings' => 'sales-targets.settings',
            ],
        ],

        self::COLLECTIONS => [
            'lang' => 'sales_targets.collections',
            'icon' => 'trending-up',
            'settings_type' => TargetSetting::TYPE_COLLECTIONS,
            'model' => CollectionsTarget::class,
            'achievement' => CollectionsTargetAchievement::class,
            'tier' => CollectionsTargetCommissionTier::class,
            'foreign_key' => 'collections_target_id',
            'log_module' => 'CollectionsTargets',
            'guide' => 'sales-collections-targets',

            'metrics' => [
                ['key' => 'invoice_payments', 'total' => 'total_invoice_payments', 'text' => 'invoice_payments',
                    'icon' => 'credit-card', 'tone' => 'info', 'minus' => false, 'hint' => 'invoice_payments_hint'],
                ['key' => 'receipts', 'total' => 'total_receipts', 'text' => 'receipts',
                    'icon' => 'inbox', 'tone' => 'success', 'minus' => false, 'hint' => 'receipts_hint'],
            ],

            'achieved' => ['text' => 'collected', 'hint' => 'collected_hint'],
            'routes' => [
                'index' => 'collections-targets.index',
                'create' => 'collections-targets.create',
                'store' => 'collections-targets.store',
                'show' => 'collections-targets.show',
                'edit' => 'collections-targets.edit',
                'update' => 'collections-targets.update',
                'destroy' => 'collections-targets.destroy',
                'report' => 'collections-targets.report',
                'generate' => 'collections-targets.generate-report',
                'settle' => 'collections-targets.settle',
                // شاشة الإعدادات واحدة للنوعين، تفصلهما بوسيط `type`
                'settings' => 'sales-targets.settings',
            ],
        ],
    ];

    /**
     * وصفُ النوع كاملاً، مضافاً إليه ما يُشتقّ منه: النصوص المترجَمة
     * ووسيطُ شاشة الإعدادات.
     *
     * @return array<string, mixed>
     *
     * @throws \InvalidArgumentException نوعٌ غير معروف — خطأُ برمجةٍ يُكشف
     *                                   عند أول نداء لا يُصمت
     */
    public static function config(string $mode): array
    {
        if (! isset(self::MAP[$mode])) {
            throw new \InvalidArgumentException("نوع هدف غير معروف: {$mode}");
        }

        $c = self::MAP[$mode];
        $lang = $c['lang'];

        return $c + [
            'mode' => $mode,
            'title' => __($lang.'.title'),
            'subtitle' => __($lang.'.subtitle'),
            'one' => __($lang.'.one'),
            'new' => __($lang.'.new'),
            'edit_title' => __($lang.'.edit'),
            'empty' => __($lang.'.empty'),
            'empty_hint' => __($lang.'.empty_hint'),
            'achievement_source' => __($lang.'.achievement_source'),
            'settings_url' => route($c['routes']['settings'], $c['settings_type']),
        ];
    }

    /** الأنواع كلّها — لمن أراد المرور عليها */
    public static function modes(): array
    {
        return array_keys(self::MAP);
    }
}
