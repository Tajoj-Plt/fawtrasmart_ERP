@extends('rentalmanagement::realestate.master')

@section('title', 'المستأجرون')

@section('content')
    <div class="content-body">

        @section('page-actions')
            @permission('realestate.tenants.create')
                <a href="{{ route('realestate.tenants.create') }}" class="btn btn-primary"><i class="feather icon-user-plus"></i> تسجيل مستأجر</a>
            @endpermission
        @endsection

        @include('stock::components.page-head', [
            'title' => 'المستأجرون',
            'icon' => 'user-check',
            'sub' => 'مستأجرو الوحدات وعقودهم وأرصدتهم — وهم أنفسهم خيارات حقل «المستأجر / العميل» في العقد',
            'crumbs' => ['الرئيسية' => url('/'), 'إدارة العقارات' => route('realestate.dashboard'), 'المستأجرون' => null],
        ])

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')

        @include('rentalmanagement::realestate.components.kpis', ['items' => [
            ['label' => 'إجمالي المستأجرين', 'value' => number_format($stats['total']), 'icon' => 'users', 'tone' => 'info'],
            ['label' => 'لهم عقد قائم', 'value' => number_format($stats['with_contract']), 'icon' => 'key', 'tone' => 'success'],
            ['label' => 'مسجَّلون بلا عقد قائم', 'value' => number_format($stats['without_contract']), 'icon' => 'user', 'tone' => 'primary'],
            ['label' => 'عليهم دفعات متأخرة', 'value' => number_format($stats['overdue']), 'icon' => 'alert-triangle', 'tone' => $stats['overdue'] ? 'danger' : 'success'],
        ]])

        <p class="text-muted mb-1"><i class="feather icon-info"></i>
            المستأجر عميلٌ من الملفّ المركزي يحمل دور «مستأجر». يُسجَّل هنا قبل التعاقد، أو يُضاف آلياً عند سريان أوّل عقدٍ له.
        </p>

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
                                <input type="text" id="keywords" name="keywords" class="form-control" value="{{ request('keywords') }}" placeholder="اسم المستأجر أو رمزه أو هاتفه أو هويته…">
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label for="tenant_type">النوع</label>
                                <select id="tenant_type" name="tenant_type" class="form-control select2">
                                    <option value="">الكل</option>
                                    @foreach (\Modules\RentalManagement\Models\TenantProfile::TYPES as $value => $meta)
                                        <option value="{{ $value }}" @selected(request('tenant_type') === $value)>{{ $meta['label'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label for="state">الحالة</label>
                                <select id="state" name="state" class="form-control select2">
                                    <option value="">الكل</option>
                                    <option value="with_contract" @selected(request('state') === 'with_contract')>له عقد قائم</option>
                                    <option value="without_contract" @selected(request('state') === 'without_contract')>بلا عقد قائم</option>
                                    <option value="overdue" @selected(request('state') === 'overdue')>عليه دفعات متأخرة</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label for="building_id">العقار</label>
                                <select id="building_id" name="building_id" class="form-control select2">
                                    <option value="">الكل</option>
                                    @foreach ($buildings as $building) <option value="{{ $building->id }}" @selected((string) request('building_id') === (string) $building->id)>{{ $building->name }}</option> @endforeach
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
                <h6 class="stk-table-head__title"><i class="feather icon-user-check"></i> المستأجرون <span class="stk-count">{{ $tenants->total() }}</span></h6>
                <div class="spinner-border spinner-border-sm text-primary d-none" id="loading-indicator" role="status"></div>
            </div>
            <div id="results-container">
                @include('rentalmanagement::realestate.tenants.partials.table')
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    @include('rentalmanagement::realestate.components.list-scripts')
    <script>
        $(function () {
            reList({ url: @json(route('realestate.tenants.index')) });

        });
    </script>
@endsection
