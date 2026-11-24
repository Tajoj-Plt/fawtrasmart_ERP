@extends('sales::master')

{{--
    ══════════════════════════════════════════════════════════════════════
    فهرس عروض الأسعار
    ══════════════════════════════════════════════════════════════════════

    البنية منقولة عن شاشة «الأذون المخزنية»
    (`stock::store_permits_management.index`): ترويسةٌ بإجراءاتها، ثم
    بطاقة بحثٍ وتصفية ببحثٍ متقدّم مطويّ، ثم بطاقة نتائج يُستبدل جدولها
    وحده بـAjax. الأصناف بالبادئة `sl-`.

    ── ما كان ──

      • ٤٨٤ سطراً، عربيّتها كلّها في الوسم.
      • `<style>` في `@section('css')` بألوانٍ مثبَّتة لا تتبع «مظهر النظام».
      • بطاقة إجراءات منفصلة تحمل زرّاً واحداً، وترويسةٌ مكتوبة بيدها.
      • فلاتر بأصناف Bootstrap 5 (`mb-3`, `gap-2`) في قالبٍ على Bootstrap 4.
      • `#results-container` فارغ ثم `loadData()` بعد ١٠٠ مِلّي — فالشاشة
        تُرسَم فارغةً ثم تُملأ، ومن أطفأ JS لا يرى شيئاً أبداً.

    ── ما صار ──

      • الصفحة الأولى مُصيَّرة في الخادم، وAjax للبحث والتنقّل بعدها.
      • النطاق الافتراضي من الفترة المحاسبية الجارية لا من تاريخ المنشأة كلّه.
      • كل مبلغ عبر «العملات»، وكل تاريخ عبر صيغة اللغة النشطة.
--}}

@section('title', $doc['t']('doc.title'))

@section('content')
    @include('sales::partials.settings-theme')
    @include('components.hint-tips')
    @include('sales::partials.table-menu')

    @php
        $activeFilters = request()->hasAny([
            'client_id', 'id', 'status', 'branch', 'total_from', 'total_to',
            'from_date_1', 'to_date_1', 'item_search', 'created_by', 'currency', 'sales_person',
        ]);

        /* «بحث متقدّم» يُفتح وحده حين يكون فيه فلترٌ مضبوط — لا لأنّ التاريخ
           في الرابط: التاريخ مملوءٌ سلفاً، فكانت اللوحةُ تُفتح بعد كل بحث. */
        $advancedActive = request()->filled('created_by') || request()->filled('sales_person')
            || request()->filled('item_search') || request()->filled('total_from')
            || request()->filled('total_to');
    @endphp

    <div class="content-body sl-scope">

        @section('page-actions')
            {{-- الصلاحيتان تفتحان الشاشة نفسها، ويفرّق بينهما المتحكّم في
                 ما يُعرَض لا في الوصول — فشرطٌ واحد يجمعهما --}}
            @if ($doc['can']('create'))
                <a href="{{ route($doc['routes']['create']) }}" class="btn btn-primary">
                    <i class="feather icon-plus"></i> {{ $doc['t']('doc.new') }}
                </a>
            @endif

            <button type="button" class="btn btn-outline-secondary" id="printReport">
                <i class="feather icon-printer"></i> {{ $doc['t']('ui.print') }}
            </button>

            <button type="button" class="btn btn-outline-secondary" id="exportExcel">
                <i class="feather icon-download"></i> {{ $doc['t']('ui.export_shown') }}
            </button>
        @endsection

        @include('sales::components.page-head', [
            'title' => $doc['t']('doc.title'),
            'icon' => 'file-text',
            'sub' => $doc['t']('doc.subtitle'),
            'crumbs' => [
                $doc['t']('ui.home') => route('dashboard'),
                $doc['t']('doc.title') => null,
            ],
        ])

        @include('layouts.alerts.success')
        @include('layouts.alerts.error')

        {{-- ══════════ إرشادُ الشاشة ══════════ --}}
        @include('sales::components.guide', [
            'key' => 'sales-quotes.index',
            'title' => $doc['t']('guide.index.title'),
            'lead' => $doc['t']('guide.lead_index'),
            'open' => false,
            'blocks' => [
                $doc['t']('guide.what_is.title') => [
                    $doc['t']('guide.what_is.a'),
                    $doc['t']('guide.what_is.b'),
                    $doc['t']('guide.what_is.c'),
                ],
                $doc['t']('guide.conversion.title') => [
                    $doc['t']('guide.conversion.a'),
                    $doc['t']('guide.conversion.b'),
                    $doc['t']('guide.conversion.c'),
                    $doc['t']('guide.conversion.d'),
                ],
                $doc['t']('guide.statuses.title') => [
                    $doc['t']('guide.statuses.a'),
                    $doc['t']('guide.statuses.b'),
                    $doc['t']('guide.statuses.c'),
                ],
                $doc['t']('guide.settings.title') => [
                    $doc['t']('guide.settings.a'),
                    $doc['t']('guide.settings.b'),
                    $doc['t']('guide.settings.c'),
                ],
            ],
        ])

        @include('sales::components.subscription-bar', ['limit' => $subscriptionLimit ?? null])

        {{-- ══════════ الإحصاءات ══════════
             نفس صفّ البطاقات في شاشة الفواتير: قيمةٌ وعددٌ ونطاق. --}}
        <div class="sl-stats">
            <div class="sl-stat">
                <span class="sl-stat__icon sl-stat__icon--success"><i class="feather icon-file-text"></i></span>
                <div class="sl-stat__body">
                    <p class="sl-stat__value" id="quotes-total-stat">
                        {{ \App\Support\CurrencySettings::format($quotesTotal ?? 0) }}
                    </p>
                    <p class="sl-stat__label">{{ $doc['t']('stats.total') }}</p>
                </div>
            </div>

            <div class="sl-stat">
                <span class="sl-stat__icon"><i class="feather icon-hash"></i></span>
                <div class="sl-stat__body">
                    <p class="sl-stat__value" id="quotes-count-stat">{{ $quotesCount ?? 0 }}</p>
                    <p class="sl-stat__label">{{ $doc['t']('stats.count') }}</p>
                </div>
            </div>

            <div class="sl-stat">
                <span class="sl-stat__icon sl-stat__icon--info"><i class="feather icon-calendar"></i></span>
                <div class="sl-stat__body">
                    <p class="sl-stat__value sl-num" style="font-size:1.05rem">
                        {{ $range['start'] ?? '' }} — {{ $range['end'] ?? '' }}
                    </p>
                    <p class="sl-stat__label">{{ $doc['t']('filters.date_range') }}</p>
                </div>
            </div>
        </div>

        {{-- ══════════════════════════════════════════════════════════
             البحث والتصفية
             ══════════════════════════════════════════════════════════ --}}
        <div class="card sl-filters">
            <div class="card-body">
                <div class="sl-filters__head">
                    <h6 class="sl-filters__title">
                        <i class="feather icon-filter"></i> {{ $doc['t']('ui.filter_title') }}
                    </h6>

                    <div class="d-flex align-items-center" style="gap:.5rem">
                        @if ($activeFilters)
                            <span class="sl-count">{{ $doc['t']('ui.filters_active') }}</span>
                        @endif

                        {{-- `data-sl-toggle` لا `data-toggle`: القالب يحمّل Bootstrap 4 و5،
                             فيفتح أحدُهما اللوحة ويغلقها الآخر --}}
                        <button type="button" class="btn btn-sm btn-outline-secondary"
                                data-sl-toggle="#advancedSearchForm"
                                aria-expanded="{{ $advancedActive ? 'true' : 'false' }}">
                            <i class="feather icon-sliders"></i> {{ $doc['t']('ui.advanced_search') }}
                        </button>
                    </div>
                </div>

                <form id="searchForm" data-search-url="{{ route($doc['routes']['index']) }}">
                    <div class="row">
                        <div class="col-lg-3 col-md-6">
                            <div class="form-group">
                                <label for="id">{{ $doc['t']('filters.number') }}</label>
                                <input type="text" id="id" name="id" class="form-control"
                                       value="{{ request('id') }}"
                                       placeholder="{{ $doc['t']('filters.keywords_placeholder') }}">
                            </div>
                        </div>

                        <div class="col-lg-3 col-md-6">
                            <div class="form-group">
                                <label for="client_id">{{ $doc['t']('filters.client') }}</label>
                                <select id="client_id" name="client_id" class="form-control select2">
                                    <option value="">{{ $doc['t']('filters.any_client') }}</option>
                                    @foreach ($clients as $client)
                                        <option value="{{ $client->id }}"
                                            {{ (string) request('client_id') === (string) $client->id ? 'selected' : '' }}>
                                            {{ $client->trade_name ?: trim(($client->first_name ?? '').' '.($client->last_name ?? '')) }}@if (filled($client->code)) - {{ $client->code }}@endif
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="col-lg-2 col-md-6">
                            <div class="form-group">
                                <label for="status">{{ $doc['t']('status.label') }}</label>
                                <select id="status" name="status" class="form-control select2">
                                    <option value="">{{ $doc['t']('status.any') }}</option>
                                    @foreach (\App\Models\Quote::STATUSES as $state)
                                        <option value="{{ $state }}"
                                            {{ (string) request('status') === $state ? 'selected' : '' }}>
                                            {{ $doc['t']('status.'.$state) }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        {{-- نطاق التاريخ في الصفّ الظاهر: يصفّي كلَّ زيارةٍ بالفترة الجارية،
                             وكان مطويّاً في «بحث متقدّم» --}}
                        <div class="col-lg-4 col-md-6">
                            <div class="form-group">
                                <label for="from_date_1">{{ $doc['t']('filters.date_range') }}</label>

                                <div class="input-group">
                                    <input type="date" id="from_date_1" name="from_date_1" class="form-control"
                                           value="{{ request('from_date_1', $range['start'] ?? '') }}">
                                    <input type="date" name="to_date_1" class="form-control"
                                           value="{{ request('to_date_1', $range['end'] ?? '') }}">
                                </div>

                                @if (! empty($range['is_default']))
                                    <span class="sl-hint">{{ $doc['t']('filters.period_default') }}</span>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="collapse {{ $advancedActive ? 'show' : '' }}" id="advancedSearchForm">
                        <div class="row">
                            <div class="col-md-3 col-sm-6">
                                <div class="form-group">
                                    <label for="created_by">{{ $doc['t']('filters.created_by') }}</label>
                                    <select id="created_by" name="created_by" class="form-control select2">
                                        <option value="">{{ $doc['t']('filters.any_user') }}</option>
                                        @foreach ($employees as $employee)
                                            <option value="{{ $employee->id }}"
                                                {{ (string) request('created_by') === (string) $employee->id ? 'selected' : '' }}>
                                                {{ $employee->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            {{-- مسؤول المبيعات — `employee_id` المحفوظ مع المستند، غير «المُنشئ» --}}
                            <div class="col-md-3 col-sm-6">
                                <div class="form-group">
                                    <label for="sales_person">{{ __('sales_invoice.filters.sales_person') }}</label>
                                    <select id="sales_person" name="sales_person" class="form-control select2">
                                        <option value="">{{ $doc['t']('filters.any_user') }}</option>
                                        @foreach ($salesPersons ?? [] as $person)
                                            <option value="{{ $person->id }}"
                                                {{ (string) request('sales_person') === (string) $person->id ? 'selected' : '' }}>
                                                {{ $person->full_name ?? $person->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-3 col-sm-6">
                                <div class="form-group">
                                    <label for="item_search">{{ $doc['t']('filters.item') }}</label>
                                    <input type="text" id="item_search" name="item_search" class="form-control"
                                           value="{{ request('item_search') }}"
                                           placeholder="{{ $doc['t']('filters.item_placeholder') }}">
                                </div>
                            </div>

                            <div class="col-md-3 col-sm-6">
                                <div class="form-group">
                                    <label for="total_from">{{ $doc['t']('filters.total_from') }}</label>
                                    <input type="number" id="total_from" name="total_from" min="0" step="0.01"
                                           class="form-control" value="{{ request('total_from') }}">
                                </div>
                            </div>

                            <div class="col-md-3 col-sm-6">
                                <div class="form-group">
                                    <label for="total_to">{{ $doc['t']('filters.total_to') }}</label>
                                    <input type="number" id="total_to" name="total_to" min="0" step="0.01"
                                           class="form-control" value="{{ request('total_to') }}">
                                </div>
                            </div>

                        </div>
                    </div>

                    <div class="sl-filters__foot">
                        <button type="button" id="resetSearch" class="btn btn-outline-secondary">
                            <i class="feather icon-rotate-ccw"></i> {{ $doc['t']('ui.reset') }}
                        </button>

                        <button type="submit" class="btn btn-primary">
                            <i class="feather icon-search"></i> {{ $doc['t']('ui.search') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- ══════════════════════════════════════════════════════════
             النتائج
             ══════════════════════════════════════════════════════════ --}}
        <div class="card sl-table-card">
            <div class="sl-table-head">
                <h6 class="sl-table-head__title">
                    <i class="feather icon-list"></i> {{ $doc['t']('doc.list') }}
                    <span class="sl-count">{{ $quotes->total() }}</span>
                </h6>

                <div class="spinner-border spinner-border-sm text-primary d-none"
                     id="loading-indicator" role="status">
                    <span class="sr-only">{{ $doc['t']('ui.loading') }}</span>
                </div>
            </div>

            {{-- الصفحة الأولى مُصيَّرة في الخادم: الشاشة تصل مكتملةً بلا
                 ومضةٍ فارغة، وAjax للبحث والتنقّل بعدها --}}
            <div id="results-container">
                @include('sales::qoution.partials.table', ['quotes' => $quotes])
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @php $slFiltersVersion = @filemtime(public_path('assets/js/sales-index-filters.js')) ?: time(); @endphp
    <script src="{{ asset('assets/js/sales-index-filters.js') }}?v={{ $slFiltersVersion }}"></script>

    @include('sales::components.report-tools')

    <script>
        $(function () {
            slReportTools({
                table: '#quotesTable',
                fileName: @json($doc['t']('doc.title')),
                title: @json($doc['t']('doc.title')),
                subTitle: @json(config('app.name')),
                // الإجمالي هو العمود السادس (رقم، تاريخ، عميل، فرع، بنود، إجمالي) — [4] كان عدد البنود
                numericColumns: [5],
                exportButton: '#exportExcel',
                printButton: '#printReport',
                descriptionColumn: 2,
            });
        });
    </script>

    @php
        /*
         * مفردات الرسائل تُجمَّع هنا لا داخل `@json([...])`.
         *
         * مُحلّل وسائط Blade يوازن الأقواس الهلالية `()` وحدها ولا يرى
         * المربّعة `[]`. فمصفوفةٌ متعدّدة الأسطر داخل `@json(` وفيها
         * `__('key', ['number' => '…'])` تُقطَع عند أوّل موضعٍ تتوازن فيه
         * الهلالية، فيخرج `json_encode([` بلا `]` تقابله — ويسقط الطلب
         * بخطأ 500: «Unclosed '[' … does not match ')'». وهو ما كان يمنع
         * فتح هذه الشاشة أصلاً.
         *
         * المتغيّر الواحد لا قوس مربّع فيه، فيمرّ سليماً.
         */
        $jsText = [
            'delete_title' => $doc['t']('confirm.delete_title', ['number' => '__N__']),
            'delete_text' => $doc['t']('confirm.delete_text'),
            'convert_title' => $doc['t']('confirm.convert_title', ['number' => '__N__']),
            'convert_text' => $doc['t']('confirm.convert_text'),
            'yes' => $doc['t']('ui.yes'),
            'cancel' => $doc['t']('ui.cancel'),
            'failed' => $doc['t']('ui.action_failed'),
            'load_failed' => $doc['t']('ui.load_failed'),
        ];
    @endphp

    <script>
        (function () {
            'use strict';

            var routes = {
                index: @json(route($doc['routes']['index'])),
                destroy: @json(route($doc['routes']['destroy'], ['id' => '__ID__'])),
                convert: @json(route($doc['routes']['convert'], ['id' => '__ID__'])),
            };

            var token = @json(csrf_token());

            var text = @json($jsText);

            /* التأكيد بـSweetAlert2 إن وُجد وإلّا بـ`confirm` الأصلي:
               الشاشة يجب أن تعمل قبل أن تُحمَّل مكتبةٌ خارجية */
            function ask(title, body, run) {
                if (window.Swal) {
                    window.Swal.fire({
                        icon: 'question', title: title, text: body,
                        showCancelButton: true,
                        confirmButtonText: text.yes, cancelButtonText: text.cancel,
                        reverseButtons: true,
                    }).then(function (r) { if (r.isConfirmed) run(); });
                    return;
                }

                if (window.confirm(title + '\n\n' + body)) run();
            }

            function fail(message) {
                if (window.Swal) {
                    window.Swal.fire({ icon: 'error', title: text.failed, text: message || '' });
                } else {
                    window.alert(text.failed + (message ? '\n' + message : ''));
                }
            }

            $(function () {
                if ($.fn.select2) {
                    $('#searchForm .select2').select2({
                        placeholder: @json($doc['t']('ui.all')),
                        allowClear: true,
                        dir: @json(\App\Support\LanguageSettings::direction()),
                        width: '100%',
                    });
                }

                /* التصفية والترقيم والمسح وزرّ الرجوع — `sales-index-filters.js`،
                   السلوكُ نفسُه في الشاشات الخمس. */
                SalesIndexFilters.bind({
                    url: routes.index,
                    failText: text.load_failed,
                    onData: function (response) {
                        $('#quotes-total-stat').text(response.quotes_total);
                        $('#quotes-count-stat').text(response.quotes_count);
                    },
                });

                $(document).on('click', '.quote-delete', function (e) {
                    e.preventDefault();
                    var id = $(this).data('id');
                    var number = $(this).data('number');

                    ask(
                        text.delete_title.replace('__N__', number),
                        text.delete_text,
                        function () {
                            $.ajax({
                                url: routes.destroy.replace('__ID__', id),
                                method: 'POST',
                                data: { _token: token, _method: 'DELETE' },
                            })
                                .done(function () { window.loadData(1); })
                                .fail(function (xhr) {
                                    fail(xhr.responseJSON && xhr.responseJSON.message);
                                });
                        }
                    );
                });

                $(document).on('click', '.quote-convert', function (e) {
                    e.preventDefault();
                    var id = $(this).data('id');
                    var number = $(this).data('number');

                    ask(
                        text.convert_title.replace('__N__', number),
                        text.convert_text,
                        function () {
                            /* التحويل يُنشئ فاتورةً ويخصم مخزوناً ويُرحّل
                               قيداً — فيُرسَل بنموذجٍ حقيقي لتتبعه إعادةُ
                               التوجيه إلى الفاتورة الجديدة */
                            var form = document.createElement('form');
                            form.method = 'POST';
                            form.action = routes.convert.replace('__ID__', id);
                            form.innerHTML = '<input type="hidden" name="_token" value="' + token + '">';
                            document.body.appendChild(form);
                            form.submit();
                        }
                    );
                });

                /* الطباعة والتصدير من `slReportTools` — نفس الأداة التي
                   تستعملها شاشة الفواتير وشاشة الأذون المخزنية.

                   كان الزرّان مكتوبَين هنا بيدهما: `window.print()` تطبع
                   الصفحة كلَّها بفلاترها وقائمتها الجانبية، والتصدير يبني
                   CSV بسلاسل نصّية — بلا ترويسة تقرير ولا تنسيق أرقام ولا
                   عمود إجمالي. */
            });
        })();
    </script>
@endpush
