@extends('account::master')

@section('title', 'تفاصيل الخزينة')

@section('content')
    @php
        $isBank = $treasury->type_accont != 0;
        $isActive = $treasury->is_active == 1;
    @endphp

    <div class="content-body">

        @section('page-actions')
            <button type="button" class="btn btn-outline-secondary" onclick="printTransactions()">
                <i class="feather icon-printer"></i> طباعة الحركات
            </button>
            <a href="{{ route('treasury.transferCreate', $treasury->id) }}" class="btn btn-outline-primary">
                <i class="feather icon-repeat"></i> تحويل
            </a>
            {{-- البوّابةُ صلاحيةٌ كما في المتحكّم، لا ارتباطُ المستخدم بسجلّ موظف:
                 حسابات المديرين كلُّها مربوطةٌ بموظفين، فكان الزرُّ غائباً عنهم --}}
            @if (!$isBank)
                @permission('account.treasury.edit')
                    <a href="{{ route('treasury.edit', $treasury->id) }}" class="btn btn-primary">
                        <i class="feather icon-edit-2"></i> تعديل
                    </a>
                @endpermission
            @endif
        @endsection

        @include('account::components.page-head', [
            'title' => $treasury->name,
            'icon' => $isBank ? 'credit-card' : 'briefcase',
            'sub' => ($isBank ? 'حساب بنكي' : 'خزينة') . ' — ' . ($isActive ? 'نشطة' : 'غير نشطة'),
            'crumbs' => [
                'الرئيسية' => url('/'),
                'الخزائن' => route('treasury.index'),
                $treasury->name => null,
            ],
        ])

        <div class="stk-metrics">
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-dollar-sign"></i></span>
                <span class="stk-metric__label">الرصيد الحالي</span>
                <span class="stk-metric__value">@money($treasury->balance ?? 0)</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-{{ $isBank ? 'credit-card' : 'archive' }}"></i></span>
                <span class="stk-metric__label">النوع</span>
                <span class="stk-metric__value">{{ $isBank ? 'حساب بنكي' : 'خزينة' }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-check-circle"></i></span>
                <span class="stk-metric__label">الحالة</span>
                <span class="stk-metric__value">
                    <span class="stk-state {{ $isActive ? 'stk-state--on' : 'stk-state--off' }}">
                        {{ $isActive ? 'نشطة' : 'غير نشطة' }}
                    </span>
                </span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-hash"></i></span>
                <span class="stk-metric__label">رقم الحساب</span>
                <span class="stk-metric__value stk-num">{{ $treasury->code ?? $treasury->id }}</span>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <ul class="nav nav-tabs stk-tabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="details-tab" data-bs-toggle="tab" data-bs-target="#details"
                            type="button" role="tab" aria-controls="details" aria-selected="true">
                            <i class="feather icon-file-text"></i> التفاصيل
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="transactions-tab" data-bs-toggle="tab"
                            data-bs-target="#transactions" type="button" role="tab" aria-controls="transactions"
                            aria-selected="false">
                            <i class="feather icon-activity"></i> حركات الخزينة
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="transfers-tab" data-bs-toggle="tab" data-bs-target="#transfers"
                            type="button" role="tab" aria-controls="transfers" aria-selected="false">
                            <i class="feather icon-repeat"></i> التحويلات
                        </button>
                    </li>
                </ul>

                <div class="tab-content pt-3">

                    {{--
                        لسان التفاصيل كان جدولاً بخلايا فيها «الاسم : القيمة»
                        نصّاً، وصفٌّ يكرّر اسم الخزينة مرّتين حين تكون حساباً
                        بنكياً. والأهمّ: الحالة كانت معكوسة — `is_active == 0`
                        تُعرض «نشط» بأخضر — على خلاف الجدول في القائمة الذي
                        يقرأ `== 1`. صُحِّحت هنا لتوافقه.
                    --}}
                    <div class="tab-pane active" id="details" role="tabpanel" aria-labelledby="details-tab">
                        <div class="row">
                            <div class="col-md-6">
                                <dl class="stk-def">
                                    <div class="stk-def__row">
                                        <dt>الاسم</dt>
                                        <dd>{{ $treasury->name }}</dd>
                                    </div>
                                    <div class="stk-def__row">
                                        <dt>النوع</dt>
                                        <dd>{{ $isBank ? 'حساب بنكي' : 'خزينة' }}</dd>
                                    </div>
                                    <div class="stk-def__row">
                                        <dt>الحالة</dt>
                                        <dd>
                                            <span class="stk-state {{ $isActive ? 'stk-state--on' : 'stk-state--off' }}">
                                                {{ $isActive ? 'نشطة' : 'غير نشطة' }}
                                            </span>
                                        </dd>
                                    </div>
                                </dl>
                            </div>

                            <div class="col-md-6">
                                <dl class="stk-def">
                                    <div class="stk-def__row">
                                        <dt>الرصيد</dt>
                                        <dd><strong>@money($treasury->balance ?? 0)</strong></dd>
                                    </div>
                                    <div class="stk-def__row">
                                        <dt>الكود</dt>
                                        <dd class="stk-num">{{ $treasury->code ?? '—' }}</dd>
                                    </div>
                                    <div class="stk-def__row">
                                        <dt>العملة</dt>
                                        <dd class="stk-num">{{ $treasury->currency ?: '—' }}</dd>
                                    </div>
                                    <div class="stk-def__row">
                                        <dt>الوصف</dt>
                                        <dd>{{ $treasury->description ?: '—' }}</dd>
                                    </div>
                                </dl>

                                {{--
                                    بياناتُ البنك لم تكن تُعرض إطلاقاً: تُدخَل في
                                    الإنشاء ثم لا يراها أحد، فيتعذّر التحقّق من
                                    الآيبان قبل الحوالة إلا بفتح شاشة التعديل.
                                --}}
                                @if ($isBank)
                                    <dl class="stk-def mt-1">
                                        <div class="stk-def__row">
                                            <dt>البنك</dt>
                                            <dd>{{ \App\Constants\Banks::name($treasury->bank_name) ?? ($treasury->bank_name ?: '—') }}</dd>
                                        </div>
                                        <div class="stk-def__row">
                                            <dt>رقم الآيبان</dt>
                                            <dd class="stk-num" dir="ltr" style="text-align:start">
                                                {{ \App\Constants\Banks::formatIban($treasury->account_number) ?: '—' }}
                                            </dd>
                                        </div>
                                        <div class="stk-def__row">
                                            <dt>الحساب المرتبط</dt>
                                            <dd>
                                                @if ($treasury->account)
                                                    @can('account.chart.view')
                                                        <a href="{{ route('accounts_chart.view', $treasury->account->id) }}">
                                                            {{ $treasury->account->code }} — {{ $treasury->account->name }}
                                                        </a>
                                                    @else
                                                        {{ $treasury->account->code }} — {{ $treasury->account->name }}
                                                    @endcan
                                                @else
                                                    <span class="text-muted">غير مرتبط بحسابٍ في الشجرة</span>
                                                @endif
                                            </dd>
                                        </div>
                                    </dl>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane" id="transactions" role="tabpanel" aria-labelledby="transactions-tab">
                        <div class="stk-filters mb-3">
                            <div class="stk-filters__head">
                                <h6 class="stk-filters__title"><i class="feather icon-filter"></i> بحث وتصفية</h6>

                                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse"
                                    data-bs-target="#advancedSearchForm" onclick="toggleSearchText(this)">
                                    <i class="feather icon-sliders"></i> <span class="button-text">بحث متقدم</span>
                                </button>
                            </div>

                            <form id="operationsSearchForm" class="form">
                                <div class="row">
                                    {{-- الفترة المحاسبية: اختيارها يملأ حقلَي المدى --}}
                                    @include('partials.accounting-period-filter', [
                                        'fromName' => 'from_date',
                                        'toName' => 'to_date',
                                        'colClass' => 'col-md-3 col-sm-6',
                                    ])

                                    <div class="col-md-3 col-sm-6">
                                        <div class="form-group">
                                            <label for="from_date">من تاريخ</label>
                                            <input type="date" class="form-control" name="from_date" id="from_date">
                                        </div>
                                    </div>

                                    <div class="col-md-3 col-sm-6">
                                        <div class="form-group">
                                            <label for="to_date">إلى تاريخ</label>
                                            <input type="date" class="form-control" name="to_date" id="to_date">
                                        </div>
                                    </div>

                                    <div class="col-md-3 col-sm-6">
                                        <div class="form-group">
                                            <label for="operation_type">نوع العملية</label>
                                            <select name="operation_type" class="form-control" id="operation_type">
                                                <option value="">جميع العمليات</option>
                                                <option value="payment">عمليات الدفع</option>
                                                <option value="receipt">سندات القبض</option>
                                                <option value="transfer">التحويلات</option>
                                                <option value="expense">سندات الصرف</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="collapse" id="advancedSearchForm">
                                    <div class="row">
                                        <div class="col-md-3 col-sm-6">
                                            <div class="form-group">
                                                <label for="amount_from">أقل مبلغ</label>
                                                <input type="number" class="form-control" name="amount_from"
                                                    id="amount_from" step="0.01" placeholder="0.00">
                                            </div>
                                        </div>

                                        <div class="col-md-3 col-sm-6">
                                            <div class="form-group">
                                                <label for="amount_to">أكبر مبلغ</label>
                                                <input type="number" class="form-control" name="amount_to"
                                                    id="amount_to" step="0.01" placeholder="0.00">
                                            </div>
                                        </div>

                                        <div class="col-md-3 col-sm-6">
                                            <div class="form-group">
                                                <label for="branch_filter">الفرع</label>
                                                <select name="branch_id" class="form-control" id="branch_filter">
                                                    <option value="">جميع الفروع</option>
                                                    @foreach ($branches as $branch)
                                                        <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="stk-filters__foot">
                                    <button type="button" class="btn btn-outline-secondary" onclick="resetFilters()">
                                        <i class="feather icon-rotate-ccw"></i> إعادة تعيين
                                    </button>
                                    <button type="button" class="btn btn-primary" onclick="loadOperations()">
                                        <i class="feather icon-search"></i> بحث
                                    </button>
                                </div>
                            </form>
                        </div>

                        <div class="stk-table-head">
                            <h6 class="stk-table-head__title">
                                <i class="feather icon-list"></i> حركات الخزينة
                            </h6>

                            @include('stock::components.per-page', [
                                'id' => 'per_page',
                                'name' => 'per_page',
                                'sizes' => [20, 50, 100],
                                'current' => 50,
                                'onchange' => 'loadOperations()',
                            ])
                        </div>

                        <div id="operationsLoading" class="text-center p-4" style="display:none">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">جارٍ التحميل…</span>
                            </div>
                        </div>

                        <div id="operationsTableContainer">
                            <div class="table-responsive">
                                <table class="table table-hover table-striped mb-0" id="operationsTable">
                                    <thead>
                                        <tr>
                                            <th style="width:40px"></th>
                                            <th>العملية</th>
                                            <th>الوصف</th>
                                            <th class="text-center">إيداع</th>
                                            <th class="text-center">سحب</th>
                                            <th class="text-center">الرصيد بعدها</th>
                                            <th>التاريخ</th>
                                            <th class="text-center" style="width:70px">القيد</th>
                                        </tr>
                                    </thead>
                                    <tbody id="operationsTableBody"></tbody>
                                </table>
                            </div>

                            <div id="operationsPagination"></div>
                        </div>
                    </div>

                    {{--
                        كان فوق هذا الجدول نموذجُ بحثٍ يرسل إلى
                        `route('invoices.index')` — شاشة الفواتير — بثلاثة حقول
                        تاريخ، حقلان منها باسم `to_date` نفسه. ولا يقبل مسار
                        التحويلات فلترةً بتاريخ أصلاً. حُذف النموذج بدل تركه
                        يَعِد بتصفيةٍ لا تحدث.
                    --}}
                    <div class="tab-pane" id="transfers" role="tabpanel" aria-labelledby="transfers-tab">
                        <div class="stk-table-head">
                            <h6 class="stk-table-head__title">
                                <i class="feather icon-repeat"></i> التحويلات من وإلى هذه الخزينة
                            </h6>
                        </div>

                        <div id="transfersLoading" class="text-center p-4" style="display:none">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">جارٍ التحميل…</span>
                            </div>
                        </div>

                        <div id="transfersTableContainer">
                            <div class="table-responsive">
                                <table class="table table-hover table-striped mb-0">
                                    <thead>
                                        <tr>
                                            <th>رقم القيد</th>
                                            <th>التاريخ</th>
                                            <th>من</th>
                                            <th>إلى</th>
                                            <th class="text-center">المبلغ</th>
                                        </tr>
                                    </thead>
                                    <tbody id="transfersTableBody"></tbody>
                                </table>
                            </div>

                            <div id="transfersPagination"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <script src="{{ asset('js/print-table.js') }}"></script>
    <script>
        var treasuryId = {{ $treasury->id }};
        var journalShowBase = '{{ route('journal.show', '') }}';

        $(document).ready(function() {
            loadOperations();

            $('#transactions-tab').on('shown.bs.tab', function() {
                loadOperations();
            });

            $('#transfers-tab').on('shown.bs.tab', function() {
                loadTransfers();
            });
        });

        /** مبلغ منسَّق بعملة النظام المضبوطة في الإعدادات */
        function money(value) {
            return window.AppCurrency.format(value || 0);
        }

        function formatDate(value) {
            if (!value) return '—';
            var date = new Date(value);
            return isNaN(date) ? value : date.toLocaleDateString('en-GB');
        }

        function escapeHtml(value) {
            return $('<span>').text(value == null ? '' : value).html();
        }

        /*
            شريط ترقيم واحد يخدم اللسانين.

            كان لكل لسان نسخته: تسعون سطراً تبني «الأول» و«السابق» و«التالي»
            و«الأخير» بأربع كتل `if/else` متطابقة، مرّتين — مئةٌ وثمانون سطراً
            تفعل شيئاً واحداً. والفارق الوحيد بينهما اسمُ دالة التحميل.
        */
        function renderPagination(pagination, containerId, loader, unit) {
            var container = $('#' + containerId);

            if (!pagination || !pagination.total) {
                container.html('');
                return;
            }

            var from = pagination.from || 0;
            var to = pagination.to || pagination.total;
            var current = pagination.current_page || 1;
            var last = pagination.last_page || 1;

            var html = '<div class="stk-table-foot">' +
                '<span class="stk-table-foot__count">عرض <strong class="stk-num">' + from +
                '</strong> إلى <strong class="stk-num">' + to +
                '</strong> من <strong class="stk-num">' + pagination.total + '</strong> ' + unit + '</span>';

            if (last > 1) {
                html += '<nav aria-label="صفحات النتائج"><ul class="pagination pagination-sm mb-0">';

                for (var page = 1; page <= last; page++) {
                    // نافذة من خمس صفحات حول الحالية، مع الأولى والأخيرة دائماً
                    if (page !== 1 && page !== last && Math.abs(page - current) > 2) {
                        if (Math.abs(page - current) === 3) {
                            html += '<li class="page-item disabled"><span class="page-link">…</span></li>';
                        }
                        continue;
                    }

                    html += page === current ?
                        '<li class="page-item active"><span class="page-link">' + page + '</span></li>' :
                        '<li class="page-item"><a class="page-link" href="#" onclick="' + loader + '(' + page +
                        '); return false;">' + page + '</a></li>';
                }

                html += '</ul></nav>';
            }

            container.html(html + '</div>');
        }

        /* ═══════════════ حركات الخزينة ═══════════════ */

        function loadOperations(page) {
            page = page || 1;

            $('#operationsLoading').show();
            $('#operationsTableContainer').hide();

            // مُنتقي عدد السجلّات يعيش في ترويسة الجدول لا في النموذج، فيُضاف يدوياً
            var formData = new FormData(document.getElementById('operationsSearchForm'));
            formData.append('page', page);
            formData.append('per_page', $('#per_page').val() || 50);

            $.ajax({
                url: '{{ route('treasury.show', $treasury->id) }}',
                method: 'GET',
                data: Object.fromEntries(formData),
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                success: function(response) {
                    renderOperationsTable(response.operations);
                    renderPagination(response.pagination, 'operationsPagination', 'loadOperations', 'حركة');
                },
                error: function(xhr, status, error) {
                    console.error('خطأ في تحميل حركات الخزينة:', error);
                    $('#operationsTableBody').html(
                        '<tr><td colspan="8" class="text-center text-danger py-4">' +
                        '<i class="feather icon-alert-triangle"></i> تعذّر تحميل الحركات — أعِد المحاولة.' +
                        '</td></tr>'
                    );
                    $('#operationsPagination').html('');
                },
                complete: function() {
                    $('#operationsLoading').hide();
                    $('#operationsTableContainer').show();
                }
            });
        }

        function renderOperationsTable(operations) {
            var tbody = $('#operationsTableBody');

            if (!operations || operations.length === 0) {
                tbody.html(
                    '<tr><td colspan="8" class="p-0">' +
                    '<div class="stk-empty">' +
                    '<span class="stk-empty__icon"><i class="feather icon-activity"></i></span>' +
                    '<h6 class="stk-empty__title">لا حركات في هذه الفترة</h6>' +
                    '<p class="stk-empty__hint">وسّع المدى أو غيّر نوع العملية.</p>' +
                    '</div></td></tr>'
                );
                return;
            }

            tbody.html(operations.map(function(operation) {
                var action = operation.journal_entry_id ?
                    '<a href="' + journalShowBase + '/' + operation.journal_entry_id +
                    '" class="btn btn-sm btn-outline-secondary" title="عرض القيد المحاسبي">' +
                    '<i class="feather icon-eye"></i></a>' :
                    '<span class="text-muted">—</span>';

                return '<tr>' +
                    '<td><span class="stk-move ' + moveClass(operation.type) + '">' +
                    '<i class="feather icon-' + operationIcon(operation.type) + '"></i></span></td>' +
                    '<td><strong>' + escapeHtml(operation.operation) + '</strong></td>' +
                    '<td>' + escapeHtml(operation.description || '—') + '</td>' +
                    '<td class="text-center">' + (operation.deposit > 0 ? money(operation.deposit) : '—') + '</td>' +
                    '<td class="text-center">' + (operation.withdraw > 0 ? money(operation.withdraw) : '—') + '</td>' +
                    '<td class="text-center"><strong>' + money(operation.balance_after) + '</strong></td>' +
                    '<td class="stk-num">' + formatDate(operation.date) + '</td>' +
                    '<td class="text-center">' + action + '</td>' +
                    '</tr>';
            }).join(''));
        }

        function operationIcon(type) {
            return {
                payment: 'credit-card',
                receipt: 'file-plus',
                transfer: 'repeat',
                expense: 'file-minus',
                revenue: 'trending-up'
            }[type] || 'circle';
        }

        function moveClass(type) {
            if (type === 'receipt' || type === 'revenue') return 'stk-move--success';
            if (type === 'expense' || type === 'payment') return 'stk-move--danger';
            return 'stk-move--muted';
        }

        function resetFilters() {
            document.getElementById('operationsSearchForm').reset();
            loadOperations();
        }

        function toggleSearchText(button) {
            var label = button.querySelector('.button-text');
            label.textContent = label.textContent === 'بحث متقدم' ? 'بحث بسيط' : 'بحث متقدم';
        }

        /* ═══════════════ التحويلات ═══════════════ */

        function loadTransfers(page) {
            page = page || 1;

            $('#transfersLoading').show();
            $('#transfersTableContainer').hide();

            $.ajax({
                url: '{{ route('treasury.transfers', $treasury->id) }}?page=' + page,
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                success: function(response) {
                    renderTransfersTable(response.transfers);
                    renderPagination(response.pagination, 'transfersPagination', 'loadTransfers', 'تحويلاً');
                },
                error: function(xhr, status, error) {
                    console.error('خطأ في تحميل التحويلات:', error);
                    $('#transfersTableBody').html(
                        '<tr><td colspan="5" class="text-center text-danger py-4">' +
                        '<i class="feather icon-alert-triangle"></i> تعذّر تحميل التحويلات — أعِد المحاولة.' +
                        '</td></tr>'
                    );
                    $('#transfersPagination').html('');
                },
                complete: function() {
                    $('#transfersLoading').hide();
                    $('#transfersTableContainer').show();
                }
            });
        }

        function renderTransfersTable(transfers) {
            var tbody = $('#transfersTableBody');

            if (!transfers || transfers.length === 0) {
                tbody.html(
                    '<tr><td colspan="5" class="p-0">' +
                    '<div class="stk-empty">' +
                    '<span class="stk-empty__icon"><i class="feather icon-repeat"></i></span>' +
                    '<h6 class="stk-empty__title">لا تحويلات</h6>' +
                    '<p class="stk-empty__hint">لم يُسجَّل تحويلٌ من هذه الخزينة أو إليها.</p>' +
                    '</div></td></tr>'
                );
                return;
            }

            tbody.html(transfers.map(function(transfer) {
                return '<tr>' +
                    '<td class="stk-num">' + escapeHtml(transfer.reference_number || '—') + '</td>' +
                    '<td class="stk-num">' + formatDate(transfer.date) + '</td>' +
                    '<td>' + escapeHtml(transfer.from_account ? transfer.from_account.name : '—') + '</td>' +
                    '<td>' + escapeHtml(transfer.to_account ? transfer.to_account.name : '—') + '</td>' +
                    '<td class="text-center"><strong>' + money(transfer.amount) + '</strong></td>' +
                    '</tr>';
            }).join(''));
        }

        /* ═══════════════ الطباعة ═══════════════ */

        function printTransactions() {
            printTable({
                tableSelector: '#operationsTableBody tr',
                title: 'حركات الخزينة — {{ $treasury->name }}',
                infoCards: [{
                    label: 'الخزينة',
                    value: @json($treasury->name)
                }, {
                    label: 'النوع',
                    value: @json($isBank ? 'حساب بنكي' : 'خزينة')
                }, {
                    label: 'الرصيد الحالي',
                    value: '@money($treasury->balance ?? 0)',
                    class: 'text-success'
                }],
                filterInfo: {
                    'الفترة': ($('#from_date').val() || 'الكل') + ' إلى ' + ($('#to_date').val() || 'الكل'),
                    'نوع العملية': $('#operation_type option:selected').text()
                },
                tableHeaders: ['#', 'العملية', 'الوصف', 'إيداع', 'سحب', 'الرصيد بعدها', 'التاريخ'],
                columnWidths: ['5%', '15%', '25%', '13%', '13%', '15%', '14%'],
                excludeLastColumn: true,
                descriptionColumn: 3
            });
        }
    </script>
@endsection
