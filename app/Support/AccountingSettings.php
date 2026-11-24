<?php

namespace App\Support;

use App\Models\Account;
use App\Models\AccountingSetting;
use Illuminate\Support\Facades\Cache;

/**
 * الحسابات المحاسبية كما رُبطت في شاشة «الحسابات المحاسبية».
 *
 * **لماذا؟**
 *
 * جدول `accounting_settings` مزروع بثمانين مفتاحاً ومربوط بشجرة الحسابات،
 * لكن لا سطر واحد في النظام يقرأه: كل مولّدات القيود تستعمل ثوابت
 * `App\Constants\SystemAccountIds` المكتوبة في الشيفرة. فما يضبطه المحاسب
 * في الشاشة لا أثر له، والقيود تذهب إلى الحسابات المكتوبة برمجياً.
 *
 * هذه نقطة القراءة الموحّدة:
 *
 *     AccountingSettings::id('purchases', SystemAccountIds::PURCHASES)
 *     AccountingSettings::account('vat_paid', SystemAccountIds::VAT_PAID)
 *
 * الترتيب: الإعداد المربوط والمفعَّل ← وإلا الثابت البرمجي كاحتياط. فلا
 * ينكسر شيء قبل أن يربط المحاسب مفتاحاً، ويسري ربطه فور حفظه.
 */
class AccountingSettings
{
    /** مفتاحٌ قديم لم يعد يُكتب — يمحوه `forget()` إن بقي من نسخةٍ سابقة */
    protected const CACHE_KEY = 'accounting_settings.map';

    /** @var array<string, int>|null */
    protected static ?array $resolved = null;

    /**
     * خريطة المفتاح ← معرّف الحساب، للمفاتيح المفعَّلة المربوطة وحدها.
     *
     * تُقرأ من الجدول مرّةً لكل طلب — **لا ذاكرة مؤقّتة بين الطلبات**.
     * كانت تُحفظ ساعةً في `accounting_settings.map`، فكلُّ تغييرٍ للجدول لا
     * يمرّ بـ`forget()` — استيرادُ نسخة قاعدة، تعديلٌ مباشر — يُبقي القيودَ
     * ساعةً على الخريطة القديمة: فاتورةٌ رُحّلت تكلفتُها إلى «الخصوم» (٢)
     * وضريبتُها إلى «الأصول» (١) والجدولُ يقول ٥٠ و٣٨٣٥. والجدول نحو ثمانين
     * صفّاً، فقراءتُه أرخص من أن يُخاطَر لأجلها بقيدٍ على حسابٍ خاطئ.
     *
     * @return array<string, int>
     */
    public static function map(): array
    {
        if (self::$resolved !== null) {
            return self::$resolved;
        }

        try {
            self::$resolved = AccountingSetting::query()
                ->where('is_active', true)
                ->whereNotNull('account_id')
                ->pluck('account_id', 'key')
                ->map(fn ($id) => (int) $id)
                ->all();
        } catch (\Throwable $e) {
            // قاعدة بيانات غير مهيّأة: يعمل النظام بالثوابت كما كان
            self::$resolved = [];
        }

        return self::$resolved;
    }

    /** يُنسى الحل المخزَّن — يُستدعى بعد حفظ الإعدادات */
    public static function forget(): void
    {
        self::$resolved = null;
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * معرّف الحساب المضبوط لمفتاح — أو الاحتياط البرمجي.
     *
     * @param  int|null  $fallback  ثابت `SystemAccountIds` المقابل
     */
    public static function id(string $key, ?int $fallback = null): ?int
    {
        return self::map()[$key] ?? $fallback;
    }

    /**
     * سجلّ الحساب نفسه — أو null إن لم يوجد لا في الإعدادات ولا في الاحتياط.
     */
    public static function account(string $key, ?int $fallback = null): ?Account
    {
        $id = self::id($key, $fallback);

        return $id ? Account::find($id) : null;
    }

    /** هل رُبط هذا المفتاح فعلاً في الشاشة؟ */
    public static function isMapped(string $key): bool
    {
        return isset(self::map()[$key]);
    }
}
