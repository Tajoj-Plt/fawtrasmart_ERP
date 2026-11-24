<?php

namespace Modules\RentalManagement\Http\Controllers\RealEstate;

use App\Constants\permissions\RealEstatePermissions;
use Illuminate\Http\Request;
use Modules\RentalManagement\Models\Building;
use Modules\RentalManagement\Models\Contract;
use Modules\RentalManagement\Models\Listing;
use Modules\RentalManagement\Models\Payment;
use Modules\RentalManagement\Models\PaymentSchedule;
use Modules\RentalManagement\Models\Unit;
use Modules\RentalManagement\Models\Viewing;
use Modules\RentalManagement\Services\AttentionService;
use Modules\RentalManagement\Support\RealEstateSettings;

/**
 * لوحة التحكم تجيب فوراً عن أربعة أسئلة: ماذا ندير؟ ما المتاح؟ ماذا يجب
 * تحصيله؟ وما الذي يحتاج إجراءً الآن؟
 */
class DashboardController extends RealEstateController
{
    public function __construct(protected AttentionService $attention)
    {
        // بوّابة اللوحة: صلاحيتها، أو أيّ صلاحيةٍ في الوحدة — فلا يُردّ من
        // يملك «الصيانة» وحدها عن مدخل القسم (كما في لوحة المخزون)
        $this->middleware(function ($request, $next) {
            abort_unless(auth()->user()->hasAnyPermissionWithPrefix(RealEstatePermissions::PREFIX), 403,
                'ليست لديك صلاحية الوصول إلى إدارة العقارات.');

            return $next($request);
        });
    }

    public function index(Request $request)
    {
        $can = fn (string $p) => hasPermission('realestate.'.$p);
        $full = $can('dashboard.view');

        $units = $this->scoped(Unit::query());
        $byStatus = (clone $units)->selectRaw('occupancy_status, COUNT(*) c')->groupBy('occupancy_status')->pluck('c', 'occupancy_status');
        $totalUnits = $byStatus->sum();
        $rented = (int) ($byStatus['rented'] ?? 0);

        $contracts = $this->scoped(Contract::query());
        $contractIds = (clone $contracts)->select('id');

        $monthStart = now()->startOfMonth()->toDateString();
        $monthEnd = now()->endOfMonth()->toDateString();

        $dueMonth = (float) PaymentSchedule::whereIn('contract_id', $contractIds)->where('status', '!=', 'cancelled')
            ->whereBetween('due_date', [$monthStart, $monthEnd])->sum('amount_due');
        $paidOfDueMonth = (float) PaymentSchedule::whereIn('contract_id', $contractIds)->where('status', '!=', 'cancelled')
            ->whereBetween('due_date', [$monthStart, $monthEnd])->sum('amount_paid');

        $showMoney = $full && ($can('payments.view') || $can('reports.financial'));

        // خريطة كل العقارات المسجَّلة — تُعرض فوق «تحتاج انتباهك»
        $canMap = $full && hasPermission('realestate.buildings.view');
        $canShowBuilding = hasPermission('realestate.buildings.show');
        // "name" وصفٌ محسوب (name_ar/name_en) لا عمودٌ حقيقي — orderBy('name')
        // يفشل على MySQL (42S22 Unknown column) بخلاف SQLite الذي يمرّه صامتاً
        $mapBuildings = $canMap ? $this->scoped(Building::query())->whereNotNull('latitude')->whereNotNull('longitude')
            ->withCount(['units' => fn ($q) => $this->scoped($q), 'units as vacant_units_count' => fn ($q) => $this->scoped($q)->where('occupancy_status', 'vacant')])
            ->orderBy('name_ar')->get() : collect();

        return view('rentalmanagement::realestate.dashboard.index', [
            'full' => $full,
            'showMoney' => $showMoney,
            'kpis' => [
                'buildings' => $this->scoped(Building::query())->count(),
                'units' => $totalUnits,
                'rented' => $rented,
                'vacant' => (int) ($byStatus['vacant'] ?? 0),
                'occupancy' => $totalUnits ? round($rented / $totalUnits * 100, 1) : 0,
                'active_value' => (float) (clone $contracts)->whereIn('status', ['active', 'renewing'])->sum('annual_value'),
                'active_contracts' => (clone $contracts)->whereIn('status', ['active', 'renewing'])->count(),
            ],
            'collection' => [
                'due' => $dueMonth,
                'collected' => (float) $this->scoped(Payment::query())->where('posting_status', 'posted')
                    ->whereBetween('payment_date', [$monthStart, $monthEnd])->sum('amount'),
                'paid_of_due' => $paidOfDueMonth,
                'rate' => $dueMonth > 0 ? round($paidOfDueMonth / $dueMonth * 100, 1) : 0,
                'overdue' => (float) PaymentSchedule::overdue()->whereIn('contract_id', $contractIds)
                    ->selectRaw('COALESCE(SUM(amount_due - amount_paid),0) t')->value('t'),
            ],
            'byStatus' => $byStatus,
            'mapPoints' => $mapBuildings->map(fn (Building $b) => [
                'id' => $b->id,
                'name' => $b->name,
                'code' => $b->code,
                'lat' => (float) $b->latitude,
                'lng' => (float) $b->longitude,
                'address' => collect([$b->city, $b->district, $b->address])->filter()->implode('، '),
                'units' => $b->units_count,
                'vacant' => $b->vacant_units_count,
                'url' => $canShowBuilding ? route('realestate.buildings.show', $b->id) : null,
            ])->values(),
            'mapMissing' => $canMap ? $this->scoped(Building::query())->where(fn ($q) => $q->whereNull('latitude')->orWhereNull('longitude'))->count() : 0,
            'attention' => $full ? $this->attention->items(fn ($q, $t = null) => $this->scoped($q, $t)) : [],
            'expiring' => $full ? (clone $contracts)->whereIn('status', ['active', 'renewing'])
                ->whereBetween('end_date', [now()->toDateString(), now()->addDays((int) RealEstateSettings::get('contract_expiry_alert_days', 60))->toDateString()])
                ->with('tenant', 'unit.building')->orderBy('end_date')->limit(8)->get() : collect(),
            // أرقام كلّ عقار من وحداته وعقوده وصيانته داخل النطاق — لا كلّ ما في المبنى
            'performance' => $full ? $this->scoped(Building::query())->withCount([
                'units' => fn ($q) => $this->scoped($q),
                'units as rented_count' => fn ($q) => $this->scoped($q)->where('occupancy_status', 'rented'),
                'maintenanceRequests as open_maintenance' => fn ($q) => $this->scoped($q)->whereNotIn('status', ['completed', 'cancelled']),
            ])->withSum(['contracts as active_value' => fn ($q) => $this->scoped($q)->whereIn('status', ['active', 'renewing'])], 'annual_value')
                ->orderByDesc('units_count')->limit(6)->get() : collect(),
            'listings' => [
                'active' => $this->scoped(Listing::query())->where('listing_kind', 'offer')->where('status', 'active')->count(),
                'rent' => $this->scoped(Listing::query())->where('listing_kind', 'offer')->where('status', 'active')->where('listing_type', 'rent')->count(),
                'sale' => $this->scoped(Listing::query())->where('listing_kind', 'offer')->where('status', 'active')->where('listing_type', 'sale')->count(),
                'requests' => $this->scoped(Listing::query())->where('listing_kind', 'request')->whereIn('status', Listing::REQUEST_OPEN)->count(),
                'inquiries' => (int) $this->scoped(Listing::query())->sum('inquiries_count'),
                'viewings' => Viewing::where('kind', 'viewing')->whereIn('listing_id', $this->scoped(Listing::query())->select('id'))->count(),
                'converted' => $this->scoped(Listing::query())->whereHas('contracts')->count(),
            ],
            // ستة أشهر: المحصَّل شهرياً — للرسم
            'trend' => $showMoney ? collect(range(5, 0))->map(function ($back) {
                $month = now()->subMonthsNoOverflow($back);

                return [
                    'label' => $month->translatedFormat('M Y'),
                    'collected' => (float) $this->scoped(Payment::query())->where('posting_status', 'posted')
                        ->whereBetween('payment_date', [$month->copy()->startOfMonth()->toDateString(), $month->copy()->endOfMonth()->toDateString()])->sum('amount'),
                ];
            })->values() : collect(),
        ]);
    }
}
