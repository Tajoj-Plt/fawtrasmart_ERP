@php
    use Modules\RentalManagement\Support\RealEstateVocabulary as V;

    // المبلغ بعملته: `RealEstateMoney` إن وُجد، وإلّا رقمٌ بعملة النظام
    $reMoney = fn ($v, $currency = null) => class_exists(\Modules\RentalManagement\Support\RealEstateMoney::class)
        ? \Modules\RentalManagement\Support\RealEstateMoney::format($v, $currency)
        : number_format((float) $v, 2).' '.(\App\Support\CurrencySettings::code() ?? '');
@endphp
@if ($listings->count() > 0)
    <div class="d-flex justify-content-end p-2 pb-0">@include('stock::components.per-page', ['onchange' => 'loadData(1)'])</div>

    <div class="table-responsive">
        <table class="table table-hover mb-0" id="reportTable">
            <thead>
                <tr><th>العرض / الطلب</th><th>الوحدة / العقار</th><th class="text-center">النوع</th><th class="text-end">السعر المطلوب</th><th>المسوّق</th>
                    <th class="text-center" title="مشاهدات · استفسارات · معاينات">التفاعل</th><th>ينتهي في</th><th class="text-center">الحالة</th><th></th></tr>
            </thead>
            <tbody>
                @foreach ($listings as $listing)
                    @php
                        $cover = $listing->coverUrl();
                        $place = $listing->unit?->building ?? $listing->building;
                        $expired = $listing->expiry_date && $listing->expiry_date->isPast() && $listing->status === 'active';
                    @endphp
                    <tr>
                        <td>
                            <div class="stk-media">
                                @if ($cover)
                                    <span class="stk-media__thumb" style="background:url('{{ $cover }}') center/cover no-repeat"></span>
                                @else
                                    <span class="stk-media__thumb text-primary"><i class="feather icon-tag"></i></span>
                                @endif
                                <div class="stk-media__body">
                                    <a href="{{ hasPermission('realestate.listings.show') ? route('realestate.listings.show', $listing->id) : '#' }}" class="stk-media__title">{{ $listing->title }}</a>
                                    <span class="stk-media__sub stk-num">{{ $listing->code }} <span class="badge badge-light-{{ $listing->kind_meta['tone'] }}">{{ $listing->kind_meta['label'] }}</span></span>
                                </div>
                            </div>
                        </td>
                        <td>
                            @if ($listing->unit)
                                {{ $listing->unit->type_label }} {{ $listing->unit->unit_no }}
                                <small class="text-muted d-block">{{ $place->name ?? '—' }}</small>
                            @elseif ($place)
                                {{ $place->name }}
                                <small class="text-muted d-block">العقار كاملاً</small>
                            @else
                                {{ trim(($listing->property_type ? V::label(V::UNIT_TYPES, $listing->property_type) : '').' '.$listing->city.' '.$listing->district) ?: 'خارجي' }}
                                <small class="text-muted d-block">خارجي{{ $listing->contact_name ? ' — '.$listing->contact_name : '' }}</small>
                            @endif
                        </td>
                        <td class="text-center"><span class="badge badge-light-{{ $listing->type_meta['tone'] }}">{{ $listing->type_meta['label'] }}</span></td>
                        <td class="text-end re-num">
                            @if ($listing->listing_kind === 'request' && ($listing->budget_min || $listing->budget_max))
                                {{ number_format((float) $listing->budget_min) }} – {{ $reMoney($listing->budget_max) }}
                            @else
                            {{ $reMoney($listing->asking_price) }}
                            @endif
                            @if ($listing->listing_type === 'rent' && $listing->rent_period)
                                <small class="text-muted">/ {{ V::label(V::RENT_PERIODS, $listing->rent_period) }}</small>
                            @endif
                        </td>
                        <td>{{ $listing->agent->name ?? '—' }}</td>
                        <td class="text-center stk-num" style="white-space:nowrap">
                            <span title="مشاهدات"><i class="feather icon-eye"></i> {{ (int) $listing->views_count }}</span>
                            <span class="mx-25" title="استفسارات"><i class="feather icon-message-circle"></i> {{ (int) $listing->inquiries_count }}</span>
                            <span title="استفسارات ومعاينات مسجّلة"><i class="feather icon-calendar"></i> {{ (int) $listing->viewings_count }}</span>
                        </td>
                        <td class="stk-num {{ $expired ? 'text-danger' : '' }}">{{ $listing->expiry_date?->format('Y-m-d') ?? '—' }}</td>
                        <td class="text-center"><span class="badge badge-light-{{ $listing->status_meta['tone'] }}">{{ $listing->status_meta['label'] }}</span></td>
                        <td class="text-center">
                            <div class="dropdown">
                                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="dropdown" aria-expanded="false" aria-label="إجراءات {{ $listing->code }}">
                                    <i class="feather icon-more-vertical"></i>
                                </button>
                                <div class="dropdown-menu dropdown-menu-end">
                                    @permission('realestate.listings.show')
                                        <a class="dropdown-item" href="{{ route('realestate.listings.show', $listing->id) }}"><i class="feather icon-eye"></i> عرض</a>
                                    @endpermission
                                    @permission('realestate.listings.edit')
                                        <a class="dropdown-item" href="{{ route('realestate.listings.edit', $listing->id) }}"><i class="feather icon-edit"></i> تعديل</a>
                                    @endpermission
                                    @permission('realestate.listings.publish')
                                        <div class="dropdown-divider"></div>
                                        @foreach ([['active', 'icon-upload-cloud', 'تفعيل', 'info'], ['paused', 'icon-pause-circle', 'إيقاف', 'warning'], ['rented', 'icon-key', 'تم الإيجار', 'warning'], ['purchased', 'icon-shopping-bag', 'تم الشراء', 'warning'], ['closed', 'icon-x-circle', 'إغلاق', 'warning']] as [$st, $ic, $lbl, $tone])
                                            @continue($listing->status === $st)
                                            @continue($st === 'paused' && $listing->status !== 'active')
                                            @continue(! isset($listing->allowedStatuses()[$st]))
                                            <a class="dropdown-item re-action" href="#" data-url="{{ route('realestate.listings.status', $listing->id) }}" data-method="POST"
                                               data-payload="{{ json_encode(['status' => $st]) }}" data-tone="{{ $tone }}" data-confirm="{{ $lbl }}: «{{ $listing->title }}»؟">
                                                <i class="feather {{ $ic }}"></i> {{ $lbl }}
                                            </a>
                                        @endforeach
                                    @endpermission
                                    @permission('realestate.listings.delete')
                                        <div class="dropdown-divider"></div>
                                        <a class="dropdown-item text-danger re-action" href="#" data-url="{{ route('realestate.listings.destroy', $listing->id) }}"
                                           data-method="DELETE" data-tone="danger" data-confirm="حذف {{ $listing->noun }} «{{ $listing->title }}»؟">
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

    @include('rentalmanagement::realestate.components.pagination', ['paginator' => $listings, 'unit' => 'سجلاً'])
@else
    @include('rentalmanagement::realestate.components.empty', ['icon' => 'tag', 'title' => 'لا عروض ولا طلبات', 'hint' => 'أنشئ أول عرض أو طلب، أو عدّل معايير البحث'])
@endif
