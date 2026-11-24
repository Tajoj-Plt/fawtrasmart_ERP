@extends('manufacturing::master')

@section('title', 'استلام مواد — ' . $order->name)

@section('content')
    @php
        $items = collect($rawMaterials);
        $shortages = session('insufficient_materials');
        $trim = fn ($n) => rtrim(rtrim(number_format((float) $n, 4, '.', ''), '0'), '.') ?: '0';

        /*
            ما سيُصرَف فعلاً: الكميةُ بالوحدة الأساسية × «سعر التكلفة» —
            متوسطُ تكلفة الصنف، وهو ما يُرحَّل به قيدُ الصرف. كانت الشاشةُ
            تعرض سعرَ البند التقديريّ، فيرى المستخدم رقماً ويُقيَّد غيرُه.
        */
        $inventory = app(\Modules\Manufacturing\Services\InventoryService::class);
        $issueLines = $items->map(function ($item) use ($inventory, $order) {
            $factor = (float) ($item->raw_conversion_factor ?: 1) ?: 1;
            $base = (float) $item->raw_quantity * $factor;
            $cost = $inventory->unitCost((int) $item->raw_product_id, (int) ($order->storehouse_id ?: 0));

            return ['base' => $base, 'cost' => $cost, 'total' => round($base * $cost, 2)];
        });
        $totalAmount = $issueLines->sum('total');

        // المطلوبُ من كل مادةٍ بالوحدة الأساسية — مجموعاً إن وردت في أكثر من سطر
        $requiredByProduct = [];
        foreach ($items as $index => $item) {
            $requiredByProduct[$item->raw_product_id] = ($requiredByProduct[$item->raw_product_id] ?? 0) + $issueLines[$index]['base'];
        }
    @endphp

    <div class="content-body">

        @include('stock::components.page-head', [
            'title' => 'استلام مواد الأمر',
            'icon' => 'download',
            'sub' => $order->name . ' — #' . $order->code,
            'crumbs' => [
                'الرئيسية' => url('/'),
                'أوامر التصنيع' => route('manufacturing.orders.index'),
                $order->name => route('manufacturing.orders.show', $order->id),
                'استلام المواد' => null,
            ],
            'actions' => '<a href="' . route('manufacturing.orders.show', $order->id) . '" class="btn btn-outline-secondary">'
                . '<i class="feather icon-x"></i> إلغاء</a>'
                . '<button type="submit" form="receiveMaterialsForm" class="btn btn-primary">'
                . '<i class="feather icon-check"></i> تأكيد الاستلام</button>',
        ])

        @include('layouts.alerts.error')
        @include('stock::components.form-errors')
        @include('layouts.alerts.success')

        {{-- نقص المخزون: يُعرض جدولاً لا قائمةَ تنبيهات متلاصقة --}}
        @if ($shortages)
            <div class="card stk-table-card">
                <div class="stk-table-head">
                    <h6 class="stk-table-head__title text-danger">
                        <i class="feather icon-alert-triangle"></i> مواد غير كافية في المستودع
                        <span class="stk-count">{{ count($shortages) }}</span>
                    </h6>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>المادة</th>
                                <th class="text-center" style="width:16%">المطلوب</th>
                                <th class="text-center" style="width:16%">المتوفر</th>
                                <th class="text-center" style="width:16%">النقص</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($shortages as $material)
                                <tr>
                                    <td>{{ $material['name'] ?? $material['product_name'] ?? '—' }}</td>
                                    <td class="text-center stk-num">{{ $material['required'] ?? '—' }}</td>
                                    <td class="text-center stk-num">{{ $material['available'] ?? '—' }}</td>
                                    <td class="text-center stk-num text-danger">{{ $material['shortage'] ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        {{-- جدولُ «المواد في المستودعات» أُزيل بطلب المستخدم: الشاشةُ تعرض الموادَّ
             التي ستُستلَم ورصيدَها في المستودع المختار وحدَه — في جدول المواد أدناه. --}}

        <form id="receiveMaterialsForm" method="POST"
              action="{{ route('manufacturing.orders.receive-materials', $order->id) }}">
            @csrf

            <div class="card">
                <div class="card-header">
                    <h4 class="card-title"><i class="feather icon-home"></i> المستودع</h4>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group mb-0">
                                <label for="storehouse_id" class="stk-req">المستودع المصروف منه</label>
                                <select id="storehouse_id" name="storehouse_id" required class="form-control select2">
                                    <option value="">اختر المستودع…</option>
                                    @foreach ($storeHouses as $storehouse)
                                        <option value="{{ $storehouse->id }}" @selected(old('storehouse_id', $order->storehouse_id) == $storehouse->id)>{{ $storehouse->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="alert alert-info mb-0">
                                <i class="feather icon-info"></i>
                                سيُنشأ إذن صرف مخزني بالمواد أدناه من المستودع المختار، ويُقيَّد بسعر التكلفة
                                على حساب ذلك المستودع.
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card stk-table-card">
                <div class="stk-table-head">
                    <h6 class="stk-table-head__title">
                        <i class="feather icon-package"></i> المواد الخام المطلوبة
                        <span class="stk-count">{{ $items->count() }}</span>
                    </h6>
                    <span class="stk-balance stk-num">@money($totalAmount)</span>
                </div>

                @if ($items->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th style="width:44px">#</th>
                                    <th>المنتج</th>
                                    <th class="text-center" style="width:10%">الوحدة</th>
                                    <th class="text-center" style="width:11%">الكمية المطلوبة</th>
                                    <th class="text-center" style="width:13%">الموجود في المستودع</th>
                                    <th class="text-center" style="width:11%">المتاح للسحب</th>
                                    <th class="text-center" style="width:11%">سعر التكلفة</th>
                                    <th class="text-center" style="width:12%">الإجمالي</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($items as $index => $item)
                                    @php
                                        $product = $item->rawProduct;
                                        $issue = $issueLines[$index];
                                    @endphp

                                    <tr>
                                        <td class="stk-num text-muted">{{ $index + 1 }}</td>
                                        <td>
                                            <div class="stk-media__body">
                                                <span class="stk-media__title">{{ optional($product)->name ?? '—' }}</span>
                                                @if (optional($product)->serial_number)
                                                    <span class="stk-media__sub stk-num">#{{ $product->serial_number }}</span>
                                                @endif
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            @include('manufacturing::orders.partials.unit-name', ['item' => $item])
                                        </td>
                                        <td class="text-center stk-num">
                                            {{ $trim($issue['base']) }}
                                            @if (abs($issue['base'] - (float) $item->raw_quantity) > 0.0001)
                                                <span class="stk-media__sub d-block">{{ $trim($item->raw_quantity) }} بوحدة البند</span>
                                            @endif
                                        </td>
                                        {{-- رصيدُ المستودع المختار — يملؤه السكربت عند اختيار المستودع --}}
                                        <td class="text-center stk-num js-on-hand" data-product="{{ $item->raw_product_id }}">—</td>
                                        <td class="text-center stk-num js-available" data-product="{{ $item->raw_product_id }}"
                                            data-required="{{ $requiredByProduct[$item->raw_product_id] ?? $issue['base'] }}">—</td>
                                        <td class="text-center stk-num">{{ $trim($issue['cost']) }}</td>
                                        <td class="text-center stk-num"><strong>@money($issue['total'])</strong></td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr class="stk-total-row">
                                    <td colspan="7">إجمالي قيد الصرف</td>
                                    <td class="text-center stk-num">@money($totalAmount)</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                @else
                    <div class="card-body">
                        <div class="stk-empty">
                            <div class="stk-empty__icon"><i class="feather icon-package"></i></div>
                            <h5 class="stk-empty__title">لا مواد خام</h5>
                            <p class="stk-empty__hint">لم تُحدَّد مواد خام لهذا الأمر</p>
                        </div>
                    </div>
                @endif

                {{-- لا يُسحب من المستودع أكثرُ ممّا فيه — يظهر فور اختيار مستودعٍ لا يكفي --}}
                <div class="alert alert-danger m-2 mb-0" id="shortageNotice" hidden>
                    <i class="feather icon-alert-triangle"></i>
                    <strong>لا يمكن سحب أكثر من الكمية الموجودة في المستودع.</strong>
                    <span id="shortageList"></span>
                </div>

                <div class="stk-form-foot">
                    <a href="{{ route('manufacturing.orders.show', $order->id) }}" class="btn btn-outline-secondary">
                        <i class="feather icon-x"></i> إلغاء
                    </a>
                    <button type="submit" class="btn btn-primary" id="confirmReceive" @disabled($items->isEmpty())>
                        <i class="feather icon-check"></i> تأكيد الاستلام
                    </button>
                </div>
            </div>
        </form>
    </div>
@endsection

@section('scripts')
    <script>
        $(function () {
            if ($.fn.select2) {
                $('#receiveMaterialsForm .select2').select2({ placeholder: 'اختر…', dir: 'rtl', width: '100%' });
            }

            /* رصيدُ كل مادةٍ في كل مستودع: الفعليّ، والمحجوز لأوامر أخرى، والمتاح */
            const stock = @json($stockByWarehouse ?? []);
            const names = @json($items->mapWithKeys(fn ($item) => [$item->raw_product_id => optional($item->rawProduct)->name])->all());
            const trim = value => String(Number(Number(value || 0).toFixed(4)));

            function refreshStock() {
                const warehouse = $('#storehouse_id').val();
                const figures = warehouse ? (stock[warehouse] || {}) : null;
                const short = {};

                $('.js-on-hand').each(function () {
                    const row = figures ? figures[$(this).data('product')] : null;
                    $(this).text(figures ? trim(row ? row.on_hand : 0) : '—');
                });

                $('.js-available').each(function () {
                    const product = $(this).data('product');
                    const required = parseFloat($(this).data('required')) || 0;
                    const row = figures ? figures[product] : null;
                    const available = row ? Number(row.available) : 0;
                    const enough = ! figures || available + 0.0001 >= required;

                    $(this).text(figures ? trim(available) : '—')
                        .toggleClass('text-danger', ! enough)
                        .toggleClass('text-success', !! figures && enough);
                    $(this).closest('tr').toggleClass('table-danger', ! enough);

                    if (! enough) {
                        short[product] = (names[product] || ('#' + product))
                            + ': المطلوب ' + trim(required) + '، المتاح ' + trim(available)
                            + (row && Number(row.reserved) > 0 ? ' (محجوز لأوامر أخرى ' + trim(row.reserved) + ')' : '');
                    }
                });

                const list = Object.values(short);

                $('#shortageNotice').prop('hidden', list.length === 0);
                $('#shortageList').html(list.length ? '<br>' + list.map(text => '• ' + $('<div>').text(text).html()).join('<br>') : '');
                $('#confirmReceive, button[form="receiveMaterialsForm"]').prop('disabled', list.length > 0);
            }

            $('#storehouse_id').on('change', refreshStock);
            refreshStock();
        });
    </script>
@endsection
