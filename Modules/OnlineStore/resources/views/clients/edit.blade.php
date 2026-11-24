@extends('onlinestore::master')

@section('title', 'تعديل بيانات العميل')

@section('content')
    <div class="content-body">

        @section('page-actions')
            <a href="{{ route('store_clients.index') }}" class="btn btn-outline-secondary">
                <i class="feather icon-x"></i> إلغاء
            </a>
            @if (hasPermission('online_store.clients.edit'))
                <button type="submit" form="client_form" class="btn btn-primary">
                    <i class="feather icon-save"></i> حفظ التغييرات
                </button>
            @endif
        @endsection

        @include('onlinestore::components.page-head', [
            'title' => 'تعديل بيانات العميل',
            'icon' => 'users',
            'sub' => trim($storeClient->first_name . ' ' . $storeClient->last_name),
            'crumbs' => [
                'الرئيسية' => url('/'),
                'عملاء المتجر' => route('store_clients.index'),
                'تعديل' => null,
            ],
        ])

        @include('layouts.alerts.error')

        <form id="client_form" action="{{ route('store_clients.update', $storeClient) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="card">
                <div class="stk-table-head">
                    <h6 class="stk-table-head__title"><i class="feather icon-user"></i> البيانات الأساسية</h6>
                </div>

                <div class="card-body">
                    <div class="row">
                        <div class="form-group col-md-4">
                            <label for="first_name" class="stk-req">الاسم الأول</label>
                            <input type="text" name="first_name" id="first_name" class="form-control" value="{{ old('first_name', $storeClient->first_name) }}" required>
                            @error('first_name')<small class="text-danger">{{ $message }}</small>@enderror
                        </div>

                        <div class="form-group col-md-4">
                            <label for="last_name">الاسم الأخير</label>
                            <input type="text" name="last_name" id="last_name" class="form-control" value="{{ old('last_name', $storeClient->last_name) }}">
                            @error('last_name')<small class="text-danger">{{ $message }}</small>@enderror
                        </div>

                        <div class="form-group col-md-4">
                            <label for="trade_name">الاسم التجاري</label>
                            <input type="text" name="trade_name" id="trade_name" class="form-control" value="{{ old('trade_name', $storeClient->trade_name) }}">
                            @error('trade_name')<small class="text-danger">{{ $message }}</small>@enderror
                        </div>
                    </div>

                    <div class="row">
                        <div class="form-group col-md-4">
                            <label for="email" class="stk-req">البريد الإلكتروني</label>
                            <input type="email" name="email" id="email" class="form-control" value="{{ old('email', $storeClient->email) }}" required dir="ltr">
                            @error('email')<small class="text-danger">{{ $message }}</small>@enderror
                        </div>

                        <div class="form-group col-md-4">
                            <label for="mobile">رقم الجوال</label>
                            <input type="text" name="mobile" id="mobile" class="form-control" value="{{ old('mobile', $storeClient->mobile) }}" dir="ltr">
                            @error('mobile')<small class="text-danger">{{ $message }}</small>@enderror
                        </div>

                        <div class="form-group col-md-4">
                            <label for="phone">رقم الهاتف</label>
                            <input type="text" name="phone" id="phone" class="form-control" value="{{ old('phone', $storeClient->phone) }}" dir="ltr">
                            @error('phone')<small class="text-danger">{{ $message }}</small>@enderror
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="stk-table-head">
                    <h6 class="stk-table-head__title"><i class="feather icon-map-pin"></i> عناوين التوصيل</h6>
                </div>

                <div class="card-body">
                    <p class="text-muted small mb-2">
                        يمكنك إدارة عناوين العميل من
                        <a href="{{ route('store_clients.show', $storeClient) }}">صفحة تفاصيل العميل</a>.
                    </p>

                    @if ($storeClient->addresses->count() > 0)
                        <div class="table-responsive mb-2">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>التسمية</th>
                                        <th>المدينة</th>
                                        <th>الشارع</th>
                                        <th class="text-center">الحالة</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($storeClient->addresses as $address)
                                        <tr>
                                            <td>{{ $address->title }}</td>
                                            <td>{{ $address->city }}</td>
                                            <td>{{ $address->street }}</td>
                                            <td class="text-center">
                                                @if ($address->is_default)
                                                    <span class="badge badge-light-primary">افتراضي</span>
                                                @endif
                                                @if ($address->is_active)
                                                    <span class="stk-state stk-state--on">نشط</span>
                                                @else
                                                    <span class="stk-state stk-state--off">غير نشط</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="stk-empty">
                            <span class="stk-empty__icon"><i class="feather icon-map-pin"></i></span>
                            <h6 class="stk-empty__title">لا توجد عناوين مسجلة</h6>
                            <p class="stk-empty__hint">لا توجد عناوين توصيل مسجلة لهذا العميل.</p>
                        </div>
                    @endif
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
                            <textarea name="notes" id="notes" class="form-control" rows="3">{{ old('notes', $storeClient->notes) }}</textarea>
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
