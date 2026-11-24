@extends('rentalmanagement::realestate.master')

@section('title', 'العقود')

@section('content')
    @php
        use Modules\RentalManagement\Support\RealEstateVocabulary as V;
        $hasFilters = request()->hasAny(['contract_type', 'building_id', 'tenant_client_id', 'payment_frequency', 'from_date', 'to_date', 'expiring_in']);
    @endphp

    <div class="content-body">

        @section('page-actions')
            @permission('realestate.contracts.create')
                <a href="{{ route('realestate.contracts.create') }}" class="btn btn-primary"><i class="feather icon-plus"></i> إنشاء عقد</a>
            @endpermission
        @endsection

        @include('stock::components.page-head', [
            'title' => 'العقود',
            'icon' => 'file-text',
            'sub' => 'العقد يربط العميل بالوحدة ويولّد جدول الاستحقاقات',
            'crumbs' => ['الرئيسية' => url('/'), 'إدارة العقارات' => route('realestate.dashboard'), 'العقود' => null],
        ])

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')

        @php
            $kpis = [['label' => 'إجمالي العقود', 'value' => number_format($total), 'icon' => 'file-text', 'tone' => 'primary', 'url' => route('realestate.contracts.index')]];
            $icons = ['draft' => 'edit-3', 'pending_approval' => 'clock', 'active' => 'check-circle', 'renewing' => 'refresh-cw', 'expired' => 'x-circle', 'cancelled' => 'slash', 'terminated' => 'log-out'];
            foreach (\Modules\RentalManagement\Models\Contract::statuses() as $status => $meta) {
                $kpis[] = ['label' => $meta['label'], 'value' => number_format($counts[$status] ?? 0), 'icon' => $icons[$status] ?? 'file-text', 'tone' => $meta['tone'],
                    'url' => route('realestate.contracts.index', ['status' => $status])];
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
                                data-target="#advancedSearchForm" data-bs-target="#advancedSearchForm" aria-expanded="false"><i class="feather icon-sliders"></i> بحث متقدّم</button>
                    </div>
                </div>

                <form id="searchForm">
                    <div class="row">
                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="keywords">البحث</label>
                                <input type="text" id="keywords" name="keywords" class="form-control" value="{{ request('keywords') }}" placeholder="رقم العقد أو المستأجر أو الوحدة…">
                            </div>
                        </div>
                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="status">الحالة</label>
                                <select id="status" name="status" class="form-control select2">
                                    <option value="">الكل</option>
                                    @foreach (\Modules\RentalManagement\Models\Contract::statuses() as $value => $meta) <option value="{{ $value }}" @selected(request('status') === $value)>{{ $meta['label'] }}</option> @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="expiring_in">تنتهي خلال</label>
                                <select id="expiring_in" name="expiring_in" class="form-control select2">
                                    <option value="">—</option>
                                    @foreach ([30, 60, 90, 180] as $days) <option value="{{ $days }}" @selected((string) request('expiring_in') === (string) $days)>{{ $days }} يوماً</option> @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="collapse {{ $hasFilters ? 'show' : '' }}" id="advancedSearchForm">
                        <div class="row">
                            <div class="col-md-3 col-sm-6">
                                <div class="form-group">
                                    <label for="contract_type">نوع العقد</label>
                                    <select id="contract_type" name="contract_type" class="form-control select2">
                                        <option value="">الكل</option>
                                        @foreach (V::CONTRACT_TYPES as $value => $meta) <option value="{{ $value }}" @selected(request('contract_type') === $value)>{{ $meta['label'] }}</option> @endforeach
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
                            <div class="col-md-3 col-sm-6">
                                <div class="form-group">
                                    <label for="tenant_client_id">المستأجر</label>
                                    @include('rentalmanagement::realestate.components.client-select', ['name' => 'tenant_client_id', 'selected' => request('tenant_client_id'), 'tenantsOnly' => true, 'placeholder' => 'الكل', 'filter' => true])
                                </div>
                            </div>
                            <div class="col-md-3 col-sm-6">
                                <div class="form-group">
                                    <label for="payment_frequency">دورية السداد</label>
                                    <select id="payment_frequency" name="payment_frequency" class="form-control select2">
                                        <option value="">الكل</option>
                                        @foreach (V::PAYMENT_FREQUENCIES as $value => $meta) <option value="{{ $value }}" @selected(request('payment_frequency') === $value)>{{ $meta['label'] }}</option> @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3 col-sm-6">
                                <div class="form-group">
                                    <label for="from_date">تبدأ من</label>
                                    <input type="date" id="from_date" name="from_date" class="form-control" value="{{ request('from_date') }}">
                                </div>
                            </div>
                            <div class="col-md-3 col-sm-6">
                                <div class="form-group">
                                    <label for="to_date">تنتهي حتى</label>
                                    <input type="date" id="to_date" name="to_date" class="form-control" value="{{ request('to_date') }}">
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
                <h6 class="stk-table-head__title"><i class="feather icon-list"></i> العقود <span class="stk-count">{{ $contracts->total() }}</span></h6>
                <div class="spinner-border spinner-border-sm text-primary d-none" id="loading-indicator" role="status"></div>
            </div>
            <div id="results-container">
                @include('rentalmanagement::realestate.contracts.partials.table', ['contracts' => $contracts])
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    @include('rentalmanagement::realestate.components.list-scripts')
    <script>$(function () { reList({ url: @json(route('realestate.contracts.index')) }); });</script>
@endsection
