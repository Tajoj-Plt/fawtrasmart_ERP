@extends('hr::master')

@section('title', 'قوائم العطلات')

@section('content')
    <!-- Header Section -->
    {{-- ترويسةُ نظام التصميم — كانت مكتوبةً يدوياً بستّة مستويات
         من `div` وفتاتِ تنقّلٍ رابطُه الأوّل فارغ. --}}
    @include('stock::components.page-head', [
        'title' => 'قوائم العطلات',
        'crumbs' => ['الرئيسية' => null, 'إضافة' => null],
    ])

    <div class="card">
    <div class="card-body">
        <!-- السطر الأول: زر أضف وكلمة بحث -->
        <div class="row mb-3 align-items-right">
        <div class="col-md-6 text-md-start">
                <span class="h5">بحث</span>
            </div>
            <div class="col-md-6">
                {{-- كان المسارُ مكتوباً `Attendance.attendance-days.create` ولا
                     وجودَ له في جدول المسارات — فالصفحةُ كانت ترمي
                     RouteNotFoundException عند العرض لا عند النقر. والاسمُ
                     المسجَّل فعلاً هو `attendanceDays.create`. --}}
                @permission('holidays.create')
                    <a href="{{ route('holiday_lists.create') }}" class="btn btn-outline-success">
                        <i class="feather icon-plus me-2"></i>أضف قائمة العطلات
                    </a>
                @endpermission
            </div>
        </div>

        <!-- السطر الثاني: حقل البحث -->
        <form id="search-form" method="GET">
            <div class="row">
                <div class="col-12">
                    <label for="employee-search" class="form-label">الموظف:</label>
                    <input 
                        type="text" 
                        class="form-control" 
                        id="employee-search" 
                        name="employee" 
                        placeholder="البحث باسم الموظف أو المعرف أو البريد الإلكتروني">
                </div>
            </div>
            <div class="d-flex justify-content-start mt-3">
                <!-- زر البحث -->
                <button type="submit" class="btn btn-primary me-2 waves-effect waves-light">بحث</button>

                <!-- زر الإلغاء -->
                <button type="reset" class="btn btn-outline-warning waves-effect waves-light">إلغاء</button>
            </div>
        </form>
    </div>
</div>
<div class="card mt-5">
        <table class="table table-bordered table-striped">
            <thead class="table-light">
                <tr>
                    <th scope="col">الاسم</th>
                    <th scope="col">إجمالي الأيام</th>
                    <th scope="col">ترتيب بواسطة</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>قائمة العطلات العامة #1</td>
                    <td>0</td>
                    <td>
                    <div class="btn-group">
                            <button class="btn bg-gradient-info feather icon-more-vertical" type="button" data-toggle="dropdown" data-display="static" aria-haspopup="true" aria-expanded="false"></button>
                            <div class="dropdown-menu dropdown-menu-right">
                                <a class="dropdown-item" href="#"><i class="feather icon-eye me-2 text-primary"></i>عرض</a>
                                <a class="dropdown-item" href="#"><i class="feather icon-edit-2 text-primary me-2"></i>تعديل</a>
                                <div class="dropdown-divider"></div>
                                <button class="dropdown-item text-danger delete-client">
                                    <i class="feather icon-trash-2 me-2"></i>حذف
                                </button>
                            </div>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>








    @endsection