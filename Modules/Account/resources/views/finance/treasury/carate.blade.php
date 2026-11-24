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
            <i class="feather icon-save"></i> حفظ الخزينة
        </button>
    @endsection

    @include('account::components.page-head', [
        'title' => 'خزائن وحسابات بنكية',
        'crumbs' => [
            'الرئيسيه' => route('treasury.index'),
            'اضافة خزينة' => null,
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
                    <form id="products_form" class="form form-vertical" action="{{ route('treasury.store') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <div class="form-body">
                            <div class="row">

                                <div class="col-4">
                                    <div class="form-group">
                                        <label for="first-name-vertical">النوع</label>
                                        <input type="text" disabled id="first-name-vertical" class="form-control" value="خزينة">
                                    </div>
                                </div>

                                <div class="col-4">
                                    <div class="form-group">
                                        <label for="is_active">الحالة</label>
                                        <select class="form-control" id="is_active" name="is_active">
                                            <option value="1" {{ old('is_active', 1) == 1 ? 'selected' : '' }}>نشط</option>
                                            <option value="0" {{ old('is_active', 1) == 0 ? 'selected' : '' }}>غير نشط</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="col-4">
                                    <div class="form-group">
                                        <label for="name">اسم الخزينة<span class="stk-req"></span></label>
                                        <input type="text" id="name" class="form-control" name="name" value="{{ old('name') }}" required>
                                        @error('name')
                                        <span class="text-danger">
                                            {{ $message }}
                                        </span>
                                        @enderror
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