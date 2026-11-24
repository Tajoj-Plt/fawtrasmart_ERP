@php
    // $product متاح فقط في شاشة التعديل — انظر تمريره من StoreProductController::edit()
    $initialOptions = isset($product) ? $product->options->map(fn ($o) => [
        'id' => $o->id,
        'name' => $o->name,
        'label_ar' => $o->label_ar,
        'values' => $o->values->sortBy('position')->values()->map(fn ($v) => [
            'id' => $v->id,
            'value' => $v->value,
            'label_ar' => $v->label_ar,
            'color_code' => $v->color_code,
        ])->values(),
    ])->values() : collect();

    $initialVariants = isset($product) ? $product->variants->map(function ($v) {
        $optionSelections = [];
        foreach ($v->optionValues as $ov) {
            if ($ov->option) {
                $optionSelections[$ov->option->id] = $ov->id;
            }
        }

        return [
            'id' => $v->id,
            'sku' => $v->sku,
            'barcode' => $v->barcode,
            'price_modifier' => $v->price_modifier,
            'override_price' => $v->override_price,
            'compare_at_price' => $v->compare_at_price,
            'cost_price' => $v->cost_price,
            'stock_quantity' => $v->stock_quantity,
            'is_active' => $v->is_active,
            'image_url' => $v->image_url ? asset($v->image_url) : null,
            // مفاتيح option_selections هنا معرّفات ProductOption/ProductOptionValue
            // الحقيقية (ثابتة في وضع التعديل)، لا uid العميل المؤقت — تُطابَق بها
            // عند بناء الحالة الأولية في JS.
            'option_selections' => $optionSelections,
        ];
    })->values() : collect();

    // مبنية هنا كمتغيّر مستقل بدل تمريرها مباشرة داخل @json(...) لأن موجّه
    // @json يقسّم تعبيره على الفواصل حرفياً (explode(',', ...))، فأي فاصلة
    // داخل مصفوفة متعددة المفاتيح تكسر الناتج المُصرَّف.
    $optionTemplatesForJs = collect($optionTemplates)->map(fn ($t) => [
        'name' => $t['name'],
        'label_ar' => $t['label_ar'],
        'values' => collect($t['values'])->map(fn ($v) => [
            'value' => $v['value'],
            'label_ar' => $v['label_ar'],
            'color_code' => $v['color_code'] ?? null,
        ]),
    ]);
@endphp

<div class="card" id="ovCard">
    <div class="stk-table-head">
        <h6 class="stk-table-head__title"><i class="feather icon-sliders"></i> الخيارات والمتغيرات</h6>
    </div>
    <div class="card-body">
        <p class="text-muted small mb-3">
            الخيارات (كاللون والمقاس) تتيح للعميل اختيار نسخة محددة من المنتج. اختر خياراً جاهزاً من القائمة أو أنشئ
            خياراً مخصصاً، أضف قيمه، ثم ولّد المتغيرات تلقائياً من كل التركيبات الممكنة وحدّد لكل متغير سعره ومخزونه.
        </p>

        <div id="ovOptionsContainer"></div>

        <div class="stk-empty" id="ovNoOptionsMsg">
            <i class="feather icon-tag"></i>
            <p class="mb-0">لا توجد خيارات بعد.</p>
        </div>

        <button type="button" class="btn btn-sm btn-outline-primary mt-2" id="ovAddOptionBtn">
            <i class="feather icon-plus"></i> إضافة خيار
        </button>

        <hr>

        <div class="d-flex align-items-center justify-content-between mb-2 flex-wrap" style="gap:.5rem;">
            <h6 class="mb-0"><i class="feather icon-grid"></i> المتغيرات</h6>
            <div>
                <button type="button" class="btn btn-sm btn-outline-success" id="ovGenerateVariantsBtn" disabled>
                    <i class="feather icon-refresh-cw"></i> توليد المتغيرات من الخيارات
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="ovAddVariantBtn">
                    <i class="feather icon-plus"></i> إضافة متغير يدوياً
                </button>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table stk-table align-middle" id="ovVariantsTable" style="display:none;">
                <thead>
                <tr>
                    <th style="width:64px">الصورة</th>
                    <th style="min-width:180px">الخيارات</th>
                    <th style="min-width:140px">SKU</th>
                    <th style="min-width:120px">الباركود</th>
                    <th style="width:110px">فرق السعر</th>
                    <th style="width:110px">سعر مخصص</th>
                    <th style="width:110px">سعر قبل الخصم</th>
                    <th style="width:110px">التكلفة</th>
                    <th style="width:100px" title="حقل وصفي فقط — المتغيّرات لا تحمل مخزوناً: المتاح في المتجر هو رصيد المنتج الأساسي في مستودع المتجر">المخزون <i class="feather icon-info text-muted"></i></th>
                    <th style="width:60px">مفعّل</th>
                    <th style="width:44px"></th>
                </tr>
                </thead>
                <tbody id="ovVariantsTbody"></tbody>
            </table>
        </div>
        <div class="stk-empty" id="ovNoVariantsMsg">
            <i class="feather icon-box"></i>
            <p class="mb-0">لا توجد متغيرات بعد. أضف خيارات ثم اضغط «توليد المتغيرات».</p>
        </div>

        <div id="ovHiddenDeletes"></div>
    </div>
</div>

<style>
    .ov-option-card { border: 1px solid var(--app-border); border-radius: .5rem; padding: 1rem; margin-bottom: 1rem; background: var(--app-surface, #fff); }
    .ov-chip { display: inline-flex; align-items: center; gap: .35rem; padding: .25rem .5rem .25rem .35rem; border-radius: 999px; font-size: .8rem; font-weight: 500; color: var(--app-body); background: var(--app-surface2, rgba(127,127,127,.12)); border: 1px solid var(--app-border); }
    .ov-chip .ov-chip-remove { cursor: pointer; color: var(--app-danger, #dc3545); font-weight: 700; line-height: 1; padding: 0 .2rem; }
    .ov-variant-options { display: flex; flex-direction: column; gap: .25rem; }
    .ov-variant-options select { font-size: .78rem; padding: .15rem .4rem; height: auto; }
    .ov-swatch { display: inline-block; width: 14px; height: 14px; border-radius: 50%; border: 1px solid rgba(0,0,0,.15); flex-shrink: 0; }
    .ov-new-value-color { width: 34px; height: 31px; padding: 2px; border: 1px solid var(--app-border); border-radius: .35rem; cursor: pointer; }
</style>

<script>
window.__ovTemplates = @json($optionTemplatesForJs);
window.__ovInitialOptions = @json($initialOptions);
window.__ovInitialVariants = @json($initialVariants);
</script>

@push('scripts')
<script>
(function ($) {
    'use strict';

    var templates = (window.__ovTemplates || []).slice();
    var state = { options: [], variants: [] };
    var deletedOptionIds = [];
    var deletedOptionValueIds = [];
    var deletedVariantIds = [];
    var uidCounter = 0;

    function nextUid(prefix) {
        uidCounter++;
        return prefix + uidCounter + '_' + Math.random().toString(36).slice(2, 7);
    }

    function escapeHtml(str) {
        return $('<div>').text(str === null || str === undefined ? '' : String(str)).html();
    }

    function slugify(str) {
        var s = (str || '').toString().trim().toLowerCase()
            .replace(/[^a-z0-9؀-ۿ]+/g, '_')
            .replace(/^_+|_+$/g, '');
        return s || ('opt_' + Math.random().toString(36).slice(2, 8));
    }

    // يمنع تصادم "name" (فريد ضمن المنتج في قاعدة البيانات) بين خيارين
    // بنفس التسمية أو نفس الخيار الجاهز المختار داخل هذا المنتج نفسه.
    function uniqueOptionName(candidate, selfUid) {
        var existing = state.options.filter(function (o) { return o._uid !== selfUid; }).map(function (o) { return o.name; });
        if (existing.indexOf(candidate) === -1) return candidate;
        var i2 = 2;
        while (existing.indexOf(candidate + '_' + i2) !== -1) i2++;
        return candidate + '_' + i2;
    }

    // ------- تهيئة الحالة الأولية (فارغة في الإضافة، مُحمَّلة من المنتج في التعديل) -------
    var serverOptionIdToUid = {};
    var serverValueIdToUid = {};

    (window.__ovInitialOptions || []).forEach(function (o) {
        var optUid = nextUid('o');
        serverOptionIdToUid[o.id] = optUid;

        var values = (o.values || []).map(function (v) {
            var valUid = nextUid('v');
            serverValueIdToUid[v.id] = valUid;
            return { _uid: valUid, id: v.id, value: v.value, label_ar: v.label_ar, color_code: v.color_code || null };
        });

        state.options.push({
            _uid: optUid, id: o.id, name: o.name, label_ar: o.label_ar,
            template_name: null, values: values,
        });
    });

    (window.__ovInitialVariants || []).forEach(function (v) {
        var selections = {};
        Object.keys(v.option_selections || {}).forEach(function (optId) {
            var valId = v.option_selections[optId];
            var optUid = serverOptionIdToUid[optId];
            var valUid = serverValueIdToUid[valId];
            if (optUid && valUid) selections[optUid] = valUid;
        });

        state.variants.push({
            _uid: nextUid('v'), id: v.id, sku: v.sku, barcode: v.barcode,
            price_modifier: v.price_modifier, override_price: v.override_price,
            compare_at_price: v.compare_at_price, cost_price: v.cost_price,
            stock_quantity: v.stock_quantity, is_active: !!v.is_active,
            image_url: v.image_url, _pendingFile: null,
            option_selections: selections,
        });
    });

    // ------------------------------- العرض -------------------------------

    function renderOptions() {
        var $c = $('#ovOptionsContainer');
        $c.empty();

        state.options.forEach(function (opt, i) {
            var tplOptionsHtml = '<option value="">— خيار جاهز أو خيار مخصص —</option>';
            templates.forEach(function (t) {
                var sel = opt.template_name === t.name ? 'selected' : '';
                tplOptionsHtml += '<option value="' + escapeHtml(t.name) + '" ' + sel + '>' + escapeHtml(t.label_ar) + '</option>';
            });

            var $card = $(
                '<div class="ov-option-card" data-uid="' + opt._uid + '">' +
                    '<div class="row align-items-end">' +
                        '<div class="col-md-3">' +
                            '<label class="small mb-1">من خيار جاهز</label>' +
                            '<select class="form-control form-control-sm ov-template-select">' + tplOptionsHtml + '</select>' +
                        '</div>' +
                        '<div class="col-md-3">' +
                            '<label class="small mb-1 stk-req">اسم الخيار</label>' +
                            '<input type="text" class="form-control form-control-sm ov-option-label" name="options[' + i + '][label_ar]" value="' + escapeHtml(opt.label_ar || '') + '" placeholder="مثال: اللون">' +
                            '<input type="hidden" name="options[' + i + '][id]" value="' + (opt.id || '') + '">' +
                            '<input type="hidden" class="ov-option-name-hidden" name="options[' + i + '][name]" value="' + escapeHtml(opt.name || '') + '">' +
                        '</div>' +
                        '<div class="col-md-4">' +
                            '<label class="small mb-1">إضافة قيمة</label>' +
                            '<div class="input-group input-group-sm">' +
                                '<input type="text" class="form-control ov-new-value-input" placeholder="مثال: أحمر">' +
                                '<div class="input-group-append">' +
                                    '<button type="button" class="btn btn-outline-primary ov-add-value-btn" title="إضافة قيمة"><i class="feather icon-plus"></i></button>' +
                                '</div>' +
                            '</div>' +
                        '</div>' +
                        '<div class="col-md-1 px-0 text-center">' +
                            '<label class="small mb-1 d-block">اللون</label>' +
                            '<input type="color" class="ov-new-value-color" value="#7367f0" data-set="0" title="لونٌ اختياري لهذه القيمة — اترك بلا لمسٍ لتجاهله">' +
                        '</div>' +
                        '<div class="col-md-1 text-left">' +
                            '<button type="button" class="btn btn-outline-danger btn-sm ov-remove-option-btn" title="حذف الخيار"><i class="feather icon-trash-2"></i></button>' +
                        '</div>' +
                    '</div>' +
                    '<div class="ov-values-chips mt-3 d-flex flex-wrap" style="gap:8px;"></div>' +
                '</div>'
            );

            var $chips = $card.find('.ov-values-chips');
            opt.values.forEach(function (val, k) {
                var swatch = val.color_code
                    ? '<span class="ov-swatch" style="background:' + escapeHtml(val.color_code) + '"></span>'
                    : '';
                $chips.append(
                    '<span class="ov-chip">' +
                        '<input type="hidden" name="options[' + i + '][values][' + k + '][id]" value="' + (val.id || '') + '">' +
                        '<input type="hidden" name="options[' + i + '][values][' + k + '][value]" value="' + escapeHtml(val.value) + '">' +
                        '<input type="hidden" name="options[' + i + '][values][' + k + '][label_ar]" value="' + escapeHtml(val.label_ar) + '">' +
                        '<input type="hidden" name="options[' + i + '][values][' + k + '][color_code]" value="' + escapeHtml(val.color_code || '') + '">' +
                        swatch +
                        '<span>' + escapeHtml(val.label_ar) + '</span>' +
                        '<span class="ov-chip-remove" data-uid="' + val._uid + '" title="حذف القيمة">&times;</span>' +
                    '</span>'
                );
            });

            $c.append($card);
        });

        $('#ovNoOptionsMsg').toggle(state.options.length === 0);
        $('#ovGenerateVariantsBtn').prop('disabled', state.options.length === 0 || state.options.some(function (o) { return o.values.length === 0; }));
    }

    function renderVariants() {
        var $tbody = $('#ovVariantsTbody');
        $tbody.empty();

        state.variants.forEach(function (v, j) {
            var optionsHtml = '';
            state.options.forEach(function (opt, optIndex) {
                var selectedValueUid = v.option_selections[opt._uid] || '';
                var html = '<option value="">' + escapeHtml(opt.label_ar || opt.name) + ': —</option>';
                opt.values.forEach(function (val, k) {
                    var sel = selectedValueUid === val._uid ? 'selected' : '';
                    var swatchPrefix = val.color_code ? '● ' : '';
                    var style = val.color_code ? ' style="color:' + escapeHtml(val.color_code) + '"' : '';
                    html += '<option value="' + k + '" ' + sel + style + '>' + swatchPrefix + escapeHtml(opt.label_ar || opt.name) + ': ' + escapeHtml(val.label_ar) + '</option>';
                });
                optionsHtml += '<select class="form-control form-control-sm ov-variant-option-select" data-option-uid="' + opt._uid + '" name="variants[' + j + '][option_values][' + optIndex + ']">' + html + '</select>';
            });

            var previewSrc = v.image_url || (v._pendingFile ? URL.createObjectURL(v._pendingFile) : null);
            var imgPreview = previewSrc
                ? '<img src="' + previewSrc + '" style="width:40px;height:40px;object-fit:cover;border-radius:6px;">'
                : '<i class="feather icon-image text-muted"></i>';

            var $tr = $(
                '<tr data-uid="' + v._uid + '">' +
                    '<td>' +
                        '<div class="ov-variant-image-preview mb-1">' + imgPreview + '</div>' +
                        '<input type="file" class="ov-variant-image-input" name="variants[' + j + '][image]" accept="image/*" style="max-width:90px;font-size:.65rem;">' +
                        '<input type="hidden" name="variants[' + j + '][id]" value="' + (v.id || '') + '">' +
                    '</td>' +
                    '<td><div class="ov-variant-options">' + optionsHtml + '</div></td>' +
                    '<td><input type="text" class="form-control form-control-sm ov-v-sku" name="variants[' + j + '][sku]" value="' + escapeHtml(v.sku || '') + '" placeholder="تلقائي" dir="ltr"></td>' +
                    '<td><input type="text" class="form-control form-control-sm ov-v-barcode" name="variants[' + j + '][barcode]" value="' + escapeHtml(v.barcode || '') + '" dir="ltr"></td>' +
                    '<td><input type="number" step="0.01" class="form-control form-control-sm ov-v-price-modifier" name="variants[' + j + '][price_modifier]" value="' + (v.price_modifier != null ? v.price_modifier : 0) + '"></td>' +
                    '<td><input type="number" step="0.01" class="form-control form-control-sm ov-v-override-price" name="variants[' + j + '][override_price]" value="' + (v.override_price != null ? v.override_price : '') + '" placeholder="—"></td>' +
                    '<td><input type="number" step="0.01" class="form-control form-control-sm ov-v-compare-price" name="variants[' + j + '][compare_at_price]" value="' + (v.compare_at_price != null ? v.compare_at_price : '') + '" placeholder="—"></td>' +
                    '<td><input type="number" step="0.01" class="form-control form-control-sm ov-v-cost-price" name="variants[' + j + '][cost_price]" value="' + (v.cost_price != null ? v.cost_price : '') + '" placeholder="—"></td>' +
                    '<td><input type="number" class="form-control form-control-sm ov-v-stock" name="variants[' + j + '][stock_quantity]" value="' + (v.stock_quantity != null ? v.stock_quantity : 0) + '"></td>' +
                    '<td class="text-center">' +
                        '<div class="custom-control custom-checkbox">' +
                            '<input type="checkbox" class="custom-control-input ov-v-active" id="ovVActive' + v._uid + '" name="variants[' + j + '][is_active]" value="1" ' + (v.is_active ? 'checked' : '') + '>' +
                            '<label class="custom-control-label" for="ovVActive' + v._uid + '"></label>' +
                        '</div>' +
                    '</td>' +
                    '<td><button type="button" class="btn btn-outline-danger btn-sm ov-remove-variant-btn" title="حذف المتغير"><i class="feather icon-trash-2"></i></button></td>' +
                '</tr>'
            );

            $tbody.append($tr);
        });

        $('#ovVariantsTable').toggle(state.variants.length > 0);
        $('#ovNoVariantsMsg').toggle(state.variants.length === 0);
    }

    function applyPendingFiles() {
        state.variants.forEach(function (v) {
            if (!v._pendingFile) return;
            var $tr = $('#ovVariantsTbody > tr[data-uid="' + v._uid + '"]');
            var input = $tr.find('.ov-variant-image-input')[0];
            if (!input) return;
            try {
                var dt = new DataTransfer();
                dt.items.add(v._pendingFile);
                input.files = dt.files;
            } catch (e) {
                // متصفح لا يدعم DataTransfer برمجياً — يُتجاهَل بأمان، يبقى معاينة الصورة فقط
            }
        });
    }

    function renderAll() {
        renderOptions();
        renderVariants();
        applyPendingFiles();
    }

    // -------- مزامنة القيم من DOM قبل أي إعادة عرض قد تمحو حقولاً غير محفوظة --------

    function syncOptionsFromDom() {
        $('#ovOptionsContainer > .ov-option-card').each(function () {
            var uid = $(this).data('uid');
            var opt = state.options.filter(function (o) { return o._uid === uid; })[0];
            if (!opt) return;
            var labelVal = $(this).find('.ov-option-label').val();
            opt.label_ar = labelVal;
            if (!opt.template_name) {
                opt.name = uniqueOptionName(slugify(labelVal || opt.name), opt._uid);
            }
        });
    }

    function syncVariantsFromDom() {
        $('#ovVariantsTbody > tr').each(function () {
            var uid = $(this).data('uid');
            var v = state.variants.filter(function (x) { return x._uid === uid; })[0];
            if (!v) return;
            v.sku = $(this).find('.ov-v-sku').val();
            v.barcode = $(this).find('.ov-v-barcode').val();
            v.price_modifier = $(this).find('.ov-v-price-modifier').val();
            v.override_price = $(this).find('.ov-v-override-price').val() || null;
            v.compare_at_price = $(this).find('.ov-v-compare-price').val() || null;
            v.cost_price = $(this).find('.ov-v-cost-price').val() || null;
            v.stock_quantity = $(this).find('.ov-v-stock').val();
            v.is_active = $(this).find('.ov-v-active').is(':checked');
        });
    }

    // ------------------------------- أحداث الخيارات -------------------------------

    $('#ovAddOptionBtn').on('click', function () {
        syncOptionsFromDom();
        syncVariantsFromDom();
        state.options.push({ _uid: nextUid('o'), id: null, name: '', label_ar: '', template_name: null, values: [] });
        renderAll();
    });

    $(document).on('change', '.ov-template-select', function () {
        syncOptionsFromDom();
        syncVariantsFromDom();
        var uid = $(this).closest('.ov-option-card').data('uid');
        var opt = state.options.filter(function (o) { return o._uid === uid; })[0];
        if (!opt) return;

        var name = $(this).val();
        if (!name) {
            opt.template_name = null;
            renderAll();
            return;
        }

        var t = templates.filter(function (x) { return x.name === name; })[0];
        if (!t) return;

        opt.template_name = t.name;
        opt.name = uniqueOptionName(t.name, opt._uid);
        if (!opt.label_ar) opt.label_ar = t.label_ar;

        var existingValues = opt.values.map(function (v) { return v.value; });
        (t.values || []).forEach(function (tv) {
            if (existingValues.indexOf(tv.value) === -1) {
                opt.values.push({ _uid: nextUid('v'), id: null, value: tv.value, label_ar: tv.label_ar, color_code: tv.color_code || null });
            }
        });

        renderAll();
    });

    $(document).on('click', '.ov-add-value-btn', function () {
        syncOptionsFromDom();
        syncVariantsFromDom();
        var $card = $(this).closest('.ov-option-card');
        var uid = $card.data('uid');
        var opt = state.options.filter(function (o) { return o._uid === uid; })[0];
        if (!opt) return;

        var $input = $card.find('.ov-new-value-input');
        var text = ($input.val() || '').trim();
        if (!text) return;

        var valueKey = slugify(text);
        if (opt.values.some(function (v) { return v.value === valueKey; })) {
            if (window.toastr) toastr.warning('هذه القيمة موجودة بالفعل ضمن هذا الخيار');
            return;
        }

        var $colorInput = $card.find('.ov-new-value-color');
        var colorCode = $colorInput.length && $colorInput.data('set') ? $colorInput.val() : null;

        opt.values.push({ _uid: nextUid('v'), id: null, value: valueKey, label_ar: text, color_code: colorCode });
        $input.val('');

        renderAll();
    });

    // يُعلَّم لونٌ "مُختاراً" فقط حين يلمسه المستخدم فعلاً — وإلا لتّصق
    // اللون البنفسجي الافتراضي بكل قيمةٍ حتى غير اللونية سهواً
    $(document).on('input change', '.ov-new-value-color', function () {
        $(this).data('set', true);
    });

    $(document).on('click', '.ov-remove-option-btn', function () {
        syncOptionsFromDom();
        syncVariantsFromDom();
        var uid = $(this).closest('.ov-option-card').data('uid');
        var opt = state.options.filter(function (o) { return o._uid === uid; })[0];
        if (!opt) return;
        if (!confirm('حذف هذا الخيار سيحذف كل قيمه ويزيل اختياره من كل المتغيرات. هل تريد المتابعة؟')) return;

        if (opt.id) deletedOptionIds.push(opt.id);
        state.options = state.options.filter(function (o) { return o._uid !== uid; });
        state.variants.forEach(function (v) { delete v.option_selections[uid]; });

        renderAll();
    });

    $(document).on('click', '.ov-chip-remove', function () {
        syncOptionsFromDom();
        syncVariantsFromDom();
        var $card = $(this).closest('.ov-option-card');
        var optUid = $card.data('uid');
        var valUid = $(this).data('uid');
        var opt = state.options.filter(function (o) { return o._uid === optUid; })[0];
        if (!opt) return;

        var val = opt.values.filter(function (v) { return v._uid === valUid; })[0];
        if (val && val.id) deletedOptionValueIds.push(val.id);
        opt.values = opt.values.filter(function (v) { return v._uid !== valUid; });

        state.variants.forEach(function (v) {
            if (v.option_selections[optUid] === valUid) delete v.option_selections[optUid];
        });

        renderAll();
    });

    // ------------------------------- أحداث المتغيرات -------------------------------

    $('#ovGenerateVariantsBtn').on('click', function () {
        syncOptionsFromDom();
        syncVariantsFromDom();

        if (!state.options.length || state.options.some(function (o) { return !o.values.length; })) {
            if (window.toastr) toastr.warning('أضف قيمة واحدة على الأقل لكل خيار قبل توليد المتغيرات');
            return;
        }

        var lists = state.options.map(function (o) {
            return o.values.map(function (v) { return { optUid: o._uid, valUid: v._uid }; });
        });

        var combos = lists.reduce(function (acc, curr) {
            var res = [];
            acc.forEach(function (a) { curr.forEach(function (c) { res.push(a.concat([c])); }); });
            return res;
        }, [[]]);

        function signature(selections) {
            return Object.keys(selections).sort().map(function (k) { return k + ':' + selections[k]; }).join('|');
        }

        var existingSignatures = state.variants.map(function (v) { return signature(v.option_selections); });
        var added = 0;

        combos.forEach(function (combo) {
            var selections = {};
            combo.forEach(function (p) { selections[p.optUid] = p.valUid; });
            var sig = signature(selections);
            if (existingSignatures.indexOf(sig) !== -1) return;

            existingSignatures.push(sig);
            state.variants.push({
                _uid: nextUid('v'), id: null, sku: '', barcode: '',
                price_modifier: 0, override_price: null, compare_at_price: null, cost_price: null,
                stock_quantity: 0, is_active: true, image_url: null, _pendingFile: null,
                option_selections: selections,
            });
            added++;
        });

        renderAll();

        if (window.toastr) {
            added > 0 ? toastr.success('تم توليد ' + added + ' متغيّر جديد') : toastr.info('كل التركيبات موجودة بالفعل ضمن المتغيرات');
        }
    });

    $('#ovAddVariantBtn').on('click', function () {
        syncVariantsFromDom();
        state.variants.push({
            _uid: nextUid('v'), id: null, sku: '', barcode: '',
            price_modifier: 0, override_price: null, compare_at_price: null, cost_price: null,
            stock_quantity: 0, is_active: true, image_url: null, _pendingFile: null,
            option_selections: {},
        });
        renderAll();
    });

    $(document).on('click', '.ov-remove-variant-btn', function () {
        syncVariantsFromDom();
        var uid = $(this).closest('tr').data('uid');
        var v = state.variants.filter(function (x) { return x._uid === uid; })[0];
        if (v && v.id) deletedVariantIds.push(v.id);
        state.variants = state.variants.filter(function (x) { return x._uid !== uid; });
        renderAll();
    });

    $(document).on('change', '.ov-variant-option-select', function () {
        var vUid = $(this).closest('tr').data('uid');
        var v = state.variants.filter(function (x) { return x._uid === vUid; })[0];
        if (!v) return;

        var optUid = $(this).data('option-uid');
        var k = $(this).val();
        if (k === '') { delete v.option_selections[optUid]; return; }

        var opt = state.options.filter(function (o) { return o._uid === optUid; })[0];
        if (!opt) return;
        var val = opt.values[parseInt(k, 10)];
        if (val) v.option_selections[optUid] = val._uid;
    });

    $(document).on('change', '.ov-variant-image-input', function () {
        var vUid = $(this).closest('tr').data('uid');
        var v = state.variants.filter(function (x) { return x._uid === vUid; })[0];
        if (!v) return;
        var file = this.files[0];
        if (!file) return;
        v._pendingFile = file;
        v.image_url = null;
        $(this).closest('tr').find('.ov-variant-image-preview').html('<img src="' + URL.createObjectURL(file) + '" style="width:40px;height:40px;object-fit:cover;border-radius:6px;">');
    });

    // ------------------------------- حذف صريح للنماذج القائمة -------------------------------

    $('#product_form').on('submit', function () {
        var $c = $('#ovHiddenDeletes');
        $c.empty();
        deletedOptionIds.forEach(function (id) { $c.append('<input type="hidden" name="delete_options[]" value="' + id + '">'); });
        deletedOptionValueIds.forEach(function (id) { $c.append('<input type="hidden" name="delete_option_values[]" value="' + id + '">'); });
        deletedVariantIds.forEach(function (id) { $c.append('<input type="hidden" name="delete_variants[]" value="' + id + '">'); });
    });

    renderAll();
})(jQuery);
</script>
@endpush
