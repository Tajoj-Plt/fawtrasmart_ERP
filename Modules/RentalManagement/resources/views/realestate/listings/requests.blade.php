@extends('rentalmanagement::realestate.master')

@section('title', 'طلبات العملاء')

@section('content')
    @php
        use Modules\RentalManagement\Models\Listing as L;
        use Modules\RentalManagement\Support\RealEstateVocabulary as V;
        $hasFilters = request()->hasAny(['property_type', 'assigned_agent_id']);
    @endphp

    <div class="content-body">

        @section('page-actions')
            @permission('realestate.listings.create')
                <a href="{{ route('realestate.listings.create', ['kind' => 'request']) }}" class="btn btn-primary"><i class="feather icon-plus"></i> طلب جديد</a>
            @endpermission
        @endsection

        @include('stock::components.page-head', [
            'title' => 'طلبات العملاء',
            'icon' => 'search',
            'sub' => 'عميلٌ يبحث عن شقة أو أرض أو محلّ: ما يطلبه وميزانيته، ومن يتابعه، وما يطابقه من عروضنا',
            'crumbs' => ['الرئيسية' => url('/'), 'إدارة العقارات' => route('realestate.dashboard'), 'طلبات العملاء' => null],
        ])

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')

        @include('rentalmanagement::realestate.components.kpis', ['items' => [
            ['label' => 'طلبات مفتوحة', 'value' => number_format($stats['open']), 'icon' => 'search', 'tone' => 'info'],
            ['label' => 'قيد المتابعة', 'value' => number_format($stats['follow']), 'icon' => 'phone-call', 'tone' => $stats['follow'] ? 'warning' : 'success'],
            ['label' => 'يبحثون عن إيجار', 'value' => number_format($stats['rent']), 'icon' => 'key', 'tone' => 'primary'],
            ['label' => 'يبحثون عن شراء', 'value' => number_format($stats['sale']), 'icon' => 'shopping-bag', 'tone' => 'primary'],
            ['label' => 'تمت تلبيتها', 'value' => number_format($stats['fulfilled']), 'icon' => 'check-circle', 'tone' => 'success'],
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
                                <input type="text" id="keywords" name="keywords" class="form-control" value="{{ request('keywords') }}" placeholder="اسم العميل أو هاتفه، رقم الطلب، المدينة أو الحي…">
                            </div>
                        </div>
                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="status">حالة الطلب</label>
                                <select id="status" name="status" class="form-control select2">
                                    <option value="">الكل</option>
                                    <option value="open" @selected(request('status') === 'open')>مفتوح (جديد / نشط / قيد المتابعة)</option>
                                    @foreach (['draft', 'active', 'paused'] as $value) <option value="{{ $value }}" @selected(request('status') === $value)>{{ L::REQUEST_STATUSES[$value]['label'] }}</option> @endforeach
                                    <option value="fulfilled" @selected(request('status') === 'fulfilled')>تمت تلبيته</option>
                                    <option value="closed" @selected(request('status') === 'closed')>مغلق</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="listing_type">يبحث عن</label>
                                <select id="listing_type" name="listing_type" class="form-control select2">
                                    <option value="">الكل</option>
                                    <option value="rent" @selected(request('listing_type') === 'rent')>إيجار</option>
                                    <option value="sale" @selected(request('listing_type') === 'sale')>شراء</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="collapse {{ $hasFilters ? 'show' : '' }}" id="advancedSearchForm">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="property_type">نوع العقار المطلوب</label>
                                    <select id="property_type" name="property_type" class="form-control select2">
                                        <option value="">الكل</option>
                                        @foreach (V::UNIT_TYPES as $value => $meta) <option value="{{ $value }}" @selected(request('property_type') === $value)>{{ $meta['label'] }}</option> @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="assigned_agent_id">المتابِع</label>
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
                <h6 class="stk-table-head__title"><i class="feather icon-search"></i> الطلبات <span class="stk-count">{{ $requests->total() }}</span></h6>
                <div class="spinner-border spinner-border-sm text-primary d-none" id="loading-indicator" role="status"></div>
            </div>
            <div id="results-container">
                @include('rentalmanagement::realestate.listings.partials.requests-table', ['requests' => $requests])
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    @include('rentalmanagement::realestate.components.list-scripts')
    <script>
        $(function () {
            reList({ url: @json(route('realestate.listings.requests')) });
        });
    </script>
@endsection
