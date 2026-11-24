@extends('sales::master')

{{--
    جدول الأقساط عبر الاتفاقيات — شاشة المتابعة والتحصيل.

    ══════════════════ ما كان ══════════════════

    `agreement()` كانت `InstallmentDetail::all()` — الجدول كلُّه إلى
    الذاكرة. وقالبُها:

    • **بلا ترقيم** إطلاقاً.
    • **بلا حالةٍ فارغة**: `@foreach` بلا حارس، فنتيجةٌ فارغة تُخرج
      ترويسةَ جدولٍ معلَّقةً فوق فراغ.
    • **بلا `value="{{ request(…) }}"` على أيّ حقل تصفية** — فكلّ بحثٍ
      يمحو ما قبله ولا يرى المستخدم بماذا فلتر.
    • خياران `<option selected>` **بلا `value`** فيُرسَل نصُّ التسمية
      العربية قيمةً (`الكل`، `اختر الفترة`).
    • قيمُ الفلتر `1`/`2` والعمودُ يُقارَن بـ`'active'`/`'unactive'` —
      فلا فلترةَ حالةٍ تطابق شيئاً.
    • **لا `@include('layouts.alerts.…')`** — فرسالةُ نجاح السداد لا تظهر
      في الشاشة التي يُسجَّل منها السداد.
--}}

@section('title', __('sales_installments.schedule_title'))

@section('content')
    @include('sales::partials.settings-theme')
    @include('components.hint-tips')

    @php
        $hasFilters = collect(['search', 'status', 'from_date', 'to_date'])
            ->contains(fn ($k) => request()->filled($k));
    @endphp

    <div class="content-body sl-scope">

        @section('page-actions')
            <a href="{{ route('installments.index') }}" class="btn btn-outline-secondary">
                <i class="feather icon-credit-card"></i> {{ __('sales_installments.title') }}
            </a>
        @endsection

        @include('sales::components.page-head', [
            'title' => __('sales_installments.schedule_title'),
            'icon' => 'list',
            'sub' => __('sales_installments.schedule_subtitle'),
            'crumbs' => [
                __('sales_settings.ui.home') => route('dashboard'),
                __('sales_installments.title') => route('installments.index'),
                __('sales_installments.schedule_title') => null,
            ],
        ])

        @include('sales::components.system-context', ['only' => ['currency', 'payments']])

        {{-- تظهر هنا رسالةُ نجاح سند القبض حين يعود المستخدم من تسجيله --}}
        @include('layouts.alerts.success')
        @include('layouts.alerts.error')
        @include('sales::components.form-errors')

        @include('sales::components.guide', [
            'key' => 'installments.schedule',
            'open' => false,
            'title' => __('sales_installments.guide.schedule.title'),
            'lead' => __('sales_installments.guide.schedule.lead'),
            'blocks' => [
                __('sales_installments.guide.schedule.use.title') => [
                    __('sales_installments.guide.schedule.use.a'),
                    __('sales_installments.guide.schedule.use.b'),
                ],
                __('sales_installments.guide.money_flow.title') => [
                    __('sales_installments.guide.money_flow.b'),
                    __('sales_installments.guide.money_flow.c'),
                    __('sales_installments.guide.money_flow.e'),
                ],
                __('sales_installments.guide.statuses.title') => [
                    __('sales_installments.guide.statuses.c'),
                ],
            ],
        ])

        {{-- ══════════ التصفية ══════════ --}}
        <div class="card sl-filters">
            <div class="card-body">
                <div class="sl-filters__head">
                    <h6 class="sl-filters__title">
                        <i class="feather icon-filter"></i> {{ __('sales_settings.ui.filters') }}
                    </h6>
                    @if ($hasFilters)
                        <span class="sl-count">{{ __('sales_settings.ui.filters_active') }}</span>
                    @endif
                </div>

                <form method="GET" action="{{ route('installments.agreement_installments') }}">
                    <div class="row">
                        <div class="col-md-5 col-12">
                            <div class="form-group">
                                <label for="search">{{ __('sales_settings.ui.search') }}</label>
                                {{-- القيمة تعود بعد البحث: كانت كلّ الحقول
                                     تُمحى فلا يرى المستخدم بماذا فلتر --}}
                                <input type="text" id="search" name="search" class="form-control"
                                       value="{{ request('search') }}"
                                       placeholder="{{ __('sales_installments.filters.search_placeholder') }}">
                            </div>
                        </div>

                        <div class="col-md-3 col-sm-6 col-12">
                            <div class="form-group">
                                <label for="status">{{ __('sales_installments.fields.status') }}</label>
                                <select id="status" name="status" class="form-control">
                                    <option value="">{{ __('sales_installments.detail_status.all') }}</option>
                                    @foreach ($statuses as $status)
                                        <option value="{{ $status }}" @selected(request('status') === $status)>
                                            {{ __('sales_installments.detail_status.'.$status) }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="col-md-2 col-sm-6 col-12">
                            <div class="form-group">
                                <label for="from_date">{{ __('sales_installments.filters.from_date') }}</label>
                                <input type="date" id="from_date" name="from_date" class="form-control"
                                       value="{{ request('from_date') }}">
                            </div>
                        </div>

                        <div class="col-md-2 col-sm-6 col-12">
                            <div class="form-group">
                                <label for="to_date">{{ __('sales_installments.filters.to_date') }}</label>
                                <input type="date" id="to_date" name="to_date" class="form-control"
                                       value="{{ request('to_date') }}">
                            </div>
                        </div>
                    </div>

                    <div class="sl-filters__foot">
                        @if ($hasFilters)
                            <a href="{{ route('installments.agreement_installments') }}" class="btn btn-outline-secondary">
                                <i class="feather icon-x"></i> {{ __('sales_settings.ui.filters_clear') }}
                            </a>
                        @endif
                        <button type="submit" class="btn btn-primary">
                            <i class="feather icon-search"></i> {{ __('sales_settings.ui.filters_apply') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- ══════════ الجدول ══════════ --}}
        <div class="card sl-table-card">
            <div class="sl-table-head">
                <h6 class="sl-table-head__title">
                    <i class="feather icon-list"></i> {{ __('sales_installments.schedule_title') }}
                </h6>
                <span class="sl-count sl-num">{{ \App\Support\CurrencySettings::number($rows->total(), 0) }}</span>
            </div>

            @include('sales::installments.partials.detail-table', [
                'rows' => $rows,
                'showAgreement' => true,
                'hasFilters' => $hasFilters,
            ])

            @include('sales::components.table-foot', [
                'paginator' => $rows,
                'sizes' => $pageSizes,
            ])
        </div>
    </div>
@endsection

@section('scripts')
    <script>
        $(function () {
            $('.sl-cancel-detail').on('submit', function (e) {
                e.preventDefault();
                var form = $(this);

                Swal.fire({
                    title: @json(__('sales_settings.ui.confirm_delete_title')),
                    text: @json(__('sales_installments.messages.confirm_cancel_detail'))
                        .replace(':number', form.data('number')),
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: @json(__('sales_settings.ui.confirm_yes')),
                    cancelButtonText: @json(__('sales_settings.ui.cancel')),
                    reverseButtons: true
                }).then(function (result) {
                    if (result.isConfirmed) {
                        form.off('submit').submit();
                    }
                });
            });
        });
    </script>
@endsection
