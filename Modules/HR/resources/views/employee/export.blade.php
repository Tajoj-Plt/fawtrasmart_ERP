@extends('hr::master')

@section('title')
    ادراة الموظفين
@stop

@section('content')
    {{-- ترويسةُ نظام التصميم — كانت مكتوبةً يدوياً بستّة مستويات
         من `div` وفتاتِ تنقّلٍ رابطُه الأوّل فارغ. --}}
    @include('stock::components.page-head', [
        'title' => 'ادارة الموظفين',
        'crumbs' => ['الرئيسيه' => null, 'تصدير' => null],
    ])

    <div class="content-body">
        <div class="container-fluid">
            <div class="card">

                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center flex-wrap">
                        <div></div>
                        <div class="d-flex justify-between">
                            <div class="vs-checkbox-con vs-checkbox-primary" style="padding: 0 12px">
                                <input type="checkbox" id="select-all" checked>
                                <span class="vs-checkbox">
                                    <span class="vs-checkbox--check">
                                        <i class="vs-icon feather icon-check"></i>
                                    </span>
                                </span>
                                <span class="">تحديد الكل</span>
                            </div>

                            <button type="submit" form="export-form" class="btn btn-outline-info">
                                <i class="feather icon-share-2 me-2"></i>  تصدير
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            @permission('employees.export')
                <form id="export-form" action="{{ route('employee.export') }}" method="POST">
                    @csrf
                    <div class="card">
                        <div class="card-body">
                            <h6 class="stk-table-head__title" style="background: var(--app-body-bg, #f8f8f8)">تصدير قسم</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <fieldset>
                                            <div class="vs-checkbox-con vs-checkbox-primary">
                                                <input type="checkbox" class="checkbox-option" name="fields[]" checked value="id">
                                                <span class="vs-checkbox">
                                                    <span class="vs-checkbox--check">
                                                        <i class="vs-icon feather icon-check"></i>
                                                    </span>
                                                </span>
                                                <span class="">المعرف</span>
                                            </div>
                                        </fieldset>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <fieldset>
                                            <div class="vs-checkbox-con vs-checkbox-primary">
                                                <input type="checkbox" class="checkbox-option" name="fields[]" checked value="created_by">
                                                <span class="vs-checkbox">
                                                    <span class="vs-checkbox--check">
                                                        <i class="vs-icon feather icon-check"></i>
                                                    </span>
                                                </span>
                                                <span class="">أضيفت بواسطة</span>
                                            </div>
                                        </fieldset>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <fieldset>
                                            <div class="vs-checkbox-con vs-checkbox-primary">
                                                <input type="checkbox" class="checkbox-option" name="fields[]" checked value="job_role_id">
                                                <span class="vs-checkbox">
                                                    <span class="vs-checkbox--check">
                                                        <i class="vs-icon feather icon-check"></i>
                                                    </span>
                                                </span>
                                                <span class="">الدور الوظيفي</span>
                                            </div>
                                        </fieldset>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <fieldset>
                                            <div class="vs-checkbox-con vs-checkbox-primary">
                                                <input type="checkbox" class="checkbox-option" name="fields[]" checked value="first_name">
                                                <span class="vs-checkbox">
                                                    <span class="vs-checkbox--check">
                                                        <i class="vs-icon feather icon-check"></i>
                                                    </span>
                                                </span>
                                                <span class="">الاسم الأول</span>
                                            </div>
                                        </fieldset>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <fieldset>
                                            <div class="vs-checkbox-con vs-checkbox-primary">
                                                <input type="checkbox" class="checkbox-option" name="fields[]" checked value="middle_name">
                                                <span class="vs-checkbox">
                                                    <span class="vs-checkbox--check">
                                                        <i class="vs-icon feather icon-check"></i>
                                                    </span>
                                                </span>
                                                <span class="">الاسم الاوسط</span>
                                            </div>
                                        </fieldset>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <fieldset>
                                            <div class="vs-checkbox-con vs-checkbox-primary">
                                                <input type="checkbox" class="checkbox-option" name="fields[]" checked value="nickname">
                                                <span class="vs-checkbox">
                                                    <span class="vs-checkbox--check">
                                                        <i class="vs-icon feather icon-check"></i>
                                                    </span>
                                                </span>
                                                <span class="">اللقب</span>
                                            </div>
                                        </fieldset>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <fieldset>
                                            <div class="vs-checkbox-con vs-checkbox-primary">
                                                <input type="checkbox" class="checkbox-option" name="fields[]" checked value="phone_number">
                                                <span class="vs-checkbox">
                                                    <span class="vs-checkbox--check">
                                                        <i class="vs-icon feather icon-check"></i>
                                                    </span>
                                                </span>
                                                <span class="">رقم الهاتف</span>
                                            </div>
                                        </fieldset>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <fieldset>
                                            <div class="vs-checkbox-con vs-checkbox-primary">
                                                <input type="checkbox" class="checkbox-option" name="fields[]" checked value="mobile_number">
                                                <span class="vs-checkbox">
                                                    <span class="vs-checkbox--check">
                                                        <i class="vs-icon feather icon-check"></i>
                                                    </span>
                                                </span>
                                                <span class="">رقم الجوال</span>
                                            </div>
                                        </fieldset>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <fieldset>
                                            <div class="vs-checkbox-con vs-checkbox-primary">
                                                <input type="checkbox" class="checkbox-option" name="fields[]" checked value="current_address_1">
                                                <span class="vs-checkbox">
                                                    <span class="vs-checkbox--check">
                                                        <i class="vs-icon feather icon-check"></i>
                                                    </span>
                                                </span>
                                                <span class="">العنوان 1</span>
                                            </div>
                                        </fieldset>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <fieldset>
                                            <div class="vs-checkbox-con vs-checkbox-primary">
                                                <input type="checkbox" class="checkbox-option" name="fields[]" checked value="current_address_2">
                                                <span class="vs-checkbox">
                                                    <span class="vs-checkbox--check">
                                                        <i class="vs-icon feather icon-check"></i>
                                                    </span>
                                                </span>
                                                <span class="">العنوان 2</span>
                                            </div>
                                        </fieldset>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <fieldset>
                                            <div class="vs-checkbox-con vs-checkbox-primary">
                                                <input type="checkbox" class="checkbox-option" name="fields[]" checked value="city">
                                                <span class="vs-checkbox">
                                                    <span class="vs-checkbox--check">
                                                        <i class="vs-icon feather icon-check"></i>
                                                    </span>
                                                </span>
                                                <span class="">المدينة</span>
                                            </div>
                                        </fieldset>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <fieldset>
                                            <div class="vs-checkbox-con vs-checkbox-primary">
                                                <input type="checkbox" class="checkbox-option" name="fields[]" checked value="region">
                                                <span class="vs-checkbox">
                                                    <span class="vs-checkbox--check">
                                                        <i class="vs-icon feather icon-check"></i>
                                                    </span>
                                                </span>
                                                <span class="">المنطقة</span>
                                            </div>
                                        </fieldset>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <fieldset>
                                            <div class="vs-checkbox-con vs-checkbox-primary">
                                                <input type="checkbox" class="checkbox-option" name="fields[]" checked value="postal_code">
                                                <span class="vs-checkbox">
                                                    <span class="vs-checkbox--check">
                                                        <i class="vs-icon feather icon-check"></i>
                                                    </span>
                                                </span>
                                                <span class="">الرمز البريدي</span>
                                            </div>
                                        </fieldset>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <fieldset>
                                            <div class="vs-checkbox-con vs-checkbox-primary">
                                                <input type="checkbox" class="checkbox-option" name="fields[]" checked value="email">
                                                <span class="vs-checkbox">
                                                    <span class="vs-checkbox--check">
                                                        <i class="vs-icon feather icon-check"></i>
                                                    </span>
                                                </span>
                                                <span class="">البريد الإلكتروني</span>
                                            </div>
                                        </fieldset>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <fieldset>
                                            <div class="vs-checkbox-con vs-checkbox-primary">
                                                <input type="checkbox" class="checkbox-option" name="fields[]" checked value="status">
                                                <span class="vs-checkbox">
                                                    <span class="vs-checkbox--check">
                                                        <i class="vs-icon feather icon-check"></i>
                                                    </span>
                                                </span>
                                                <span class="">الحالة</span>
                                            </div>
                                        </fieldset>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <fieldset>
                                            <div class="vs-checkbox-con vs-checkbox-primary">
                                                <input type="checkbox" class="checkbox-option" name="fields[]" checked value="created_at">
                                                <span class="vs-checkbox">
                                                    <span class="vs-checkbox--check">
                                                        <i class="vs-icon feather icon-check"></i>
                                                    </span>
                                                </span>
                                                <span class="">تاريخ الانشاء</span>
                                            </div>
                                        </fieldset>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <fieldset>
                                            <div class="vs-checkbox-con vs-checkbox-primary">
                                                <input type="checkbox" class="checkbox-option" name="fields[]" checked value="branch_id">
                                                <span class="vs-checkbox">
                                                    <span class="vs-checkbox--check">
                                                        <i class="vs-icon feather icon-check"></i>
                                                    </span>
                                                </span>
                                                <span class="">فرع</span>
                                            </div>
                                        </fieldset>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <fieldset>
                                            <div class="vs-checkbox-con vs-checkbox-primary">
                                                <input type="checkbox" class="checkbox-option" name="fields[]" checked value="gender">
                                                <span class="vs-checkbox">
                                                    <span class="vs-checkbox--check">
                                                        <i class="vs-icon feather icon-check"></i>
                                                    </span>
                                                </span>
                                                <span class="">النوع</span>
                                            </div>
                                        </fieldset>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <fieldset>
                                            <div class="vs-checkbox-con vs-checkbox-primary">
                                                <input type="checkbox" class="checkbox-option" name="fields[]" checked value="nationality_status">
                                                <span class="vs-checkbox">
                                                    <span class="vs-checkbox--check">
                                                        <i class="vs-icon feather icon-check"></i>
                                                    </span>
                                                </span>
                                                <span class="">حالة المواطنة</span>
                                            </div>
                                        </fieldset>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <fieldset>
                                            <div class="vs-checkbox-con vs-checkbox-primary">
                                                <input type="checkbox" class="checkbox-option" name="fields[]" checked value="created_at">
                                                <span class="vs-checkbox">
                                                    <span class="vs-checkbox--check">
                                                        <i class="vs-icon feather icon-check"></i>
                                                    </span>
                                                </span>
                                                <span class="">تاريخ الانشاء</span>
                                            </div>
                                        </fieldset>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <fieldset>
                                            <div class="vs-checkbox-con vs-checkbox-primary">
                                                <input type="checkbox" class="checkbox-option" name="fields[]" checked value="country">
                                                <span class="vs-checkbox">
                                                    <span class="vs-checkbox--check">
                                                        <i class="vs-icon feather icon-check"></i>
                                                    </span>
                                                </span>
                                                <span class="">الجنسيه</span>
                                            </div>
                                        </fieldset>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
    
                    <div class="card">
                        <div class="card-body">
                            <h6 class="stk-table-head__title" style="background: var(--app-body-bg, #f8f8f8)">معلومات الموظف</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <fieldset>
                                            <div class="vs-checkbox-con vs-checkbox-primary">
                                                <input type="checkbox" class="checkbox-option" name="fields[]" checked value="department_id">
                                                <span class="vs-checkbox">
                                                    <span class="vs-checkbox--check">
                                                        <i class="vs-icon feather icon-check"></i>
                                                    </span>
                                                </span>
                                                <span class="">القسم</span>
                                            </div>
                                        </fieldset>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <fieldset>
                                            <div class="vs-checkbox-con vs-checkbox-primary">
                                                <input type="checkbox" class="checkbox-option" name="fields[]" checked value="job_level_id">
                                                <span class="vs-checkbox">
                                                    <span class="vs-checkbox--check">
                                                        <i class="vs-icon feather icon-check"></i>
                                                    </span>
                                                </span>
                                                <span class="">المستوى الوظيفي</span>
                                            </div>
                                        </fieldset>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <fieldset>
                                            <div class="vs-checkbox-con vs-checkbox-primary">
                                                <input type="checkbox" class="checkbox-option" name="fields[]" checked value="job_type_id">
                                                <span class="vs-checkbox">
                                                    <span class="vs-checkbox--check">
                                                        <i class="vs-icon feather icon-check"></i>
                                                    </span>
                                                </span>
                                                <span class="">نوع الوظيفة</span>
                                            </div>
                                        </fieldset>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <fieldset>
                                            <div class="vs-checkbox-con vs-checkbox-primary">
                                                <input type="checkbox" class="checkbox-option" name="fields[]" checked value="job_title_id">
                                                <span class="vs-checkbox">
                                                    <span class="vs-checkbox--check">
                                                        <i class="vs-icon feather icon-check"></i>
                                                    </span>
                                                </span>
                                                <span class="">المسمى الوظيفي</span>
                                            </div>
                                        </fieldset>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <fieldset>
                                            <div class="vs-checkbox-con vs-checkbox-primary">
                                                <input type="checkbox" class="checkbox-option" name="fields[]" checked value="birth_date">
                                                <span class="vs-checkbox">
                                                    <span class="vs-checkbox--check">
                                                        <i class="vs-icon feather icon-check"></i>
                                                    </span>
                                                </span>
                                                <span class="">تاريخ الميلاد</span>
                                            </div>
                                        </fieldset>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <fieldset>
                                            <div class="vs-checkbox-con vs-checkbox-primary">
                                                <input type="checkbox" class="checkbox-option" name="fields[]" checked value="personal_email">
                                                <span class="vs-checkbox">
                                                    <span class="vs-checkbox--check">
                                                        <i class="vs-icon feather icon-check"></i>
                                                    </span>
                                                </span>
                                                <span class="">البريد الالكتروني الشخصي</span>
                                            </div>
                                        </fieldset>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <fieldset>
                                            <div class="vs-checkbox-con vs-checkbox-primary">
                                                <input type="checkbox" class="checkbox-option" name="fields[]" checked value="postal_code">
                                                <span class="vs-checkbox">
                                                    <span class="vs-checkbox--check">
                                                        <i class="vs-icon feather icon-check"></i>
                                                    </span>
                                                </span>
                                                <span class="">الرمز البريدي</span>
                                            </div>
                                        </fieldset>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <fieldset>
                                            <div class="vs-checkbox-con vs-checkbox-primary">
                                                <input type="checkbox" class="checkbox-option" name="fields[]" checked value="shift_id">
                                                <span class="vs-checkbox">
                                                    <span class="vs-checkbox--check">
                                                        <i class="vs-icon feather icon-check"></i>
                                                    </span>
                                                </span>
                                                <span class="">وردية الخضور</span>
                                            </div>
                                        </fieldset>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <fieldset>
                                            <div class="vs-checkbox-con vs-checkbox-primary">
                                                <input type="checkbox" class="checkbox-option" name="fields[]" checked value="leave_policy">
                                                <span class="vs-checkbox">
                                                    <span class="vs-checkbox--check">
                                                        <i class="vs-icon feather icon-check"></i>
                                                    </span>
                                                </span>
                                                <span class="">سياسة الاجازات</span>
                                            </div>
                                        </fieldset>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <fieldset>
                                            <div class="vs-checkbox-con vs-checkbox-primary">
                                                <input type="checkbox" class="checkbox-option" name="fields[]" checked value="direct_manager_id">
                                                <span class="vs-checkbox">
                                                    <span class="vs-checkbox--check">
                                                        <i class="vs-icon feather icon-check"></i>
                                                    </span>
                                                </span>
                                                <span class="">المدير المباشر</span>
                                            </div>
                                        </fieldset>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </form>
            @endpermission

        </div>
    </div>

@endsection

@section('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const selectAllCheckbox = document.getElementById('select-all');
            const checkboxes = document.querySelectorAll('.checkbox-option');

            selectAllCheckbox.addEventListener('change', function () {
                const isChecked = this.checked;
                checkboxes.forEach(checkbox => {
                    checkbox.checked = isChecked;
                });
            });

            checkboxes.forEach(checkbox => {
                checkbox.addEventListener('change', function () {
                    if (!this.checked) {
                        selectAllCheckbox.checked = false;
                    } else if ([...checkboxes].every(chk => chk.checked)) {
                        selectAllCheckbox.checked = true;
                    }
                });
            });
        });
    </script>
@endsection
