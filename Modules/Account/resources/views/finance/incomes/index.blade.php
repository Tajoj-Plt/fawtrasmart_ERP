@extends('account::master')

@section('title', 'سندات القبض')

@section('content')
    <div class="content-body">

        @section('page-actions')
            <button type="button" id="printBtn" class="btn btn-outline-secondary">
                <i class="feather icon-printer"></i> طباعة
            </button>
            @can('account.incomes.create')
                <a href="{{ route('incomes.create') }}" class="btn btn-primary">
                    <i class="feather icon-plus"></i> سند قبض جديد
                </a>
            @endcan
        @endsection

        @include('account::components.page-head', [
            'title' => 'سندات القبض',
            'icon' => 'file-plus',
            'sub' => 'الإيرادات المقبوضة وتصنيفاتها وقيودها المحاسبية',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'الحسابات' => null,
                'سندات القبض' => null,
            ],
        ])

        {{--
            الإجماليات كانت لوحاً أرجوانياً (bg-royal-deep) بثلاثة عناوين h2
            بيضاء على تدرّج — لونٌ لا يظهر في بقية الشاشات ولا يتبع سمة النظام.
            صارت بطاقات stk-metric نفسها المستعملة في الأصول والمخزون،
            ويحدّثها AJAX بالترميز ذاته.

            وزرّ «استيراد» كان يشير إلى # — رابطٌ لا يفعل شيئاً — فحُذف.
        --}}
        @php
            $totals = $initialIncomes['totals'] ?? null;
        @endphp

        <div class="stk-metrics" id="totals-container">
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-calendar"></i></span>
                <span class="stk-metric__label">{{ $period['label'] ?? 'الفترة المعروضة' }}</span>
                <span class="stk-metric__value">@money($totals['totalPeriod'] ?? 0)</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-calendar"></i></span>
                <span class="stk-metric__label">آخر 30 يوماً</span>
                <span class="stk-metric__value">@money($totals['totalLast30Days'] ?? 0)</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-trending-up"></i></span>
                <span class="stk-metric__label">آخر 365 يوماً</span>
                <span class="stk-metric__value">@money($totals['totalLast365Days'] ?? 0)</span>
            </div>
        </div>

        <div class="card stk-filters">
            <div class="card-body">
                <div class="stk-filters__head">
                    <h6 class="stk-filters__title"><i class="feather icon-filter"></i> بحث وتصفية</h6>

                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse"
                        data-bs-target="#advancedSearchForm" onclick="toggleSearchText(this)">
                        <i class="feather icon-sliders"></i> <span class="button-text">بحث متقدم</span>
                    </button>
                </div>

                <form class="form" id="searchForm">
                    @csrf
                    <div class="row">
                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="keywords">بحث</label>
                                <input type="text" id="keywords" name="keywords" class="form-control"
                                    placeholder="الاسم أو الكود…">
                            </div>
                        </div>

                        {{--
                            حقلا التاريخ كانا فارغين دائماً، والشاشة تفتح على
                            الجدول كلّه. صارا يُملآن من الفترة المحاسبية
                            الجارية، ومعهما قائمةٌ باختيار فترةٍ أخرى ومركزِ
                            تكلفة.
                        --}}
                        @include('partials.accounting-period-filter', [
                            'colClass' => 'col-md-3 col-sm-6',
                            'costName' => 'cost_center_id',
                            'period' => $period ?? [],
                        ])

                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="added_by">أضيف بواسطة</label>
                                <select name="added_by" id="added_by" class="form-control select2">
                                    <option value="">جميع الموظفين</option>
                                    @foreach ($users as $user)
                                        <option value="{{ $user->id }}">{{ $user->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="category">التصنيف</label>
                                <select name="category" id="category" class="form-control select2">
                                    <option value="">جميع التصنيفات</option>
                                    @foreach ($categories as $category)
                                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="status">الحالة</label>
                                <select name="status" id="status" class="form-control select2">
                                    <option value="">جميع الحالات</option>
                                    <option value="1">نشط</option>
                                    <option value="2">متوقف</option>
                                    <option value="3">غير نشط</option>
                                </select>
                            </div>
                        </div>

                        {{-- ثلاثُ حالاتٍ لا اثنتان: خانةُ التأشير لا تُتيح طلبَ
                             «غير الضريبية» وحدَها، وهي ما يُراجَع عند التسوية. --}}
                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="taxable">الضريبة</label>
                                <select name="taxable" id="taxable" class="form-control select2">
                                    <option value="">الكل</option>
                                    <option value="1" @selected(request('taxable') === '1')>ضريبية فقط</option>
                                    <option value="0" @selected(request('taxable') === '0')>غير ضريبية</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="collapse" id="advancedSearchForm">
                        <div class="row">
                            <div class="col-md-4 col-sm-6">
                                <div class="form-group">
                                    <label for="description">الوصف</label>
                                    <input type="text" id="description" name="description" class="form-control"
                                        placeholder="جزء من نصّ الوصف…">
                                </div>
                            </div>

                            <div class="col-md-4 col-sm-6">
                                <div class="form-group">
                                    <label for="branch">الفرع</label>
                                    <select name="branch" id="branch" class="form-control select2">
                                        <option value="">جميع الفروع</option>
                                        @foreach ($branches as $branch)
                                            <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-2 col-sm-6">
                                <div class="form-group">
                                    <label for="amount_from">أقل مبلغ</label>
                                    <input type="number" step="0.01" id="amount_from" name="amount_from"
                                        class="form-control" placeholder="0.00">
                                </div>
                            </div>

                            <div class="col-md-2 col-sm-6">
                                <div class="form-group">
                                    <label for="amount_to">أكبر مبلغ</label>
                                    <input type="number" step="0.01" id="amount_to" name="amount_to"
                                        class="form-control" placeholder="0.00">
                                </div>
                            </div>

                            <div class="col-md-2 col-sm-6">
                                <div class="form-group">
                                    <label for="created_at_from">أُنشئ من</label>
                                    <input type="date" id="created_at_from" name="created_at_from" class="form-control">
                                </div>
                            </div>

                            <div class="col-md-2 col-sm-6">
                                <div class="form-group">
                                    <label for="created_at_to">أُنشئ إلى</label>
                                    <input type="date" id="created_at_to" name="created_at_to" class="form-control">
                                </div>
                            </div>

                            <div class="col-md-4 col-sm-6">
                                <div class="form-group">
                                    <label for="sub_account">الحساب الفرعي</label>
                                    <select name="sub_account" id="sub_account" class="form-control select2">
                                        <option value="">أي حساب</option>
                                        @foreach ($Accounts as $account)
                                            <option value="{{ $account->id }}">
                                                {{ $account->name }}{{ $account->client?->code ? ' — ' . $account->client->code : '' }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="stk-filters__foot">
                        <button type="button" id="resetSearch" class="btn btn-outline-secondary">
                            <i class="feather icon-rotate-ccw"></i> إعادة تعيين
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="feather icon-search"></i> بحث
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card stk-table-card">
            <div id="results-container">
                @if (isset($initialIncomes))
                    @include('account::finance.incomes.partials.table', [
                        'incomes' => $initialIncomes['incomes'],
                        'account_setting' => $initialIncomes['account_setting'],
                        'canEdit' => $canEdit,
                    ])
                @endif
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <script src="{{ asset('js/print-table.js') }}"></script>

    <script>
        window.csrfToken = '{{ csrf_token() }}';
        window.routes = {
            incomes: {
                cancel: '{{ route('incomes.cancel', ':id') }}',
                index: '{{ route('incomes.index') }}'
            }
        };

        /** تنسيق المبالغ المولَّدة من AJAX بعملة النظام المضبوطة في الإعدادات */
        function money(value) {
            return window.AppCurrency.format(value || 0);
        }

        $(document).ready(function() {
            showLoading();

            setTimeout(function() {
                @if (!isset($initialIncomes))
                    loadData();
                @else
                    hideLoading();
                @endif
            }, 100);

            $('#searchForm').on('submit', function(e) {
                e.preventDefault();
                loadData();
            });

            $('#searchForm input, #searchForm select').on('change input', function() {
                clearTimeout(window.searchTimeout);
                window.searchTimeout = setTimeout(function() {
                    loadData();
                }, 500);
            });

            $('#resetSearch').on('click', function() {
                $('#searchForm')[0].reset();
                $('.select2').val(null).trigger('change');
                loadData();
            });

            $('#printBtn').on('click', function() {
                printIncomesReport();
            });

            $(document).on('click', '.pagination a', function(e) {
                e.preventDefault();
                var url = $(this).attr('href');
                if (url) {
                    var page = new URL(url, window.location.origin).searchParams.get('page');
                    loadData(page, $('#perPage').val() || 10);
                }
            });

            window.loadData = function(page, perPage) {
                page = page || 1;
                perPage = perPage || $('#perPage').val() || 10;

                showLoading();

                var formData = $('#searchForm').serialize();
                formData += '&page=' + page;
                formData += '&per_page=' + perPage;
                formData += '&is_ajax=1';

                $.ajax({
                    url: window.routes.incomes.index,
                    method: 'GET',
                    data: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    cache: false,
                    success: function(response) {
                        if (response.success) {
                            $('#results-container').html(response.data);
                            updateTotals(response.totals);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('خطأ في تحميل سندات القبض:', error, status, xhr.responseText);
                        $('#results-container').html(
                            '<div class="stk-empty">' +
                            '<span class="stk-empty__icon"><i class="feather icon-alert-triangle"></i></span>' +
                            '<h6 class="stk-empty__title">تعذّر تحميل البيانات</h6>' +
                            '<p class="stk-empty__hint">حدث خطأ أثناء الجلب — أعِد المحاولة.</p>' +
                            '</div>'
                        );
                    },
                    complete: function() {
                        hideLoading();
                    }
                });
            };

            window.showLoading = showLoading;
            window.hideLoading = hideLoading;

            function showLoading() {
                $('#results-container').css('opacity', '0.6');
                if ($('#loading-indicator').length === 0) {
                    $('#results-container').prepend(
                        '<div id="loading-indicator" class="text-center p-3">' +
                        '<div class="spinner-border text-primary" role="status">' +
                        '<span class="visually-hidden">جارٍ التحميل…</span>' +
                        '</div></div>'
                    );
                }
            }

            function hideLoading() {
                $('#loading-indicator').remove();
                $('#results-container').css('opacity', '1');
            }

            /** إعادة رسم بطاقات الإجماليات بنفس ترميز stk-metric المرسوم خادمياً */
            function updateTotals(totals) {
                if (!totals) {
                    return;
                }

                var cards = [
                    ['calendar', @json($period['label'] ?? 'الفترة المعروضة'), totals.totalPeriod],
                    ['calendar', 'آخر 30 يوماً', totals.totalLast30Days],
                    ['trending-up', 'آخر 365 يوماً', totals.totalLast365Days]
                ];

                $('#totals-container').html(cards.map(function(card) {
                    return '<div class="stk-metric">' +
                        '<span class="stk-metric__icon"><i class="feather icon-' + card[0] + '"></i></span>' +
                        '<span class="stk-metric__label">' + card[1] + '</span>' +
                        '<span class="stk-metric__value">' + money(card[2]) + '</span>' +
                        '</div>';
                }).join(''));
            }
        });

        /**
         * إلغاء السند مع عكس قيده.
         *
         * في النطاق العام لأن أزرار الجدول تُعاد بناؤها مع كل استجابة AJAX،
         * فلا يصلح ربطها داخل ready.
         */
        function confirmCancel(id) {
            Swal.fire({
                title: 'إلغاء سند القبض؟',
                text: 'سيُعكَس القيد ويعود المبلغ من الخزينة.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'نعم، ألغِ السند',
                cancelButtonText: 'تراجع',
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6'
            }).then(function(result) {
                if (!result.isConfirmed) {
                    return;
                }

                $.ajax({
                    url: window.routes.incomes.cancel.replace(':id', id),
                    method: 'POST',
                    data: {
                        _token: window.csrfToken
                    },
                    beforeSend: function() {
                        window.showLoading();
                    },
                    success: function() {
                        Swal.fire('تمّ', 'أُلغي السند وعُكس قيده.', 'success');
                        window.loadData();
                    },
                    error: function(xhr) {
                        var messages = {
                            404: 'السند غير موجود.',
                            403: 'لا تملك صلاحية هذا الإجراء.',
                            419: 'انتهت صلاحية الجلسة — حدّث الصفحة.'
                        };

                        Swal.fire('خطأ', (xhr.responseJSON && xhr.responseJSON.message) ||
                            messages[xhr.status] || 'حدث خطأ أثناء إلغاء السند.', 'error');
                    },
                    complete: function() {
                        window.hideLoading();
                    }
                });
            });
        }

        function toggleSearchText(button) {
            var label = button.querySelector('.button-text');
            label.textContent = label.textContent === 'بحث متقدم' ? 'بحث بسيط' : 'بحث متقدم';
        }

        function printIncomesReport() {
            var values = $('#totals-container .stk-metric__value');
            var filterInfo = {};
            var fromDate = $('#from_date').val();
            var toDate = $('#to_date').val();

            if (fromDate && toDate) {
                filterInfo['الفترة'] = 'من ' + fromDate + ' إلى ' + toDate;
            }

            [
                ['التصنيف', '#category'],
                ['الحالة', '#status'],
                ['الفرع', '#branch'],
                ['أضيف بواسطة', '#added_by']
            ].forEach(function(row) {
                if ($(row[1]).val()) {
                    filterInfo[row[0]] = $(row[1] + ' option:selected').text().trim();
                }
            });

            $('#incomesTable th:last-child, #incomesTable td:last-child').hide();

            printTable({
                tableSelector: '#incomesTable tbody tr',
                title: 'تقرير سندات القبض',
                infoCards: [{
                    label: $('#totals-container .stk-metric__label').eq(0).text().trim(),
                    value: values.eq(0).text().trim()
                }, {
                    label: 'آخر 30 يوماً',
                    value: values.eq(1).text().trim(),
                    class: 'text-success'
                }, {
                    label: 'آخر 365 يوماً',
                    value: values.eq(2).text().trim(),
                    class: 'text-primary'
                }],
                filterInfo: filterInfo,
                tableHeaders: ['الحساب', 'التاريخ', 'الوصف', 'المبلغ', 'الفرع', 'الحالة'],
                columnWidths: ['20%', '15%', '25%', '15%', '15%', '10%'],
                excludeLastColumn: true,
                windowWidth: 1200,
                windowHeight: 800
            });

            setTimeout(function() {
                $('#incomesTable th:last-child, #incomesTable td:last-child').show();
            }, 1000);
        }
    </script>
@endsection
