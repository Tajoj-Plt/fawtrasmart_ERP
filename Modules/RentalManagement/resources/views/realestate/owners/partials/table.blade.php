@if ($owners->count() > 0)
    <div class="d-flex justify-content-end p-2 pb-0">@include('stock::components.per-page', ['onchange' => 'loadData(1)'])</div>

    <div class="table-responsive">
        <table class="table table-hover mb-0" id="reportTable">
            <thead>
                <tr><th>المالك</th><th>الجوال</th><th>اتفاقية الإدارة</th><th>أساس العمولة</th><th class="text-center">العقارات</th>@if ($canSeeMoney)<th class="text-end">الرصيد المستحق له</th>@endif<th class="text-center">الحالة</th><th></th></tr>
            </thead>
            <tbody>
                @foreach ($owners as $owner)
                    @php $balance = (float) ($balances[$owner->id] ?? 0); @endphp
                    <tr>
                        <td>
                            <div class="stk-media">
                                <span class="stk-media__thumb text-primary"><i class="feather icon-user"></i></span>
                                <div class="stk-media__body">
                                    @if (hasPermission('realestate.owners.show'))
                                        <a href="{{ route('realestate.owners.show', $owner->id) }}" class="stk-media__title">{{ $owner->name }}</a>
                                    @else
                                        <span class="stk-media__title">{{ $owner->name }}</span>
                                    @endif
                                    <span class="stk-media__sub stk-num">{{ $owner->code }}@if ($owner->is_vat_registered) · <span class="text-info">مسجَّل ضريبياً</span>@endif</span>
                                </div>
                            </div>
                        </td>
                        <td class="stk-num" dir="ltr">{{ $owner->mobile ?: ($owner->phone ?: '—') }}</td>
                        <td>{{ $owner->agreement_label }}</td>
                        <td>{{ \Modules\RentalManagement\Support\RealEstateVocabulary::label(\Modules\RentalManagement\Support\RealEstateVocabulary::COMMISSION_BASES, $owner->commission_basis) }}</td>
                        <td class="text-center"><span class="stk-count">{{ $owner->buildings_count }}</span></td>
                        @if ($canSeeMoney)
                            <td class="text-end stk-num {{ $balance > 0 ? 'text-success' : ($balance < 0 ? 'text-danger' : '') }}">{{ \Modules\RentalManagement\Support\RealEstateMoney::format($balance) }}</td>
                        @endif
                        <td class="text-center"><span class="badge badge-light-{{ $owner->status_meta['tone'] }}">{{ $owner->status_meta['label'] }}</span></td>
                        <td class="text-center">
                            <div class="dropdown">
                                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="dropdown" aria-expanded="false" aria-label="إجراءات {{ $owner->name }}">
                                    <i class="feather icon-more-vertical"></i>
                                </button>
                                <div class="dropdown-menu dropdown-menu-end">
                                    @permission('realestate.owners.show')
                                        <a class="dropdown-item" href="{{ route('realestate.owners.show', $owner->id) }}"><i class="feather icon-file-text"></i> الملفّ وكشف الحساب</a>
                                    @endpermission
                                    @permission('realestate.owners.edit')
                                        <a class="dropdown-item" href="{{ route('realestate.owners.edit', $owner->id) }}"><i class="feather icon-edit-2"></i> تعديل</a>
                                    @endpermission
                                    @permission('realestate.buildings.create')
                                        <a class="dropdown-item" href="{{ route('realestate.buildings.create', ['owner_id' => $owner->id]) }}"><i class="feather icon-home"></i> مبنى جديد لهذا المالك</a>
                                    @endpermission
                                </div>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @include('rentalmanagement::realestate.components.pagination', ['paginator' => $owners, 'unit' => 'مالك'])
@else
    @include('rentalmanagement::realestate.components.empty', ['icon' => 'user', 'title' => 'لا ملّاك', 'hint' => 'أضف مالكاً لتربطه بمبانيه وعقوده، أو عدّل معايير البحث'])
@endif
