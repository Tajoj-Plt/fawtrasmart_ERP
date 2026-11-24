@extends('rentalmanagement::realestate.master')

@php
    use Modules\RentalManagement\Support\RealEstateVocabulary as V;
    $editing = $listing->exists;
    $pageTitle = $editing ? 'تعديل الطلب '.$listing->code : 'طلب عميل جديد';
    $currency = class_exists(\Modules\RentalManagement\Support\RealEstateMoney::class) && method_exists(\Modules\RentalManagement\Support\RealEstateMoney::class, 'code')
        ? \Modules\RentalManagement\Support\RealEstateMoney::code()
        : (\App\Support\CurrencySettings::code() ?? '');
@endphp

@section('title', $pageTitle)

@section('content')
    <div class="content-body">
        @include('stock::components.page-head', [
            'title' => $pageTitle,
            'icon' => $editing ? 'edit' : 'search',
            'sub' => 'ما يبحث عنه العميل: نوع العقار ومكانه وميزانيته — وتظهر العروض المطابقة في صفحة الطلب',
            'crumbs' => ['الرئيسية' => url('/'), 'طلبات العملاء' => route('realestate.listings.requests'), $editing ? 'تعديل' : 'إضافة' => null],
            'actions' => '<a href="'.($editing ? route('realestate.listings.show', $listing->id) : route('realestate.listings.requests')).'" class="btn btn-outline-secondary"><i class="feather icon-x"></i> إلغاء</a>'
                .'<button type="submit" form="requestForm" class="btn btn-primary"><i class="feather icon-check"></i> حفظ</button>',
        ])

        @include('layouts.alerts.error')
        @include('stock::components.form-errors')

        <form id="requestForm" method="POST" novalidate data-re-validate
              action="{{ $editing ? route('realestate.listings.update', $listing->id) : route('realestate.listings.store') }}">
            @csrf
            @if ($editing) @method('PUT') @endif
            <input type="hidden" name="listing_kind" value="request">
            <input type="hidden" name="rent_period" value="{{ old('rent_period', $listing->rent_period ?: 'yearly') }}">

            <div class="row">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title"><i class="feather icon-user"></i> العميل الطالب</h4>
                            <span class="badge badge-light-secondary stk-num">{{ $editing ? $listing->code : $code }}</span>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-12">
                                    <div class="form-group">
                                        <label for="client_id">العميل (من ملفّ العملاء)</label>
                                        @include('rentalmanagement::realestate.components.client-select', ['name' => 'client_id', 'selected' => $listing->client_id, 'placeholder' => 'ابحث عن العميل الطالب…'])
                                        <small class="text-muted">طلب العميل المسجَّل يُضاف تلقائياً إلى «اهتمامات العملاء».</small>
                                    </div>
                                </div>
                                <div class="col-12"><p class="text-muted mb-75">أو زائر غير مسجَّل (الاسم والهاتف معاً):</p></div>
                                <div class="col-md-7">
                                    <div class="form-group">
                                        <label for="contact_name">اسم الزائر</label>
                                        <input type="text" id="contact_name" name="contact_name" class="form-control" maxlength="191" value="{{ old('contact_name', $listing->contact_name) }}">
                                    </div>
                                </div>
                                <div class="col-md-5">
                                    <div class="form-group">
                                        <label for="contact_phone">هاتفه</label>
                                        <input type="text" id="contact_phone" name="contact_phone" class="form-control" dir="ltr" maxlength="40" inputmode="tel" value="{{ old('contact_phone', $listing->contact_phone) }}">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header"><h4 class="card-title"><i class="feather icon-search"></i> ما يبحث عنه</h4></div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4 col-6">
                                    <div class="form-group">
                                        <label for="listing_type" class="stk-req">الغرض</label>
                                        <select id="listing_type" name="listing_type" class="form-control" data-required="1">
                                            <option value="rent" @selected(old('listing_type', $listing->listing_type) === 'rent')>إيجار</option>
                                            <option value="sale" @selected(old('listing_type', $listing->listing_type) === 'sale')>شراء</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4 col-6">
                                    <div class="form-group">
                                        <label for="property_type">نوع العقار المطلوب</label>
                                        <select id="property_type" name="property_type" class="form-control">
                                            <option value="">أيّ نوع</option>
                                            @foreach (V::UNIT_TYPES as $value => $meta) <option value="{{ $value }}" @selected(old('property_type', $listing->property_type) === $value)>{{ $meta['label'] }}</option> @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4 col-6">
                                    <div class="form-group">
                                        <label for="bedrooms">غرف النوم (على الأقلّ)</label>
                                        <input type="number" min="0" max="100" id="bedrooms" name="bedrooms" class="form-control" value="{{ old('bedrooms', $listing->bedrooms) }}">
                                    </div>
                                </div>
                                <div class="col-md-4 col-6">
                                    <div class="form-group">
                                        <label for="city">المدينة</label>
                                        <input type="text" id="city" name="city" class="form-control" maxlength="100" value="{{ old('city', $listing->city) }}">
                                    </div>
                                </div>
                                <div class="col-md-4 col-6">
                                    <div class="form-group">
                                        <label for="district">الحي</label>
                                        <input type="text" id="district" name="district" class="form-control" maxlength="100" value="{{ old('district', $listing->district) }}">
                                    </div>
                                </div>
                                <div class="col-md-4 col-6">
                                    <div class="form-group">
                                        <label for="area_sqm">المساحة المطلوبة م²</label>
                                        <input type="number" step="0.01" min="0" id="area_sqm" name="area_sqm" class="form-control" value="{{ old('area_sqm', $listing->area_sqm) }}">
                                    </div>
                                </div>
                                <div class="col-md-4 col-6">
                                    <div class="form-group">
                                        <label for="budget_min">الميزانية من ({{ $currency }})</label>
                                        <input type="number" step="0.01" min="0" id="budget_min" name="budget_min" class="form-control" dir="ltr" value="{{ old('budget_min', $listing->budget_min) }}">
                                    </div>
                                </div>
                                <div class="col-md-4 col-6">
                                    <div class="form-group">
                                        <label for="budget_max">الميزانية إلى ({{ $currency }})</label>
                                        <input type="number" step="0.01" min="0" id="budget_max" name="budget_max" class="form-control" dir="ltr" value="{{ old('budget_max', $listing->budget_max) }}">
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="form-group mb-0">
                                        <label for="description">تفاصيل الطلب</label>
                                        <textarea id="description" name="description" rows="3" class="form-control" maxlength="10000" placeholder="مواصفات إضافية يطلبها العميل: الدور، المواقف، قرب الخدمات…">{{ old('description', $listing->description) }}</textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header"><h4 class="card-title"><i class="feather icon-flag"></i> المتابعة</h4></div>
                        <div class="card-body">
                            <div class="form-group">
                                <label for="title">عنوان الطلب</label>
                                <input type="text" id="title" name="title" class="form-control" maxlength="191" value="{{ old('title', $listing->title) }}" placeholder="يُكتب تلقائياً من المواصفات إن تُرك فارغاً">
                            </div>
                            <div class="form-group">
                                <label for="assigned_agent_id">المتابِع</label>
                                <select id="assigned_agent_id" name="assigned_agent_id" class="form-control select2">
                                    <option value="">اختر مستخدماً…</option>
                                    @foreach ($users as $user) <option value="{{ $user->id }}" @selected((string) old('assigned_agent_id', $listing->assigned_agent_id) === (string) $user->id)>{{ $user->name }}</option> @endforeach
                                </select>
                            </div>
                            <div class="form-group mb-0">
                                <label for="expiry_date">يبقى الطلب قائماً حتى</label>
                                <input type="date" id="expiry_date" name="expiry_date" class="form-control" value="{{ old('expiry_date', $listing->expiry_date?->format('Y-m-d')) }}">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end mb-2" style="gap:.5rem">
                <a href="{{ $editing ? route('realestate.listings.show', $listing->id) : route('realestate.listings.requests') }}" class="btn btn-outline-secondary">إلغاء</a>
                <button type="submit" class="btn btn-primary"><i class="feather icon-check"></i> حفظ الطلب</button>
            </div>
        </form>
    </div>
@endsection

@section('scripts')
    @include('rentalmanagement::realestate.components.list-scripts')
    <script>
        $(function () {
            // صاحب الطلب: عميلٌ مسجَّل، أو زائرٌ باسمه وهاتفه — يسبق الفحص العام
            $('#requestForm').on('submit', function (e) {
                const visitor = $('#contact_name').val().trim() && $('#contact_phone').val().trim();
                if ($('#client_id').val() || visitor) return;

                e.preventDefault();
                e.stopImmediatePropagation();
                Swal.fire({ icon: 'warning', title: 'العميل الطالب', text: 'حدّد العميل من ملفّ العملاء، أو اكتب اسم الزائر وهاتفه.' });
            });
        });
    </script>
@endsection
