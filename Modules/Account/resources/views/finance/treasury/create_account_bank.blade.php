@extends('account::master')
@section('title')
خزائن وحسابات بنكية
@stop

@section('content')
    @section('page-actions')
        <a href="{{ route('treasury.index') }}" class="btn btn-outline-secondary">
            <i class="feather icon-x"></i> إلغاء
        </a>
        <button type="submit" form="products_form" class="btn btn-primary">
            <i class="feather icon-save"></i> حفظ الحساب البنكي
        </button>
    @endsection

    @include('account::components.page-head', [
        'title' => 'خزائن وحسابات بنكية',
        'crumbs' => [
            'الرئيسيه' => route('treasury.index'),
            'اضافة حساب' => null,
        ],
    ])

    <div class="content-body">

        </div>

        <div class="card">
            <div class="stk-table-head">
                    <h6 class="stk-table-head__title">تسجيل البيانات</h6>
                </div>
            <div class="card-content">
                <div class="card-body">
                    {{-- ملخّصُ الأخطاء قبل النموذج: الرسالةُ تحت الحقل وحدَها
                         تُفوَّت في نموذجٍ أطولَ من الشاشة. --}}
                    <x-form-errors title="تعذّر حفظ الحساب البنكي" />

                    <form id="products_form" class="form form-vertical" action="{{ route('treasury.store_account_bank') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <div class="form-body">
                            <div class="row">

                                <div class="col-6">
                                    <div class="form-group">
                                        <label for="type">النوع</label>
                                        <input type="text" disabled id="type" class="form-control" value="حساب بنكي">
                                    </div>
                                </div>

                                <div class="col-6">
                                    <div class="form-group">
                                        <label for="name">الاسم<span class="stk-req"></span></label>
                                        <input type="text" id="name" class="form-control @error('name') is-invalid @enderror" name="name" value="{{ old('name') }}" required>
                                        @error('name')
                                        <span class="text-danger">
                                            {{ $message }}
                                        </span>
                                        @enderror
                                    </div>
                                </div>

                                <div class="col-6">
                                    <div class="form-group">
                                        <label for="bank_name">البنك <span class="text-danger">*</span></label>
                                        {{--
                                            قائمةٌ لا حقلٌ حرّ: الحرُّ يُسجِّل «الراجحي»
                                            و«الرّاجحي» بنكَين، ويتعذّر عندها ربطُ الآيبان
                                            ببنكه للتحقّق.
                                        --}}
                                        <select id="bank_name" class="form-control @error('bank_name') is-invalid @enderror" name="bank_name" required>
                                            <option value="">— اختر البنك —</option>
                                            @foreach ($banks as $code => $bankName)
                                                <option value="{{ $code }}"
                                                    data-code="{{ $code }}"
                                                    @selected(old('bank_name') === $code)>
                                                    {{ $bankName }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('bank_name')
                                            <span class="text-danger">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>

                                <div class="col-6">
                                    <div class="form-group">
                                        <label for="account_number">رقم الآيبان (IBAN) <span class="text-danger">*</span></label>
                                        <input type="text" id="account_number" class="form-control @error('account_number') is-invalid @enderror"
                                            name="account_number"
                                            value="{{ \App\Constants\Banks::formatIban(old('account_number')) }}"
                                            placeholder="SA00 0000 0000 0000 0000 0000"
                                            dir="ltr" style="text-align:left; letter-spacing:.5px"
                                            autocomplete="off" required>
                                        <small class="stk-help" id="ibanHint">
                                            يبدأ برمز الدولة ثم خانتَي تحقّق — ويجب أن يطابق البنكَ المختار.
                                        </small>
                                        @error('account_number')
                                            <span class="text-danger">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>

                                <div class="col-6">
                                    <div class="form-group">
                                        @include('account::components.currency-select', [
                                            'name' => 'currency',
                                            'label' => 'العملة',
                                            'selected' => old('currency'),
                                        ])
                                    </div>
                                </div>

                                <div class="col-6">
                                    <div class="form-group">
                                        <label for="status">الحالة</label>
                                        <select class="form-control @error('status') is-invalid @enderror" id="status" name="status">
                                            <option value="1" {{ old('status', 1) == 1 ? 'selected' : '' }}>نشط</option>
                                            <option value="0" {{ old('status', 1) == 0 ? 'selected' : '' }}>غير نشط</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="col-6">
                                    <div class="form-group">
                                        <label for="description">الوصف</label>
                                        <textarea name="description" class="form-control" id="description" rows="2">{{ old('description') }}</textarea>
                                    </div>
                                </div>

                            </div>

                            <hr>
                            <div class="card">
                                <div class="card-header">
                                    <h5>الصلاحيات</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-6">
                                            <div class="form-group">
                                                <label for="deposit_permissions">ايداع</label>
                                                <select class="form-control" id="deposit_permissions" name="deposit_permissions">
                                                    <option selected value="0">الكل</option>
                                                    <option value="1" {{ old('deposit_permissions') == 1 ? 'selected' : '' }}>موظف محدد</option>
                                                    <option value="2" {{ old('deposit_permissions') == 2 ? 'selected' : '' }}>دور وظيفي محدد</option>
                                                    <option value="3" {{ old('deposit_permissions') == 3 ? 'selected' : '' }}>فرع محدد</option>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="col-6">
                                            <div class="form-group">
                                                <label for="withdraw_permissions">سحب</label>
                                                <select class="form-control" id="withdraw_permissions" name="withdraw_permissions">
                                                    <option value="0">الكل</option>
                                                    <option value="1" {{ old('withdraw_permissions') == 1 ? 'selected' : '' }}>موظف محدد</option>
                                                    <option value="2" {{ old('withdraw_permissions') == 2 ? 'selected' : '' }}>دور وظيفي محدد</option>
                                                    <option value="3" {{ old('withdraw_permissions') == 3 ? 'selected' : '' }}>فرع محدد</option>
                                                </select>
                                            </div>
                                        </div>

                                    </div>

                                    <div class="row">

                                        <div class="col-6">
                                            <div class="form-group" style='display: none' id="employee_id">
                                                <label for="v_employee_id">اختر الموظف</label>
                                                <select class="form-control" name="v_employee_id" id="v_employee_id">
                                                    @foreach ($employees as $employee)
                                                        <option value="{{ $employee->id }}">{{ $employee->first_name }} {{ $employee->middle_name }}</option>
                                                    @endforeach
                                                </select>
                                                <x-create-first
                                                    :count="count($employees ?? [])"
                                                    route="employee.create"
                                                    label="موظفاً"
                                                    hint="الموظفون يُدارون في وحدة الموارد البشرية، ولا موظف مسجَّل بعد." />
                                            </div>
                                            <div class="form-group" style='display: none' id="functional_role_id">
                                                <label for="v_functional_role_id">اختر الدور الوظيفي</label>
                                                <select class="form-control" name="v_functional_role_id" id="v_functional_role_id">
                                                    <option value="">— اختر الدور الوظيفي —</option>
                                                    @foreach ($roles as $role)
                                                        <option value="{{ $role->id }}" {{ old('v_functional_role_id') == $role->id ? 'selected' : '' }}>{{ $role->name }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="form-group" style='display: none' id="branch_id">
                                                <label for="v_branch_id">اختر الفرع</label>
                                                <select class="form-control" name="v_branch_id" id="v_branch_id">
                                                    <option value="">— اختر الفرع —</option>
                                                    @foreach ($branches as $branch)
                                                        <option value="{{ $branch->id }}" {{ old('v_branch_id') == $branch->id ? 'selected' : '' }}>{{ $branch->name }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>

                                        <div class="col-6">
                                            <div class="form-group" style='display: none' id="1employee_id">
                                                <label for="c_employee_id">اختر الموظف</label>
                                                <select class="form-control" name="c_employee_id" id="c_employee_id">
                                                    @foreach ($employees as $employee)
                                                        <option value="{{ $employee->id }}">{{ $employee->first_name }} {{ $employee->middle_name }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="form-group" style='display: none' id="1functional_role_id">
                                                <label for="c_functional_role_id">اختر الدور الوظيفي</label>
                                                <select class="form-control" name="c_functional_role_id" id="c_functional_role_id">
                                                    <option value="">— اختر الدور الوظيفي —</option>
                                                    @foreach ($roles as $role)
                                                        <option value="{{ $role->id }}" {{ old('c_functional_role_id') == $role->id ? 'selected' : '' }}>{{ $role->name }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="form-group" style='display: none' id="1branch_id">
                                                <label for="c_branch_id">اختر الفرع</label>
                                                <select class="form-control" name="c_branch_id" id="c_branch_id">
                                                    <option value="">— اختر الفرع —</option>
                                                    @foreach ($branches as $branch)
                                                        <option value="{{ $branch->id }}" {{ old('c_branch_id') == $branch->id ? 'selected' : '' }}>{{ $branch->name }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>

                                    </div>

                                </div>
                            </div>


                        </div>
                </div>
            </div>
        </div>



@endsection

@section('scripts')
    <script>
        document.getElementById('deposit_permissions').onchange = function(){
            if(this.value == 1){
                document.getElementById('employee_id').style.display = '';
                document.getElementById('functional_role_id').style.display = 'none';
                document.getElementById('branch_id').style.display = 'none';
            } else if(this.value == 2) {
                document.getElementById('functional_role_id').style.display = '';
                document.getElementById('branch_id').style.display = 'none';
                document.getElementById('employee_id').style.display = 'none';
            } else if(this.value == 3) {
                document.getElementById('branch_id').style.display = '';
                document.getElementById('employee_id').style.display = 'none';
                document.getElementById('functional_role_id').style.display = 'none';
            }
            else{
                document.getElementById('branch_id').style.display = 'none';
                document.getElementById('employee_id').style.display = 'none';
                document.getElementById('functional_role_id').style.display = 'none';
            }
        };
    </script>

    <script>
        document.getElementById('withdraw_permissions').onchange = function(){
            if(this.value == 1){
                document.getElementById('1employee_id').style.display = '';
                document.getElementById('1functional_role_id').style.display = 'none';
                document.getElementById('1branch_id').style.display = 'none';
            } else if(this.value == 2) {
                document.getElementById('1functional_role_id').style.display = '';
                document.getElementById('1branch_id').style.display = 'none';
                document.getElementById('1employee_id').style.display = 'none';
            } else if(this.value == 3) {
                document.getElementById('1branch_id').style.display = '';
                document.getElementById('1employee_id').style.display = 'none';
                document.getElementById('1functional_role_id').style.display = 'none';
            }
            else{
                document.getElementById('1branch_id').style.display = 'none';
                document.getElementById('1employee_id').style.display = 'none';
                document.getElementById('1functional_role_id').style.display = 'none';
            }
        };
    </script>

@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        /*
            الإرسالُ بـfetch لا بإعادة تحميل الصفحة — كما في سند الصرف.

            كان النموذجُ يُرسَل إرسالاً كاملاً: تُعاد الصفحةُ ويضيع ما كُتب،
            والنتيجةُ — نجاحاً كانت أو خطأً — تظهر بعد وميضِ تحميل. وشاشتان
            لعملٍ واحد لا ينبغي أن تفترقا في سلوكهما.
        */
        document.getElementById('products_form').addEventListener('submit', function (e) {
            e.preventDefault();

            var form = this;
            var btn = document.querySelector('button[form="products_form"]');
            var original = btn ? btn.innerHTML : '';

            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<i class="feather icon-loader"></i> جارٍ الحفظ…';
            }

            fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': form.querySelector('input[name="_token"]').value
                }
            })
                .then(function (response) {
                    return response.json().then(function (data) {
                        return { ok: response.ok, status: response.status, data: data };
                    }).catch(function () {
                        return { ok: response.ok, status: response.status, data: null };
                    });
                })
                .then(function (res) {
                    if (res.ok && res.data && res.data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'تمّ',
                            text: res.data.message || 'أُضيف الحساب البنكي.',
                            timer: 2000,
                            showConfirmButton: false
                        }).then(function () {
                            window.location.href = res.data.redirect || '{{ route('treasury.index') }}';
                        });
                        return;
                    }

                    /*
                        أخطاءُ التحقّق تصل في `errors` مفتاحُها اسمُ الحقل.
                        تُجمَع في قائمةٍ واحدة، ويُعلَّم كلُّ حقلٍ خاطئ في
                        النموذج — فالرسالةُ تقول ما الخطأ والتعليمُ يقول أين.
                    */
                    var messages = [];

                    form.querySelectorAll('.is-invalid').forEach(function (el) {
                        el.classList.remove('is-invalid');
                    });

                    if (res.data && res.data.errors) {
                        Object.keys(res.data.errors).forEach(function (field) {
                            var list = res.data.errors[field];
                            messages.push(Array.isArray(list) ? list.join('، ') : list);

                            var input = form.querySelector('[name="' + field + '"]');
                            if (input) input.classList.add('is-invalid');
                        });
                    }

                    if (res.data && res.data.error_details) {
                        messages.push(res.data.error_details);
                    }

                    var title = (res.data && res.data.message) || 'تعذّر الحفظ';

                    Swal.fire({
                        icon: 'error',
                        title: 'خطأ في البيانات',
                        html: '<div dir="rtl" class="text-end">' +
                            '<p><strong>' + title + '</strong></p>' +
                            (messages.length
                                ? '<ul class="text-danger mt-2" style="padding-inline-start:1.2rem">' +
                                  messages.map(function (m) { return '<li>' + m + '</li>'; }).join('') +
                                  '</ul>'
                                : '') +
                            '</div>',
                        confirmButtonText: 'موافق',
                        width: '600px'
                    }).then(function () {
                        var first = form.querySelector('.is-invalid');
                        if (first) {
                            first.scrollIntoView({ behavior: 'smooth', block: 'center' });
                            if (typeof first.focus === 'function') first.focus({ preventScroll: true });
                        }
                    });
                })
                .catch(function (error) {
                    Swal.fire({
                        icon: 'error',
                        title: 'خطأ في الاتصال',
                        html: '<div dir="rtl" class="text-end">' +
                            '<p>تعذّر إرسال البيانات إلى الخادم.</p>' +
                            '<p class="text-danger mt-2">' + error.message + '</p>' +
                            '</div>',
                        confirmButtonText: 'موافق'
                    });
                })
                .finally(function () {
                    if (btn) {
                        btn.disabled = false;
                        btn.innerHTML = original;
                    }
                });
        });
    </script>
@endpush
