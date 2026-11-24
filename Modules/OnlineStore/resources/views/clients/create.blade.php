@extends('onlinestore::master')

@section('title', 'إضافة عميل متجر')

@section('content')
    <div class="content-body">

        @section('page-actions')
            <a href="{{ route('store_clients.index') }}" class="btn btn-outline-secondary">
                <i class="feather icon-x"></i> إلغاء
            </a>
            @if (hasPermission('online_store.clients.create'))
                <button type="submit" form="client_form" class="btn btn-primary">
                    <i class="feather icon-save"></i> حفظ
                </button>
            @endif
        @endsection

        @include('onlinestore::components.page-head', [
            'title' => 'إضافة عميل متجر',
            'icon' => 'users',
            'sub' => 'عميل جديد لعملاء المتجر الإلكتروني',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'عملاء المتجر' => route('store_clients.index'),
                'إضافة' => null,
            ],
        ])

        @include('layouts.alerts.error')

        <form id="client_form" action="{{ route('store_clients.store') }}" method="POST">
            @csrf

            <div class="card">
                <div class="stk-table-head">
                    <h6 class="stk-table-head__title"><i class="feather icon-user"></i> البيانات الأساسية</h6>
                </div>

                <div class="card-body">
                    <div class="row">
                        <div class="form-group col-md-4">
                            <label for="first_name" class="stk-req">الاسم الأول</label>
                            <input type="text" name="first_name" id="first_name" class="form-control" value="{{ old('first_name') }}" required>
                            @error('first_name')<small class="text-danger">{{ $message }}</small>@enderror
                        </div>

                        <div class="form-group col-md-4">
                            <label for="last_name">الاسم الأخير</label>
                            <input type="text" name="last_name" id="last_name" class="form-control" value="{{ old('last_name') }}">
                            @error('last_name')<small class="text-danger">{{ $message }}</small>@enderror
                        </div>

                        <div class="form-group col-md-4">
                            <label for="trade_name">الاسم التجاري</label>
                            <input type="text" name="trade_name" id="trade_name" class="form-control" value="{{ old('trade_name') }}">
                            @error('trade_name')<small class="text-danger">{{ $message }}</small>@enderror
                        </div>
                    </div>

                    <div class="row">
                        <div class="form-group col-md-4">
                            <label for="email" class="stk-req">البريد الإلكتروني</label>
                            <input type="email" name="email" id="email" class="form-control" value="{{ old('email') }}" required dir="ltr">
                            @error('email')<small class="text-danger">{{ $message }}</small>@enderror
                        </div>

                        <div class="form-group col-md-4">
                            <label for="mobile">رقم الجوال</label>
                            <input type="text" name="mobile" id="mobile" class="form-control" value="{{ old('mobile') }}" dir="ltr">
                            @error('mobile')<small class="text-danger">{{ $message }}</small>@enderror
                        </div>

                        <div class="form-group col-md-4">
                            <label for="phone">رقم الهاتف</label>
                            <input type="text" name="phone" id="phone" class="form-control" value="{{ old('phone') }}" dir="ltr">
                            @error('phone')<small class="text-danger">{{ $message }}</small>@enderror
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="stk-table-head">
                    <h6 class="stk-table-head__title"><i class="feather icon-map-pin"></i> عنوان التوصيل الأول (اختياري)</h6>
                </div>

                <div class="card-body">
                    <p class="text-muted small mb-2">يمكنك إضافة عناوين إضافية بعد إنشاء العميل من صفحة التفاصيل.</p>

                    <div class="row">
                        <div class="form-group col-md-3">
                            <label for="address_title">عنوان التسمية</label>
                            <input type="text" name="address_title" id="address_title" class="form-control"
                                value="{{ old('address_title', 'المنزل') }}" placeholder="مثال: المنزل، العمل">
                        </div>

                        <div class="form-group col-md-3">
                            <label for="address_receiver_name">اسم المستلم</label>
                            <input type="text" name="address_receiver_name" id="address_receiver_name" class="form-control"
                                value="{{ old('address_receiver_name') }}" placeholder="اترك فارغاً لاستخدام اسم العميل">
                        </div>

                        <div class="form-group col-md-3">
                            <label for="address_receiver_phone">هاتف المستلم</label>
                            <input type="text" name="address_receiver_phone" id="address_receiver_phone" class="form-control"
                                value="{{ old('address_receiver_phone') }}" placeholder="اترك فارغاً لاستخدام رقم العميل" dir="ltr">
                        </div>

                        <div class="form-group col-md-3">
                            <label for="address_country">الدولة</label>
                            <input type="text" name="address_country" id="address_country" class="form-control"
                                value="{{ old('address_country', 'المملكة العربية السعودية') }}">
                        </div>
                    </div>

                    <div class="row">
                        <div class="form-group col-md-3">
                            <label for="address_city">المدينة</label>
                            <input type="text" name="address_city" id="address_city" class="form-control" value="{{ old('address_city') }}">
                        </div>

                        <div class="form-group col-md-3">
                            <label for="address_region">المنطقة</label>
                            <input type="text" name="address_region" id="address_region" class="form-control" value="{{ old('address_region') }}">
                        </div>

                        <div class="form-group col-md-3">
                            <label for="address_district">الحي</label>
                            <input type="text" name="address_district" id="address_district" class="form-control" value="{{ old('address_district') }}">
                        </div>

                        <div class="form-group col-md-3">
                            <label for="address_street">الشارع</label>
                            <input type="text" name="address_street" id="address_street" class="form-control" value="{{ old('address_street') }}">
                        </div>
                    </div>

                    <div class="row">
                        <div class="form-group col-md-3">
                            <label for="address_building_number">رقم المبنى</label>
                            <input type="text" name="address_building_number" id="address_building_number" class="form-control" value="{{ old('address_building_number') }}">
                        </div>

                        <div class="form-group col-md-3">
                            <label for="address_floor">الطابق</label>
                            <input type="text" name="address_floor" id="address_floor" class="form-control" value="{{ old('address_floor') }}">
                        </div>

                        <div class="form-group col-md-3">
                            <label for="address_apartment">الشقة</label>
                            <input type="text" name="address_apartment" id="address_apartment" class="form-control" value="{{ old('address_apartment') }}">
                        </div>

                        <div class="form-group col-md-3">
                            <label for="address_postal_code">الرمز البريدي</label>
                            <input type="text" name="address_postal_code" id="address_postal_code" class="form-control" value="{{ old('address_postal_code') }}" dir="ltr">
                        </div>
                    </div>

                    <div class="row">
                        <div class="form-group col-12">
                            <label for="address_notes">ملاحظات العنوان</label>
                            <input type="text" name="address_notes" id="address_notes" class="form-control"
                                value="{{ old('address_notes') }}" placeholder="معلومات إضافية للتوصيل">
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="stk-table-head">
                    <h6 class="stk-table-head__title"><i class="feather icon-file-text"></i> ملاحظات</h6>
                </div>

                <div class="card-body">
                    <div class="row">
                        <div class="form-group col-12">
                            <label for="notes">ملاحظات</label>
                            <textarea name="notes" id="notes" class="form-control" rows="3">{{ old('notes') }}</textarea>
                            @error('notes')<small class="text-danger">{{ $message }}</small>@enderror
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
@endsection

@section('scripts')
<script>
$(document).ready(function() {
    if ($.fn.select2) {
        $('.select2').select2({
            dir: 'rtl',
            language: {
                noResults: function() {
                    return "لا توجد نتائج";
                }
            },
            allowClear: true,
            width: '100%'
        });
    }
});
</script>
@endsection
