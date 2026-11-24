@extends('rentalmanagement::realestate.master')

@section('title', 'الملّاك')

@section('content')
    <div class="content-body">

        @section('page-actions')
            @permission('realestate.owners.create')
                <a href="{{ route('realestate.owners.create') }}" class="btn btn-primary"><i class="feather icon-user-plus"></i> إضافة مالك</a>
            @endpermission
        @endsection

        @include('stock::components.page-head', [
            'title' => 'الملّاك',
            'icon' => 'user',
            'sub' => 'ملّاك العقارات واتفاقيات إدارة أملاكهم وأرصدتهم — وهم من يُختار في المباني والعقود',
            'crumbs' => ['الرئيسية' => url('/'), 'إدارة العقارات' => route('realestate.dashboard'), 'الملّاك' => null],
        ])

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')

        @include('rentalmanagement::realestate.components.kpis', ['items' => [
            ['label' => 'إجمالي الملّاك', 'value' => number_format($stats['total']), 'icon' => 'users', 'tone' => 'info'],
            ['label' => 'نشطون', 'value' => number_format($stats['active']), 'icon' => 'user-check', 'tone' => 'success'],
            ['label' => 'اتفاقية بنسبة', 'value' => number_format($stats['percentage']), 'icon' => 'percent', 'tone' => 'primary'],
            ['label' => 'اتفاقية بقيمة ثابتة', 'value' => number_format($stats['fixed']), 'icon' => 'dollar-sign', 'tone' => 'primary'],
        ]])

        <div class="card stk-filters">
            <div class="card-body">
                <div class="stk-filters__head">
                    <h6 class="stk-filters__title"><i class="feather icon-filter"></i> بحث وتصفية</h6>
                </div>

                <form id="searchForm">
                    <div class="row">
                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label for="keywords">البحث</label>
                                <input type="text" id="keywords" name="keywords" class="form-control" value="{{ request('keywords') }}" placeholder="الاسم أو الرمز أو الجوال أو الهوية…">
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label for="agreement_type">نوع الاتفاق</label>
                                <select id="agreement_type" name="agreement_type" class="form-control select2">
                                    <option value="">الكل</option>
                                    @foreach (\Modules\RentalManagement\Support\RealEstateVocabulary::AGREEMENT_TYPES as $value => $meta) <option value="{{ $value }}" @selected(request('agreement_type') === $value)>{{ $meta['label'] }}</option> @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label for="owner_type">نوع المالك</label>
                                <select id="owner_type" name="owner_type" class="form-control select2">
                                    <option value="">الكل</option>
                                    @foreach (\Modules\RentalManagement\Support\RealEstateVocabulary::OWNER_TYPES as $value => $meta) <option value="{{ $value }}" @selected(request('owner_type') === $value)>{{ $meta['label'] }}</option> @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label for="status">الحالة</label>
                                <select id="status" name="status" class="form-control select2">
                                    <option value="">الكل</option>
                                    @foreach (\Modules\RentalManagement\Support\RealEstateVocabulary::OWNER_STATUSES as $value => $meta) <option value="{{ $value }}" @selected(request('status') === $value)>{{ $meta['label'] }}</option> @endforeach
                                </select>
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
                <h6 class="stk-table-head__title"><i class="feather icon-user"></i> الملّاك <span class="stk-count">{{ $owners->total() }}</span></h6>
                <div class="spinner-border spinner-border-sm text-primary d-none" id="loading-indicator" role="status"></div>
            </div>
            <div id="results-container">
                @include('rentalmanagement::realestate.owners.partials.table')
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    @include('rentalmanagement::realestate.components.list-scripts')
    <script>
        $(function () {
            reList({ url: @json(route('realestate.owners.index')) });
        });
    </script>
@endsection
