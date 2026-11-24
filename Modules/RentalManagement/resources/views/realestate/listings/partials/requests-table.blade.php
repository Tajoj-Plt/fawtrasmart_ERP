{{-- جدول طلبات العملاء (listing_kind = request) — بألفاظ الطلب لا العرض. --}}
@php
    use Modules\RentalManagement\Support\RealEstateVocabulary as V;

    // المبلغ بعملته: `RealEstateMoney` إن وُجد، وإلّا رقمٌ بعملة النظام
    $reMoney = fn ($v, $currency = null) => class_exists(\Modules\RentalManagement\Support\RealEstateMoney::class)
        ? \Modules\RentalManagement\Support\RealEstateMoney::format($v, $currency)
        : number_format((float) $v, 2).' '.(\App\Support\CurrencySettings::code() ?? '');
    $canShow = hasPermission('realestate.listings.show');
@endphp
@if ($requests->count() > 0)
    <div class="d-flex justify-content-end p-2 pb-0">@include('stock::components.per-page', ['onchange' => 'loadData(1)'])</div>

    <div class="table-responsive">
        <table class="table table-hover mb-0" id="reportTable">
            <thead>
                <tr><th>الطلب</th><th>العميل الطالب</th><th>يبحث عن</th><th>المدينة / الحي</th><th class="text-end">الميزانية</th><th>المتابِع</th><th>تاريخ الطلب</th><th class="text-center">الحالة</th><th></th></tr>
            </thead>
            <tbody>
                @foreach ($requests as $item)
                    <tr>
                        <td>
                            <a href="{{ $canShow ? route('realestate.listings.show', $item->id) : '#' }}" class="font-weight-bold stk-num">{{ $item->code }}</a>
                            <small class="text-muted d-block">{{ \Illuminate\Support\Str::limit($item->title, 50) }}</small>
                        </td>
                        <td>
                            {{ $item->requester_name }}
                            <small class="text-muted d-block stk-num" dir="ltr">{{ $item->client->mobile ?? ($item->contact_phone ?: '') }}</small>
                            @unless ($item->client_id) <span class="badge badge-light-secondary">زائر غير مسجَّل</span> @endunless
                        </td>
                        <td>
                            {{ $item->property_type ? V::label(V::UNIT_TYPES, $item->property_type) : 'أيّ عقار' }}
                            <span class="badge badge-light-{{ $item->listing_type === 'sale' ? 'primary' : 'info' }}">{{ $item->listing_type === 'sale' ? 'شراء' : 'إيجار' }}</span>
                            @if ($item->bedrooms) <small class="text-muted d-block">{{ $item->bedrooms }} غرف فأكثر</small> @endif
                        </td>
                        <td>{{ collect([$item->city, $item->district])->filter()->implode('، ') ?: '—' }}</td>
                        <td class="text-end re-num">
                            @if ((float) $item->budget_min > 0 && (float) $item->budget_max > 0)
                                {{ number_format((float) $item->budget_min) }} – {{ $reMoney($item->budget_max) }}
                            @elseif ((float) $item->budget_max > 0)
                                حتى {{ $reMoney($item->budget_max) }}
                            @elseif ((float) $item->budget_min > 0)
                                من {{ $reMoney($item->budget_min) }}
                            @else
                                —
                            @endif
                        </td>
                        <td>{{ $item->agent->name ?? '—' }}</td>
                        <td class="stk-num">{{ $item->created_at?->format('Y-m-d') }}</td>
                        <td class="text-center"><span class="badge badge-light-{{ $item->status_meta['tone'] }}">{{ $item->status_meta['label'] }}</span></td>
                        <td class="text-center">
                            <div class="dropdown">
                                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="dropdown" aria-expanded="false" aria-label="إجراءات الطلب {{ $item->code }}">
                                    <i class="feather icon-more-vertical"></i>
                                </button>
                                <div class="dropdown-menu dropdown-menu-end">
                                    @if ($canShow)
                                        <a class="dropdown-item" href="{{ route('realestate.listings.show', $item->id) }}"><i class="feather icon-eye"></i> عرض الطلب والعروض المطابقة</a>
                                    @endif
                                    @permission('realestate.listings.edit')
                                        <a class="dropdown-item" href="{{ route('realestate.listings.edit', $item->id) }}"><i class="feather icon-edit"></i> تعديل</a>
                                    @endpermission
                                    @permission('realestate.listings.publish')
                                        <div class="dropdown-divider"></div>
                                        @foreach ([['active', 'icon-play-circle', 'نشط', 'info'], ['paused', 'icon-phone-call', 'قيد المتابعة', 'warning'], ['fulfilled', 'icon-check-circle', 'تمت تلبيته', 'info'], ['closed', 'icon-x-circle', 'إغلاق الطلب', 'warning']] as [$st, $ic, $lbl, $tone])
                                            @continue($item->status === $st || ($st === 'fulfilled' && $item->status === $item->fulfilledStatus()))
                                            <a class="dropdown-item re-action" href="#" data-url="{{ route('realestate.listings.status', $item->id) }}" data-method="POST"
                                               data-payload="{{ json_encode(['status' => $st]) }}" data-tone="{{ $tone }}" data-confirm="{{ $lbl }}: الطلب {{ $item->code }}؟">
                                                <i class="feather {{ $ic }}"></i> {{ $lbl }}
                                            </a>
                                        @endforeach
                                    @endpermission
                                    @permission('realestate.listings.delete')
                                        <div class="dropdown-divider"></div>
                                        <a class="dropdown-item text-danger re-action" href="#" data-url="{{ route('realestate.listings.destroy', $item->id) }}"
                                           data-method="DELETE" data-tone="danger" data-confirm="حذف الطلب {{ $item->code }}؟">
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

    @include('rentalmanagement::realestate.components.pagination', ['paginator' => $requests, 'unit' => 'طلب'])
@else
    @include('rentalmanagement::realestate.components.empty', ['icon' => 'search', 'title' => 'لا طلبات', 'hint' => 'سجّل طلب عميلٍ يبحث عن عقار، أو عدّل معايير البحث'])
@endif
