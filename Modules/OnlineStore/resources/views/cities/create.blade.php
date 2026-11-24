@extends('onlinestore::master')

@section('title', 'إضافة مدينة')

@section('content')
    <div class="content-body">

        @section('page-actions')
            <a href="{{ route('cities.index') }}" class="btn btn-outline-secondary">
                <i class="feather icon-x"></i> إلغاء
            </a>
            @if (hasPermission('online_store.geo.create'))
                <button type="submit" form="city_form" class="btn btn-primary">
                    <i class="feather icon-save"></i> حفظ
                </button>
            @endif
        @endsection

        @include('onlinestore::components.page-head', [
            'title' => 'إضافة مدينة',
            'icon' => 'map-pin',
            'sub' => 'مدينة جديدة تُتاح للشحن والفوترة في المتجر',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'المدن' => route('cities.index'),
                'إضافة' => null,
            ],
        ])

        @include('layouts.alerts.error')

        <form id="city_form" action="{{ route('cities.store') }}" method="POST">
            @csrf

            <div class="card">
                <div class="stk-table-head">
                    <h6 class="stk-table-head__title"><i class="feather icon-info"></i> بيانات المدينة</h6>
                </div>

                <div class="card-body">
                    <div class="row">
                        <div class="form-group col-md-6">
                            <label for="country_id" class="stk-req">الدولة</label>
                            <select name="country_id" id="country_id" class="form-control select2" required>
                                <option value="">اختر الدولة</option>
                                @foreach ($countries as $country)
                                    <option value="{{ $country->id }}"
                                        @selected(old('country_id', request('country_id')) == $country->id)>
                                        {{ $country->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('country_id')<small class="text-danger">{{ $message }}</small>@enderror
                            <x-create-first
                                :count="$countries->count()"
                                route="countries.create"
                                permission="online_store.geo.create"
                                label="دولة"
                                hint="المدينة تتبع دولة، ولا دول نشطة مسجلة بعد." />
                        </div>

                        <div class="form-group col-md-6">
                            <label for="name" class="stk-req">اسم المدينة</label>
                            <input type="text" name="name" id="name" class="form-control" value="{{ old('name') }}"
                                placeholder="مثال: الرياض" required>
                            @error('name')<small class="text-danger">{{ $message }}</small>@enderror
                        </div>
                    </div>

                    <div class="row">
                        <div class="form-group col-md-3">
                            <label for="status">مدينة نشطة</label>
                            <select name="status" id="status" class="form-control">
                                <option value="1" @selected(old('status', 1) == 1)>نشطة</option>
                                <option value="0" @selected((string) old('status', 1) === '0')>غير نشطة</option>
                            </select>
                            <small class="text-muted">إذا كانت المدينة نشطة، ستظهر في قوائم الاختيار</small>
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
            placeholder: 'اختر الدولة',
            allowClear: true,
            width: '100%'
        });
    }
});
</script>
@endsection
