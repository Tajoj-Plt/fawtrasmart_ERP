@php use Modules\RentalManagement\Support\RealEstateVocabulary as V; @endphp
@if ($units->count() > 0)
    <div class="d-flex justify-content-end p-2 pb-0">@include('stock::components.per-page', ['onchange' => 'loadData(1)'])</div>

    <div class="table-responsive">
        <table class="table table-hover mb-0" id="reportTable">
            <thead>
                <tr><th style="width:24%">الوحدة</th><th>الطابق</th><th class="text-center">المساحة</th><th class="text-center">غرف</th><th class="text-end">السعر الإرشادي</th><th class="text-end">الإيجار الحالي</th><th>المستأجر الحالي</th><th class="text-center">الحالة</th><th></th></tr>
            </thead>
            <tbody>
                @foreach ($units as $unit)
                    <tr>
                        <td>
                            <div class="stk-media">
                                @if ($unit->coverUrl())
                                    <span class="stk-media__thumb" style="background:url('{{ $unit->coverUrl() }}') center/cover"></span>
                                @else
                                    <span class="stk-media__thumb text-{{ $unit->occupancy_meta['tone'] }}"><i class="feather icon-grid"></i></span>
                                @endif
                                <div class="stk-media__body">
                                    <a href="{{ hasPermission('realestate.units.show') ? route('realestate.units.show', $unit->id) : '#' }}" class="stk-media__title">{{ $unit->type_label }} {{ $unit->unit_no }}</a>
                                    <span class="stk-media__sub">{{ $unit->building->name ?? '—' }} · {{ $unit->usage_label }}@if ($unit->is_auto) · تلقائية @endif</span>
                                </div>
                            </div>
                        </td>
                        <td>{{ $unit->floor->label ?? '—' }}</td>
                        <td class="text-center stk-num">{{ $unit->area_sqm ? rtrim(rtrim(number_format((float) $unit->area_sqm, 2), '0'), '.').' م²' : '—' }}</td>
                        <td class="text-center stk-num">{{ $unit->bedrooms ?: '—' }}</td>
                        <td class="text-end re-num">{{ \Modules\RentalManagement\Support\RealEstateMoney::format($unit->base_rent) }} <small class="text-muted">/ {{ V::label(V::RENT_PERIODS, $unit->rent_period) }}</small></td>
                        <td class="text-end re-num">
                            @if ($unit->current_rent !== null)
                                {{ \Modules\RentalManagement\Support\RealEstateMoney::format($unit->current_rent) }} <small class="text-muted">/ {{ V::label(V::RENT_PERIODS, $unit->rent_period) }}</small>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td>
                            @if ($unit->activeContract)
                                {{ $unit->activeContract->tenant->display_name ?? '—' }}
                                <small class="text-muted d-block stk-num">حتى {{ $unit->activeContract->end_date->format('Y-m-d') }}</small>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td class="text-center">
                            <span class="badge badge-light-{{ $unit->occupancy_meta['tone'] }}">{{ $unit->occupancy_meta['label'] }}</span>
                            @unless ($unit->is_marketable) <small class="d-block text-muted">غير قابلة للتسويق</small> @endunless
                        </td>
                        <td class="text-center">
                            <div class="dropdown">
                                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="dropdown" aria-expanded="false" aria-label="إجراءات الوحدة {{ $unit->unit_no }}"><i class="feather icon-more-vertical"></i></button>
                                <div class="dropdown-menu dropdown-menu-end">
                                    @permission('realestate.units.show')
                                        <a class="dropdown-item" href="{{ route('realestate.units.show', $unit->id) }}"><i class="feather icon-eye"></i> عرض</a>
                                    @endpermission
                                    @permission('realestate.units.edit')
                                        <a class="dropdown-item" href="{{ route('realestate.units.edit', $unit->id) }}"><i class="feather icon-edit"></i> تعديل</a>
                                    @endpermission
                                    @if ($unit->occupancy_status === 'vacant')
                                        @permission('realestate.contracts.create')
                                            <a class="dropdown-item" href="{{ route('realestate.contracts.create', ['unit_id' => $unit->id]) }}"><i class="feather icon-file-text"></i> إنشاء عقد</a>
                                        @endpermission
                                        @permission('realestate.listings.create')
                                            <a class="dropdown-item" href="{{ route('realestate.listings.create', ['unit_id' => $unit->id, 'convert' => 1]) }}"><i class="feather icon-tag"></i> تحويل إلى عرض عقاري</a>
                                        @endpermission
                                    @endif
                                    @permission('realestate.maintenance.create')
                                        <a class="dropdown-item" href="{{ route('realestate.maintenance.create', ['unit_id' => $unit->id]) }}"><i class="feather icon-tool"></i> طلب صيانة</a>
                                    @endpermission
                                    @permission('realestate.units.change_status')
                                        @unless ($unit->activeContract)
                                            <div class="dropdown-divider"></div>
                                            @foreach (V::OCCUPANCY as $status => $meta)
                                                @continue($status === $unit->occupancy_status || $status === 'rented')
                                                <a class="dropdown-item re-action" href="#" data-url="{{ route('realestate.units.status', $unit->id) }}"
                                                   data-payload='@json(['occupancy_status' => $status])' data-confirm="تغيير حالة الوحدة إلى «{{ $meta['label'] }}»؟">
                                                    <i class="feather icon-{{ $meta['icon'] }}"></i> اجعلها {{ $meta['label'] }}
                                                </a>
                                            @endforeach
                                        @endunless
                                    @endpermission
                                    @if (! $unit->is_auto && hasPermission('realestate.units.delete'))
                                        <div class="dropdown-divider"></div>
                                        <a class="dropdown-item text-danger re-action" href="#" data-url="{{ route('realestate.units.destroy', $unit->id) }}"
                                           data-method="DELETE" data-tone="danger" data-confirm="حذف الوحدة {{ $unit->unit_no }}؟"><i class="feather icon-trash-2"></i> حذف</a>
                                    @endif
                                </div>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @include('rentalmanagement::realestate.components.pagination', ['paginator' => $units, 'unit' => 'وحدة'])
@else
    @include('rentalmanagement::realestate.components.empty', ['icon' => 'grid', 'title' => 'لا وحدات', 'hint' => 'أضف وحدات العقار أو عدّل معايير البحث'])
@endif
