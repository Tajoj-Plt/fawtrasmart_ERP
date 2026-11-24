{{--
    نموذج الإذن المخزني — مشترك بين ثماني شاشات (إضافة، صرف، تحويل، رصيد
    افتتاحي × إنشاء/تعديل)، نفس نمط
    Stock\StorePermitsManagementController الفعلي — نسخة مطابقة له لهذا
    الموديول بدل ثمانية ملفاتٍ مكرَّرة نحو 250 سطراً لكلٍّ منها.

    الوسائط:
      $type       نوع الإذن (1 إضافة، 2 صرف، 3 تحويل، 4 رصيد افتتاحي)
      $action     وجهة الحفظ
      $permit     السجلّ عند التعديل (اختياري)
      $stores     'single' لمخزنٍ واحد، 'pair' لمخزنَي «من» و«إلى»
      $items      بنود محفوظة عند التعديل (اختياري)
      $subAccount هل تُعرض قائمة الحساب الفرعي؟ (اختياري)
--}}

@php
    $permit = $permit ?? null;
    $items = $items ?? collect();
    $stores = $stores ?? 'single';
    $subAccount = $subAccount ?? false;

    $isEdit = (bool) $permit;

    $defaultDate = $isEdit && $permit->permission_date
        ? \Illuminate\Support\Carbon::parse($permit->permission_date)->format('Y-m-d\TH:i')
        : now()->format('Y-m-d\TH:i');

    // إذن الصرف والتحويل يُنقصان الرصيد؛ الإضافة والرصيد الافتتاحي يزيدانه
    $decreases = in_array($type, [2, 3], true);
@endphp

{{--
    `novalidate` مقصود:

    قوائم المستودع والأصناف من نوع select2، وselect2 يُخفي العنصر الأصلي.
    والمتصفّح حين ينقر المستخدم «حفظ» يتحقّق أوّلاً من الحقول الإلزامية،
    فيجد حقلاً `required` فارغاً **لا يمكن تركيز المؤشّر عليه** لأنه مخفيّ،
    فيُلغي الإرسال بصمتٍ ولا يُطلق حدث submit أصلاً: لا رسالة ولا حفظ.
    صار التحقّق كلّه لنا: فحصٌ في المتصفّح برسالةٍ ظاهرة في الصفحة،
    وتحقّقٌ في الخادم تظهر أخطاؤه فوق النموذج.
--}}
@include('onlinestore::components.form-errors')

{{--
    لا مخزنَ متاحاً: المنسدلةُ مخازنُ المتجر النشطة داخل نطاق المستخدم وفرع
    الشريط. تُقال صراحةً بدل نموذجٍ بمنسدلةٍ فارغة لا تفسّر نفسها.
--}}
@if ($storeHouses->isEmpty())
    <div class="alert alert-warning" role="alert">
        <i class="feather icon-alert-triangle"></i>
        لا مخازن للمتجر متاحة لك في الفرع المختار — بدّل الفرع من الشريط العلوي، أو أضف مخزناً لهذا الفرع من شاشة «المخازن».
    </div>
@endif

<div class="alert alert-danger" id="permitClientErrors" role="alert" hidden></div>

<form id="permitForm" action="{{ $action }}" method="POST" enctype="multipart/form-data" novalidate>
    @csrf
    <input type="hidden" name="permission_type" value="{{ $type }}">

    <div class="card">
        <div class="stk-table-head">
            <h6 class="stk-table-head__title"><i class="feather icon-info"></i> بيانات الإذن</h6>
            <span class="badge badge-light-{{ $decreases ? 'danger' : 'success' }}">{{ $typeLabel }}</span>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4 col-sm-6">
                    <div class="form-group">
                        <label for="number">رقم الإذن</label>
                        {{--
                            رقم الإذن حقلٌ تلقائي. كان مفتوحاً للكتابة، فيتخطّى
                            المستخدم التسلسل أو يعيد استعمال رقم إذنٍ محذوف —
                            والرقم هو ما يربط الإذن بقيده المحاسبي وبالمطبوعات.
                            صار للقراءة فقط، والخادم يُصدره عند الحفظ ويُهمل ما
                            يصل من هنا.
                        --}}
                        <input type="text" id="number" name="number" readonly tabindex="-1"
                               class="form-control text-center stk-num stk-locked"
                               value="{{ $isEdit ? $permit->number : ($serial_number ?? '') }}">
                        <span class="stk-help stk-help--locked">
                            <i class="feather icon-lock"></i>
                            @if ($isEdit)
                                رقم الإذن لا يُعدَّل بعد إصداره
                            @else
                                يُصدره النظام عند الحفظ من إعدادات الترقيم
                            @endif
                        </span>
                    </div>
                </div>

                <div class="col-md-4 col-sm-6">
                    <div class="form-group">
                        <label for="permission_date" class="stk-req">التاريخ والوقت</label>
                        <input type="datetime-local" id="permission_date" name="permission_date" required
                               class="form-control" value="{{ old('permission_date', $defaultDate) }}">
                    </div>
                </div>

                @if ($stores === 'pair')
                    <div class="col-md-4 col-sm-6">
                        <div class="form-group">
                            <label for="from_store_houses_id" class="stk-req">من مستودع</label>
                            <select id="from_store_houses_id" name="from_store_houses_id" data-required="1"
                                    class="form-control select2 js-store">
                                <option value="">اختر المستودع…</option>
                                @foreach ($storeHouses as $storeHouse)
                                    <option value="{{ $storeHouse->id }}"
                                        {{ old('from_store_houses_id', $isEdit ? $permit->from_store_houses_id : null) == $storeHouse->id ? 'selected' : '' }}>
                                        {{ $storeHouse->name }}{{ $storeHouse->branch ? ' — '.$storeHouse->branch->name : '' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="col-md-4 col-sm-6">
                        <div class="form-group">
                            <label for="to_store_houses_id" class="stk-req">إلى مستودع</label>
                            <select id="to_store_houses_id" name="to_store_houses_id" data-required="1" class="form-control select2">
                                <option value="">اختر المستودع…</option>
                                @foreach ($storeHouses as $storeHouse)
                                    <option value="{{ $storeHouse->id }}"
                                        {{ old('to_store_houses_id', $isEdit ? $permit->to_store_houses_id : null) == $storeHouse->id ? 'selected' : '' }}>
                                        {{ $storeHouse->name }}{{ $storeHouse->branch ? ' — '.$storeHouse->branch->name : '' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                @else
                    <div class="col-md-4 col-sm-6">
                        <div class="form-group">
                            <label for="store_houses_id" class="stk-req">المستودع</label>
                            <select id="store_houses_id" name="store_houses_id" data-required="1"
                                    class="form-control select2 js-store">
                                @foreach ($storeHouses as $storeHouse)
                                    <option value="{{ $storeHouse->id }}"
                                        {{ old('store_houses_id', $isEdit ? $permit->store_houses_id : request('warehouse')) == $storeHouse->id ? 'selected' : '' }}>
                                        {{ $storeHouse->name }}{{ $storeHouse->branch ? ' — '.$storeHouse->branch->name : '' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                @endif

                @if ($subAccount)
                    <div class="col-md-4 col-sm-6">
                        <div class="form-group">
                            <label for="sub_account">الحساب الفرعي</label>
                            <select id="sub_account" name="sub_account" class="form-control">
                                @foreach ([1 => 'الحساب الفرعي 1', 2 => 'الحساب الفرعي 2'] as $value => $label)
                                    <option value="{{ $value }}"
                                        {{ old('sub_account', $isEdit ? $permit->sub_account : null) == $value ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                @endif

                <div class="col-md-6">
                    <div class="form-group mb-0">
                        <label for="details">الملاحظات</label>
                        <textarea id="details" name="details" class="form-control"
                                  rows="2">{{ old('details', $isEdit ? $permit->details : '') }}</textarea>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="form-group mb-0">
                        <label for="attachments">المرفقات</label>
                        <input type="file" id="attachments" name="attachments" class="form-control">
                        @if ($isEdit && $permit->attachments)
                            <span class="stk-help">
                                <a href="{{ asset('assetss/uploads/warehouse/' . $permit->attachments) }}" target="_blank">
                                    <i class="feather icon-paperclip"></i> المرفق الحالي
                                </a>
                            </span>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ══════════ البنود ══════════ --}}
    <div class="card stk-table-card">
        <div class="stk-table-head">
            <h6 class="stk-table-head__title">
                <i class="feather icon-package"></i> بنود الإذن
                <span class="stk-count" id="rowsCount">{{ $items->count() }}</span>
            </h6>

            <div class="d-flex align-items-center" style="gap:.6rem">
                <span class="stk-balance stk-num" id="grandTotalText">0.00</span>
                <button type="button" class="btn btn-sm btn-outline-primary" id="addRow">
                    <i class="feather icon-plus"></i> إضافة بند
                </button>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover mb-0" id="itemsTable">
                <thead>
                    <tr>
                        <th style="width:28%">البند</th>
                        <th class="text-center" style="width:13%">سعر الوحدة ({{ App\Support\CurrencySettings::code() }})</th>
                        <th class="text-center" style="width:13%">الكمية</th>
                        <th class="text-center" style="width:12%">الرصيد قبل</th>
                        <th class="text-center" style="width:12%">الرصيد بعد</th>
                        <th class="text-center" style="width:14%">الإجمالي ({{ App\Support\CurrencySettings::code() }})</th>
                        <th class="text-center" style="width:60px">حذف</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($items as $item)
                        <tr>
                            <td>
                                <select name="product_id[]" class="form-control select2 product-select" data-required="1">
                                    <option value="">اختر البند…</option>
                                    @foreach ($products as $product)
                                        <option value="{{ $product->id }}"
                                                data-price="{{ $product->averageCost() ?? $product->purchase_price ?? 0 }}"
                                                {{ $item->product_id == $product->id ? 'selected' : '' }}>
                                            {{ $product->name }}
                                        </option>
                                    @endforeach
                                </select>
                                <span class="stk-avail" data-state="idle">
                                    المتاح في المستودع:
                                    <strong class="stk-num stk-avail__value">—</strong>
                                    <span class="stk-avail__note"></span>
                                </span>
                            </td>
                            <td>
                                <input type="number" step="0.01" readonly name="unit_price[]"
                                       class="form-control stk-num text-center unit-price"
                                       value="{{ $item->unit_price }}">
                            </td>
                            <td>
                                <input type="number" step="0.01" min="0.01" required name="quantity[]"
                                       class="form-control stk-num text-center quantity"
                                       value="{{ $item->quantity }}">
                            </td>
                            <td class="text-center stk-num">
                                <span class="stock-before">{{ number_format($item->stock_before ?? 0, 2) }}</span>
                                <input type="hidden" name="stock_before[]" class="stock-before-input"
                                       value="{{ $item->stock_before ?? 0 }}">
                            </td>
                            <td class="text-center stk-num">
                                <span class="stock-after">{{ number_format($item->stock_after ?? 0, 2) }}</span>
                                <input type="hidden" name="stock_after[]" class="stock-after-input"
                                       value="{{ $item->stock_after ?? 0 }}">
                            </td>
                            <td>
                                <input type="number" step="0.01" readonly name="total[]"
                                       class="form-control stk-num text-center total" value="{{ $item->total }}">
                            </td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-outline-danger js-remove"
                                        aria-label="حذف البند"><i class="feather icon-trash-2"></i></button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="card-body" id="itemsEmpty" {{ $items->count() ? 'hidden' : '' }}>
            <div class="stk-empty">
                <div class="stk-empty__icon"><i class="feather icon-package"></i></div>
                <h5 class="stk-empty__title">لا بنود بعد</h5>
                <p class="stk-empty__hint">اضغط «إضافة بند» لإضافة أول صنف</p>
            </div>
        </div>

        <div class="stk-form-foot">
            <span class="stk-help me-auto">
                الإجمالي: <strong class="stk-num" id="grandTotalFoot">0.00</strong>
                {{ App\Support\CurrencySettings::code() }}
            </span>
            <input type="hidden" name="grand_total" id="grand_total"
                   value="{{ $isEdit ? $permit->grand_total : 0 }}">

            <a href="{{ route('online_store.permits.index') }}" class="btn btn-outline-secondary">
                <i class="feather icon-x"></i> إلغاء
            </a>
            <button type="submit" class="btn btn-primary">
                <i class="feather icon-check"></i> {{ $isEdit ? 'حفظ التعديلات' : 'حفظ الإذن' }}
            </button>
        </div>
    </div>
</form>

@push('scripts')
    <script>
        $(function () {
            @php
                $productOptions = collect($products)->map(fn ($p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'price' => (float) ($p->averageCost() ?? $p->purchase_price ?? 0),
                ])->values();

                // أصناف البنود المحفوظة (وضع التعديل) — تُضاف دائماً إلى قائمة
                // إذن التحويل ولو نفد رصيدها من المستودع، كي لا يُفرَّغ بندٌ محفوظ.
                $savedProductOptions = ($items ?? collect())
                    ->map(fn ($it) => $it->product)
                    ->filter()
                    ->unique('id')
                    ->map(fn ($p) => [
                        'id' => $p->id,
                        'name' => $p->name,
                        'price' => (float) ($p->averageCost() ?? $p->purchase_price ?? 0),
                    ])->values();
            @endphp

            const products = @json($productOptions);
            const savedProducts = @json($savedProductOptions);
            // رصيد الصنف من مسار المتجر نفسه (نطاقُ مخازن المتجر) — لا مسار المخزون العام
            const stockUrl = @json(route('online_store.permits.product_stock', ['storeId' => '__STORE__', 'productId' => '__PRODUCT__']));
            const decreases = @json($decreases);

            // إذن التحويل وحده: قائمة البنود تنحصر بأصناف المستودع المصدر
            // بعد اختياره, لا كل أصناف النظام. باقي الأذون تعرض كل الأصناف.
            const scopeToStore = @json((int) $type === 3);
            const productsByStoreUrl = @json(route('online_store.permits.products_by_store', ['storeId' => '__STORE__']));

            // يضمّ الأصناف المحفوظة إلى قائمةٍ واردة بلا تكرار
            function withSavedProducts(list) {
                const merged = list.slice();
                savedProducts.forEach(function (sp) {
                    if (! merged.some(p => String(p.id) === String(sp.id))) merged.push(sp);
                });
                return merged;
            }

            const esc = value => $('<div>').text(value == null ? '' : value).html();
            const fixed = value => Number(value || 0).toFixed(2);

            // القائمة المعروضة حالياً — كل الأصناف افتراضياً، أو أصناف المستودع
            // المصدر وحدها في إذن التحويل (تبدأ فارغة حتى يُختار «من مستودع»).
            let currentProducts = scopeToStore ? withSavedProducts([]) : products.slice();

            function buildOptionsHtml(list) {
                return '<option value="">اختر البند…</option>'
                    + list.map(p => '<option value="' + p.id + '" data-price="' + p.price + '">'
                        + esc(p.name) + '</option>').join('');
            }

            let productOptionsHtml = buildOptionsHtml(currentProducts);

            // يعيد بناء خيارات كل صفوف «البند» بالقائمة الحالية، محافظاً على
            // اختيار الصفّ إن بقي صنفه ضمنها، وإلا يُفرَّغ الاختيار ومشتقّاته.
            function applyProductOptions() {
                $('#itemsTable tbody tr').each(function () {
                    const $row = $(this);
                    const $sel = $row.find('.product-select');
                    const current = $sel.val();
                    const stillValid = current && currentProducts.some(p => String(p.id) === String(current));

                    $sel.html(productOptionsHtml).val(stillValid ? current : '');
                    if ($.fn.select2) $sel.trigger('change.select2');

                    if (! stillValid) {
                        $row.data('avail', null);
                        $row.find('.unit-price').val('');
                        setAvail($row, 'idle', '—');
                    }
                });

                refresh();
            }

            // إذن التحويل فقط: يجلب أصناف المستودع المصدر ويحدّث القوائم.
            function reloadProductsForStore(storeId) {
                if (! scopeToStore) return;

                if (! storeId) {
                    currentProducts = withSavedProducts([]);
                    productOptionsHtml = buildOptionsHtml(currentProducts);
                    applyProductOptions();
                    return;
                }

                $.getJSON(productsByStoreUrl.replace('__STORE__', storeId))
                    .done(function (response) {
                        currentProducts = withSavedProducts((response && response.products) || []);
                        productOptionsHtml = buildOptionsHtml(currentProducts);
                        applyProductOptions();
                    })
                    .fail(function () {
                        currentProducts = withSavedProducts([]);
                        productOptionsHtml = buildOptionsHtml(currentProducts);
                        applyProductOptions();
                    });
            }

            function refresh() {
                let total = 0;

                $('#itemsTable tbody tr').each(function () {
                    const $row = $(this);
                    const price = parseFloat($row.find('.unit-price').val()) || 0;
                    const qty = parseFloat($row.find('.quantity').val()) || 0;
                    const line = price * qty;

                    $row.find('.total').val(fixed(line));
                    total += line;

                    const before = parseFloat($row.find('.stock-before-input').val()) || 0;
                    const after = decreases ? before - qty : before + qty;

                    $row.find('.stock-after-input').val(after);
                    $row.find('.stock-after')
                        .text(fixed(after))
                        .toggleClass('text-danger', after < 0);

                    const avail = $row.data('avail');
                    const short = decreases && avail != null && qty > avail;

                    $row.find('.quantity').toggleClass('is-invalid', short);

                    if (avail != null) {
                        setAvail($row, short ? 'short' : 'ready', fixed(avail),
                            short ? 'المطلوب أكبر من المتاح' : '');
                    }
                });

                const count = $('#itemsTable tbody tr').length;
                $('#rowsCount').text(count);
                document.getElementById('itemsEmpty').hidden = count > 0;

                $('#grandTotalText, #grandTotalFoot').text(fixed(total));
                $('#grand_total').val(fixed(total));
            }

            function setAvail($row, state, text, note) {
                $row.find('.stk-avail')
                    .attr('data-state', state)
                    .find('.stk-avail__value').text(text)
                    .end()
                    .find('.stk-avail__note').text(note || '');
            }

            function loadStock($row) {
                const productId = $row.find('.product-select').val();
                const storeId = $('.js-store').val();

                if (! productId || ! storeId) {
                    $row.data('avail', null);
                    setAvail($row, 'idle', '—');
                    return;
                }

                $row.data('avail', null);
                setAvail($row, 'loading', '…');

                $.getJSON(stockUrl.replace('__STORE__', storeId).replace('__PRODUCT__', productId))
                    .done(function (response) {
                        const stock = Number(response && (response.stock ?? response.quantity)) || 0;

                        $row.data('avail', stock);
                        $row.find('.stock-before-input').val(stock);
                        $row.find('.stock-before').text(fixed(stock));
                        setAvail($row, 'ready', fixed(stock));
                        refresh();
                    })
                    .fail(function (xhr) {
                        $row.data('avail', null);
                        $row.find('.stock-before-input').val(0);
                        $row.find('.stock-before').text('؟');
                        setAvail($row, 'error', '؟',
                            (xhr.status === 403 || xhr.status === 404) ? 'المستودع خارج نطاق صلاحياتك' : 'تعذّرت قراءة الرصيد');
                        refresh();
                    });
            }

            $('#addRow').on('click', function () {
                const $row = $(
                    '<tr>'
                    + '<td><select name="product_id[]" class="form-control select2 product-select" data-required="1">'
                    + productOptionsHtml + '</select>'
                    + '<span class="stk-avail" data-state="idle">المتاح في المستودع: '
                    + '<strong class="stk-num stk-avail__value">—</strong>'
                    + '<span class="stk-avail__note"></span></span></td>'
                    + '<td><input type="number" step="0.01" readonly name="unit_price[]"'
                    + '  class="form-control stk-num text-center unit-price"></td>'
                    + '<td><input type="number" step="0.01" min="0.01" value="1" required name="quantity[]"'
                    + '  class="form-control stk-num text-center quantity"></td>'
                    + '<td class="text-center stk-num"><span class="stock-before">0.00</span>'
                    + '  <input type="hidden" name="stock_before[]" class="stock-before-input" value="0"></td>'
                    + '<td class="text-center stk-num"><span class="stock-after">0.00</span>'
                    + '  <input type="hidden" name="stock_after[]" class="stock-after-input" value="0"></td>'
                    + '<td><input type="number" step="0.01" readonly name="total[]"'
                    + '  class="form-control stk-num text-center total"></td>'
                    + '<td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger js-remove"'
                    + '  aria-label="حذف البند"><i class="feather icon-trash-2"></i></button></td>'
                    + '</tr>'
                );

                $('#itemsTable tbody').append($row);

                if ($.fn.select2) {
                    $row.find('.select2').select2({ placeholder: 'اختر…', dir: 'rtl', width: '100%' });
                }

                refresh();
            });

            $('#itemsTable').on('click', '.js-remove', function () {
                $(this).closest('tr').remove();
                refresh();
            });

            $('#itemsTable').on('change', '.product-select', function () {
                const $row = $(this).closest('tr');
                const option = this.options[this.selectedIndex];

                $row.find('.unit-price').val(fixed(option.dataset.price));
                loadStock($row);
            });

            $('#itemsTable').on('input', '.quantity, .unit-price', refresh);

            $('.js-store').on('change', function () {
                // إذن التحويل: يعيد حصر الأصناف بمستودع المصدر الجديد أولاً
                reloadProductsForStore($(this).val());
                $('#itemsTable tbody tr').each(function () { loadStock($(this)); });
            });

            if ($.fn.select2) {
                $('#permitForm .select2').select2({ placeholder: 'اختر…', dir: 'rtl', width: '100%' });
            }

            const clientErrors = document.getElementById('permitClientErrors');

            function fail(message, field) {
                if (clientErrors) {
                    clientErrors.textContent = message;
                    clientErrors.hidden = false;
                    clientErrors.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }

                $('#permitForm .is-invalid').removeClass('is-invalid');

                if (field) {
                    $(field).addClass('is-invalid');
                    if ($(field).is(':visible')) $(field).trigger('focus');
                }

                if (typeof Swal !== 'undefined') {
                    Swal.fire({ icon: 'error', title: 'بيانات ناقصة', text: message });
                }

                return false;
            }

            $('#permitForm').on('submit', function (e) {
                e.preventDefault();

                if (! $('#permission_date').val()) return fail('حدّد تاريخ الإذن.', '#permission_date');

                const single = $('#store_houses_id');
                const from = $('#from_store_houses_id');
                const to = $('#to_store_houses_id');

                if (single.length && ! single.val()) return fail('اختر المستودع.', single);

                if (from.length && ! from.val()) return fail('اختر المستودع المصدر.', from);
                if (to.length && ! to.val()) return fail('اختر المستودع الهدف.', to);

                if (from.length && to.length && from.val() === to.val()) {
                    return fail('اختر مستودعين مختلفين — لا يُحوَّل المخزون إلى نفسه.', to);
                }

                const rows = $('#itemsTable tbody tr');
                if (! rows.length) return fail('أضف بنداً واحداً على الأقل.');

                let invalid = null;

                rows.each(function () {
                    if (invalid) return;

                    const product = $(this).find('select[name="product_id[]"]');
                    const quantity = parseFloat($(this).find('input[name="quantity[]"]').val());

                    if (! product.val()) { invalid = ['اختر الصنف في كل بند.', product]; return; }
                    if (! (quantity > 0)) {
                        invalid = ['أدخل كمية أكبر من صفر في كل بند.', $(this).find('input[name="quantity[]"]')];

                        return;
                    }

                    const avail = $(this).data('avail');

                    if (decreases && avail != null && quantity > avail) {
                        const name = product.find('option:selected').text().trim();

                        invalid = [
                            'الكمية غير متوفّرة لـ«' + name + '»: المتاح ' + fixed(avail)
                                + ' والمطلوب ' + fixed(quantity) + '.',
                            $(this).find('input[name="quantity[]"]'),
                        ];
                    }
                });

                if (invalid) return fail(invalid[0], invalid[1]);

                if (clientErrors) clientErrors.hidden = true;

                $('#permitForm').find('button[type="submit"]').prop('disabled', true);
                this.submit();
            });

            @if (! $isEdit)
                $('#addRow').trigger('click');
            @else
                $('#itemsTable tbody tr').each(function () { loadStock($(this)); });
                refresh();
            @endif

            @if ((int) $type === 3)
                // إذن التحويل: حصر قائمة الأصناف بمستودع المصدر المختار حالياً
                // (فارغٌ عند الإنشاء، أو مستودع الإذن المحفوظ عند التعديل)
                reloadProductsForStore($('#from_store_houses_id').val());
            @endif
        });
    </script>
@endpush
