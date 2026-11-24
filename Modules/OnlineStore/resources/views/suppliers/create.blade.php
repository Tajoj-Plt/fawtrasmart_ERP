@extends('onlinestore::master')

@section('title', 'إضافة مورد')

@section('content')
    <div class="content-body">

        @section('page-actions')
            <a href="{{ route('store_suppliers.index') }}" class="btn btn-outline-secondary">
                <i class="feather icon-x"></i> إلغاء
            </a>
            @if (hasPermission('purchases.suppliers.create'))
                <button type="submit" form="supplierForm" class="btn btn-primary" id="saveBtn">
                    <i class="feather icon-save"></i> حفظ
                </button>
            @endif
        @endsection

        @include('onlinestore::components.page-head', [
            'title' => 'إضافة مورد جديد',
            'icon' => 'truck',
            'sub' => 'مورد جديد لموردي المتجر الإلكتروني',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'الموردين' => route('store_suppliers.index'),
                'إضافة' => null,
            ],
        ])

        <form id="supplierForm" action="{{ route('store_suppliers.store') }}" method="POST">
            @csrf

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
                                            id="trade_name" name="trade_name" value="{{ old('trade_name') }}"
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
                                            id="email" name="email" value="{{ old('email') }}"
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
                                            id="mobile" name="mobile" value="{{ old('mobile') }}"
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
                                            id="phone" name="phone" value="{{ old('phone') }}"
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
                                            placeholder="العنوان الكامل">{{ old('full_address') }}</textarea>
                                        @error('full_address')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
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
                                            id="tax_number" name="tax_number" value="{{ old('tax_number') }}"
                                            placeholder="الرقم الضريبي">
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
                                            value="{{ old('commercial_registration') }}" placeholder="رقم السجل التجاري">
                                        @error('commercial_registration')
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
                            <div class="row">
                                <div class="col-md-6 col-12">
                                    <div class="form-group">
                                        <label for="number_suply">رقم المورد</label>
                                        {{-- معاينة فقط (بلا name): الرقم يصدره الخادم من «تسلسل أرقام
                                             المستندات» عند الحفظ ولا يُقبل من النموذج --}}
                                        <input type="text" id="number_suply" class="form-control"
                                            value="{{ $nextNumber }}" readonly tabindex="-1"
                                            style="background-color: #f8f8f8; cursor: not-allowed;">
                                        <small class="text-muted"><i class="feather icon-lock"></i> يصدر تلقائياً من تسلسل أرقام المستندات عند الحفظ</small>
                                    </div>
                                </div>
                                <div class="col-md-6 col-12">
                                    <div class="form-group">
                                        <label for="currency">العملة</label>
                                        <select name="currency" id="currency"
                                            class="form-control @error('currency') is-invalid @enderror">
                                            {{-- العملات المفعَّلة في شاشة «العملات» — لا قائمة مكتوبة هنا --}}
                                            @forelse ($currencies as $currency)
                                                <option value="{{ $currency->code }}" @selected(old('currency', $defaultCurrency) == $currency->code)>{{ $currency->code }} - {{ $currency->name }}</option>
                                            @empty
                                                <option value="{{ $defaultCurrency }}" selected>{{ $defaultCurrency }}</option>
                                            @endforelse
                                        </select>
                                        @error('currency')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                                {{-- فرع المورد — به تُرشَّح قائمة الموردين (الفرع المختار في الشريط وما تحته) --}}
                                <div class="col-md-6 col-12">
                                    <div class="form-group">
                                        <label for="branch_id">الفرع</label>
                                        @if (\App\Support\Scopes::canSelectBranch(auth()->user()))
                                            <select name="branch_id" id="branch_id" class="form-control select2 @error('branch_id') is-invalid @enderror">
                                                @foreach ($branches as $branch)
                                                    <option value="{{ $branch->id }}" @selected(old('branch_id', $defaultBranchId) == $branch->id)>{{ $branch->name }}</option>
                                                @endforeach
                                            </select>
                                        @else
                                            <input type="text" class="form-control" readonly tabindex="-1"
                                                value="{{ optional($branches->firstWhere('id', $defaultBranchId))->name ?? 'فرعك الحالي' }}">
                                        @endif
                                        @error('branch_id')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
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
                                    'help' => 'يرى هذا المورد المسؤولُ عنه ومن يديره، ومن يرى كل موردي المتجر. يُسنَد إليك تلقائياً إن لم تختر أحداً.',
                                ])
                                <div class="col-12">
                                    <div class="alert alert-info mb-0">
                                        <i class="feather icon-info"></i>
                                        يمكنك إضافة الرصيد الافتتاحي من صفحة عرض المورد بعد إنشائه. الرصيد يُحسب تلقائياً من القيود المحاسبية.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="stk-table-head">
                            <h6 class="stk-table-head__title"><i class="feather icon-file-text"></i> ملاحظات</h6>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <textarea class="form-control @error('notes') is-invalid @enderror" id="notes"
                                    name="notes" rows="4" placeholder="ملاحظات إضافية">{{ old('notes') }}</textarea>
                                @error('notes')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
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

        var saveBtn = document.getElementById('saveBtn');
        if (saveBtn) {
            saveBtn.addEventListener('click', function(e) {
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
                    title: 'تأكيد الحفظ',
                    text: 'هل أنت متأكد من رغبتك في حفظ بيانات المورد؟',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#28a745',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'نعم، احفظ',
                    cancelButtonText: 'إلغاء',
                    reverseButtons: true
                }).then((result) => {
                    if (result.isConfirmed) {
                        Swal.fire({
                            title: 'جاري الحفظ...',
                            allowOutsideClick: false,
                            didOpen: () => {
                                Swal.showLoading();
                            }
                        });
                        document.getElementById('supplierForm').submit();
                    }
                });
            });
        }
    </script>
@endsection
