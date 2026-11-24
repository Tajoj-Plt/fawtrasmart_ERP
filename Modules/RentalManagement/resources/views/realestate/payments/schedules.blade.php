@extends('rentalmanagement::realestate.master')

@section('title', 'الاستحقاقات والمتأخرات')

@section('content')
    <div class="content-body">
        @section('page-actions')
            @permission('realestate.payments.view')
                <a href="{{ route('realestate.payments.index') }}" class="btn btn-outline-secondary"><i class="feather icon-credit-card"></i> الدفعات</a>
            @endpermission
            @if (hasPermission('realestate.reports.financial'))
                <a href="{{ route('realestate.reports.show', 'aging') }}" class="btn btn-outline-secondary"><i class="feather icon-bar-chart"></i> أعمار الديون</a>
            @endif
        @endsection

        @include('stock::components.page-head', [
            'title' => 'الاستحقاقات والمتأخرات',
            'icon' => 'calendar',
            'sub' => 'ما يولّده كلّ عقد من أقساط: المستحق والمتأخر والقادم',
            'crumbs' => ['الرئيسية' => url('/'), 'إدارة العقارات' => route('realestate.dashboard'), 'الاستحقاقات' => null],
        ])

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')

        <div class="card stk-filters">
            <div class="card-body">
                <form id="searchForm">
                    <input type="hidden" name="bucket" id="bucket" value="{{ $bucket }}">
                    <div class="btn-group flex-wrap mb-1 re-view-toggle" role="group" aria-label="فئة الاستحقاقات">
                        @foreach (['open' => 'المفتوحة', 'overdue' => 'المتأخرة', 'due' => 'المستحقة حتى اليوم', 'upcoming' => 'خلال 30 يوماً', 'paid' => 'المدفوعة', 'all' => 'الكل'] as $value => $label)
                            <button type="button" class="btn btn-outline-secondary {{ $bucket === $value ? 'active' : '' }}" data-bucket="{{ $value }}">{{ $label }}</button>
                        @endforeach
                    </div>
                    <div class="row">
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
                                <label for="client_id">المستأجر</label>
                                @include('rentalmanagement::realestate.components.client-select', ['name' => 'client_id', 'selected' => request('client_id'), 'tenantsOnly' => true, 'placeholder' => 'الكل', 'filter' => true])
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="form-group"><label for="from_date">الاستحقاق من</label><input type="date" id="from_date" name="from_date" class="form-control" value="{{ request('from_date') }}"></div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="form-group"><label for="to_date">إلى</label><input type="date" id="to_date" name="to_date" class="form-control" value="{{ request('to_date') }}"></div>
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
                <h6 class="stk-table-head__title"><i class="feather icon-list"></i> الاستحقاقات <span class="stk-count">{{ $schedules->total() }}</span></h6>
                <div class="spinner-border spinner-border-sm text-primary d-none" id="loading-indicator" role="status"></div>
            </div>
            <div id="results-container">
                @include('rentalmanagement::realestate.payments.partials.schedules-table', ['schedules' => $schedules])
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    @include('rentalmanagement::realestate.components.list-scripts')
    <script>
        $(function () {
            // يُسجَّل قبل `reList`: معالجات jQuery تعمل بترتيب تسجيلها، و«إعادة تعيين» هناك
            // تُحمِّل البيانات فوراً — فيجب أن تكون الفئة قد عادت إلى «المفتوحة» قبلها
            $('#resetSearch').on('click', function () {
                $('#bucket').val('open');
                $('[data-bucket]').removeClass('active').filter('[data-bucket="open"]').addClass('active');
            });

            reList({ url: @json(route('realestate.payments.schedules')) });

            $('[data-bucket]').on('click', function () {
                $('#bucket').val($(this).data('bucket'));
                $(this).addClass('active').siblings().removeClass('active');
                loadData(1);
            });

        });
    </script>
@endsection
