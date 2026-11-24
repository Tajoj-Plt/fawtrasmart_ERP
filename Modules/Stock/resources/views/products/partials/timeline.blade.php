{{--
    الخطّ الزمني لحركة المنتج — يُحمَّل بـAjax وتُعاد صياغته وحده.

    كان السطر الأول من هذا الملف مسارَ الملف نفسه مكتوباً نصّاً، فيُطبع في
    الصفحة. حُذف.

    كلّ حركة سطرٌ واحد: مَن، وكم، وإلى أين، وما صار الرصيد بعدها.
--}}

@php
    $movements = $stock_movements ?? null;
    $rows = ($movements && is_object($movements) && method_exists($movements, 'count')) ? $movements : collect();
@endphp

@if ($rows->count() > 0)
    <ul class="stk-timeline">
        @foreach ($rows as $movement)
            @php
                $permit = $movement->warehousePermits;
                // نوع الإذن 2 = صرف؛ ما عداه إضافة
                $isOut = optional($permit)->permission_type == 2;
            @endphp

            <li class="stk-timeline__item">
                <span class="stk-timeline__dot stk-timeline__dot--{{ $isOut ? 'out' : 'in' }}">
                    <i class="feather icon-{{ $isOut ? 'arrow-down' : 'arrow-up' }}"></i>
                </span>

                <div class="stk-timeline__body">
                    <p class="stk-timeline__head">
                        <strong>{{ optional(optional($permit)->user)->name ?? 'مستخدم غير معروف' }}</strong>
                        {{ $isOut ? 'أنقص' : 'أضاف' }}
                        <span class="stk-num stk-timeline__qty">{{ $movement->quantity }}</span> وحدة
                        {{ $isOut ? 'من' : 'إلى' }} مخزون
                        <a href="{{ route('products.show', $product->id) }}" target="_blank">
                            <span class="stk-num">#{{ $product->serial_number }}</span> ({{ $product->name }})
                        </a>
                    </p>

                    <div class="stk-def stk-def--inline">
                        <span class="stk-def__row">
                            <span>رقم العملية</span>
                            <b class="stk-num">#{{ optional($permit)->number }}</b>
                        </span>
                        <span class="stk-def__row">
                            <span>سعر الوحدة</span>
                            <b class="stk-num">@money($movement->unit_price)</b>
                        </span>
                        <span class="stk-def__row">
                            <span>متوسط التكلفة</span>
                            <b class="stk-num">@money($average_cost)</b>
                        </span>
                        <span class="stk-def__row">
                            <span>الرصيد بعدها</span>
                            <b class="stk-num">{{ $movement->stock_after }}</b>
                        </span>
                    </div>

                    <p class="stk-timeline__meta">
                        <span><i class="feather icon-clock"></i> {{ optional($permit)->permission_date }}</span>
                        <span title="{{ optional(optional($permit)->user)->ip_address }}">
                            <i class="feather icon-user"></i> {{ optional(optional($permit)->user)->name }}
                        </span>
                        <span><i class="feather icon-home"></i> {{ optional(optional($permit)->storeHouse)->name }}</span>
                    </p>
                </div>
            </li>
        @endforeach
    </ul>

    @if (method_exists($rows, 'hasPages'))
        @include('stock::components.js-pagination', [
            'paginator' => $rows,
            'callback' => 'loadTimeline',
            'unit' => 'حركة',
        ])
    @endif
@else
    <div class="stk-empty">
        <div class="stk-empty__icon"><i class="feather icon-git-commit"></i></div>
        <h5 class="stk-empty__title">لا توجد حركات بعد</h5>
        <p class="stk-empty__hint">تظهر هنا كل إضافة أو صرف لهذا المنتج مرتّبةً زمنيّاً</p>
    </div>
@endif
