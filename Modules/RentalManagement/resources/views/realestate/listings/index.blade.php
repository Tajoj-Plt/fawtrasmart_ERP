@extends('rentalmanagement::realestate.master')

@section('title', 'العروض العقارية')

@section('content')
    @php
        use Modules\RentalManagement\Support\RealEstateVocabulary as V;
        $hasFilters = request()->hasAny(['building_id', 'assigned_agent_id']);
    @endphp

    <div class="content-body">

        @section('page-actions')
            @permission('realestate.listings.create')
                <a href="{{ route('realestate.listings.create') }}" class="btn btn-primary"><i class="feather icon-plus"></i> عرض جديد</a>
            @endpermission
        @endsection

        @include('stock::components.page-head', [
            'title' => 'العروض العقارية',
            'icon' => 'tag',
            'sub' => 'تسويق العقارات للإيجار أو البيع — من وحداتك أو من مصادر خارجية. طلبات العملاء الباحثين عن عقار في شاشة «طلبات العملاء»',
            'crumbs' => ['الرئيسية' => url('/'), 'إدارة العقارات' => route('realestate.dashboard'), 'العروض العقارية' => null],
        ])

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')

        @include('rentalmanagement::realestate.components.kpis', ['items' => [
            ['label' => 'عروض نشطة', 'value' => number_format($stats['active']), 'icon' => 'tag', 'tone' => 'success'],
            ['label' => 'للإيجار', 'value' => number_format($stats['rent']), 'icon' => 'key', 'tone' => 'info'],
            ['label' => 'للبيع', 'value' => number_format($stats['sale']), 'icon' => 'shopping-bag', 'tone' => 'primary'],
            ['label' => 'تمّ الإيجار / الشراء', 'value' => number_format($stats['done']), 'icon' => 'check-circle', 'tone' => 'success'],
            ['label' => 'الاستفسارات', 'value' => number_format($stats['inquiries']), 'icon' => 'message-circle', 'tone' => 'info'],
            ['label' => 'المعاينات', 'value' => number_format($stats['viewings']), 'icon' => 'eye', 'tone' => 'warning'],
            ['label' => 'تحوّلت إلى عقود', 'value' => number_format($stats['converted']), 'icon' => 'file-text', 'tone' => 'success'],
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
                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label for="keywords">البحث</label>
                                <input type="text" id="keywords" name="keywords" class="form-control" value="{{ request('keywords') }}" placeholder="العنوان أو الرمز…">
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label for="listing_type">إيجار / بيع</label>
                                <select id="listing_type" name="listing_type" class="form-control select2">
                                    <option value="">الكل</option>
                                    @foreach (V::LISTING_TYPES as $value => $meta) <option value="{{ $value }}" @selected(request('listing_type') === $value)>{{ $meta['label'] }}</option> @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label for="status">الحالة</label>
                                <select id="status" name="status" class="form-control select2">
                                    <option value="">الكل</option>
                                    @foreach (V::LISTING_STATUSES as $value => $meta) <option value="{{ $value }}" @selected(request('status') === $value)>{{ $meta['label'] }}</option> @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="collapse {{ $hasFilters ? 'show' : '' }}" id="advancedSearchForm">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="building_id">العقار</label>
                                    <select id="building_id" name="building_id" class="form-control select2">
                                        <option value="">الكل</option>
                                        @foreach ($buildings as $building) <option value="{{ $building->id }}" @selected((string) request('building_id') === (string) $building->id)>{{ $building->name }}</option> @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="assigned_agent_id">المسوّق المسؤول</label>
                                    <select id="assigned_agent_id" name="assigned_agent_id" class="form-control select2">
                                        <option value="">الكل</option>
                                        @foreach ($users as $user) <option value="{{ $user->id }}" @selected((string) request('assigned_agent_id') === (string) $user->id)>{{ $user->name }}</option> @endforeach
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
                <h6 class="stk-table-head__title"><i class="feather icon-tag"></i> العروض <span class="stk-count">{{ $listings->total() }}</span></h6>
                <div class="spinner-border spinner-border-sm text-primary d-none" id="loading-indicator" role="status"></div>
            </div>
            <div id="results-container">
                @include('rentalmanagement::realestate.listings.partials.table', ['listings' => $listings])
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    @include('rentalmanagement::realestate.components.list-scripts')
    <script>
        $(function () {
            reList({ url: @json(route('realestate.listings.index')) });
        });
    </script>
@endsection
