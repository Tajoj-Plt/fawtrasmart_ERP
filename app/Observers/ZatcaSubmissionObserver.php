<?php

namespace App\Observers;

use App\Models\CreditNotification;
use App\Models\Invoice;
use App\Services\Zatca\ZatcaInvoiceService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * يرسل المستند الضريبي إلى هيئة الزكاة والضريبة والجمارك متى اكتمل حفظه.
 *
 * **لماذا مراقبٌ لا نداءٌ في كلّ متحكّم؟** الفاتورة تُنشأ من عشرة مسارات:
 * شاشة المبيعات، تحويل عرض السعر وأمر البيع، نقطة البيع ومرتجعها، المتجر
 * الإلكتروني ومرتجعه، تطبيق الجوال، الفواتير الدورية. نداءٌ في كلٍّ منها
 * يُنسى في الحادي عشر — وفاتورةٌ لا تبلغ الهيئة مخالفةٌ لا يراها أحد.
 *
 * **بعد اكتمال المعاملة لا عند الإنشاء:** صفّ الفاتورة يُكتَب قبل بنودها،
 * فالإرسال لحظة `created` يجد مستنداً بلا بنود. `DB::afterCommit` يؤجّله
 * إلى أن تُثبَّت المعاملة كلّها — وإن أُلغيت لم يُرسَل شيء.
 *
 * **ولا يُسقِط شيئاً:** الفاتورة حُفظت؛ تعذُّر بلوغ الهيئة يُسجَّل، والمهمّة
 * المجدولة `zatca:submit-pending` تعيد المحاولة.
 */
class ZatcaSubmissionObserver
{
    public function created(Model $document): void
    {
        $this->afterCommit($document, 'create');
    }

    /** اكتمل سداد الفاتورة — لمن أجّل الإرسال حتى الدفع. */
    public function updated(Model $document): void
    {
        if (! $document instanceof Invoice) {
            return;
        }

        if ($document->wasChanged('payment_status') && (int) $document->payment_status === 1) {
            $this->afterCommit($document, 'payment');
        }
    }

    protected function afterCommit(Model $document, string $trigger): void
    {
        if (! $document instanceof Invoice && ! $document instanceof CreditNotification) {
            return;
        }

        // بلا معاملة لا يُعرَف متى تكتمل البنود: يُترك للمهمّة المجدولة
        if (DB::transactionLevel() === 0 && $trigger === 'create') {
            return;
        }

        try {
            DB::afterCommit(function () use ($document, $trigger) {
                try {
                    $fresh = $document->fresh();

                    if ($fresh !== null) {
                        app(ZatcaInvoiceService::class)->submitQuietly($fresh, $trigger);
                    }
                } catch (Throwable $e) {
                    Log::warning('ZATCA observer submit failed', ['message' => $e->getMessage()]);
                }
            });
        } catch (Throwable $e) {
            Log::warning('ZATCA observer could not schedule', ['message' => $e->getMessage()]);
        }
    }
}
