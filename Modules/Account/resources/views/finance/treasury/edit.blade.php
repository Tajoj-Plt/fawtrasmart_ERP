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
            <i class="feather icon-save"></i> حفظ التغييرات
        </button>
    @endsection

    @include('account::components.page-head', [
        'title' => 'خزائن وحسابات بنكية',
        'crumbs' => [
            'الرئيسيه' => route('treasury.index'),
            'تعديل خزينة' => null,
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
                    {{--
                        الشاشةُ تصل بمعرّف **الحساب** (الفهرس يعرض `accounts`)،
                        وشرطا الإيداع والسحب يسكنان صفَّ `treasuries` — وهو ما
                        يقرؤه `TreasuryAccessGuard`. فالمتحكّم يمرّر الصفّين:
                        `$account` للرابط، و`$treasury` للشرطين.
                    --}}
                    @php
                        $depositScope = old('deposit_permissions', $treasury->deposit_permissions ?? 0);
                        $withdrawScope = old('withdraw_permissions', $treasury->withdraw_permissions ?? 0);
                        $depositValue = old('deposit_permissions') !== null
                            ? old('v_employee_id') ?? old('v_functional_role_id') ?? old('v_branch_id')
                            : ($treasury->value_of_deposit_permissions ?? null);
                        $withdrawValue = old('withdraw_permissions') !== null
                            ? old('c_employee_id') ?? old('c_functional_role_id') ?? old('c_branch_id')
                            : ($treasury->value_of_withdraw_permissions ?? null);
                    @endphp
                    <form id="products_form" class="form form-vertical" action="{{ route('treasury.update',$account->id) }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <div class="form-body">
                            <div class="row">

                                <div class="col-6">
                                    <div class="form-group">
                                        <label for="first-name-vertical">النوع</label>
                                        <input type="text" disabled id="first-name-vertical" class="form-control" value="خزينة">
                                    </div>
                                </div>

                                <div class="col-6">
                                    <div class="form-group">
                                        <label for="first-name-vertical">الحالة</label>
                                        {{-- في `treasuries.status`: 1 = نشط، 0 = غير نشط — وكانت التسميتان مقلوبتين --}}
                                        <select class="form-control" id="basicSelect" name="status">
                                            <option value="1" {{ old('status', $treasury->status ?? 1) == 1 ? 'selected' : '' }}>نشط</option>
                                            <option value="0" {{ old('status', $treasury->status ?? 1) == 0 ? 'selected' : '' }}>غير نشط</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="col-6">
                                    <div class="form-group">
                                        <label for="email-id-vertical">اسم الخزينة<span class="stk-req"></span></label>
                                        <input type="text" id="email-id-vertical" class="form-control"name="name" value="{{ old('name', $treasury->name ?? $account->name) }}">
                                        @error('name')
                                        <span class="text-danger" id="basic-default-name-error" class="error">
                                            {{ $message }}
                                        </span>
                                        @enderror
                                    </div>
                                </div>

                                <div class="col-6">
                                    <div class="form-group">
                                        <label for="email-id-vertical">الوصف</label>
                                        <textarea name="description" class="form-control" id="basicTextarea" rows="2">{{ old('description', $treasury->description ?? '') }}</textarea>
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
                                                <label for="first-name-icon">ايداع</label>
                                                <select class="form-control" id="deposit_permissions" name="deposit_permissions">
                                                    <option value="0" {{ $depositScope == 0 ? 'selected' : '' }}>الكل</option>
                                                    <option value="1" {{ $depositScope == 1 ? 'selected' : '' }}>موظف محدد</option>
                                                    <option value="2" {{ $depositScope == 2 ? 'selected' : '' }}>دور وظيفي محدد</option>
                                                    <option value="3" {{ $depositScope == 3 ? 'selected' : '' }}>فرع محدد</option>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="col-6">
                                            <div class="form-group">
                                                <label for="first-name-icon">سحب</label>
                                                <select class="form-control" id="withdraw_permissions" name="withdraw_permissions">
                                                    <option value="0" {{ $withdrawScope == 0 ? 'selected' : '' }}>الكل</option>
                                                    <option value="1" {{ $withdrawScope == 1 ? 'selected' : '' }}>موظف محدد</option>
                                                    <option value="2" {{ $withdrawScope == 2 ? 'selected' : '' }}>دور وظيفي محدد</option>
                                                    <option value="3" {{ $withdrawScope == 3 ? 'selected' : '' }}>فرع محدد</option>
                                                </select>
                                            </div>
                                        </div>

                                    </div>

                                    <div class="row">

                                        {{--
                                            كانت خيارات الموظفين كلُّها
                                            `value="1"`: أيَّ موظفٍ اختار
                                            المسؤولُ حُفظ الموظف #1، فقُفلت
                                            الخزينةُ على غير صاحبها. والأدوارُ
                                            والفروعُ كانت أسماءً وهمية
                                            («دور وظيفي 1»، «فرع 1») تحفظ
                                            أرقاماً لا تقابل شيئاً.
                                        --}}
                                        <div class="col-6">
                                            <div class="form-group" style='display: none' id="employee_id">
                                                <label for="first-name-icon">اختر الموظف</label>
                                                <select class="form-control" name="v_employee_id">
                                                    <option value="">— اختر الموظف —</option>
                                                    @foreach ($employees as $employee)
                                                        <option value="{{ $employee->id }}" {{ $depositScope == 1 && (string) $depositValue === (string) $employee->id ? 'selected' : '' }}>{{ $employee->full_name }}</option>
                                                    @endforeach
                                                </select>
                                                <x-create-first
                                                    :count="count($employees ?? [])"
                                                    route="employee.create"
                                                    label="موظفاً"
                                                    hint="الموظفون يُدارون في وحدة الموارد البشرية، ولا موظف مسجَّل بعد." />
                                            </div>
                                            <div class="form-group" style='display: none' id="functional_role_id">
                                                <label for="first-name-icon">اختر الدور الوظيفي</label>
                                                <select class="form-control" name="v_functional_role_id">
                                                    <option value="">— اختر الدور الوظيفي —</option>
                                                    @foreach ($roles as $role)
                                                        <option value="{{ $role->id }}" {{ $depositScope == 2 && (string) $depositValue === (string) $role->id ? 'selected' : '' }}>{{ $role->name }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="form-group" style='display: none' id="branch_id">
                                                <label for="first-name-icon">اختر الفرع</label>
                                                <select class="form-control" name="v_branch_id">
                                                    <option value="">— اختر الفرع —</option>
                                                    @foreach ($branches as $branch)
                                                        <option value="{{ $branch->id }}" {{ $depositScope == 3 && (string) $depositValue === (string) $branch->id ? 'selected' : '' }}>{{ $branch->name }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>

                                        <div class="col-6">
                                            <div class="form-group" style='display: none' id="1employee_id">
                                                <label for="first-name-icon">اختر الموظف</label>
                                                <select class="form-control" name="c_employee_id">
                                                    <option value="">— اختر الموظف —</option>
                                                    @foreach ($employees as $employee)
                                                        <option value="{{ $employee->id }}" {{ $withdrawScope == 1 && (string) $withdrawValue === (string) $employee->id ? 'selected' : '' }}>{{ $employee->full_name }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="form-group" style='display: none' id="1functional_role_id">
                                                <label for="first-name-icon">اختر الدور الوظيفي</label>
                                                <select class="form-control" name="c_functional_role_id">
                                                    <option value="">— اختر الدور الوظيفي —</option>
                                                    @foreach ($roles as $role)
                                                        <option value="{{ $role->id }}" {{ $withdrawScope == 2 && (string) $withdrawValue === (string) $role->id ? 'selected' : '' }}>{{ $role->name }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="form-group" style='display: none' id="1branch_id">
                                                <label for="first-name-icon">اختر الفرع</label>
                                                <select class="form-control" name="c_branch_id">
                                                    <option value="">— اختر الفرع —</option>
                                                    @foreach ($branches as $branch)
                                                        <option value="{{ $branch->id }}" {{ $withdrawScope == 3 && (string) $withdrawValue === (string) $branch->id ? 'selected' : '' }}>{{ $branch->name }}</option>
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
        /* الشرطُ المحفوظ يُعرض عند الفتح، لا عند التغيير وحده: كان منتقي
           الموظف مخفياً على خزينةٍ شرطُها «موظف محدد»، فلا يرى المسؤولُ من
           اختير ولا يملك تغييره. */
        document.addEventListener('DOMContentLoaded', function () {
            ['deposit_permissions', 'withdraw_permissions'].forEach(function (id) {
                var select = document.getElementById(id);
                if (select && typeof select.onchange === 'function') {
                    select.onchange();
                }
            });
        });
    </script>

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
