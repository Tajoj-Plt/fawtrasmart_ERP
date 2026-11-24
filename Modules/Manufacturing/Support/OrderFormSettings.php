<?php

namespace Modules\Manufacturing\Support;

use App\Models\ManufacturingGeneralSetting;
use Illuminate\Support\Facades\Schema;

/**
 * إعداداتُ نموذج أمر التصنيع — ما يظهر، وما يُلزَم، وما يُخفى.
 *
 * نموذجُ الأمر كان يعرض كلَّ حقوله وأقسامه دائماً، وأكثرُها لا يلزم كلَّ
 * منشأة: من لا يصنّع لعميلٍ بعينه لا يحتاج حقلَ العميل، ومن لا هالكَ عنده
 * لا يحتاج قسمَه. فصار لكل حقلٍ اختياريٍّ ثلاثُ حالات تُضبط من «الإعدادات
 * العامة للتصنيع»، ولكل قسمٍ إظهارٌ أو إخفاء — والنموذجُ وقواعدُ التحقّق
 * يقرآن من هنا معاً، فلا يُلزِم الخادمُ بحقلٍ لا تعرضه الشاشة.
 *
 * والقسمُ المخفيّ يظهر مع ذلك في أمرٍ يحمل بنوداً فيه — الإخفاءُ لتخفيف
 * الإدخال لا لإخفاء بياناتٍ محفوظة.
 */
final class OrderFormSettings
{
    public const REQUIRED = 'required';

    public const OPTIONAL = 'optional';

    public const HIDDEN = 'hidden';

    /** الحقول القابلة للضبط ⟵ [التسمية، الافتراض] */
    public const FIELDS = [
        'employee_id' => ['الموظف المسؤول', self::REQUIRED],
        'production_material_id' => ['قائمة المواد', self::REQUIRED],
        'production_path_id' => ['مسار الإنتاج', self::REQUIRED],
        'stage' => ['المرحلة في كل بند', self::REQUIRED],
        'storehouse_id' => ['مستودع المواد', self::OPTIONAL],
        'client_id' => ['العميل', self::OPTIONAL],
        'account_id' => ['الحساب', self::OPTIONAL],
    ];

    /** الأقسام القابلة للإخفاء ⟵ التسمية */
    public const SECTIONS = [
        'expenses' => 'المصروفات',
        'manufacturing' => 'عمليات التصنيع',
        'indirect' => 'المصروفات غير المباشرة',
        'endlife' => 'المواد الهالكة',
    ];

    public const MODES = [
        self::REQUIRED => 'يظهر وإلزامي',
        self::OPTIONAL => 'يظهر واختياري',
        self::HIDDEN => 'لا يظهر',
    ];

    /** @var array<string, mixed>|null */
    private static ?array $stored = null;

    public static function field(string $key): string
    {
        $mode = self::stored()['fields'][$key] ?? (self::FIELDS[$key][1] ?? self::OPTIONAL);

        return isset(self::MODES[$mode]) ? $mode : (self::FIELDS[$key][1] ?? self::OPTIONAL);
    }

    public static function visible(string $key): bool
    {
        return self::field($key) !== self::HIDDEN;
    }

    public static function required(string $key): bool
    {
        return self::field($key) === self::REQUIRED;
    }

    public static function section(string $key): bool
    {
        return (bool) (self::stored()['sections'][$key] ?? true);
    }

    /** قاعدةُ الوجوب لحقلٍ في `FormRequest` — `required` أو `nullable`. */
    public static function presence(string $key): string
    {
        return self::required($key) ? 'required' : 'nullable';
    }

    /**
     * ما يُحفظ من طلب شاشة الإعدادات — القيمُ المجهولة تُردّ إلى الافتراض.
     *
     * @param  array<string, mixed>  $fields
     * @param  array<string, mixed>  $sections
     * @return array{fields: array<string, string>, sections: array<string, bool>}
     */
    public static function normalise(array $fields, array $sections): array
    {
        $out = ['fields' => [], 'sections' => []];

        foreach (self::FIELDS as $key => [, $default]) {
            $mode = $fields[$key] ?? $default;
            $out['fields'][$key] = isset(self::MODES[$mode]) ? $mode : $default;
        }

        foreach (array_keys(self::SECTIONS) as $key) {
            $out['sections'][$key] = ! empty($sections[$key]);
        }

        return $out;
    }

    public static function flush(): void
    {
        self::$stored = null;
    }

    /** @return array<string, mixed> */
    private static function stored(): array
    {
        if (self::$stored !== null) {
            return self::$stored;
        }

        try {
            if (! Schema::hasColumn('manufacturing_general_settings', 'order_form_settings')) {
                return self::$stored = [];
            }

            $raw = ManufacturingGeneralSetting::query()->value('order_form_settings');
            $decoded = is_array($raw) ? $raw : json_decode((string) $raw, true);

            return self::$stored = is_array($decoded) ? $decoded : [];
        } catch (\Throwable $e) {
            return self::$stored = [];
        }
    }
}
