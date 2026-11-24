@extends('rentalmanagement::realestate.master')

@section('title', 'المباني والمجمعات')

@section('content')
    @php
        use Modules\RentalManagement\Support\RealEstateVocabulary as V;
        $hasFilters = request()->hasAny(['city', 'entity_type', 'usage_type', 'status', 'owner_id', 'complex_id']);
    @endphp

    <div class="content-body">

        @section('page-actions')
            <div class="btn-group re-view-toggle" role="group" aria-label="طريقة العرض">
                <button type="button" class="btn btn-outline-secondary {{ $view === 'cards' ? 'active' : '' }}" data-view="cards" title="بطاقات"><i class="feather icon-grid"></i></button>
                <button type="button" class="btn btn-outline-secondary {{ $view === 'table' ? 'active' : '' }}" data-view="table" title="جدول"><i class="feather icon-list"></i></button>
            </div>
            <a href="{{ route('realestate.buildings.map') }}" class="btn btn-outline-primary"><i class="feather icon-map"></i> كل العقارات على الخريطة</a>
            <a href="{{ route('realestate.buildings.complexes.index') }}" class="btn btn-outline-secondary"><i class="feather icon-layers"></i> المجمعات</a>
            @permission('realestate.buildings.create')
                <a href="{{ route('realestate.buildings.create') }}" class="btn btn-primary"><i class="feather icon-plus"></i> إضافة عقار</a>
            @endpermission
        @endsection

        @include('stock::components.page-head', [
            'title' => 'المباني والمجمعات',
            'icon' => 'home',
            'sub' => 'الأصول العقارية: المجمع ← المبنى ← الطابق ← الوحدة',
            'crumbs' => ['الرئيسية' => url('/'), 'إدارة العقارات' => route('realestate.dashboard'), 'المباني والمجمعات' => null],
        ])

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')

        @include('rentalmanagement::realestate.components.kpis', ['items' => [
            ['label' => 'إجمالي العقارات', 'value' => number_format($stats['total']), 'icon' => 'home', 'tone' => 'info'],
            ['label' => 'نشطة', 'value' => number_format($stats['active']), 'icon' => 'check-circle', 'tone' => 'success'],
            ['label' => 'تحت الصيانة', 'value' => number_format($stats['maintenance']), 'icon' => 'tool', 'tone' => $stats['maintenance'] ? 'warning' : 'success'],
            ['label' => 'المجمعات', 'value' => number_format($stats['complexes']), 'icon' => 'layers', 'tone' => 'info'],
        ]])

        <div class="card stk-filters">
            <div class="card-body">
                <div class="stk-filters__head">
                    <h6 class="stk-filters__title"><i class="feather icon-filter"></i> بحث وتصفية</h6>
                    <div class="d-flex align-items-center" style="gap:.5rem">
                        @if ($hasFilters) <span class="stk-count">فلاتر مفعّلة</span> @endif
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-toggle="collapse" data-bs-toggle="collapse"
                                data-target="#advancedSearchForm" data-bs-target="#advancedSearchForm" aria-expanded="false">
                            <i class="feather icon-sliders"></i> بحث متقدّم
                        </button>
                    </div>
                </div>

                <form id="searchForm">
                    <div class="row">
                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="keywords">البحث</label>
                                <input type="text" id="keywords" name="keywords" class="form-control" value="{{ request('keywords') }}" placeholder="الاسم أو الرمز أو العنوان…">
                            </div>
                        </div>
                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="city">المدينة</label>
                                <select id="city" name="city" class="form-control select2">
                                    <option value="">الكل</option>
                                    @foreach ($cities as $city) <option value="{{ $city }}" @selected(request('city') === $city)>{{ $city }}</option> @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="status">الحالة</label>
                                <select id="status" name="status" class="form-control select2">
                                    <option value="">الكل</option>
                                    @foreach (V::BUILDING_STATUSES as $value => $meta) <option value="{{ $value }}" @selected(request('status') === $value)>{{ $meta['label'] }}</option> @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="collapse {{ $hasFilters ? 'show' : '' }}" id="advancedSearchForm">
                        <div class="row">
                            <div class="col-md-3 col-sm-6">
                                <div class="form-group">
                                    <label for="entity_type">نوع العقار</label>
                                    <select id="entity_type" name="entity_type" class="form-control select2">
                                        <option value="">الكل</option>
                                        @foreach (V::ENTITY_TYPES as $value => $meta) <option value="{{ $value }}" @selected(request('entity_type') === $value)>{{ $meta['label'] }}</option> @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3 col-sm-6">
                                <div class="form-group">
                                    <label for="usage_type">الاستخدام</label>
                                    <select id="usage_type" name="usage_type" class="form-control select2">
                                        <option value="">الكل</option>
                                        @foreach (V::USAGE_TYPES as $value => $meta) <option value="{{ $value }}" @selected(request('usage_type') === $value)>{{ $meta['label'] }}</option> @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3 col-sm-6">
                                <div class="form-group">
                                    <label for="owner_id">المالك</label>
                                    <select id="owner_id" name="owner_id" class="form-control select2">
                                        <option value="">الكل</option>
                                        @foreach ($owners as $owner) <option value="{{ $owner->id }}" @selected((string) request('owner_id') === (string) $owner->id)>{{ $owner->name }}</option> @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3 col-sm-6">
                                <div class="form-group">
                                    <label for="complex_id">المجمع</label>
                                    <select id="complex_id" name="complex_id" class="form-control select2">
                                        <option value="">الكل</option>
                                        @foreach ($complexes as $complex) <option value="{{ $complex->id }}" @selected((string) request('complex_id') === (string) $complex->id)>{{ $complex->name_ar }}</option> @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="stk-filters__foot">
                        <button type="button" id="resetSearch" class="btn btn-outline-secondary"><i class="feather icon-rotate-ccw"></i> إعادة تعيين</button>
                        <button type="submit" class="btn btn-primary"><i class="feather icon-search"></i> بحث</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card stk-table-card">
            <div class="stk-table-head">
                <h6 class="stk-table-head__title"><i class="feather icon-home"></i> العقارات <span class="stk-count">{{ $buildings->total() }}</span></h6>
                <div class="spinner-border spinner-border-sm text-primary d-none" id="loading-indicator" role="status"></div>
            </div>
            <div id="results-container">
                @include('rentalmanagement::realestate.buildings.partials.'.$view, ['buildings' => $buildings])
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    @include('rentalmanagement::realestate.components.list-scripts')
    <script>
        $(function () {
            let view = @json($view);

            reList({ url: @json(route('realestate.buildings.index')), extra: () => ({ view: view }) });

            $('.re-view-toggle [data-view]').on('click', function () {
                view = $(this).data('view');
                $(this).addClass('active').siblings().removeClass('active');
                loadData(1);
            });
        });
    </script>
@endsection
