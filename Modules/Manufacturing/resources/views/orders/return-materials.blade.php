@extends('manufacturing::master')

@section('title', 'إرجاع مواد — ' . $order->name)

@section('content')
    @php
        /*
            سطرٌ لكل مادةٍ لا لكل بند، بالوحدة الأساسية وبتكلفتها الفعلية —
            كما يحسبها الخادم عند الإرجاع. يُكتب أمام المادة ما يُراد
            إرجاعُه منها، ويُترك الباقي صفراً.
        */
        $items = collect($returnLines ?? []);
        $trim = fn ($n) => rtrim(rtrim(number_format((float) $n, 4, '.', ''), '0'), '.') ?: '0';
    @endphp

    <div class="content-body">

        @include('stock::components.page-head', [
            'title' => 'إرجاع مواد الأمر',
            'icon' => 'rotate-ccw',
            'sub' => $order->name . ' — #' . $order->code,
            'crumbs' => [
                'الرئيسية' => url('/'),
                'أوامر التصنيع' => route('manufacturing.orders.index'),
                $order->name => route('manufacturing.orders.show', $order->id),
                'إرجاع المواد' => null,
            ],
            'actions' => '<a href="' . route('manufacturing.orders.show', $order->id) . '" class="btn btn-outline-secondary">'
                . '<i class="feather icon-x"></i> إلغاء</a>'
                . '<button type="submit" form="returnMaterialsForm" class="btn btn-primary">'
                . '<i class="feather icon-check"></i> تأكيد الإرجاع</button>',
        ])

        @include('layouts.alerts.error')
        @include('stock::components.form-errors')
        @include('layouts.alerts.success')

        <form id="returnMaterialsForm" method="POST"
              action="{{ route('manufacturing.orders.return-materials', $order->id) }}">
            @csrf

            <div class="card">
                <div class="card-header">
                    <h4 class="card-title"><i class="feather icon-home"></i> وجهة الإرجاع</h4>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group mb-0">
                                <label for="storehouse_id" class="stk-req">المستودع</label>
                                <select id="storehouse_id" name="storehouse_id" required class="form-control select2">
                                    <option value="">اختر المستودع…</option>
                                    @foreach ($storehouses as $storehouse)
                                        <option value="{{ $storehouse->id }}" @selected(old('storehouse_id', $order->storehouse_id) == $storehouse->id)>{{ $storehouse->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group mb-0">
                                <label for="notes">ملاحظات</label>
                                <textarea id="notes" name="notes" class="form-control" rows="2"
                                          placeholder="سبب الإرجاع…"></textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card stk-table-card">
                <div class="stk-table-head">
                    <h6 class="stk-table-head__title">
                        <i class="feather icon-package"></i> المواد القابلة للإرجاع
                        <span class="stk-count">{{ $items->count() }}</span>
                    </h6>

                    @if ($items->count())
                        <div class="d-flex" style="gap:.4rem">
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="returnAll">
                                <i class="feather icon-check-square"></i> إرجاع الكل
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="clearAll">
                                <i class="feather icon-square"></i> تصفير
                            </button>
                        </div>
                    @endif
                </div>

                @if ($items->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" id="returnTable">
                            <thead>
                                <tr>
                                    <th style="width:44px">#</th>
                                    <th>المادة الخام</th>
                                    <th class="text-center" style="width:10%">الوحدة</th>
                                    <th class="text-center" style="width:12%">المصروفة</th>
                                    <th class="text-center" style="width:12%">أُرجع سابقاً</th>
                                    <th class="text-center" style="width:12%">المتاح للإرجاع</th>
                                    <th class="text-center" style="width:12%">تكلفة الوحدة</th>
                                    <th class="text-center" style="width:16%">الكمية المُرجَعة</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($items as $index => $item)
                                    <tr>
                                        <td class="stk-num text-muted">{{ $index + 1 }}</td>
                                        <td>
                                            <span class="stk-media__title">{{ $item['name'] }}</span>
                                        </td>
                                        <td class="text-center">{{ $item['unit'] }}</td>
                                        <td class="text-center stk-num">{{ $trim($item['issued']) }}</td>
                                        <td class="text-center stk-num">{{ $trim($item['returned']) }}</td>
                                        <td class="text-center stk-num stk-balance">{{ $trim($item['remaining']) }}</td>
                                        <td class="text-center stk-num">{{ $trim($item['unit_cost']) }}</td>
                                        <td>
                                            <input type="hidden" name="return_items[{{ $index }}][product_id]"
                                                   value="{{ $item['product_id'] }}">
                                            <input type="number" step="any" min="0" max="{{ $item['remaining'] }}"
                                                   name="return_items[{{ $index }}][quantity]"
                                                   class="form-control stk-num text-center js-qty"
                                                   data-max="{{ $item['remaining'] }}" data-cost="{{ $item['unit_cost'] }}"
                                                   value="{{ old('return_items.' . $index . '.quantity', 0) }}"
                                                   @disabled($item['remaining'] <= 0)>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="card-body">
                        <div class="stk-empty">
                            <div class="stk-empty__icon"><i class="feather icon-package"></i></div>
                            <h5 class="stk-empty__title">لا مواد قابلة للإرجاع</h5>
                            <p class="stk-empty__hint">لم تُستلَم مواد لهذا الأمر بعد</p>
                        </div>
                    </div>
                @endif

                <div class="stk-form-foot">
                    <span class="stk-help me-auto">
                        اكتب الكمية أمام المادة التي تريد إرجاعها واترك الباقي صفراً —
                        تُرجَع بالتكلفة التي خرجت بها.
                        قيمة المُرجَع: <strong class="stk-num" id="returnTotal">0.00</strong>
                    </span>
                    <a href="{{ route('manufacturing.orders.show', $order->id) }}" class="btn btn-outline-secondary">
                        <i class="feather icon-x"></i> إلغاء
                    </a>
                    <button type="submit" class="btn btn-primary" @disabled($items->isEmpty())>
                        <i class="feather icon-check"></i> تأكيد الإرجاع
                    </button>
                </div>
            </div>
        </form>
    </div>
@endsection

@section('scripts')
    <script src="{{ asset('app-assets/vendors/js/extensions/sweetalert2.all.min.js') }}"></script>
    <script>
        $(function () {
            if ($.fn.select2) {
                $('#returnMaterialsForm .select2').select2({ placeholder: 'اختر…', dir: 'rtl', width: '100%' });
            }

            function refresh() {
                let total = 0;
                $('.js-qty').each(function () {
                    total += (parseFloat(this.value) || 0) * (parseFloat($(this).data('cost')) || 0);
                });
                $('#returnTotal').text(total.toFixed(2));
            }

            /* الكمية لا تتجاوز المستلَمة — كان الحدّ في `max` وحده،
               وهو لا يمنع الكتابة بل يمنع الإرسال بلا شرح */
            $('#returnTable').on('input', '.js-qty', function () {
                const max = parseFloat($(this).data('max')) || 0;
                if (parseFloat(this.value) > max) this.value = max;
                if (parseFloat(this.value) < 0) this.value = 0;
                refresh();
            });

            $('#returnAll').on('click', function () {
                $('.js-qty:not(:disabled)').each(function () { this.value = $(this).data('max'); });
                refresh();
            });

            $('#clearAll').on('click', function () {
                $('.js-qty').val(0);
                refresh();
            });

            $('#returnMaterialsForm').on('submit', function (e) {
                const any = $('.js-qty').filter(function () { return (parseFloat(this.value) || 0) > 0; }).length;

                if (! any) {
                    e.preventDefault();
                    Swal.fire({ icon: 'error', title: 'لا كميات', text: 'اكتب كمية أمام مادةٍ واحدة على الأقل' });
                }
            });

            refresh();
        });
    </script>
@endsection
