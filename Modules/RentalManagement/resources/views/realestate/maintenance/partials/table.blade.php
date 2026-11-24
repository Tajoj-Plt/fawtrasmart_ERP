@php use Modules\RentalManagement\Support\RealEstateVocabulary as V; @endphp
@if ($requests->count() > 0)
    <div class="d-flex justify-content-end p-2 pb-0">@include('stock::components.per-page', ['onchange' => 'loadData(1)'])</div>

    <div class="table-responsive">
        <table class="table table-hover mb-0" id="reportTable">
            <thead>
                <tr><th>الطلب</th><th>الموقع</th><th>المبلِّغ</th><th class="text-center">الأولوية</th><th>المسؤول</th><th>الموعد</th><th class="text-end">التكلفة الفعلية</th><th class="text-center">الحالة</th><th></th></tr>
            </thead>
            <tbody>
                @foreach ($requests as $item)
                    @php $closed = in_array($item->status, ['completed', 'cancelled'], true); @endphp
                    <tr>
                        <td>
                            <div class="stk-media">
                                <span class="stk-media__thumb text-primary"><i class="feather icon-{{ V::MAINTENANCE_CATEGORIES[$item->category]['icon'] ?? 'tool' }}"></i></span>
                                <div class="stk-media__body">
                                    <a href="{{ hasPermission('realestate.maintenance.show') ? route('realestate.maintenance.show', $item->id) : '#' }}" class="stk-media__title">{{ $item->title }}</a>
                                    <span class="stk-media__sub stk-num">{{ $item->code }} · {{ $item->category_label }}</span>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="badge badge-light-{{ $item->scope_meta['tone'] }}"><i class="feather icon-{{ $item->scope_meta['icon'] }}"></i> {{ $item->scope_meta['label'] }}</span>
                            <span class="d-block mt-25">{{ $item->location_label }}</span>
                        </td>
                        <td>{{ $item->reporter->display_name ?? '—' }}</td>
                        <td class="text-center"><span class="badge badge-light-{{ $item->priority_meta['tone'] }}">{{ $item->priority_meta['label'] }}</span></td>
                        <td>{{ $item->assignee->name ?? '—' }}</td>
                        <td class="stk-num {{ $item->isLate() ? 'text-danger' : '' }}">
                            {{ $item->scheduled_at?->format('Y-m-d H:i') ?? '—' }}
                            @if ($item->isLate()) <span class="badge badge-light-danger">متأخر</span> @endif
                        </td>
                        <td class="text-end re-num">{{ \Modules\RentalManagement\Support\RealEstateMoney::format($item->actual_cost) }}</td>
                        <td class="text-center"><span class="badge badge-light-{{ $item->status_meta['tone'] }}">{{ $item->status_meta['label'] }}</span></td>
                        <td class="text-center">
                            <div class="dropdown">
                                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="dropdown" aria-expanded="false" aria-label="إجراءات {{ $item->code }}">
                                    <i class="feather icon-more-vertical"></i>
                                </button>
                                <div class="dropdown-menu dropdown-menu-end">
                                    @permission('realestate.maintenance.show')
                                        <a class="dropdown-item" href="{{ route('realestate.maintenance.show', $item->id) }}"><i class="feather icon-eye"></i> عرض</a>
                                    @endpermission
                                    @permission('realestate.maintenance.edit')
                                        @unless ($closed)
                                            <a class="dropdown-item" href="{{ route('realestate.maintenance.edit', $item->id) }}"><i class="feather icon-edit"></i> تعديل</a>
                                        @endunless
                                    @endpermission
                                    @permission('realestate.maintenance.delete')
                                        <div class="dropdown-divider"></div>
                                        <a class="dropdown-item text-danger re-action" href="#" data-url="{{ route('realestate.maintenance.destroy', $item->id) }}"
                                           data-method="DELETE" data-tone="danger" data-confirm="حذف طلب الصيانة «{{ $item->code }}»؟">
                                            <i class="feather icon-trash-2"></i> حذف
                                        </a>
                                    @endpermission
                                </div>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @include('rentalmanagement::realestate.components.pagination', ['paginator' => $requests, 'unit' => 'طلباً'])
@else
    @include('rentalmanagement::realestate.components.empty', ['icon' => 'tool', 'title' => 'لا طلبات صيانة', 'hint' => 'افتح أول بلاغ صيانة، أو عدّل معايير البحث'])
@endif
