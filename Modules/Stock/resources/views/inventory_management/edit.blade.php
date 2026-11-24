@extends('stock::master')

@section('title', 'تنفيذ الجرد')

@section('content')
    {{--
        صُحّح هنا عطلان يوقفان الشاشة كلّها:

        1. زرّ «إضافة كل المنتجات» كان يُولّد `const productId` داخل حلقة
           `@foreach` بلوفية، فتتكرّر في النطاق نفسه — وهو خطأ نحوي يُسقط
           ملفّ الجافاسكربت بأكمله متى وُجد منتجان فأكثر، فلا يعمل شيء.

        2. نافذة «إضافة منتج» تسأل عن الكمية الفعلية والملاحظات ثم لا تقرأهما،
           فيخرج الصفّ فارغاً ويظنّ المستخدم أنه أدخلهما.

        وصار المنتجات تُمرَّر بيانات (`@json`) لا نصّاً مُقحَماً في القالب،
        فلا يكسرها اسمٌ فيه علامة اقتباس.
    --}}

    <div class="content-body">

        @include('stock::components.page-head', [
            'title' => 'تنفيذ الجرد',
            'icon' => 'check-square',
            'sub' => 'أدخل الكمّيّات الفعلية ليُحسب الفارق مع النظام',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'إدارة الجرد' => route('inventory_management.index'),
                '#' . ($adjustment->reference_number ?: $adjustment->id) => route('inventory.show', $adjustment->id),
                'تنفيذ' => null,
            ],
            'actions' => '<button type="submit" form="final-form" class="btn btn-primary">'
                . '<i class="feather icon-check"></i> حفظ الجرد</button>',
        ])

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')

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
                                <th style="width:26%">الصنف</th>
                                <th class="text-center" style="width:12%">كمية النظام</th>
                                <th class="text-center" style="width:14%">الكمية الفعلية</th>
                                <th class="text-center" style="width:10%">الفارق</th>
                                <th style="width:16%">صورة</th>
                                <th style="width:18%">ملاحظات</th>
                                <th class="text-center" style="width:6%">حذف</th>
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
                        <select id="productSelect" class="form-control">
                            <option value="">اختر الصنف…</option>
                            @foreach ($products as $product)
                                <option value="{{ $product->id }}">
                                    {{ $product->name }} ({{ $product->current_quantity }})
                                </option>
                            @endforeach
                        </select>
                        <x-create-first
                            :count="count($products)"
                            route="products.create"
                            permission="stock.products.edit"
                            :plan="\App\Constants\planFeatures\StockPlanFeatures::PRODUCTS"
                            label="منتجاً"
                            hint="الجرد يقع على أصناف، ولا أصناف مسجَّلة بعد." />
                    </div>

                    <div class="form-group">
                        <label for="realQty">الكمية الفعلية</label>
                        <input type="number" step="0.01" id="realQty" class="form-control stk-num">
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
            @php
                // القائمة تُبنى هنا لا داخل توجيه @json متعدّد الأسطر: Blade
                // لا يقرأ وسيطاً يمتدّ على أسطر داخل التوجيه
                $productPayload = collect($products)->map(fn ($p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'system' => $p->current_quantity,
                ])->values();
            @endphp

            const products = @json($productPayload);

            const byId = {};
            products.forEach(p => { byId[p.id] = p; });

            const escapeHtml = value => $('<div>').text(value == null ? '' : value).html();

            function syncState() {
                const count = $('#products-body tr').length;
                $('#rows-count').text(count);
                // `style.display` لا `hidden` — انظر التعليق المقابل في doStock
                document.getElementById('empty-state').style.display = count > 0 ? 'none' : '';
            }

            function addRow(product, quantity, note) {
                if ($('#products-body').find('[name="items[' + product.id + '][product_id]"]').length) {
                    return false;   // الصنف مضاف سلفاً
                }

                const name = 'items[' + product.id + ']';

                $('#products-body').append(
                    '<tr>'
                    + '<td><span class="stk-media__title">' + escapeHtml(product.name) + '</span>'
                    + '  <input type="hidden" name="' + name + '[product_id]" value="' + product.id + '"></td>'
                    + '<td class="text-center stk-num">' + product.system
                    + '  <input type="hidden" name="' + name + '[quantity_in_system]" value="' + product.system + '"></td>'
                    + '<td><input type="number" step="0.01" required class="form-control stk-num qty-input"'
                    + '  name="' + name + '[quantity_in_stock]" value="' + (quantity != null ? quantity : '') + '"></td>'
                    + '<td class="text-center"><span class="stk-move stk-move--muted difference-cell">'
                    + '<span class="stk-num">0</span></span></td>'
                    + '<td><input type="file" class="form-control" name="' + name + '[image]"></td>'
                    + '<td><textarea class="form-control" rows="1" name="' + name + '[note]">'
                    + escapeHtml(note || '') + '</textarea></td>'
                    + '<td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger remove-row"'
                    + '  aria-label="حذف الصنف"><i class="feather icon-trash-2"></i></button></td>'
                    + '</tr>'
                );

                syncState();

                // احسب الفارق فوراً إن جاءت الكمية مع الصفّ
                if (quantity != null && quantity !== '') {
                    $('#products-body tr:last .qty-input').trigger('input');
                }

                return true;
            }

            $('#addProductBtn').on('click', function () {
                const id = $('#productSelect').val();
                if (! id) return;

                const added = addRow(byId[id], $('#realQty').val(), $('#modalNote').val());

                $('#productSelect').val('');
                $('#realQty').val('');
                $('#modalNote').val('');
                $('#addProductModal').modal('hide');

                if (! added && typeof Swal !== 'undefined') {
                    Swal.fire({ icon: 'info', title: 'الصنف مضاف سلفاً', timer: 1600, showConfirmButton: false });
                }
            });

            $('#addAllBtn').on('click', function () {
                products.forEach(product => addRow(product, null, ''));
            });

            $('#products-body').on('click', '.remove-row', function () {
                $(this).closest('tr').remove();
                syncState();
            });

            /* الفارق: موجبٌ زيادة وسالبٌ عجز — واللون والإشارة معاً */
            $('#products-body').on('input', '.qty-input', function () {
                const row = $(this).closest('tr');
                const system = parseFloat(row.find('[name*="quantity_in_system"]').val()) || 0;
                const actual = parseFloat(this.value);

                const cell = row.find('.difference-cell');

                if (isNaN(actual)) {
                    cell.attr('class', 'stk-move stk-move--muted difference-cell').find('.stk-num').text('0');
                    return;
                }

                const difference = actual - system;
                const tone = difference > 0 ? 'success' : (difference < 0 ? 'danger' : 'muted');

                cell.attr('class', 'stk-move stk-move--' + tone + ' difference-cell')
                    .find('.stk-num')
                    .text(difference > 0 ? '+' + difference : difference);
            });

            syncState();
        });
    </script>
@endsection
