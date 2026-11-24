@if(isset($stock_movements) && is_object($stock_movements) && method_exists($stock_movements, 'count') && $stock_movements->count() > 0)
    <div class="stk-timeline">
        @foreach($stock_movements as $movement)
            @if($movement->warehousePermits)
            <div class="stk-timeline__item">
                <span class="stk-timeline__dot stk-timeline__dot--log">
                    <i class="feather icon-package"></i>
                </span>

                <div class="stk-timeline__body">
                    <div class="stk-timeline__text">
                        @if($movement->warehousePermits->permission_type == 2)
                            أنقص <strong>{{ $movement->warehousePermits->user->name ?? 'مستخدم غير معروف' }}</strong>
                            <strong>{{ $movement->quantity }}</strong> وحدة من مخزون
                            <strong><a href="{{ route('store_products.show', $product->id) }}" target="_blank">#{{ $product->serial_number }} ({{ $product->name }})</a></strong>
                            يدويا (رقم العملية: <strong>#{{ $movement->warehousePermits->number }}</strong>)،
                            وسعر الوحدة: <strong>{{ $movement->unit_price }}&nbsp;{{ \App\Support\CurrencySettings::symbol() }}</strong>،
                            وأصبح المخزون الباقي من المنتج: <strong>{{ $movement->stock_after }}</strong>
                            @if($movement->warehousePermits->storeHouse)
                                وأصبح المخزون <strong>{{ $movement->warehousePermits->storeHouse->name }}</strong> رصيده <strong>{{ $movement->stock_after }}</strong>
                            @endif
                            , متوسط السعر: <strong>{{ $average_cost ?? 0 }}&nbsp;{{ \App\Support\CurrencySettings::symbol() }}</strong>
                        @else
                            أضاف <strong>{{ $movement->warehousePermits->user->name ?? 'مستخدم غير معروف' }}</strong>
                            <strong>{{ $movement->quantity }}</strong> وحدة إلى مخزون
                            <strong><a href="{{ route('store_products.show', $product->id) }}" target="_blank">#{{ $product->serial_number }} ({{ $product->name }})</a></strong>
                            يدويا (رقم العملية: <strong>#{{ $movement->warehousePermits->number }}</strong>)،
                            وسعر الوحدة: <strong>{{ $movement->unit_price }}&nbsp;{{ \App\Support\CurrencySettings::symbol() }}</strong>،
                            وأصبح المخزون الباقي من المنتج: <strong>{{ $movement->stock_after }}</strong>
                            @if($movement->warehousePermits->storeHouse)
                                وأصبح المخزون <strong>{{ $movement->warehousePermits->storeHouse->name }}</strong> رصيده <strong>{{ $movement->stock_after }}</strong>
                            @endif
                            , متوسط السعر: <strong>{{ $average_cost ?? 0 }}&nbsp;{{ \App\Support\CurrencySettings::symbol() }}</strong>
                        @endif
                    </div>

                    <div class="stk-timeline__meta">
                        <span class="stk-timeline__time">
                            <i class="fa fa-clock-o"></i> {{ $movement->warehousePermits->permission_date }}
                        </span>
                        <span>
                            <i class="fa fa-user"></i> {{ $movement->warehousePermits->user->name ?? 'مستخدم غير معروف' }}
                        </span>
                        @if($movement->warehousePermits->storeHouse)
                            <span><i class="fa fa-building"></i> {{ $movement->warehousePermits->storeHouse->name }}</span>
                        @endif
                    </div>
                </div>
            </div>
            @endif
        @endforeach
    </div>

    {{-- الصفحات --}}
    @if(isset($stock_movements) && is_object($stock_movements) && method_exists($stock_movements, 'hasPages') && $stock_movements->hasPages())
        <nav aria-label="Timeline pagination" class="mt-4">
            <ul class="pagination justify-content-center">
                @if ($stock_movements->onFirstPage())
                    <li class="page-item disabled"><span class="page-link">الأول</span></li>
                    <li class="page-item disabled"><span class="page-link">السابق</span></li>
                @else
                    <li class="page-item">
                        <a class="page-link" href="javascript:void(0)" onclick="loadTimeline(1)">الأول</a>
                    </li>
                    <li class="page-item">
                        <a class="page-link" href="javascript:void(0)" onclick="loadTimeline({{ $stock_movements->currentPage() - 1 }})">السابق</a>
                    </li>
                @endif

                <li class="page-item active"><span class="page-link">{{ $stock_movements->currentPage() }}</span></li>

                @if ($stock_movements->hasMorePages())
                    <li class="page-item">
                        <a class="page-link" href="javascript:void(0)" onclick="loadTimeline({{ $stock_movements->currentPage() + 1 }})">التالي</a>
                    </li>
                @else
                    <li class="page-item disabled"><span class="page-link">التالي</span></li>
                @endif

                @if ($stock_movements->currentPage() < $stock_movements->lastPage())
                    <li class="page-item">
                        <a class="page-link" href="javascript:void(0)" onclick="loadTimeline({{ $stock_movements->lastPage() }})">الأخير</a>
                    </li>
                @else
                    <li class="page-item disabled"><span class="page-link">الأخير</span></li>
                @endif
            </ul>
        </nav>
    @endif

@else
    <div class="stk-empty">
        <span class="stk-empty__icon"><i class="feather icon-clock"></i></span>
        <h6 class="stk-empty__title">لا توجد عمليات</h6>
        <p class="stk-empty__hint">لا توجد عمليات مضافة حتى الآن.</p>
    </div>
@endif
