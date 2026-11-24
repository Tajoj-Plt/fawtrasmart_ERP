<?php

namespace Modules\Pos\Services\Session;

use App\Enums\LinkingType;
use App\Enums\ReferenceType;
use App\Models\PosSession;
use Illuminate\Support\Facades\DB;
use Modules\Pos\Http\Controllers\Concerns\GeneratesDocumentNumbers;
use Modules\Pos\Http\Controllers\Concerns\ResolvesAccountingAccounts;
use Modules\Pos\Http\Controllers\Concerns\ResolvesPosSettings;
use Modules\Pos\Http\Controllers\Concerns\RespectsAccountingPeriods;
use Modules\Pos\Http\Controllers\Concerns\UsesDocumentCurrency;
use Modules\Pos\Models\PosAuditLog;
use Modules\Pos\Models\PosCashMovement;
use Modules\Pos\Services\Accounting\PosJournalPoster;

/**
 * الدرجُ النقدي: إيداعٌ وسحبٌ وترحيلٌ وعدّ — ولكلٍّ قيدُه.
 *
 * ═════════════════════ ما كان ═════════════════════
 *
 * لا شيء. الجلسةُ تُفتح برصيدٍ افتتاحي وتُقفل برصيدٍ فعلي، وبينهما **لا
 * سبيل لإخراج ريالٍ من الدرج ولا إدخاله**.
 *
 * وهذا لا يوافق تشغيلاً حقيقياً: الكاشير يحتاج فكّةً في منتصف الوردية،
 * والمشرفُ يسحب النقد الزائد إلى الخزينة حين يمتلئ الدرج (وهو إجراءٌ أمنيّ
 * قبل أن يكون محاسبياً)، والمصروفُ النثري الصغير يُدفع من الدرج.
 *
 * فكان كلُّ ذلك يحدث **خارج النظام**، ثم يظهر أثرُه فرقاً في الإقفال لا
 * تفسير له. فالفرقُ الذي وُضع ليقيس الخطأ صار يقيس الحركاتِ المشروعةَ
 * غيرَ المسجَّلة — وضاعت قيمتُه الرقابية كلُّها.
 *
 * ═════════════════════ ولماذا لكلٍّ قيدُه؟ ═════════════════════
 *
 * نقلُ مالٍ من درج الكاشير إلى الخزينة الرئيسية حركةٌ بين حسابين. وتركُها
 * بلا قيدٍ يجعل رصيدَ الخزينة في الدفاتر يخالف عدَّها الفعلي — وهو خطأٌ
 * يظهر متأخّراً جداً، عند الجرد النقدي الشهري، حين يستحيل تتبّعُ مصدره.
 */
class CashDrawerService
{
    use GeneratesDocumentNumbers;
    // `ResolvesAccountingAccounts` تقرأ `posSettings()` — فتلزم السِّمتان معاً
    use ResolvesAccountingAccounts, ResolvesPosSettings;
    use RespectsAccountingPeriods;
    use UsesDocumentCurrency;

    /**
     * تسجيلُ حركةِ درج، وقيدُها إن كانت تمسّ المال.
     *
     * @param  array{
     *     type: string,
     *     amount: float,
     *     reason?: string|null,
     *     counterpart_account_id?: int|null,
     *     needs_approval?: bool
     * }  $data
     *
     * @throws \RuntimeException إن كانت الفترة مقفلة أو النوع مجهولاً
     */
    public function record(PosSession $session, array $data): PosCashMovement
    {
        $type = $data['type'];

        if (! array_key_exists($type, PosCashMovement::CASH_EFFECT)) {
            throw new \RuntimeException("نوعُ حركةِ درجٍ مجهول: {$type}");
        }

        $amount = round((float) ($data['amount'] ?? 0), 2);

        if ($type !== PosCashMovement::TYPE_COUNT && $amount <= 0) {
            throw new \RuntimeException('مبلغُ حركة الدرج يجب أن يكون أكبر من صفر.');
        }

        // الفترةُ المقفلة تردّ الحركة قبل أن تُكتب — لا بعد أن تُكتب ويُرفض قيدُها
        if ($block = $this->accountingPeriodBlock(now())) {
            throw new \RuntimeException($block);
        }

        /*
            السحبُ والترحيل لا يخرجان من الدرج قبل اعتماد المشرف.

            والإيداعُ والعدُّ لا يحتاجان إذناً: الأولُ يزيد النقد فلا خطرَ
            فيه، والثاني لا يحرّكه أصلاً.
        */
        $needsApproval = $data['needs_approval']
            ?? in_array($type, [PosCashMovement::TYPE_PAY_OUT, PosCashMovement::TYPE_DROP], true);

        return DB::transaction(function () use ($session, $type, $amount, $data, $needsApproval) {
            $movement = PosCashMovement::create([
                'reference_number' => $this->issueCashMovementNumber(),
                'session_id' => $session->id,
                'device_id' => $session->device_id,
                // الفرعُ والفترةُ والعملة والمُنشئ تُختَم في [PosCashMovement]
                'type' => $type,
                'amount' => $amount,
                'counterpart_account_id' => $data['counterpart_account_id'] ?? null,
                'reason' => $data['reason'] ?? null,
                'status' => $needsApproval
                    ? PosCashMovement::STATUS_PENDING
                    : PosCashMovement::STATUS_APPROVED,
                'approved_by' => $needsApproval ? null : auth()->id(),
                'approved_at' => $needsApproval ? null : now(),
                'movement_time' => now(),
            ]);

            // المعتمدةُ فوراً تُقيَّد فوراً؛ والمعلَّقةُ تنتظر `approve()`
            if (! $needsApproval) {
                $this->postJournal($movement, $session);
            }

            $this->audit($movement, $session);

            return $movement->fresh();
        });
    }

    /**
     * اعتمادُ حركةٍ معلَّقة — وعندها يُكتب قيدُها.
     *
     * القيدُ عند الاعتماد لا عند الإنشاء: حركةٌ لم تُعتمَد لم يخرج مالُها
     * فعلاً، فقيدُها يُثبت ما لم يقع.
     */
    public function approve(PosCashMovement $movement): PosCashMovement
    {
        if ($movement->status !== PosCashMovement::STATUS_PENDING) {
            throw new \RuntimeException('هذه الحركة ليست بانتظار الاعتماد.');
        }

        if ($block = $this->accountingPeriodBlock($movement->movement_time)) {
            throw new \RuntimeException($block);
        }

        return DB::transaction(function () use ($movement) {
            $movement->update([
                'status' => PosCashMovement::STATUS_APPROVED,
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]);

            $this->postJournal($movement, $movement->session);

            PosAuditLog::record(
                $movement->type === PosCashMovement::TYPE_DROP
                    ? PosAuditLog::ACTION_CASH_DROP
                    : PosAuditLog::ACTION_CASH_PAY_OUT,
                [
                    'session_id' => $movement->session_id,
                    'device_id' => $movement->device_id,
                    'branch_id' => $movement->branch_id,
                    'approved_by' => auth()->id(),
                    'amount' => $movement->amount,
                    'currency' => $movement->currency,
                    'reason' => $movement->reason,
                    'accounting_period_id' => $movement->accounting_period_id,
                ]
            );

            return $movement->fresh();
        });
    }

    public function reject(PosCashMovement $movement, ?string $reason = null): PosCashMovement
    {
        if ($movement->status !== PosCashMovement::STATUS_PENDING) {
            throw new \RuntimeException('هذه الحركة ليست بانتظار الاعتماد.');
        }

        $movement->update([
            'status' => PosCashMovement::STATUS_REJECTED,
            'approved_by' => auth()->id(),
            'approved_at' => now(),
            'reason' => $reason ?: $movement->reason,
        ]);

        return $movement->fresh();
    }

    /**
     * قيدُ الحركة.
     *
     * الاتجاهُ يتبع النوع:
     *
     *   `pay_in`  ← الدرجُ يزيد: مدينٌ خزينةُ الكاشير، دائنٌ المصدر.
     *   `pay_out` ← الدرجُ ينقص: مدينٌ المصروف، دائنٌ خزينةُ الكاشير.
     *   `drop`    ← تحويلٌ بين خزينتين: مدينٌ الخزينة الرئيسية، دائنٌ الدرج.
     *   `count`   ← لا قيد.
     */
    private function postJournal(PosCashMovement $movement, ?PosSession $session): void
    {
        if ($movement->type === PosCashMovement::TYPE_COUNT) {
            return;
        }

        $employeeId = $session?->user?->employee_id ?? auth()->user()?->employee_id;

        // خزينةُ الكاشير — خزينتُه المربوطة إن وُجدت، وإلا الافتراضية
        $drawerAccount = $this->treasuryAccountFor($employeeId);

        $counterpartId = $movement->counterpart_account_id;

        /*
            الطرفُ المقابل الافتراضي — الخزينة الرئيسية.

            `drop` ترحيلٌ **إلى** الخزينة، و`pay_in` فكّةٌ تأتي **منها**.
            فكلاهما حركةٌ بين خزينتين، ووجهتُها معروفةٌ بلا سؤال.

            وحدَه `pay_out` يبقى مطالباً بحسابٍ صريح: صرفُ نثرياتٍ من الدرج
            مصروفٌ، والقرارُ بأيّ حساب مصروفٍ يُقيَّد ليس للنظام أن يخمّنه —
            وتخمينُه يُلقي كلَّ النثريات في حسابٍ واحد لا يُقرأ منه شيء.
        */
        if (in_array($movement->type, [PosCashMovement::TYPE_DROP, PosCashMovement::TYPE_PAY_IN], true)) {
            $counterpartId ??= $this->requireAccountingAccountId(
                'main_treasury',
                \App\Constants\SystemAccountIds::MAIN_TREASURY
            );
        }

        if (! $counterpartId) {
            throw new \RuntimeException(
                'لا يمكن قيدُ سحبٍ من الدرج بلا حسابٍ مقابل — اختر حساب المصروف الذي يُقيَّد عليه.'
            );
        }

        /*
            الدرجُ والمقابلُ حسابٌ واحد؟

            يقع حين لا تكون للكاشير خزينةٌ مربوطة، فتُحَلّ خزينتُه إلى
            الخزينة الرئيسية نفسِها. والقيدُ حينئذ طرفاه واحد: لا أثرَ له
            محاسبياً، ويُرفض في [PosJournalPoster] بلا رسالةٍ مفهومة.
        */
        if ((int) $counterpartId === (int) $drawerAccount->id) {
            \Illuminate\Support\Facades\Log::info('حركةُ درجٍ بلا قيد — الدرجُ والحسابُ المقابل واحد', [
                'movement_id' => $movement->id,
                'account_id' => $counterpartId,
            ]);

            return;
        }

        $poster = PosJournalPoster::make([
            'reference_number' => $movement->reference_number,
            'date' => $movement->movement_time,
            'description' => PosCashMovement::typeLabel($movement->type)
                .' — جلسة '.($session?->session_number ?? $movement->session_id),
            'currency' => $movement->currency,
            'branch_id' => $movement->branch_id,
            'accounting_period_id' => $movement->accounting_period_id,
            'reference_type' => ReferenceType::CUSTOMER_COLLECTION,
            'linking_type' => LinkingType::SINGLE,
        ]);

        $amount = (float) $movement->amount;

        if ($movement->type === PosCashMovement::TYPE_PAY_IN) {
            $poster->debit($drawerAccount, $amount, 'إيداع نقد في درج الكاشير')
                ->credit($counterpartId, $amount, 'مصدر النقد المودَع');
        } else {
            // `pay_out` و`drop` كلاهما يُنقص الدرج — يختلفان في الوجهة لا في الاتجاه
            $poster->debit($counterpartId, $amount, PosCashMovement::typeLabel($movement->type))
                ->credit($drawerAccount, $amount, 'صرف من درج الكاشير');
        }

        $entry = $poster->post();

        if ($entry) {
            $movement->update(['journal_entry_id' => $entry->id]);
        }
    }

    /**
     * تسجيلُ الحركة في سجلّ التدقيق — عند وقوعها لا عند طلبها.
     *
     * الحركةُ المعلَّقة لم يخرج مالُها بعد، و`approve()` تسجّلها حين تخرج.
     * فلولا هذا الشرطُ لظهرت كلُّ حركةِ سحبٍ **مرّتين** في السجلّ: مرّةً
     * بلا مُعتمِد ومرّةً بمُعتمِد — فيبدو أنّ سحبين وقعا لا واحد.
     */
    private function audit(PosCashMovement $movement, PosSession $session): void
    {
        if ($movement->status === PosCashMovement::STATUS_PENDING) {
            return;
        }

        if (! in_array($movement->type, [PosCashMovement::TYPE_PAY_OUT, PosCashMovement::TYPE_DROP], true)) {
            return;
        }

        PosAuditLog::record(
            $movement->type === PosCashMovement::TYPE_DROP
                ? PosAuditLog::ACTION_CASH_DROP
                : PosAuditLog::ACTION_CASH_PAY_OUT,
            [
                'session_id' => $session->id,
                'device_id' => $session->device_id,
                'branch_id' => $session->branch_id,
                'amount' => $movement->amount,
                'currency' => $movement->currency,
                'reason' => $movement->reason,
                'accounting_period_id' => $movement->accounting_period_id,
                'after' => ['type' => $movement->type, 'status' => $movement->status],
            ]
        );
    }

    /**
     * ملخّصُ الدرج لجلسة — لشاشة الإقفال وللتقارير.
     *
     * @return array{pay_in: float, pay_out: float, drop: float, net: float, pending: int}
     */
    public function summary(PosSession $session): array
    {
        $rows = PosCashMovement::forSession($session->id)
            ->approved()
            ->selectRaw('type, SUM(amount) as total')
            ->groupBy('type')
            ->pluck('total', 'type');

        $payIn = (float) ($rows[PosCashMovement::TYPE_PAY_IN] ?? 0);
        $payOut = (float) ($rows[PosCashMovement::TYPE_PAY_OUT] ?? 0);
        $drop = (float) ($rows[PosCashMovement::TYPE_DROP] ?? 0);

        return [
            'pay_in' => $payIn,
            'pay_out' => $payOut,
            'drop' => $drop,
            'net' => round($payIn - $payOut - $drop, 2),
            'pending' => PosCashMovement::forSession($session->id)->pending()->count(),
        ];
    }
}
