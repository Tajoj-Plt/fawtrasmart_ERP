@extends('onlinestore::master')

@section('title', 'إضافة عملية على المخزون')

@section('css')
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
@endsection

@section('content')
    <div class="content-body">

        @section('page-actions')
            <a href="{{ route('store_products.show', $product->id) }}" class="btn btn-outline-secondary">
                <i class="feather icon-arrow-right"></i> رجوع
            </a>
            @if (hasPermission('online_store.products.manual_stock_adjust'))
                <button type="submit" form="stock_adjustment_form" class="btn btn-primary">
                    <i class="feather icon-save"></i> حفظ العملية
                </button>
            @endif
        @endsection

        @include('onlinestore::components.page-head', [
            'title' => 'إضافة عملية على المخزون',
            'icon' => 'layers',
            'sub' => $product->name,
            'crumbs' => [
                'الرئيسية' => url('/'),
                'المتجر الإلكتروني' => route('onlinestore.department.dashboard'),
                'المنتجات' => route('store_products.index'),
                $product->name => route('store_products.show', $product->id),
                'إضافة عملية على المخزون' => null,
            ],
        ])

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')

        {{-- بطاقة معلومات المنتج --}}
        <div class="card mb-3">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center flex-wrap">
                    <div>
                        <h5 class="mb-1">
                            <i class="feather icon-package text-primary"></i>
                            <strong>{{ $product->name }}</strong>
                        </h5>
                        <small class="text-muted">
                            رقم المنتج: <span class="badge badge-light-secondary stk-num">#{{ $product->serial_number }}</span>
                            @if($product->barcode)
                                | الباركود: <span class="badge badge-light-info stk-num">{{ $product->barcode }}</span>
                            @endif
                        </small>
                    </div>
                    <div class="text-left">
                        <span class="stk-state stk-state--on">
                            <i class="feather icon-check-circle"></i> في المخزن
                        </span>
                        <div class="mt-1">
                            <strong>الكمية الحالية بالمستودع المختار:</strong>
                            <span id="top_current_quantity" class="text-primary font-weight-bold stk-num">—</span>
                        </div>
                        <div>
                            <small class="text-muted">
                                إجمالي مستودعات المتجر:
                                <span class="stk-num">{{ number_format($total_quantity) }}</span>
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @if($product->relationLoaded('variants') ? $product->variants->isNotEmpty() : $product->hasVariants())
            <div class="alert alert-warning">
                <i class="feather icon-alert-triangle"></i>
                هذا المنتج له متغيرات (ألوان/مقاسات...)، وهذه العملية تُعدِّل مخزون <strong>المنتج الأساسي</strong> عبر المستودعات فقط.
                لتعديل مخزون متغيّرٍ محدَّد، افتح <a href="{{ route('store_products.edit', $product->id) }}">شاشة تعديل المنتج</a> وعدِّل حقل «المخزون» في جدول المتغيرات مباشرة.
            </div>
        @endif

        {{-- نموذج الإضافة --}}
        <div class="card">
            <div class="stk-table-head">
                <h6 class="stk-table-head__title"><i class="feather icon-plus-circle"></i> إضافة عملية على المخزون</h6>
            </div>
            <div class="card-body">
                <form id="stock_adjustment_form" action="{{ route('store_products.add_manual_stock_adjust', $product->id) }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="form-body">
                        <div class="row">
                            {{-- نوع العملية --}}
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="operation_type" class="stk-req">نوع العملية</label>
                                    <select class="form-control" name="type" id="operation_type" required>
                                        <option value="" disabled selected>-- اختر العملية --</option>
                                        <option value="1" {{ old('type') == 1 ? 'selected' : '' }}>إضافة</option>
                                        <option value="2" {{ old('type') == 2 ? 'selected' : '' }}>سحب</option>
                                    </select>
                                </div>
                            </div>

                            {{-- الكمية --}}
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="quantity" class="stk-req">الكمية</label>
                                    <input type="number" id="quantity" class="form-control" name="quantity"
                                           value="{{ old('quantity') }}" min="0.01" step="0.01" required
                                           placeholder="أدخل الكمية">
                                </div>
                            </div>

                            {{-- سعر الوحدة --}}
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="unit_price" class="stk-req">سعر الوحدة</label>
                                    <div class="input-group">
                                        <input type="number" id="unit_price" class="form-control" name="unit_price"
                                               value="{{ old('unit_price', $product->averageCost() ?? $product->purchase_price ?? 0) }}"
                                               min="0" step="0.01" required>
                                        <div class="input-group-append">
                                            <span class="input-group-text">{{ \App\Support\CurrencySettings::symbol() }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- المستودع — مستودعات المتجر وحدها، قابل للبحث --}}
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="store_house" class="stk-req">مستودع المتجر</label>
                                    <select class="form-control select2-store" name="store_house_id" id="store_house" required
                                            data-placeholder="اختر مستودع المتجر…">
                                        <option value=""></option>
                                        @foreach ($storehouses as $storehouse)
                                            <option value="{{ $storehouse->id }}" {{ old('store_house_id') == $storehouse->id ? 'selected' : '' }}>
                                                {{ $storehouse->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <small class="text-muted d-block mt-1">
                                        فور الاختيار يظهر رصيد هذا المنتج في المستودع.
                                    </small>
                                </div>
                            </div>

                            {{-- تاريخ الحركة --}}
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="date" class="stk-req">تاريخ الحركة</label>
                                    <input type="date" class="form-control" name="date" id="date"
                                           value="{{ old('date') }}" required>
                                </div>
                            </div>

                            {{-- الوقت --}}
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="time" class="stk-req">الوقت</label>
                                    <input type="time" class="form-control" name="time" id="time"
                                           value="{{ old('time') }}" required>
                                </div>
                            </div>

                            {{-- المرفقات --}}
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="attachments">المرفقات</label>
                                    <div class="custom-file">
                                        <input type="file" class="custom-file-input" name="attachments" id="attachments"
                                               accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.gif">
                                        <label class="custom-file-label" for="attachments">اختر ملف...</label>
                                    </div>
                                    <small class="text-muted">الملفات المسموحة: PDF, DOC, DOCX, JPG, PNG, GIF (الحد الأقصى: 10MB)</small>
                                </div>
                            </div>

                            {{-- ملاحظات --}}
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="comments">ملاحظات</label>
                                    <textarea name="comments" class="form-control" id="comments" rows="2"
                                              placeholder="أدخل ملاحظاتك هنا (اختياري)">{{ old('comments') }}</textarea>
                                </div>
                            </div>

                            {{-- ملخص العملية --}}
                            <div class="col-12 mb-3">
                                <div class="stk-summary" id="operationSummary" data-direction="">
                                    <div class="stk-summary__head">
                                        <span class="stk-summary__title"><i class="feather icon-info"></i> ملخص العملية</span>
                                        <span class="stk-summary__badge" id="summary_operation">
                                            <i class="feather icon-help-circle"></i> اختر نوع العملية
                                        </span>
                                    </div>
                                    <div class="stk-metrics stk-metrics--summary">
                                        <div class="stk-metric">
                                            <span class="stk-metric__icon"><i class="feather icon-hash"></i></span>
                                            <span class="stk-metric__value stk-num" id="summary_quantity">0</span>
                                            <span class="stk-metric__label">الكمية</span>
                                        </div>
                                        <div class="stk-metric">
                                            <span class="stk-metric__icon"><i class="feather icon-tag"></i></span>
                                            <span class="stk-metric__value stk-num"><span id="summary_unit_price">0.00</span> {{ \App\Support\CurrencySettings::symbol() }}</span>
                                            <span class="stk-metric__label">سعر الوحدة</span>
                                        </div>
                                        <div class="stk-metric stk-metric--success">
                                            <span class="stk-metric__icon"><i class="feather icon-check-circle"></i></span>
                                            <span class="stk-metric__value stk-num" id="summary_stock_before">—</span>
                                            <span class="stk-metric__label">الرصيد الحالي (المستودع المختار)</span>
                                        </div>
                                        <div class="stk-metric stk-metric--info">
                                            <span class="stk-metric__icon"><i class="feather icon-package"></i></span>
                                            <span class="stk-metric__value stk-num" id="summary_stock_after">—</span>
                                            <span class="stk-metric__label">الرصيد بعد العملية</span>
                                        </div>
                                        <div class="stk-metric stk-metric--warning">
                                            <span class="stk-metric__icon"><i class="feather icon-dollar-sign"></i></span>
                                            <span class="stk-metric__value stk-num" id="summary_total">0.00</span>
                                            <span class="stk-metric__label">الإجمالي ({{ \App\Support\CurrencySettings::symbol() }})</span>
                                        </div>
                                    </div>
                                    <p class="stk-summary__note">
                                        <i class="feather icon-zap"></i>
                                        تُطبَّق العملية على المخزون فور الحفظ — لا تنتظر اعتماداً منفصلاً.
                                    </p>
                                </div>
                            </div>

                            {{-- الأزرار --}}
                            <div class="col-12">
                                <hr>
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <small class="text-muted">الحقول التي عليها علامة <span class="text-danger">*</span> إلزامية</small>
                                    </div>
                                    <div>
                                        <button type="button" class="btn btn-outline-warning mr-1" id="reset_form">
                                            <i class="feather icon-refresh-cw"></i> تفريغ
                                        </button>
                                        @if (hasPermission('online_store.products.manual_stock_adjust'))
                                            <button type="submit" class="btn btn-primary">
                                                <i class="feather icon-save"></i> حفظ العملية
                                            </button>
                                        @endif
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>
                </form>
            </div>
        </div>

    </div>

@endsection

@section('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // تعيين التاريخ والوقت الحاليين
            const now = new Date();
            const year = now.getFullYear();
            const month = String(now.getMonth() + 1).padStart(2, '0');
            const day = String(now.getDate()).padStart(2, '0');
            const formattedDate = `${year}-${month}-${day}`;

            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');
            const formattedTime = `${hours}:${minutes}`;

            document.getElementById('date').value = formattedDate;
            document.getElementById('time').value = formattedTime;

            // العناصر
            const quantityInput = document.getElementById('quantity');
            const unitPriceInput = document.getElementById('unit_price');
            const operationTypeSelect = document.getElementById('operation_type');
            const form = document.getElementById('stock_adjustment_form');
            const resetButton = document.getElementById('reset_form');

            // عناصر الملخص
            const storeHouseSelect = document.getElementById('store_house');
            const summaryQuantity = document.getElementById('summary_quantity');
            const summaryUnitPrice = document.getElementById('summary_unit_price');
            const summaryTotal = document.getElementById('summary_total');
            const summaryStockBefore = document.getElementById('summary_stock_before');
            const summaryStockAfter = document.getElementById('summary_stock_after');
            const summaryOperation = document.getElementById('summary_operation');
            const operationSummary = document.getElementById('operationSummary');
            const topCurrentQuantity = document.getElementById('top_current_quantity');

            // رصيد الصنف في المستودع يُجلب حياً عند اختياره — من نفس مصدر شاشة
            // الأذون المخزنية (StorePermitsController::getProductStock): جدول
            // `product_details` مرشَّحاً بالمستودع والمنتج (مخزون المنتج
            // الأساسي، بلا مخزون المتغيرات). كانت القيمة تُحسب في الواجهة من
            // خريطة مُمرَّرة فتظهر صفراً حين لا يوافق مفتاحها رقم المستودع.
            const stockUrlTemplate = @json(route('store_products.stock_in_store', ['product' => $product->id, 'storeId' => '__STORE__']));

            // رصيد المستودع المختار — أو null إن لم يُختَر/لم يُجلب بعد
            let currentStock = null;
            let stockRequestToken = 0;

            function setBalanceText(value) {
                summaryStockBefore.textContent = value;
                topCurrentQuantity.textContent = value;
            }

            function refreshWarehouseBalance() {
                const storeId = storeHouseSelect.value;

                if (!storeId) {
                    currentStock = null;
                    setBalanceText('—');
                    updateSummary();
                    return;
                }

                const token = ++stockRequestToken;
                currentStock = null;
                setBalanceText('…');
                updateSummary();

                fetch(stockUrlTemplate.replace('__STORE__', encodeURIComponent(storeId)), {
                        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
                    })
                    .then(function (response) {
                        if (!response.ok) throw new Error('HTTP ' + response.status);
                        return response.json();
                    })
                    .then(function (data) {
                        if (token !== stockRequestToken) return; // تجاوزه طلبٌ أحدث
                        currentStock = parseFloat(data && data.stock) || 0;
                        setBalanceText(currentStock.toLocaleString('ar'));
                        updateSummary();
                    })
                    .catch(function () {
                        if (token !== stockRequestToken) return;
                        currentStock = null;
                        setBalanceText('تعذّر جلب الرصيد');
                        updateSummary();
                    });
            }

            // دالة تحديث الملخص
            function updateSummary() {
                const quantity = parseFloat(quantityInput.value) || 0;
                const unitPrice = parseFloat(unitPriceInput.value) || 0;
                const total = quantity * unitPrice;
                const operationType = operationTypeSelect.value;

                summaryQuantity.textContent = quantity;
                summaryUnitPrice.textContent = unitPrice.toFixed(2);
                summaryTotal.textContent = total.toFixed(2);

                // لا يُحسب الرصيد بعد العملية قبل اختيار المستودع — لا رصيد مرجعي
                if (currentStock === null) {
                    summaryStockAfter.textContent = '—';
                } else if (operationType == 1) {
                    summaryStockAfter.textContent = (currentStock + quantity).toLocaleString('ar');
                } else if (operationType == 2) {
                    summaryStockAfter.textContent = Math.max(currentStock - quantity, 0).toLocaleString('ar');
                } else {
                    summaryStockAfter.textContent = currentStock.toLocaleString('ar');
                }

                if (operationType == 1) {
                    summaryOperation.innerHTML = '<i class="feather icon-arrow-up-circle"></i> إضافة';
                    operationSummary.dataset.direction = 'in';
                } else if (operationType == 2) {
                    summaryOperation.innerHTML = '<i class="feather icon-arrow-down-circle"></i> سحب';
                    operationSummary.dataset.direction = 'out';
                } else {
                    summaryOperation.innerHTML = '<i class="feather icon-help-circle"></i> اختر نوع العملية';
                    operationSummary.dataset.direction = '';
                }
            }

            // ربط الأحداث
            quantityInput.addEventListener('input', updateSummary);
            unitPriceInput.addEventListener('input', updateSummary);
            operationTypeSelect.addEventListener('change', updateSummary);
            storeHouseSelect.addEventListener('change', refreshWarehouseBalance);

            // قائمة المستودع قابلة للبحث (select2) — وربطٌ عبر jQuery أيضاً لأن
            // select2 قد لا يُطلق حدث change الأصلي في كل الإصدارات
            if (window.jQuery && jQuery.fn.select2) {
                jQuery('#store_house')
                    .select2({ dir: 'rtl', width: '100%', placeholder: jQuery('#store_house').data('placeholder'), allowClear: true })
                    .on('change', refreshWarehouseBalance);
            }

            // التحقق من النموذج
            form.addEventListener('submit', function(e) {
                e.preventDefault();

                const quantity = parseFloat(quantityInput.value);
                const unitPrice = parseFloat(unitPriceInput.value);
                const operationType = operationTypeSelect.value;
                const storeHouse = document.getElementById('store_house').value;

                if (!quantity || quantity <= 0) {
                    Swal.fire({
                        icon: 'error',
                        title: 'خطأ في البيانات',
                        text: 'يجب إدخال كمية صحيحة أكبر من صفر!',
                        confirmButtonText: 'حسناً'
                    });
                    return;
                }

                if (!unitPrice || unitPrice < 0) {
                    Swal.fire({
                        icon: 'error',
                        title: 'خطأ في البيانات',
                        text: 'يجب إدخال سعر وحدة صحيح!',
                        confirmButtonText: 'حسناً'
                    });
                    return;
                }

                if (!operationType) {
                    Swal.fire({
                        icon: 'error',
                        title: 'خطأ في البيانات',
                        text: 'يجب اختيار نوع العملية!',
                        confirmButtonText: 'حسناً'
                    });
                    return;
                }

                if (!storeHouse) {
                    Swal.fire({
                        icon: 'error',
                        title: 'خطأ في البيانات',
                        text: 'يجب اختيار المستودع!',
                        confirmButtonText: 'حسناً'
                    });
                    return;
                }

                const operationText = operationType == 1 ? 'إضافة' : 'سحب';
                const total = (quantity * unitPrice).toFixed(2);

                Swal.fire({
                    title: 'تأكيد العملية',
                    html: `
                        <div style="text-align: right; direction: rtl;">
                            <p><strong>نوع العملية:</strong> ${operationText}</p>
                            <p><strong>الكمية:</strong> ${quantity}</p>
                            <p><strong>سعر الوحدة:</strong> ${unitPrice} {{ \App\Support\CurrencySettings::symbol() }}</p>
                            <p><strong>الإجمالي:</strong> ${total} {{ \App\Support\CurrencySettings::symbol() }}</p>
                        </div>
                    `,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#28a745',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: '<i class="feather icon-check"></i> نعم، احفظ!',
                    cancelButtonText: '<i class="feather icon-x"></i> إلغاء',
                    reverseButtons: true
                }).then((result) => {
                    if (result.isConfirmed) {
                        form.submit();
                    }
                });
            });

            // زر التفريغ
            resetButton.addEventListener('click', function() {
                Swal.fire({
                    title: 'تأكيد التفريغ',
                    text: 'هل أنت متأكد من تفريغ جميع البيانات؟',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#ffc107',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'نعم، فرغ!',
                    cancelButtonText: 'إلغاء'
                }).then((result) => {
                    if (result.isConfirmed) {
                        form.reset();
                        if (window.jQuery && jQuery.fn.select2) {
                            jQuery('#store_house').val(null).trigger('change.select2');
                        }
                        document.getElementById('date').value = formattedDate;
                        document.getElementById('time').value = formattedTime;
                        refreshWarehouseBalance();

                        Swal.fire({
                            title: 'تم التفريغ!',
                            icon: 'success',
                            timer: 1500,
                            showConfirmButton: false
                        });
                    }
                });
            });

            // تحديث اسم الملف عند الاختيار
            document.getElementById('attachments').addEventListener('change', function(e) {
                const fileName = e.target.files[0] ? e.target.files[0].name : 'اختر ملف...';
                e.target.nextElementSibling.textContent = fileName;

                // التحقق من حجم الملف
                if (e.target.files[0]) {
                    const fileSize = e.target.files[0].size / 1024 / 1024;
                    if (fileSize > 10) {
                        Swal.fire({
                            icon: 'warning',
                            title: 'حجم الملف كبير',
                            text: 'حجم الملف يجب أن يكون أقل من 10 ميجابايت',
                            confirmButtonText: 'حسناً'
                        });
                        e.target.value = '';
                        e.target.nextElementSibling.textContent = 'اختر ملف...';
                    }
                }
            });

            // تحديث الملخص عند تحميل الصفحة — يشمل رصيد المستودع إن كان
            // مُختاراً مسبقاً (رجوعٌ بعد فشل تحقّق بـ old('store_house_id'))
            refreshWarehouseBalance();
        });
    </script>
@endsection
