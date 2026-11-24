@extends('rentalmanagement::realestate.master')

@section('title', 'الوحدات')

@section('content')
    @php
        use Modules\RentalManagement\Support\RealEstateVocabulary as V;
        $hasFilters = request()->hasAny(['unit_type', 'usage_type', 'furnished_status', 'bedrooms', 'rent_from', 'rent_to']);
    @endphp

    <div class="content-body">

        @section('page-actions')
            @permission('realestate.units.create')
                <button type="button" class="btn btn-outline-primary js-bulk-units"><i class="feather icon-layers"></i> وحدات دفعة واحدة</button>
                <a href="{{ route('realestate.units.create') }}" class="btn btn-primary"><i class="feather icon-plus"></i> إضافة وحدة</a>
            @endpermission
        @endsection

        @include('stock::components.page-head', [
            'title' => 'الوحدات',
            'icon' => 'grid',
            'sub' => 'حالة الإشغال مستقلّة عن حالة العرض: قد تكون الوحدة شاغرة وغير منشورة',
            'crumbs' => ['الرئيسية' => url('/'), 'إدارة العقارات' => route('realestate.dashboard'), 'الوحدات' => null],
        ])

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')

        {{-- بطاقات الحالات تصفيةٌ بنقرة --}}
        @php
            $kpis = [['label' => 'كل الوحدات', 'value' => number_format($total), 'icon' => 'grid', 'tone' => 'primary', 'url' => route('realestate.units.index')]];
            foreach (V::OCCUPANCY as $status => $meta) {
                $kpis[] = ['label' => $meta['label'], 'value' => number_format($counts[$status] ?? 0), 'icon' => $meta['icon'], 'tone' => $meta['tone'],
                    'url' => route('realestate.units.index', ['occupancy_status' => $status])];
            }
        @endphp
        @include('rentalmanagement::realestate.components.kpis', ['items' => $kpis])

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
                                <input type="text" id="keywords" name="keywords" class="form-control" value="{{ request('keywords') }}" placeholder="رقم الوحدة أو اسم المبنى…">
                            </div>
                        </div>
                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="building_id">المبنى</label>
                                <select id="building_id" name="building_id" class="form-control select2">
                                    <option value="">الكل</option>
                                    @foreach ($buildings as $building) <option value="{{ $building->id }}" @selected((string) request('building_id') === (string) $building->id)>{{ $building->name }}</option> @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="occupancy_status">حالة الإشغال</label>
                                <select id="occupancy_status" name="occupancy_status" class="form-control select2">
                                    <option value="">الكل</option>
                                    @foreach (V::OCCUPANCY as $value => $meta) <option value="{{ $value }}" @selected(request('occupancy_status') === $value)>{{ $meta['label'] }}</option> @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="collapse {{ $hasFilters ? 'show' : '' }}" id="advancedSearchForm">
                        <div class="row">
                            <div class="col-md-3 col-sm-6">
                                <div class="form-group">
                                    <label for="unit_type">نوع الوحدة</label>
                                    <select id="unit_type" name="unit_type" class="form-control select2">
                                        <option value="">الكل</option>
                                        @foreach (V::UNIT_TYPES as $value => $meta) <option value="{{ $value }}" @selected(request('unit_type') === $value)>{{ $meta['label'] }}</option> @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3 col-sm-6">
                                <div class="form-group">
                                    <label for="usage_type">الاستخدام</label>
                                    <select id="usage_type" name="usage_type" class="form-control select2">
                                        <option value="">الكل</option>
                                        @foreach (V::UNIT_USAGE_TYPES as $value => $meta) <option value="{{ $value }}" @selected(request('usage_type') === $value)>{{ $meta['label'] }}</option> @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3 col-sm-6">
                                <div class="form-group">
                                    <label for="furnished_status">التأثيث</label>
                                    <select id="furnished_status" name="furnished_status" class="form-control select2">
                                        <option value="">الكل</option>
                                        @foreach (V::FURNISHED as $value => $meta) <option value="{{ $value }}" @selected(request('furnished_status') === $value)>{{ $meta['label'] }}</option> @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-2 col-sm-4">
                                <div class="form-group">
                                    <label for="bedrooms">غرف النوم</label>
                                    <input type="number" min="0" id="bedrooms" name="bedrooms" class="form-control" value="{{ request('bedrooms') }}">
                                </div>
                            </div>
                            <div class="col-md-2 col-sm-4">
                                <div class="form-group">
                                    <label for="rent_from">الإيجار من ({{ \Modules\RentalManagement\Support\RealEstateMoney::code() }})</label>
                                    <input type="number" min="0" id="rent_from" name="rent_from" class="form-control" value="{{ request('rent_from') }}">
                                </div>
                            </div>
                            <div class="col-md-2 col-sm-4">
                                <div class="form-group">
                                    <label for="rent_to">إلى</label>
                                    <input type="number" min="0" id="rent_to" name="rent_to" class="form-control" value="{{ request('rent_to') }}">
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
                <h6 class="stk-table-head__title"><i class="feather icon-list"></i> الوحدات <span class="stk-count">{{ $units->total() }}</span></h6>
                <div class="spinner-border spinner-border-sm text-primary d-none" id="loading-indicator" role="status"></div>
            </div>
            <div id="results-container">
                @include('rentalmanagement::realestate.units.partials.table', ['units' => $units])
            </div>
        </div>
    </div>

    @permission('realestate.units.create')
        @include('rentalmanagement::realestate.units.partials.bulk-modal', ['bulkBuildings' => $bulkBuildings])
    @endpermission
@endsection

@section('scripts')
    @include('rentalmanagement::realestate.components.list-scripts')
    @include('rentalmanagement::realestate.components.ajax-form-scripts')
    <script>$(function () { reList({ url: @json(route('realestate.units.index')) }); });</script>
@endsection
