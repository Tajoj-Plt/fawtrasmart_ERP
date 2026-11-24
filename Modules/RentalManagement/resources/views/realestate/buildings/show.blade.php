@extends('rentalmanagement::realestate.master')

@section('title', $building->name)

@section('content')
    @php
        use Modules\RentalManagement\Support\RealEstateVocabulary as V;
        // المبالغ تُعرض بعملتها (RealEstateMoney من وكيل المحاسبة؛ وقبل وجوده عملة النظام)
        $money = fn ($v, $currency = null) => class_exists(\Modules\RentalManagement\Support\RealEstateMoney::class)
            ? \Modules\RentalManagement\Support\RealEstateMoney::format($v, $currency)
            : number_format((float) $v, 2).' '.\App\Support\CurrencySettings::symbol();
        $area = fn ($v) => rtrim(rtrim(number_format((float) $v, 2), '0'), '.').' م²';
        $single = $building->isSingleUnit();
        $rented = $units->where('occupancy_status', 'rented')->count();
        $rate = $units->count() ? round($rented / $units->count() * 100, 1) : 0;
        $canMoney = canAny(['realestate.payments.view', 'realestate.reports.financial']);
    @endphp

    <div class="content-body">

        @section('page-actions')
            @if ($single)
                {{-- عقارٌ وحيد الوحدة: العقد يُنشأ من صفحة العقار مباشرة، ووحدته تعمل في الخلفية --}}
                @if ($autoUnit && $autoUnit->occupancy_status === 'vacant')
                    @permission('realestate.contracts.create')
                        <a href="{{ route('realestate.contracts.create', ['unit_id' => $autoUnit->id]) }}" class="btn btn-outline-primary"><i class="feather icon-file-text"></i> إنشاء عقد</a>
                    @endpermission
                @endif
            @else
                @permission('realestate.units.create')
                    <a href="{{ route('realestate.units.create', ['building_id' => $building->id]) }}" class="btn btn-outline-primary"><i class="feather icon-plus"></i> وحدة</a>
                    <button type="button" class="btn btn-outline-primary js-bulk-units"><i class="feather icon-layers"></i> وحدات دفعة واحدة</button>
                @endpermission
            @endif
            @if ($building->latitude && $building->longitude)
                <button type="button" class="btn btn-outline-primary" id="showOnMap"><i class="feather icon-map-pin"></i> عرض على الخريطة</button>
            @endif
            @permission('realestate.maintenance.create')
                <a href="{{ route('realestate.maintenance.create', ['building_id' => $building->id, 'scope' => 'building']) }}" class="btn btn-outline-secondary"><i class="feather icon-tool"></i> صيانة المبنى</a>
            @endpermission
            @permission('realestate.buildings.edit')
                <a href="{{ route('realestate.buildings.edit', $building->id) }}" class="btn btn-primary"><i class="feather icon-edit"></i> تعديل</a>
            @endpermission
        @endsection

        @include('stock::components.page-head', [
            'title' => $building->name,
            'icon' => 'home',
            'sub' => $building->code.' · '.V::label(V::ENTITY_TYPES, $building->entity_type).' '.V::label(V::USAGE_TYPES, $building->usage_type),
            'crumbs' => ['الرئيسية' => url('/'), 'المباني والمجمعات' => route('realestate.buildings.index'), $building->name => null],
        ])

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')

        <div class="stk-metrics mb-2">
            <div class="stk-metric"><span class="stk-metric__icon"><i class="feather icon-flag"></i></span>
                <span class="stk-metric__value"><span class="badge badge-light-{{ $building->status_meta['tone'] }}">{{ $building->status_meta['label'] }}</span></span><span class="stk-metric__label">الحالة</span></div>
            <div class="stk-metric"><span class="stk-metric__icon"><i class="feather icon-grid"></i></span><span class="stk-metric__value stk-num">{{ $units->count() }}</span><span class="stk-metric__label">الوحدات</span></div>
            <div class="stk-metric"><span class="stk-metric__icon"><i class="feather icon-key"></i></span><span class="stk-metric__value stk-num">{{ $rented }}</span><span class="stk-metric__label">مؤجرة</span></div>
            <div class="stk-metric"><span class="stk-metric__icon"><i class="feather icon-pie-chart"></i></span><span class="stk-metric__value stk-num">{{ $rate }}%</span><span class="stk-metric__label">الإشغال</span></div>
            <div class="stk-metric"><span class="stk-metric__icon"><i class="feather icon-user"></i></span><span class="stk-metric__value">@if ($building->owner && hasPermission('realestate.owners.show'))<a href="{{ route('realestate.owners.show', $building->owner->id) }}">{{ $building->owner->name }}</a>@else{{ $building->owner->display_name ?? '—' }}@endif</span><span class="stk-metric__label">المالك</span></div>
        </div>

        <div class="card">
            <div class="card-body">
                <ul class="nav nav-tabs stk-tabs" role="tablist">
                    @foreach ([['overview', 'info', 'نظرة عامة'], ['units', 'grid', 'الوحدات'], ['contracts', 'file-text', 'العقود'], ['listings', 'tag', 'العروض'],
                        ['finance', 'dollar-sign', 'المالية'], ['maintenance', 'tool', 'الصيانة'], ['documents', 'paperclip', 'المستندات'], ['activity', 'activity', 'سجل النشاط']] as $i => [$id, $icon, $label])
                        @continue($id === 'finance' && ! $canMoney)
                        <li class="nav-item">
                            <a class="nav-link {{ $i === 0 ? 'active' : '' }}" data-toggle="tab" data-bs-toggle="tab" href="#tab-{{ $id }}" role="tab"><i class="feather icon-{{ $icon }}"></i> {{ $label }}</a>
                        </li>
                    @endforeach
                </ul>

                <div class="tab-content pt-2">
                    <div class="tab-pane active" id="tab-overview" role="tabpanel">
                        <div class="row">
                            <div class="col-lg-7">
                                <dl class="stk-def">
                                    <div class="stk-def__row"><dt>النوع والاستخدام</dt><dd>{{ V::label(V::ENTITY_TYPES, $building->entity_type) }} · {{ V::label(V::USAGE_TYPES, $building->usage_type) }}</dd></div>
                                    <div class="stk-def__row"><dt>ملكية المبنى</dt><dd>{{ V::label(V::OWNERSHIP, $building->isOwn() ? 'own' : 'managed') }}</dd></div>
                                    <div class="stk-def__row"><dt>المجمع</dt><dd>{{ $building->complex->name_ar ?? 'مبنى مستقل' }}</dd></div>
                                    <div class="stk-def__row"><dt>مدير العقار</dt><dd>{{ $building->manager->name ?? '—' }}</dd></div>
                                    <div class="stk-def__row"><dt>العنوان</dt><dd>{{ collect([$building->country, $building->city, $building->district, $building->address])->filter()->implode('، ') ?: '—' }}</dd></div>
                                    @if ($building->latitude && $building->longitude)
                                        <div class="stk-def__row"><dt>الخريطة</dt><dd><a href="#" class="js-show-on-map"><i class="feather icon-map-pin"></i> عرض على الخريطة</a></dd></div>
                                    @endif
                                    <div class="stk-def__row"><dt>المساحة الكلية</dt><dd class="stk-num">{{ $building->total_area ? $area($building->total_area) : '—' }}</dd></div>
                                    @if ($building->deed_no || $building->deed_date)
                                        <div class="stk-def__row"><dt>الصك</dt><dd class="stk-num">{{ $building->deed_no ?: '—' }}@if ($building->deed_date) · {{ $building->deed_date->format('Y-m-d') }}@endif</dd></div>
                                    @endif
                                    @if ($building->entity_type === 'land')
                                        <div class="stk-def__row"><dt>المخطط / القطعة</dt><dd class="stk-num">{{ $building->plan_no ?: '—' }} / {{ $building->plot_no ?: '—' }}</dd></div>
                                        <div class="stk-def__row"><dt>عرض الشارع</dt><dd class="stk-num">{{ $building->street_width ? (float) $building->street_width.' م' : '—' }}</dd></div>
                                        <div class="stk-def__row"><dt>الحدود والأطوال</dt><dd>{{ $building->boundaries ?: '—' }}</dd></div>
                                    @endif
                                    @if ($building->building_permit_no)
                                        <div class="stk-def__row"><dt>رخصة البناء</dt><dd class="stk-num">{{ $building->building_permit_no }}</dd></div>
                                    @endif
                                    @if ($building->municipal_license_no)
                                        <div class="stk-def__row"><dt>رخصة البلدية</dt><dd class="stk-num">{{ $building->municipal_license_no }}</dd></div>
                                    @endif
                                    @if ($building->entity_type !== 'land')
                                    <div class="stk-def__row"><dt>سنة البناء</dt><dd class="stk-num">
                                        @if ($building->construction_year_hijri || $building->construction_year)
                                            {{ $building->construction_year_hijri ? $building->construction_year_hijri.' هـ' : '' }}@if ($building->construction_year_hijri && $building->construction_year) / @endif{{ $building->construction_year ? $building->construction_year.' م' : '' }}
                                        @else
                                            —
                                        @endif
                                    </dd></div>
                                    @endif
                                    <div class="stk-def__row"><dt>الخزينة الافتراضية للمبنى</dt><dd>{{ $treasuryName ?: '— (تُختار عند كل دفعة)' }}</dd></div>
                                    <div class="stk-def__row"><dt>مستودع المبنى</dt><dd>
                                        @if ($building->storeHouse)
                                            @if (hasPermission('stock.warehouses.show') && Route::has('storehouse.show'))
                                                <a href="{{ route('storehouse.show', $building->storeHouse->id) }}"><i class="feather icon-package"></i> {{ $building->storeHouse->name }}</a>
                                            @else
                                                {{ $building->storeHouse->name }}
                                            @endif
                                        @else
                                            —
                                        @endif
                                    </dd></div>
                                    <div class="stk-def__row"><dt>الحساب في دليل الحسابات</dt><dd>@include('rentalmanagement::realestate.components.account-link', ['account' => $building->account])</dd></div>
                                    @unless ($single)
                                        <div class="stk-def__row"><dt>الوحدات المتوقعة</dt><dd class="stk-num">{{ $building->expected_units_count }} (مسجَّل {{ $units->count() }})</dd></div>
                                    @endunless
                                    <div class="stk-def__row"><dt>أنشأه</dt><dd>{{ $building->creator->name ?? '—' }} <small class="text-muted stk-num">· {{ $building->created_at?->format('Y-m-d') }}</small></dd></div>
                                </dl>
                                @if ($building->description) <p class="mt-1">{{ $building->description }}</p> @endif
                                @include('rentalmanagement::realestate.components.file-list', ['paths' => $building->filePaths('images'), 'images' => true])
                            </div>
                            <div class="col-lg-5">
                                @if ($single && $autoUnit)
                                    <h6 class="mb-1">وحدة العقار <span class="badge badge-light-{{ $autoUnit->occupancy_meta['tone'] }}">{{ $autoUnit->occupancy_meta['label'] }}</span></h6>
                                    <dl class="stk-def mb-2">
                                        <div class="stk-def__row"><dt>النوع</dt><dd>{{ $autoUnit->type_label }} · {{ $autoUnit->usage_label }}</dd></div>
                                        <div class="stk-def__row"><dt>المساحة</dt><dd class="stk-num">{{ $autoUnit->area_sqm ? $area($autoUnit->area_sqm) : '—' }}</dd></div>
                                        <div class="stk-def__row"><dt>السعر الإرشادي</dt><dd class="stk-num">{{ $money($autoUnit->base_rent) }} / {{ V::label(V::RENT_PERIODS, $autoUnit->rent_period) }}</dd></div>
                                        <div class="stk-def__row"><dt>المستأجر الحالي</dt><dd>{{ $autoUnit->activeContract->tenant->display_name ?? '—' }}</dd></div>
                                        <div class="stk-def__row"><dt>صفحة الوحدة</dt><dd><a href="{{ route('realestate.units.show', $autoUnit->id) }}">عقودها وعروضها وصيانتها</a></dd></div>
                                    </dl>
                                @endif
                                @if ($building->hasFloors())
                                <h6 class="mb-1">الطوابق <span class="stk-count">{{ $building->floors->count() }}</span></h6>
                                <ul class="list-group mb-1">
                                    @forelse ($building->floors as $floor)
                                        <li class="list-group-item d-flex justify-content-between align-items-center py-50">
                                            <span>{{ $floor->label }} <small class="text-muted">· {{ $floor->units->count() }} وحدة</small></span>
                                            @permission('realestate.buildings.edit')
                                                @if ($floor->units->isEmpty())
                                                    <form method="POST" action="{{ route('realestate.buildings.floors.destroy', [$building->id, $floor->id]) }}" class="m-0">
                                                        @csrf @method('DELETE')
                                                        <button class="btn btn-sm btn-link text-danger p-0" aria-label="حذف {{ $floor->label }}"><i class="feather icon-x"></i></button>
                                                    </form>
                                                @endif
                                            @endpermission
                                        </li>
                                    @empty
                                        <li class="list-group-item text-muted">لا طوابق مسجّلة.</li>
                                    @endforelse
                                </ul>
                                @permission('realestate.buildings.edit')
                                    <form method="POST" action="{{ route('realestate.buildings.floors.store', $building->id) }}" class="d-flex" style="gap:.4rem">
                                        @csrf
                                        <input type="number" name="number" class="form-control" placeholder="رقم الطابق" required style="max-width:120px" value="{{ ($building->floors->max('number') ?? -1) + 1 }}">
                                        <input type="text" name="name" class="form-control" placeholder="اسم اختياري (الميزانين…)">
                                        <button class="btn btn-outline-primary"><i class="feather icon-plus"></i></button>
                                    </form>
                                @endpermission
                                @else
                                    <p class="text-muted mb-0"><i class="feather icon-info"></i> الأرض لا طوابق لها.</p>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane" id="tab-units" role="tabpanel">
                        @if ($units->isEmpty())
                            @include('rentalmanagement::realestate.components.empty', ['icon' => 'grid', 'title' => 'لا وحدات', 'hint' => 'أضف وحدات هذا العقار'])
                            @unless ($single)
                                @permission('realestate.units.create')
                                    <div class="text-center mb-1">
                                        <a href="{{ route('realestate.units.create', ['building_id' => $building->id]) }}" class="btn btn-primary"><i class="feather icon-plus"></i> إضافة وحدة</a>
                                        <button type="button" class="btn btn-outline-primary js-bulk-units"><i class="feather icon-layers"></i> إضافة وحدات دفعة واحدة</button>
                                    </div>
                                @endpermission
                            @endunless
                        @else
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead><tr><th>الوحدة</th><th>الاستخدام</th><th>الطابق</th><th class="text-center">المساحة</th><th class="text-end">السعر الإرشادي</th><th class="text-end">الإيجار الحالي</th><th>المستأجر الحالي</th><th class="text-center">الحالة</th></tr></thead>
                                    <tbody>
                                        @foreach ($units as $unit)
                                            <tr>
                                                <td><a href="{{ route('realestate.units.show', $unit->id) }}">{{ $unit->type_label }} {{ $unit->unit_no }}</a></td>
                                                <td>{{ $unit->usage_label }}</td>
                                                <td>{{ $unit->floor->label ?? '—' }}</td>
                                                <td class="text-center stk-num">{{ $unit->area_sqm ?: '—' }}</td>
                                                <td class="text-end re-num">{{ $money($unit->base_rent) }} <small class="text-muted">/ {{ V::label(V::RENT_PERIODS, $unit->rent_period) }}</small></td>
                                                <td class="text-end re-num">{{ $unit->current_rent !== null ? $money($unit->current_rent) : '—' }}</td>
                                                <td>{{ $unit->activeContract->tenant->display_name ?? '—' }}</td>
                                                <td class="text-center"><span class="badge badge-light-{{ $unit->occupancy_meta['tone'] }}">{{ $unit->occupancy_meta['label'] }}</span></td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>

                    <div class="tab-pane" id="tab-contracts" role="tabpanel">
                        @if ($contracts->isEmpty())
                            @include('rentalmanagement::realestate.components.empty', ['icon' => 'file-text', 'title' => 'لا عقود', 'hint' => 'لم يُبرم عقدٌ على وحدات هذا العقار بعد'])
                        @else
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead><tr><th>العقد</th><th>المستأجر</th><th>الوحدة</th><th>الفترة</th><th class="text-end">القيمة السنوية</th><th class="text-center">الحالة</th></tr></thead>
                                    <tbody>
                                        @foreach ($contracts as $contract)
                                            <tr>
                                                <td><a href="{{ route('realestate.contracts.show', $contract->id) }}">{{ $contract->code }}</a></td>
                                                <td>{{ $contract->tenant->display_name ?? '—' }}</td>
                                                <td>{{ $contract->unit->unit_no ?? '—' }}</td>
                                                <td class="stk-num">{{ $contract->start_date->format('Y-m-d') }} ← {{ $contract->end_date->format('Y-m-d') }}</td>
                                                <td class="text-end re-num">{{ $money($contract->annual_value) }}</td>
                                                <td class="text-center"><span class="badge badge-light-{{ $contract->status_meta['tone'] }}">{{ $contract->status_meta['label'] }}</span></td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>

                    <div class="tab-pane" id="tab-listings" role="tabpanel">
                        @if ($listings->isEmpty())
                            @include('rentalmanagement::realestate.components.empty', ['icon' => 'tag', 'title' => 'لا عروض', 'hint' => 'لم يُنشر عرضٌ لهذا العقار أو وحداته'])
                        @else
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead><tr><th>العرض</th><th>العنوان</th><th>النوع</th><th class="text-end">السعر</th><th class="text-center">الحالة</th></tr></thead>
                                    <tbody>
                                        @foreach ($listings as $listing)
                                            <tr>
                                                <td><a href="{{ route('realestate.listings.show', $listing->id) }}">{{ $listing->code }}</a></td>
                                                <td>{{ $listing->title }}</td>
                                                <td>{{ $listing->type_meta['label'] }}</td>
                                                <td class="text-end re-num">{{ $money($listing->asking_price) }}</td>
                                                <td class="text-center"><span class="badge badge-light-{{ $listing->status_meta['tone'] }}">{{ $listing->status_meta['label'] }}</span></td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>

                    @if ($canMoney)
                        <div class="tab-pane" id="tab-finance" role="tabpanel">
                            @include('rentalmanagement::realestate.components.kpis', ['items' => [
                                ['label' => 'قيمة العقود السارية (سنوياً)', 'value' => $money($finance['active_value']), 'icon' => 'file-text', 'tone' => 'primary'],
                                ['label' => 'إجمالي المحصَّل (مرحَّل)', 'value' => $money($finance['collected']), 'icon' => 'check-circle', 'tone' => 'success'],
                                ['label' => 'المتأخرات', 'value' => $money($finance['overdue']), 'icon' => 'alert-circle', 'tone' => $finance['overdue'] > 0 ? 'danger' : 'success'],
                                ['label' => 'تكاليف الصيانة', 'value' => $money($finance['maintenance_cost']), 'icon' => 'tool', 'tone' => 'warning'],
                            ]])
                            <p class="text-muted mb-0">المحصَّل من الدفعات المرحّلة فقط (إيجار وتأمين). تكاليف الصيانة تشغيلية ما لم تُقيَّد في الحسابات.</p>
                        </div>
                    @endif

                    <div class="tab-pane" id="tab-maintenance" role="tabpanel">
                        @if ($maintenance->isEmpty())
                            @include('rentalmanagement::realestate.components.empty', ['icon' => 'tool', 'title' => 'لا طلبات صيانة', 'hint' => 'لم يُفتح بلاغٌ على هذا العقار'])
                        @else
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead><tr><th>الطلب</th><th>البلاغ</th><th>التصنيف</th><th class="text-center">الأولوية</th><th class="text-end">التكلفة</th><th class="text-center">الحالة</th></tr></thead>
                                    <tbody>
                                        @foreach ($maintenance as $item)
                                            <tr>
                                                <td><a href="{{ route('realestate.maintenance.show', $item->id) }}">{{ $item->code }}</a></td>
                                                <td>{{ $item->title }}</td>
                                                <td>{{ $item->category_label }}</td>
                                                <td class="text-center"><span class="badge badge-light-{{ $item->priority_meta['tone'] }}">{{ $item->priority_meta['label'] }}</span></td>
                                                <td class="text-end re-num">{{ $money($item->actual_cost) }}</td>
                                                <td class="text-center"><span class="badge badge-light-{{ $item->status_meta['tone'] }}">{{ $item->status_meta['label'] }}</span></td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>

                    <div class="tab-pane" id="tab-documents" role="tabpanel">
                        @include('rentalmanagement::realestate.components.file-list', ['paths' => $building->filePaths('attachments')])
                    </div>

                    <div class="tab-pane" id="tab-activity" role="tabpanel">
                        @include('rentalmanagement::realestate.components.timeline', ['logs' => $logs])
                    </div>
                </div>
            </div>
        </div>
    </div>

    @unless ($single)
        @permission('realestate.units.create')
            @include('rentalmanagement::realestate.units.partials.bulk-modal', ['bulkBuildings' => collect([$building]), 'bulkBuilding' => $building])
        @endpermission
    @endunless

    @if ($building->latitude && $building->longitude)
        <div class="modal fade" id="buildingMapModal" tabindex="-1" aria-labelledby="buildingMapTitle" aria-hidden="true">
            <div class="modal-dialog modal-xl">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="buildingMapTitle"><i class="feather icon-map-pin"></i> موقع {{ $building->name }}</h5>
                        <button type="button" class="btn-close close" data-dismiss="modal" data-bs-dismiss="modal" aria-label="إغلاق"></button>
                    </div>
                    <div class="modal-body p-0">
                        <div id="buildingMap" style="height:65vh;min-height:360px"></div>
                    </div>
                    <div class="modal-footer">
                        @permission('realestate.buildings.view')
                            <a href="{{ route('realestate.buildings.map', ['focus' => $building->id]) }}" class="btn btn-outline-primary"><i class="feather icon-map"></i> كل العقارات على الخريطة</a>
                        @endpermission
                        <a href="https://www.google.com/maps/dir/?api=1&destination={{ $building->latitude }},{{ $building->longitude }}" target="_blank" rel="noopener" class="btn btn-outline-secondary"><i class="feather icon-navigation"></i> الاتجاهات في خرائط Google</a>
                        <button type="button" class="btn btn-secondary" data-dismiss="modal" data-bs-dismiss="modal">إغلاق</button>
                    </div>
                </div>
            </div>
        </div>

        @push('scripts')
            @include('rentalmanagement::realestate.components.map-provider')
            <script>
                $(function () {
                    const point = { lat: {{ (float) $building->latitude }}, lng: {{ (float) $building->longitude }} };
                    let map = null, started = false;

                    $('#showOnMap, .js-show-on-map').on('click', function (e) {
                        e.preventDefault();
                        reModal($('#buildingMapModal'), true);

                        // تُبنى بعد ظهور النافذة: خريطةٌ تُنشأ في عنصرٍ مخفيّ تُرسم رمادية
                        setTimeout(function () {
                            if (map) { map.resize(); return map.setView(point, 17); }
                            if (started) return;
                            started = true;

                            reMap.init(function (api) {
                                map = api.map(document.getElementById('buildingMap'), { center: point, zoom: 17 });
                                map.marker(point, { title: @json($building->name) });
                            }, function () {
                                reModal($('#buildingMapModal'), false);
                                window.open('https://www.google.com/maps?q=' + point.lat + ',' + point.lng, '_blank', 'noopener');
                            });
                        }, 300);
                    });
                });
            </script>
        @endpush
    @endif
@endsection

@section('scripts')
    @include('rentalmanagement::realestate.components.list-scripts')
    @include('rentalmanagement::realestate.components.ajax-form-scripts')
@endsection
