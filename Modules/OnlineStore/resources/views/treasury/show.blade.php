@extends('onlinestore::master')

@section('title', 'تفاصيل الخزينة')

@section('content')
    <div class="content-body">

        @section('page-actions')
            @if (hasPermission('account.treasury_transfers.view') && hasPermission('account.treasury_transfers.create'))
                <a href="{{ route('store_treasury.transfer_create') }}" class="btn btn-outline-success">
                    <i class="feather icon-repeat"></i> تحويل
                </a>
            @endif
            <a href="{{ route('store_treasury.index') }}" class="btn btn-outline-secondary">
                <i class="feather icon-arrow-right"></i> العودة للقائمة
            </a>
        @endsection

        @include('onlinestore::components.page-head', [
            'title' => 'تفاصيل الخزينة',
            'icon' => $isBank ? 'credit-card' : 'archive',
            'sub' => $treasury->name,
            'crumbs' => [
                'الرئيسية' => url('/'),
                'الخزائن والحسابات البنكية' => route('store_treasury.index'),
                $treasury->name => null,
            ],
        ])

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')

        <div class="stk-metrics">
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-dollar-sign"></i></span>
                <span class="stk-metric__label">الرصيد الحالي</span>
                <span class="stk-metric__value">{{ \App\Support\CurrencySettings::number($treasury->balance) }} {{ $currencyCode }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-{{ $isBank ? 'credit-card' : 'archive' }}"></i></span>
                <span class="stk-metric__label">النوع</span>
                <span class="stk-metric__value">{{ $isBank ? 'حساب بنكي' : 'خزينة' }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-activity"></i></span>
                <span class="stk-metric__label">الحالة</span>
                <span class="stk-metric__value">
                    <span class="stk-state {{ $treasury->is_active == 1 ? 'stk-state--on' : 'stk-state--off' }}">
                        {{ $treasury->is_active == 1 ? 'نشط' : 'غير نشط' }}
                    </span>
                </span>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <ul class="nav nav-tabs stk-tabs" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" id="home-tab" data-bs-toggle="tab" data-toggle="tab" href="#home" role="tab">التفاصيل</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="transactions-tab" data-bs-toggle="tab" data-toggle="tab" href="#transactions" role="tab">معاملات النظام</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="transfers-tab" data-bs-toggle="tab" data-toggle="tab" href="#transfers" role="tab">التحويلات</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="activate-tab" data-bs-toggle="tab" data-toggle="tab" href="#activate" role="tab">سجل النشاطات</a>
                    </li>
                </ul>

                <div class="tab-content pt-3">
                    <!-- تبويب التفاصيل -->
                    <div class="tab-pane fade show active" id="home" role="tabpanel">
                        <dl class="stk-def">
                            <div class="stk-def__row">
                                <dt>الاسم</dt>
                                <dd>{{ $treasury->name }}</dd>
                            </div>
                            <div class="stk-def__row">
                                <dt>الكود في شجرة الحسابات</dt>
                                <dd class="stk-num">{{ $treasury->code }}</dd>
                            </div>
                            <div class="stk-def__row">
                                <dt>الفرع</dt>
                                <dd>{{ $branchName ?? 'بلا فرع' }}</dd>
                            </div>
                            @if ($isBank)
                                <div class="stk-def__row">
                                    <dt>البنك</dt>
                                    <dd>{{ \App\Constants\Banks::forCountry()[$treasuryRow->bank_name ?? ''] ?? ($treasuryRow->bank_name ?? 'غير محدد') }}</dd>
                                </div>
                                <div class="stk-def__row">
                                    <dt>رقم الآيبان</dt>
                                    <dd dir="ltr" class="stk-num">{{ \App\Constants\Banks::formatIban($treasuryRow->account_number ?? '') ?: 'غير محدد' }}</dd>
                                </div>
                            @endif
                            <div class="stk-def__row">
                                <dt>العملة</dt>
                                <dd>{{ $currencyCode }}</dd>
                            </div>
                            @if ($isDefaultTreasury)
                                <div class="stk-def__row">
                                    <dt>الاستعمال</dt>
                                    <dd><span class="badge badge-light-primary">الخزينة الافتراضية للمتجر (الدفع عند إتمام الطلب)</span></dd>
                                </div>
                            @endif
                            <div class="stk-def__row">
                                <dt>النوع</dt>
                                <dd>
                                    @if (! $isBank)
                                        <span class="badge badge-light-success">خزينة</span>
                                    @else
                                        <span class="badge badge-light-primary">حساب بنكي</span>
                                    @endif
                                </dd>
                            </div>
                            <div class="stk-def__row">
                                <dt>الحالة</dt>
                                <dd>
                                    <span class="stk-state {{ $treasury->is_active == 1 ? 'stk-state--on' : 'stk-state--off' }}">
                                        {{ $treasury->is_active == 1 ? 'نشط' : 'غير نشط' }}
                                    </span>
                                </dd>
                            </div>
                            <div class="stk-def__row">
                                <dt>الرصيد</dt>
                                <dd class="stk-num">{{ \App\Support\CurrencySettings::number($treasury->balance) }} {{ $currencyCode }}</dd>
                            </div>
                            <div class="stk-def__row">
                                <dt>الوصف</dt>
                                <dd>{{ $treasuryRow->description ?? 'غير محدد' }}</dd>
                            </div>
                        </dl>
                    </div>

                    <!-- تبويب المعاملات -->
                    <div class="tab-pane fade" id="transactions" role="tabpanel">
                        <div class="card stk-filters mt-3">
                            <div class="card-body">
                                <div class="stk-filters__head">
                                    <h6 class="stk-filters__title"><i class="feather icon-filter"></i> بحث وتصفية</h6>
                                </div>

                                <form id="operationsSearchForm">
                                    <div class="row">
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label for="from_date">التاريخ من</label>
                                                <input type="date" class="form-control" name="from_date" id="from_date">
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label for="to_date">التاريخ إلى</label>
                                                <input type="date" class="form-control" name="to_date" id="to_date">
                                            </div>
                                        </div>
                                        <div class="col-md-3">
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

                                    <div class="stk-filters__foot">
                                        <button type="button" class="btn btn-outline-secondary" onclick="resetFilters()">
                                            <i class="feather icon-rotate-ccw"></i> إلغاء
                                        </button>
                                        <button type="button" class="btn btn-primary" onclick="loadOperations()">
                                            <i class="feather icon-search"></i> بحث
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <div class="card stk-table-card">
                            <!-- Loading indicator -->
                            <div id="operationsLoading" class="text-center p-3" style="display: none;">
                                <i class="fa fa-spinner fa-spin"></i> جاري التحميل...
                            </div>

                            <!-- Operations table -->
                            <div id="operationsTableContainer">
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0" id="operationsTable">
                                        <thead>
                                            <tr>
                                                <th width="5%"><i class="feather icon-list"></i></th>
                                                <th>العملية</th>
                                                <th>الوصف</th>
                                                <th>الإيداع</th>
                                                <th>السحب</th>
                                                <th>الرصيد بعد العملية</th>
                                                <th>التاريخ</th>
                                                <th width="10%">الإجراءات</th>
                                            </tr>
                                        </thead>
                                        <tbody id="operationsTableBody">
                                            <!-- سيتم تحميل البيانات هنا عبر AJAX -->
                                        </tbody>
                                    </table>
                                </div>

                                <!-- Pagination -->
                                <div id="operationsPagination" class="d-flex justify-content-between align-items-center mt-3 w-100 px-3 pb-3">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- تبويب التحويلات -->
                    <div class="tab-pane fade" id="transfers" role="tabpanel">
                        <div class="card stk-table-card mt-3">
                            <div class="stk-table-head">
                                <h6 class="stk-table-head__title"><i class="feather icon-repeat"></i> التحويلات</h6>
                            </div>

                            <div id="transfersTableContainer">
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0" id="transfersTable">
                                        <thead>
                                            <tr>
                                                <th>رقم القيد</th>
                                                <th>التاريخ</th>
                                                <th>من خزينة</th>
                                                <th>إلى خزينة</th>
                                                <th>المبلغ</th>
                                                <th style="width: 10%">الإجراءات</th>
                                            </tr>
                                        </thead>
                                        <tbody id="transfersTableBody">
                                            <!-- سيتم تحميل البيانات هنا عبر AJAX -->
                                        </tbody>
                                    </table>
                                </div>

                                <div id="transfersPagination" class="d-flex justify-content-between align-items-center mt-3 w-100 px-3 pb-3">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- تبويب سجل النشاطات -->
                    <div class="tab-pane fade" id="activate" role="tabpanel">
                        <div class="stk-empty">
                            <span class="stk-empty__icon"><i class="feather icon-activity"></i></span>
                            <h6 class="stk-empty__title">سجل النشاطات</h6>
                            <p class="stk-empty__hint">لا سجلّ نشاطٍ لعرضه حالياً.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
<script>
    let currentPage = 1;
    const treasuryId = {{ $treasury->id }};

    $(document).ready(function() {
        loadOperations();

        $('#transactions-tab').on('shown.bs.tab', function() {
            loadOperations();
        });

        $('#transfers-tab').on('shown.bs.tab', function() {
            loadTransfers();
        });
    });

    function loadOperations(page = 1) {
        currentPage = page;
        showLoading();

        const formData = new FormData(document.getElementById('operationsSearchForm'));
        formData.append('page', page);

        $.ajax({
            url: `{{ route('store_treasury.show', $treasury->id) }}`,
            method: 'GET',
            data: Object.fromEntries(formData),
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            success: function(response) {
                hideLoading();
                renderOperationsTable(response.operations);
                renderPagination(response.pagination, 'operationsPagination');
            },
            error: function(xhr, status, error) {
                hideLoading();
                console.error('خطأ في تحميل البيانات:', error);
            }
        });
    }

    function loadTransfers() {
        $.ajax({
            url: `{{ route('store_treasury.transfers_data', $treasury->id) }}`,
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            success: function(response) {
                renderTransfersTable(response.transfers);
            },
            error: function(xhr, status, error) {
                console.error('خطأ في تحميل التحويلات:', error);
            }
        });
    }

    function showLoading() {
        $('#operationsLoading').show();
        $('#operationsTableContainer').hide();
    }

    function hideLoading() {
        $('#operationsLoading').hide();
        $('#operationsTableContainer').show();
    }

    function renderOperationsTable(operations) {
        const tbody = $('#operationsTableBody');
        tbody.empty();

        if (!operations || operations.length === 0) {
            tbody.append(`
                <tr>
                    <td colspan="8" class="text-center text-muted py-4">
                        <i class="fa fa-info-circle"></i> لا توجد عمليات للعرض
                    </td>
                </tr>
            `);
            return;
        }

        operations.forEach(function(operation) {
            const depositAmount = operation.deposit > 0 ? formatNumber(operation.deposit) : '-';
            const withdrawAmount = operation.withdraw > 0 ? formatNumber(operation.withdraw) : '-';
            const balanceAfter = formatNumber(operation.balance_after);

            tbody.append(`
                <tr>
                    <td><i class="fa fa-circle text-${operation.deposit > 0 ? 'success' : 'danger'}"></i></td>
                    <td><strong>${operation.operation}</strong></td>
                    <td><small class="text-muted">${operation.description || '-'}</small></td>
                    <td><span class="text-success font-weight-bold">${depositAmount}</span></td>
                    <td><span class="text-danger font-weight-bold">${withdrawAmount}</span></td>
                    <td><span class="font-weight-bold text-primary">${balanceAfter}</span></td>
                    <td><small>${formatDate(operation.date)}</small></td>
                    <td>
                        ${operation.journal_entry_id ? `<a href="/journal/${operation.journal_entry_id}" class="btn btn-sm btn-outline-primary"><i class="fa fa-eye"></i></a>` : '-'}
                    </td>
                </tr>
            `);
        });
    }

    function renderTransfersTable(transfers) {
        const tbody = $('#transfersTableBody');
        tbody.empty();

        if (!transfers || transfers.length === 0) {
            tbody.append(`
                <tr>
                    <td colspan="6" class="text-center text-muted py-4">
                        <i class="fa fa-info-circle"></i> لا توجد تحويلات للعرض
                    </td>
                </tr>
            `);
            return;
        }

        transfers.forEach(function(transfer) {
            tbody.append(`
                <tr>
                    <td>${transfer.reference_number}</td>
                    <td>${formatDate(transfer.date)}</td>
                    <td>${transfer.from_account ? transfer.from_account.name : '-'}</td>
                    <td>${transfer.to_account ? transfer.to_account.name : '-'}</td>
                    <td><strong>${formatNumber(transfer.amount)}</strong></td>
                    <td>
                        <a href="/journal/${transfer.id}" class="btn btn-sm btn-outline-primary"><i class="fa fa-eye"></i></a>
                    </td>
                </tr>
            `);
        });
    }

    function renderPagination(pagination, containerId) {
        const container = $('#' + containerId);
        container.empty();

        if (!pagination || pagination.total <= pagination.per_page) return;

        const info = `<div class="pagination-info text-muted">عرض ${pagination.from || 0} إلى ${pagination.to || 0} من ${pagination.total} نتيجة</div>`;

        let paginationHtml = '<nav><ul class="pagination pagination-sm mb-0">';

        // Previous
        if (pagination.current_page > 1) {
            paginationHtml += `<li class="page-item"><a class="page-link" href="#" onclick="loadOperations(${pagination.current_page - 1})"><i class="fa fa-angle-right"></i></a></li>`;
        }

        // Current
        paginationHtml += `<li class="page-item active"><span class="page-link">${pagination.current_page}</span></li>`;

        // Next
        if (pagination.has_more_pages) {
            paginationHtml += `<li class="page-item"><a class="page-link" href="#" onclick="loadOperations(${pagination.current_page + 1})"><i class="fa fa-angle-left"></i></a></li>`;
        }

        paginationHtml += '</ul></nav>';
        container.html(info + paginationHtml);
    }

    function formatNumber(num) {
        return parseFloat(num || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    }

    function formatDate(dateStr) {
        if (!dateStr) return '-';
        const date = new Date(dateStr);
        return date.toLocaleDateString('ar-SA');
    }

    function resetFilters() {
        document.getElementById('operationsSearchForm').reset();
        loadOperations();
    }
</script>
@endsection
