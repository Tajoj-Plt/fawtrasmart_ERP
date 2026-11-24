{{--
    مدفوعات العملاء — سندات القبض.

    أُعيد بناء الشاشة على قياس شاشة فواتير المبيعات
    (`sales::invoices.index`): ترويسة ← بطاقات ملخّص ← بطاقة تصفية ←
    بطاقة جدول. وقائمة إجراءات الصفّ تخرج من قصّ الجدول عبر
    `sales::partials.table-menu`.

    ما تأتي به الإعدادات:
      • العملة والكسور  ← «العملات»
      • صيغة التاريخ    ← إعدادات اللغة النشطة
      • طرق الدفع       ← «طرق الدفع» المفعَّلة
      • الخزائن         ← «الحسابات المحاسبية» (`cash_treasuries_parent`)
      • الألوان والخط   ← «مظهر النظام» عبر `--app-*`

    ما أُصلح:
      • *بلا بطاقات ملخّص* — لا يُعرف المحصَّل في المدّة إلّا بجمع الصفحة.
      • *كل النصوص مكتوبةٌ بالعربية* في القالب بلا ترجمة.
      • *بنية DataTables كاملةً بلا مكتبة DataTables*، ومُنتقي عدد
        السجلّات مكتوبٌ بيده.
--}}

@extends('sales::master')

@section('title', __('sales_payment.index_title'))

@section('content')
    @include('sales::partials.settings-theme')
    @include('components.hint-tips')
    @include('sales::partials.table-menu')

    @php
        use App\Support\CurrencySettings;

        /* الحقول التي يملؤها المستخدم — لا التاريخ: هو مملوءٌ سلفاً بالفترة
           الجارية، فعدُّه يُظهر «فلاتر مفعّلة» لكل من بحث مرّةً. وكانت القائمة
           تسأل عن `amount_from/to` و`branch` ولا حقلَ لها في الشاشة. */
        $hasFilters = collect(['client_id', 'payment_method', 'treasury_id', 'status_payment', 'collected_by', 'sales_person_user', 'custom_field'])
            ->contains(fn ($key) => request()->filled($key));

        // «بحث متقدّم» يُفتح وحده حين يكون فيه فلترٌ مضبوط
        $advancedActive = request()->filled('treasury_id') || request()->filled('status_payment')
            || request()->filled('collected_by') || request()->filled('sales_person_user');

        $statuses = [1, 2, 3, 4, 5];
    @endphp

    <div class="content-body sl-scope">

        @section('page-actions')
            <button type="button" class="btn btn-outline-secondary" id="printReport">
                <i class="feather icon-printer"></i> {{ __('sales_settings.ui.print') }}
            </button>
        @endsection

        @include('sales::components.page-head', [
            'title' => __('sales_payment.index_title'),
            'icon' => 'credit-card',
            'sub' => __('sales_payment.index_subtitle'),
            'crumbs' => [
                __('sales_settings.ui.home') => route('dashboard'),
                __('sales_payment.plural') => null,
            ],
        ])

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')

        {{-- ══════════ إرشادُ الشاشة ══════════ --}}
        @include('sales::components.guide', [
            'key' => 'sales-payments.index',
            'open' => false,
            'title' => __('sales_payment.guide.index.title'),
            'lead' => __('sales_payment.guide.lead_index'),
            'blocks' => [
                __('sales_payment.guide.what_is.title') => [
                    __('sales_payment.guide.what_is.a'),
                    __('sales_payment.guide.what_is.b'),
                    __('sales_payment.guide.what_is.c'),
                ],
                __('sales_payment.guide.effects.title') => [
                    __('sales_payment.guide.effects.a'),
                    __('sales_payment.guide.effects.b'),
                    __('sales_payment.guide.effects.c'),
                ],
                __('sales_payment.guide.blockers.title') => [
                    __('sales_payment.guide.blockers.a'),
                    __('sales_payment.guide.blockers.b'),
                    __('sales_payment.guide.blockers.c'),
                ],
                __('sales_payment.guide.settings.title') => [
                    __('sales_payment.guide.settings.a'),
                    __('sales_payment.guide.settings.b'),
                ],
            ],
        ])

        @include('sales::components.subscription-bar', ['limit' => $subscriptionLimit ?? null])

        {{-- ══════════ الإحصاءات ══════════ --}}
        <div class="sl-stats">
            <div class="sl-stat">
                <span class="sl-stat__icon sl-stat__icon--success"><i class="feather icon-trending-up"></i></span>
                <div class="sl-stat__body">
                    <p class="sl-stat__value" id="stat-total">{{ CurrencySettings::format($stats['total'] ?? 0) }}</p>
                    <p class="sl-stat__label">{{ __('sales_payment.stats.total') }}</p>
                </div>
            </div>

            <div class="sl-stat">
                <span class="sl-stat__icon"><i class="feather icon-file-text"></i></span>
                <div class="sl-stat__body">
                    <p class="sl-stat__value sl-num" id="stat-count">{{ $stats['count'] ?? 0 }}</p>
                    <p class="sl-stat__label">{{ __('sales_payment.stats.count') }}</p>
                </div>
            </div>

            <div class="sl-stat">
                <span class="sl-stat__icon sl-stat__icon--info"><i class="feather icon-calendar"></i></span>
                <div class="sl-stat__body">
                    <p class="sl-stat__value" id="stat-today">{{ CurrencySettings::format($stats['today'] ?? 0) }}</p>
                    <p class="sl-stat__label">{{ __('sales_payment.stats.today') }}</p>
                </div>
            </div>
        </div>

        {{-- ══════════ البحث والتصفية ══════════ --}}
        <div class="card sl-filters">
            <div class="card-body">
                <div class="sl-filters__head">
                    <h6 class="sl-filters__title">
                        <i class="feather icon-filter"></i> {{ __('sales_settings.ui.filters') }}
                    </h6>

                    <div class="d-flex align-items-center" style="gap:.5rem">
                        @if ($hasFilters)
                            <span class="sl-count">{{ __('sales_documents.ui.filters_active') }}</span>
                        @endif

                        <button type="button" class="btn btn-sm btn-outline-secondary"
                                data-sl-toggle="#advancedSearchForm"
                                aria-expanded="{{ $advancedActive ? 'true' : 'false' }}">
                            <i class="feather icon-sliders"></i> {{ __('sales_settings.ui.advanced') }}
                        </button>
                    </div>
                </div>

                {{-- الصفّ الظاهر: البحث والعميل وطريقة الدفع والمدّة — ما يُسأل عنه
                     كلَّ يوم. والباقي في «بحث متقدّم»، كشاشات الفواتير والمرتجع --}}
                <form method="GET" action="{{ route('paymentsClient.index') }}" id="searchForm"
                      data-search-url="{{ route('paymentsClient.index') }}">
                    <div class="row g-3">
                        <div class="col-lg-3 col-md-6">
                            <label for="custom_field">{{ __('sales_payment.filters.keywords') }}</label>
                            <input type="text" class="form-control" id="custom_field" name="custom_field"
                                   value="{{ request('custom_field') }}"
                                   placeholder="{{ __('sales_payment.filters.keywords_placeholder') }}">
                        </div>

                        <div class="col-lg-3 col-md-6">
                            <label for="client_id">{{ __('sales_payment.filters.client') }}</label>
                            <select class="form-control select2" id="client_id" name="client_id">
                                <option value="">{{ __('sales_payment.filters.any_client') }}</option>
                                @foreach ($clients as $client)
                                    <option value="{{ $client->id }}" @selected(request('client_id') == $client->id)>
                                        {{ $client->trade_name ?: trim($client->first_name.' '.$client->last_name) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-lg-2 col-md-6">
                            <label for="payment_method">{{ __('sales_payment.filters.method') }}</label>
                            <select class="form-control" id="payment_method" name="payment_method">
                                <option value="">{{ __('sales_payment.filters.any_method') }}</option>
                                @foreach ($paymentMethods as $method)
                                    <option value="{{ $method->id }}" @selected(request('payment_method') == $method->id)>
                                        {{ $method->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- المدّة حقلاً واحداً بطرفين — كانت خانتين متجاورتين بلا عنوانٍ للثانية --}}
                        <div class="col-lg-4 col-md-6">
                            <label for="from_date">{{ __('sales_payment.filters.date_range') }}</label>
                            <div class="input-group">
                                <input type="date" class="form-control" id="from_date" name="from_date"
                                       value="{{ request('from_date', $range['start'] ?? '') }}">
                                <input type="date" class="form-control" id="to_date" name="to_date"
                                       value="{{ request('to_date', $range['end'] ?? '') }}">
                            </div>
                            @if ($range['is_default'] ?? false)
                                <small class="sl-help">{{ __('sales_payment.filters.period_default') }}</small>
                            @endif
                        </div>
                    </div>

                    <div class="collapse {{ $advancedActive ? 'show' : '' }}" id="advancedSearchForm">
                        <div class="row g-3 mt-1">
                        <div class="col-md-4">
                            <label for="treasury_id">{{ __('sales_payment.filters.treasury') }}</label>
                            <select class="form-control" id="treasury_id" name="treasury_id">
                                <option value="">{{ __('sales_payment.filters.any_treasury') }}</option>
                                @foreach ($treasuries as $box)
                                    <option value="{{ $box->id }}" @selected(request('treasury_id') == $box->id)>
                                        {{ $box->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label for="status_payment">{{ __('sales_payment.filters.status') }}</label>
                            <select class="form-control" id="status_payment" name="status_payment">
                                <option value="">{{ __('sales_payment.filters.any_status') }}</option>
                                @foreach ($statuses as $status)
                                    <option value="{{ $status }}" @selected(request('status_payment') == $status)>
                                        {{ __('sales_payment.status.'.$status) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label for="collected_by">{{ __('sales_payment.filters.added_by') }}</label>
                            <select class="form-control select2" id="collected_by" name="collected_by">
                                <option value="">{{ __('sales_payment.filters.any_user') }}</option>
                                @foreach ($employees as $employee)
                                    <option value="{{ $employee->id }}" @selected(request('collected_by') == $employee->id)>
                                        {{ $employee->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- مسؤول المبيعات — مسؤولُ الفاتورة المسدَّدة؛ السندُ لا عمودَ له --}}
                        <div class="col-md-4">
                            <label for="sales_person_user">{{ __('sales_invoice.filters.sales_person') }}</label>
                            <select class="form-control select2" id="sales_person_user" name="sales_person_user">
                                <option value="">{{ __('sales_payment.filters.any_user') }}</option>
                                @foreach ($salesPersons ?? [] as $person)
                                    <option value="{{ $person->id }}" @selected(request('sales_person_user') == $person->id)>
                                        {{ $person->full_name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        </div>
                    </div>

                    <div class="sl-filters__foot">
                        <div class="d-flex" style="gap:.5rem">
                            <a href="{{ route('paymentsClient.index') }}" id="resetSearch" class="btn btn-outline-secondary">
                                <i class="feather icon-x"></i> {{ __('sales_documents.ui.reset') }}
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="feather icon-search"></i> {{ __('sales_documents.ui.search') }}
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        {{-- ══════════ الجدول ══════════ --}}
        <div class="card sl-table-card">
            <div class="sl-table-head">
                <h6 class="sl-table-head__title">
                    <i class="feather icon-list"></i> {{ __('sales_payment.plural') }}
                    <span class="sl-count">{{ $payments->total() }}</span>
                </h6>

                <div class="spinner-border spinner-border-sm text-primary d-none" id="loading-indicator"
                     role="status"></div>
            </div>

            {{-- `#results-container` عقدٌ مع السكربت: يُستبدَل محتواه وحده
                 عند كل تصفية أو تنقّل، وعليه تُفوَّض نقرات الترقيم.

                 وعدّاد «يُعرض من… إلى…» ومُنتقي عدد السجلّات صارا داخل
                 الجدول لا فوقه: هما جزءٌ ممّا يتغيّر مع الصفحة، وبقاؤهما
                 خارج الحاوية يُبقيهما على أرقام الصفحة الأولى. --}}
            <div id="results-container">
                @include('sales::payment.partials.table', ['payments' => $payments])
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    @php $slFiltersVersion = @filemtime(public_path('assets/js/sales-index-filters.js')) ?: time(); @endphp
    <script src="{{ asset('assets/js/sales-index-filters.js') }}?v={{ $slFiltersVersion }}"></script>

    {{--
        التنقّل بين الصفحات والتصفية بـAjax.

        **ما كان** — قالبُ الترقيم يرسم روابطَ `href="#"` تحمل
        `data-page`، **ولا مستمعَ لها في الشاشة إطلاقاً**: فالضغط على
        «التالي» أو على رقم صفحةٍ لا يفعل شيئاً سوى القفز إلى أعلى
        الصفحة. ومُنتقي عدد السجلّات يُمرَّر إليه `paginator` — والمكوّن
        لا يعرف هذا الوسيط، إنّما يعرف `onchange` — فتغييرُ العدد لا
        يعيد التحميل أيضاً.

        و`SalesPaymentController::index()` يردّ JSON كاملاً على طلب
        XHR منذ البداية (`success` و`data` و`current_page` و
        `last_page`)، فالخادم كان جاهزاً والشاشة وحدها لا تناديه.

        الوصل الآن نفسه الذي في شاشات الفواتير والمرتجع والإشعارات
        والفواتير الدورية: `loadData(page)` واحدة، ونقراتُ الترقيم
        مفوَّضةٌ على `#results-container`.
    --}}
    <script>
        (function () {
            'use strict';

            /* التصفية والترقيم والمسح وزرّ الرجوع — `sales-index-filters.js`،
               السلوكُ نفسُه في الشاشات الخمس. */
            $(function () {
                SalesIndexFilters.bind({
                    url: @json(route('paymentsClient.index')),
                    failText: @json(__('sales_settings.ui.load_failed')),
                    onData: function (response) {
                        $('#stat-total').text(response.stats_total);
                        $('#stat-count').text(response.stats_count);
                        $('#stat-today').text(response.stats_today);
                    },
                });
            });
        })();
    </script>

    <script>
        (function () {
            'use strict';

            /* الإلغاء يعكس القيد ويعيد المبلغ إلى ذمّة العميل — فعلٌ لا
               يُتراجَع عنه، فيُسأل عنه قبل الإرسال. والربط بالتفويض:
               الجدول يُستبدَل بعد كل تصفية. */
            document.addEventListener('click', function (event) {
                const trigger = event.target.closest('.cancel-payment');
                if (!trigger) return;

                event.preventDefault();

                const id = trigger.dataset.id;
                const code = trigger.dataset.code || '';

                const send = function () {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = @json(route('paymentsClient.cancel', ':id')).replace(':id', id);
                    form.innerHTML = '<input type="hidden" name="_token" value="{{ csrf_token() }}">';
                    document.body.appendChild(form);
                    form.submit();
                };

                if (typeof Swal === 'undefined') {
                    if (confirm(@json(__('sales_payment.show.cancel_text')))) send();
                    return;
                }

                Swal.fire({
                    title: @json(__('sales_payment.show.cancel_title')) + ' ' + code,
                    text: @json(__('sales_payment.show.cancel_text')),
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: @json(__('sales_payment.show.cancel_confirm')),
                    cancelButtonText: @json(__('sales_payment.show.cancel_dismiss')),
                    reverseButtons: true,
                }).then(function (result) {
                    if (result.isConfirmed) send();
                });
            });

            const print = document.getElementById('printReport');
            if (print) print.addEventListener('click', () => window.print());
        })();
    </script>
@endsection
