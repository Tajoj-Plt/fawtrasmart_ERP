<?php

namespace Modules\RentalManagement\Support;

use App\Models\TaxSitting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * إعدادات إدارة العقارات — مفتاح/قيمة في `re_settings`.
 *
 * `DEFINITIONS` هي المصدر الوحيد: شاشة الإعدادات تُبنى منها، والتحقّق
 * يُشتقّ منها، والقيم الافتراضية تُقرأ منها. فإضافةُ إعدادٍ سطرٌ واحد هنا.
 */
class RealEstateSettings
{
    public const CACHE_KEY = 'realestate.settings';

    /**
     * @var array<string, array{label:string, group:string, type:string, default:mixed, hint?:string, account_type?:string, parent?:bool, max?:int}>
     */
    public const DEFINITIONS = [
        // ═══════ الحسابات ═══════
        // `account_type`: النوع المقبول للحساب. `parent`: يُقبل حسابٌ أب (لا يُرحَّل عليه مباشرةً).
        'owner_payables_account_id' => [
            'label' => 'حساب مستحقات ملاك العقار', 'group' => 'accounting', 'type' => 'account', 'default' => null,
            'account_type' => 'liabilities', 'parent' => true,
            'hint' => 'حسابٌ أب في الخصوم تتفرّع منه الحسابات الجارية للملّاك. إن تُرك فارغاً أُنشئ تلقائياً باسم «مستحقات ملاك العقار».',
        ],
        'rent_revenue_account_id' => [
            'label' => 'حساب إيرادات الإيجار', 'group' => 'accounting', 'type' => 'account', 'default' => null,
            'account_type' => 'revenues',
            'hint' => 'إيجار المباني المملوكة للمنشأة (أو التي لا مالك لها). أمّا المبنى المُدار لمالكٍ خارجي فإيجاره يُقيَّد في الحساب الجاري للمالك.',
        ],
        'management_commission_account_id' => [
            'label' => 'حساب إيرادات إدارة الأملاك', 'group' => 'accounting', 'type' => 'account', 'default' => null,
            'account_type' => 'revenues',
            'hint' => 'إيراد المكتب من عمولة إدارة الأملاك المخصومة من الإيجار المحصَّل لحساب المالك.',
        ],
        'deposit_liability_account_id' => [
            'label' => 'حساب تأمينات المستأجرين', 'group' => 'accounting', 'type' => 'account', 'default' => null,
            'account_type' => 'liabilities',
            'hint' => 'التأمين التزامٌ يُردّ لا إيراد — يُقيَّد هنا عند قبضه ويُعكَس عند تسويته.',
        ],
        'deposit_forfeit_account_id' => [
            'label' => 'حساب إيرادات خصم التأمينات', 'group' => 'accounting', 'type' => 'account', 'default' => null,
            'account_type' => 'revenues',
            'hint' => 'ما يُخصم من تأمين المستأجر لصالح المنشأة عند تسوية التأمين.',
        ],
        'cheque_clearing_account_id' => [
            'label' => 'حساب دفعات الشيكات تحت التحصيل', 'group' => 'accounting', 'type' => 'account', 'default' => null,
            'account_type' => 'liabilities',
            'hint' => 'وسيط: يُقيَّد له الشيك عند استلامه من المستأجر، ويُقفل عند تحصيله (بقيد الدفعة) أو ارتجاعه.',
        ],
        'maintenance_expense_account_id' => [
            'label' => 'حساب مصروفات صيانة العقارات', 'group' => 'accounting', 'type' => 'account', 'default' => null,
            'account_type' => 'expenses',
            'hint' => 'الصيانة التي تتحمّلها المنشأة، وصيانة المباني المملوكة لها.',
        ],
        'post_payments' => [
            'label' => 'ترحيل الدفعات محاسبياً', 'group' => 'accounting', 'type' => 'bool', 'default' => true,
            'hint' => 'عند تعطيله تُسجَّل الدفعات «غير مرحّلة»: بلا قيد يومية ولا حركة في دفتر المالك.',
        ],
        'default_tax_id' => [
            'label' => 'الضريبة الافتراضية للعقود التجارية', 'group' => 'accounting', 'type' => 'tax', 'default' => null,
            'hint' => 'من ضرائب النظام (الإعدادات ← الضرائب). ضريبة الإيجار في المبنى المُدار تُقيَّد لحساب المالك، وفي المبنى المملوك في حساب ضريبة المنشأة.',
        ],
        'commission_taxable' => [
            'label' => 'احتساب ضريبة على عمولة المكتب', 'group' => 'accounting', 'type' => 'bool', 'default' => false,
            'hint' => 'تُضاف ضريبة مخرجات على عمولة إدارة الأملاك بنسبة الضريبة الافتراضية أعلاه (أو الضريبة الشاملة للنظام) وتُخصم مع العمولة من نصيب المالك.',
        ],

        // ═══════ العقود ═══════
        'require_contract_approval' => [
            'label' => 'اعتماد العقد قبل سريانه', 'group' => 'contracts', 'type' => 'bool', 'default' => true,
            'hint' => 'عند تعطيله يسري العقد مباشرةً عند الحفظ لمن يملك صلاحية الإنشاء.',
        ],
        'auto_update_unit_status' => [
            'label' => 'تحديث حالة الوحدة آلياً مع العقد', 'group' => 'contracts', 'type' => 'bool', 'default' => true,
            'hint' => 'تصير الوحدة «مؤجرة» عند سريان العقد و«شاغرة» عند انتهائه أو إلغائه.',
        ],
        'default_payment_frequency' => [
            'label' => 'دورية السداد الافتراضية', 'group' => 'contracts', 'type' => 'select', 'default' => 'monthly',
            'options' => 'PAYMENT_FREQUENCIES',
        ],
        'contract_expiry_alert_days' => [
            'label' => 'التنبيه قبل انتهاء العقد (يوم)', 'group' => 'contracts', 'type' => 'number', 'default' => 60,
        ],
        'default_renewal_increase' => [
            'label' => 'نسبة الزيادة الافتراضية عند التجديد %', 'group' => 'contracts', 'type' => 'number', 'default' => 0, 'max' => 100,
        ],

        // ═══════ التحصيل ═══════
        'payment_due_alert_days' => [
            'label' => 'التنبيه قبل استحقاق الدفعة (يوم)', 'group' => 'collection', 'type' => 'number', 'default' => 5,
        ],
        'overdue_grace_days' => [
            'label' => 'مهلة السماح قبل اعتبار الدفعة متأخرة (يوم)', 'group' => 'collection', 'type' => 'number', 'default' => 0,
        ],
        'allow_advance_payment' => [
            'label' => 'السماح بدفعة مقدّمة غير موزّعة', 'group' => 'collection', 'type' => 'bool', 'default' => false,
            'hint' => 'عند تعطيله لا يُقبل مبلغٌ يتجاوز رصيد الاستحقاقات المفتوحة للعقد.',
        ],

        // ═══════ التشغيل ═══════
        'long_vacancy_days' => [
            'label' => 'تُعدّ الوحدة شاغرة طويلاً بعد (يوم)', 'group' => 'operations', 'type' => 'number', 'default' => 60,
        ],
        'listing_default_days' => [
            'label' => 'مدة العرض الافتراضية (يوم)', 'group' => 'operations', 'type' => 'number', 'default' => 90,
        ],
        'maintenance_sla_hours' => [
            'label' => 'المهلة المستهدفة لإنجاز الصيانة (ساعة)', 'group' => 'operations', 'type' => 'number', 'default' => 72,
        ],
    ];

    public const GROUPS = [
        'accounting' => ['label' => 'الربط المحاسبي', 'icon' => 'dollar-sign'],
        'contracts' => ['label' => 'العقود والتجديد', 'icon' => 'file-text'],
        'collection' => ['label' => 'الدفعات والتحصيل', 'icon' => 'credit-card'],
        'operations' => ['label' => 'العروض والصيانة والتشغيل', 'icon' => 'sliders'],
    ];

    /** @return array<string, mixed> */
    public static function all(): array
    {
        $stored = Cache::remember(self::CACHE_KEY, 600, function () {
            try {
                if (! Schema::hasTable('re_settings')) {
                    return [];
                }

                return DB::table('re_settings')->pluck('value', 'key')->all();
            } catch (Throwable $e) {
                return [];
            }
        });

        $values = [];

        foreach (self::DEFINITIONS as $key => $definition) {
            $values[$key] = self::cast($definition['type'], $stored[$key] ?? null, $definition['default']);
        }

        return $values;
    }

    public static function get(string $key, $default = null)
    {
        return self::all()[$key] ?? $default;
    }

    public static function enabled(string $key): bool
    {
        return (bool) self::get($key, false);
    }

    /** الضريبة الافتراضية للعقود التجارية من ضرائب النظام، وإلا الضريبة الشاملة للنظام. */
    public static function defaultTax(): ?TaxSitting
    {
        try {
            $id = self::get('default_tax_id');

            return ($id ? TaxSitting::find($id) : null) ?? TaxSitting::comprehensiveDefault();
        } catch (Throwable $e) {
            return null;
        }
    }

    public static function defaultTaxRate(): float
    {
        return (float) (self::defaultTax()?->tax ?? 0);
    }

    /** نسبة ضريبة عمولة المكتب — صفر ما لم يُفعَّل «احتساب ضريبة على عمولة المكتب». */
    public static function commissionTaxRate(): float
    {
        return self::enabled('commission_taxable') ? max(0.0, self::defaultTaxRate()) : 0.0;
    }

    /**
     * يكتب مفتاحاً واحداً من الشيفرة. `save()` لنموذج الإعدادات كاملاً: تعدّ
     * كل مفتاحٍ منطقي غائبٍ عن الطلب «معطَّلاً»، فلا تصلح لكتابةٍ جزئية.
     */
    public static function put(string $key, $value): void
    {
        if (! isset(self::DEFINITIONS[$key])) {
            return;
        }

        DB::table('re_settings')->updateOrInsert(
            ['key' => $key],
            ['value' => $value === null ? null : (string) $value, 'updated_at' => now(), 'created_at' => now()],
        );

        Cache::forget(self::CACHE_KEY);
    }

    /** يحفظ ما هو معرَّفٌ فقط — أيّ مفتاحٍ غريب في الطلب يُهمَل. */
    public static function save(array $input): void
    {
        foreach (self::DEFINITIONS as $key => $definition) {
            if ($definition['type'] === 'bool') {
                $value = ! empty($input[$key]) ? '1' : '0';
            } elseif ($definition['type'] === 'account') {
                // حقلُ حسابٍ لم يُمسّ أو وصل فارغاً لا يمحو الربط القائم: القيود تتوقّف عليه
                if (empty($input[$key])) {
                    continue;
                }

                $value = (string) (int) $input[$key];
            } elseif (array_key_exists($key, $input)) {
                $value = $input[$key] === '' || $input[$key] === null ? null : (string) $input[$key];
            } else {
                continue;
            }

            DB::table('re_settings')->updateOrInsert(
                ['key' => $key],
                ['value' => $value, 'updated_at' => now(), 'created_at' => now()],
            );
        }

        Cache::forget(self::CACHE_KEY);
    }

    private static function cast(string $type, $value, $default)
    {
        if ($value === null || $value === '') {
            return $default;
        }

        return match ($type) {
            'bool' => (bool) (int) $value,
            'number' => (float) $value,
            'account', 'tax' => (int) $value ?: null,
            default => $value,
        };
    }
}
