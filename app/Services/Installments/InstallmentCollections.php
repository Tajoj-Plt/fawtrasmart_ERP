<?php

namespace App\Services\Installments;

use App\Models\Installment;
use App\Models\InstallmentDetail;
use App\Models\JournalEntry;
use App\Support\OperationLog;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * توزيعُ سندات القبض على أقساط الاتفاقية، ثمّ تحقّقُ ربحها.
 *
 * كانت `InstallmentsController::applyPayments()` خاصّةً بالمتحكّم، فلا
 * تجري إلا حين تُفتح شاشةُ الاتفاقية — وسندٌ يُسجَّل من شاشة السندات
 * لا يُحرّك الأقساطَ ولا الإيرادَ حتى يزورها أحد. صارت هنا، وتُنادى من
 * شاشة التقسيط ومن حفظ السند وتعديله وإلغائه وحذفه.
 */
class InstallmentCollections
{
    private const LOG_MODULE = 'SalesInstallments';

    /** كلُّ اتفاقيات الفاتورة — بعد أيّ تغييرٍ في سنداتها. */
    public static function syncInvoice(?int $invoiceId, ?JournalEntry $receiptJournal = null): void
    {
        if (! $invoiceId) {
            return;
        }

        Installment::where('invoice_id', $invoiceId)
            ->get()
            ->each(fn (Installment $agreement) => self::apply($agreement, $receiptJournal));
    }

    /**
     * إعادة قراءة السداد من سندات القبض وتوزيعه على الأقساط.
     *
     * التوزيع بترتيب الاستحقاق: أقدمُ قسطٍ يُسدَّد أوّلاً — وهو العرف في
     * التقسيط، ويمنع أن يبقى قسطٌ قديمٌ متأخّراً بينما سُدِّد ما بعده.
     *
     * لا مالَ يُكتب هنا: `paid_amount` مرآةٌ لما سُجِّل في سندات القبض على
     * الفاتورة، لا مصدرٌ ثانٍ له. والقيدُ الوحيد نقلُ الربح المحقَّق من
     * المؤجَّل إلى الإيراد (`InstallmentFinancing::recognize`).
     */
    public static function apply(Installment $agreement, ?JournalEntry $receiptJournal = null): void
    {
        try {
            $invoice = $agreement->invoice;

            if (! $invoice) {
                return;
            }

            // السندات الملغاة (5) خارج المقبوض — كانت تُحسب فيبقى القسطُ
            // «مسدَّداً» بعد إلغاء سنده
            $collected = InstallmentFinancing::invoiceCollected($invoice);

            // ما سُدِّد قبل الاتفاقية (الدفعة المقدَّمة) لا يُوزَّع على
            // أقساطها: هو خارج المبلغ الممول أصلاً
            $collected = max(0, $collected - (float) $agreement->down_payment);

            DB::transaction(function () use ($agreement, $collected) {
                $left = $collected;

                foreach ($agreement->details()->get() as $detail) {
                    if ($detail->status === InstallmentDetail::STATUS_CANCELLED) {
                        continue;
                    }

                    /* داخل القسط الواحد: مبلغُه أوّلاً ثمّ غرامتُه.
                     *
                     * «توضع الغرامة مع الدفعة التي تأخّر بدفعها» — فهي
                     * جزءٌ من مطالبة هذا القسط لا بندٌ معلّقٌ في مكانٍ آخر.
                     *
                     * والترتيبُ مقصود: من سدّد قيمةَ القسط بالضبط أُقفل
                     * قسطُه وبقيت الغرامةُ ظاهرةً مستحقّة. والعكسُ —
                     * الغرامةُ أوّلاً — يجعل من دفع مبلغ القسط كاملاً
                     * يجده «مسدَّداً جزئياً»، فيظنّ أنّ دفعته نقصت. */
                    $share = min($left, (float) $detail->amount);
                    $left = round($left - $share, 2);

                    $feeShare = min($left, (float) $detail->late_fee_amount);
                    $left = round($left - $feeShare, 2);

                    $samePaid = abs((float) $detail->paid_amount - $share) < 0.005;
                    $sameFee = abs((float) $detail->late_fee_paid - $feeShare) < 0.005;

                    if ($samePaid && $sameFee) {
                        continue;
                    }

                    $detail->paid_amount = round($share, 2);
                    $detail->late_fee_paid = round($feeShare, 2);
                    $detail->syncStatus();
                    $detail->save();
                }
            });

            // الربح المؤجَّل يتحقّق بنصيب ما حُصِّل — في قيد السند نفسِه إن وُجد
            InstallmentFinancing::recognize($agreement, $receiptJournal);

            // الاتفاقية تُقفَل تلقائياً عند اكتمال السداد
            $agreement->refresh()->load('details');

            $shouldBe = $agreement->effective_status;

            if ($agreement->status !== Installment::STATUS_CANCELLED && $agreement->status !== $shouldBe) {
                $agreement->update(['status' => $shouldBe]);

                OperationLog::record(
                    'updated',
                    self::LOG_MODULE,
                    $agreement,
                    __('sales_installments.log.status', [
                        'code' => $agreement->code,
                        'status' => __('sales_installments.status.'.$shouldBe),
                    ])
                );
            }
        } catch (Throwable $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'apply_payments', $agreement);
        }
    }
}
