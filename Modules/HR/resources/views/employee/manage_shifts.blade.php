@extends('hr::master')

@section('title')
    أدارة الورديات
@stop

@section('content')       
{{-- ترويسةُ نظام التصميم — كانت مكتوبةً يدوياً بستّة مستويات
     من `div` وفتاتِ تنقّلٍ رابطُه الأوّل فارغ. --}}
@include('stock::components.page-head', [
    'title' => 'أدارة الورديات',
    'crumbs' => ['الرئيسيه' => null, 'عرض' => null],
])
    <div class="card">
            <div class="card-body">
                <div class="stk-table-head">

                    <div class="d-flex justify-content-between align-items-center flex-wrap">
                        <div>بحث في الورديات</div>
                        <div>
                            @permission('shifts.create')
                                <a href="{{ route('add_shift') }}" class="btn btn-outline-success">
                                    <i class="feather icon-plus me-2"></i>أضافة وردية
                                </a>
                            @endpermission

                       
                    </div>
                </div>
            </div>

            <div class="card-body">
                <form class="form">
                    <div class="form-body row">
                        <div class="form-group col-md-8">
                            <select name="" class="form-control" id="">
                                <option value="">أبحث بواسطة أسم الوردية </option>
                                <a class="dropdown-item" href="#">Option 1</a>
                            </select>
                        </div>


                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary mr-1 waves-effect waves-light">بحث</button>

                        <button type="reset"
                            class="btn btn-outline-warning waves-effect waves-light">ألغاء</button>
                    </div>
                </form>
            </div>


        </div>
<div class="card">
    <div class="card-body">
        <!-- عناوين الأعمدة -->
        <div class="row border-bottom py-2 bg-light font-weight-bold text-center">
            <div class="col-md-4">الاسم</div>
            <div class="col-md-4">أيام العطلات</div>
            <div class="col-md-4">الإجراءات</div>
        </div>

        <!-- بيانات الصف الأول -->
        <div class="row py-2 text-center">
            <div class="col-md-4">محمد أحمد</div>
            <div class="col-md-4">الجمعة</div>
            <div class="col-md-4">
                <div class="dropdown">
                    <button class="btn bg-gradient-info feather icon-more-vertical mr-1 mb-1" type="button"
                        id="dropdownMenuButton1" data-toggle="dropdown" data-display="static" aria-haspopup="true"
                        aria-expanded="false"></button>
                    <div class="dropdown-menu dropdown-menu-right" aria-labelledby="dropdownMenuButton1">
                        <a class="dropdown-item" href="#"><i class="feather icon-eye me-2 text-primary"></i>عرض</a>
                        <a class="dropdown-item" href="#"><i class="feather icon-edit-2 me-2 text-success"></i>تعديل</a>
                        <a class="dropdown-item" href="#"><i class="feather icon-printer me-2 text-dark"></i>تعطيل</a>
                        <a class="dropdown-item" href="#"><i class="feather icon-mail me-2 text-warning"></i>إرسال بيانات الدخول</a>
                        <a class="dropdown-item" href="#"><i class="feather icon-credit-card me-2 text-info"></i>تغيير كلمة المرور</a>
                        <a class="dropdown-item text-danger" href="#"><i class="feather icon-trash-2 me-2"></i>حذف</a>
                    </div>
                </div>
            </div>
        </div>

        <!-- بيانات الصف الثاني -->
        <div class="row py-2 text-center">
            <div class="col-md-4">أحمد علي</div>
            <div class="col-md-4">الأحد</div>
            <div class="col-md-4">
                <div class="dropdown">
                    <button class="btn bg-gradient-info feather icon-more-vertical mr-1 mb-1" type="button"
                        id="dropdownMenuButton2" data-toggle="dropdown" data-display="static" aria-haspopup="true"
                        aria-expanded="false"></button>
                    <div class="dropdown-menu dropdown-menu-right" aria-labelledby="dropdownMenuButton2">
                        <a class="dropdown-item" href="#"><i class="feather icon-eye me-2 text-primary"></i>عرض</a>
                        <a class="dropdown-item" href="#"><i class="feather icon-edit-2 me-2 text-success"></i>تعديل</a>
                        <a class="dropdown-item" href="#"><i class="feather icon-printer me-2 text-dark"></i>تعطيل</a>
                        <a class="dropdown-item" href="#"><i class="feather icon-mail me-2 text-warning"></i>إرسال بيانات الدخول</a>
                        <a class="dropdown-item" href="#"><i class="feather icon-credit-card me-2 text-info"></i>تغيير كلمة المرور</a>
                        <a class="dropdown-item text-danger" href="#"><i class="feather icon-trash-2 me-2"></i>حذف</a>
                    </div>
                </div>
            </div>
        </div>

        <!-- بيانات الصف الثالث -->
        <div class="row py-2 text-center">
            <div class="col-md-4">سارة محمود</div>
            <div class="col-md-4">السبت</div>
            <div class="col-md-4">
                <div class="dropdown">
                    <button class="btn bg-gradient-info feather icon-more-vertical mr-1 mb-1" type="button"
                        id="dropdownMenuButton3" data-toggle="dropdown" data-display="static" aria-haspopup="true"
                        aria-expanded="false"></button>
                    <div class="dropdown-menu dropdown-menu-right" aria-labelledby="dropdownMenuButton3">
                        <a class="dropdown-item" href="#"><i class="feather icon-eye me-2 text-primary"></i>عرض</a>
                        <a class="dropdown-item" href="#"><i class="feather icon-edit-2 me-2 text-success"></i>تعديل</a>
                        <a class="dropdown-item" href="#"><i class="feather icon-printer me-2 text-dark"></i>تعطيل</a>
                        <a class="dropdown-item" href="#"><i class="feather icon-mail me-2 text-warning"></i>إرسال بيانات الدخول</a>
                        <a class="dropdown-item" href="#"><i class="feather icon-credit-card me-2 text-info"></i>تغيير كلمة المرور</a>
                        <a class="dropdown-item text-danger" href="#"><i class="feather icon-trash-2 me-2"></i>حذف</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>


    
        @endsection