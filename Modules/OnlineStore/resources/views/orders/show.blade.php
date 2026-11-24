@extends('onlinestore::master')

@section('title', 'طلب #' . $order->order_number)

@section('content')
    <div class="content-body">

        @php
            // ألوان bootstrap للحالة تُطابَق مع نغمات stk-state المتاحة فعلياً: on/off/neutral/warn.
            $stkTone = ['success' => 'on', 'danger' => 'off', 'secondary' => 'off', 'dark' => 'off', 'warning' => 'warn', 'info' => 'neutral', 'primary' => 'neutral'];
            $currentStatus = $order->status;
        @endphp

        @section('page-actions')
            @if (hasPermission('online_store.orders.print'))
                <a href="{{ route('online_orders.print', $order) }}" class="btn btn-outline-secondary" target="_blank">
                    <i class="feather icon-printer"></i> طباعة
                </a>
                @if ($order->invoice)
                    <a href="{{ route('online_orders.print-invoice', $order) }}" class="btn btn-outline-secondary" target="_blank">
                        <i class="feather icon-file-text"></i> الفاتورة
                    </a>
                @endif
            @endif

            @if (hasPermission('online_store.orders.update_status'))
                @if ($currentStatus === \App\Enums\OrderStatus::PENDING)
                    <form action="{{ route('online_orders.update-status', $order) }}" method="POST" class="d-inline">
                        @csrf
                        <input type="hidden" name="status" value="confirmed">
                        <button type="submit" class="btn btn-info">
                            <i class="feather icon-check"></i> تأكيد
                        </button>
                    </form>
                @endif

                @if ($currentStatus === \App\Enums\OrderStatus::CONFIRMED)
                    <form action="{{ route('online_orders.update-status', $order) }}" method="POST" class="d-inline">
                        @csrf
                        <input type="hidden" name="status" value="processing">
                        <button type="submit" class="btn btn-primary">
                            <i class="feather icon-settings"></i> تجهيز
                        </button>
                    </form>
                @endif

                @if ($currentStatus === \App\Enums\OrderStatus::PROCESSING)
                    <form action="{{ route('online_orders.update-status', $order) }}" method="POST" class="d-inline">
                        @csrf
                        <input type="hidden" name="status" value="ready_for_shipping">
                        <button type="submit" class="btn btn-info">
                            <i class="feather icon-box"></i> جاهز
                        </button>
                    </form>
                @endif
            @endif

            @if ($currentStatus === \App\Enums\OrderStatus::READY_FOR_SHIPPING && hasPermission('online_store.orders.update_shipping'))
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-toggle="modal" data-bs-target="#shippingModal" data-target="#shippingModal">
                    <i class="feather icon-truck"></i> شحن
                </button>
            @endif

            @if (hasPermission('online_store.orders.update_status'))
                @if ($currentStatus === \App\Enums\OrderStatus::SHIPPED)
                    <form action="{{ route('online_orders.update-status', $order) }}" method="POST" class="d-inline">
                        @csrf
                        <input type="hidden" name="status" value="out_for_delivery">
                        <button type="submit" class="btn btn-info">
                            <i class="feather icon-navigation"></i> توصيل
                        </button>
                    </form>
                @endif

                @if (in_array($currentStatus, [\App\Enums\OrderStatus::SHIPPED, \App\Enums\OrderStatus::OUT_FOR_DELIVERY]))
                    <form action="{{ route('online_orders.update-status', $order) }}" method="POST" class="d-inline">
                        @csrf
                        <input type="hidden" name="status" value="delivered">
                        <button type="submit" class="btn btn-success">
                            <i class="feather icon-check-circle"></i> تسليم
                        </button>
                    </form>
                @endif

                @if (in_array($currentStatus, [\App\Enums\OrderStatus::SHIPPED, \App\Enums\OrderStatus::OUT_FOR_DELIVERY, \App\Enums\OrderStatus::DELIVERED]))
                    <button type="button" class="btn btn-outline-warning" data-bs-toggle="modal" data-toggle="modal" data-bs-target="#returnModal" data-target="#returnModal">
                        <i class="feather icon-rotate-ccw"></i> إرجاع
                    </button>
                @endif
            @endif

            {{-- التحصيل: طلبٌ لم يُدفع (الدفع عند الاستلام/التحويل) ولم يُلغَ --}}
            @if ($order->collectibleAmount() > 0 && hasPermission('online_store.orders.update_status'))
                <button type="button" class="btn btn-success" data-bs-toggle="modal" data-toggle="modal" data-bs-target="#collectModal" data-target="#collectModal">
                    <i class="feather icon-check-circle"></i> تسجيل التحصيل
                </button>
            @endif

            {{-- الاسترداد بعد الشحن (قبله: الإلغاء) — ما دام في الطلب ما يُردّ --}}
            @if (in_array($currentStatus, [\App\Enums\OrderStatus::SHIPPED, \App\Enums\OrderStatus::OUT_FOR_DELIVERY, \App\Enums\OrderStatus::DELIVERED, \App\Enums\OrderStatus::RETURNED]) && $order->refundableAmount() > 0 && hasPermission('online_store.orders.refund'))
                <button type="button" class="btn btn-outline-dark" data-bs-toggle="modal" data-toggle="modal" data-bs-target="#refundModal" data-target="#refundModal">
                    <i class="feather icon-dollar-sign"></i> استرداد
                </button>
            @endif

            @if (in_array(\App\Enums\OrderStatus::CANCELLED, $currentStatus->allowedNextStatuses(), true) && hasPermission('online_store.orders.cancel'))
                <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-toggle="modal" data-bs-target="#cancelModal" data-target="#cancelModal">
                    <i class="feather icon-x"></i> إلغاء
                </button>
            @endif
        @endsection

        @include('onlinestore::components.page-head', [
            'title' => 'طلب #' . $order->order_number,
            'icon' => 'shopping-bag',
            'sub' => null,
            'crumbs' => [
                'الرئيسية' => url('/'),
                'المتجر الإلكتروني' => route('onlinestore.department.dashboard'),
                'الطلبات' => route('online_orders.index'),
                '#'.$order->order_number => null,
            ],
        ])

        @include('layouts.alerts.success')
        @include('layouts.alerts.error')

        <div class="stk-metrics">
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-info"></i></span>
                <span class="stk-metric__label">حالة الطلب</span>
                <span class="stk-metric__value">
                    <span class="stk-state stk-state--{{ $stkTone[$order->status->color()] ?? 'neutral' }}">{{ $order->status->label() }}</span>
                </span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-calendar"></i></span>
                <span class="stk-metric__label">تاريخ الطلب</span>
                <span class="stk-metric__value stk-num">{{ $order->created_at->format('d/m/Y') }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-package"></i></span>
                <span class="stk-metric__label">عدد المنتجات</span>
                <span class="stk-metric__value">{{ $order->items->count() }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-dollar-sign"></i></span>
                <span class="stk-metric__label">الإجمالي</span>
                <span class="stk-metric__value">{{ $order->formatMoney($order->total_amount) }}</span>
            </div>
        </div>

        <div class="row">
            <!-- المحتوى الرئيسي -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="stk-table-head">
                        <h6 class="stk-table-head__title"><i class="feather icon-shopping-bag"></i> المنتجات</h6>
                        <span class="badge badge-light-primary">{{ $order->items->count() }}</span>
                    </div>

                    <ul class="nav nav-tabs stk-tabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="products-tab" data-bs-toggle="tab" data-toggle="tab" data-bs-target="#products" href="#products" type="button" role="tab" aria-controls="products" aria-selected="true">
                                <i class="feather icon-list"></i> المنتجات والملخص
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="timeline-tab" data-bs-toggle="tab" data-toggle="tab" data-bs-target="#timeline" href="#timeline" type="button" role="tab" aria-controls="timeline" aria-selected="false">
                                <i class="feather icon-clock"></i> سجل الطلب
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content">
                        <div class="tab-pane active" id="products" role="tabpanel" aria-labelledby="products-tab">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>المنتج</th>
                                            <th class="text-center">الكمية</th>
                                            <th class="text-center">سعر الوحدة</th>
                                            <th class="text-center">الإجمالي</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($order->items as $item)
                                            @php
                                                $productImage = null;
                                                if ($item->product && $item->product->images) {
                                                    $images = json_decode($item->product->images, true);
                                                    $productImage = is_array($images) && count($images) > 0 ? $images[0] : null;
                                                }
                                            @endphp
                                            <tr>
                                                <td>
                                                    <div class="stk-media">
                                                        @if ($productImage)
                                                            <span class="stk-media__thumb"><img src="{{ asset($productImage) }}" alt="{{ $item->product_name }}" style="width:100%;height:100%;object-fit:cover;border-radius:inherit;"></span>
                                                        @else
                                                            <span class="stk-media__thumb"><i class="feather icon-package"></i></span>
                                                        @endif
                                                        <span class="stk-media__body">
                                                            <span class="stk-media__title">{{ $item->product_name }}</span>
                                                            @if ($item->product_sku)
                                                                <span class="stk-media__sub stk-num"><i class="feather icon-hash"></i> {{ $item->product_sku }}</span>
                                                            @endif
                                                            @if (!empty($item->options['variant_label']))
                                                                <span class="stk-media__sub"><i class="feather icon-tag"></i> {{ $item->options['variant_label'] }}</span>
                                                            @endif
                                                        </span>
                                                    </div>
                                                </td>
                                                <td class="text-center stk-num">{{ $item->quantity }}</td>
                                                <td class="text-center stk-num">{{ $order->formatMoney($item->unit_price) }}</td>
                                                <td class="text-center stk-num"><strong>{{ $order->formatMoney($item->total) }}</strong></td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <td colspan="3">المجموع الفرعي</td>
                                            <td class="text-center stk-num">{{ $order->formatMoney($order->subtotal) }}</td>
                                        </tr>
                                        @if ($order->discount_amount > 0)
                                            <tr class="text-success">
                                                <td colspan="3">الخصم @if ($order->coupon)({{ $order->coupon->code }})@endif</td>
                                                <td class="text-center stk-num">- {{ $order->formatMoney($order->discount_amount) }}</td>
                                            </tr>
                                        @endif
                                        @if ($order->tax_amount > 0)
                                            <tr>
                                                <td colspan="3">الضريبة (15%)</td>
                                                <td class="text-center stk-num">{{ $order->formatMoney($order->tax_amount) }}</td>
                                            </tr>
                                        @endif
                                        <tr>
                                            <td colspan="3">الشحن
                                                @if ($order->shipping_cost <= 0 && $order->shipping_company_cost > 0)
                                                    <small class="d-block text-muted">مجاني للعميل — تتحمّل المنشأة {{ $order->formatMoney($order->shipping_company_cost) }} لشركة الشحن (تُقيَّد مصروفاً وتُسدَّد عند «تم الشحن»)</small>
                                                @endif
                                            </td>
                                            <td class="text-center stk-num">{{ $order->shipping_cost > 0 ? $order->formatMoney($order->shipping_cost) : 'مجاني' }}</td>
                                        </tr>
                                        @if ($order->shipping_tax_amount > 0)
                                            <tr>
                                                <td colspan="3">ضريبة الشحن</td>
                                                <td class="text-center stk-num">{{ $order->formatMoney($order->shipping_tax_amount) }}</td>
                                            </tr>
                                        @endif
                                        <tr class="stk-total-row">
                                            <th colspan="3">الإجمالي</th>
                                            <th class="text-center stk-num">{{ $order->formatMoney($order->total_amount) }}</th>
                                        </tr>
                                        @if ($order->prices_include_tax && $order->tax_amount > 0)
                                            <tr>
                                                <td colspan="4" class="text-muted">الأسعار شاملة ضريبة القيمة المضافة — منها ضريبة {{ $order->formatMoney($order->tax_amount) }}</td>
                                            </tr>
                                        @endif
                                        @if ($order->refundedSoFar() > 0)
                                            <tr>
                                                <td colspan="3" class="text-danger">{{ $order->status === \App\Enums\OrderStatus::CANCELLED ? 'ملغي — عُكس' : 'مردود / مخصوم' }}</td>
                                                <td class="text-center stk-num text-danger">- {{ $order->formatMoney($order->refundedSoFar()) }}</td>
                                            </tr>
                                        @endif
                                        <tr>
                                            <td colspan="3">حالة الدفع</td>
                                            <td class="text-center">
                                                {{ $order->payment_status?->label() }}
                                                @if ($order->collectibleAmount() > 0) — مستحق {{ $order->formatMoney($order->collectibleAmount()) }} @endif
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>

                        <div class="tab-pane" id="timeline" role="tabpanel" aria-labelledby="timeline-tab">
                            <div class="card-body">
                                @forelse ($order->statusHistory->sortByDesc('created_at') as $history)
                                    <div class="stk-timeline">
                                        <div class="stk-timeline__item">
                                            <span class="stk-timeline__dot"><i class="feather icon-check"></i></span>
                                            <div class="stk-timeline__body">
                                                <div class="stk-timeline__text"><strong>{{ $history->status_name }}</strong></div>
                                                @if ($history->notes)
                                                    <div class="stk-timeline__text">{{ $history->notes }}</div>
                                                @endif
                                                <div class="stk-timeline__meta">
                                                    <span class="stk-timeline__time">{{ $history->formatted_time }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @empty
                                    <div class="stk-empty">
                                        <span class="stk-empty__icon"><i class="feather icon-clock"></i></span>
                                        <h6 class="stk-empty__title">لا يوجد سجلّ</h6>
                                        <p class="stk-empty__hint">لم تُسجَّل أي حالة لهذا الطلب بعد.</p>
                                    </div>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- الشريط الجانبي -->
            <div class="col-lg-4">
                <div class="card">
                    <div class="stk-table-head">
                        <h6 class="stk-table-head__title"><i class="feather icon-user"></i> العميل</h6>
                    </div>
                    <div class="card-body">
                        <dl class="stk-def">
                            <div class="stk-def__row">
                                <dt>الاسم</dt>
                                <dd>{{ $order->shipping_name }}</dd>
                            </div>
                            <div class="stk-def__row">
                                <dt>الهاتف</dt>
                                <dd class="stk-num">{{ $order->shipping_phone }}</dd>
                            </div>
                            <div class="stk-def__row">
                                <dt>العنوان</dt>
                                <dd>
                                    {{ $order->shipping_city }} - {{ $order->shipping_region }}<br>
                                    {{ $order->shipping_address }}
                                </dd>
                            </div>
                            @if ($order->shipping_latitude && $order->shipping_longitude)
                                <div class="stk-def__row">
                                    <dt>الموقع</dt>
                                    <dd>
                                        <a href="https://www.google.com/maps?q={{ $order->shipping_latitude }},{{ $order->shipping_longitude }}" target="_blank">
                                            <i class="feather icon-map"></i> عرض على الخريطة
                                        </a>
                                    </dd>
                                </div>
                            @endif
                        </dl>
                    </div>
                </div>

                @if ($order->invoice)
                    <div class="card">
                        <div class="stk-table-head">
                            <h6 class="stk-table-head__title"><i class="feather icon-file-text"></i> الفاتورة</h6>
                        </div>
                        <div class="card-body">
                            <dl class="stk-def stk-def--inline mb-2">
                                <div class="stk-def__row">
                                    <dt>رقم الفاتورة</dt>
                                    <dd class="stk-num">#{{ $order->invoice->code ?? $order->invoice->id }}</dd>
                                </div>
                                <div class="stk-def__row">
                                    <dt>التاريخ</dt>
                                    <dd class="stk-num">{{ $order->invoice->created_at->format('Y-m-d') }}</dd>
                                </div>
                            </dl>
                            <div class="d-flex gap-2">
                                <a href="{{ route('invoices.show', $order->invoice) }}" class="btn btn-sm btn-outline-secondary">
                                    <i class="feather icon-eye"></i> عرض
                                </a>
                                @if (hasPermission('online_store.orders.print'))
                                    <a href="{{ route('online_orders.print-invoice', $order) }}" class="btn btn-sm btn-outline-secondary" target="_blank">
                                        <i class="feather icon-printer"></i> طباعة
                                    </a>
                                @endif
                            </div>
                        </div>
                    </div>
                @endif

                @if (isset($journalEntries) && $journalEntries->isNotEmpty())
                    <div class="card">
                        <div class="card-header"><h4 class="card-title"><i class="feather icon-book"></i> القيود المحاسبية</h4></div>
                        <div class="card-body p-0">
                            <table class="table table-sm mb-0">
                                <tbody>
                                    @foreach ($journalEntries as $entry)
                                        <tr class="table-light">
                                            <td colspan="3"><strong>{{ $entry->reference_number }}</strong> <small class="text-muted">{{ optional($entry->date)->format('Y-m-d') }}</small><br><small>{{ $entry->description }}</small></td>
                                        </tr>
                                        @foreach ($entry->details as $line)
                                            <tr>
                                                <td><small>{{ $line->account?->name }}</small></td>
                                                <td class="text-center stk-num"><small>{{ $line->debit > 0 ? \App\Support\CurrencySettings::number($line->debit) : '' }}</small></td>
                                                <td class="text-center stk-num"><small>{{ $line->credit > 0 ? \App\Support\CurrencySettings::number($line->credit) : '' }}</small></td>
                                            </tr>
                                        @endforeach
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif

                <div class="card">
                    <div class="stk-table-head">
                        <h6 class="stk-table-head__title"><i class="feather icon-truck"></i> الشحن</h6>
                        @if (hasPermission('online_store.orders.update_shipping'))
                            <button type="button" class="btn btn-sm btn-icon" data-bs-toggle="modal" data-toggle="modal" data-bs-target="#shippingModal" data-target="#shippingModal">
                                <i class="feather icon-edit-2"></i>
                            </button>
                        @endif
                    </div>
                    <div class="card-body">
                        <dl class="stk-def">
                            @if ($order->shippingMethod)
                                <div class="stk-def__row">
                                    <dt>الطريقة</dt>
                                    <dd>{{ $order->shippingMethod->name }}</dd>
                                </div>
                            @endif
                            @if ($order->tracking_number)
                                <div class="stk-def__row">
                                    <dt>رقم التتبع</dt>
                                    <dd class="stk-num">{{ $order->tracking_number }}</dd>
                                </div>
                            @endif
                            @if ($order->shipping_company)
                                <div class="stk-def__row">
                                    <dt>شركة الشحن</dt>
                                    <dd>{{ $order->shipping_company }}</dd>
                                </div>
                            @endif
                            @if ($order->driver_name)
                                <div class="stk-def__row">
                                    <dt>المندوب</dt>
                                    <dd>{{ $order->driver_name }}</dd>
                                </div>
                            @endif
                            <div class="stk-def__row">
                                <dt>التوصيل المتوقع</dt>
                                <dd>{{ $order->estimated_delivery_text }}</dd>
                            </div>
                        </dl>
                    </div>
                </div>

                <div class="card">
                    <div class="stk-table-head">
                        <h6 class="stk-table-head__title"><i class="feather icon-credit-card"></i> الدفع</h6>
                    </div>
                    <div class="card-body">
                        <dl class="stk-def">
                            <div class="stk-def__row">
                                <dt>الطريقة</dt>
                                <dd>{{ $order->payment_method->label() }}</dd>
                            </div>
                            <div class="stk-def__row">
                                <dt>الحالة</dt>
                                <dd><span class="stk-state stk-state--{{ $stkTone[$order->payment_status->color()] ?? 'neutral' }}">{{ $order->payment_status->label() }}</span></dd>
                            </div>
                            @if ($order->paid_at)
                                <div class="stk-def__row">
                                    <dt>تاريخ الدفع</dt>
                                    <dd class="stk-num">{{ $order->paid_at->format('Y-m-d H:i') }}</dd>
                                </div>
                            @endif
                        </dl>
                    </div>
                </div>

                @if (hasPermission('online_store.orders.update_status'))
                    <div class="card">
                        <div class="stk-table-head">
                            <h6 class="stk-table-head__title"><i class="feather icon-refresh-cw"></i> تحديث الحالة</h6>
                        </div>
                        <div class="card-body">
                            <form action="{{ route('online_orders.update-status', $order) }}" method="POST">
                                @csrf
                                <div class="form-group">
                                    <label>الحالة الجديدة</label>
                                    <select name="status" class="form-control" required>
                                        <option value="">اختر...</option>
                                        @foreach ($nextStatuses as $status)
                                            <option value="{{ $status->value }}">{{ $status->label() }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>ملاحظة (اختياري)</label>
                                    <textarea name="notes" class="form-control" rows="2"></textarea>
                                </div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="feather icon-check"></i> تحديث
                                </button>
                            </form>
                        </div>
                    </div>
                @endif

                @if (hasPermission('online_store.orders.add_note'))
                    <div class="card">
                        <div class="stk-table-head">
                            <h6 class="stk-table-head__title"><i class="feather icon-edit-3"></i> ملاحظات إدارية</h6>
                        </div>
                        <div class="card-body">
                            @if ($order->admin_notes)
                                <p class="mb-2">{{ $order->admin_notes }}</p>
                            @endif
                            <form action="{{ route('online_orders.add-note', $order) }}" method="POST">
                                @csrf
                                <div class="form-group">
                                    <textarea name="admin_notes" class="form-control" rows="2" placeholder="أضف ملاحظة..." required></textarea>
                                </div>
                                <button type="submit" class="btn btn-outline-secondary">
                                    <i class="feather icon-plus"></i> إضافة
                                </button>
                            </form>
                        </div>
                    </div>
                @endif

                @if ($order->notes)
                    <div class="card">
                        <div class="stk-table-head">
                            <h6 class="stk-table-head__title"><i class="feather icon-message-square"></i> ملاحظات العميل</h6>
                        </div>
                        <div class="card-body">
                            <p class="mb-0">{{ $order->notes }}</p>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    @if (hasPermission('online_store.orders.update_shipping'))
        <!-- Shipping Modal -->
        <div class="modal fade" id="shippingModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="feather icon-truck"></i> معلومات الشحن</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" data-dismiss="modal" aria-label="إغلاق"></button>
                    </div>
                    <form action="{{ route('online_orders.update-shipping', $order) }}" method="POST">
                        @csrf
                        @if (in_array($order->status, [\App\Enums\OrderStatus::PROCESSING, \App\Enums\OrderStatus::READY_FOR_SHIPPING]))
                            <input type="hidden" name="update_status" value="shipped">
                        @endif
                        <div class="modal-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>رقم التتبع</label>
                                        <input type="text" name="tracking_number" class="form-control" value="{{ $order->tracking_number }}">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>شركة الشحن</label>
                                        <input type="text" name="shipping_company" class="form-control" value="{{ $order->shipping_company }}">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>تاريخ التوصيل المتوقع</label>
                                        <input type="date" name="estimated_delivery_date" class="form-control" value="{{ $order->estimated_delivery_date ? $order->estimated_delivery_date->format('Y-m-d') : '' }}">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>اسم المندوب</label>
                                        <input type="text" name="driver_name" class="form-control" value="{{ $order->driver_name }}">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>هاتف المندوب</label>
                                        <input type="text" name="driver_phone" class="form-control" value="{{ $order->driver_phone }}">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" data-dismiss="modal">إلغاء</button>
                            <button type="submit" class="btn btn-primary">حفظ</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    @if (hasPermission('online_store.orders.cancel'))
        <!-- Cancel Modal -->
        <div class="modal fade" id="cancelModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">إلغاء الطلب</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" data-dismiss="modal" aria-label="إغلاق"></button>
                    </div>
                    <form action="{{ route('online_orders.cancel', $order) }}" method="POST">
                        @csrf
                        <div class="modal-body">
                            <div class="alert alert-warning">
                                <i class="feather icon-alert-triangle"></i> هذا الإجراء لا يمكن التراجع عنه
                            </div>
                            <div class="form-group">
                                <label>سبب الإلغاء</label>
                                <textarea name="cancel_reason" class="form-control" rows="3" required></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" data-dismiss="modal">تراجع</button>
                            <button type="submit" class="btn btn-danger">تأكيد الإلغاء</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    @if (hasPermission('online_store.orders.update_status'))
        <!-- Return Modal -->
        <div class="modal fade" id="returnModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">إرجاع الطلب</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" data-dismiss="modal" aria-label="إغلاق"></button>
                    </div>
                    <form action="{{ route('online_orders.update-status', $order) }}" method="POST">
                        @csrf
                        <input type="hidden" name="status" value="returned">
                        <div class="modal-body">
                            <div class="form-group">
                                <label>سبب الإرجاع</label>
                                <select name="return_reason_type" class="form-control mb-2">
                                    <option value="">اختر السبب...</option>
                                    <option value="customer_request">طلب العميل</option>
                                    <option value="damaged">المنتج تالف</option>
                                    <option value="wrong_item">منتج خاطئ</option>
                                    <option value="not_as_described">لا يطابق الوصف</option>
                                    <option value="delivery_failed">فشل التوصيل</option>
                                </select>
                                <textarea name="notes" class="form-control" rows="3" placeholder="تفاصيل إضافية..."></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" data-dismiss="modal">إلغاء</button>
                            <button type="submit" class="btn btn-warning">تأكيد الإرجاع</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    @if (hasPermission('online_store.orders.refund'))
        <!-- Refund Modal -->
        <div class="modal fade" id="refundModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">استرداد المبلغ</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" data-dismiss="modal" aria-label="إغلاق"></button>
                    </div>
                    <form action="{{ route('online_orders.refund', $order) }}" method="POST">
                        @csrf
                        <div class="modal-body">
                            @php $refundable = $order->refundableAmount(); @endphp
                            <div class="form-group mb-2">
                                <label>مبلغ الاسترداد ({{ $order->documentCurrency() }})</label>
                                <input type="number" name="refund_amount" class="form-control" step="0.01" min="0.01" max="{{ $refundable }}" value="{{ $refundable }}" required>
                                <small class="text-muted">
                                    الحد الأقصى: {{ $order->formatMoney($refundable) }}
                                    @if ($order->refundedSoFar() > 0) — رُدّ سابقاً {{ $order->formatMoney($order->refundedSoFar()) }} @endif
                                    @if (! $order->shippingIsRefundable() && $order->shipping_cost > 0) — رسوم الشحن ({{ $order->formatMoney($order->shipping_cost + $order->shipping_tax_amount) }}) لا تُردّ بعد الشحن @endif
                                </small>
                            </div>
                            @if ($order->isPaid())
                                <div class="form-group mb-2">
                                    <label>الخزينة التي يُصرف منها</label>
                                    <select name="treasury_id" class="form-control">
                                        @foreach ($refundTreasuries as $treasury)
                                            <option value="{{ $treasury->id }}" @selected($treasury->id == $defaultTreasuryId)>
                                                {{ $treasury->name }} — الرصيد {{ \App\Support\CurrencySettings::number($treasury->balance) }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @if ($refundTreasuries->isEmpty())
                                        <small class="text-danger">لا توجد خزينة متجر يحقّ لك السحب منها.</small>
                                    @endif
                                </div>
                            @else
                                <div class="alert alert-warning py-2">الطلب لم يُحصَّل بعد: يُخفَّض المستحق على العميل دون صرفٍ من الخزينة.</div>
                            @endif
                            @if (! $order->stock_returned_at)
                                <div class="form-check mb-2">
                                    <input type="hidden" name="restock" value="0">
                                    <input type="checkbox" class="form-check-input" id="refundRestock" name="restock" value="1" checked>
                                    <label class="form-check-label" for="refundRestock">البضاعة عادت للمستودع (تُعاد للمخزون وتُعكَس تكلفتها)</label>
                                    <small class="d-block text-muted">تُطبَّق فقط عند استرداد كامل المبلغ المتبقي. الاسترداد الجزئي خصمٌ بلا إرجاع بضاعة.</small>
                                </div>
                            @endif
                            <div class="form-group">
                                <label>السبب</label>
                                <textarea name="refund_reason" class="form-control" rows="2" maxlength="500" required></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" data-dismiss="modal">إلغاء</button>
                            <button type="submit" class="btn btn-dark">تأكيد الاسترداد</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
    @if ($order->collectibleAmount() > 0 && hasPermission('online_store.orders.update_status'))
        <!-- Collect Payment Modal -->
        <div class="modal fade" id="collectModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">تسجيل تحصيل قيمة الطلب</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" data-dismiss="modal" aria-label="إغلاق"></button>
                    </div>
                    <form action="{{ route('online_orders.collect-payment', $order) }}" method="POST">
                        @csrf
                        <div class="modal-body">
                            <p class="mb-2">المبلغ المستحق: <strong>{{ $order->formatMoney($order->collectibleAmount()) }}</strong></p>
                            <div class="form-group">
                                <label>الخزينة / البنك الذي قُبض فيه المبلغ</label>
                                <select name="treasury_id" class="form-control" required>
                                    @foreach ($collectTreasuries as $treasury)
                                        <option value="{{ $treasury->id }}" @selected($treasury->id == $defaultTreasuryId)>{{ $treasury->name }}</option>
                                    @endforeach
                                </select>
                                @if ($collectTreasuries->isEmpty())
                                    <small class="text-danger">لا توجد خزينة متجر يحقّ لك الإيداع فيها. أنشئ خزينة للمتجر أو راجع صلاحيات الخزائن.</small>
                                @endif
                            </div>
                            <small class="text-muted">القيد: مدين الخزينة — دائن ذمّة العميل، بتاريخ اليوم.</small>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" data-dismiss="modal">إلغاء</button>
                            <button type="submit" class="btn btn-success" @disabled($collectTreasuries->isEmpty())>تأكيد التحصيل</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
@endsection
