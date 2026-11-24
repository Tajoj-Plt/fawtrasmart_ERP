@extends('account::master')

@section('title')
    تعديل اصل
@stop

@section('content')
    @section('page-actions')
        <a href="{{ route('Assets.index') }}" class="btn btn-outline-secondary">
            <i class="feather icon-x"></i> إلغاء
        </a>
        <button type="submit" form="assetForm" class="btn btn-primary">
            <i class="feather icon-save"></i> حفظ التغييرات
        </button>
    @endsection

    @include('account::components.page-head', [
        'title' => 'تعديل الأصل',
        'icon' => 'edit-2',
        'sub' => $asset->name . ' — قيمة الشراء وعملته مجمَّدتان بعد الحفظ لارتباطهما بالقيد',
        'crumbs' => [
            'الرئيسية' => url('/'),
            'الأصول الثابتة' => route('Assets.index'),
            $asset->name => route('Assets.show', $asset->id),
            'تعديل' => null,
        ],
    ])


    <div class="content-body">
                <form id="assetForm" action="{{ route('Assets.update', $asset) }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    @method('PUT')

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
                                    <input type="text" name="code" id="code" class="form-control" value="{{ old('code', $asset->code) }}" readonly>
                                    <small class="text-muted">لا يمكن تعديل الكود</small>
                                </div>

                                <!-- الاسم -->
                                <div class="form-group col-md-4 mb-3">
                                    <label for="name">اسم الأصل <span class="text-danger">*</span></label>
                                    <input type="text" name="name" id="name" class="form-control" value="{{ old('name', $asset->name) }}" required>
                                </div>

                                <!-- تاريخ الشراء -->
                                <div class="form-group col-md-4 mb-3">
                                    <label for="date_price">تاريخ الشراء <span class="text-danger">*</span></label>
                                    <input type="date" name="date_price" id="date_price" class="form-control" value="{{ old('date_price', $asset->date_price ? \Carbon\Carbon::parse($asset->date_price)->format('Y-m-d') : '') }}" required>
                                </div>

                                <!-- تاريخ بداية الخدمة -->
                                <div class="form-group col-md-4 mb-3">
                                    <label for="date_service">تاريخ بداية الخدمة <span class="text-danger">*</span></label>
                                    <input type="date" name="date_service" id="date_service" class="form-control" value="{{ old('date_service', $asset->date_service ? \Carbon\Carbon::parse($asset->date_service)->format('Y-m-d') : '') }}" required>
                                </div>

                                <!-- حساب الأصل (تصنيف في الشجرة) -->
                                <div class="form-group col-md-4 mb-3">
                                    <label for="account_id">تصنيف الأصل في الشجرة</label>
                                    <select name="account_id" id="account_id" class="form-control select2" disabled>
                                        <option value="">حساب الأصل</option>
                                        @foreach ($accounts as $account)
                                            <option value="{{ $account->id }}" data-code="{{ $account->code }}" {{ old('account_id', $asset->account_id) == $account->id ? 'selected' : '' }}>
                                                {{ $account->code ?? '' }} - {{ $account->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <input type="hidden" name="account_id" value="{{ $asset->account_id }}">
                                    <small class="text-muted">لا يمكن تغيير تصنيف الأصل بعد الإنشاء</small>
                                </div>

                                <!-- المكان -->
                                <div class="form-group col-md-4 mb-3">
                                    <label for="place">موقع الأصل</label>
                                    <input type="text" name="place" id="place" class="form-control" value="{{ old('place', $asset->place) }}" placeholder="مثال: المبنى الرئيسي - الطابق الأول">
                                </div>

                                <!-- العمر الانتاجي -->
                                <div class="form-group col-md-4 mb-3">
                                    <label for="region_age">العمر الانتاجي (بالسنوات)</label>
                                    <input type="number" name="region_age" id="region_age" class="form-control" value="{{ old('region_age', $asset->region_age) }}" min="1">
                                </div>

                                <!-- الكمية -->
                                <div class="form-group col-md-4 mb-3">
                                    <label for="quantity">الكمية <span class="text-danger">*</span></label>
                                    <input type="number" name="quantity" id="quantity" class="form-control" value="{{ old('quantity', $asset->quantity) }}" min="1" required>
                                </div>

                                <!-- الموظف -->
                                <div class="form-group col-md-4 mb-3">
                                    <label for="employee_id">الموظف المسؤول</label>
                                    <select name="employee_id" id="employee_id" class="form-control select2">
                                        <option value="">اختر الموظف</option>
                                        @foreach ($employees as $employee)
                                            <option value="{{ $employee->id }}" {{ old('employee_id', $asset->employee_id) == $employee->id ? 'selected' : '' }}>
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

                    <!-- الصف الثاني: بيانات التسعير -->
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
                                        <input type="number" step="0.01" id="purchase_value" class="form-control" name="purchase_value" value="{{ old('purchase_value', $asset->purchase_value) }}" readonly>
                                        <div class="input-group-append">
                                            <span class="input-group-text">{{ $asset->currency }}</span>
                                        </div>
                                    </div>
                                    <small class="text-muted">لا يمكن تعديل قيمة الشراء لتأثيرها على القيد المحاسبي</small>
                                </div>

                                <!-- العملة -->
                                <div class="form-group col-md-4 mb-3">
                                    @include('account::components.currency-select', [
                                        'name' => 'currency_display',
                                        'id' => 'currency',
                                        'label' => 'العملة',
                                        'selected' => $asset->currency,
                                        'disabled' => true,
                                    ])
                                    {{-- عملة المستند تُجمَّد بعد الحفظ — تُعرَض ولا تُعدَّل --}}
                                    <input type="hidden" name="currency" value="{{ $asset->currency }}">
                                </div>

                                <!-- حساب النقدية -->
                                <div class="form-group col-md-4 mb-3">
                                    <label for="cash_account">حساب الدفع (نقدي/بنكي)</label>
                                    <select name="cash_account" id="cash_account" class="form-control select2" disabled>
                                        <option value="">حساب الدفع</option>
                                        @foreach ($accounts_all as $account)
                                            <option value="{{ $account->id }}" {{ old('cash_account', $asset->cash_account) == $account->id ? 'selected' : '' }}>
                                                {{ $account->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <x-create-first
                                        :count="count($accounts_all ?? [])"
                                        route="accounts_chart.view_root"
                                        permission="account.chart.view"
                                        :plan="\App\Constants\planFeatures\AccountPlanFeatures::CHART_OF_ACCOUNTS"
                                        label="حساباً"
                                        hint="الحسابات تُنشأ من دليل الحسابات، ولا حساب متاح بعد." />
                                </div>

                                <!-- الضريبة 1 -->
                                <div class="form-group col-md-4 mb-3">
                                    <label for="tax1">الضريبة 1</label>
                                    <select name="tax1" class="form-control" id="tax1">
                                        <option value="1" {{ old('tax1', $asset->tax1) == 1 ? 'selected' : '' }}>القيمة المضافة</option>
                                        <option value="2" {{ old('tax1', $asset->tax1) == 2 ? 'selected' : '' }}>صفرية</option>
                                        <option value="3" {{ old('tax1', $asset->tax1) == 3 ? 'selected' : '' }}>معفاة</option>
                                    </select>
                                </div>

                                <!-- الضريبة 2 -->
                                <div class="form-group col-md-4 mb-3">
                                    <label for="tax2">الضريبة 2</label>
                                    <select name="tax2" class="form-control" id="tax2">
                                        <option value="1" {{ old('tax2', $asset->tax2) == 1 ? 'selected' : '' }}>القيمة المضافة</option>
                                        <option value="2" {{ old('tax2', $asset->tax2) == 2 ? 'selected' : '' }}>صفرية</option>
                                        <option value="3" {{ old('tax2', $asset->tax2) == 3 ? 'selected' : '' }}>معفاة</option>
                                    </select>
                                </div>

                                <!-- الوصف -->
                                <div class="form-group col-md-4 mb-3">
                                    <label for="description">الوصف</label>
                                    <textarea class="form-control" id="description" name="description" rows="3">{{ old('description', $asset->description) }}</textarea>
                                </div>

                                <!-- المرفقات -->
                                <div class="form-group col-md-12 mb-3">
                                    <label for="attachments">المرفقات</label>
                                    <input id="attachments" type="file" name="attachments" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
                                    <small class="text-muted">يمكنك رفع ملف PDF أو صورة (الحد الأقصى 2 ميجابايت)</small>
                                    @if($asset->attachments)
                                        <div class="mt-2">
                                            <small class="text-info">المرفق الحالي: <a href="{{ asset('storage/' . $asset->attachments) }}" target="_blank">عرض الملف</a></small>
                                        </div>
                                    @endif
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
                                        <option value="1" {{ old('dep_method', $asset->dep_method) == 1 ? 'selected' : '' }}>طريقة القسط الثابت</option>
                                        <option value="2" {{ old('dep_method', $asset->dep_method) == 2 ? 'selected' : '' }}>طريقة القسط المتناقص</option>
                                        <option value="3" {{ old('dep_method', $asset->dep_method) == 3 ? 'selected' : '' }}>وحدات الانتاج</option>
                                        <option value="4" {{ old('dep_method', $asset->dep_method) == 4 ? 'selected' : '' }}>بدون الاهلاك</option>
                                    </select>
                                </div>

                                <!-- قيمة الخردة -->
                                <div class="form-group col-md-6 mb-3" id="salvage-value-field">
                                    <label for="salvage_value">قيمة الخردة</label>
                                    <input type="number" step="0.01" class="form-control" name="salvage_value" id="salvage_value" value="{{ old('salvage_value', $asset->salvage_value) }}">
                                </div>
                            </div>

                            <!-- الحقول التي تظهر عند اختيار طريقة إهلاك -->
                            <div class="depreciation-fields {{ $asset->dep_method && $asset->dep_method != 4 ? '' : 'd-none' }}">
                                <!-- القسط الثابت -->
                                <div class="row method-1 {{ $asset->dep_method == 1 ? '' : 'd-none' }}">
                                    <div class="form-group col-md-4 mb-3">
                                        <label>قيمة الإهلاك الثابتة</label>
                                        <input type="number" step="0.01" class="form-control" name="dep_rate" value="{{ old('dep_rate', $asset->dep_rate) }}">
                                    </div>
                                    <div class="form-group col-md-4 mb-3">
                                        <label>الفترة</label>
                                        <input type="number" min="1" class="form-control" name="duration" value="{{ old('duration', $asset->duration) }}">
                                    </div>
                                    <div class="form-group col-md-4 mb-3">
                                        <label>التكرار</label>
                                        <select name="period" class="form-control">
                                            <option value="">اختر</option>
                                            <option value="1" {{ old('period', $asset->period) == 1 ? 'selected' : '' }}>يومي</option>
                                            <option value="2" {{ old('period', $asset->period) == 2 ? 'selected' : '' }}>شهري</option>
                                            <option value="3" {{ old('period', $asset->period) == 3 ? 'selected' : '' }}>سنوي</option>
                                        </select>
                                    </div>
                                </div>

                                <!-- القسط المتناقص -->
                                <div class="row method-2 {{ $asset->dep_method == 2 ? '' : 'd-none' }}">
                                    <div class="form-group col-md-4 mb-3">
                                        <label>نسبة الإهلاك (%)</label>
                                        <input type="number" step="0.01" class="form-control" name="depreciation_rate" value="{{ old('depreciation_rate', $asset->depreciation_rate) }}">
                                    </div>
                                    <div class="form-group col-md-4 mb-3">
                                        <label>الفترة</label>
                                        <input type="number" min="1" class="form-control" name="declining_duration" value="{{ old('declining_duration', $asset->declining_duration) }}">
                                    </div>
                                    <div class="form-group col-md-4 mb-3">
                                        <label>التكرار</label>
                                        <select name="declining_period" class="form-control">
                                            <option value="">اختر</option>
                                            <option value="monthly" {{ old('declining_period', $asset->declining_period) == 'monthly' ? 'selected' : '' }}>شهري</option>
                                            <option value="quarterly" {{ old('declining_period', $asset->declining_period) == 'quarterly' ? 'selected' : '' }}>ربع سنوي</option>
                                            <option value="yearly" {{ old('declining_period', $asset->declining_period) == 'yearly' ? 'selected' : '' }}>سنوي</option>
                                        </select>
                                    </div>
                                </div>

                                <!-- وحدات الإنتاج -->
                                <div class="row method-3 {{ $asset->dep_method == 3 ? '' : 'd-none' }}">
                                    <div class="form-group col-md-4 mb-3">
                                        <label>اسم الوحدة</label>
                                        <input type="text" class="form-control" name="unit_name" value="{{ old('unit_name', $asset->unit_name) }}">
                                    </div>
                                    <div class="form-group col-md-4 mb-3">
                                        <label>إجمالي الوحدات</label>
                                        <input type="number" min="1" class="form-control" name="total_units" value="{{ old('total_units', $asset->total_units) }}">
                                    </div>
                                    <div class="form-group col-md-4 mb-3">
                                        <label>تاريخ انتهاء الإهلاك</label>
                                        <input type="date" class="form-control" name="depreciation_end_date" value="{{ old('depreciation_end_date', $asset->depreciation_end_date) }}">
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
                            <i class="feather icon-save"></i> حفظ التغييرات
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

            // معالجة إرسال النموذج
            $('#assetForm').submit(function(e) {
                e.preventDefault();

                const form = this;
                const submitBtn = $('#submitBtn');

                Swal.fire({
                    title: 'تأكيد تعديل الأصل',
                    html: `
                        <div class="text-right" dir="rtl">
                            <p><strong>الكود:</strong> ${$('#code').val()}</p>
                            <p><strong>الاسم:</strong> ${$('#name').val()}</p>
                            <p><strong>قيمة الشراء:</strong> ${$('#purchase_value').val()}</p>
                        </div>
                    `,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'نعم، تأكيد التعديل',
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
