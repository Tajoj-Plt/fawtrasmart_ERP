@extends('onlinestore::master')

@section('title', 'تعديل الدولة')

@section('content')
    <div class="content-body">

        @section('page-actions')
            <a href="{{ route('countries.index') }}" class="btn btn-outline-secondary">
                <i class="feather icon-x"></i> إلغاء
            </a>
            @if (hasPermission('online_store.geo.edit'))
                <button type="submit" form="country_form" class="btn btn-primary">
                    <i class="feather icon-save"></i> تحديث
                </button>
            @endif
        @endsection

        @include('onlinestore::components.page-head', [
            'title' => 'تعديل الدولة',
            'icon' => 'globe',
            'sub' => $country->name,
            'crumbs' => [
                'الرئيسية' => url('/'),
                'الدول' => route('countries.index'),
                'تعديل' => null,
            ],
        ])

        @include('layouts.alerts.error')

        <form id="country_form" action="{{ route('countries.update', $country) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="card">
                <div class="stk-table-head">
                    <h6 class="stk-table-head__title"><i class="feather icon-info"></i> بيانات الدولة</h6>
                </div>

                <div class="card-body">
                    <div class="row">
                        <div class="form-group col-md-4">
                            <label for="name" class="stk-req">اسم الدولة</label>
                            <input type="text" name="name" id="name" class="form-control"
                                value="{{ old('name', $country->name) }}" placeholder="مثال: المملكة العربية السعودية" required>
                            @error('name')<small class="text-danger">{{ $message }}</small>@enderror
                        </div>

                        <div class="form-group col-md-4">
                            <label for="code" class="stk-req">كود الدولة</label>
                            <input type="text" name="code" id="code" class="form-control"
                                value="{{ old('code', $country->code) }}" placeholder="مثال: SA" maxlength="2"
                                style="text-transform: uppercase;" dir="ltr" required>
                            <small class="text-muted">كود ISO من حرفين (مثال: SA, EG, AE)</small>
                            @error('code')<small class="text-danger">{{ $message }}</small>@enderror
                        </div>

                        <div class="form-group col-md-4">
                            <label for="phone_code" class="stk-req">كود الهاتف</label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text">+</span>
                                </div>
                                <input type="text" name="phone_code" id="phone_code" class="form-control"
                                    value="{{ old('phone_code', $country->phone_code) }}" placeholder="966" dir="ltr" required>
                            </div>
                            <small class="text-muted">كود الاتصال الدولي (مثال: 966, 20, 971)</small>
                            @error('phone_code')<small class="text-danger">{{ $message }}</small>@enderror
                        </div>
                    </div>

                    <div class="row">
                        <div class="form-group col-md-3">
                            <label for="status">دولة نشطة</label>
                            <select name="status" id="status" class="form-control">
                                <option value="1" @selected(old('status', $country->status) == 1)>نشط</option>
                                <option value="0" @selected(old('status', $country->status) == 0)>غير نشط</option>
                            </select>
                            <small class="text-muted">إذا كانت الدولة نشطة، ستظهر في قوائم الاختيار</small>
                        </div>

                        <div class="form-group col-md-3">
                            <label for="is_default">دولة افتراضية</label>
                            <select name="is_default" id="is_default" class="form-control">
                                <option value="0" @selected(old('is_default', $country->is_default) == 0)>لا</option>
                                <option value="1" @selected(old('is_default', $country->is_default) == 1)>نعم</option>
                            </select>
                            <small class="text-muted">الدولة الافتراضية للمتجر</small>
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
    $('input[name="code"]').on('input', function() {
        $(this).val($(this).val().toUpperCase().replace(/[^A-Z]/g, ''));
    });

    $('input[name="phone_code"]').on('input', function() {
        $(this).val($(this).val().replace(/[^0-9]/g, ''));
    });
});
</script>
@endsection
