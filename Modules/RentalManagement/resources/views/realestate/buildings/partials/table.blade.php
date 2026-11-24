@php use Modules\RentalManagement\Support\RealEstateVocabulary as V; @endphp
@if ($buildings->count() > 0)
    <div class="d-flex justify-content-end p-2 pb-0">@include('stock::components.per-page', ['onchange' => 'loadData(1)'])</div>

    <div class="table-responsive">
        <table class="table table-hover mb-0" id="reportTable">
            <thead>
                <tr><th>العقار</th><th>النوع</th><th>المدينة</th><th>المالك</th><th class="text-center">الوحدات</th><th class="text-center">مؤجرة</th><th class="text-center">شاغرة</th><th class="text-center">الحالة</th><th></th></tr>
            </thead>
            <tbody>
                @foreach ($buildings as $building)
                    <tr>
                        <td>
                            <div class="stk-media">
                                <span class="stk-media__thumb text-primary"><i class="feather icon-home"></i></span>
                                <div class="stk-media__body">
                                    <a href="{{ hasPermission('realestate.buildings.show') ? route('realestate.buildings.show', $building->id) : '#' }}" class="stk-media__title">{{ $building->name }}</a>
                                    <span class="stk-media__sub stk-num">{{ $building->code }}@if ($building->complex) · {{ $building->complex->name_ar }}@endif</span>
                                </div>
                            </div>
                        </td>
                        <td>{{ V::label(V::ENTITY_TYPES, $building->entity_type) }} · {{ V::label(V::USAGE_TYPES, $building->usage_type) }}</td>
                        <td>{{ $building->city ?: '—' }}</td>
                        <td>{{ $building->owner->display_name ?? '—' }}</td>
                        <td class="text-center stk-num">{{ $building->units_count }}</td>
                        <td class="text-center stk-num">{{ $building->rented_units_count }}</td>
                        <td class="text-center stk-num">{{ $building->vacant_units_count }}</td>
                        <td class="text-center"><span class="badge badge-light-{{ $building->status_meta['tone'] }}">{{ $building->status_meta['label'] }}</span></td>
                        <td class="text-center">@include('rentalmanagement::realestate.buildings.partials._actions')</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @include('rentalmanagement::realestate.components.pagination', ['paginator' => $buildings, 'unit' => 'عقاراً'])
@else
    @include('rentalmanagement::realestate.components.empty', ['icon' => 'home', 'title' => 'لا عقارات', 'hint' => 'أضف أول مبنى أو مجمع لتبدأ، أو عدّل معايير البحث'])
@endif
