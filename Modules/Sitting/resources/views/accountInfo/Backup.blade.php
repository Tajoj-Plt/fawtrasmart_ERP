@extends('sitting::master')

@section('title')
    {{ __('settings.account_info.backup_title') }}
@stop

@section('content')
<div class="sl-page-head">
    <div class="sl-page-head__titles">
        <span class="sl-page-head__icon" aria-hidden="true"><i class="feather icon-briefcase"></i></span>
        <div style="min-width:0">
            <h2 class="sl-page-head__title"><i data-feather="download" class="mr-1"></i>
                    {{ __('settings.account_info.backup_title') }}</h2>
            <nav>
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('settings.common.home') }}</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('AccountInfo.index') }}">{{ __('settings.account_info.title') }}</a></li>
                    <li class="breadcrumb-item active">{{ __('settings.account_info.backup_crumb') }}</li>

                </ol>
            </nav>
        </div>
    </div>
</div>

<div class="content-body">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-gradient-secondary">
                    <h4 class="card-title text-white mb-0">
                        <i data-feather="database" class="mr-1"></i>
                        {{ __('settings.account_info.pick_data') }}
                    </h4>
                </div>
                <div class="card-body">


                    <form action="{{ route('AccountInfo.download') }}" method="POST" id="backupForm">
                        @csrf

                        <!-- زر تحديد/{{ __('settings.common.cancel') }} تحديد الكل -->
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="mb-0">
                                <i data-feather="list" class="mr-1"></i>
                                {{ __('settings.account_info.available_data') }}
                            </h5>
                            <button type="button" class="btn btn-outline-primary btn-sm" onclick="toggleAll()">
                                <i data-feather="check-square" class="mr-1"></i>
                                {{ __('settings.account_info.toggle_all') }}
                            </button>
                        </div>

                        <div class="row">
                            <!-- قسم العملاء والموردين -->
                            <div class="col-md-6 mb-3">
                                <div class="backup-category">
                                    <h6 class="category-title">
                                        <i data-feather="users" class="mr-1"></i>
                                        {{ __('settings.account_info.group_clients') }}
                                    </h6>
                                    <div class="backup-items">
                                        <div class="custom-control custom-checkbox mb-2">
                                            <input type="checkbox" class="custom-control-input backup-checkbox" name="data_types[]" value="clients" id="clients">
                                            <label class="custom-control-label" for="clients">
                                                <i data-feather="user" class="item-icon"></i>
                                                {{ __('settings.account_info.tables.clients') }}
                                            </label>
                                        </div>
                                        <div class="custom-control custom-checkbox mb-2">
                                            <input type="checkbox" class="custom-control-input backup-checkbox" name="data_types[]" value="suppliers" id="suppliers">
                                            <label class="custom-control-label" for="suppliers">
                                                <i data-feather="truck" class="item-icon"></i>
                                                {{ __('settings.account_info.tables.suppliers') }}
                                            </label>
                                        </div>
                                        <div class="custom-control custom-checkbox mb-2">
                                            <input type="checkbox" class="custom-control-input backup-checkbox" name="data_types[]" value="employees" id="employees">
                                            <label class="custom-control-label" for="employees">
                                                <i data-feather="user-check" class="item-icon"></i>
                                                {{ __('settings.account_info.tables.employees') }}
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- قسم المبيعات -->
                            <div class="col-md-6 mb-3">
                                <div class="backup-category">
                                    <h6 class="category-title">
                                        <i data-feather="shopping-cart" class="mr-1"></i>
                                        {{ __('settings.account_info.group_sales') }}
                                    </h6>
                                    <div class="backup-items">
                                        <div class="custom-control custom-checkbox mb-2">
                                            <input type="checkbox" class="custom-control-input backup-checkbox" name="data_types[]" value="invoices" id="invoices">
                                            <label class="custom-control-label" for="invoices">
                                                <i data-feather="file-text" class="item-icon"></i>
                                                {{ __('settings.account_info.tables.invoices') }}
                                            </label>
                                        </div>
                                        <div class="custom-control custom-checkbox mb-2">
                                            <input type="checkbox" class="custom-control-input backup-checkbox" name="data_types[]" value="quotations" id="quotations">
                                            <label class="custom-control-label" for="quotations">
                                                <i data-feather="file" class="item-icon"></i>
                                                {{ __('settings.account_info.tables.quotations') }}
                                            </label>
                                        </div>
                                        <div class="custom-control custom-checkbox mb-2">
                                            <input type="checkbox" class="custom-control-input backup-checkbox" name="data_types[]" value="sales_orders" id="sales_orders">
                                            <label class="custom-control-label" for="sales_orders">
                                                <i data-feather="shopping-bag" class="item-icon"></i>
                                                {{ __('settings.account_info.tables.sales_orders') }}
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- قسم المشتريات -->
                            <div class="col-md-6 mb-3">
                                <div class="backup-category">
                                    <h6 class="category-title">
                                        <i data-feather="package" class="mr-1"></i>
                                        {{ __('settings.account_info.group_purchases') }}
                                    </h6>
                                    <div class="backup-items">
                                        <div class="custom-control custom-checkbox mb-2">
                                            <input type="checkbox" class="custom-control-input backup-checkbox" name="data_types[]" value="purchase_orders" id="purchase_orders">
                                            <label class="custom-control-label" for="purchase_orders">
                                                <i data-feather="inbox" class="item-icon"></i>
                                                {{ __('settings.account_info.tables.purchase_orders') }}
                                            </label>
                                        </div>
                                        <div class="custom-control custom-checkbox mb-2">
                                            <input type="checkbox" class="custom-control-input backup-checkbox" name="data_types[]" value="products" id="products">
                                            <label class="custom-control-label" for="products">
                                                <i data-feather="box" class="item-icon"></i>
                                                {{ __('settings.account_info.tables.products') }}
                                            </label>
                                        </div>
                                        <div class="custom-control custom-checkbox mb-2">
                                            <input type="checkbox" class="custom-control-input backup-checkbox" name="data_types[]" value="warehouses" id="warehouses">
                                            <label class="custom-control-label" for="warehouses">
                                                <i data-feather="home" class="item-icon"></i>
                                                {{ __('settings.account_info.tables.warehouses') }}
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- قسم المالية -->
                            <div class="col-md-6 mb-3">
                                <div class="backup-category">
                                    <h6 class="category-title">
                                        <i data-feather="dollar-sign" class="mr-1"></i>
                                        {{ __('settings.account_info.group_finance') }}
                                    </h6>
                                    <div class="backup-items">
                                        <div class="custom-control custom-checkbox mb-2">
                                            <input type="checkbox" class="custom-control-input backup-checkbox" name="data_types[]" value="expenses" id="expenses">
                                            <label class="custom-control-label" for="expenses">
                                                <i data-feather="trending-down" class="item-icon"></i>
                                                {{ __('settings.account_info.tables.expenses') }}
                                            </label>
                                        </div>
                                        <div class="custom-control custom-checkbox mb-2">
                                            <input type="checkbox" class="custom-control-input backup-checkbox" name="data_types[]" value="revenues" id="revenues">
                                            <label class="custom-control-label" for="revenues">
                                                <i data-feather="trending-up" class="item-icon"></i>
                                                {{ __('settings.account_info.tables.revenues') }}
                                            </label>
                                        </div>
                                        <div class="custom-control custom-checkbox mb-2">
                                            <input type="checkbox" class="custom-control-input backup-checkbox" name="data_types[]" value="accounts" id="accounts">
                                            <label class="custom-control-label" for="accounts">
                                                <i data-feather="credit-card" class="item-icon"></i>
                                                {{ __('settings.account_info.tables.accounts') }}
                                            </label>
                                        </div>
                                        <div class="custom-control custom-checkbox mb-2">
                                            <input type="checkbox" class="custom-control-input backup-checkbox" name="data_types[]" value="payments" id="payments">
                                            <label class="custom-control-label" for="payments">
                                                <i data-feather="dollar-sign" class="item-icon"></i>
                                                {{ __('settings.account_info.tables.payments') }}
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- قسم الإدارة -->
                            <div class="col-md-6 mb-3">
                                <div class="backup-category">
                                    <h6 class="category-title">
                                        <i data-feather="briefcase" class="mr-1"></i>
                                        {{ __('settings.account_info.group_management') }}
                                    </h6>
                                    <div class="backup-items">
                                        <div class="custom-control custom-checkbox mb-2">
                                            <input type="checkbox" class="custom-control-input backup-checkbox" name="data_types[]" value="projects" id="projects">
                                            <label class="custom-control-label" for="projects">
                                                <i data-feather="folder" class="item-icon"></i>
                                                {{ __('settings.account_info.tables.projects') }}
                                            </label>
                                        </div>
                                        <div class="custom-control custom-checkbox mb-2">
                                            <input type="checkbox" class="custom-control-input backup-checkbox" name="data_types[]" value="tasks" id="tasks">
                                            <label class="custom-control-label" for="tasks">
                                                <i data-feather="check-square" class="item-icon"></i>
                                                {{ __('settings.account_info.tables.tasks') }}
                                            </label>
                                        </div>
                                        <div class="custom-control custom-checkbox mb-2">
                                            <input type="checkbox" class="custom-control-input backup-checkbox" name="data_types[]" value="appointments" id="appointments">
                                            <label class="custom-control-label" for="appointments">
                                                <i data-feather="calendar" class="item-icon"></i>
                                                {{ __('settings.account_info.tables.appointments') }}
                                            </label>
                                        </div>
                                        <div class="custom-control custom-checkbox mb-2">
                                            <input type="checkbox" class="custom-control-input backup-checkbox" name="data_types[]" value="visits" id="visits">
                                            <label class="custom-control-label" for="visits">
                                                <i data-feather="map-pin" class="item-icon"></i>
                                                {{ __('settings.account_info.tables.visits') }}
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- قسم {{ __('settings.account_info.group_hr') }} -->
                            <div class="col-md-6 mb-3">
                                <div class="backup-category">
                                    <h6 class="category-title">
                                        <i data-feather="user-plus" class="mr-1"></i>
                                        {{ __('settings.account_info.group_hr') }}
                                    </h6>
                                    <div class="backup-items">
                                        <div class="custom-control custom-checkbox mb-2">
                                            <input type="checkbox" class="custom-control-input backup-checkbox" name="data_types[]" value="contracts" id="contracts">
                                            <label class="custom-control-label" for="contracts">
                                                <i data-feather="file-minus" class="item-icon"></i>
                                                {{ __('settings.account_info.tables.contracts') }}
                                            </label>
                                        </div>
                                        <div class="custom-control custom-checkbox mb-2">
                                            <input type="checkbox" class="custom-control-input backup-checkbox" name="data_types[]" value="attendances" id="attendances">
                                            <label class="custom-control-label" for="attendances">
                                                <i data-feather="clock" class="item-icon"></i>
                                                {{ __('settings.account_info.tables.attendances') }}
                                            </label>
                                        </div>
                                        <div class="custom-control custom-checkbox mb-2">
                                            <input type="checkbox" class="custom-control-input backup-checkbox" name="data_types[]" value="leaves" id="leaves">
                                            <label class="custom-control-label" for="leaves">
                                                <i data-feather="wind" class="item-icon"></i>
                                                {{ __('settings.account_info.tables.leaves') }}
                                            </label>
                                        </div>
                                        <div class="custom-control custom-checkbox mb-2">
                                            <input type="checkbox" class="custom-control-input backup-checkbox" name="data_types[]" value="salaries" id="salaries">
                                            <label class="custom-control-label" for="salaries">
                                                <i data-feather="briefcase" class="item-icon"></i>
                                                {{ __('settings.account_info.tables.salaries') }}
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- قسم الإعدادات -->
                            <div class="col-md-12 mb-3">
                                <div class="backup-category">
                                    <h6 class="category-title">
                                        <i data-feather="settings" class="mr-1"></i>
                                        {{ __('settings.account_info.group_settings') }}
                                    </h6>
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="custom-control custom-checkbox mb-2">
                                                <input type="checkbox" class="custom-control-input backup-checkbox" name="data_types[]" value="users" id="users">
                                                <label class="custom-control-label" for="users">
                                                    <i data-feather="users" class="item-icon"></i>
                                                    {{ __('settings.account_info.tables.users') }}
                                                </label>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="custom-control custom-checkbox mb-2">
                                                <input type="checkbox" class="custom-control-input backup-checkbox" name="data_types[]" value="branches" id="branches">
                                                <label class="custom-control-label" for="branches">
                                                    <i data-feather="git-branch" class="item-icon"></i>
                                                    {{ __('settings.account_info.tables.branches') }}
                                                </label>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="custom-control custom-checkbox mb-2">
                                                <input type="checkbox" class="custom-control-input backup-checkbox" name="data_types[]" value="activity_logs" id="activity_logs">
                                                <label class="custom-control-label" for="activity_logs">
                                                    <i data-feather="activity" class="item-icon"></i>
                                                    {{ __('settings.account_info.tables.activity_logs') }}
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <hr class="my-4">

                        <!-- اختيار {{ __('settings.account_info.file_format') }} -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="file_format" class="form-label">
                                        <i data-feather="file" class="mr-1"></i>
                                        {{ __('settings.account_info.file_format') }}
                                    </label>
                                    <select name="file_format" id="file_format" class="form-control form-control-lg" required>
                                        <option value="">{{ __('settings.account_info.choose_format') }}</option>
                                        <option value="json" selected>
                                            <i data-feather="code"></i> {{ __('settings.account_info.format_json_import') }}
                                        </option>
                                        <option value="xml">{{ __('settings.account_info.format_xml_other') }}</option>
                                        <option value="csv">{{ __('settings.account_info.format_csv_excel') }}</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label d-block">
                                    <i data-feather="info" class="mr-1"></i>
                                    {{ __('settings.account_info.selected_tables') }}
                                </label>
                                <div class="alert alert-success mb-0">
                                    <h3 class="alert-heading mb-0">
                                        <span id="selectedCount">0</span> {{ __('settings.account_info.tables_selected') }}
                                    </h3>
                                </div>
                            </div>
                        </div>

                        <!-- أزرار التحكم -->
                        <div class="d-flex justify-content-between align-items-center mt-4">
                            <a href="{{ route('AccountInfo.index') }}" class="btn btn-outline-secondary btn-lg">
                                <i data-feather="x" class="mr-1"></i>
                                {{ __('settings.common.cancel') }}
                            </a>
                            <button type="submit" class="btn btn-primary" id="downloadBtn" disabled>
                                <i data-feather="download" class="mr-1"></i>
                                {{ __('settings.account_info.download') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .bg-gradient-primary {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }

    .backup-category {
        padding: 1.5rem;
        border-radius: 0.5rem;
        background: #f8f9fa;
        border: 2px solid #e9ecef;
        height: 100%;
        transition: all 0.3s ease;
    }

    .backup-category:hover {
        border-color: #7367f0;
        box-shadow: 0 4px 12px rgba(115, 103, 240, 0.15);
    }

    .category-title {
        font-weight: 700;
        color: #7367f0;
        margin-bottom: 1rem;
        padding-bottom: 0.5rem;
        border-bottom: 2px solid #e9ecef;
    }

    .backup-items {
        padding-top: 0.5rem;
    }

    .custom-control-label {
        font-weight: 500;
        cursor: pointer;
        user-select: none;
        display: flex;
        align-items: center;
    }

    .item-icon {
        width: 18px;
        height: 18px;
        margin-left: 0.5rem;
        color: #7367f0;
    }

    .custom-control-input:checked ~ .custom-control-label {
        color: #7367f0;
        font-weight: 600;
    }

    .custom-checkbox .custom-control-input:checked ~ .custom-control-label::before {
        background-color: #7367f0;
        border-color: #7367f0;
    }

    .btn-gradient-primary {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border: none;
        color: #fff;
        font-weight: 600;
        transition: all 0.3s ease;
    }

    .btn-gradient-primary:hover:not(:disabled) {
        background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
        transform: translateY(-2px);
        box-shadow: 0 8px 16px rgba(115, 103, 240, 0.3);
        color: #fff;
    }

    .btn-gradient-primary:disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }

    .alert {
        border-radius: 0.5rem;
    }

    .form-control-lg {
        border-radius: 0.5rem;
        border: 2px solid #e9ecef;
    }

    .form-control-lg:focus {
        border-color: #7367f0;
        box-shadow: 0 0 0 0.2rem rgba(115, 103, 240, 0.25);
    }
</style>

<script>
    // عداد الجداول المحددة
    function updateSelectedCount() {
        const checkboxes = document.querySelectorAll('.backup-checkbox:checked');
        const count = checkboxes.length;
        document.getElementById('selectedCount').textContent = count;
        document.getElementById('downloadBtn').disabled = count === 0;
    }

    // تحديد/{{ __('settings.common.cancel') }} تحديد الكل
    function toggleAll() {
        const checkboxes = document.querySelectorAll('.backup-checkbox');
        const allChecked = Array.from(checkboxes).every(cb => cb.checked);

        checkboxes.forEach(checkbox => {
            checkbox.checked = !allChecked;
        });

        updateSelectedCount();
    }

    // مراقبة التغييرات على الـ checkboxes
    document.addEventListener('DOMContentLoaded', function() {
        const checkboxes = document.querySelectorAll('.backup-checkbox');
        checkboxes.forEach(checkbox => {
            checkbox.addEventListener('change', updateSelectedCount);
        });

        // تفعيل أيقونات Feather
        if (typeof feather !== 'undefined') {
            feather.replace();
        }

        // تحديث العداد عند التحميل
        updateSelectedCount();
    });

    // تأكيد قبل التنزيل
    document.getElementById('backupForm').addEventListener('submit', function(e) {
        const count = document.querySelectorAll('.backup-checkbox:checked').length;
        if (!confirm(@json(__('settings.account_info.confirm_download')).replace(':count', count))) {
            e.preventDefault();
        }
    });
</script>
@endsection
