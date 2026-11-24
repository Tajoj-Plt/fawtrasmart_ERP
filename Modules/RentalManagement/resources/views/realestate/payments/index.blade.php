@extends('rentalmanagement::realestate.master')

@section('title', 'الدفعات')

@section('content')
    @php
        use Modules\RentalManagement\Support\RealEstateVocabulary as V;
        use Modules\RentalManagement\Models\Payment;
        use Modules\RentalManagement\Support\RealEstateMoney;
        // إجماليات عبر دفعات: بعملة النظام
        $money = fn ($v) => RealEstateMoney::format($v);
        $hasFilters = request()->hasAny(['payment_method_id', 'client_id', 'contract_id', 'from_date', 'to_date']);
    @endphp

    <div class="content-body">

        @section('page-actions')
            @permission('realestate.payments.schedules')
                <a href="{{ route('realestate.payments.schedules') }}" class="btn btn-outline-secondary"><i class="feather icon-calendar"></i> جدول الاستحقاقات</a>
            @endpermission
            @permission('realestate.payments.create')
                <a href="{{ route('realestate.payments.create') }}" class="btn btn-primary"><i class="feather icon-plus"></i> تسجيل دفعة</a>
            @endpermission
        @endsection

        @include('stock::components.page-head', [
            'title' => 'الدفعات',
            'icon' => 'credit-card',
            'sub' => 'كلّ دفعةٍ تُوزَّع على استحقاقاتٍ محدّدة وتُرحَّل بقيد يومية',
            'crumbs' => ['الرئيسية' => url('/'), 'إدارة العقارات' => route('realestate.dashboard'), 'الدفعات' => null],
        ])

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')

        @include('rentalmanagement::realestate.components.kpis', ['items' => [
            ['label' => 'محصَّل هذا الشهر', 'value' => $money($stats['month']), 'icon' => 'trending-up', 'tone' => 'success'],
            ['label' => 'مستحق متبقٍّ هذا الشهر', 'value' => $money($stats['due_month']), 'icon' => 'calendar', 'tone' => 'info',
                'url' => hasPermission('realestate.payments.schedules') ? route('realestate.payments.schedules', ['from_date' => now()->startOfMonth()->toDateString(), 'to_date' => now()->endOfMonth()->toDateString()]) : null],
            ['label' => 'المتأخرات', 'value' => $money($stats['overdue']), 'icon' => 'alert-circle', 'tone' => $stats['overdue'] > 0 ? 'danger' : 'success',
                'url' => hasPermission('realestate.payments.schedules') ? route('realestate.payments.schedules', ['bucket' => 'overdue']) : null],
            ['label' => 'إجمالي المحصَّل (مرحَّل)', 'value' => $money($stats['total']), 'icon' => 'check-circle', 'tone' => 'primary',
                'note' => $stats['unposted'] ? $stats['unposted'].' دفعة غير مرحّلة خارج الإجمالي' : null],
        ]])

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
                    @if (request('contract_id')) <input type="hidden" name="contract_id" value="{{ request('contract_id') }}"> @endif
                    <div class="row">
                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="keywords">البحث</label>
                                <input type="text" id="keywords" name="keywords" class="form-control" value="{{ request('keywords') }}" placeholder="رقم الدفعة أو المرجع أو العقد…">
                            </div>
                        </div>
                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="posting_status">حالة الترحيل</label>
                                <select id="posting_status" name="posting_status" class="form-control select2">
                                    <option value="">الكل</option>
                                    @foreach (Payment::POSTING_STATUSES as $value => $meta) <option value="{{ $value }}" @selected(request('posting_status') === $value)>{{ $meta['label'] }}</option> @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="client_id">العميل</label>
                                @include('rentalmanagement::realestate.components.client-select', ['name' => 'client_id', 'selected' => request('client_id'), 'tenantsOnly' => true, 'placeholder' => 'الكل', 'filter' => true])
                            </div>
                        </div>
                    </div>
                    <div class="collapse {{ $hasFilters ? 'show' : '' }}" id="advancedSearchForm">
                        <div class="row">
                            <div class="col-md-4 col-sm-6">
                                <div class="form-group">
                                    <label for="payment_method_id">طريقة الدفع</label>
                                    <select id="payment_method_id" name="payment_method_id" class="form-control select2">
                                        <option value="">الكل</option>
                                        @foreach ($methods as $method) <option value="{{ $method->id }}" @selected((string) request('payment_method_id') === (string) $method->id)>{{ $method->name }}</option> @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4 col-sm-6">
                                <div class="form-group"><label for="from_date">من تاريخ</label><input type="date" id="from_date" name="from_date" class="form-control" value="{{ request('from_date') }}"></div>
                            </div>
                            <div class="col-md-4 col-sm-6">
                                <div class="form-group"><label for="to_date">إلى تاريخ</label><input type="date" id="to_date" name="to_date" class="form-control" value="{{ request('to_date') }}"></div>
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
                <h6 class="stk-table-head__title"><i class="feather icon-list"></i> الدفعات <span class="stk-count">{{ $payments->total() }}</span></h6>
                <div class="spinner-border spinner-border-sm text-primary d-none" id="loading-indicator" role="status"></div>
            </div>
            <div id="results-container">
                @include('rentalmanagement::realestate.payments.partials.table', ['payments' => $payments])
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    @include('rentalmanagement::realestate.components.list-scripts')
    <script>$(function () { reList({ url: @json(route('realestate.payments.index')) }); });</script>
@endsection
