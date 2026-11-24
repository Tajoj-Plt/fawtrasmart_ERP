@extends('stock::master')

@section('title', 'تنفيذ الجرد')

@section('content')
    @php
        $statuses = [
            'draft' => ['مسودة', 'secondary'],
            'in_progress' => ['قيد التنفيذ', 'warning'],
            'completed' => ['مكتمل', 'info'],
            'adjusted' => ['تمت التسوية', 'success'],
            'cancelled' => ['ملغي', 'danger'],
        ];
        [$statusLabel, $statusTone] = $statuses[$adjustment->status] ?? [$adjustment->status, 'secondary'];

        $types = [
            'full' => 'جرد شامل',
            'partial' => 'جرد دوري',
            'spot' => 'جرد مفاجئ',
            'category' => 'جرد حسب الصنف',
        ];

        $reference = $adjustment->reference_number ?: $adjustment->id;

        /* تُمرَّر بيانات لا نصّاً مُقحَماً في القالب: كان بناء الصفوف يكتب
           اسم المنتج داخل قالب نصّي جافاسكربتي، فيكسره أي اقتباس في الاسم. */
        $productPayload = collect($products)->map(fn ($p) => [
            'id' => $p->id,
            'name' => $p->name,
            'barcode' => $p->barcode,
            'system' => (float) $p->current_quantity,
            'price' => (float) ($p->averageCost() ?? $p->purchase_price ?? 0),
        ])->values();
    @endphp

    <div class="content-body">

        @include('stock::components.page-head', [
            'title' => 'تنفيذ الجرد',
            'icon' => 'check-square',
            'sub' => 'ورقة #' . $reference . ' — ' . (optional($adjustment->storeHouse)->name ?? 'مستودع غير محدد'),
            'crumbs' => [
                'الرئيسية' => url('/'),
                'إدارة الجرد' => route('inventory_management.index'),
                '#' . $reference => route('inventory.show', $adjustment->id),
                'تنفيذ' => null,
            ],
            'actions' => '<button type="submit" form="final-form" class="btn btn-primary">'
                . '<i class="feather icon-check"></i> حفظ الجرد</button>',
        ])

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')

        @if ($errors->any())
            <div class="alert alert-danger">
                <h6 class="mb-1"><i class="feather icon-alert-circle"></i> توجد أخطاء</h6>
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- ══════════ بيانات الورقة والإحصاءات ══════════ --}}
        <div class="stk-metrics">
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-home"></i></span>
                <span class="stk-metric__value">{{ optional($adjustment->storeHouse)->name ?? '—' }}</span>
                <span class="stk-metric__label">المستودع</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-calendar"></i></span>
                <span class="stk-metric__value stk-num">
                    {{ $adjustment->inventory_time ? \Illuminate\Support\Carbon::parse($adjustment->inventory_time)->format('Y-m-d') : '—' }}
                </span>
                <span class="stk-metric__label">تاريخ الجرد</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-tag"></i></span>
                <span class="stk-metric__value">{{ $types[$adjustment->type] ?? $adjustment->type }}</span>
                <span class="stk-metric__label">نوع الجرد</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-flag"></i></span>
                <span class="stk-metric__value">
                    <span class="badge badge-light-{{ $statusTone }}">{{ $statusLabel }}</span>
                </span>
                <span class="stk-metric__label">الحالة</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-package"></i></span>
                <span class="stk-metric__value stk-num" id="itemsCount">0</span>
                <span class="stk-metric__label">الأصناف المُدخَلة</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-alert-triangle"></i></span>
                <span class="stk-metric__value stk-num" id="itemsWithDiff">0</span>
                <span class="stk-metric__label">أصناف بفروقات</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-arrow-up"></i></span>
                <span class="stk-metric__value stk-num text-success" id="positiveDiff">0.00</span>
                <span class="stk-metric__label">فروقات موجبة</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-arrow-down"></i></span>
                <span class="stk-metric__value stk-num text-danger" id="negativeDiff">0.00</span>
                <span class="stk-metric__label">فروقات سالبة</span>
            </div>
        </div>

        <form id="final-form" method="POST" enctype="multipart/form-data"
              action="{{ route('inventory.save_final', $adjustment->id) }}">
            @csrf

            <div class="card stk-table-card">
                <div class="stk-table-head">
                    <h6 class="stk-table-head__title">
                        <i class="feather icon-list"></i> أصناف الجرد
                        <span class="stk-count" id="rows-count">0</span>
                    </h6>

                    <div class="d-flex" style="gap:.4rem">
                        <button type="button" class="btn btn-sm btn-outline-primary"
                                data-toggle="modal" data-bs-toggle="modal"
                                data-target="#addProductModal" data-bs-target="#addProductModal">
                            <i class="feather icon-plus"></i> إضافة صنف
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="addAllBtn">
                            <i class="feather icon-layers"></i> إضافة كل الأصناف
                        </button>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th style="width:44px">#</th>
                                <th style="width:24%">الصنف</th>
                                <th class="text-center" style="width:13%">الباركود</th>
                                <th class="text-center" style="width:11%">سعر الوحدة</th>
                                <th class="text-center" style="width:11%">كمية النظام</th>
                                <th class="text-center" style="width:17%">الكمية الفعلية</th>
                                <th class="text-center" style="width:11%">الفارق</th>
                                <th class="text-center" style="width:7%">حذف</th>
                            </tr>
                        </thead>
                        <tbody id="products-body"></tbody>
                    </table>
                </div>

                <div class="card-body" id="empty-state">
                    <div class="stk-empty">
                        <div class="stk-empty__icon"><i class="feather icon-clipboard"></i></div>
                        <h5 class="stk-empty__title">لم تُضَف أصناف بعد</h5>
                        <p class="stk-empty__hint">أضف صنفاً بعينه، أو أضف كل أصناف المستودع دفعةً واحدة</p>
                    </div>
                </div>

                <div class="stk-form-foot">
                    <span class="stk-help me-auto">راجع الكمّيّات قبل الحفظ — تُرحَّل الفروقات إلى المخزون</span>
                    <a href="{{ route('inventory_management.index') }}" class="btn btn-outline-secondary">
                        <i class="feather icon-x"></i> إلغاء
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="feather icon-check"></i> حفظ الجرد
                    </button>
                </div>
            </div>
        </form>
    </div>

    {{-- ══════════ نافذة إضافة صنف ══════════ --}}
    <div class="modal fade" id="addProductModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content text-right">
                <div class="modal-header">
                    <h5 class="modal-title">إضافة صنف للجرد</h5>
                    <button type="button" class="close" data-dismiss="modal" data-bs-dismiss="modal" aria-label="إغلاق">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>

                <div class="modal-body">
                    <div class="form-group">
                        <label for="productSelect" class="stk-req">الصنف</label>
                        <select id="productSelect" class="form-control select2">
                            <option value="">اختر الصنف…</option>
                            @foreach ($products as $product)
                                <option value="{{ $product->id }}">
                                    {{ $product->name }} ({{ $product->current_quantity }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="row">
                        <div class="col-6">
                            <div class="form-group">
                                <label for="realQty" class="stk-req">الكمية الفعلية</label>
                                <input type="number" step="0.01" min="0" id="realQty" class="form-control stk-num">
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-group">
                                <label for="unitPrice">سعر الوحدة</label>
                                <input type="number" step="0.01" id="unitPrice" class="form-control stk-num" readonly>
                            </div>
                        </div>
                    </div>

                    <div class="form-group mb-0">
                        <label for="modalNote">ملاحظات</label>
                        <textarea id="modalNote" class="form-control" rows="2"></textarea>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal" data-bs-dismiss="modal">
                        إلغاء
                    </button>
                    <button type="button" class="btn btn-primary" id="addProductBtn">
                        <i class="feather icon-plus"></i> إضافة
                    </button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <script>
        $(function () {
            const products = @json($productPayload);

            const byId = {};
            products.forEach(p => { byId[p.id] = p; });

            const escapeHtml = value => $('<div>').text(value == null ? '' : value).html();
            const fixed = value => Number(value || 0).toFixed(2);

            function toast(message, tone) {
                if (typeof Swal === 'undefined') return;

                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: tone || 'info',
                    title: message,
                    showConfirmButton: false,
                    timer: 2200,
                });
            }

            function renumber() {
                $('#products-body tr').each(function (index) {
                    $(this).find('.row-index').text(index + 1);
                });
            }

            function refreshStats() {
                let withDiff = 0, positive = 0, negative = 0;
                const rows = $('#products-body tr');

                rows.each(function () {
                    const system = parseFloat($(this).find('[name*="quantity_in_system"]').val()) || 0;
                    const actual = parseFloat($(this).find('.qty-input').val()) || 0;
                    const difference = actual - system;

                    if (difference !== 0) withDiff++;
                    if (difference > 0) positive += difference;
                    else if (difference < 0) negative += Math.abs(difference);
                });

                $('#itemsCount, #rows-count').text(rows.length);
                $('#itemsWithDiff').text(withDiff);
                $('#positiveDiff').text(fixed(positive));
                $('#negativeDiff').text(fixed(negative));

                /*
                    `style.display` لا الخاصية `hidden`: قاعدة السمة
                    `[hidden]{display:none}` من متصفّحٍ تُغلب بأي `display`
                    تكتبه ثيمات الشاشة على `.card-body` — فكانت رسالة
                    «لم تُضَف أصناف» تبقى ظاهرة والجدول مملوء.
                */
                document.getElementById('empty-state').style.display = rows.length > 0 ? 'none' : '';
            }

            function updateDifference($row) {
                const system = parseFloat($row.find('[name*="quantity_in_system"]').val()) || 0;
                const actual = parseFloat($row.find('.qty-input').val());
                const cell = $row.find('.difference-cell');

                if (isNaN(actual)) {
                    cell.attr('class', 'stk-move stk-move--muted difference-cell').find('.stk-num').text('0.00');
                    return;
                }

                const difference = actual - system;
                const tone = difference > 0 ? 'success' : (difference < 0 ? 'danger' : 'muted');

                cell.attr('class', 'stk-move stk-move--' + tone + ' difference-cell')
                    .find('.stk-num')
                    .text(difference > 0 ? '+' + fixed(difference) : fixed(difference));
            }

            function addRow(product, quantity, note) {
                if ($('#products-body').find('[name="items[' + product.id + '][product_id]"]').length) {
                    return false;
                }

                const name = 'items[' + product.id + ']';
                const actual = (quantity === null || quantity === '' || quantity === undefined)
                    ? product.system      // بلا كمية مُدخَلة: نبدأ من رصيد النظام فالفارق صفر
                    : parseFloat(quantity);

                $('#products-body').append(
                    '<tr>'
                    + '<td class="stk-num text-muted row-index"></td>'

                    + '<td><span class="stk-media__title">' + escapeHtml(product.name) + '</span>'
                    + '  <input type="hidden" name="' + name + '[product_id]" value="' + product.id + '">'
                    + '  <input type="hidden" name="' + name + '[note]" value="' + escapeHtml(note || '') + '"></td>'

                    + '<td class="text-center stk-num">' + escapeHtml(product.barcode || '—') + '</td>'

                    + '<td class="text-center"><input type="number" step="0.01" readonly'
                    + '  class="form-control form-control-sm stk-num text-center"'
                    + '  name="' + name + '[unit_price]" value="' + fixed(product.price) + '"></td>'

                    + '<td class="text-center"><span class="badge badge-light-info stk-num">' + fixed(product.system) + '</span>'
                    + '  <input type="hidden" name="' + name + '[quantity_in_system]" value="' + fixed(product.system) + '"></td>'

                    + '<td><div class="stk-stepper">'
                    + '  <button type="button" class="stk-stepper__btn" data-step="-1" aria-label="إنقاص">'
                    + '    <i class="feather icon-minus"></i></button>'
                    + '  <input type="number" step="0.01" min="0" required class="stk-stepper__input stk-num qty-input"'
                    + '    name="' + name + '[quantity_in_stock]" value="' + fixed(actual) + '">'
                    + '  <button type="button" class="stk-stepper__btn" data-step="1" aria-label="زيادة">'
                    + '    <i class="feather icon-plus"></i></button>'
                    + '</div></td>'

                    + '<td class="text-center"><span class="stk-move stk-move--muted difference-cell">'
                    + '<span class="stk-num">0.00</span></span></td>'

                    + '<td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger remove-row"'
                    + '  aria-label="حذف الصنف"><i class="feather icon-trash-2"></i></button></td>'
                    + '</tr>'
                );

                updateDifference($('#products-body tr:last'));
                return true;
            }

            if ($.fn.select2) {
                $('#productSelect').select2({
                    dir: 'rtl', width: '100%', placeholder: 'اختر الصنف…',
                    dropdownParent: $('#addProductModal'),
                }).on('change', function () {
                    const product = byId[this.value];
                    $('#unitPrice').val(product ? fixed(product.price) : '');
                });
            }

            /*
                إغلاق النافذة: النسخة jQuery من `.modal('hide')` لا تُغلق نافذةً
                فُتحت بواجهة Bootstrap 5 الأصليّة، فتبقى الطبقة الرماديّة
                (backdrop) فوق الشاشة ويظنّها المستخدم «تحميلاً عالقاً».
                نُغلق بالواجهة الأصليّة ثم ننظّف أي بقايا.
            */
            function closeModal(selector) {
                const element = document.querySelector(selector);
                if (! element) return;

                if (window.bootstrap && bootstrap.Modal) {
                    (bootstrap.Modal.getInstance(element) || new bootstrap.Modal(element)).hide();
                } else if ($.fn.modal) {
                    $(element).modal('hide');
                }

                setTimeout(function () {
                    if (document.querySelector('.modal.show')) return;

                    document.querySelectorAll('.modal-backdrop').forEach(node => node.remove());
                    document.body.classList.remove('modal-open');
                    document.body.style.removeProperty('overflow');
                    document.body.style.removeProperty('padding-right');
                }, 400);
            }

            $('#addProductBtn').on('click', function () {
                const id = $('#productSelect').val();
                const quantity = $('#realQty').val();

                if (! id) return toast('اختر صنفاً أولاً', 'error');
                if (quantity === '' || isNaN(quantity) || parseFloat(quantity) < 0) {
                    return toast('أدخل كمية صحيحة غير سالبة', 'error');
                }

                if (! addRow(byId[id], quantity, $('#modalNote').val())) {
                    return toast('الصنف مضاف سلفاً', 'warning');
                }

                $('#productSelect').val('').trigger('change');
                $('#realQty').val('');
                $('#unitPrice').val('');
                $('#modalNote').val('');
                closeModal('#addProductModal');

                renumber();
                refreshStats();
            });

            $('#addAllBtn').on('click', function () {
                let added = 0, skipped = 0;

                products.forEach(product => addRow(product, null, '') ? added++ : skipped++);

                renumber();
                refreshStats();

                toast(
                    'أُضيف ' + added + ' صنفاً' + (skipped ? ' — وتُخطّي ' + skipped + ' مضافاً سلفاً' : ''),
                    added ? 'success' : 'info'
                );
            });

            $('#products-body').on('click', '.remove-row', function () {
                $(this).closest('tr').remove();
                renumber();
                refreshStats();
            });

            $('#products-body').on('click', '.stk-stepper__btn', function () {
                const input = $(this).closest('.stk-stepper').find('.qty-input');
                const step = parseInt($(this).data('step'), 10);
                const next = (parseFloat(input.val()) || 0) + step;

                input.val(fixed(Math.max(0, next)));
                updateDifference($(this).closest('tr'));
                refreshStats();
            });

            $('#products-body').on('input', '.qty-input', function () {
                if (parseFloat(this.value) < 0) this.value = 0;
                updateDifference($(this).closest('tr'));
                refreshStats();
            });

            $('#final-form').on('submit', function (e) {
                if (! $('#products-body tr').length) {
                    e.preventDefault();
                    toast('أضف أصنافاً قبل حفظ الجرد', 'error');
                }
            });

            refreshStats();
        });
    </script>
@endsection
