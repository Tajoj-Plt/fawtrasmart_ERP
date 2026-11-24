{{--
    جدول الأذون المخزنية — يُستبدل وحده عند البحث والتنقّل بـAjax.

    كانت نافذة «الرفض» تُطبع داخل كل صفّ، ومُنتقي عدد السجلّات مكتوباً بيده،
    والحالة سلسلة `elseif` تعيد الشارة خمس مرّات. وزر «طباعة» كان `href=""`.

    ملاحظة على البيانات: الحالة المحفوظة تأتي بصيغتين لكل معنى
    (`approved`/`apporod` و`rejected`/`rejec`) — أخطاء إملائية قديمة في
    القاعدة، تُقرأ هنا بخريطة واحدة بدل تكرار الشرط.
--}}

@php
    $states = [
        'pending' => ['قيد الانتظار', 'warning'],
        'approved' => ['موافق عليه', 'success'],
        'apporod' => ['موافق عليه', 'success'],
        'rejected' => ['مرفوض', 'danger'],
        'rejec' => ['مرفوض', 'danger'],
    ];

    // نوع الإذن: [الرمز، اللون]
    $kinds = [
        1 => ['plus', 'success'],
        2 => ['minus', 'danger'],
    ];
@endphp

@if ($wareHousePermits->count() > 0)
    <div class="d-flex justify-content-end p-2 pb-0">
        @include('stock::components.per-page', ['onchange' => 'loadData(1)'])
    </div>

    <div class="table-responsive">
        <table class="table table-hover mb-0" id="reportTable">
            <thead>
                <tr>
                    <th style="width:20%">رقم الإذن</th>
                    <th style="width:18%">التاريخ</th>
                    <th style="width:16%">مصدر الإذن</th>
                    <th>المستودع</th>
                    <th class="text-center" style="width:15%">الحالة</th>
                    <th class="text-center" style="width:9%">إجراءات</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($wareHousePermits as $item)
                    @php
                        [$stateLabel, $stateTone] = $states[$item->status] ?? [$item->status, 'secondary'];
                        [$kindIcon, $kindTone] = $kinds[$item->permission_type] ?? ['repeat', 'warning'];

                        $isPending = $item->status === 'pending';
                        $isApproved = in_array($item->status, ['approved', 'apporod'], true);
                        $isRejected = in_array($item->status, ['rejected', 'rejec'], true);

                        $warehouse = match (true) {
                            (bool) ($item->fromStoreHouse && $item->toStoreHouse)
                                => $item->fromStoreHouse->name.' ← '.$item->toStoreHouse->name,
                            (bool) $item->fromStoreHouse => $item->fromStoreHouse->name,
                            (bool) $item->toStoreHouse => $item->toStoreHouse->name,
                            (bool) $item->storeHouse => $item->storeHouse->name,
                            default => 'غير محدد',
                        };
                    @endphp

                    <tr>
                        <td>
                            <div class="stk-media">
                                <span class="stk-media__thumb text-{{ $kindTone }}">
                                    <i class="feather icon-{{ $kindIcon }}"></i>
                                </span>
                                <div class="stk-media__body">
                                    <a href="{{ route('store_permits_management.show', $item->id) }}" class="stk-media__title">
                                        {{ $item->number }}
                                    </a>
                                    <span class="stk-media__sub stk-num">#{{ $item->id }}</span>
                                </div>
                            </div>
                        </td>

                        <td>
                            <span class="stk-num d-block">
                                {{ \Illuminate\Support\Carbon::parse($item->permission_date)->format('Y-m-d H:i') }}
                            </span>
                            <span class="stk-media__sub">
                                <i class="feather icon-user"></i> {{ $item->user->name ?? '—' }}
                            </span>
                        </td>

                        <td>
                            <span class="badge badge-light-primary">
                                {{ \App\Constants\permissions\PermissionSources::getName($item->permission_source_id ?? 0) }}
                            </span>
                        </td>

                        <td><span class="stk-media__sub">{{ $warehouse }}</span></td>

                        <td class="text-center">
                            <span class="badge badge-light-{{ $stateTone }}">{{ $stateLabel }}</span>
                            <span class="stk-media__sub d-block">{{ $item->user->branch->name ?? 'بلا فرع' }}</span>
                        </td>

                        <td class="text-center">
                            <div class="dropdown">
                                <button type="button" class="btn btn-sm btn-outline-secondary"
                                        data-bs-toggle="dropdown"
                                        aria-expanded="false" aria-label="خيارات الإذن {{ $item->number }}">
                                    <i class="feather icon-more-vertical"></i>
                                </button>
                                <div class="dropdown-menu dropdown-menu-end">
                                    @permission('stock.permits.show')
                                    <a class="dropdown-item" href="{{ route('store_permits_management.show', $item->id) }}">
                                        <i class="feather icon-eye"></i> عرض
                                    </a>
                                    @endpermission

                                    @if ($isPending)
                                        @permission('stock.permits.edit')
                                        <a class="dropdown-item" href="{{ route('store_permits_management.edit', $item->id) }}">
                                            <i class="feather icon-edit-2"></i> تعديل
                                        </a>
                                        @endpermission
                                    @endif

                                    @if ($isPending || $isRejected)
                                        @permission('stock.permits.approve')
                                        <div class="dropdown-divider"></div>
                                        <a class="dropdown-item text-success approve-btn" href="#"
                                           data-id="{{ $item->id }}" data-number="{{ $item->number }}">
                                            <i class="feather icon-check"></i> موافقة
                                        </a>
                                        @endpermission
                                    @endif

                                    @if ($isPending)
                                        @permission('stock.permits.reject')
                                        <a class="dropdown-item text-warning reject-btn" href="#"
                                           data-id="{{ $item->id }}" data-number="{{ $item->number }}">
                                            <i class="feather icon-x"></i> رفض
                                        </a>
                                        @endpermission
                                    @endif

                                    @if ($isApproved)
                                        @permission('stock.permits.cancel_approval')
                                        <div class="dropdown-divider"></div>
                                        <a class="dropdown-item text-warning cancel-approval-btn" href="#"
                                           data-id="{{ $item->id }}" data-number="{{ $item->number }}">
                                            <i class="feather icon-rotate-ccw"></i> إلغاء الموافقة
                                        </a>
                                        @endpermission
                                    @endif

                                    @permission('stock.permits.delete')
                                    <div class="dropdown-divider"></div>
                                    <a class="dropdown-item text-danger delete-btn" href="#"
                                       data-id="{{ $item->id }}" data-number="{{ $item->number }}">
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

    @include('stock::store_permits_management.partials.pagination', [
        'wareHousePermits' => $wareHousePermits,
    ])
@else
    <div class="stk-empty">
        <div class="stk-empty__icon"><i class="feather icon-file-text"></i></div>
        <h5 class="stk-empty__title">لا أذون مخزنية</h5>
        <p class="stk-empty__hint">لا شيء يطابق معايير البحث الحالية</p>
    </div>
@endif
