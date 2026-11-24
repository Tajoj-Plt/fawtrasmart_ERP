<?php

namespace App\Support;

use App\Models\AccountingPeriod;
use App\Services\Accounting\AccountingPeriodService;
use Throwable;

/**
 * منعُ الكتابة المحاسبية في فترة مقفلة — نقطة واحدة لكل ما يكتب قيداً.
 *
 * الضوابط من شاشة «الفترات المحاسبية»:
 *
 *   • سنةٌ مالية مقفلة ⇒ تُمنع على الجميع، بلا تجاوز ولا إطفاء (`closedYearReason`)
 *   • `enforce_lock` مطفأً ⇒ تمرّ بقيّة التواريخ
 *   • `override_permission` ⇒ من يملكها لا يُمنع في الفترات المقفلة داخل سنةٍ مفتوحة
 *   • وإلا يُرَدّ سببُ المنع نصّاً جاهزاً للعرض
 *
 * **لماذا هنا لا في المتحكّم؟** كان الفحص دالّةً خاصّة داخل متحكّم فواتير
 * الشراء وحده، فبقيت العمليات الأخرى التي تكتب قيوداً — تحويل أمر الشراء
 * إلى فاتورة، وتأكيد الاستلام، وحذف الفاتورة — بلا فحص أصلاً: تكتب في
 * فترة مقفلة ولا يمنعها شيء. صار المرجع واحداً يناديه كل من يكتب قيداً.
 *
 * لا يُعطّل هذا الصنف العمليةَ حين تكون خدمة الفترات غير مهيّأة: غياب
 * الإعداد ليس منعاً.
 */
class AccountingPeriodGuard
{
    /**
     * سبب منع الكتابة في هذا التاريخ — أو `null` إن كان مسموحاً.
     */
    public static function blockReason($date): ?string
    {
        try {
            $service = app(AccountingPeriodService::class);

            // السنةُ المقفلة قبل الصلاحية: لا تجاوزَ لها
            if ($closedYear = $service->closedYearReason($date ?: now())) {
                return $closedYear;
            }

            $override = $service->settings()->override_permission;

            if ($override && auth()->user()?->can($override)) {
                return null;
            }

            return $service->blockReason($date ?: now());
        } catch (Throwable $e) {
            // خدمة الفترات غير مهيّأة: لا تُعطَّل العملية بسببها
            return null;
        }
    }

    /**
     * سببُ منع الكتابة على مستندٍ **مختومٍ** بفترة — أو `null`.
     *
     * الختمُ لا التاريخ: مستندٌ خُتم بفترةٍ أُقفلت يبقى منها ولو حُرِّك
     * تاريخُه أو تغيّرت حدودُها. ويسري ضابطُ `enforce_lock` وصلاحيةُ
     * التجاوز كما يسريان على التاريخ.
     */
    public static function blockReasonForPeriod(?int $periodId): ?string
    {
        if (! $periodId) {
            return null;
        }

        try {
            $service = app(AccountingPeriodService::class);
            $settings = $service->settings();

            $period = AccountingPeriod::with('fiscalYear')->find($periodId);

            // فترةٌ من سنةٍ مقفلة: لا تجاوزَ لها — ولا يُطفئها «فرضُ القفل»
            if ($closedYear = $service->closedYearMessage($period?->fiscalYear)) {
                return $closedYear;
            }

            if (! $settings->enforce_lock) {
                return null;
            }

            $override = $settings->override_permission;
            if ($override && auth()->user()?->can($override)) {
                return null;
            }

            if (! $period || ! in_array($period->status, [AccountingPeriod::STATUS_CLOSED, AccountingPeriod::STATUS_LOCKED], true)) {
                return null;
            }

            return "المستند من فترة «{$period->name}» المقفلة — لا يُنقل منها ولا يُحذف.";
        } catch (Throwable $e) {
            return null;
        }
    }

    /** هل التاريخ مسموح بالكتابة فيه؟ */
    public static function allows($date): bool
    {
        return self::blockReason($date) === null;
    }
}
