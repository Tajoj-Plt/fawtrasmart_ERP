<?php

namespace Modules\RentalManagement\Observers;

use App\Enums\ChequeStatus;
use App\Models\ReceivedCheque;
use Illuminate\Validation\ValidationException;
use Modules\RentalManagement\Models\Payment;
use Modules\RentalManagement\Services\PaymentService;

/**
 * يربط دورة الشيك المستلم (شاشة الشيكات في الحسابات) بدفعة العقار:
 * التحصيل أو التظهير يرحّل الدفعة، والارتجاع أو الحذف يلغيها.
 */
class ReceivedChequeObserver
{
    public function updating(ReceivedCheque $cheque): void
    {
        // مبلغ شيكٍ مربوط بدفعة عقار لا يُعدَّل من شاشة الشيكات: الدفعة موزَّعة على أقساطٍ بمبلغه
        if ($cheque->isDirty('amount') && $this->payment($cheque)) {
            throw ValidationException::withMessages([
                'amount' => 'هذا الشيك مربوط بدفعة عقار — لتغيير مبلغه ألغِ الدفعة من العقارات وسجّلها من جديد.',
            ]);
        }
    }

    public function updated(ReceivedCheque $cheque): void
    {
        if (! $cheque->wasChanged('status') || ! ($payment = $this->payment($cheque))) {
            return;
        }

        $status = $cheque->status instanceof ChequeStatus ? $cheque->status : ChequeStatus::tryFrom((string) $cheque->status);

        if (in_array($status, [ChequeStatus::COLLECTED, ChequeStatus::ENDORSED], true)) {
            app(PaymentService::class)->chequeCollected($payment);
        } elseif ($status === ChequeStatus::REJECTED) {
            app(PaymentService::class)->chequeBounced($payment, 'شيك مرتجع رقم '.$cheque->cheque_number);
        }
    }

    public function deleting(ReceivedCheque $cheque): void
    {
        if (PaymentService::$releasingCheque || ! ($payment = $this->payment($cheque))) {
            return;
        }

        app(PaymentService::class)->chequeBounced($payment, 'حُذف الشيك رقم '.$cheque->cheque_number.' من شاشة الشيكات');
    }

    private function payment(ReceivedCheque $cheque): ?Payment
    {
        return Payment::withoutGlobalScopes()->where('received_cheque_id', $cheque->id)->where('posting_status', 'cheque_pending')->first();
    }
}
