@php use Modules\RentalManagement\Support\RealEstateVocabulary as V; @endphp
@if ($buildings->count() > 0)
    <div class="d-flex justify-content-end p-2 pb-0">@include('stock::components.per-page', ['onchange' => 'loadData(1)', 'sizes' => [12, 24, 48]])</div>

    <div class="re-cards">
        @foreach ($buildings as $building)
            @php $rate = $building->units_count ? round($building->rented_units_count / $building->units_count * 100) : 0; @endphp
            <div class="re-card">
                <div class="re-card__cover" @if ($building->coverUrl()) style="background-image:url('{{ $building->coverUrl() }}')" @endif>
                    @unless ($building->coverUrl()) <i class="feather icon-home"></i> @endunless
                    <span class="badge badge-light-{{ $building->status_meta['tone'] }}">{{ $building->status_meta['label'] }}</span>
                </div>
                <div class="re-card__body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <a class="re-card__title" href="{{ hasPermission('realestate.buildings.show') ? route('realestate.buildings.show', $building->id) : '#' }}">{{ $building->name }}</a>
                            <span class="re-card__sub stk-num">{{ $building->code }}</span>
                        </div>
                        @include('rentalmanagement::realestate.buildings.partials._actions')
                    </div>
                    <p class="re-card__sub mt-50 mb-50">
                        <i class="feather icon-map-pin"></i> {{ collect([$building->city, $building->district])->filter()->implode(' — ') ?: 'بلا عنوان' }}
                        · {{ V::label(V::ENTITY_TYPES, $building->entity_type) }} {{ V::label(V::USAGE_TYPES, $building->usage_type) }}
                    </p>
                    <p class="re-card__sub mb-50"><i class="feather icon-user"></i> {{ $building->owner->display_name ?? 'بلا مالك محدَّد' }}</p>
                    <div class="d-flex justify-content-between"><small>الإشغال</small><small class="stk-num">{{ $rate }}%</small></div>
                    <div class="re-progress"><span style="width:{{ $rate }}%"></span></div>
                </div>
                <div class="re-card__stats">
                    <div><strong>{{ $building->units_count }}</strong><span>وحدة</span></div>
                    <div><strong class="text-success">{{ $building->rented_units_count }}</strong><span>مؤجرة</span></div>
                    <div><strong class="{{ $building->vacant_units_count ? 'text-warning' : '' }}">{{ $building->vacant_units_count }}</strong><span>شاغرة</span></div>
                </div>
            </div>
        @endforeach
    </div>

    @include('rentalmanagement::realestate.components.pagination', ['paginator' => $buildings, 'unit' => 'عقاراً'])
@else
    @include('rentalmanagement::realestate.components.empty', ['icon' => 'home', 'title' => 'لا عقارات', 'hint' => 'أضف أول مبنى أو مجمع لتبدأ، أو عدّل معايير البحث'])
@endif
