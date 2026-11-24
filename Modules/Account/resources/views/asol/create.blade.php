@extends('account::master')

@section('title')
    اضافة اصل جديد
@stop

@section('content')
    @section('page-actions')
        <a href="{{ route('Assets.index') }}" class="btn btn-outline-secondary">
            <i class="feather icon-x"></i> إلغاء
        </a>
        <button type="submit" form="assetForm" class="btn btn-primary">
            <i class="feather icon-save"></i> حفظ الأصل
        </button>
    @endsection

    @include('account::components.page-head', [
        'title' => 'أصل ثابت جديد',
        'icon' => 'box',
        'sub' => 'تسجيل الأصل وقيمة شرائه وطريقة إهلاكه — يُنشأ له حساب في الشجرة وقيدٌ بتاريخ الشراء',
        'crumbs' => [
            'الرئيسية' => url('/'),
            'الأصول الثابتة' => route('Assets.index'),
            'أصل جديد' => null,
        ],
    ])


    <div class="content-body">
                <form id="assetForm" action="{{ route('Assets.store') }}" method="POST" enctype="multipart/form-data">
                    @csrf

                    <!-- الصف الأول: بيانات الأصل الأساسية -->
                    <div class="card">
                        <div class="stk-table-head">
                            <h6 class="stk-table-head__title">بيانات الأصل الأساسية</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <!-- الكود -->
                                <div class="form-group col-md-4 mb-3">
                                    <label for="code">كود الأصل <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input type="text" name="code" id="code" class="form-control" value="{{ $nextAssetCode ?? old('code') }}" readonly>
                                        <div class="input-group-append">
                                            <button type="button" class="btn btn-outline-secondary" id="refreshCodeBtn" title="تحديث الكود">
                                                <i class="fa fa-refresh"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <small class="text-muted">يتم توليد الكود تلقائياً</small>
                                </div>

                                <!-- الاسم -->
                                <div class="form-group col-md-4 mb-3">
                                    <label for="name">اسم الأصل <span class="text-danger">*</span></label>
                                    <input type="text" name="name" id="name" class="form-control" value="{{ old('name') }}" required placeholder="أدخل اسم الأصل">
                                </div>

                                <!-- تاريخ الشراء -->
                                <div class="form-group col-md-4 mb-3">
                                    <label for="date_price">تاريخ الشراء <span class="text-danger">*</span></label>
                                    <input type="date" name="date_price" id="date_price" class="form-control" value="{{ old('date_price', date('Y-m-d')) }}" required>
                                </div>

                                <!-- تاريخ بداية الخدمة -->
                                <div class="form-group col-md-4 mb-3">
                                    <label for="date_service">تاريخ بداية الخدمة <span class="text-danger">*</span></label>
                                    <input type="date" name="date_service" id="date_service" class="form-control" value="{{ old('date_service', date('Y-m-d')) }}" required>
                                </div>

                                <!-- حساب الأصل (مكان الإضافة في الشجرة) -->
                                <div class="form-group col-md-4 mb-3">
                                    <label for="account_id">تصنيف الأصل في الشجرة <span class="text-danger">*</span></label>
                                    <select name="account_id" id="account_id" class="form-control select2" required>
                                        <option value="">اختر تصنيف الأصل</option>
                                        @foreach ($accounts as $account)
                                            <option value="{{ $account->id }}" data-code="{{ $account->code }}" {{ old('account_id') == $account->id ? 'selected' : '' }}>
                                                {{ $account->code }} - {{ $account->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <small class="text-muted">سيتم إنشاء حساب فرعي للأصل تحت هذا التصنيف</small>
                                </div>
                                
                                <!-- الكود المتوقع في الشجرة -->
                                <div class="form-group col-md-4 mb-3">
                                    <label>كود الحساب المتوقع</label>
                                    <input type="text" id="expected_account_code" class="form-control" readonly disabled>
                                    <small class="text-muted">سيتم توليده تلقائياً</small>
                                </div>

                                <!-- المكان -->
                                <div class="form-group col-md-4 mb-3">
                                    <label for="place">موقع الأصل</label>
                                    <input type="text" name="place" id="place" class="form-control" value="{{ old('place') }}" placeholder="مثال: المبنى الرئيسي - الطابق الأول">
                                </div>

                                <!-- العمر الانتاجي -->
                                <div class="form-group col-md-4 mb-3">
                                    <label for="region_age">العمر الانتاجي (بالسنوات)</label>
                                    <input type="number" name="region_age" id="region_age" class="form-control" value="{{ old('region_age') }}" min="1" placeholder="مثال: 5">
                                </div>

                                <!-- الكمية -->
                                <div class="form-group col-md-4 mb-3">
                                    <label for="quantity">الكمية <span class="text-danger">*</span></label>
                                    <input type="number" name="quantity" id="quantity" class="form-control" value="{{ old('quantity', 1) }}" min="1" required>
                                </div>

                                <!-- الموظف -->
                                <div class="form-group col-md-4 mb-3">
                                    <label for="employee_id">الموظف المسؤول</label>
                                    <select name="employee_id" id="employee_id" class="form-control select2">
                                        <option value="">اختر الموظف</option>
                                        @foreach ($employees as $employee)
                                            <option value="{{ $employee->id }}" {{ old('employee_id') == $employee->id ? 'selected' : '' }}>
                                                {{ $employee->full_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <x-create-first
                                        :count="count($employees ?? [])"
                                        route="employee.create"
                                        label="موظفاً"
                                        hint="الموظفون يُدارون في وحدة الموارد البشرية، ولا موظف مسجَّل بعد." />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- الصف الثاني: بيانات التسعير والدفع -->
                    <div class="card">
                        <div class="stk-table-head">
                            <h6 class="stk-table-head__title">بيانات التسعير والدفع</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <!-- قيمة الشراء -->
                                <div class="form-group col-md-4 mb-3">
                                    <label for="purchase_value">قيمة الشراء <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input type="number" step="0.01" id="purchase_value" class="form-control" name="purchase_value" value="{{ old('purchase_value') }}" required placeholder="0.00">
                                        <div class="input-group-append">
                                            <span class="input-group-text" id="currency_label">{{ \App\Support\CurrencySettings::code() }}</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- العملة -->
                                <div class="form-group col-md-4 mb-3">
                                    @include('account::components.currency-select', [
                                        'name' => 'currency',
                                        'label' => 'العملة',
                                        'selected' => old('currency'),
                                    ])
                                </div>

                                <!-- حساب النقدية/البنك -->
                                <div class="form-group col-md-4 mb-3">
                                    <label for="cash_account">حساب الدفع (نقدي/بنكي) <span class="text-danger">*</span></label>
                                    <select name="cash_account" id="cash_account" class="form-control select2" required>
                                        <option value="">اختر حساب الدفع</option>
                                        @if(isset($cash_bank_accounts) && $cash_bank_accounts->count() > 0)
                                            <optgroup label="الخزائن والحسابات البنكية">
                                                @foreach ($cash_bank_accounts as $account)
                                                    <option value="{{ $account->id }}" {{ old('cash_account') == $account->id ? 'selected' : '' }}>
                                                        {{ $account->code }} - {{ $account->name }}
                                                    </option>
                                                @endforeach
                                            </optgroup>
                                        @endif
                                        <optgroup label="حسابات أخرى">
                                            @foreach ($accounts_all as $account)
                                                <option value="{{ $account->id }}" {{ old('cash_account') == $account->id ? 'selected' : '' }}>
                                                    {{ $account->code ?? '' }} - {{ $account->name }}
                                                </option>
                                            @endforeach
                                        </optgroup>
                                    </select>
                                    <x-create-first
                                        :count="count($cash_bank_accounts ?? []) + count($accounts_all ?? [])"
                                        route="accounts_chart.view_root"
                                        permission="account.chart.view"
                                        :plan="\App\Constants\planFeatures\AccountPlanFeatures::CHART_OF_ACCOUNTS"
                                        label="حساب خزينة أو بنك"
                                        hint="الأصل يُشترى من حسابٍ نقديّ، ولا حسابات في الدليل بعد." />
                                    <small class="text-muted">الحساب الذي سيتم خصم قيمة الشراء منه</small>
                                </div>

                                <!-- الضريبة 1 -->
                                <div class="form-group col-md-4 mb-3">
                                    <label for="tax1">نوع الضريبة</label>
                                    <select name="tax1" class="form-control" id="tax1">
                                        <option value="2" {{ old('tax1', 2) == 2 ? 'selected' : '' }}>صفرية (معفاة من الضريبة)</option>
                                        <option value="1" {{ old('tax1') == 1 ? 'selected' : '' }}>القيمة المضافة (15%)</option>
                                        <option value="3" {{ old('tax1') == 3 ? 'selected' : '' }}>معفاة</option>
                                    </select>
                                </div>

                                <!-- الضريبة 2 -->
                                <div class="form-group col-md-4 mb-3 d-none">
                                    <label for="tax2">الضريبة 2</label>
                                    <select name="tax2" class="form-control" id="tax2">
                                        <option value="2" {{ old('tax2', 2) == 2 ? 'selected' : '' }}>صفرية</option>
                                        <option value="1" {{ old('tax2') == 1 ? 'selected' : '' }}>القيمة المضافة</option>
                                        <option value="3" {{ old('tax2') == 3 ? 'selected' : '' }}>معفاة</option>
                                    </select>
                                </div>

                                <!-- الوصف -->
                                <div class="form-group col-md-8 mb-3">
                                    <label for="description">ملاحظات / وصف الأصل</label>
                                    <textarea class="form-control" id="description" name="description" rows="2" placeholder="أدخل أي ملاحظات إضافية عن الأصل...">{{ old('description') }}</textarea>
                                </div>

                                <!-- المرفقات -->
                                <div class="form-group col-md-12 mb-3">
                                    <label for="attachments">المرفقات (فاتورة الشراء / صور الأصل)</label>
                                    <input id="attachments" type="file" name="attachments" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
                                    <small class="text-muted">يمكنك رفع ملف PDF أو صورة (الحد الأقصى 2 ميجابايت)</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- الصف الثالث: إهلاك الأصل -->
                    <div class="card">
                        <div class="stk-table-head">
                            <h6 class="stk-table-head__title">إهلاك الأصل</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <!-- طريقة الإهلاك -->
                                <div class="form-group col-md-6 mb-3">
                                    <label for="dep_method">طريقة الإهلاك</label>
                                    <select name="dep_method" id="depreciation_method" class="form-control select2">
                                        <option value="">من فضلك اختر</option>
                                        <option value="1" {{ old('dep_method') == 1 ? 'selected' : '' }}>طريقة القسط الثابت</option>
                                        <option value="2" {{ old('dep_method') == 2 ? 'selected' : '' }}>طريقة القسط المتناقص</option>
                                        <option value="3" {{ old('dep_method') == 3 ? 'selected' : '' }}>وحدات الانتاج</option>
                                        <option value="4" {{ old('dep_method') == 4 ? 'selected' : '' }}>بدون الاهلاك</option>
                                    </select>
                                </div>

                                <!-- قيمة الخردة -->
                                <div class="form-group col-md-6 mb-3" id="salvage-value-field">
                                    <label for="salvage_value">قيمة الخردة</label>
                                    <input type="number" step="0.01" class="form-control" name="salvage_value" id="salvage_value" value="{{ old('salvage_value', 0) }}">
                                </div>
                            </div>

                            <!-- الحقول التي تظهر عند اختيار طريقة إهلاك -->
                            <div class="depreciation-fields d-none">
                                <!-- القسط الثابت -->
                                <div class="row method-1 d-none">
                                    <div class="form-group col-md-4 mb-3">
                                        <label>قيمة الإهلاك الثابتة</label>
                                        <input type="number" step="0.01" class="form-control" name="dep_rate" value="{{ old('dep_rate') }}">
                                    </div>
                                    <div class="form-group col-md-4 mb-3">
                                        <label>الفترة</label>
                                        <input type="number" min="1" class="form-control" name="duration" value="{{ old('duration') }}">
                                    </div>
                                    <div class="form-group col-md-4 mb-3">
                                        <label>التكرار</label>
                                        <select name="period" class="form-control">
                                            <option value="">اختر</option>
                                            <option value="1" {{ old('period') == 1 ? 'selected' : '' }}>يومي</option>
                                            <option value="2" {{ old('period') == 2 ? 'selected' : '' }}>شهري</option>
                                            <option value="3" {{ old('period') == 3 ? 'selected' : '' }}>سنوي</option>
                                        </select>
                                    </div>
                                </div>

                                <!-- القسط المتناقص -->
                                <div class="row method-2 d-none">
                                    <div class="form-group col-md-4 mb-3">
                                        <label>نسبة الإهلاك (%)</label>
                                        <input type="number" step="0.01" class="form-control" name="depreciation_rate" value="{{ old('depreciation_rate') }}">
                                    </div>
                                    <div class="form-group col-md-4 mb-3">
                                        <label>الفترة</label>
                                        <input type="number" min="1" class="form-control" name="declining_duration" value="{{ old('declining_duration') }}">
                                    </div>
                                    <div class="form-group col-md-4 mb-3">
                                        <label>التكرار</label>
                                        <select name="declining_period" class="form-control">
                                            <option value="">اختر</option>
                                            <option value="monthly" {{ old('declining_period') == 'monthly' ? 'selected' : '' }}>شهري</option>
                                            <option value="quarterly" {{ old('declining_period') == 'quarterly' ? 'selected' : '' }}>ربع سنوي</option>
                                            <option value="yearly" {{ old('declining_period') == 'yearly' ? 'selected' : '' }}>سنوي</option>
                                        </select>
                                    </div>
                                </div>

                                <!-- وحدات الإنتاج -->
                                <div class="row method-3 d-none">
                                    <div class="form-group col-md-4 mb-3">
                                        <label>اسم الوحدة</label>
                                        <input type="text" class="form-control" name="unit_name" value="{{ old('unit_name') }}">
                                    </div>
                                    <div class="form-group col-md-4 mb-3">
                                        <label>إجمالي الوحدات</label>
                                        <input type="number" min="1" class="form-control" name="total_units" value="{{ old('total_units') }}">
                                    </div>
                                    <div class="form-group col-md-4 mb-3">
                                        <label>تاريخ انتهاء الإهلاك</label>
                                        <input type="date" class="form-control" name="depreciation_end_date" value="{{ old('depreciation_end_date') }}">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="stk-form-foot">
                        <a href="{{ route('Assets.index') }}" class="btn btn-outline-secondary">
                            <i class="feather icon-x"></i> إلغاء
                        </a>
                        <button type="submit" class="btn btn-primary" id="submitBtn">
                            <i class="feather icon-save"></i> حفظ الأصل
                        </button>
                    </div>

                </form>
    </div>
@endsection

@section('scripts')
    <!-- إضافة مكتبة Select2 -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        $(document).ready(function() {
            // تهيئة Select2
            $('.select2').select2({
                placeholder: "اختر...",
                allowClear: true,
                width: '100%'
            });

            // =====================================================
            // تحديث كود الأصل تلقائياً
            // =====================================================
            $('#refreshCodeBtn').click(function() {
                var btn = $(this);
                btn.prop('disabled', true);
                btn.find('i').addClass('fa-spin');
                
                $.ajax({
                    url: "{{ route('Assets.nextCode') }}",
                    type: 'GET',
                    success: function(response) {
                        $('#code').val(response.code);
                    },
                    error: function() {
                        Swal.fire('خطأ', 'حدث خطأ أثناء توليد الكود', 'error');
                    },
                    complete: function() {
                        btn.prop('disabled', false);
                        btn.find('i').removeClass('fa-spin');
                    }
                });
            });

            // =====================================================
            // تحديث كود الحساب المتوقع عند تغيير تصنيف الأصل
            // =====================================================
            $('#account_id').change(function() {
                var parentId = $(this).val();
                var selectedOption = $(this).find('option:selected');
                var parentCode = selectedOption.data('code');
                
                if (parentId && parentCode) {
                    $('#expected_account_code').val(parentCode + '.X');
                    
                    // جلب الكود الفعلي من السيرفر
                    $.ajax({
                        url: "{{ route('Assets.generateAccountCode', '') }}/" + parentId,
                        type: 'GET',
                        success: function(response) {
                            if (response) {
                                $('#expected_account_code').val(response);
                            }
                        }
                    });
                } else {
                    $('#expected_account_code').val('');
                }
            });

            // =====================================================
            // تحديث عرض العملة
            // =====================================================
            $('#currency').change(function() {
                var currency = $(this).val();
                // القيمة صارت رمز العملة نفسه — لا رقماً يُترجَم في القالب
                $('#currency_label').text(currency);
            });

            // =====================================================
            // إخفاء جميع حقول الإهلاك في البداية
            // =====================================================
            $('.method-1, .method-2, .method-3').addClass('d-none');
            $('.depreciation-fields').addClass('d-none');

            // عند تغيير طريقة الإهلاك
            $('#depreciation_method').change(function() {
                var method = $(this).val();

                // إخفاء جميع الحقول أولاً
                $('.method-1, .method-2, .method-3').addClass('d-none');
                $('.depreciation-fields').addClass('d-none');

                // إذا كان بدون إهلاك، نخفي قيمة الخردة
                if (method == '4') {
                    $('#salvage-value-field').addClass('d-none');
                    return;
                }

                // إظهار قيمة الخردة لجميع الطرق الأخرى
                $('#salvage-value-field').removeClass('d-none');

                // إذا تم اختيار طريقة إهلاك (غير بدون إهلاك)
                if (method && method != '4') {
                    $('.depreciation-fields').removeClass('d-none');
                    $('.method-' + method).removeClass('d-none');
                }
            });

            // =====================================================
            // حساب قيمة الإهلاك الدورية تلقائياً (للقسط الثابت)
            // =====================================================
            function calculateStraightLineDepreciation() {
                var purchaseValue = parseFloat($('#purchase_value').val()) || 0;
                var salvageValue = parseFloat($('#salvage_value').val()) || 0;
                var duration = parseInt($('input[name="duration"]').val()) || 0;
                var period = $('select[name="period"]').val();
                
                if (purchaseValue > 0 && duration > 0) {
                    var depreciableAmount = purchaseValue - salvageValue;
                    var periodsPerYear = period == '1' ? 365 : (period == '2' ? 12 : 1);
                    var totalPeriods = duration;
                    var depRate = depreciableAmount / totalPeriods;
                    
                    $('input[name="dep_rate"]').val(depRate.toFixed(2));
                }
            }

            $('#purchase_value, #salvage_value, input[name="duration"], select[name="period"]').on('change keyup', function() {
                if ($('#depreciation_method').val() == '1') {
                    calculateStraightLineDepreciation();
                }
            });

            // =====================================================
            // معالجة إرسال النموذج
            // =====================================================
            $('#assetForm').submit(function(e) {
                e.preventDefault();

                const form = this;
                const submitBtn = $('#submitBtn');
                const accountName = $('#account_id option:selected').text();
                const cashAccountName = $('#cash_account option:selected').text();

                Swal.fire({
                    title: 'تأكيد إضافة الأصل',
                    html: `
                        <div class="text-right" dir="rtl">
                            <p><strong>كود الأصل:</strong> ${$('#code').val()}</p>
                            <p><strong>اسم الأصل:</strong> ${$('#name').val()}</p>
                            <p><strong>قيمة الشراء:</strong> ${parseFloat($('#purchase_value').val()).toLocaleString('ar-SA')} ${$('#currency').val()}</p>
                            <p><strong>تصنيف الأصل:</strong> ${accountName}</p>
                            <p><strong>حساب الدفع:</strong> ${cashAccountName}</p>
                            <hr>
                            <p class="text-info"><i class="fa fa-info-circle"></i> سيتم إنشاء قيد محاسبي تلقائياً لإثبات شراء الأصل</p>
                        </div>
                    `,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'نعم، تأكيد الإضافة',
                    cancelButtonText: 'إلغاء',
                    reverseButtons: true
                }).then((result) => {
                    if (result.isConfirmed) {
                        submitBtn.prop('disabled', true);
                        submitBtn.html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> جاري الحفظ...');
                        form.submit();
                    }
                });
            });
        });
    </script>
@endsection
