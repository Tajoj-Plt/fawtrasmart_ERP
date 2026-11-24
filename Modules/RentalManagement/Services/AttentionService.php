<?php

namespace Modules\RentalManagement\Services;

use Illuminate\Support\Collection;
use Modules\RentalManagement\Models\Contract;
use Modules\RentalManagement\Models\Listing;
use Modules\RentalManagement\Models\MaintenanceRequest;
use Modules\RentalManagement\Models\PaymentSchedule;
use Modules\RentalManagement\Models\Task;
use Modules\RentalManagement\Models\Unit;
use Modules\RentalManagement\Support\RealEstateSettings;

/**
 * «تحتاج تدخّلاً» — قائمةٌ تُشتقّ آلياً من قواعد، لا مهامُّ يكتبها أحد:
 * عقدٌ يقترب انتهاؤه بلا متابعة، دفعةٌ متأخرة بلا إجراء، عرضٌ بلا متابعة،
 * صيانةٌ تجاوزت موعدها، وحدةٌ شاغرة طويلاً، عقودٌ بانتظار اعتماد.
 *
 * تغذّي لوحة التحكم ولوحة المهام معاً فلا يختلف العدد بينهما.
 */
class AttentionService
{
    /**
     * @param  callable  $scope  يقيّد الاستعلام بنطاق بيانات المستخدم: fn($query, ?string $table)
     * @return array<int, array{key:string, label:string, count:int, tone:string, icon:string, url:string, rows:Collection}>
     */
    public function items(callable $scope): array
    {
        $alertDays = (int) RealEstateSettings::get('contract_expiry_alert_days', 60);
        $vacancyDays = (int) RealEstateSettings::get('long_vacancy_days', 60);

        // متابعةٌ مفتوحة على السجلّ تعني أنّ أحداً يعمل عليه — فلا يُعدّ مهمَلاً
        $followed = fn (string $type) => Task::where('related_type', $type)->where('status', '!=', 'completed')->select('related_id');

        $expiring = $scope(Contract::query())->where('status', 'active')
            ->whereBetween('end_date', [now()->toDateString(), now()->addDays($alertDays)->toDateString()])
            ->whereNotIn('id', $followed('contract'))->with('tenant', 'unit.building')->orderBy('end_date');

        $pending = $scope(Contract::query())->where('status', 'pending_approval')->with('tenant', 'unit.building')->latest('id');

        $overdue = PaymentSchedule::overdue()->whereIn('contract_id', $scope(Contract::query())->select('id'))
            ->with('contract.tenant', 'contract.unit.building')->orderBy('due_date');

        $staleListings = $scope(Listing::query())->where('listing_kind', 'offer')->where('status', 'active')
            ->where(fn ($q) => $q->whereDate('expiry_date', '<=', now()->addDays(7)->toDateString())
                ->orWhere(fn ($w) => $w->where('inquiries_count', 0)->whereDate('publish_date', '<=', now()->subDays(30)->toDateString())))
            ->whereNotIn('id', $followed('listing'))->with('unit.building')->orderBy('expiry_date');

        $lateMaintenance = $scope(MaintenanceRequest::query())->openOnes()
            ->where(fn ($q) => $q->where(fn ($w) => $w->whereNotNull('scheduled_at')->where('scheduled_at', '<', now()))
                ->orWhere(fn ($w) => $w->where('priority', 'urgent')->where('created_at', '<', now()->subDay())))
            ->with('unit.building', 'building')->oldest('scheduled_at');

        $longVacant = $scope(Unit::query())->where('occupancy_status', 'vacant')
            ->whereDate('vacant_since', '<=', now()->subDays($vacancyDays)->toDateString())->with('building')->orderBy('vacant_since');

        $make = fn ($key, $label, $query, $tone, $icon, $url) => [
            'key' => $key, 'label' => $label, 'count' => (clone $query)->count(), 'tone' => $tone,
            'icon' => $icon, 'url' => $url, 'rows' => $query->limit(5)->get(),
        ];

        return array_values(array_filter([
            $make('overdue', 'دفعات متأخرة', $overdue, 'danger', 'alert-circle', route('realestate.payments.schedules', ['bucket' => 'overdue'])),
            $make('expiring', 'عقود تنتهي قريباً بلا متابعة', $expiring, 'warning', 'clock', route('realestate.contracts.index', ['expiring_in' => $alertDays])),
            $make('pending', 'عقود بانتظار اعتماد', $pending, 'info', 'check-circle', route('realestate.contracts.index', ['status' => 'pending_approval'])),
            $make('maintenance', 'صيانة تجاوزت موعدها', $lateMaintenance, 'danger', 'tool', route('realestate.maintenance.index', ['late' => 1])),
            $make('listings', 'عروض تنتهي أو بلا استفسارات', $staleListings, 'warning', 'tag', route('realestate.listings.index', ['status' => 'active'])),
            $make('vacant', 'وحدات شاغرة طويلاً', $longVacant, 'secondary', 'unlock', route('realestate.units.index', ['occupancy_status' => 'vacant'])),
        ], fn ($item) => $item['count'] > 0));
    }
}
