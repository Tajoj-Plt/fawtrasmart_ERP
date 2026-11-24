@extends('stock::master')

@section('title', 'حركة يدوية على المخزون')

@section('content')
    <div class="content-body">

        @include('stock::components.page-head', [
            'title' => 'حركة يدوية على المخزون',
            'icon' => 'sliders',
            'sub' => $product->name . ' — ' . $product->serial_number,
            'crumbs' => [
                'الرئيسية' => url('/'),
                'المنتجات' => route('products.index'),
                $product->name => route('products.show', $product->id),
                'حركة يدوية' => null,
            ],
        ])

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')
        @include('stock::components.form-errors')

        <div class="alert alert-danger" id="client_errors" role="alert" hidden></div>

        {{--
            `novalidate` مقصود:

            «المستودع» قائمة select2، وselect2 يُخفي العنصر الأصلي ويضع مكانه
            عنصراً آخر. والمتصفّح حين ينقر المستخدم «حفظ» يتحقّق من الحقول
            الإلزامية أوّلاً، فيجد حقلاً `required` فارغاً **لا يمكن تركيز
            المؤشّر عليه** لأنه مخفيّ — فيُلغي الإرسال بصمت ولا يُطلق حدث
            submit أصلاً: لا رسالة، ولا حفظ، ولا شيء. (رسالة الكونسول:
            An invalid form control with name='store_house_id' is not focusable.)

            صار التحقّق كلّه لنا: فحصٌ في المتصفّح برسائل واضحة، وتحقّقٌ كامل
            في الخادم يعود بأخطائه ظاهرةً فوق النموذج.
        --}}
        <form id="stock_adjustment_form" method="POST" enctype="multipart/form-data" novalidate
              action="{{ route('products.add_manual_stock_adjust', $product->id) }}">
            @csrf

            <div class="row">
                <div class="col-lg-8 col-12">
                    {{-- ══════════ الحركة ══════════ --}}
                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title"><i class="feather icon-repeat"></i> تفاصيل الحركة</h4>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="operation_type" class="stk-req">نوع الحركة</label>
                                        <select id="operation_type" name="type" required
                                                class="form-control @error('type') is-invalid @enderror">
                                            <option value="" disabled {{ old('type') ? '' : 'selected' }}>اختر…</option>
                                            <option value="1" {{ old('type') == 1 ? 'selected' : '' }}>إضافة للمخزون</option>
                                            <option value="2" {{ old('type') == 2 ? 'selected' : '' }}>سحب من المخزون</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="quantity" class="stk-req">الكمية</label>
                                        @if ($role)
                                            {{-- المنتج له وحدات فرعية: تُختار الوحدة مع الكمية --}}
                                            @php
                                                // أول وحدة هي المختارة ما لم يُعِد التحقّق قيمةً سابقة
                                                $defaultSubUnit = old('sub_unit_id', collect($SubUnits)->first()->id ?? null);
                                            @endphp
                                            <div class="input-group">
                                                <input type="number" id="quantity" name="quantity" required
                                                       class="form-control stk-num @error('quantity') is-invalid @enderror" min="0.01" step="0.01"
                                                       value="{{ old('quantity') }}">
                                                <select id="sub-unit" name="sub_unit_id" class="form-control" style="max-width:45%">
                                                    @foreach ($SubUnits as $SubUnit)
                                                        <option value="{{ $SubUnit->id }}"
                                                            {{ $defaultSubUnit == $SubUnit->id ? 'selected' : '' }}>
                                                            {{ $SubUnit->larger_unit_name }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        @else
                                            <input type="number" id="quantity" name="quantity" required
                                                   class="form-control stk-num @error('quantity') is-invalid @enderror" min="0.01" step="0.01"
                                                   value="{{ old('quantity') }}">
                                        @endif

                                        @error('quantity')
                                            <span class="invalid-feedback d-block">{{ $message }}</span>
                                        @enderror
                                        @error('sub_unit_id')
                                            <span class="invalid-feedback d-block">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>

                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="unit_price" class="stk-req">سعر الوحدة</label>
                                        <input type="number" id="unit_price" name="unit_price" required
                                               class="form-control stk-num @error('unit_price') is-invalid @enderror" min="0" step="0.01"
                                               value="{{ old('unit_price', $product->averageCost() ?? $product->purchase_price ?? 0) }}">
                                        @error('unit_price')
                                            <span class="invalid-feedback d-block">{{ $message }}</span>
                                        @else
                                            <span class="stk-help">متوسط التكلفة الحالي مقترَحاً</span>
                                        @enderror
                                    </div>
                                </div>

                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="date" class="stk-req">تاريخ الحركة</label>
                                        <input type="date" id="date" name="date" class="form-control" required
                                               value="{{ old('date', now()->toDateString()) }}">
                                    </div>
                                </div>

                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="time" class="stk-req">الوقت</label>
                                        <input type="time" id="time" name="time" class="form-control" required
                                               value="{{ old('time', now()->format('H:i')) }}">
                                    </div>
                                </div>

                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="store_house" class="stk-req">المستودع</label>
                                        <select id="store_house" name="store_house_id" data-required="1"
                                                class="form-control select2 @error('store_house_id') is-invalid @enderror">
                                            <option value="" disabled {{ old('store_house_id') ? '' : 'selected' }}>اختر…</option>
                                            @foreach ($storehouses as $storehouse)
                                                <option value="{{ $storehouse->id }}"
                                                    {{ old('store_house_id') == $storehouse->id ? 'selected' : '' }}>
                                                    {{ $storehouse->name }}
                                                </option>
                                            @endforeach
                                        </select>

                                        @error('store_house_id')
                                            <span class="invalid-feedback d-block">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>

                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="type_of_operation">تصنيف العملية</label>
                                        <select id="type_of_operation" name="type_of_operation" class="form-control">
                                            @foreach ([1 => 'رصيد افتتاحي', 2 => 'تسوية مخزونية', 3 => 'عملية يدوية'] as $value => $label)
                                                <option value="{{ $value }}" {{ old('type_of_operation') == $value ? 'selected' : '' }}>
                                                    {{ $label }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="status">الحالة</label>
                                        <select id="status" name="status" class="form-control">
                                            @foreach ([1 => 'معلق', 2 => 'مكتمل', 3 => 'متجاهل', 4 => 'مجدول مرة أخرى'] as $value => $label)
                                                <option value="{{ $value }}" {{ old('status') == $value ? 'selected' : '' }}>
                                                    {{ $label }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                <div class="col-md-4">
                                    <div class="form-group mb-0">
                                        <label for="duration">المدة (أيام)</label>
                                        <input type="number" id="duration" name="duration" min="0"
                                               class="form-control stk-num" value="{{ old('duration') }}">
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="form-group mb-0">
                                        <label for="subaccount">الحساب الفرعي</label>

                                        {{-- كان حقلاً نصّياً حرّاً لا يرتبط بشيء؛ صار حساباً من شجرة الحسابات --}}
                                        <select id="subaccount" name="subaccount"
                                                class="form-control select2-accounts @error('subaccount') is-invalid @enderror">
                                            <option value="">بلا حساب فرعي</option>

                                            @foreach ($accounts as $account)
                                                <option value="{{ $account->id }}"
                                                        data-depth="{{ $account->dropdown_depth }}"
                                                        data-code="{{ $account->dropdown_code }}"
                                                        data-name="{{ $account->name }}"
                                                        {{ $account->dropdown_selectable ? '' : 'disabled' }}
                                                        {{ old('subaccount') == $account->id ? 'selected' : '' }}>
                                                    {{ $account->dropdown_label }}
                                                </option>
                                            @endforeach
                                        </select>

                                        @error('subaccount')
                                            <span class="invalid-feedback d-block">{{ $message }}</span>
                                        @else
                                            <span class="stk-help">ابحث بالاسم أو بالكود — الترحيل على الحسابات الفرعية وحدها</span>
                                        @enderror
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="form-group mb-0">
                                        <label for="attachments">المرفقات</label>
                                        <input type="file" id="attachments" name="attachments" class="form-control"
                                               accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.gif">
                                        <span class="stk-help">PDF أو DOC أو صورة — بحدّ أقصى 10 ميغابايت</span>
                                    </div>
                                </div>

                                <div class="col-12">
                                    <div class="form-group mb-0 mt-1">
                                        <label for="comments">ملاحظات</label>
                                        <textarea id="comments" name="comments" class="form-control"
                                                  rows="2">{{ old('comments') }}</textarea>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="stk-form-foot">
                            <span class="stk-help me-auto">الحقول المعلَّمة بـ<span class="stk-req"></span> إلزامية</span>
                            <button type="button" class="btn btn-outline-secondary" id="reset_form">
                                <i class="feather icon-rotate-ccw"></i> تفريغ
                            </button>
                            <button type="submit" class="btn btn-primary">
                                <i class="feather icon-check"></i> حفظ الحركة
                            </button>
                        </div>
                    </div>
                </div>

                {{-- ══════════ الملخّص ══════════ --}}
                <div class="col-lg-4 col-12">
                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title"><i class="feather icon-package"></i> المنتج</h4>
                        </div>
                        <div class="card-body">
                            <div class="stk-media">
                                <span class="stk-media__thumb">{{ Str::upper(Str::substr($product->name, 0, 1)) }}</span>
                                <div class="stk-media__body">
                                    <span class="stk-media__title">{{ $product->name }}</span>
                                    <span class="stk-media__sub stk-num">#{{ $product->serial_number }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title"><i class="feather icon-clipboard"></i> ملخّص العملية</h4>
                            <span class="stk-count">{{ App\Support\CurrencySettings::code() }}</span>
                        </div>
                        <div class="card-body">
                            <dl class="stk-def">
                                <div class="stk-def__row">
                                    <dt>نوع الحركة</dt>
                                    <dd id="summary_operation">—</dd>
                                </div>
                                <div class="stk-def__row">
                                    <dt>الكمية</dt>
                                    <dd class="stk-num" id="summary_quantity">0</dd>
                                </div>
                                <div class="stk-def__row">
                                    <dt>سعر الوحدة</dt>
                                    <dd class="stk-num" id="summary_unit_price">0.00</dd>
                                </div>
                                <div class="stk-def__row">
                                    <dt>الإجمالي</dt>
                                    <dd class="stk-num stk-balance" id="summary_total">0.00</dd>
                                </div>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
@endsection

@section('scripts')
    {{-- حُذف تحميل SweetAlert من @@section('head'): القالب لا يُخرج هذا القسم
         أصلاً، والمكتبة محمَّلة في `stock::master` — فكان الوسم بلا أثر. --}}
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (window.jQuery && $.fn.select2) {
                $('.select2').select2({ placeholder: 'اختر…', dir: 'rtl', width: '100%' });

                /* الحساب الفرعي: بحثٌ بالاسم أو بالكود مع إبقاء تدرّج الشجرة،
                   والحسابات الرئيسية عناوين غير قابلة للاختيار */
                $('.select2-accounts').select2({
                    dir: 'rtl',
                    width: '100%',
                    placeholder: 'ابحث بالاسم أو بالكود…',
                    allowClear: true,
                    matcher: function (params, data) {
                        const term = $.trim(params.term || '').toLowerCase();
                        if (! term) return data;

                        const haystack = [
                            data.text || '',
                            $(data.element).data('code') || '',
                            $(data.element).data('name') || '',
                        ].join(' ').toLowerCase();

                        return haystack.indexOf(term) > -1 ? data : null;
                    },
                    templateResult: function (state) {
                        if (! state.id) return state.text;

                        const depth = parseInt($(state.element).data('depth'), 10) || 0;
                        const node = $('<span></span>').text(state.text);

                        node.css('padding-inline-start', (depth * 0.9) + 'rem');
                        if ($(state.element).is(':disabled')) node.css({ opacity: .7, fontWeight: 600 });

                        return node;
                    },
                    templateSelection: function (state) {
                        return $(state.element).data('name') || state.text;
                    },
                });
            }

            if (typeof Swal === 'undefined') {
                // بديلٌ بسيط كي لا يتوقّف الحفظ إن تعذّر تحميل المكتبة
                window.Swal = {
                    fire: function (options) {
                        const text = (options && (options.text || options.title)) || '';
                        if (options && options.showCancelButton) {
                            return Promise.resolve({ isConfirmed: window.confirm(text) });
                        }
                        window.alert(text);
                        return Promise.resolve({ isConfirmed: false });
                    },
                };
            }

            const form = document.getElementById('stock_adjustment_form');
            const quantity = document.getElementById('quantity');
            const unitPrice = document.getElementById('unit_price');
            const operation = document.getElementById('operation_type');
            const storeHouse = document.getElementById('store_house');

            /* التاريخ والوقت يأتيان من الخادم بقيمة `old` إن وُجدت — كان
               السكربت يدهسهما بلحظة التحميل، فيضيع ما أدخله المستخدم كلّما
               رفض التحقّق النموذج. */
            const initialDate = document.getElementById('date').value;
            const initialTime = document.getElementById('time').value;

            const summary = {
                operation: document.getElementById('summary_operation'),
                quantity: document.getElementById('summary_quantity'),
                unitPrice: document.getElementById('summary_unit_price'),
                total: document.getElementById('summary_total'),
            };

            function refresh() {
                const q = parseFloat(quantity.value) || 0;
                const p = parseFloat(unitPrice.value) || 0;
                summary.quantity.textContent = q;
                summary.unitPrice.textContent = p.toFixed(2);
                summary.total.textContent = (q * p).toFixed(2);
                summary.operation.textContent = operation.value
                    ? operation.options[operation.selectedIndex].text
                    : '—';
            }

            [quantity, unitPrice].forEach(el => el.addEventListener('input', refresh));
            operation.addEventListener('change', refresh);
            refresh();

            /* الرسالة تظهر في الصفحة أيضاً لا في نافذةٍ وحدها،
               كي لا يضيع السبب إن تعذّر تحميل مكتبة النوافذ */
            const inlineBox = document.getElementById('client_errors');

            function fail(text, field) {
                if (inlineBox) {
                    inlineBox.textContent = text;
                    inlineBox.hidden = false;
                    inlineBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }

                document.querySelectorAll('#stock_adjustment_form .is-invalid')
                    .forEach(el => el.classList.remove('is-invalid'));

                if (field) {
                    field.classList.add('is-invalid');
                    if (field.offsetParent !== null) field.focus();
                }

                Swal.fire({ icon: 'error', title: 'بيانات ناقصة', text, confirmButtonText: 'حسناً' });
            }

            let confirmed = false;

            form.addEventListener('submit', function (e) {
                if (confirmed) return;   // المرور الثاني بعد التأكيد
                e.preventDefault();

                const q = parseFloat(quantity.value);
                const p = parseFloat(unitPrice.value);

                if (!operation.value) return fail('اختر نوع الحركة: إضافة أو سحب.', operation);
                if (!q || q <= 0) return fail('أدخل كمية أكبر من صفر.', quantity);
                if (isNaN(p) || p < 0) return fail('أدخل سعر وحدة صحيحاً (لا يقلّ عن صفر).', unitPrice);
                if (!storeHouse.value) return fail('اختر المستودع.', storeHouse);
                if (!document.getElementById('date').value) return fail('حدّد تاريخ الحركة.', document.getElementById('date'));
                if (!document.getElementById('time').value) return fail('حدّد وقت الحركة.', document.getElementById('time'));

                if (inlineBox) inlineBox.hidden = true;

                Swal.fire({
                    title: 'تأكيد الحركة',
                    html: '<div style="text-align:right;direction:rtl">'
                        + '<p><strong>نوع الحركة:</strong> ' + operation.options[operation.selectedIndex].text + '</p>'
                        + '<p><strong>الكمية:</strong> ' + q + '</p>'
                        + '<p><strong>سعر الوحدة:</strong> ' + p.toFixed(2) + '</p>'
                        + '<p><strong>الإجمالي:</strong> ' + (q * p).toFixed(2) + '</p>'
                        + '</div>',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'نعم، احفظ',
                    cancelButtonText: 'إلغاء',
                    reverseButtons: true,
                }).then(result => {
                    if (result.isConfirmed) {
                        confirmed = true;
                        form.submit();
                    }
                });
            });

            document.getElementById('reset_form').addEventListener('click', function () {
                Swal.fire({
                    title: 'تفريغ النموذج؟',
                    text: 'ستُمسح كل القيم المُدخَلة',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'نعم، فرّغ',
                    cancelButtonText: 'إلغاء',
                    reverseButtons: true,
                }).then(result => {
                    if (!result.isConfirmed) return;
                    form.reset();
                    document.getElementById('date').value = initialDate;
                    document.getElementById('time').value = initialTime;
                    if (window.jQuery && $.fn.select2) $('.select2, .select2-accounts').val(null).trigger('change');
                    refresh();
                });
            });

            const attachments = document.getElementById('attachments');
            attachments.addEventListener('change', function () {
                const file = this.files && this.files[0];
                if (file && file.size / 1024 / 1024 > 10) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'الملف كبير',
                        text: 'الحدّ الأقصى 10 ميغابايت',
                        confirmButtonText: 'حسناً',
                    });
                    this.value = '';
                }
            });
        });
    </script>
@endsection
