@php use Modules\RentalManagement\Support\RealEstateVocabulary as V; @endphp
@if ($contracts->count() > 0)
    <div class="d-flex justify-content-end p-2 pb-0">@include('stock::components.per-page', ['onchange' => 'loadData(1)'])</div>

    <div class="table-responsive">
        <table class="table table-hover mb-0" id="reportTable">
            <thead>
                <tr><th>العقد</th><th>المستأجر</th><th>الوحدة</th><th>الفترة</th><th class="text-end">القيمة السنوية</th><th>السداد</th><th class="text-center">الحالة</th><th></th></tr>
            </thead>
            <tbody>
                @foreach ($contracts as $contract)
                    @php $soon = in_array($contract->status, ['active', 'renewing'], true) && $contract->days_left <= 60; @endphp
                    <tr>
                        <td>
                            <div class="stk-media">
                                <span class="stk-media__thumb text-{{ $contract->status_meta['tone'] }}"><i class="feather icon-file-text"></i></span>
                                <div class="stk-media__body">
                                    <a href="{{ hasPermission('realestate.contracts.show') ? route('realestate.contracts.show', $contract->id) : '#' }}" class="stk-media__title">{{ $contract->code }}</a>
                                    <span class="stk-media__sub">{{ $contract->type_label }}@if ($contract->previous_contract_id) · تجديد @endif</span>
                                </div>
                            </div>
                        </td>
                        <td>{{ $contract->tenant->display_name ?? '—' }}</td>
                        <td>{{ $contract->unit->full_label ?? '—' }}</td>
                        <td class="stk-num">
                            {{ $contract->start_date->format('Y-m-d') }} ← {{ $contract->end_date->format('Y-m-d') }}
                            @if ($soon) <small class="d-block text-{{ $contract->days_left <= 15 ? 'danger' : 'warning' }}">{{ $contract->days_left >= 0 ? 'باقٍ '.$contract->days_left.' يوماً' : 'تجاوز النهاية بـ '.abs($contract->days_left).' يوماً' }}</small> @endif
                        </td>
                        <td class="text-end re-num">{{ $contract->money($contract->annual_value) }}</td>
                        <td>{{ V::label(V::PAYMENT_FREQUENCIES, $contract->payment_frequency) }}</td>
                        <td class="text-center"><span class="badge badge-light-{{ $contract->status_meta['tone'] }}">{{ $contract->status_meta['label'] }}</span></td>
                        <td class="text-center">
                            <div class="dropdown">
                                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="dropdown" aria-expanded="false" aria-label="إجراءات العقد {{ $contract->code }}"><i class="feather icon-more-vertical"></i></button>
                                <div class="dropdown-menu dropdown-menu-end">
                                    @permission('realestate.contracts.show')
                                        <a class="dropdown-item" href="{{ route('realestate.contracts.show', $contract->id) }}"><i class="feather icon-eye"></i> عرض</a>
                                    @endpermission
                                    @if ($contract->isEditable())
                                        @permission('realestate.contracts.edit')
                                            <a class="dropdown-item" href="{{ route('realestate.contracts.edit', $contract->id) }}"><i class="feather icon-edit"></i> تعديل</a>
                                        @endpermission
                                    @endif
                                    @if ($contract->status === 'pending_approval')
                                        @permission('realestate.contracts.approve')
                                            <a class="dropdown-item text-success re-action" href="#" data-url="{{ route('realestate.contracts.approve', $contract->id) }}" data-confirm="اعتماد العقد {{ $contract->code }} ليصير سارياً؟"><i class="feather icon-check-circle"></i> اعتماد</a>
                                        @endpermission
                                    @endif
                                    @if (in_array($contract->status, \Modules\RentalManagement\Models\Contract::COLLECTIBLE_STATUSES, true))
                                        @permission('realestate.payments.create')
                                            <a class="dropdown-item" href="{{ route('realestate.payments.create', ['contract_id' => $contract->id]) }}"><i class="feather icon-credit-card"></i> تسجيل دفعة</a>
                                        @endpermission
                                    @endif
                                    @if (in_array($contract->status, ['active', 'expired'], true) && ! $contract->renewedInto)
                                        @permission('realestate.contracts.renew')
                                            <a class="dropdown-item" href="{{ route('realestate.contracts.renew', $contract->id) }}"><i class="feather icon-refresh-cw"></i> تجديد</a>
                                        @endpermission
                                    @endif
                                    @permission('realestate.contracts.print')
                                        <a class="dropdown-item" href="{{ route('realestate.contracts.print', $contract->id) }}" target="_blank" rel="noopener"><i class="feather icon-printer"></i> طباعة</a>
                                    @endpermission
                                    @if ($contract->status === 'draft')
                                        @permission('realestate.contracts.delete')
                                            <div class="dropdown-divider"></div>
                                            <a class="dropdown-item text-danger re-action" href="#" data-url="{{ route('realestate.contracts.destroy', $contract->id) }}" data-method="DELETE" data-tone="danger" data-confirm="حذف مسودة العقد {{ $contract->code }}؟"><i class="feather icon-trash-2"></i> حذف المسودة</a>
                                        @endpermission
                                    @endif
                                </div>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @include('rentalmanagement::realestate.components.pagination', ['paginator' => $contracts, 'unit' => 'عقداً'])
@else
    @include('rentalmanagement::realestate.components.empty', ['icon' => 'file-text', 'title' => 'لا عقود', 'hint' => 'أنشئ أول عقد أو عدّل معايير البحث'])
@endif
