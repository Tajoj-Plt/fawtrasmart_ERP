@extends('onlinestore::master')

@section('title', 'تعديل المورد')

@section('content')
    <div class="content-body">

        @section('page-actions')
            <a href="{{ route('store_suppliers.index') }}" class="btn btn-outline-secondary" id="cancelBtn">
                <i class="feather icon-x"></i> إلغاء
            </a>
            @if (hasPermission('purchases.suppliers.edit'))
                <button type="submit" form="supplierUpdateForm" class="btn btn-primary" id="updateBtn">
                    <i class="feather icon-save"></i> حفظ التعديلات
                </button>
            @endif
        @endsection

        @include('onlinestore::components.page-head', [
            'title' => 'تعديل المورد',
            'icon' => 'truck',
            'sub' => $supplier->trade_name,
            'crumbs' => [
                'الرئيسية' => url('/'),
                'الموردين' => route('store_suppliers.index'),
                'تعديل' => null,
            ],
        ])

        <form id="supplierUpdateForm" action="{{ route('store_suppliers.update', $supplier->id) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="row">
                <div class="col-md-6 col-12">
                    <div class="card">
                        <div class="stk-table-head">
                            <h6 class="stk-table-head__title"><i class="feather icon-info"></i> البيانات الأساسية</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-12">
                                    <div class="form-group">
                                        <label for="trade_name" class="stk-req">الاسم التجاري</label>
                                        <input type="text" class="form-control @error('trade_name') is-invalid @enderror"
                                            id="trade_name" name="trade_name"
                                            value="{{ old('trade_name', $supplier->trade_name) }}"
                                            placeholder="أدخل الاسم التجاري" required>
                                        @error('trade_name')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-md-6 col-12">
                                    <div class="form-group">
                                        <label for="email">البريد الإلكتروني</label>
                                        <input type="email" class="form-control @error('email') is-invalid @enderror"
                                            id="email" name="email" value="{{ old('email', $supplier->email) }}"
                                            placeholder="example@email.com">
                                        @error('email')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-md-6 col-12">
                                    <div class="form-group">
                                        <label for="mobile">رقم الجوال</label>
                                        <input type="text" class="form-control @error('mobile') is-invalid @enderror"
                                            id="mobile" name="mobile" value="{{ old('mobile', $supplier->mobile) }}"
                                            placeholder="05xxxxxxxx">
                                        @error('mobile')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-md-6 col-12">
                                    <div class="form-group">
                                        <label for="phone">رقم الهاتف</label>
                                        <input type="text" class="form-control @error('phone') is-invalid @enderror"
                                            id="phone" name="phone" value="{{ old('phone', $supplier->phone) }}"
                                            placeholder="رقم الهاتف الثابت">
                                        @error('phone')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="form-group">
                                        <label for="full_address">العنوان</label>
                                        <textarea class="form-control @error('full_address') is-invalid @enderror"
                                            id="full_address" name="full_address" rows="3"
                                            placeholder="العنوان الكامل">{{ old('full_address', $supplier->full_address) }}</textarea>
                                        @error('full_address')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                                @include('onlinestore::components.assignee-field', [
                                    'name' => 'employee_id',
                                    'employees' => $employees,
                                    'selected' => [$selectedEmployeeId],
                                    'multiple' => false,
                                    'allowEmpty' => \Modules\OnlineStore\Support\StoreAssignmentScope::seesAll(),
                                    'label' => 'المسؤول عن المورد',
                                    'help' => 'يرى هذا المورد المسؤولُ عنه ومن يديره، ومن يرى كل موردي المتجر. مورّدٌ بلا مسؤولٍ لا يراه إلا من يرى الكل.',
                                ])
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card">
                        <div class="stk-table-head">
                            <h6 class="stk-table-head__title"><i class="feather icon-file-text"></i> البيانات الضريبية والتجارية</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 col-12">
                                    <div class="form-group">
                                        <label for="tax_number">الرقم الضريبي</label>
                                        <input type="text" class="form-control @error('tax_number') is-invalid @enderror"
                                            id="tax_number" name="tax_number"
                                            value="{{ old('tax_number', $supplier->tax_number) }}" placeholder="الرقم الضريبي">
                                        @error('tax_number')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-md-6 col-12">
                                    <div class="form-group">
                                        <label for="commercial_registration">السجل التجاري</label>
                                        <input type="text" class="form-control @error('commercial_registration') is-invalid @enderror"
                                            id="commercial_registration" name="commercial_registration"
                                            value="{{ old('commercial_registration', $supplier->commercial_registration) }}"
                                            placeholder="رقم السجل التجاري">
                                        @error('commercial_registration')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="form-group">
                                        <label for="notes">ملاحظات</label>
                                        <textarea class="form-control @error('notes') is-invalid @enderror" id="notes"
                                            name="notes" rows="3"
                                            placeholder="ملاحظات إضافية">{{ old('notes', $supplier->notes) }}</textarea>
                                        @error('notes')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="stk-table-head">
                            <h6 class="stk-table-head__title"><i class="feather icon-credit-card"></i> بيانات الحساب</h6>
                        </div>
                        <div class="card-body">
                            <dl class="stk-def">
                                <div class="stk-def__row">
                                    <dt>رقم المورد</dt>
                                    <dd class="stk-num">#{{ $supplier->number_suply ?? $supplier->id }}</dd>
                                </div>
                                <div class="stk-def__row">
                                    <dt>الحالة</dt>
                                    <dd>
                                        @if ($supplier->status == 1)
                                            <span class="stk-state stk-state--on">نشط</span>
                                        @else
                                            <span class="stk-state stk-state--off">موقوف</span>
                                        @endif
                                    </dd>
                                </div>
                                <div class="stk-def__row">
                                    <dt>كود الحساب</dt>
                                    <dd class="stk-num">{{ $supplier->account->code ?? 'غير محدد' }}</dd>
                                </div>
                                <div class="stk-def__row">
                                    <dt>الرصيد الحالي</dt>
                                    <dd class="{{ ($supplier->account->balance ?? 0) >= 0 ? 'text-success' : 'text-danger' }}">
                                        {{ \App\Support\CurrencySettings::number($supplier->account->balance ?? 0) }} {{ $supplier->currency ?: \App\Support\CurrencySettings::code() }}
                                    </dd>
                                </div>
                                <div class="stk-def__row">
                                    <dt>نوع الحساب</dt>
                                    <dd>{{ ($supplier->account->balance_type ?? 'credit') == 'credit' ? 'دائن' : 'مدين' }}</dd>
                                </div>
                                <div class="stk-def__row">
                                    <dt>العملة</dt>
                                    <dd>{{ $supplier->currency ?: \App\Support\CurrencySettings::code() }}</dd>
                                </div>
                            </dl>
                        </div>
                    </div>

                    <div class="card">
                        <div class="stk-table-head">
                            <h6 class="stk-table-head__title"><i class="feather icon-clock"></i> معلومات النظام</h6>
                        </div>
                        <div class="card-body">
                            <dl class="stk-def">
                                <div class="stk-def__row">
                                    <dt>تاريخ الإنشاء</dt>
                                    <dd class="stk-num">{{ $supplier->created_at?->format('Y-m-d H:i') ?? 'غير محدد' }}</dd>
                                </div>
                                <div class="stk-def__row">
                                    <dt>آخر تحديث</dt>
                                    <dd class="stk-num">{{ $supplier->updated_at?->format('Y-m-d H:i') ?? 'غير محدد' }}</dd>
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
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        let formChanged = false;

        @if (session('success'))
            Swal.fire({
                icon: 'success',
                title: 'تم بنجاح!',
                text: '{{ session("success") }}',
                confirmButtonText: 'موافق',
                confirmButtonColor: '#28a745',
                timer: 3000,
                timerProgressBar: true
            });
        @endif

        @if (session('error'))
            Swal.fire({
                icon: 'error',
                title: 'حدث خطأ!',
                text: '{{ session("error") }}',
                confirmButtonText: 'موافق',
                confirmButtonColor: '#dc3545'
            });
        @endif

        @if ($errors->any())
            let errorMessages = '';
            @foreach ($errors->all() as $error)
                errorMessages += '• {{ $error }}\n';
            @endforeach

            Swal.fire({
                icon: 'error',
                title: 'يرجى تصحيح الأخطاء التالية:',
                text: errorMessages,
                confirmButtonText: 'موافق',
                confirmButtonColor: '#dc3545'
            });
        @endif

        // تتبع التغييرات
        document.querySelectorAll('#supplierUpdateForm input, #supplierUpdateForm textarea').forEach(input => {
            input.addEventListener('change', () => formChanged = true);
            input.addEventListener('input', () => formChanged = true);
        });

        var updateBtn = document.getElementById('updateBtn');
        if (updateBtn) {
            updateBtn.addEventListener('click', function(e) {
                e.preventDefault();

                const tradeName = document.getElementById('trade_name').value.trim();

                if (!tradeName) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'تنبيه',
                        text: 'الاسم التجاري مطلوب',
                        confirmButtonText: 'موافق',
                        confirmButtonColor: '#ffc107'
                    });
                    return;
                }

                Swal.fire({
                    title: 'تأكيد التحديث',
                    text: 'هل أنت متأكد من رغبتك في حفظ التعديلات؟',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#28a745',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'نعم، حدّث',
                    cancelButtonText: 'إلغاء',
                    reverseButtons: true
                }).then((result) => {
                    if (result.isConfirmed) {
                        formChanged = false;
                        Swal.fire({
                            title: 'جاري الحفظ...',
                            allowOutsideClick: false,
                            didOpen: () => {
                                Swal.showLoading();
                            }
                        });
                        document.getElementById('supplierUpdateForm').submit();
                    }
                });
            });
        }

        document.getElementById('cancelBtn').addEventListener('click', function(e) {
            if (formChanged) {
                e.preventDefault();
                Swal.fire({
                    title: 'هل أنت متأكد؟',
                    text: 'لديك تغييرات غير محفوظة. هل تريد المغادرة؟',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#dc3545',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'نعم، غادر',
                    cancelButtonText: 'ابقَ في الصفحة',
                    reverseButtons: true
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = "{{ route('store_suppliers.index') }}";
                    }
                });
            }
        });
    </script>
@endsection
