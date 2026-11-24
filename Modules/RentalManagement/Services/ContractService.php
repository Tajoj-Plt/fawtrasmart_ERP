<?php

namespace Modules\RentalManagement\Services;

use App\Models\notifications;
use App\Support\OperationLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Modules\RentalManagement\Models\Contract;
use Modules\RentalManagement\Models\ContractAmendment;
use Modules\RentalManagement\Models\ContractRenewal;
use Modules\RentalManagement\Models\CustomerRelationship;
use Modules\RentalManagement\Models\Listing;
use Modules\RentalManagement\Models\Owner;
use Modules\RentalManagement\Models\PaymentSchedule;
use Modules\RentalManagement\Models\Unit;
use Modules\RentalManagement\Support\RealEstateSettings;
use Modules\RentalManagement\Support\RealEstateVocabulary as V;

/**
 * دورة حياة العقد: التداخل، جدول الاستحقاقات، الاعتماد، الإنهاء، التجديد.
 *
 * كلّ انتقالِ حالةٍ يمرّ من هنا لا من المتحكّم — فحالة الوحدة والاستحقاقات
 * والسجلّ تتحرّك معاً أو لا يتحرّك شيء.
 */
class ContractService
{
    public const LOG = 're_contract_log';

    /** أنواع الوحدات التجارية والسكنية — يُشتقّ منها الاستخدام حين يغيب عمود `usage_type`. */
    private const COMMERCIAL_UNIT_TYPES = ['shop', 'office', 'warehouse', 'showroom', 'workshop'];

    private const RESIDENTIAL_UNIT_TYPES = ['apartment', 'villa', 'studio', 'floor', 'annex', 'room'];

    /** @var array<string, bool> */
    private static array $columns = [];

    /** فحص وجود عمودٍ يضيفه وكيلٌ آخر — مرّةً واحدة لكلّ طلب. */
    public static function hasColumn(string $table, string $column): bool
    {
        return self::$columns[$table.'.'.$column] ??= Schema::hasColumn($table, $column);
    }

    // ═══════════════ استخدام الوحدة والضريبة ═══════════════

    /** استخدام الوحدة (`residential` / `commercial`): من عمودها إن وُجد، وإلا من نوعها، وإلا من مبناها. */
    public function unitUsage(Unit $unit): string
    {
        if (self::hasColumn('re_units', 'usage_type') && in_array($unit->getAttribute('usage_type'), ['residential', 'commercial'], true)) {
            return $unit->getAttribute('usage_type');
        }

        if (in_array($unit->unit_type, self::COMMERCIAL_UNIT_TYPES, true)) {
            return 'commercial';
        }

        if (in_array($unit->unit_type, self::RESIDENTIAL_UNIT_TYPES, true)) {
            return 'residential';
        }

        return $unit->building?->usage_type === 'commercial' ? 'commercial' : 'residential';
    }

    /** نوع العقد الذي يفرضه استخدام الوحدة. */
    public function contractTypeFor(Unit $unit): string
    {
        return $this->unitUsage($unit) === 'commercial' ? 'commercial_lease' : 'residential_lease';
    }

    /** ملكية المبنى: `own` للمنشأة نفسها، `managed` لمالكٍ خارجي. بلا مالك = `own`. */
    public function ownership(Unit $unit): string
    {
        $building = $unit->building;

        if ($building && self::hasColumn('re_buildings', 'ownership') && in_array($building->getAttribute('ownership'), ['own', 'managed'], true)) {
            return $building->getAttribute('ownership');
        }

        return $building?->owner_id ? 'managed' : 'own';
    }

    /**
     * هل تُطبَّق الضريبة على عقد هذه الوحدة؟ ولماذا؟
     *
     * السكني معفى. التجاري تُطبَّق ضريبته إن كان المبنى للمنشأة نفسها (هي
     * المؤجِّر) أو كان المالك مسجَّلاً في الضريبة؛ وإلا فبدون ضريبة.
     *
     * @return array{usage:string, ownership:string, taxable:bool, reason:string}
     */
    public function taxPolicy(Unit $unit, ?Owner $owner = null): array
    {
        $usage = $this->unitUsage($unit);
        $ownership = $this->ownership($unit);
        $owner = $owner ?? $unit->building?->owner;

        if ($usage === 'residential') {
            return ['usage' => $usage, 'ownership' => $ownership, 'taxable' => false, 'reason' => 'الوحدة سكنية — الإيجار السكني بلا ضريبة.'];
        }

        if ($ownership === 'own' || ! $owner) {
            return ['usage' => $usage, 'ownership' => $ownership, 'taxable' => true, 'reason' => 'المبنى مملوك للمنشأة وهي المؤجِّر — تُطبَّق ضريبة النظام.'];
        }

        if (! self::hasColumn('re_owners', 'is_vat_registered')) {
            return ['usage' => $usage, 'ownership' => $ownership, 'taxable' => true, 'reason' => 'وحدة تجارية — تُطبَّق ضريبة النظام.'];
        }

        return $owner->getAttribute('is_vat_registered')
            ? ['usage' => $usage, 'ownership' => $ownership, 'taxable' => true, 'reason' => 'المالك مسجَّل في الضريبة — تُطبَّق ضريبة النظام لحسابه.']
            : ['usage' => $usage, 'ownership' => $ownership, 'taxable' => false, 'reason' => 'المالك غير مسجَّل في الضريبة — العقد بدون ضريبة.'];
    }

    // ═══════════════ التداخل ═══════════════

    /**
     * عقدٌ آخر يشغل الوحدة في فترةٍ تتقاطع مع هذه؟
     * التقاطع: بداية أحدهما ≤ نهاية الآخر، والعكس.
     */
    public function overlapping(int $unitId, $start, $end, int|array|null $exceptId = null): ?Contract
    {
        return Contract::query()
            ->where('unit_id', $unitId)
            ->whereIn('status', V::CONTRACT_OCCUPYING)
            ->when($exceptId, fn ($q) => $q->whereNotIn('id', array_filter((array) $exceptId)))
            ->whereDate('start_date', '<=', Carbon::parse($end)->toDateString())
            ->whereDate('end_date', '>=', Carbon::parse($start)->toDateString())
            ->first();
    }

    /**
     * يمنع العقدين المتداخلين على الوحدة نفسها — إلا باستثناءٍ **صريح**.
     *
     * حملُ الصلاحية وحده لا يكفي: لو كفى لمرّ المديرُ على التداخل دون أن
     * يراه أصلاً، وهو أكثر من يُنشئ العقود. فالتجاوز قرارٌ يُتَّخذ في كلّ
     * مرّة (`$confirmed`) ويُسجَّل باسم صاحبه.
     */
    public function assertNoOverlap(int $unitId, $start, $end, int|array|null $exceptId = null, bool $confirmed = false): void
    {
        $other = $this->overlapping($unitId, $start, $end, $exceptId);

        if (! $other) {
            return;
        }

        $mayOverride = hasPermission('realestate.contracts.override_overlap');

        if ($mayOverride && $confirmed) {
            OperationLog::record('overlap_override', self::LOG, $other,
                sprintf('أُنشئ عقدٌ متداخل مع العقد **%s** على الوحدة نفسها بصلاحيةٍ استثنائية', $other->code));

            return;
        }

        throw ValidationException::withMessages([
            'unit_id' => sprintf(
                'الوحدة مشغولة بالعقد %s من %s إلى %s. %s',
                $other->code, $other->start_date->format('Y-m-d'), $other->end_date->format('Y-m-d'),
                $mayOverride
                    ? 'تملك صلاحية التجاوز: أكّد «تجاوز منع التداخل» إن كان ذلك مقصوداً — ويُسجَّل باسمك.'
                    : 'اختر فترةً لا تتقاطع معه أو أنهِ العقد القائم أولاً.'
            ),
        ]);
    }

    // ═══════════════ المدّة والقيمة والجدول ═══════════════

    /**
     * مدّة العقد بالأشهر **مع كسر الأيام**: أشهرٌ كاملة من تاريخ البداية ثم
     * الأيام الباقية منسوبةً إلى طول شهرها. 45 يوماً ≈ شهر ونصف لا شهران.
     *
     * يومٌ واحد زائد بعد أشهرٍ كاملة يُهمل: «من 1 أكتوبر إلى 1 أكتوبر» سنةٌ
     * كما يكتبها الناس، لا سنة ويوم.
     */
    public function termMonths($start, $end): float
    {
        $start = Carbon::parse($start)->startOfDay();
        $endExclusive = Carbon::parse($end)->startOfDay()->addDay();

        if ($endExclusive->lte($start)) {
            return 0.0;
        }

        $months = 0;

        while ($start->copy()->addMonthsNoOverflow($months + 1)->lte($endExclusive)) {
            $months++;
        }

        $anchor = $start->copy()->addMonthsNoOverflow($months);
        $days = (int) round($anchor->diffInDays($endExclusive));

        if ($months >= 1 && $days <= 1) {
            return (float) $months;
        }

        $periodDays = max(1, (int) round($anchor->diffInDays($start->copy()->addMonthsNoOverflow($months + 1))));

        return round($months + $days / $periodDays, 6);
    }

    /** المدّة مقرَّبةً إلى أشهرٍ صحيحة — لعمود `duration_months` (عرضٌ وتقارير). */
    public function wholeMonths($start, $end): int
    {
        return max(1, (int) round($this->termMonths($start, $end)));
    }

    /** إجمالي قيمة العقد (قبل الضريبة المضافة) من قيمته السنوية ومدّته بالأشهر وكسورها. */
    public function totalValue(float $annual, float $months): float
    {
        return round($annual / 12 * max(0, $months), 2);
    }

    /** إجمالي قيمة العقد من تاريخَيه مباشرةً. */
    public function totalFor(float $annual, $start, $end): float
    {
        return $this->totalValue($annual, $this->termMonths($start, $end));
    }

    /**
     * يبني جدول الاستحقاقات من شروط العقد. يُعاد بناؤه ما دام العقد بلا
     * تحصيل؛ فإن حُصِّل منه شيء امتنع — الجدول حينها تاريخٌ لا مسودة.
     */
    public function generateSchedule(Contract $contract): void
    {
        if ($contract->isClosed()) {
            throw ValidationException::withMessages([
                'schedule' => 'لا يُعاد بناء جدول استحقاقات عقدٍ '.$contract->status_meta['label'].'.',
            ]);
        }

        // مطالبات الصيانة على المستأجر ليست من شروط العقد: لا تمنع إعادة البناء ولا تُحذف بها
        if ($contract->schedules()->whereIn('kind', ['rent', 'deposit'])->where('amount_paid', '>', 0)->exists()) {
            throw ValidationException::withMessages([
                'schedule' => 'لا يُعاد بناء جدول الاستحقاقات بعد تحصيل دفعاتٍ عليه.',
            ]);
        }

        $contract->schedules()->whereIn('kind', ['rent', 'deposit'])->delete();

        // التأمين المرحَّل من العقد السابق لا يُفوتَر ثانيةً: يُستحقّ `security_deposit` وحده (فرق التجديد)
        if ((float) $contract->security_deposit > 0) {
            PaymentSchedule::create([
                'contract_id' => $contract->id,
                'installment_no' => 0,
                'kind' => 'deposit',
                'due_date' => $contract->start_date,
                'amount_due' => $contract->security_deposit,
            ]);
        }

        foreach ($this->installments($contract) as $row) {
            PaymentSchedule::create(['contract_id' => $contract->id, 'kind' => 'rent'] + $row);
        }

        $contract->schedules()->get()->each->refreshStatus();
    }

    /**
     * أقساط الإيجار لمدّة العقد كاملةً بقيمته الحالية.
     *
     * مدّةٌ تنقسم على دورية السداد: أقساطٌ متساوية وكسور التقريب على الأخير.
     * مدّةٌ لا تنقسم: أقساطٌ كاملة ثم قسطٌ أخير بنسبة ما بقي من الأيام.
     *
     * @return array<int, array{installment_no:int, due_date:Carbon, amount_due:float}>
     */
    public function installments(Contract $contract, ?float $annual = null, $end = null): array
    {
        $step = V::PAYMENT_FREQUENCIES[$contract->payment_frequency]['months'] ?? 1;
        $months = $this->termMonths($contract->start_date, $end ?? $contract->end_date);
        $count = max(1, (int) ceil($months / $step - 1e-6));
        $factor = $contract->taxFactor();
        $annual = $annual ?? (float) $contract->annual_value;

        $gross = round($this->totalValue($annual, $months) * $factor, 2);
        $aligned = abs($months - $count * $step) < 1e-6;
        $each = $aligned ? round($gross / $count, 2) : round($annual / 12 * $step * $factor, 2);

        $rows = [];

        for ($i = 0; $i < $count; $i++) {
            $rows[] = [
                'installment_no' => $i + 1,
                'due_date' => $contract->start_date->copy()->addMonthsNoOverflow($i * $step),
                'amount_due' => $i === $count - 1 ? max(0, round($gross - $each * ($count - 1), 2)) : $each,
            ];
        }

        return $rows;
    }

    // ═══════════════ الاعتماد والإلغاء ═══════════════

    public function submit(Contract $contract, bool $overlapConfirmed = false): void
    {
        $this->assertNoOverlap($contract->unit_id, $contract->start_date, $contract->end_date, [$contract->id, $contract->previous_contract_id], $overlapConfirmed);

        $contract->update(['status' => 'pending_approval']);
        OperationLog::record('submitted', self::LOG, $contract, sprintf('أُرسل العقد **%s** للاعتماد', $contract->code));
    }

    public function approve(Contract $contract, bool $overlapConfirmed = false): void
    {
        // عقدٌ سابق سُجِّل للتاريخ: انتهت مدّته قبل اليوم، فيُعتمد «منتهياً» ولا يشغل وحدته
        $historic = $contract->end_date->lt(now()->startOfDay());

        DB::transaction(function () use ($contract, $overlapConfirmed, $historic) {
            $this->assertNoOverlap($contract->unit_id, $contract->start_date, $contract->end_date, [$contract->id, $contract->previous_contract_id], $overlapConfirmed);

            if (! $contract->schedules()->exists()) {
                $this->generateSchedule($contract);
            }

            $contract->update([
                'status' => $historic ? 'expired' : 'active',
                'approved_by' => auth()->id(),
                'approved_at' => now(),
                'signed_at' => $contract->signed_at ?: now(),
            ]);

            $this->syncUnit($contract);
            $this->linkTenant($contract);

            // العرض الذي أثمر عقداً يُقفل بنتيجته — لا يبقى منشوراً على وحدةٍ مؤجرة
            if ($contract->listing_id) {
                Listing::whereKey($contract->listing_id)->whereIn('status', ['active', 'paused'])
                    ->update(['status' => $contract->contract_type === 'sale' ? 'purchased' : 'rented']);
            }

            /*
                عقدُ التجديد يُنهي سابقَه **يوم يبدأ هو** لا يوم يُعتمَد: التجديد
                يُبرَم عادةً قبل نهاية العقد بأسابيع، والسابق سارٍ حتى آخر يومٍ
                فيه — تُحصَّل أقساطه ويُحسب في الإشغال. فإن كان الجديد لم يبدأ
                بعدُ عاد السابق «سارياً» (انتهت مرحلة «قيد التجديد») ويتكفّل
                الكنس اليومي بإنهائه عند تاريخه.
            */
            if ($contract->previous_contract_id) {
                $started = $contract->start_date->lte(now()->endOfDay());

                Contract::whereKey($contract->previous_contract_id)
                    ->whereIn('status', ['active', 'renewing'])
                    ->update(['status' => $started ? 'expired' : 'active']);

                $this->syncUnit($contract->fresh());
            }
        });

        OperationLog::record('approved', self::LOG, $contract, $historic
            ? sprintf('اعتُمد العقد السابق **%s** تسجيلاً تاريخياً (منتهٍ)', $contract->code)
            : sprintf('اعتُمد العقد **%s** وصار سارياً', $contract->code));

        if ($contract->created_by) {
            notifications::create([
                'user_id' => $contract->created_by,
                'receiver_id' => $contract->created_by,
                'title' => 'اعتُمد عقد الإيجار رقم '.$contract->code,
                'description' => 'اعتُمد عقد الإيجار رقم '.$contract->code.($historic ? ' (تسجيل تاريخي)' : ' وصار سارياً'),
                'type' => 'success',
            ]);
        }
    }

    public function reject(Contract $contract, ?string $reason): void
    {
        $contract->update(['status' => 'draft']);
        OperationLog::record('rejected', self::LOG, $contract,
            sprintf('أُعيد العقد **%s** إلى المسودة%s', $contract->code, $reason ? ' — '.$reason : ''));
    }

    /** إلغاء عقدٍ لم يُحصَّل عليه شيء. ما حُصِّل عليه يُنهى ولا يُلغى. */
    public function cancel(Contract $contract, ?string $reason): void
    {
        if ($contract->payments()->whereIn('posting_status', ['posted', 'cheque_pending'])->exists()) {
            throw ValidationException::withMessages([
                'contract' => 'على العقد دفعاتٌ مرحّلة — استعمل «إنهاء العقد» أو ألغِ الدفعات أولاً.',
            ]);
        }

        DB::transaction(function () use ($contract, $reason) {
            $contract->schedules()->update(['status' => 'cancelled']);
            $contract->update(['status' => 'cancelled', 'terminated_at' => now(), 'termination_reason' => $reason]);
            $contract->renewals()->whereIn('renewal_status', ['draft', 'review'])->update(['renewal_status' => 'rejected']);
            $this->syncUnit($contract);
        });

        OperationLog::record('cancelled', self::LOG, $contract,
            sprintf('أُلغي العقد **%s**%s', $contract->code, $reason ? ' — '.$reason : ''));
    }

    // ═══════════════ الإنهاء ═══════════════

    /**
     * إنهاء عقدٍ سارٍ: مراجعة المستحقات ← تسوية التأمين ← إغلاق ← الوحدة شاغرة.
     *
     * أقساط الإيجار غير المسدَّدة التي لم يحن أجلها تُلغى، وكذلك قسط التأمين
     * غير المقبوض؛ ما استحقّ قبل تاريخ الإنهاء يبقى ديناً. ثم تُقيَّد تسوية
     * التأمين (ردّاً وخصماً) إن وُجدت خدمتها.
     *
     * @param  string  $deductTo  لمن يذهب المخصوم: `owner` أو `company`
     */
    public function terminate(Contract $contract, string $date, ?string $reason, float $depositDeducted = 0, ?int $treasuryAccountId = null, string $deductTo = 'owner'): void
    {
        $date = Carbon::parse($date)->startOfDay();

        if ($date->gt(now()->endOfDay())) {
            throw ValidationException::withMessages(['terminated_at' => 'تاريخ الإنهاء لا يكون في المستقبل.']);
        }

        if ($date->lt($contract->start_date) || $date->gt($contract->end_date)) {
            throw ValidationException::withMessages(['terminated_at' => sprintf(
                'تاريخ الإنهاء يجب أن يقع داخل مدّة العقد (%s ← %s).', $contract->start_date->format('Y-m-d'), $contract->end_date->format('Y-m-d'))]);
        }

        $held = $contract->heldDeposit();

        if ($depositDeducted > $held + 0.004) {
            throw ValidationException::withMessages([
                'deposit_deducted' => 'المخصوم من التأمين يتجاوز التأمين المحتجز ('.number_format($held, 2).').',
            ]);
        }

        $refund = round(max(0, $held - $depositDeducted), 2);

        DB::transaction(function () use ($contract, $date, $reason, $depositDeducted, $refund, $treasuryAccountId, $deductTo) {
            $contract->schedules()->where('kind', 'rent')->where('amount_paid', 0)
                ->whereDate('due_date', '>', $date->toDateString())
                ->update(['status' => 'cancelled']);

            // التأمين الذي لم يُقبض لا يبقى «متأخراً» على عقدٍ أُغلق
            $contract->schedules()->where('kind', 'deposit')->where('status', '!=', 'cancelled')->get()
                ->each(function (PaymentSchedule $schedule) {
                    if ((float) $schedule->amount_paid <= 0) {
                        $schedule->update(['status' => 'cancelled']);
                    } elseif ($schedule->balance > 0) {
                        $schedule->update(['amount_due' => $schedule->amount_paid, 'status' => 'paid']);
                    }
                });

            $contract->renewals()->whereIn('renewal_status', ['draft', 'review'])->update(['renewal_status' => 'rejected']);

            $contract->update([
                'status' => 'terminated',
                'terminated_at' => $date,
                'termination_reason' => $reason,
                'deposit_deducted' => $depositDeducted,
                'deposit_refunded' => $refund,
            ]);

            $this->syncUnit($contract);

            // قيد التسوية (ردّ التأمين وخصمه) — داخل المعاملة نفسها: إن تعذّر القيد لم يُنهَ العقد
            if ($refund > 0 || $depositDeducted > 0) {
                $this->settleDeposit($contract, $depositDeducted, $treasuryAccountId, $reason, $deductTo, $date->toDateString());

                // تأمينٌ مرحَّل سُوّي على عقود السلسلة: هذا العقد يعرض إجمالي ما قُرِّر عند إنهائه
                if ((float) $contract->deposit_carried > 0) {
                    $contract->update(['deposit_deducted' => $depositDeducted, 'deposit_refunded' => $refund]);
                }
            }
        });

        OperationLog::record('terminated', self::LOG, $contract, sprintf(
            'أُنهي العقد **%s** بتاريخ %s — خُصم من التأمين **%s** (%s) ويُردّ **%s**',
            $contract->code, $date->format('Y-m-d'), number_format($depositDeducted, 2),
            $deductTo === 'company' ? 'لصالح المنشأة' : 'لصالح المالك', number_format($refund, 2)
        ));
    }

    /**
     * يقيّد تسوية التأمين عبر `DepositService` إن وُجدت.
     *
     * التأمين المرحَّل قُبض على العقد السابق، فقيده هناك: تُسوّى السلسلة
     * (هذا العقد ثم سابقه…) ويُوزَّع المخصوم على ما احتُجز في كلٍّ منها.
     */
    protected function settleDeposit(Contract $contract, float $deducted, ?int $treasuryAccountId, ?string $reason, string $deductTo, string $date): void
    {
        $settler = '\\Modules\\RentalManagement\\Services\\DepositService';

        if (! class_exists($settler) || ! method_exists($settler, 'settle')) {
            return;
        }

        $service = app($settler);
        $deductTo = $deductTo === 'owner' ? 'owner' : 'office';

        if (! method_exists($service, 'held')) {
            $service->settle($contract, $deducted, $treasuryAccountId, $reason, $deductTo, $date);

            return;
        }

        $remaining = $deducted;
        $node = $contract;

        for ($depth = 0; $node && $depth < 25; $depth++) {
            $held = (float) $service->held($node);

            if ($held > 0) {
                $part = round(min($remaining, $held), 2);
                $service->settle($node, $part, $treasuryAccountId, $reason, $deductTo, $date);
                $remaining = round($remaining - $part, 2);
            }

            if ((float) $node->deposit_carried <= 0 || ! $node->previous_contract_id) {
                break;
            }

            $node = Contract::withoutGlobalScopes()->whereNull('deleted_at')->find($node->previous_contract_id);
        }

        // التسوية تكتب أرقامها على كلّ عقدٍ في السلسلة؛ العقد المُنهى يحمل إجمالي ما قُرِّر عند إنهائه
        $contract->refresh();
    }

    // ═══════════════ تعديل عقدٍ معتمد ═══════════════

    /**
     * تعديل عقدٍ معتمد: كلّ حقلٍ تغيّر يُكتب في `re_contract_amendments`
     * بقيمتَيه ومن غيّره وسببه وتاريخ سريانه — **ثم يلحق به الجدول**:
     *
     * تغيّر القيمة السنوية أو تاريخ النهاية يعيد حساب أقساط الإيجار **غير
     * المدفوعة** المستحقّة من تاريخ السريان، ويضيف أقساط التمديد أو يحذف ما
     * بعد النهاية الجديدة. القسط المدفوع أو المدفوع جزئياً لا يُمسّ، وما
     * استحقّ قبل تاريخ السريان يبقى بقيمته القديمة. ثم يُعاد حساب إجمالي
     * العقد من الجدول نفسه، فتتطابق صفحة العقد وطباعته مع أقساطه.
     *
     * @return array{changed:array<int,string>, updated:int, added:int, removed:int, total:float}
     */
    public function amend(Contract $contract, array $changes, ?string $reason, $effectiveDate = null): array
    {
        $effective = Carbon::parse($effectiveDate ?: now())->startOfDay();
        $summary = ['changed' => [], 'updated' => 0, 'added' => 0, 'removed' => 0, 'total' => (float) $contract->total_value];
        $before = ['total_value' => (float) $contract->total_value, 'annual_value' => (float) $contract->annual_value, 'end_date' => $contract->end_date->format('Y-m-d')];

        DB::transaction(function () use ($contract, $changes, $reason, $effective, &$summary) {
            foreach ($changes as $field => $new) {
                $old = $contract->getAttribute($field);
                $oldText = $old instanceof \DateTimeInterface ? $old->format('Y-m-d') : (string) $old;
                $same = is_numeric($old) && is_numeric($new) ? abs((float) $old - (float) $new) < 0.005 : $oldText === (string) $new;

                if ($same) {
                    continue;
                }

                $row = [
                    'contract_id' => $contract->id,
                    'user_id' => auth()->id(),
                    'field' => $field,
                    'old_value' => $oldText,
                    'new_value' => (string) $new,
                    'reason' => $reason,
                ];

                if (self::hasColumn('re_contract_amendments', 'effective_date')) {
                    $row['effective_date'] = $effective->toDateString();
                }

                ContractAmendment::create($row);

                $contract->setAttribute($field, $new);
                $summary['changed'][] = $field;
            }

            if (array_intersect($summary['changed'], ['annual_value', 'end_date'])) {
                $summary = array_merge($summary, $this->rebuildUnpaid($contract, $effective));
                $contract->duration_months = $this->wholeMonths($contract->start_date, $contract->end_date);
                $contract->total_value = $summary['total'];
            }

            $contract->save();
        });

        if ($summary['changed']) {
            OperationLog::record('amended', self::LOG, $contract, sprintf(
                'عُدّل العقد المعتمد **%s** ويسري من %s%s — أُعيد حساب %d قسطاً، أُضيف %d وحُذف %d، والإجمالي **%s**',
                $contract->code, $effective->format('Y-m-d'), $reason ? ' ('.$reason.')' : '',
                $summary['updated'], $summary['added'], $summary['removed'], number_format($summary['total'], 2)
            ), [
                'before' => $before,
                'after' => ['total_value' => $summary['total'], 'annual_value' => (float) $contract->annual_value, 'end_date' => $contract->end_date->format('Y-m-d')],
            ]);
        }

        return $summary;
    }

    /**
     * يعيد بناء أقساط الإيجار غير المدفوعة المستحقّة من تاريخ السريان على
     * شروط العقد الحالية (بعد التعديل)، ويعيد إجمالي العقد قبل الضريبة.
     *
     * @return array{updated:int, added:int, removed:int, total:float}
     */
    protected function rebuildUnpaid(Contract $contract, Carbon $effective): array
    {
        $target = $this->installments($contract);
        $count = count($target);
        $existing = $contract->schedules()->where('kind', 'rent')->where('status', '!=', 'cancelled')->get()->keyBy('installment_no');
        $result = ['updated' => 0, 'added' => 0, 'removed' => 0, 'total' => 0.0];

        // أقساطٌ بعد النهاية الجديدة: المدفوع منها يمنع التقصير، وغير المدفوع يُحذف
        foreach ($existing as $no => $schedule) {
            if ($no <= $count) {
                continue;
            }

            if ((float) $schedule->amount_paid > 0) {
                throw ValidationException::withMessages([
                    'end_date' => sprintf('القسط رقم %d (استحقاق %s) محصَّل عليه — لا تُقصَّر مدّة العقد إلى ما قبله.', $no, $schedule->due_date->format('Y-m-d')),
                ]);
            }

            $schedule->allocations()->exists() ? $schedule->update(['status' => 'cancelled']) : $schedule->delete();
            $existing->forget($no);
            $result['removed']++;
        }

        $step = V::PAYMENT_FREQUENCIES[$contract->payment_frequency]['months'] ?? 1;
        $months = $this->termMonths($contract->start_date, $contract->end_date);
        $factor = $contract->taxFactor();
        $full = round((float) $contract->annual_value / 12 * $step * $factor, 2);

        foreach ($target as $i => $row) {
            // قيمة القسط بالسعر الجديد: دوريةٌ كاملة، والأخير بنسبة ما بقي من المدّة
            $covered = $i === $count - 1 ? max(0, $months - $step * ($count - 1)) : $step;
            $amount = abs($covered - $step) < 1e-6 ? $full : round((float) $contract->annual_value / 12 * $covered * $factor, 2);

            $schedule = $existing->get($row['installment_no']);

            if (! $schedule) {
                PaymentSchedule::create(['contract_id' => $contract->id, 'kind' => 'rent', 'installment_no' => $row['installment_no'], 'due_date' => $row['due_date'], 'amount_due' => $amount])->refreshStatus();
                $result['added']++;

                continue;
            }

            // مدفوعٌ أو مدفوعٌ جزئياً، أو استحقّ قبل تاريخ السريان: تاريخٌ لا يُمسّ
            if ((float) $schedule->amount_paid > 0 || $schedule->due_date->lt($effective)) {
                continue;
            }

            if (abs((float) $schedule->amount_due - $amount) >= 0.005) {
                $schedule->update(['amount_due' => $amount]);
                $schedule->refreshStatus();
                $result['updated']++;
            }
        }

        $gross = (float) $contract->schedules()->where('kind', 'rent')->where('status', '!=', 'cancelled')->reorder()->sum('amount_due');
        $result['total'] = round($gross / $factor, 2);

        return $result;
    }

    // ═══════════════ التجديد ═══════════════

    /** التجديد يبدأ بعد نهاية العقد الحالي — لا عقدان ساريان على الوحدة نفسها. */
    public function assertRenewalAfterSource(Contract $source, $newStart): void
    {
        if (Carbon::parse($newStart)->startOfDay()->lte($source->end_date)) {
            throw ValidationException::withMessages([
                'new_start_date' => sprintf(
                    'العقد الحالي سارٍ حتى %s — يبدأ التجديد من %s أو بعده.',
                    $source->end_date->format('Y-m-d'), $source->end_date->copy()->addDay()->format('Y-m-d')
                ),
            ]);
        }
    }

    /**
     * إتمام التجديد: عقدٌ جديد مرتبطٌ بالسابق. القديم لا يُمسّ تاريخُه —
     * تتغيّر حالتُه فقط حين يسري الجديد. والتأمين المحتجز يُرحَّل إلى الجديد
     * (`deposit_carried`) ولا يُفوتَر إلا فرقُ الزيادة.
     */
    public function completeRenewal(ContractRenewal $renewal): Contract
    {
        return DB::transaction(function () use ($renewal) {
            $renewal = ContractRenewal::whereKey($renewal->id)->lockForUpdate()->first();

            if (! $renewal || ! in_array($renewal->renewal_status, ['draft', 'review', 'approved'], true)) {
                throw ValidationException::withMessages([
                    'renewal' => $renewal && $renewal->renewal_status === 'completed' ? 'التجديد مُعتمد سابقاً.' : 'طلب التجديد لم يعد قائماً.',
                ]);
            }

            $source = $renewal->source;

            if (! $source || in_array($source->status, ['cancelled', 'terminated'], true)) {
                throw ValidationException::withMessages(['renewal' => 'العقد الأصلي أُلغي أو أُنهي — لا يُجدَّد.']);
            }

            $this->assertRenewalAfterSource($source, $renewal->new_start_date);

            $new = $source->replicate([
                'code', 'status', 'signed_at', 'approved_by', 'approved_at', 'terminated_at',
                'termination_reason', 'deposit_refunded', 'deposit_deducted', 'last_alert_sent_at', 'is_historical',
            ]);

            $carried = $source->heldDeposit();

            $new->fill([
                'code' => next_document_number('re-contract'),
                'previous_contract_id' => $source->id,
                'listing_id' => null,
                'start_date' => $renewal->new_start_date,
                'end_date' => $renewal->new_end_date,
                'duration_months' => $this->wholeMonths($renewal->new_start_date, $renewal->new_end_date),
                'annual_value' => $renewal->new_rent_value,
                'total_value' => $this->totalFor((float) $renewal->new_rent_value, $renewal->new_start_date, $renewal->new_end_date),
                'payment_frequency' => $renewal->payment_frequency,
                // التأمين القائم يُرحَّل ويبقى محتجزاً؛ يُستحقّ فرقُ الزيادة وحده
                'security_deposit' => max(0, (float) $renewal->deposit_adjustment),
                'deposit_carried' => $carried,
                // العملة لقطةٌ من العقد الأصلي لا من إعداد النظام الحالي
                'currency' => $source->currency_code,
                'status' => 'draft',
                'created_by' => auth()->id(),
            ]);
            $new->save();

            foreach ($source->terms as $term) {
                $new->terms()->create($term->only(['title', 'body', 'sort']));
            }

            $this->generateSchedule($new);

            $renewal->update(['renewal_status' => 'completed', 'renewed_contract_id' => $new->id, 'approved_by' => auth()->id()]);

            if (in_array($source->status, ['active', 'renewing'], true)) {
                $source->update(['status' => 'renewing']);
            }

            // التجديد امتدادٌ للإشغال نفسه — يسري مباشرةً بلا فحص تداخلٍ مع سابقه
            $this->approve($new);

            OperationLog::record('renewed', self::LOG, $source, sprintf(
                'جُدّد العقد **%s** بالعقد **%s**%s', $source->code, $new->code,
                $carried > 0 ? ' — رُحِّل تأمينٌ محتجز قدره **'.number_format($carried, 2).'**' : ''));

            return $new;
        });
    }

    // ═══════════════ الوحدة والمستأجر ═══════════════

    /** حالة الوحدة تتبع عقدها — متى فُعِّل ذلك في الإعدادات. */
    public function syncUnit(Contract $contract): void
    {
        if (! RealEstateSettings::enabled('auto_update_unit_status') || ! $contract->unit) {
            return;
        }

        $unit = $contract->unit;

        $occupied = Contract::where('unit_id', $unit->id)
            ->whereIn('status', ['active', 'renewing'])
            ->whereDate('start_date', '<=', now()->toDateString())
            ->exists();

        $reserved = ! $occupied && Contract::where('unit_id', $unit->id)
            ->whereIn('status', V::CONTRACT_OCCUPYING)->exists();

        // وحدةٌ تحت الصيانة أو غير متاحة قرارٌ يدوي لا يمحوه انتهاء عقد
        if (! $occupied && ! $reserved && in_array($unit->occupancy_status, ['maintenance', 'unavailable'], true)) {
            return;
        }

        $unit->markOccupancy($occupied ? 'rented' : ($reserved ? 'reserved' : 'vacant'));
    }

    /** يُثبت علاقة «مستأجر» للعميل المركزي — مرّةً لكلّ عقد. */
    protected function linkTenant(Contract $contract): void
    {
        CustomerRelationship::updateOrCreate(
            ['client_id' => $contract->tenant_client_id, 'contract_id' => $contract->id],
            [
                'roles' => ['tenant'],
                'unit_id' => $contract->unit_id,
                'building_id' => $contract->building_id,
                'listing_id' => $contract->listing_id,
                'relationship_status' => 'active',
                'last_activity_at' => now(),
                'branch_id' => $contract->branch_id,
            ],
        );
    }

    // ═══════════════ الكنس اليومي ═══════════════

    /**
     * صيانة يومية (من الكرون): عقودٌ انتهت مدّتها تصير «منتهية» — السارية،
     * و«قيد التجديد» التي فات تاريخها ولم يُعتمد تجديدها — وأخرى حلّ موعد
     * بدئها تشغل وحدتها، والاستحقاقات تُحدَّث حالاتها.
     *
     * @return array{expired:int, schedules:int}
     */
    public function sweep(): array
    {
        $expired = 0;

        Contract::whereIn('status', ['active', 'renewing'])->whereDate('end_date', '<', now()->toDateString())
            ->get()->each(function (Contract $contract) use (&$expired) {
                $wasRenewing = $contract->status === 'renewing';

                $contract->update(['status' => 'expired']);
                $this->syncUnit($contract);
                OperationLog::record('expired', self::LOG, $contract, $wasRenewing
                    ? sprintf('انتهت مدّة العقد **%s** وطلب تجديده لم يُعتمد — صار منتهياً', $contract->code)
                    : sprintf('انتهت مدّة العقد **%s**', $contract->code));
                $expired++;
            });

        Contract::where('status', 'active')->whereDate('start_date', now()->toDateString())
            ->get()->each(fn (Contract $contract) => $this->syncUnit($contract));

        $schedules = PaymentSchedule::open()->whereDate('due_date', '<=', now()->toDateString())->get();
        $schedules->each->refreshStatus();

        Listing::where('status', 'active')->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '<', now()->toDateString())->update(['status' => 'expired']);

        return ['expired' => $expired, 'schedules' => $schedules->count()];
    }

    /** الاسم القديم — يستدعيه أمر الكنس المجدول. */
    public function dailySweep(): array
    {
        return $this->sweep();
    }
}
