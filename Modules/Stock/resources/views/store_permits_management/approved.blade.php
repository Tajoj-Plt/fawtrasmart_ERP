@extends('stock::master')

@section('title', 'الأذون الموافق عليها')

@section('content')
    {{--
        كان هذا الملف فارغاً تماماً (صفر بايت) بينما يشير إليه مسارٌ في
        الكنترولر — فتُعرض الصفحة بيضاء. أُعيد بناؤه على الجزئية نفسها التي
        تخدم قائمة الأذون الرئيسية.
    --}}

    <div class="content-body">

        @include('stock::components.page-head', [
            'title' => 'الأذون الموافق عليها',
            'icon' => 'check-circle',
            'sub' => 'أذون اعتُمدت ورُحِّلت أثرها على المخزون',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'الأذون المخزنية' => route('store_permits_management.index'),
                'موافق عليها' => null,
            ],
        ])

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')

        <div class="card stk-table-card">
            <div class="stk-table-head">
                <h6 class="stk-table-head__title">
                    <i class="feather icon-check-circle"></i> الأذون الموافق عليها
                    @if (method_exists($permits, 'total'))
                        <span class="stk-count">{{ $permits->total() }}</span>
                    @endif
                </h6>
                <div class="spinner-border spinner-border-sm text-primary d-none" id="loading-indicator" role="status"></div>
            </div>

            <div id="results-container">
                @include('stock::store_permits_management.partials.table', [
                    'wareHousePermits' => $permits,
                ])
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        $(function () {
            /* الترقيم وعدد السجلّات يمرّان من هنا: الجزئية تنادي `loadData` */
            window.loadData = function (page) {
                $('#loading-indicator').removeClass('d-none');

                const perPage = $('#perPage').length ? $('#perPage').val() : 10;

                $.ajax({
                    url: window.location.pathname,
                    method: 'GET',
                    data: { page: page || 1, per_page: perPage },
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                })
                    .done(response => $('#results-container').html(response))
                    .fail(() => $('#results-container').html(
                        '<div class="alert alert-danger m-2">تعذّر تحميل البيانات</div>'
                    ))
                    .always(() => $('#loading-indicator').addClass('d-none'));
            };

            $('#results-container').on('click', '.pagination-link', function (e) {
                e.preventDefault();
                loadData(parseInt($(this).data('page'), 10));
            });
        });
    </script>
@endsection
