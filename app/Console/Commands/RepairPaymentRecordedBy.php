<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * يُصلح «مَن سجّل السند» في `payments_process.employee_id`.
 *
 * **لماذا؟** العمودُ يحمل `users.id` (انظر `App\Models\PaymentsProcess`)،
 * لكنّ ختمَ `StampsOwnerContext` كان يملؤه بـ`employees.id` في كلّ مسارٍ
 * لا يكتبه صراحةً: توزيعُ سند القبض على الفواتير، وتسويةُ الإشعار الدائن،
 * وتحصيلُ الشيك المستلَم. فالرقمُ يقع على مستخدمٍ آخر، وتعرض شاشةُ
 * المدفوعات اسمَ غيرِ مَن سجّل، ويدخل السندُ نطاقَ «سنداتي» عند مستخدمٍ
 * لا صلة له به.
 *
 * والمصدرُ الصادق موجود: كلُّ سندٍ من هذه المسارات يحمل في `reference_number`
 * رقمَ مستنده الأصل — سند القبض (`receipts.id`)، أو الإشعار الدائن
 * (`CN-<id>`)، أو الشيك (`received_cheques.id`) — وذاك المستندُ يحمل
 * `created_by` بـ`users.id`. فيُنسَخ منه، ولا يُخمَّن.
 *
 * يُصلَح صنفان: ما خُتم برقمٍ مخالفٍ لمُنشئ الأصل، وما بقي فارغاً من قبل
 * الختم أصلاً. ولا يُمسّ سندٌ لا أصلَ له، ولا رقمٌ مرجعيّ كُتب باليد:
 * سندُ القبض يُشترط له أن يُنشأ في دقائق إنشاء الأصل نفسها وبلا خزينة —
 * فمسارُ التوزيع لا يكتب خزينةً، وشاشةُ التسجيل تكتبها دائماً.
 *
 * الفحص وحده هو التصرّف الافتراضي؛ الكتابة تُطلب صراحةً بـ `--fix`.
 */
class RepairPaymentRecordedBy extends Command
{
    protected $signature = 'payments:repair-recorded-by
                            {--fix : يكتب التصحيح؛ بدونه فحصٌ فقط}';

    protected $description = 'إصلاح «من سجّل السند» في المدفوعات: نسخ مُنشئ سند القبض أو الإشعار الدائن أو الشيك إلى employee_id';

    public function handle(): int
    {
        $sources = [
            'سند قبض' => $this->fromReceipts(),
            'إشعار دائن' => $this->fromCreditNotes(),
            'شيك مستلَم' => $this->fromCheques(),
        ];

        $all = collect($sources)->flatten(1);

        if ($all->isEmpty()) {
            $this->info('لا سندات مدفوعاتٍ مُسجِّلُها مخالفٌ لمُنشئ أصلها.');

            return self::SUCCESS;
        }

        foreach ($sources as $label => $rows) {
            if ($rows->isEmpty()) {
                continue;
            }

            [$wrong, $empty] = $rows->partition(fn ($row) => $row->employee_id !== null);

            $this->warn("{$label}: ".$rows->count().' سنداً — مختوم برقمٍ مخالف: '.$wrong->count().'، بلا مُسجِّل: '.$empty->count());

            $this->table(
                ['السند', 'النوع', 'المرجع', 'المحفوظ', 'الصحيح (users.id)', 'المُسجِّل الصحيح'],
                $rows->take(15)->map(fn ($row) => [
                    $row->id,
                    $row->type,
                    $row->reference_number,
                    $row->employee_id ?? '—',
                    $row->correct_user_id,
                    $row->correct_user_name,
                ])->all()
            );

            if ($rows->count() > 15) {
                $this->line('… و'.($rows->count() - 15).' سنداً آخر.');
            }

            $this->newLine();
        }

        if (! $this->option('fix')) {
            $this->info('فحصٌ فقط. أعد التشغيل مع --fix للكتابة. الإجمالي: '.$all->count());

            return self::SUCCESS;
        }

        $updated = 0;

        DB::transaction(function () use ($all, &$updated) {
            foreach ($all->groupBy('correct_user_id') as $userId => $rows) {
                $updated += DB::table('payments_process')
                    ->whereIn('id', $rows->pluck('id')->all())
                    ->update(['employee_id' => (int) $userId]);
            }
        });

        $this->info("تمّ تصحيح {$updated} سنداً.");

        return self::SUCCESS;
    }

    /**
     * توزيعُ سند القبض على الفواتير والرصيد السابق
     * (`IncomesController::applyPaymentToInvoices`): مربوطةٌ بسندها في
     * `receipt_id`، بلا خزينة.
     */
    private function fromReceipts(): Collection
    {
        // `receipt_id` يكتبه التوزيعُ ويُعبَّأ للقديم في هجرة
        // `2026_10_01_120000` — بدل مطابقة المرجع النصّي بالقرائن
        return $this->base()
            ->join('receipts as r', 'r.id', '=', 'p.receipt_id')
            ->leftJoin('users as u', 'u.id', '=', 'r.created_by')
            ->whereIn('p.type', ['client payments', 'previous_balance_payment'])
            ->whereNull('p.treasury_id')
            ->whereNotNull('r.created_by')
            ->where(fn ($q) => $q->whereNull('p.employee_id')->orWhereColumn('p.employee_id', '!=', 'r.created_by'))
            ->get($this->columns('r.created_by'));
    }

    /**
     * تسويةُ الإشعار الدائن على الدين (`ClientDebtSettlement`): المرجعُ
     * `CN-<id>`، والنوعُ نوعا التسوية.
     */
    private function fromCreditNotes(): Collection
    {
        return $this->base()
            ->join('credit_notifications as cn', DB::raw("CONCAT('CN-', cn.id)"), '=', 'p.reference_number')
            ->leftJoin('users as u', 'u.id', '=', 'cn.created_by')
            ->whereIn('p.type', ['credit_note_previous_balance', 'credit_note_settlement'])
            ->whereNotNull('cn.created_by')
            ->where(fn ($q) => $q->whereNull('p.employee_id')->orWhereColumn('p.employee_id', '!=', 'cn.created_by'))
            ->get($this->columns('cn.created_by'));
    }

    /**
     * تحصيلُ الشيك المستلَم أو تظهيرُه (`ReceivedChequesController`):
     * المرجعُ رقمُ الشيك، والملاحظةُ تبدأ بعبارته الثابتة.
     */
    private function fromCheques(): Collection
    {
        return $this->base()
            ->join('received_cheques as c', 'c.id', '=', DB::raw('CAST(p.reference_number AS UNSIGNED)'))
            ->leftJoin('users as u', 'u.id', '=', 'c.created_by')
            ->whereRaw("p.reference_number REGEXP '^[0-9]+$'")
            ->where('p.notes', 'like', 'تسديد من شيك رقم #%')
            ->whereNotNull('c.created_by')
            ->where(fn ($q) => $q->whereNull('p.employee_id')->orWhereColumn('p.employee_id', '!=', 'c.created_by'))
            ->get($this->columns('c.created_by'));
    }

    private function base()
    {
        return DB::table('payments_process as p')->orderBy('p.id');
    }

    /** @return array<int, string|\Illuminate\Database\Query\Expression> */
    private function columns(string $creatorColumn): array
    {
        return [
            'p.id',
            'p.type',
            'p.reference_number',
            'p.employee_id',
            DB::raw("{$creatorColumn} as correct_user_id"),
            DB::raw('u.name as correct_user_name'),
        ];
    }
}
