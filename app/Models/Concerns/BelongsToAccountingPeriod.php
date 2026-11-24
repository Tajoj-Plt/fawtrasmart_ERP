<?php

namespace App\Models\Concerns;

use App\Models\AccountingPeriod;
use App\Support\AccountingPeriodGuard;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * الفترةُ المحاسبية صفةٌ في المستند، لا استنتاجٌ يُعاد في كلّ شاشة.
 *
 * **ما كان**
 *
 * تُسأل الفترةُ بالتاريخ في كلّ موضع: `whereBetween('date', [حدّ، حدّ])`.
 * وذاك يعمل ما دام التقويمُ متّصلاً، لكنّه ليس علاقة — والفرقُ يظهر في ثلاث:
 *
 *   • الحدُّ يُستنتَج في كلّ شاشة، فتختلف الشاشتان في الحدّ نفسِه.
 *   • إعادةُ ترسيم الفترات تُعيد تصنيف الماضي: مستندٌ رُحِّل في الربع الأول
 *     يصير في الثاني لأنّ الحدود تحرّكت — والدفترُ المُقفَل لا يُعاد تصنيفه.
 *   • لا انضمامَ ولا فهرسَ ولا إحصاءَ يُبنى على «مدىً» يُحسب كلّ مرّة.
 *
 * **ما صار**
 *
 * الفترةُ تُختَم لحظةَ الإنشاء من تاريخ المستند، فتثبت معه ما بقي.
 *
 * **الاستعمال:** ضعِ السِّمةَ في المودل، وعرِّف `accountingDateColumn()` إن
 * كان التاريخُ الحاكم غير `date`.
 *
 * والتاريخُ الحاكم هو تاريخُ **الحدث المحاسبي** لا أيّ تاريخٍ في السجلّ:
 * الشيكُ يُنسَب إلى فترة إصداره لا استحقاقه، فاستحقاقُه قد يقع بعد سنة
 * والقيدُ الذي عنه كُتب يوم أُصدر.
 */
trait BelongsToAccountingPeriod
{
    public static function bootBelongsToAccountingPeriod(): void
    {
        /*
            الختمُ في المودل لا في المتحكّم: المستندُ يُنشأ من شاشةٍ ومن
            أمرِ كرونٍ ومن خدمةِ ترحيلٍ ومن بذرة. وما وُضع في متحكّمٍ واحد
            يفلت من البقية بلا أن يُلاحَظ — فيخرج مستندٌ بلا فترة ولا يُعرف
            متى ولا من أين.
        */
        static::creating(function ($model) {
            if (empty($model->accounting_period_id)) {
                $model->accounting_period_id = $model->resolveAccountingPeriodId();
            }

            // لا مستندَ جديد في فترةٍ مقفلة — من أيّ مسارٍ جاء
            $model->guardAccountingWrite(
                AccountingPeriodGuard::blockReasonForPeriod($model->accounting_period_id ? (int) $model->accounting_period_id : null)
            );
        });

        /*
            تعديلُ تاريخِ مستندٍ يُعيد نسبتَه.

            وإلّا بقي القيدُ منسوباً إلى فترةٍ لا يقع فيها: يُنقَل تاريخُه من
            مارس إلى أبريل ويظلّ محسوباً على الربع الأول — فيختلف مجموعُ
            الفترة عن مجموع تواريخها، ولا يُعرف أيُّهما الصواب.

            **ونقلُه لا يخرج به من إقفال ولا يُدخله في إقفال.** كانت شاشاتٌ
            تفحص التاريخَ الجديد وحده (سند الصرف، سند القبض، المرتجع، …):
            سندٌ من ديسمبر المقفل يُعدَّل تاريخُه إلى فبراير المفتوح فيخرج من
            الإقفال. الفحصُ هنا على الختم القديم والجديد معاً، لكلّ مستندٍ
            يحمل هذه السِّمة. وتعديلُ ما سوى التاريخ (سدادُ فاتورةٍ قديمة من
            سنةٍ جديدة يغيّر رصيدَها) يبقى لشاشته.
        */
        static::updating(function ($model) {
            $column = $model->accountingDateColumn();

            if (! $model->isDirty($column) && ! $model->isDirty('accounting_period_id')) {
                return;
            }

            $model->guardAccountingWrite(
                AccountingPeriodGuard::blockReasonForPeriod($model->getOriginal('accounting_period_id') ? (int) $model->getOriginal('accounting_period_id') : null)
            );

            if ($model->isDirty($column) && ! $model->isDirty('accounting_period_id')) {
                $model->accounting_period_id = $model->resolveAccountingPeriodId();
            }

            $model->guardAccountingWrite(
                AccountingPeriodGuard::blockReasonForPeriod($model->accounting_period_id ? (int) $model->accounting_period_id : null)
            );
        });

        // حذفُ مستندٍ من فترةٍ مقفلة يُغيّر دفتراً أُقفل
        static::deleting(function ($model) {
            $model->guardAccountingWrite(
                AccountingPeriodGuard::blockReasonForPeriod($model->getOriginal('accounting_period_id') ? (int) $model->getOriginal('accounting_period_id') : null)
            );
        });
    }

    /**
     * هل يحرس إقفالُ الفترة هذا المستند؟
     *
     * الإقفالُ يحمي دفترَ الأستاذ: ما يُرحِّل أو يغيّر رصيداً. وما يُختم للفرز
     * والتقارير ولا أثر محاسبيَّ له بذاته (سجلُّ تدقيق، حجزٌ يُرحَّل عربونُه
     * بقيدٍ مستقلّ) يُعيد هذه `false`: يُختم ولا يُمنع — فلا يتوقّف التدقيقُ
     * أو الحجزُ لأنّ شهراً أُقفل.
     */
    public function guardsAccountingPeriod(): bool
    {
        return true;
    }

    /** يُرفض الحفظُ برسالةٍ على حقل التاريخ — كما ترفضه الشاشات. */
    protected function guardAccountingWrite(?string $reason): void
    {
        if ($reason !== null && $this->guardsAccountingPeriod()) {
            throw ValidationException::withMessages([$this->accountingDateColumn() => [$reason]]);
        }
    }

    /** الفترةُ المحاسبية التي يقع فيها هذا المستند. */
    public function accountingPeriod(): BelongsTo
    {
        return $this->belongsTo(AccountingPeriod::class, 'accounting_period_id');
    }

    /** العمودُ الذي يحكم فترةَ المستند — يُعاد تعريفُه في المودل عند الحاجة. */
    public function accountingDateColumn(): string
    {
        return 'date';
    }

    /**
     * الفترةُ التي يقع فيها تاريخُ المستند — أو `null`.
     *
     * و`null` حالٌ مشروعة: قاعدةٌ لم تُولَّد فتراتُها بعد، أو تقويمٌ فيه
     * فجوة. ومنعُ الحفظ حينئذٍ شأنُ ضابط الإقفال لا شأنُ هذه السِّمة —
     * فالسِّمةُ تصف ولا تمنع.
     */
    public function resolveAccountingPeriodId(): ?int
    {
        try {
            $value = $this->{$this->accountingDateColumn()} ?? null;

            /*
                `Illuminate\Support\Carbon` لا `Carbon\Carbon`.

                و`AccountingPeriod::forDate()` تشترط الأولى، والثانيةُ أبوها —
                فالتمريرُ بها `TypeError` يبتلعه `catch` أدناه، فيُحفَظ المستندُ
                بلا فترة ولا يظهر للخطأ أثر.
            */
            $date = $value ? Carbon::parse($value) : Carbon::now();

            return AccountingPeriod::forDate($date)?->id;
        } catch (Throwable $e) {
            // جدولُ الفترات غير مهيّأ، أو تاريخٌ لا يُقرأ: يُحفَظ بلا فترة
            return null;
        }
    }

    /**
     * حصرُ الاستعلام في فترةٍ بعينها — أو في «بلا فترة».
     *
     * `$periodId` فارغاً يعني «لا حصر»، فالمناداةُ تبقى آمنةً في الشاشات
     * التي لم يختر فيها المستخدمُ شيئاً.
     */
    public function scopeForAccountingPeriod(Builder $query, $periodId): Builder
    {
        if ($periodId === null || $periodId === '' || $periodId === 'all') {
            return $query;
        }

        // «بلا فترة» — لعرض ما خرج عن التقويم كي يُصحَّح
        if ($periodId === 'none') {
            return $query->whereNull($query->qualifyColumn('accounting_period_id'));
        }

        return $query->where($query->qualifyColumn('accounting_period_id'), $periodId);
    }

    /** حصرُ الاستعلام في مجموعة فترات — للسنة المالية مثلاً. */
    public function scopeForAccountingPeriods(Builder $query, array $periodIds): Builder
    {
        if (! $periodIds) {
            return $query;
        }

        return $query->whereIn($query->qualifyColumn('accounting_period_id'), $periodIds);
    }
}
