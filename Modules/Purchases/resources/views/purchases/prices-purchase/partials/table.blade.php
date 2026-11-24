{{--
    جدول «عروض أسعار المشتريات» (الشاشة القديمة).

    التصميم منقول عن جدول شاشة «سجلّ النشاطات» (`sitting::ActivityLogs.index`)
    بنيةً وأصنافاً كما في بقيّة جداول القسم: `table modern-table`، عمود تحديد
    في الأول، عمود `#`، عمود رئيسي بسطرين (نصّ عريض فوق نصّ رمادي صغير)،
    عمود تاريخ فوق تاريخ استحقاق، ثم زرّ `bg-gradient-info` بثلاث نقاط يفتح
    قائمة عرض/تعديل/حذف — ونفس الحالة الفارغة بأيقونة كبيرة باهتة.

    شارة الحالة من `purchases::partials.status` المشتركة، فألوانها تتبع
    «مظهر النظام» وتسمياتها تتبع لغة الواجهة.

    الأصناف والمعرّفات التي يعتمدها JavaScript تبقى كما هي:
    `#selectAll` و`.order-checkbox` و`.delete-order[data-order-id][data-order-code]`.

    @param \Illuminate\Contracts\Pagination\LengthAwarePaginator $purchaseOrders
--}}
@php
    use App\Support\LanguageSettings;
@endphp

<div class="table-responsive">
    <table class="table modern-table po-list-table mb-0">
        <thead>
            <tr>
                <th class="text-center" style="width: 40px;">
                    <input type="checkbox" id="selectAll" class="po-check"
                        aria-label="{{ __('purchase_orders.bulk.select_all') }}">
                </th>
                <th class="text-center" style="width: 60px;">#</th>
                <th>{{ __('purchase_cycle.fields.code') }}</th>
                <th>{{ __('purchase_cycle.fields.title') }}</th>
                <th>{{ __('purchase_cycle.fields.status') }}</th>
                <th class="text-center">{{ __('purchase_cycle.fields.datetime') }}</th>
                <th class="text-center" style="width: 80px;">{{ __('purchase_cycle.actions.options') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($purchaseOrders as $order)
                <tr class="order-row" data-order-id="{{ $order->id }}">
                    <td class="text-center">
                        <input type="checkbox" class="po-check order-checkbox" name="ids[]"
                            value="{{ $order->id }}" aria-label="{{ $order->code }}">
                    </td>

                    <td class="text-center">{{ $order->id }}</td>

                    <td>
                        {{-- الرقمُ رابطٌ إلى الاستعراض — نفسُ وجهة أيقونة العين --}}
                        <a href="{{ route('ViewPurchasePrice.show', $order->id) }}">
                            <strong>{{ $order->code }}</strong>
                        </a>
                        <br><small class="text-muted">
                            {{ $order->creator->name ?? __('purchase_cycle.empty.unknown') }}
                        </small>
                    </td>

                    <td>{{ $order->title ?: __('purchase_cycle.documents.purchase_quotation') }}</td>

                    <td>
                        @include('purchases::partials.status', ['status' => $order->status])
                    </td>

                    <td class="text-center num">
                        <div>{{ LanguageSettings::formatDate($order->order_date) ?: '—' }}</div>
                        <small class="text-muted">
                            @if ($order->due_date)
                                {{ __('purchase_cycle.fields.due_date') }}:
                                {{ LanguageSettings::formatDate($order->due_date) }}
                            @endif
                        </small>
                    </td>

                    <td class="text-center">
                        <div class="btn-group">
                            <div class="dropdown">
                                <button class="btn bg-gradient-info fa fa-ellipsis-v mr-1 mb-1 btn-sm" type="button"
                                    id="dropdownMenuButton{{ $order->id }}" data-bs-toggle="dropdown"
                                    aria-expanded="false" aria-label="{{ __('purchase_cycle.actions.options') }}"></button>
                                <div class="dropdown-menu dropdown-menu-end"
                                    aria-labelledby="dropdownMenuButton{{ $order->id }}">
                                    <a class="dropdown-item" href="{{ route('ViewPurchasePrice.show', $order->id) }}">
                                        <i class="fas fa-eye me-2 text-primary"></i>{{ __('purchase_cycle.actions.show') }}
                                    </a>
                                    <a class="dropdown-item" href="{{ route('pricesPurchase.edit', $order->id) }}">
                                        <i class="fas fa-edit me-2 text-success"></i>{{ __('purchase_cycle.actions.edit') }}
                                    </a>
                                    <div class="dropdown-divider"></div>
                                    <a class="dropdown-item text-danger delete-order" href="#"
                                        data-order-id="{{ $order->id }}" data-order-code="{{ $order->code }}">
                                        <i class="fas fa-trash me-2"></i>{{ __('purchase_cycle.actions.delete') }}
                                    </a>
                                </div>
                            </div>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="text-center py-4">
                        <i class="fas fa-tags" style="font-size: 48px; color: #ccc;"></i>
                        <p class="mt-2 text-muted">
                            {{ request()->hasAny(['code', 'status', 'employee_id']) ? __('purchase_cycle.empty.filtered') : __('purchase_cycle.empty.title') }}
                        </p>
                        <a href="{{ route('pricesPurchase.create') }}" class="btn btn-primary btn-sm">
                            <i class="fas fa-plus me-1"></i>{{ __('purchase_cycle.actions.create') }}
                        </a>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
