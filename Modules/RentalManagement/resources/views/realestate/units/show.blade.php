@extends('rentalmanagement::realestate.master')

@section('title', $unit->full_label)

@section('content')
    @php
        use Modules\RentalManagement\Support\RealEstateVocabulary as V;
        // المبالغ تُعرض بعملتها (RealEstateMoney من وكيل المحاسبة؛ وقبل وجوده عملة النظام)
        $money = fn ($v, $currency = null) => class_exists(\Modules\RentalManagement\Support\RealEstateMoney::class)
            ? \Modules\RentalManagement\Support\RealEstateMoney::format($v, $currency)
            : number_format((float) $v, 2).' '.\App\Support\CurrencySettings::symbol();
        $commercial = $unit->usage_type === 'commercial';
        $active = $unit->activeContract;
    @endphp

    <div class="content-body">

        @section('page-actions')
            @if ($unit->occupancy_status === 'vacant')
                @permission('realestate.listings.create')
                    <a href="{{ route('realestate.listings.create', ['unit_id' => $unit->id, 'convert' => 1]) }}" class="btn btn-outline-secondary"><i class="feather icon-tag"></i> تحويل إلى عرض عقاري</a>
                @endpermission
                @permission('realestate.contracts.create')
                    <a href="{{ route('realestate.contracts.create', ['unit_id' => $unit->id]) }}" class="btn btn-outline-primary"><i class="feather icon-file-text"></i> إنشاء عقد</a>
                @endpermission
            @endif
            @permission('realestate.maintenance.create')
                <a href="{{ route('realestate.maintenance.create', ['unit_id' => $unit->id]) }}" class="btn btn-outline-secondary"><i class="feather icon-tool"></i> طلب صيانة</a>
            @endpermission
            @permission('realestate.units.edit')
                <a href="{{ route('realestate.units.edit', $unit->id) }}" class="btn btn-primary"><i class="feather icon-edit"></i> تعديل</a>
            @endpermission
        @endsection

        @include('stock::components.page-head', [
            'title' => $unit->type_label.' '.$unit->unit_no,
            'icon' => 'grid',
            'sub' => ($unit->building->name ?? '').($unit->floor ? ' · '.$unit->floor->label : ''),
            'crumbs' => ['الرئيسية' => url('/'), 'الوحدات' => route('realestate.units.index'), $unit->unit_no => null],
        ])

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')

        <div class="stk-metrics mb-2">
            <div class="stk-metric"><span class="stk-metric__icon"><i class="feather icon-flag"></i></span>
                <span class="stk-metric__value"><span class="badge badge-light-{{ $unit->occupancy_meta['tone'] }}">{{ $unit->occupancy_meta['label'] }}</span></span><span class="stk-metric__label">حالة الإشغال</span></div>
            <div class="stk-metric"><span class="stk-metric__icon"><i class="feather icon-maximize"></i></span><span class="stk-metric__value stk-num">{{ $unit->area_sqm ? $unit->area_sqm.' م²' : '—' }}</span><span class="stk-metric__label">المساحة</span></div>
            <div class="stk-metric"><span class="stk-metric__icon"><i class="feather icon-dollar-sign"></i></span><span class="stk-metric__value stk-num">{{ $money($unit->base_rent) }}</span><span class="stk-metric__label">السعر الإرشادي / {{ V::label(V::RENT_PERIODS, $unit->rent_period) }}</span></div>
            @if ($unit->current_rent !== null)
                <div class="stk-metric"><span class="stk-metric__icon"><i class="feather icon-key"></i></span><span class="stk-metric__value stk-num">{{ $money($unit->current_rent) }}</span><span class="stk-metric__label">الإيجار الحالي / {{ V::label(V::RENT_PERIODS, $unit->rent_period) }}</span></div>
            @endif
            <div class="stk-metric"><span class="stk-metric__icon"><i class="feather icon-user"></i></span><span class="stk-metric__value">{{ $active->tenant->display_name ?? '—' }}</span><span class="stk-metric__label">المستأجر الحالي</span></div>
            @if ($unit->occupancy_status === 'vacant' && $unit->vacant_since)
                <div class="stk-metric"><span class="stk-metric__icon"><i class="feather icon-clock"></i></span><span class="stk-metric__value stk-num">{{ (int) $unit->vacant_since->diffInDays(now()) }} يوماً</span><span class="stk-metric__label">مدّة الشغور</span></div>
            @endif
        </div>

        <div class="row">
            <div class="col-lg-5">
                <div class="card">
                    <div class="card-header"><h4 class="card-title"><i class="feather icon-info"></i> المواصفات</h4></div>
                    <div class="card-body">
                        <dl class="stk-def">
                            <div class="stk-def__row"><dt>المبنى</dt><dd>
                                @if (hasPermission('realestate.buildings.show')) <a href="{{ route('realestate.buildings.show', $unit->building_id) }}">{{ $unit->building->name ?? '—' }}</a> @else {{ $unit->building->name ?? '—' }} @endif
                            </dd></div>
                            <div class="stk-def__row"><dt>النوع والاستخدام</dt><dd>{{ $unit->type_label }} · <span class="badge badge-light-{{ $commercial ? 'warning' : 'info' }}">{{ $unit->usage_label }}</span>
                                @if ($unit->is_auto) <span class="badge badge-light-secondary">وحدة تلقائية</span> @endif
                            </dd></div>
                            <div class="stk-def__row"><dt>حساب الوحدة</dt><dd>@include('rentalmanagement::realestate.components.account-link', ['account' => $unit->account])</dd></div>
                            <div class="stk-def__row"><dt>المالك</dt><dd>{{ $unit->building->owner->display_name ?? '—' }}</dd></div>
                            @if ($commercial)
                                <div class="stk-def__row"><dt>الواجهة</dt><dd class="stk-num">{{ $unit->frontage_m ? (float) $unit->frontage_m.' م' : '—' }}</dd></div>
                                <div class="stk-def__row"><dt>ارتفاع السقف</dt><dd class="stk-num">{{ $unit->ceiling_height_m ? (float) $unit->ceiling_height_m.' م' : '—' }}</dd></div>
                                <div class="stk-def__row"><dt>عدد الفتحات</dt><dd class="stk-num">{{ $unit->openings_count ?? '—' }}</dd></div>
                                <div class="stk-def__row"><dt>النشاط المسموح</dt><dd>{{ $unit->permitted_activity ?: '—' }}</dd></div>
                            @elseif ($unit->unit_type !== 'land')
                                <div class="stk-def__row"><dt>الغرف</dt><dd class="stk-num">{{ $unit->bedrooms }} نوم · {{ $unit->bathrooms }} حمام · {{ $unit->living_rooms }} صالة · {{ $unit->kitchens }} مطبخ</dd></div>
                            @endif
                            <div class="stk-def__row"><dt>المواقف</dt><dd class="stk-num">{{ $unit->parking_count }}</dd></div>
                            <div class="stk-def__row"><dt>الاتجاه</dt><dd>{{ V::label(V::ORIENTATIONS, $unit->orientation) }}</dd></div>
                            @if (! $commercial && $unit->unit_type !== 'land')
                                <div class="stk-def__row"><dt>التأثيث</dt><dd>{{ V::label(V::FURNISHED, $unit->furnished_status) }}</dd></div>
                            @endif
                            <div class="stk-def__row"><dt>الحالة الفنية</dt><dd>{{ V::label(V::UNIT_CONDITIONS, $unit->unit_condition) }}</dd></div>
                            <div class="stk-def__row"><dt>قابلة للتسويق</dt><dd>{{ $unit->is_marketable ? 'نعم' : 'لا' }}</dd></div>
                            <div class="stk-def__row"><dt>متاحة من</dt><dd class="stk-num">{{ $unit->availability_from?->format('Y-m-d') ?? '—' }}</dd></div>
                            <div class="stk-def__row"><dt>عداد الكهرباء</dt><dd class="stk-num">{{ $unit->electricity_meter ?: '—' }}</dd></div>
                            <div class="stk-def__row"><dt>عداد المياه</dt><dd class="stk-num">{{ $unit->water_meter ?: '—' }}</dd></div>
                            <div class="stk-def__row"><dt>أنشأها</dt><dd>{{ $unit->creator->name ?? '—' }} <small class="text-muted stk-num">· {{ $unit->created_at?->format('Y-m-d') }}</small></dd></div>
                        </dl>
                        @if ($unit->amenities->isNotEmpty())
                            <div class="stk-tags mt-1">@foreach ($unit->amenities as $amenity) <span class="stk-tag">{{ $amenity->name }}</span> @endforeach</div>
                        @endif
                        @if ($unit->description) <p class="mt-1 mb-0">{{ $unit->description }}</p> @endif
                        @if ($unit->notes) <p class="mt-1 mb-0 text-muted"><i class="feather icon-lock"></i> {{ $unit->notes }}</p> @endif
                    </div>
                </div>
                <div class="card">
                    <div class="card-header"><h4 class="card-title"><i class="feather icon-image"></i> الصور</h4></div>
                    <div class="card-body">@include('rentalmanagement::realestate.components.file-list', ['paths' => $unit->filePaths('images'), 'images' => true])</div>
                </div>
            </div>

            <div class="col-lg-7">
                <div class="card">
                    <div class="card-body">
                        <ul class="nav nav-tabs stk-tabs" role="tablist">
                            <li class="nav-item"><a class="nav-link active" data-toggle="tab" data-bs-toggle="tab" href="#tab-contracts" role="tab"><i class="feather icon-file-text"></i> العقود عبر الزمن</a></li>
                            <li class="nav-item"><a class="nav-link" data-toggle="tab" data-bs-toggle="tab" href="#tab-listings" role="tab"><i class="feather icon-tag"></i> العروض</a></li>
                            <li class="nav-item"><a class="nav-link" data-toggle="tab" data-bs-toggle="tab" href="#tab-maintenance" role="tab"><i class="feather icon-tool"></i> الصيانة</a></li>
                            <li class="nav-item"><a class="nav-link" data-toggle="tab" data-bs-toggle="tab" href="#tab-activity" role="tab"><i class="feather icon-activity"></i> النشاط</a></li>
                        </ul>
                        <div class="tab-content pt-2">
                            <div class="tab-pane active" id="tab-contracts" role="tabpanel">
                                @forelse ($contracts as $contract)
                                    <div class="d-flex justify-content-between align-items-center py-50 border-bottom">
                                        <div>
                                            <a href="{{ route('realestate.contracts.show', $contract->id) }}"><strong>{{ $contract->code }}</strong></a> — {{ $contract->tenant->display_name ?? '—' }}
                                            <small class="text-muted d-block stk-num">{{ $contract->start_date->format('Y-m-d') }} ← {{ $contract->end_date->format('Y-m-d') }} · {{ $money($contract->annual_value, $contract->currency ?? null) }} سنوياً</small>
                                        </div>
                                        <span class="badge badge-light-{{ $contract->status_meta['tone'] }}">{{ $contract->status_meta['label'] }}</span>
                                    </div>
                                @empty
                                    @include('rentalmanagement::realestate.components.empty', ['icon' => 'file-text', 'title' => 'لا عقود', 'hint' => 'لم تُؤجَّر هذه الوحدة من قبل'])
                                @endforelse
                            </div>
                            <div class="tab-pane" id="tab-listings" role="tabpanel">
                                @forelse ($listings as $listing)
                                    <div class="d-flex justify-content-between align-items-center py-50 border-bottom">
                                        <div>
                                            <a href="{{ route('realestate.listings.show', $listing->id) }}"><strong>{{ $listing->code }}</strong></a> — {{ $listing->title }}
                                            <small class="text-muted d-block stk-num">{{ $listing->type_meta['label'] }} · {{ $money($listing->asking_price) }} · {{ $listing->inquiries_count }} استفساراً</small>
                                        </div>
                                        <span class="badge badge-light-{{ $listing->status_meta['tone'] }}">{{ $listing->status_meta['label'] }}</span>
                                    </div>
                                @empty
                                    @include('rentalmanagement::realestate.components.empty', ['icon' => 'tag', 'title' => 'لا عروض', 'hint' => 'الوحدة لم تُعرض للتسويق'])
                                @endforelse
                            </div>
                            <div class="tab-pane" id="tab-maintenance" role="tabpanel">
                                @forelse ($maintenance as $item)
                                    <div class="d-flex justify-content-between align-items-center py-50 border-bottom">
                                        <div>
                                            <a href="{{ route('realestate.maintenance.show', $item->id) }}"><strong>{{ $item->code }}</strong></a> — {{ $item->title }}
                                            <small class="text-muted d-block">{{ $item->category_label }} · {{ $item->created_at->format('Y-m-d') }} · {{ $money($item->actual_cost) }}</small>
                                        </div>
                                        <span class="badge badge-light-{{ $item->status_meta['tone'] }}">{{ $item->status_meta['label'] }}</span>
                                    </div>
                                @empty
                                    @include('rentalmanagement::realestate.components.empty', ['icon' => 'tool', 'title' => 'لا طلبات صيانة', 'hint' => 'لم يُفتح بلاغٌ على هذه الوحدة'])
                                @endforelse
                            </div>
                            <div class="tab-pane" id="tab-activity" role="tabpanel">
                                @include('rentalmanagement::realestate.components.timeline', ['logs' => $logs])
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
