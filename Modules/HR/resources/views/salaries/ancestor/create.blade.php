@extends('hr::master')

@section('title')
    اضافة سلفة راتب
@stop

@section('content')
    {{-- ترويسةُ نظام التصميم — كانت مكتوبةً يدوياً بستّة مستويات
         من `div` وفتاتِ تنقّلٍ رابطُه الأوّل فارغ. --}}
    @include('stock::components.page-head', [
        'title' => 'اضافة سلفة راتب',
        'crumbs' => ['الرئيسيه' => null, 'عرض' => null],
    ])

    <div class="content-body">
        @if (canAll(['loans.create', 'loans.disburse']))
            <form class="form" action="{{ route('ancestor.store') }}" method="post" enctype="multipart/form-data">
                @csrf
                @if ($errors->any())
                    <div class="alert alert-danger">
                        <ul>
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                @if (session('error'))
                    <div class="alert alert-danger">
                        {{ session('error') }}
                    </div>
                @endif
    
                @isset($employeesWithoutAccount)
                    @if($employeesWithoutAccount->count() > 0)
                        <div class="alert alert-warning">
                            <i class="feather icon-alert-triangle mr-1"></i>
                            <strong>{{ $employeesWithoutAccount->count() }} موظف لا يظهرون في القائمة لعدم وجود حساب سلف لهم في شجرة الحسابات.</strong>
                            <small class="d-block mt-1 text-muted">
                                افتح بطاقة الموظف ثم اضغط حفظ ليُنشئ النظام له حساب سلف خاص ضمن شجرة الأصول → السلف.
                            </small>
                        </div>
                    @endif
                @endisset
    
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center flex-wrap">
                            <div>
                                <label>الحقول التي عليها علامة <span style="color: red">*</span> الزامية</label>
                            </div>
    
                            <div>
                                <a href="" class="btn btn-outline-danger">
                                    <i class="feather icon-slash"></i>الغاء
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="feather icon-save"></i>حفظ
                                </button>
                            </div>
    
                        </div>
                    </div>
                </div>
    
    
                <div class="card" style="max-width: 90%; margin: 0 auto;">
    
                    <div class="card-body">
                        <h6 class="stk-table-head__title">تفاصيل السلفة</h6>
    
                        <div class="form-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="advance_code">رقم السلفة</label>
                                    <input type="text" class="form-control" id="advance_code"
                                        value="{{ $nextAdvanceCode ?: 'يُولَّد عند الحفظ' }}" readonly disabled>
                                    <small class="text-muted">من إعدادات الترقيم المتسلسل</small>
                                </div>
                            </div>
                            <div class="row">
                                <!-- موظف -->
                                <div class="col-md-6 mb-3">
                                    <label for="employee" class="stk-req">موظف</label>
                                    <select class="form-control select2" id="employee" name="employee_id">
                                        <option value="">إختر موظف...</option>
                                    @foreach ($employees as $employee)
                                            <option value="{{ $employee->id }}">{{ $employee->first_name }} {{ $employee->middle_name }}</option>
                                    @endforeach
                                    </select>
                                    <x-create-first
                                        :count="count($employees ?? [])"
                                        route="employee.create"
                                        permission="employees.create"
                                        :plan="\App\Constants\planFeatures\HrPlanFeatures::EMPLOYEES"
                                        label="موظفاً"
                                        hint="العملية تقع على موظف، ولا موظف مسجَّل بعد." />
                                </div>
    
                                <!-- تاريخ التقديم -->
                                <div class="col-md-6 mb-3">
                                    <label for="submission_date" class="stk-req">تاريخ التقديم</label>
                                    <input type="date" class="form-control" id="submission_date" name="submission_date"
                                        placeholder="DD/MM/YYYY">
                                </div>
    
                                <!-- المبلغ -->
                                <div class="col-md-6 mb-3">
                                    <label for="amount" class="stk-req">المبلغ</label>
                                    <div class="input-group border rounded" style="height: 50px;">
                                        <input type="number" class="form-control border-0 text-center" value="0.00"
                                            id="amount" name="amount" style="height: 100%; font-size: 1rem;">
    
                                        <select name="currency" id="" class="form-select border-0 h-100 text-center"
                                            style="font-size: 1rem;">
                                            <option value="1">SAR</option>
                                            <option value="2">USD</option>
                                            <option value="3">EUR</option>
                                            <option value="4">GBP</option>
                                            <option value="5">CNY</option>
                                        </select>
                                    </div>
                                </div>
    
                                <!-- القسط -->
                                <div class="col-md-6 mb-3">
                                    <label for="installment_amount" class="stk-req">القسط</label>
                                    <div class="input-group border rounded" style="height: 50px;">
                                        <input type="number"
                                            class="form-control border-0 text-center @error('installment_amount') is-invalid @enderror"
                                            id="installment_amount" name="installment_amount"
                                            value="{{ old('installment_amount', '0.00') }}" step="0.01"
                                            style="height: 100%; font-size: 1rem;">
    
                                        <div class="input-group-prepend" style="min-width: 120px;">
                                            <div class="input-group-text border-0 w-100 bg-white h-100">
                                                <div class="text-center w-100">
                                                    <label for="installment_count" class="form-label"> عدد الاقساط</label>
                                                    <input type="number" class="form-control border-0 text-center"
                                                        value="0" readonly id="installment_count"
                                                        style="background: transparent; font-size: 1rem;">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    @error('installment_amount')
                                        <small class="text-danger">{{ $message }}</small>
                                    @enderror
                                    <small class="text-danger" id="installment-error" style="display: none;">
                                        قيمة القسط يجب أن تكون أقل من أو تساوي قيمة السلفة
                                    </small>
                                </div>
    
    
                                <!-- معدل السداد -->
                                <div class="col-md-6 mb-3">
                                    <label for="payment_rate" class="stk-req">معدل السداد</label>
    
                                    <select name="payment_rate" id="payment_rate" class="form-control hr-s2" required>
                                        <option value="">اختر معدل الدفع</option>
                                        <option value="monthly" {{ old('payment_rate') == 'monthly' ? 'selected' : '' }}>شهري
                                        </option>
                                        <option value="weekly" {{ old('payment_rate') == 'weekly' ? 'selected' : '' }}>أسبوعي
                                        </option>
                                        <option value="daily" {{ old('payment_rate') == 'daily' ? 'selected' : '' }}>يومي
                                        </option>
                                    </select>
                                </div>
    
                                <!-- تاريخ بدء الأقساط -->
                                <div class="col-md-6 mb-3">
                                    <label for="installment_start_date">تاريخ بدء الأقساط<span
                                            class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="installment_start_date"
                                        name="installment_start_date" placeholder="DD/MM/YYYY">
                                </div>
    
                                <!-- الخزنة -->
                                <div class="col-md-6 mb-3">
                                  <label for="treasury_id" class="form-label">الخزينة المستخدمة </label>
    
                            <input type="text" id="treasury_display" class="form-control"
           placeholder="اختر موظف لعرض خزينته"
           value="" readonly>
           <input type="hidden" id="treasury_id" name="treasury_id" value="">
    
                                </div>
    
                                <!-- الدفع من قسيمة الراتب -->
    
                                <div class="col-md-6">
                                    <div class="input-group form-group">
                                        <div class="input-group-text w-100 text-left">
                                            <div
                                                class="custom-control custom-checkbox d-flex justify-content-start align-items-center w-100">
                                                <input id="duration_check" class="custom-control-input" type="checkbox"
                                                    name="pay_from_salary">
                                                <label for="duration_check" class="custom-control-label ml-2">
                                                  الدفع من قسيمة الراتب <span class="required">*</span>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
    
    
                                <!-- رسوم -->
                                <div class="col-md-6 mb-3">
                                    <label for="fees">وسوم</label>
                                    <input type="text" class="form-control" id="fees" name="tag" placeholder=""
                                        placeholder="الوسوم">
                                </div>
    
                                <!-- ملاحظة -->
                                <div class="col-md-6 mb-3">
                                    <label for="note">ملاحظة</label>
                                    <textarea class="form-control" id="note" name="note" rows="3"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
    
                </div>
    
            </form>
        @endif

    </div>

    </div>

@endsection

@section('scripts')

    <script>
        $(document).ready(function() {
            console.log('🚀 بدء تحميل سكربت الخزينة');
            
            $('#employee').on('change', function() {
                var employeeId = $(this).val();
                console.log('تم اختيار موظف:', employeeId);
                
                if (!employeeId) {
                    $('#treasury_display').val('');
                    $('#treasury_id').val('');
                    return;
                }
                
                $('#treasury_display').val('جاري التحميل...');
                
                $.ajax({
                    url: '{{ route("ancestor.employeeTreasury") }}',
                    method: 'GET',
                    data: { employee_id: employeeId },
                    success: function(response) {
                        console.log('استجابة الخادم:', response);
                        if (response.success && response.treasury) {
                            $('#treasury_display').val(response.treasury.name);
                            $('#treasury_id').val(response.treasury.id);
                            console.log('✅ تم تحميل الخزينة:', response.treasury.name);
                        } else {
                            alert(response.message || 'لا توجد خزينة لهذا الموظف');
                            $('#treasury_display').val('');
                            $('#treasury_id').val('');
                        }
                    },
                    error: function(xhr) {
                        console.error('خطأ في الطلب:', xhr);
                        alert('حدث خطأ في جلب بيانات الخزينة');
                        $('#treasury_display').val('');
                        $('#treasury_id').val('');
                    }
                });
            });
        });
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const amountInput = document.querySelector('input[name="amount"]');
            const installmentInput = document.querySelector('input[name="installment_amount"]');
            const errorDiv = document.getElementById('installment-error');

            function checkAmount() {
                const amount = parseFloat(amountInput.value) || 0;
                const installment = parseFloat(installmentInput.value) || 0;

                if (installment > amount) {
                    errorDiv.style.display = 'block';
                    installmentInput.classList.add('is-invalid');
                } else {
                    errorDiv.style.display = 'none';
                    installmentInput.classList.remove('is-invalid');
                }
            }

            installmentInput.addEventListener('input', checkAmount);
            amountInput.addEventListener('input', checkAmount);
        });
    </script>

    <script>
        // ضبط الحقول عند تحميل الصفحة
        document.addEventListener('DOMContentLoaded', function() {
            // استدعاء الدالة لضبط الحقول الافتراضية
        });
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const amountInput = document.getElementById('amount');
            const installmentInput = document.getElementById('installment_amount');
            const installmentCountInput = document.getElementById('installment_count');
            const installmentError = document.getElementById('installment-error');

            function calculateInstallments() {
                const amount = parseFloat(amountInput.value) || 0;
                const installment = parseFloat(installmentInput.value) || 0;

                if (installment > 0) {
                    const total = Math.floor(amount / installment);
                    installmentCountInput.value = total;

                    // التحقق من صحة قيمة القسط
                    if (installment > amount) {
                        installmentError.style.display = 'block';
                    } else {
                        installmentError.style.display = 'none';
                    }
                } else {
                    installmentCountInput.value = 0;
                }
            }

            // تفعيل الحساب عند تغيير أي من القيم
            amountInput.addEventListener('input', calculateInstallments);
            installmentInput.addEventListener('input', calculateInstallments);

            // حساب القيمة الأولية
            calculateInstallments();
        });
    </script>
@endsection
