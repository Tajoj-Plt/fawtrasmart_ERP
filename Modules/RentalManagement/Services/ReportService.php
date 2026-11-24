<?php

namespace Modules\RentalManagement\Services;

use Carbon\Carbon;
use Modules\RentalManagement\Models\Building;
use Modules\RentalManagement\Models\Contract;
use Modules\RentalManagement\Models\ContractRenewal;
use Modules\RentalManagement\Models\Listing;
use Modules\RentalManagement\Models\MaintenanceRequest;
use Modules\RentalManagement\Models\Owner;
use Modules\RentalManagement\Models\OwnerEntry;
use Modules\RentalManagement\Models\Payment;
use Modules\RentalManagement\Models\PaymentAllocation;
use Modules\RentalManagement\Models\PaymentSchedule;
use Modules\RentalManagement\Models\Unit;
use Modules\RentalManagement\Support\RealEstateVocabulary as V;

/**
 * مكتبة تقارير العقارات. كلّ تقرير دالّةٌ تُرجع الشكل نفسه:
 *
 *   ['columns' => [key => ['label' => …, 'numeric' => bool]], 'rows' => [...], 'totals' => [...], 'note' => ?string]
 *
 * و`_url` في الصفّ رابطُ التنقيب (drill-down) إلى المعاملات المكوِّنة للرقم.
 *
 * **تشغيلي أم مالي؟** التقارير المالية هنا تُبنى على الدفعات **المرحّلة**
 * وحدها (`posting_status = posted`) وتقول ذلك في ملاحظتها؛ أمّا الاستحقاقات
 * فتشغيليةٌ بطبعها (التزامٌ تعاقدي لا قيد) ويُصرَّح بذلك أيضاً.
 */
class ReportService
{
    public const FINANCIAL_NOTE = 'مبنيّ على الدفعات المرحّلة محاسبياً فقط؛ الملغاة والمسودات مستبعدة.';

    public const OPERATIONAL_NOTE = 'تقرير تشغيلي من جدول الاستحقاقات التعاقدية — ليس قيوداً محاسبية.';

    /** @return array<string, array{title:string, group:string, icon:string, financial:bool, description:string, needs?:string}> */
    public static function catalog(): array
    {
        return [
            'occupancy' => ['title' => 'الإشغال وأداء العقارات', 'group' => 'المحفظة', 'icon' => 'pie-chart', 'financial' => false, 'description' => 'الوحدات والمؤجَّر والشاغر ونسبة الإشغال وقيمة العقود لكلّ عقار.'],
            'vacant_units' => ['title' => 'الوحدات الشاغرة', 'group' => 'المحفظة', 'icon' => 'unlock', 'financial' => false, 'description' => 'الوحدات الشاغرة ومدّة شغورها وإيجارها الإرشادي المهدَر.'],
            'units_distribution' => ['title' => 'توزيع الوحدات', 'group' => 'المحفظة', 'icon' => 'grid', 'financial' => false, 'description' => 'الوحدات بحسب النوع وحالة الإشغال.'],
            'contracts_active' => ['title' => 'العقود السارية', 'group' => 'العقود', 'icon' => 'file-text', 'financial' => false, 'description' => 'كلّ عقدٍ سارٍ بقيمته ومدّته ورصيده المتبقّي.'],
            'contracts_expiring' => ['title' => 'العقود القريبة من الانتهاء', 'group' => 'العقود', 'icon' => 'clock', 'financial' => false, 'description' => 'العقود التي تنتهي خلال الفترة المختارة وحالة تجديدها.'],
            'renewals' => ['title' => 'التجديدات', 'group' => 'العقود', 'icon' => 'refresh-cw', 'financial' => false, 'description' => 'طلبات التجديد ونسب الزيادة وما اكتمل منها.'],
            'dues' => ['title' => 'المستحقات', 'group' => 'المالية', 'icon' => 'calendar', 'financial' => true, 'description' => 'استحقاقات الفترة: المستحق والمحصَّل والمتبقّي.'],
            'collections' => ['title' => 'التحصيلات', 'group' => 'المالية', 'icon' => 'dollar-sign', 'financial' => true, 'description' => 'الدفعات المرحّلة في الفترة بحسب الطريقة والعقد.'],
            'aging' => ['title' => 'أعمار الديون والمتأخرات', 'group' => 'المالية', 'icon' => 'alert-circle', 'financial' => true, 'description' => 'المتأخرات لكلّ مستأجر موزّعةً على شرائح العمر.'],
            'deposits' => ['title' => 'التأمينات', 'group' => 'المالية', 'icon' => 'shield', 'financial' => true, 'description' => 'التأمين المقبوض والمخصوم والمردود والمحتجز لكلّ عقد.'],
            'property_income' => ['title' => 'دخل ومصروف العقار', 'group' => 'المالية', 'icon' => 'trending-up', 'financial' => true, 'description' => 'المحصَّل مقابل تكاليف الصيانة وصافي الدخل التشغيلي لكلّ عقار.'],
            'tenant_statement' => ['title' => 'كشف حساب مستأجر', 'group' => 'العملاء', 'icon' => 'user', 'financial' => true, 'description' => 'استحقاقات المستأجر ودفعاته ورصيده الجاري.', 'needs' => 'client_id'],
            'owner_statement' => ['title' => 'كشف حساب مالك', 'group' => 'العملاء', 'icon' => 'briefcase', 'financial' => true, 'description' => 'كشف حساب المالك الجاري: الإيجار المحصَّل له، وعمولة الإدارة والصيانة والتحويلات عليه، ورصيده.', 'needs' => 'owner_id'],
            'listings_performance' => ['title' => 'أداء العروض', 'group' => 'العروض', 'icon' => 'tag', 'financial' => false, 'description' => 'المشاهدات والاستفسارات والمعاينات والتحويل إلى عقود.'],
            'maintenance_summary' => ['title' => 'الصيانة: الأعطال والتكاليف وزمن الإنجاز', 'group' => 'الصيانة', 'icon' => 'tool', 'financial' => false, 'description' => 'الطلبات بحسب التصنيف ومتوسط زمن الإنجاز والتكلفة.'],
            'contractors' => ['title' => 'أداء الفنيين والمقاولين', 'group' => 'الصيانة', 'icon' => 'users', 'financial' => false, 'description' => 'الطلبات المسندة والمنجزة ومتوسط زمن الإنجاز لكلّ مسؤول.'],
            'owner_summary' => ['title' => 'ملخص الملّاك', 'group' => 'الإدارة', 'icon' => 'award', 'financial' => true, 'description' => 'عقارات كلّ مالك ووحداته وإشغاله ومحصَّله وصافي دخله.'],
        ];
    }

    public function run(string $key, array $f, callable $scope): array
    {
        $f['from'] = Carbon::parse($f['from_date'] ?? now()->startOfYear())->startOfDay();
        $f['to'] = Carbon::parse($f['to_date'] ?? now()->endOfYear())->endOfDay();

        $method = 'report'.str_replace(' ', '', ucwords(str_replace('_', ' ', $key)));
        abort_unless(isset(self::catalog()[$key]) && method_exists($this, $method), 404);

        $result = $this->{$method}($f, $scope) + ['totals' => [], 'note' => null];
        $result['note'] ??= self::catalog()[$key]['financial'] ? self::FINANCIAL_NOTE : null;

        return $result;
    }

    // ═══════════════ المحفظة ═══════════════

    protected function buildingStats(array $f, callable $scope)
    {
        return $this->filterBuildings($scope(Building::query()), $f)->with('owner')
            ->withCount([
                'units' => fn ($q) => $scope($q),
                'units as rented' => fn ($q) => $scope($q)->where('occupancy_status', 'rented'),
                'units as vacant' => fn ($q) => $scope($q)->where('occupancy_status', 'vacant'),
            ])->get()->map(function (Building $b) use ($f, $scope) {
                // أرقام العقار من عقوده وصيانته **داخل النطاق** — لا كلّ ما في المبنى
                $b->active_value = (float) $scope(Contract::query())->where('building_id', $b->id)->whereIn('status', ['active', 'renewing'])->sum('annual_value');
                $b->collected = $this->collectedFor(fn ($q) => $scope($q)->where('re_contracts.building_id', $b->id), $f);
                $b->arrears = (float) PaymentSchedule::overdue()->whereHas('contract', fn ($q) => $scope($q)->where('re_contracts.building_id', $b->id))
                    ->selectRaw('COALESCE(SUM(amount_due - amount_paid),0) t')->value('t');
                $b->maintenance_cost = (float) $scope(MaintenanceRequest::query())->where('building_id', $b->id)
                    ->whereBetween('created_at', [$f['from'], $f['to']])->sum('actual_cost');

                return $b;
            });
    }

    protected function reportOccupancy(array $f, callable $scope): array
    {
        $rows = $this->buildingStats($f, $scope)->map(fn ($b) => [
            'building' => $b->name, 'city' => $b->city, 'owner' => $b->owner->display_name ?? '—',
            'units' => $b->units_count, 'rented' => $b->rented, 'vacant' => $b->vacant,
            'occupancy' => $b->units_count ? round($b->rented / $b->units_count * 100, 1) : 0,
            'active_value' => $b->active_value, 'collected' => $b->collected, 'arrears' => $b->arrears,
            'maintenance_cost' => $b->maintenance_cost,
            '_url' => route('realestate.buildings.show', $b->id),
        ])->all();

        $units = array_sum(array_column($rows, 'units'));
        $rented = array_sum(array_column($rows, 'rented'));

        return [
            'columns' => $this->cols(['building' => 'العقار', 'city' => 'المدينة', 'owner' => 'المالك', 'units' => '#الوحدات', 'rented' => '#مؤجرة',
                'vacant' => '#شاغرة', 'occupancy' => '#الإشغال %', 'active_value' => '#قيمة العقود السارية', 'collected' => '#المحصَّل', 'arrears' => '#المتأخرات', 'maintenance_cost' => '#الصيانة']),
            'rows' => $rows,
            'totals' => ['units' => $units, 'rented' => $rented, 'vacant' => array_sum(array_column($rows, 'vacant')),
                'occupancy' => $units ? round($rented / $units * 100, 1) : 0] + $this->sum($rows, ['active_value', 'collected', 'arrears', 'maintenance_cost']),
            'note' => 'المحصَّل والصيانة ضمن الفترة المختارة؛ الإشغال والمتأخرات لحظيّان.',
        ];
    }

    protected function reportVacantUnits(array $f, callable $scope): array
    {
        $rows = $this->filterUnits($scope(Unit::query()), $f)->where('occupancy_status', 'vacant')->with('building')->get()
            ->map(function (Unit $u) {
                $days = $u->vacant_since ? (int) $u->vacant_since->diffInDays(now()) : null;
                $daily = ($u->rent_period === 'monthly' ? $u->base_rent * 12 : $u->base_rent) / 365;

                return [
                    'unit' => $u->full_label, 'type' => $u->type_label, 'area' => (float) $u->area_sqm,
                    'base_rent' => (float) $u->base_rent, 'period' => V::label(V::RENT_PERIODS, $u->rent_period),
                    'since' => $u->vacant_since?->format('Y-m-d') ?? '—', 'days' => $days ?? 0,
                    'lost' => round($daily * ($days ?? 0), 2),
                    'listed' => $u->listings()->where('status', 'active')->exists() ? 'نعم' : 'لا',
                    '_url' => route('realestate.units.show', $u->id),
                ];
            })->sortByDesc('days')->values()->all();

        return [
            'columns' => $this->cols(['unit' => 'الوحدة', 'type' => 'النوع', 'area' => '#المساحة م²', 'base_rent' => '#الإيجار الإرشادي', 'period' => 'الدورية',
                'since' => 'شاغرة منذ', 'days' => '#أيام الشغور', 'lost' => '#إيراد مهدَر تقديري', 'listed' => 'معروضة؟']),
            'rows' => $rows,
            'totals' => $this->sum($rows, ['lost']),
        ];
    }

    protected function reportUnitsDistribution(array $f, callable $scope): array
    {
        $units = $this->filterUnits($scope(Unit::query()), $f)->get(['unit_type', 'occupancy_status']);

        $rows = $units->groupBy('unit_type')->map(function ($group, $type) {
            $row = ['type' => V::label(V::UNIT_TYPES, $type), 'total' => $group->count()];

            foreach (array_keys(V::OCCUPANCY) as $status) {
                $row[$status] = $group->where('occupancy_status', $status)->count();
            }

            return $row + ['_url' => route('realestate.units.index', ['unit_type' => $type])];
        })->values()->all();

        $columns = ['type' => 'نوع الوحدة', 'total' => '#الإجمالي'];

        foreach (V::OCCUPANCY as $status => $meta) {
            $columns[$status] = '#'.$meta['label'];
        }

        return ['columns' => $this->cols($columns), 'rows' => $rows, 'totals' => $this->sum($rows, array_merge(['total'], array_keys(V::OCCUPANCY)))];
    }

    // ═══════════════ العقود ═══════════════

    protected function contractRow(Contract $c): array
    {
        return [
            'code' => $c->code, 'tenant' => $c->tenant->display_name ?? '—', 'unit' => $c->unit->full_label ?? '—',
            'start' => $c->start_date->format('Y-m-d'), 'end' => $c->end_date->format('Y-m-d'),
            'annual' => (float) $c->annual_value, 'frequency' => V::label(V::PAYMENT_FREQUENCIES, $c->payment_frequency),
            'balance' => $c->outstanding(), 'status' => $c->status_meta['label'],
            '_url' => route('realestate.contracts.show', $c->id), '_currency' => $c->currency,
        ];
    }

    protected function reportContractsActive(array $f, callable $scope): array
    {
        $rows = $this->filterContracts($scope(Contract::query()), $f)->whereIn('status', ['active', 'renewing'])
            ->with('tenant', 'unit.building')->orderBy('end_date')->get()->map(fn ($c) => $this->contractRow($c))->all();

        return [
            'columns' => $this->cols(['code' => 'العقد', 'tenant' => 'المستأجر', 'unit' => 'الوحدة', 'start' => 'البداية', 'end' => 'النهاية',
                'annual' => '#القيمة السنوية', 'frequency' => 'السداد', 'balance' => '#المتبقّي', 'status' => 'الحالة']),
            'rows' => $rows, 'totals' => $this->sum($rows, ['annual', 'balance']), 'note' => self::OPERATIONAL_NOTE,
        ];
    }

    protected function reportContractsExpiring(array $f, callable $scope): array
    {
        $rows = $this->filterContracts($scope(Contract::query()), $f)->whereIn('status', Contract::COLLECTIBLE_STATUSES)
            ->whereBetween('end_date', [$f['from']->toDateString(), $f['to']->toDateString()])
            ->with('tenant', 'unit.building', 'renewals', 'renewedInto')->orderBy('end_date')->get()
            ->map(fn (Contract $c) => $this->contractRow($c) + [
                'days_left' => $c->days_left,
                'renewal' => $c->renewedInto ? 'جُدّد ('.$c->renewedInto->code.')' : ($c->renewals->first()?->status_meta['label'] ?? 'لا طلب'),
            ])->all();

        return [
            'columns' => $this->cols(['code' => 'العقد', 'tenant' => 'المستأجر', 'unit' => 'الوحدة', 'end' => 'النهاية', 'days_left' => '#الأيام المتبقية',
                'annual' => '#القيمة السنوية', 'balance' => '#المتبقّي', 'renewal' => 'التجديد', 'status' => 'الحالة']),
            'rows' => $rows, 'totals' => $this->sum($rows, ['annual', 'balance']),
            'note' => 'العقود التي تقع نهايتها داخل الفترة المختارة.',
        ];
    }

    protected function reportRenewals(array $f, callable $scope): array
    {
        $rows = ContractRenewal::whereIn('source_contract_id', $this->filterContracts($scope(Contract::query()), $f)->select('id'))
            ->whereBetween('created_at', [$f['from'], $f['to']])->with('source.tenant', 'source.unit.building', 'renewedContract')->latest()->get()
            ->map(fn (ContractRenewal $r) => [
                'source' => $r->source->code, 'tenant' => $r->source->tenant->display_name ?? '—', 'unit' => $r->source->unit->full_label ?? '—',
                'old' => (float) $r->source->annual_value, 'new' => (float) $r->new_rent_value, 'increase' => (float) $r->increase_percentage,
                'period' => $r->new_start_date->format('Y-m-d').' ← '.$r->new_end_date->format('Y-m-d'),
                'status' => $r->status_meta['label'], 'result' => $r->renewedContract->code ?? '—',
                '_url' => route('realestate.contracts.show', $r->renewed_contract_id ?: $r->source_contract_id),
            ])->all();

        return [
            'columns' => $this->cols(['source' => 'العقد السابق', 'tenant' => 'المستأجر', 'unit' => 'الوحدة', 'old' => '#القيمة السابقة', 'new' => '#القيمة الجديدة',
                'increase' => '#الزيادة %', 'period' => 'فترة التجديد', 'status' => 'الحالة', 'result' => 'العقد الناتج']),
            'rows' => $rows, 'totals' => $this->sum($rows, ['old', 'new']),
        ];
    }

    // ═══════════════ المالية ═══════════════

    protected function reportDues(array $f, callable $scope): array
    {
        $rows = PaymentSchedule::whereIn('contract_id', $this->filterContracts($scope(Contract::query()), $f)->select('id'))
            ->where('status', '!=', 'cancelled')->whereBetween('due_date', [$f['from']->toDateString(), $f['to']->toDateString()])
            ->with('contract.tenant', 'contract.unit.building')->orderBy('due_date')->get()
            ->map(fn (PaymentSchedule $s) => [
                'due_date' => $s->due_date->format('Y-m-d'), 'contract' => $s->contract->code, 'tenant' => $s->contract->tenant->display_name ?? '—',
                'unit' => $s->contract->unit->full_label ?? '—', 'kind' => V::label(V::SCHEDULE_KINDS, $s->kind), 'no' => $s->installment_no,
                'due' => (float) $s->amount_due, 'paid' => (float) $s->amount_paid, 'balance' => $s->balance, 'status' => $s->status_meta['label'],
                '_url' => route('realestate.contracts.show', $s->contract_id), '_currency' => $s->contract->currency,
            ])->all();

        $totals = $this->sum($rows, ['due', 'paid', 'balance']);
        $totals['status'] = $totals['due'] > 0 ? 'نسبة التحصيل '.round($totals['paid'] / $totals['due'] * 100, 1).'%' : '';

        return [
            'columns' => $this->cols(['due_date' => 'الاستحقاق', 'contract' => 'العقد', 'tenant' => 'المستأجر', 'unit' => 'الوحدة', 'kind' => 'النوع',
                'no' => '#القسط', 'due' => '#المستحق', 'paid' => '#المدفوع', 'balance' => '#المتبقّي', 'status' => 'الحالة']),
            'rows' => $rows, 'totals' => $totals, 'note' => self::OPERATIONAL_NOTE,
        ];
    }

    protected function reportCollections(array $f, callable $scope): array
    {
        $rows = $this->postedPayments($f, $scope)->with('client', 'contract.unit.building', 'treasuryAccount')->orderBy('payment_date')->get()
            ->map(fn (Payment $p) => [
                'date' => $p->payment_date->format('Y-m-d'), 'code' => $p->code, 'contract' => $p->contract->code ?? '—',
                'tenant' => $p->client->display_name ?? '—', 'unit' => $p->contract->unit->full_label ?? '—',
                'method' => $p->method_label, 'treasury' => $p->treasuryAccount->name ?? '—', 'reference' => $p->reference_no ?: '—',
                'amount' => (float) $p->amount,
                '_url' => route('realestate.payments.show', $p->id), '_currency' => $p->currency,
            ])->all();

        return [
            'columns' => $this->cols(['date' => 'التاريخ', 'code' => 'الدفعة', 'contract' => 'العقد', 'tenant' => 'المستأجر', 'unit' => 'الوحدة',
                'method' => 'الطريقة', 'treasury' => 'الخزينة', 'reference' => 'المرجع', 'amount' => '#المبلغ']),
            'rows' => $rows, 'totals' => $this->sum($rows, ['amount']),
        ];
    }

    protected function reportAging(array $f, callable $scope): array
    {
        $buckets = ['b30' => [1, 30], 'b60' => [31, 60], 'b90' => [61, 90], 'b90p' => [91, PHP_INT_MAX]];

        $rows = PaymentSchedule::overdue()->whereIn('contract_id', $this->filterContracts($scope(Contract::query()), $f)->select('id'))
            ->with('contract.tenant')->get()->groupBy(fn ($s) => $s->contract->tenant_client_id)
            ->map(function ($group, $clientId) use ($buckets) {
                $row = ['tenant' => $group->first()->contract->tenant->display_name ?? '—', 'count' => $group->count(), 'b30' => 0, 'b60' => 0, 'b90' => 0, 'b90p' => 0];

                foreach ($group as $s) {
                    $age = (int) $s->due_date->diffInDays(now());

                    foreach ($buckets as $key => [$min, $max]) {
                        if ($age >= $min && $age <= $max) {
                            $row[$key] += $s->balance;
                        }
                    }
                }

                return $row + ['total' => $row['b30'] + $row['b60'] + $row['b90'] + $row['b90p'],
                    '_url' => route('realestate.payments.schedules', ['bucket' => 'overdue', 'client_id' => $clientId])];
            })->sortByDesc('total')->values()->all();

        return [
            'columns' => $this->cols(['tenant' => 'المستأجر', 'count' => '#أقساط متأخرة', 'b30' => '#1–30 يوم', 'b60' => '#31–60', 'b90' => '#61–90', 'b90p' => '#أكثر من 90', 'total' => '#الإجمالي']),
            'rows' => $rows, 'totals' => $this->sum($rows, ['count', 'b30', 'b60', 'b90', 'b90p', 'total']),
            'note' => 'لحظيّ حتى اليوم — لا يتأثّر بالفترة المختارة. '.self::OPERATIONAL_NOTE,
        ];
    }

    protected function reportDeposits(array $f, callable $scope): array
    {
        $rows = $this->filterContracts($scope(Contract::query()), $f)->where('security_deposit', '>', 0)->where('status', '!=', 'draft')
            ->with('tenant', 'unit.building', 'schedules')->get()->map(function (Contract $c) {
                $received = (float) $c->schedules->where('kind', 'deposit')->sum('amount_paid');
                // مغلقٌ سُوّي تأمينه: المُنهى دائماً، والمنتهي/الملغى متى سُجّل إنهاؤه
                $closed = $c->status === 'terminated' || (in_array($c->status, Contract::CLOSED_STATUSES, true) && $c->terminated_at);

                return [
                    'code' => $c->code, 'tenant' => $c->tenant->display_name ?? '—', 'unit' => $c->unit->full_label ?? '—',
                    'required' => (float) $c->security_deposit, 'received' => $received,
                    'deducted' => (float) $c->deposit_deducted, 'refunded' => (float) $c->deposit_refunded,
                    'held' => $closed ? 0.0 : $received, 'status' => $c->status_meta['label'],
                    '_url' => route('realestate.contracts.show', $c->id), '_currency' => $c->currency,
                ];
            })->all();

        return [
            'columns' => $this->cols(['code' => 'العقد', 'tenant' => 'المستأجر', 'unit' => 'الوحدة', 'required' => '#المطلوب', 'received' => '#المقبوض',
                'deducted' => '#المخصوم', 'refunded' => '#المردود', 'held' => '#المحتجز', 'status' => 'حالة العقد']),
            'rows' => $rows, 'totals' => $this->sum($rows, ['required', 'received', 'deducted', 'refunded', 'held']),
            'note' => 'المقبوض من التوزيعات على استحقاق التأمين. «المردود» هو المستحقّ ردُّه عند الإنهاء — صرفُه يتمّ بسند صرفٍ من الحسابات.',
        ];
    }

    protected function reportPropertyIncome(array $f, callable $scope): array
    {
        $rows = $this->buildingStats($f, $scope)->map(fn ($b) => [
            'building' => $b->name, 'owner' => $b->owner->display_name ?? '—', 'collected' => $b->collected,
            'maintenance' => $b->maintenance_cost, 'net' => $b->collected - $b->maintenance_cost,
            'margin' => $b->collected > 0 ? round(($b->collected - $b->maintenance_cost) / $b->collected * 100, 1) : 0,
            '_url' => route('realestate.buildings.show', $b->id),
        ])->sortByDesc('net')->values()->all();

        return [
            'columns' => $this->cols(['building' => 'العقار', 'owner' => 'المالك', 'collected' => '#الدخل المحصَّل', 'maintenance' => '#مصروف الصيانة', 'net' => '#صافي الدخل التشغيلي', 'margin' => '#الهامش %']),
            'rows' => $rows, 'totals' => $this->sum($rows, ['collected', 'maintenance', 'net']),
            'note' => 'الدخل: إيجارٌ مرحَّل (بلا التأمينات). المصروف: التكلفة الفعلية لطلبات الصيانة — تشغيليةٌ ما لم تُقيَّد في الحسابات.',
        ];
    }

    // ═══════════════ العملاء ═══════════════

    protected function reportTenantStatement(array $f, callable $scope): array
    {
        $contracts = $scope(Contract::query())->where('tenant_client_id', $f['client_id'] ?? 0)->pluck('code', 'id');

        $lines = PaymentSchedule::whereIn('contract_id', $contracts->keys())->where('status', '!=', 'cancelled')->get()
            ->map(fn ($s) => ['date' => $s->due_date, 'doc' => $contracts[$s->contract_id], 'text' => V::label(V::SCHEDULE_KINDS, $s->kind).' — قسط '.$s->installment_no,
                'debit' => (float) $s->amount_due, 'credit' => 0.0, '_url' => route('realestate.contracts.show', $s->contract_id)])
            ->concat(Payment::whereIn('contract_id', $contracts->keys())->where('posting_status', 'posted')->get()
                ->map(fn ($p) => ['date' => $p->payment_date, 'doc' => $p->code, 'text' => 'دفعة — '.$p->method_label,
                    'debit' => 0.0, 'credit' => (float) $p->amount, '_url' => route('realestate.payments.show', $p->id)]))
            ->filter(fn ($l) => $l['date']->between($f['from'], $f['to']))->sortBy('date')->values();

        $balance = 0.0;
        $rows = $lines->map(function ($l) use (&$balance) {
            $balance += $l['debit'] - $l['credit'];

            return ['date' => $l['date']->format('Y-m-d'), 'doc' => $l['doc'], 'text' => $l['text'], 'debit' => $l['debit'], 'credit' => $l['credit'],
                'balance' => round($balance, 2), '_url' => $l['_url']];
        })->all();

        return [
            'columns' => $this->cols(['date' => 'التاريخ', 'doc' => 'المستند', 'text' => 'البيان', 'debit' => '#مستحق عليه', 'credit' => '#مدفوع', 'balance' => '#الرصيد']),
            'rows' => $rows, 'totals' => $this->sum($rows, ['debit', 'credit']) + ['balance' => round($balance, 2)],
            'note' => 'المستحق من جدول الاستحقاقات التعاقدية، والمدفوع من الدفعات المرحّلة. الرصيد ضمن الفترة المختارة.',
        ];
    }

    /**
     * كشف حساب المالك الجاري من دفتره: له (إيجار محصَّل، تأمين، استرداد) وعليه (عمولة وضريبتها،
     * ضريبة، صيانة، تحويل) — والقيود العكسية تظهر بسطرها ورصيدٌ متحرّك يبدأ برصيده قبل الفترة.
     */
    protected function reportOwnerStatement(array $f, callable $scope): array
    {
        $owner = $scope(Owner::query())->findOrFail($f['owner_id'] ?? 0);

        $signed = "COALESCE(SUM(CASE WHEN direction = 'credit' THEN amount ELSE -amount END), 0)";
        $opening = round((float) OwnerEntry::query()->where('owner_id', $owner->id)
            ->where('entry_date', '<', $f['from']->toDateString())->selectRaw("$signed as total")->toBase()->value('total'), 2);

        $balance = $opening;
        $rows = OwnerEntry::query()->where('owner_id', $owner->id)->with('building', 'unit')
            ->whereBetween('entry_date', [$f['from']->toDateString(), $f['to']->toDateString()])
            ->orderBy('entry_date')->orderBy('id')->get()
            ->map(function (OwnerEntry $e) use (&$balance) {
                $balance = round($balance + $e->signed_amount, 2);

                return [
                    'date' => $e->entry_date->format('Y-m-d'), 'type' => $e->type_meta['label'],
                    'text' => ($e->reverses_entry_id ? 'قيد عكسي — ' : '').$e->description.($e->building ? ' — '.$e->building->name : ''),
                    'credit' => $e->direction === 'credit' ? $e->amount : 0.0, 'debit' => $e->direction === 'debit' ? $e->amount : 0.0,
                    'balance' => $balance, '_currency' => $e->currency,
                ];
            })->all();

        array_unshift($rows, ['date' => $f['from']->format('Y-m-d'), 'type' => 'رصيد سابق', 'text' => 'الرصيد قبل بداية الفترة', 'credit' => 0.0, 'debit' => 0.0, 'balance' => $opening]);

        return [
            'columns' => $this->cols(['date' => 'التاريخ', 'type' => 'العملية', 'text' => 'البيان', 'credit' => '#له', 'debit' => '#عليه', 'balance' => '#الرصيد']),
            'rows' => $rows, 'totals' => $this->sum($rows, ['credit', 'debit']) + ['balance' => $balance],
            'note' => 'المالك: '.$owner->name.' ('.$owner->code.') — '.$owner->agreement_label.'. الرصيد التراكمي من دفتر المالك (له − عليه).',
        ];
    }

    // ═══════════════ العروض والصيانة والإدارة ═══════════════

    protected function reportListingsPerformance(array $f, callable $scope): array
    {
        $rows = $scope(Listing::query())->when($f['building_id'] ?? null, fn ($q, $id) => $q->where('building_id', $id))
            ->whereBetween('created_at', [$f['from'], $f['to']])->with('unit.building', 'agent')
            ->withCount(['viewings as viewings_done' => fn ($q) => $q->where('kind', 'viewing'), 'contracts'])->get()
            ->map(fn (Listing $l) => [
                'code' => $l->code, 'title' => $l->title, 'type' => $l->kind_meta['label'].' — '.$l->type_meta['label'], 'price' => (float) $l->asking_price,
                'agent' => $l->agent->name ?? '—', 'views' => $l->views_count, 'inquiries' => $l->inquiries_count,
                'viewings' => $l->viewings_done, 'contracts' => $l->contracts_count, 'status' => $l->status_meta['label'],
                '_url' => route('realestate.listings.show', $l->id),
            ])->all();

        $totals = $this->sum($rows, ['views', 'inquiries', 'viewings', 'contracts']);
        $totals['status'] = $totals['inquiries'] > 0 ? 'تحويل '.round($totals['contracts'] / $totals['inquiries'] * 100, 1).'%' : '';

        return [
            'columns' => $this->cols(['code' => 'العرض', 'title' => 'العنوان', 'type' => 'النوع', 'price' => '#السعر', 'agent' => 'المسوّق',
                'views' => '#مشاهدات', 'inquiries' => '#استفسارات', 'viewings' => '#معاينات', 'contracts' => '#عقود', 'status' => 'الحالة']),
            'rows' => $rows, 'totals' => $totals,
        ];
    }

    protected function maintenanceIn(array $f, callable $scope)
    {
        return $scope(MaintenanceRequest::query())->when($f['building_id'] ?? null, fn ($q, $id) => $q->where('building_id', $id))
            ->whereBetween('created_at', [$f['from'], $f['to']]);
    }

    protected function reportMaintenanceSummary(array $f, callable $scope): array
    {
        $rows = $this->maintenanceIn($f, $scope)->get()->groupBy('category')->map(function ($group, $category) {
            $done = $group->where('status', 'completed')->filter(fn ($r) => $r->completed_at);

            return [
                'category' => V::label(V::MAINTENANCE_CATEGORIES, $category), 'total' => $group->count(),
                'open' => $group->whereNotIn('status', ['completed', 'cancelled'])->count(), 'completed' => $done->count(),
                'avg_hours' => $done->count() ? round($done->avg(fn ($r) => $r->created_at->diffInHours($r->completed_at)), 1) : 0,
                'estimated' => (float) $group->sum('estimated_cost'), 'actual' => (float) $group->sum('actual_cost'),
                '_url' => route('realestate.maintenance.index', ['category' => $category]),
            ];
        })->sortByDesc('total')->values()->all();

        return [
            'columns' => $this->cols(['category' => 'التصنيف', 'total' => '#الطلبات', 'open' => '#مفتوحة', 'completed' => '#مكتملة',
                'avg_hours' => '#متوسط الإنجاز (ساعة)', 'estimated' => '#التكلفة التقديرية', 'actual' => '#التكلفة الفعلية']),
            'rows' => $rows, 'totals' => $this->sum($rows, ['total', 'open', 'completed', 'estimated', 'actual']),
        ];
    }

    protected function reportContractors(array $f, callable $scope): array
    {
        $rows = $this->maintenanceIn($f, $scope)->whereNotNull('assigned_to_user_id')->with('assignee')->get()
            ->groupBy('assigned_to_user_id')->map(function ($group, $userId) {
                $done = $group->where('status', 'completed')->filter(fn ($r) => $r->completed_at);

                return [
                    'assignee' => $group->first()->assignee->name ?? '#'.$userId, 'total' => $group->count(), 'completed' => $done->count(),
                    'late' => $group->filter->isLate()->count(),
                    'avg_hours' => $done->count() ? round($done->avg(fn ($r) => $r->created_at->diffInHours($r->completed_at)), 1) : 0,
                    'cost' => (float) $group->sum('actual_cost'),
                    '_url' => route('realestate.maintenance.index', ['assigned_to_user_id' => $userId]),
                ];
            })->sortByDesc('total')->values()->all();

        return [
            'columns' => $this->cols(['assignee' => 'المسؤول', 'total' => '#المسندة', 'completed' => '#المنجزة', 'late' => '#متجاوزة الموعد', 'avg_hours' => '#متوسط الإنجاز (ساعة)', 'cost' => '#التكلفة']),
            'rows' => $rows, 'totals' => $this->sum($rows, ['total', 'completed', 'late', 'cost']),
        ];
    }

    protected function reportOwnerSummary(array $f, callable $scope): array
    {
        // عمولة المالك عن عقاراتٍ داخل النطاق وحدها — كما تُحسب بقيّة أعمدته
        // العمولة وضريبتها صافيتين من قيودهما العكسية (المدين عمولةٌ على المالك، والدائن عكسُها)
        $commissions = OwnerEntry::query()->whereIn('type', ['commission', 'commission_tax'])
            ->whereIn('building_id', $this->filterBuildings($scope(Building::query()), $f)->select('id'))
            ->whereBetween('entry_date', [$f['from']->toDateString(), $f['to']->toDateString()])
            ->groupBy('owner_id')->selectRaw("owner_id, SUM(CASE WHEN direction = 'debit' THEN amount ELSE -amount END) as total")->toBase()->pluck('total', 'owner_id');

        $rows = $this->buildingStats($f, $scope)->groupBy('owner_id')->map(fn ($group, $ownerId) => [
            'owner' => $group->first()->owner->display_name ?? 'بلا مالك محدَّد', 'buildings' => $group->count(),
            'units' => $group->sum('units_count'), 'rented' => $group->sum('rented'),
            'occupancy' => $group->sum('units_count') ? round($group->sum('rented') / $group->sum('units_count') * 100, 1) : 0,
            'collected' => $group->sum('collected'), 'commission' => (float) ($commissions[$ownerId] ?? 0), 'maintenance' => $group->sum('maintenance_cost'),
            'net' => $group->sum('collected') - (float) ($commissions[$ownerId] ?? 0) - $group->sum('maintenance_cost'), 'arrears' => $group->sum('arrears'),
            '_url' => $ownerId ? route('realestate.reports.show', ['key' => 'owner_statement', 'owner_id' => $ownerId]) : null,
        ])->sortByDesc('net')->values()->all();

        return [
            'columns' => $this->cols(['owner' => 'المالك', 'buildings' => '#عقارات', 'units' => '#وحدات', 'rented' => '#مؤجرة', 'occupancy' => '#الإشغال %',
                'collected' => '#المحصَّل', 'commission' => '#عمولة الإدارة (بضريبتها)', 'maintenance' => '#الصيانة', 'net' => '#صافي دخل المالك', 'arrears' => '#المتأخرات']),
            'rows' => $rows, 'totals' => $this->sum($rows, ['buildings', 'units', 'rented', 'collected', 'commission', 'maintenance', 'net', 'arrears']),
        ];
    }

    // ═══════════════ أدوات ═══════════════

    /** `'#تسمية'` = عمودٌ رقمي (يُحاذى ويُجمَع ويُصدَّر رقماً). */
    protected function cols(array $map): array
    {
        return array_map(fn ($label) => str_starts_with($label, '#')
            ? ['label' => substr($label, 1), 'numeric' => true]
            : ['label' => $label, 'numeric' => false], $map);
    }

    protected function sum(array $rows, array $keys): array
    {
        $out = [];

        foreach ($keys as $key) {
            $out[$key] = round(array_sum(array_column($rows, $key)), 2);
        }

        return $out;
    }

    protected function filterBuildings($query, array $f)
    {
        return $query->when($f['building_id'] ?? null, fn ($q, $id) => $q->where('id', $id))
            ->when($f['owner_id'] ?? null, fn ($q, $id) => $q->where('owner_id', $id))
            ->when($f['usage_type'] ?? null, fn ($q, $v) => $q->where('usage_type', $v))
            ->when($f['branch_id'] ?? null, fn ($q, $id) => $q->where('branch_id', $id));
    }

    protected function filterUnits($query, array $f)
    {
        return $query->when($f['building_id'] ?? null, fn ($q, $id) => $q->where('building_id', $id))
            ->when($f['occupancy_status'] ?? null, fn ($q, $v) => $q->where('occupancy_status', $v))
            ->when($f['usage_type'] ?? null, fn ($q, $v) => $q->where('usage_type', $v))
            ->when($f['owner_id'] ?? null, fn ($q, $id) => $q->whereHas('building', fn ($b) => $b->where('owner_id', $id)))
            ->when($f['branch_id'] ?? null, fn ($q, $id) => $q->where('branch_id', $id));
    }

    protected function filterContracts($query, array $f)
    {
        return $query->when($f['building_id'] ?? null, fn ($q, $id) => $q->where('building_id', $id))
            ->when($f['owner_id'] ?? null, fn ($q, $id) => $q->ofOwner($id))
            ->when($f['branch_id'] ?? null, fn ($q, $id) => $q->where('branch_id', $id));
    }

    protected function postedPayments(array $f, callable $scope)
    {
        return $scope(Payment::query())->where('posting_status', 'posted')
            ->whereBetween('payment_date', [$f['from']->toDateString(), $f['to']->toDateString()])
            ->whereIn('contract_id', $this->filterContracts($scope(Contract::query()), $f)->select('id'));
    }

    /** إيجارٌ مرحَّل ضمن الفترة لعقودٍ يحدّدها `$constraint` — بلا التأمينات. */
    protected function collectedFor(callable $constraint, array $f): float
    {
        return (float) PaymentAllocation::query()
            ->whereHas('schedule', fn ($q) => $q->where('kind', 'rent')->whereHas('contract', $constraint))
            ->whereHas('payment', fn ($q) => $q->where('posting_status', 'posted')
                ->whereBetween('payment_date', [$f['from']->toDateString(), $f['to']->toDateString()]))
            ->sum('amount');
    }
}
