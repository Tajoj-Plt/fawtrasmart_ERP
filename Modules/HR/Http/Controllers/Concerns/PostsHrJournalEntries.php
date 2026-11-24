<?php

namespace Modules\HR\Http\Controllers\Concerns;

use App\Models\JournalEntry;
use App\Support\AccountingPeriodGuard;
use App\Support\CurrencySettings;
use Illuminate\Validation\ValidationException;

/**
 * كتابةُ القيود المحاسبية من وحدة الموارد البشرية.
 *
 * السلفُ ورواتبُها تُنشئ قيوداً كما تفعل الفواتير تماماً، لكنها كانت
 * تكتبها بيدها فتسقط منها ضوابطُ الإعدادات التي تلتزمها بقيّةُ الوحدات:
 *
 *   • **الفترة المحاسبية**: خمسةُ مواضع تكتب قيداً بلا فحصِ إقفال، فتُقيَّد
 *     سلفةٌ في فترةٍ أُقفلت وأُصدرت عنها القوائم. والحسابات والمشتريات
 *     والمبيعات كلُّها تمرّ على `AccountingPeriodGuard` قبل الكتابة.
 *
 *   • **العملة**: `'currency' => 'SAR'` مكتوبةً نصّاً في الخمسة. فمن ضبط
 *     عملةَ نظامه على غير الريال يرى مبلغَه بعملته في الشاشة وبالريال في
 *     القيد — ويجمع تقريرُ الأستاذ عملتَين على أنهما واحدة.
 *
 *   • **الفرع**: لا `branch_id` في أيٍّ منها، وعمودُه موجودٌ في الجدول
 *     وتملؤه بقيّةُ الوحدات. فقيودُ السلف لا تظهر في تقريرِ فرعٍ بعينه.
 *
 * وهذا المرجعُ يجمع الثلاثة في موضعٍ واحد، على مثال
 * `Modules\Account\Http\Controllers\Concerns\AccountingOperations`.
 */
trait PostsHrJournalEntries
{
    /**
     * منعُ الكتابة في فترةٍ مقفلة.
     *
     * @param  mixed  $date  تاريخ العملية
     * @param  string  $field  الحقلُ الذي تُعلَّق عليه الرسالة في النموذج
     *
     * @throws ValidationException
     */
    protected function guardAccountingPeriod($date, string $field = 'date'): void
    {
        $reason = AccountingPeriodGuard::blockReason($date);

        if ($reason !== null) {
            throw ValidationException::withMessages([$field => $reason]);
        }
    }

    /**
     * إنشاءُ قيدٍ بضوابط الإعدادات: عملةُ النظام وفرعُ المستخدم وفحصُ الفترة.
     *
     * ما يمرّره النداءُ يعلو على الافتراضات، فيبقى للحالات الخاصّة مخرجُها.
     *
     * @param  array<string, mixed>  $attributes
     *
     * @throws ValidationException إذا كان التاريخ في فترةٍ مقفلة
     */
    protected function createHrJournalEntry(array $attributes, string $dateField = 'date'): JournalEntry
    {
        $date = $attributes['date'] ?? now();

        $this->guardAccountingPeriod($date, $dateField);

        $attributes['date'] = $date;
        $attributes['currency'] = $attributes['currency'] ?? CurrencySettings::code();

        if (! array_key_exists('branch_id', $attributes)) {
            $attributes['branch_id'] = current_branch_id();
        }

        return JournalEntry::create($attributes);
    }

    /**
     * مبلغٌ بصيغة عملة النظام — للرسائل وسجلّات العمليات.
     *
     * كانت الرسائلُ تُلحق `' ريال'` و`' ر.س'` نصّاً بعد `number_format`،
     * فتقول للمستخدم غيرَ عملته.
     */
    protected function hrMoney($amount): string
    {
        return CurrencySettings::format($amount);
    }
}
