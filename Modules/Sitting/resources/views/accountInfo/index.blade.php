@extends('sitting::master')

@section('title')
    {{ __('settings.account_info.page_title') }}
@stop

@section('content')
    <style>
        /* =========================================================================
           PREMIUM SaaS ACCOUNT INFO & BACKUP STYLING
           ========================================================================= */
        :root {
            --primary: #7367f0;
            --primary-light: rgba(115, 103, 240, 0.08);
            --primary-hover: #5e50ee;
            --success: #28c76f;
            --success-light: rgba(40, 199, 111, 0.08);
            --warning: #ff9f43;
            --warning-light: rgba(255, 159, 67, 0.08);
            --danger: #ea5455;
            --danger-light: rgba(234, 84, 85, 0.08);
            --info: #00cfe8;
            --info-light: rgba(0, 206, 232, 0.08);
            
            --card-bg: #ffffff;
            --text-title: #2c3e50;
            --text-body: #5e5873;
            --text-muted: #b9b9c3;
            --border-radius-lg: 16px;
            --border-radius-md: 10px;
            --transition-smooth: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .settings-header {
            padding: 1.5rem 0;
            margin-bottom: 2rem;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        }

        .settings-title {
            font-size: 1.8rem;
            font-weight: 800;
            color: var(--text-title);
            margin: 0 0 0.5rem 0;
        }

        .settings-subtitle {
            font-size: 0.95rem;
            color: var(--text-muted);
            margin: 0;
        }

        /* Profile Sidebar Card */
        .profile-card {
            background: var(--card-bg);
            border: 1px solid rgba(0, 0, 0, 0.06);
            border-radius: var(--border-radius-lg);
            box-shadow: 0 4px 25px 0 rgba(34, 41, 47, 0.03);
            text-align: center;
            padding: 2rem 1.5rem;
            transition: var(--transition-smooth);
            overflow: hidden;
            margin-bottom: 2rem;
        }

        .profile-card:hover {
            box-shadow: 0 8px 30px 0 rgba(115, 103, 240, 0.06);
        }

        .profile-avatar-wrapper {
            margin-bottom: 1.5rem;
        }

        .profile-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            padding: 4px;
            border: 3px solid var(--primary-light);
            background: #ffffff;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 10px 20px rgba(115, 103, 240, 0.12);
        }

        .profile-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
        }

        .profile-name {
            font-size: 1.3rem;
            font-weight: 800;
            color: var(--text-title);
            margin-bottom: 1.5rem;
        }

        .profile-info-list {
            text-align: right;
            margin-bottom: 2rem;
        }

        .profile-info-item {
            padding: 0.85rem 1rem;
            margin-bottom: 0.75rem;
            border-radius: var(--border-radius-md);
            background: #f8f9fa;
            border-right: 3px solid var(--primary);
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: var(--transition-smooth);
        }

        .profile-info-item:hover {
            background: var(--primary-light);
            transform: translateX(-4px);
        }

        .profile-info-label {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-body);
        }

        .profile-info-value {
            font-size: 0.9rem;
            font-weight: 700;
            color: var(--text-title);
        }

        /* Action Buttons */
        .premium-btn {
            width: 100%;
            height: 46px;
            border-radius: var(--border-radius-md);
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: var(--transition-smooth);
            border: 1px solid transparent;
            margin-bottom: 0.75rem;
        }

        .premium-btn-primary {
            background: var(--primary);
            color: #ffffff;
        }

        .premium-btn-primary:hover:not(:disabled) {
            background: var(--primary-hover);
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(115, 103, 240, 0.2);
            color: #ffffff;
        }

        .premium-btn-primary:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .premium-btn-outline-danger {
            background: transparent;
            border-color: var(--danger);
            color: var(--danger);
        }

        .premium-btn-outline-danger:hover {
            background: var(--danger-light);
            color: var(--danger);
            transform: translateY(-2px);
        }

        .premium-btn-outline-secondary {
            background: transparent;
            border-color: #dee2e6;
            color: #6c757d;
        }

        .premium-btn-outline-secondary:hover {
            background: #f8f9fa;
            color: #495057;
        }

        /* Premium Card for Subscription & Resources */
        .details-card {
            background: var(--card-bg);
            border: 1px solid rgba(0, 0, 0, 0.06);
            border-radius: var(--border-radius-lg);
            box-shadow: 0 4px 25px 0 rgba(34, 41, 47, 0.03);
            margin-bottom: 2rem;
            transition: var(--transition-smooth);
            padding: 2rem;
        }

        .details-card-title {
            font-size: 1.25rem;
            font-weight: 800;
            color: var(--text-title);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .details-card-title i {
            color: var(--primary);
            background: var(--primary-light);
            padding: 8px;
            border-radius: var(--border-radius-md);
            font-size: 1.2rem;
        }

        /* Package Details box */
        .subscription-box {
            background: linear-gradient(135deg, #7367f0 0%, #ce9ffc 100%);
            border-radius: var(--border-radius-lg);
            padding: 1.75rem;
            color: #ffffff;
            margin-bottom: 2rem;
            box-shadow: 0 10px 25px rgba(115, 103, 240, 0.2);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1.5rem;
        }

        .subscription-details h4 {
            font-size: 1.5rem;
            font-weight: 800;
            color: #ffffff;
            margin: 0 0 0.5rem 0;
        }

        .subscription-details p {
            font-size: 0.95rem;
            opacity: 0.9;
            margin: 0;
        }

        .subscription-badge {
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            padding: 6px 14px;
            border-radius: 30px;
            font-size: 0.85rem;
            font-weight: 700;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        /* Action triggers on header */
        .subscription-actions {
            display: flex;
            gap: 10px;
        }

        .sub-action-btn {
            background: #ffffff;
            color: var(--primary);
            border: none;
            border-radius: var(--border-radius-md);
            padding: 8px 16px;
            font-weight: 700;
            font-size: 0.85rem;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: var(--transition-smooth);
        }

        .sub-action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.1);
            color: var(--primary-hover);
        }

        /* Resource Usage Grid */
        .resources-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 1.5rem;
        }

        .resource-item {
            background: #fafafb;
            border: 1px solid rgba(0, 0, 0, 0.04);
            border-radius: var(--border-radius-md);
            padding: 1.25rem;
            transition: var(--transition-smooth);
        }

        .resource-item:hover {
            background: #ffffff;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.04);
            border-color: rgba(115, 103, 240, 0.15);
            transform: translateY(-4px);
        }

        .resource-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.85rem;
        }

        .resource-label {
            font-size: 0.9rem;
            font-weight: 700;
            color: var(--text-title);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .resource-label i {
            font-size: 1.15rem;
        }

        .resource-usage {
            font-size: 0.8rem;
            font-weight: 700;
            padding: 4px 8px;
            border-radius: 6px;
        }

        /* Progress bars */
        .resource-progress-container {
            height: 8px;
            background: #e9ecef;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: inset 0 1px 2px rgba(0,0,0,0.05);
        }

        .resource-progress-bar {
            height: 100%;
            border-radius: 10px;
            transition: width 0.6s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* Backup Layout design */
        .backup-category {
            padding: 1.25rem;
            border-radius: var(--border-radius-md);
            background: #fafafb;
            border: 1px solid rgba(0, 0, 0, 0.05);
            height: 100%;
            transition: var(--transition-smooth);
        }

        .backup-category:hover {
            border-color: var(--primary);
            box-shadow: 0 6px 15px rgba(115, 103, 240, 0.05);
            background: #ffffff;
        }

        .category-title {
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid rgba(115, 103, 240, 0.08);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .backup-items {
            padding-top: 0.25rem;
        }

        .custom-checkbox-wrapper {
            margin-bottom: 0.75rem;
        }

        .custom-checkbox-label {
            font-weight: 500;
            color: var(--text-body);
            cursor: pointer;
            user-select: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .custom-checkbox-label i {
            font-size: 1.05rem;
            color: var(--text-muted);
        }

        .backup-checkbox {
            accent-color: var(--primary);
            width: 16px;
            height: 16px;
            cursor: pointer;
        }

        .backup-checkbox:checked + .custom-checkbox-label {
            color: var(--primary);
            font-weight: 600;
        }

        .backup-checkbox:checked + .custom-checkbox-label i {
            color: var(--primary);
        }

        /* Theme colors wrapper */
        .color-primary { color: var(--primary); }
        .color-success { color: var(--success); }
        .color-warning { color: var(--warning); }
        .color-danger { color: var(--danger); }
        .color-info { color: var(--info); }

        .bg-primary-gradient { background: linear-gradient(90deg, #7367f0 0%, #9e95f5 100%); }
        .bg-success-gradient { background: linear-gradient(90deg, #28c76f 0%, #6ee39f 100%); }
        .bg-warning-gradient { background: linear-gradient(90deg, #ff9f43 0%, #ffc285 100%); }
        .bg-danger-gradient { background: linear-gradient(90deg, #ea5455 0%, #f39b99 100%); }
        .bg-info-gradient { background: linear-gradient(90deg, #00cfe8 0%, #65f0ff 100%); }

        .badge-light-primary { background: var(--primary-light); color: var(--primary); }
        .badge-light-success { background: var(--success-light); color: var(--success); }
        .badge-light-warning { background: var(--warning-light); color: var(--warning); }
        .badge-light-danger { background: var(--danger-light); color: var(--danger); }
        .badge-light-info { background: var(--info-light); color: var(--info); }

        /* RTL Alignment */
        html[dir="rtl"] .profile-info-item {
            border-right: 3px solid var(--primary);
            border-left: none;
        }

        /* Modals style */
        .premium-modal .modal-content {
            border-radius: var(--border-radius-lg);
            border: none;
            overflow: hidden;
            box-shadow: 0 15px 50px rgba(0,0,0,0.15);
        }
        .premium-modal .modal-header {
            background: var(--primary-light);
            border-bottom: 1px solid rgba(115, 103, 240, 0.1);
            padding: 1.25rem 1.5rem;
        }
        .premium-modal .modal-title {
            font-weight: 700;
            color: var(--primary);
            font-size: 1.15rem;
        }
        .coming-soon-icon {
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.08); }
        }
    </style>

    <!-- Header Section -->
    <div class="settings-header text-start">
        <h2 class="settings-title">{{ __('settings.account_info.page_title') }}</h2>
        <p class="settings-subtitle">{{ __('settings.account_info.subtitle') }}</p>
    </div>

    <!-- Alert Notifications -->
    @include('layouts.alerts.error')
    @include('layouts.alerts.success')

    <div class="content-body">
        <div class="row">
            <!-- Sidebar Panel: معلومات الحساب والمنشأة -->
            <div class="col-lg-4 col-12">
                <div class="profile-card">
                    <div class="profile-avatar-wrapper">
                        <div class="profile-avatar">
                            @if($account_setting && $account_setting->attachments)
                                @if(str_starts_with($account_setting->attachments, 'uploads/'))
                                    <img src="{{ request()->getBaseUrl() . '/' . $account_setting->attachments }}" alt="{{ __('settings.account.logo') }}">
                                @else
                                    <img src="{{ request()->getBaseUrl() . '/storage/' . $account_setting->attachments }}" alt="{{ __('settings.account.logo') }}">
                                @endif
                            @else
                                <img src="{{ asset('app-assets/images/logo/logo.png') }}" alt="{{ __('settings.account.logo') }}">
                            @endif
                        </div>
                    </div>
                    
                    <h3 class="profile-name">{{ $client->trade_name ?? __('settings.account_info.default_business') }}</h3>

                    <div class="profile-info-list">
                        <div class="profile-info-item">
                            <span class="profile-info-label">{{ __('settings.common.email') }}</span>
                            <span class="profile-info-value" style="font-size: 0.8rem;">{{ $client->email ?? __('settings.account_info.not_set') }}</span>
                        </div>
                        <div class="profile-info-item">
                            <span class="profile-info-label">{{ __('settings.account_info.location_city') }}</span>
                            <span class="profile-info-value">{{ $client->city ?? '' }} {{ $client->region ?? '' }}</span>
                        </div>
                        <div class="profile-info-item">
                            <span class="profile-info-label">{{ __('settings.account_info.default_currency') }}</span>
                            <span class="badge badge-light-primary">{{ $account_setting->currency ?? 'SAR' }}</span>
                        </div>
                        <div class="profile-info-item">
                            <span class="profile-info-label">{{ __('settings.account_info.default_language') }}</span>
                            <span class="profile-info-value">{{ ($account_setting->language ?? '') == 'ar' ? __('settings.account_info.arabic') : ($account_setting?->language ?: __('settings.account_info.arabic')) }}</span>
                        </div>
                        <div class="profile-info-item">
                            <span class="profile-info-label">{{ __('settings.account_info.timezone') }}</span>
                            <span class="profile-info-value" style="font-size: 0.8rem;">{{ $account_setting->timezone ?? 'Asia/Riyadh' }}</span>
                        </div>
                        <div class="profile-info-item">
                            <span class="profile-info-label">{{ __('settings.account_info.date_format') }}</span>
                            <span class="profile-info-value">{{ $account_setting->time_formula ?? 'd/m/Y' }}</span>
                        </div>
                        <div class="profile-info-item">
                            <span class="profile-info-label">{{ __('settings.account_info.operations_type') }}</span>
                            <span class="profile-info-value" style="font-size: 0.8rem;">
                                @if(($account_setting->business_type ?? '') == 'products')
                                    {{ __('settings.account_info.products_only') }}
                                @elseif(($account_setting->business_type ?? '') == 'services')
                                    {{ __('settings.account_info.services_only') }}
                                @else
                                    {{ __('settings.account_info.products_services') }}
                                @endif
                            </span>
                        </div>
                        <div class="profile-info-item">
                            <span class="profile-info-label">{{ __('settings.account_info.print_method') }}</span>
                            <span class="profile-info-value" style="font-size: 0.8rem;">{{ ($account_setting->printing_method ?? '') == 'pdf' ? __('settings.account_info.print_pdf') : __('settings.account_info.print_browser') }}</span>
                        </div>
                        <div class="profile-info-item">
                            <span class="profile-info-label">{{ __('settings.account_info.tax_number') }}</span>
                            <span class="profile-info-value">{{ $client->tax_number ?? __('settings.account_info.not_set') }}</span>
                        </div>
                        <div class="profile-info-item">
                            <span class="profile-info-label">{{ __('settings.account_info.commercial_registration') }}</span>
                            <span class="profile-info-value">{{ $client->commercial_registration ?? __('settings.account_info.not_set') }}</span>
                        </div>
                        <div class="profile-info-item">
                            <span class="profile-info-label">{{ __('settings.account_info.phone_mobile') }}</span>
                            <span class="profile-info-value">{{ $client->phone ?? ($client->mobile ?? __('settings.account_info.not_set')) }}</span>
                        </div>
                    </div>

                    <div class="d-flex flex-column gap-2 mt-2">
                        <button class="premium-btn premium-btn-outline-danger" data-bs-toggle="modal" data-bs-target="#comingSoonModal">
                            <i class="feather icon-x-circle"></i>
                            {{ __('settings.account_info.cancel_account') }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- Right Panel: معلومات الباقة والنسخ الاحتياطي -->
            <div class="col-lg-8 col-12">
                <!-- Subscription Card -->
                <div class="details-card">
                    <!-- Subscription Details -->
                    <div class="subscription-box">
                        <div class="subscription-details">
                            <h4>{{ __('settings.account_info.gold_package') }}</h4>
                            <p>{{ __('settings.account_info.expires_on') }} <strong>05/02/2025</strong></p>
                        </div>
                        <div class="subscription-actions">
                            <span class="subscription-badge">{{ __('settings.account_info.active_badge') }}</span>
                            <button class="sub-action-btn" data-bs-toggle="modal" data-bs-target="#comingSoonModal">
                                <i class="feather icon-arrow-up"></i>
                                {{ __('settings.account_info.upgrade_renew') }}
                            </button>
                        </div>
                    </div>

                    <!-- Resource Usage Section -->
                    <div class="details-card-title">
                        <i class="feather icon-pie-chart"></i>
                        <span>{{ __('settings.account_info.usage_levels') }}</span>
                    </div>

                    <div class="resources-grid mb-2">
                        <!-- الفواتير -->
                        <div class="resource-item">
                            <div class="resource-header">
                                <span class="resource-label color-primary">
                                    <i class="feather icon-file-text"></i>
                                    {{ __('settings.account_info.invoices') }}
                                </span>
                                <span class="badge badge-light-primary">
                                    {{ $usageData['invoices'] ?? 0 }} / {{ $packageLimits['invoices'] ?? 100 }}
                                </span>
                            </div>
                            <div class="resource-progress-container">
                                @php
                                    $percentage = ($packageLimits['invoices'] ?? 0) > 0 
                                        ? min((($usageData['invoices'] ?? 0) / $packageLimits['invoices']) * 100, 100) 
                                        : 0;
                                @endphp
                                <div class="resource-progress-bar bg-primary-gradient" style="width: {{ $percentage }}%"></div>
                            </div>
                        </div>

                        <!-- العملاء -->
                        <div class="resource-item">
                            <div class="resource-header">
                                <span class="resource-label color-success">
                                    <i class="feather icon-users"></i>
                                    {{ __('settings.account_info.clients') }}
                                </span>
                                <span class="badge badge-light-success">
                                    {{ $usageData['clients'] ?? 0 }} / {{ $packageLimits['clients'] ?? 50 }}
                                </span>
                            </div>
                            <div class="resource-progress-container">
                                @php
                                    $percentage = ($packageLimits['clients'] ?? 0) > 0 
                                        ? min((($usageData['clients'] ?? 0) / $packageLimits['clients']) * 100, 100) 
                                        : 0;
                                @endphp
                                <div class="resource-progress-bar bg-success-gradient" style="width: {{ $percentage }}%"></div>
                            </div>
                        </div>

                        <!-- المنتجات -->
                        <div class="resource-item">
                            <div class="resource-header">
                                <span class="resource-label color-info">
                                    <i class="feather icon-package"></i>
                                    {{ __('settings.account_info.products') }}
                                </span>
                                <span class="badge badge-light-info">
                                    {{ $usageData['products'] ?? 0 }} / {{ $packageLimits['products'] ?? 200 }}
                                </span>
                            </div>
                            <div class="resource-progress-container">
                                @php
                                    $percentage = ($packageLimits['products'] ?? 0) > 0 
                                        ? min((($usageData['products'] ?? 0) / $packageLimits['products']) * 100, 100) 
                                        : 0;
                                @endphp
                                <div class="resource-progress-bar bg-info-gradient" style="width: {{ $percentage }}%"></div>
                            </div>
                        </div>

                        <!-- الموظفين -->
                        <div class="resource-item">
                            <div class="resource-header">
                                <span class="resource-label color-warning">
                                    <i class="feather icon-user-check"></i>
                                    {{ __('settings.account_info.employees') }}
                                </span>
                                <span class="badge badge-light-warning">
                                    {{ $usageData['employees'] ?? 0 }} / {{ $packageLimits['employees'] ?? 10 }}
                                </span>
                            </div>
                            <div class="resource-progress-container">
                                @php
                                    $percentage = ($packageLimits['employees'] ?? 0) > 0 
                                        ? min((($usageData['employees'] ?? 0) / $packageLimits['employees']) * 100, 100) 
                                        : 0;
                                @endphp
                                <div class="resource-progress-bar bg-warning-gradient" style="width: {{ $percentage }}%"></div>
                            </div>
                        </div>

                        <!-- الموردين -->
                        <div class="resource-item">
                            <div class="resource-header">
                                <span class="resource-label color-primary">
                                    <i class="feather icon-truck"></i>
                                    {{ __('settings.account_info.suppliers') }}
                                </span>
                                <span class="badge badge-light-primary">
                                    {{ $usageData['suppliers'] ?? 0 }} / {{ $packageLimits['suppliers'] ?? 30 }}
                                </span>
                            </div>
                            <div class="resource-progress-container">
                                @php
                                    $percentage = ($packageLimits['suppliers'] ?? 0) > 0 
                                        ? min((($usageData['suppliers'] ?? 0) / $packageLimits['suppliers']) * 100, 100) 
                                        : 0;
                                @endphp
                                <div class="resource-progress-bar bg-primary-gradient" style="width: {{ $percentage }}%"></div>
                            </div>
                        </div>

                        <!-- مساحة التخزين -->
                        <div class="resource-item">
                            <div class="resource-header">
                                <span class="resource-label color-success">
                                    <i class="feather icon-hard-drive"></i>
                                    {{ __('settings.account_info.storage_gb') }}
                                </span>
                                <span class="badge badge-light-success">
                                    {{ $usageData['storage_gb'] ?? 0 }} / {{ $packageLimits['storage_gb'] ?? 50 }}
                                </span>
                            </div>
                            <div class="resource-progress-container">
                                @php
                                    $percentage = ($packageLimits['storage_gb'] ?? 0) > 0 
                                        ? min((($usageData['storage_gb'] ?? 0) / $packageLimits['storage_gb']) * 100, 100) 
                                        : 0;
                                @endphp
                                <div class="resource-progress-bar bg-success-gradient" style="width: {{ $percentage }}%"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Backup Card (Merged directly on this view) -->
                <div class="details-card">
                    <div class="details-card-title">
                        <i class="feather icon-database"></i>
                        <span>{{ __('settings.account_info.create_download') }}</span>
                    </div>

                    <form action="{{ route('AccountInfo.download') }}" method="POST" id="backupForm">
                        @csrf

                        <!-- Header check controls -->
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="mb-0" style="font-weight: 700; font-size: 0.95rem; color: var(--text-title);">
                                <i class="feather icon-list mr-1"></i>
                                {{ __('settings.account_info.pick_tables') }}
                            </h5>
                            <button type="button" class="btn btn-outline-primary btn-sm rounded-pill" onclick="toggleAll()">
                                <i class="feather icon-check-square mr-1"></i>
                                {{ __('settings.account_info.select_all') }}
                            </button>
                        </div>

                        <div class="row">
                            <!-- Category 1: {{ __('settings.account_info.group_clients') }} -->
                            <div class="col-md-6 mb-3">
                                <div class="backup-category">
                                    <h6 class="category-title">
                                        <i class="feather icon-users"></i>
                                        {{ __('settings.account_info.group_clients') }}
                                    </h6>
                                    <div class="backup-items">
                                        <div class="custom-checkbox-wrapper d-flex align-items-center gap-2">
                                            <input type="checkbox" class="backup-checkbox" name="data_types[]" value="clients" id="clients">
                                            <label class="custom-checkbox-label" for="clients">
                                                <i class="feather icon-user"></i>
                                                {{ __('settings.account_info.tables.clients') }}
                                            </label>
                                        </div>
                                        <div class="custom-checkbox-wrapper d-flex align-items-center gap-2">
                                            <input type="checkbox" class="backup-checkbox" name="data_types[]" value="suppliers" id="suppliers">
                                            <label class="custom-checkbox-label" for="suppliers">
                                                <i class="feather icon-truck"></i>
                                                {{ __('settings.account_info.tables.suppliers') }}
                                            </label>
                                        </div>
                                        <div class="custom-checkbox-wrapper d-flex align-items-center gap-2">
                                            <input type="checkbox" class="backup-checkbox" name="data_types[]" value="employees" id="employees">
                                            <label class="custom-checkbox-label" for="employees">
                                                <i class="feather icon-user-check"></i>
                                                {{ __('settings.account_info.tables.employees') }}
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Category 2: {{ __('settings.account_info.group_sales') }} -->
                            <div class="col-md-6 mb-3">
                                <div class="backup-category">
                                    <h6 class="category-title">
                                        <i class="feather icon-shopping-cart"></i>
                                        {{ __('settings.account_info.group_sales') }}
                                    </h6>
                                    <div class="backup-items">
                                        <div class="custom-checkbox-wrapper d-flex align-items-center gap-2">
                                            <input type="checkbox" class="backup-checkbox" name="data_types[]" value="invoices" id="invoices">
                                            <label class="custom-checkbox-label" for="invoices">
                                                <i class="feather icon-file-text"></i>
                                                {{ __('settings.account_info.tables.invoices') }}
                                            </label>
                                        </div>
                                        <div class="custom-checkbox-wrapper d-flex align-items-center gap-2">
                                            <input type="checkbox" class="backup-checkbox" name="data_types[]" value="quotations" id="quotations">
                                            <label class="custom-checkbox-label" for="quotations">
                                                <i class="feather icon-file"></i>
                                                {{ __('settings.account_info.tables.quotations') }}
                                            </label>
                                        </div>
                                        <div class="custom-checkbox-wrapper d-flex align-items-center gap-2">
                                            <input type="checkbox" class="backup-checkbox" name="data_types[]" value="sales_orders" id="sales_orders">
                                            <label class="custom-checkbox-label" for="sales_orders">
                                                <i class="feather icon-shopping-bag"></i>
                                                {{ __('settings.account_info.tables.sales_orders') }}
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Category 3: المشتريات والمخازن -->
                            <div class="col-md-6 mb-3">
                                <div class="backup-category">
                                    <h6 class="category-title">
                                        <i class="feather icon-package"></i>
                                        {{ __('settings.account_info.group_purchases') }}
                                    </h6>
                                    <div class="backup-items">
                                        <div class="custom-checkbox-wrapper d-flex align-items-center gap-2">
                                            <input type="checkbox" class="backup-checkbox" name="data_types[]" value="purchase_orders" id="purchase_orders">
                                            <label class="custom-checkbox-label" for="purchase_orders">
                                                <i class="feather icon-inbox"></i>
                                                {{ __('settings.account_info.tables.purchase_orders') }}
                                            </label>
                                        </div>
                                        <div class="custom-checkbox-wrapper d-flex align-items-center gap-2">
                                            <input type="checkbox" class="backup-checkbox" name="data_types[]" value="products" id="products">
                                            <label class="custom-checkbox-label" for="products">
                                                <i class="feather icon-box"></i>
                                                {{ __('settings.account_info.tables.products') }}
                                            </label>
                                        </div>
                                        <div class="custom-checkbox-wrapper d-flex align-items-center gap-2">
                                            <input type="checkbox" class="backup-checkbox" name="data_types[]" value="warehouses" id="warehouses">
                                            <label class="custom-checkbox-label" for="warehouses">
                                                <i class="feather icon-home"></i>
                                                {{ __('settings.account_info.tables.warehouses') }}
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Category 4: الحسابات والمالية -->
                            <div class="col-md-6 mb-3">
                                <div class="backup-category">
                                    <h6 class="category-title">
                                        <i class="feather icon-dollar-sign"></i>
                                        {{ __('settings.account_info.group_finance') }}
                                    </h6>
                                    <div class="backup-items">
                                        <div class="custom-checkbox-wrapper d-flex align-items-center gap-2">
                                            <input type="checkbox" class="backup-checkbox" name="data_types[]" value="expenses" id="expenses">
                                            <label class="custom-checkbox-label" for="expenses">
                                                <i class="feather icon-trending-down"></i>
                                                {{ __('settings.account_info.tables.expenses') }}
                                            </label>
                                        </div>
                                        <div class="custom-checkbox-wrapper d-flex align-items-center gap-2">
                                            <input type="checkbox" class="backup-checkbox" name="data_types[]" value="revenues" id="revenues">
                                            <label class="custom-checkbox-label" for="revenues">
                                                <i class="feather icon-trending-up"></i>
                                                {{ __('settings.account_info.tables.revenues') }}
                                            </label>
                                        </div>
                                        <div class="custom-checkbox-wrapper d-flex align-items-center gap-2">
                                            <input type="checkbox" class="backup-checkbox" name="data_types[]" value="accounts" id="accounts">
                                            <label class="custom-checkbox-label" for="accounts">
                                                <i class="feather icon-credit-card"></i>
                                                {{ __('settings.account_info.tables.accounts') }}
                                            </label>
                                        </div>
                                        <div class="custom-checkbox-wrapper d-flex align-items-center gap-2">
                                            <input type="checkbox" class="backup-checkbox" name="data_types[]" value="payments" id="payments">
                                            <label class="custom-checkbox-label" for="payments">
                                                <i class="feather icon-check-circle"></i>
                                                {{ __('settings.account_info.tables.payments') }}
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Category 5: المشاريع والإدارة والـ HR والسجلات -->
                            <div class="col-12 mb-3">
                                <div class="backup-category">
                                    <h6 class="category-title">
                                        <i class="feather icon-settings"></i>
                                        {{ __('settings.account_info.group_projects') }}
                                    </h6>
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="custom-checkbox-wrapper d-flex align-items-center gap-2">
                                                <input type="checkbox" class="backup-checkbox" name="data_types[]" value="projects" id="projects">
                                                <label class="custom-checkbox-label" for="projects">
                                                    <i class="feather icon-folder"></i> {{ __('settings.account_info.short.projects') }}
                                                </label>
                                            </div>
                                            <div class="custom-checkbox-wrapper d-flex align-items-center gap-2">
                                                <input type="checkbox" class="backup-checkbox" name="data_types[]" value="tasks" id="tasks">
                                                <label class="custom-checkbox-label" for="tasks">
                                                    <i class="feather icon-check-square"></i> {{ __('settings.account_info.short.tasks') }}
                                                </label>
                                            </div>
                                            <div class="custom-checkbox-wrapper d-flex align-items-center gap-2">
                                                <input type="checkbox" class="backup-checkbox" name="data_types[]" value="appointments" id="appointments">
                                                <label class="custom-checkbox-label" for="appointments">
                                                    <i class="feather icon-calendar"></i> {{ __('settings.account_info.short.appointments') }}
                                                </label>
                                            </div>
                                            <div class="custom-checkbox-wrapper d-flex align-items-center gap-2">
                                                <input type="checkbox" class="backup-checkbox" name="data_types[]" value="visits" id="visits">
                                                <label class="custom-checkbox-label" for="visits">
                                                    <i class="feather icon-map-pin"></i> {{ __('settings.account_info.short.visits') }}
                                                </label>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="custom-checkbox-wrapper d-flex align-items-center gap-2">
                                                <input type="checkbox" class="backup-checkbox" name="data_types[]" value="contracts" id="contracts">
                                                <label class="custom-checkbox-label" for="contracts">
                                                    <i class="feather icon-file-text"></i> {{ __('settings.account_info.short.contracts') }}
                                                </label>
                                            </div>
                                            <div class="custom-checkbox-wrapper d-flex align-items-center gap-2">
                                                <input type="checkbox" class="backup-checkbox" name="data_types[]" value="attendances" id="attendances">
                                                <label class="custom-checkbox-label" for="attendances">
                                                    <i class="feather icon-clock"></i> {{ __('settings.account_info.short.attendance') }}
                                                </label>
                                            </div>
                                            <div class="custom-checkbox-wrapper d-flex align-items-center gap-2">
                                                <input type="checkbox" class="backup-checkbox" name="data_types[]" value="leaves" id="leaves">
                                                <label class="custom-checkbox-label" for="leaves">
                                                    <i class="feather icon-log-out"></i> {{ __('settings.account_info.short.leaves') }}
                                                </label>
                                            </div>
                                            <div class="custom-checkbox-wrapper d-flex align-items-center gap-2">
                                                <input type="checkbox" class="backup-checkbox" name="data_types[]" value="salaries" id="salaries">
                                                <label class="custom-checkbox-label" for="salaries">
                                                    <i class="feather icon-credit-card"></i> {{ __('settings.account_info.short.salaries') }}
                                                </label>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="custom-checkbox-wrapper d-flex align-items-center gap-2">
                                                <input type="checkbox" class="backup-checkbox" name="data_types[]" value="users" id="users">
                                                <label class="custom-checkbox-label" for="users">
                                                    <i class="feather icon-users"></i> {{ __('settings.account_info.short.users') }}
                                                </label>
                                            </div>
                                            <div class="custom-checkbox-wrapper d-flex align-items-center gap-2">
                                                <input type="checkbox" class="backup-checkbox" name="data_types[]" value="branches" id="branches">
                                                <label class="custom-checkbox-label" for="branches">
                                                    <i class="feather icon-git-branch"></i> {{ __('settings.account_info.short.branches') }}
                                                </label>
                                            </div>
                                            <div class="custom-checkbox-wrapper d-flex align-items-center gap-2">
                                                <input type="checkbox" class="backup-checkbox" name="data_types[]" value="activity_logs" id="activity_logs">
                                                <label class="custom-checkbox-label" for="activity_logs">
                                                    <i class="feather icon-activity"></i> {{ __('settings.account_info.short.activity_logs') }}
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <hr class="my-4">

                        <!-- Export Options & Action Button -->
                        <div class="row align-items-end">
                            <div class="col-md-6 mb-3">
                                <div class="form-group mb-0">
                                    <label for="file_format" class="form-label" style="font-weight: 700; font-size: 0.9rem; color: var(--text-title);">
                                        <i class="feather icon-file-text text-primary mr-1"></i>
                                        {{ __('settings.account_info.format') }}
                                    </label>
                                    <select name="file_format" id="file_format" class="form-control" style="height: 48px; border-radius: var(--border-radius-md);" required>
                                        <option value="json" selected>{{ __('settings.account_info.format_json') }}</option>
                                        <option value="xml">{{ __('settings.account_info.format_xml') }}</option>
                                        <option value="csv">{{ __('settings.account_info.format_csv') }}</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="d-flex align-items-center justify-content-between p-2 border rounded bg-light" style="height: 48px;">
                                    <span style="font-size: 0.85rem; font-weight: 600; color: var(--text-body);">{{ __('settings.account_info.selected_count_label') }}</span>
                                    <span class="badge badge-light-success px-3 py-1 font-weight-bold" style="font-size: 1rem;" id="selectedCount">0</span>
                                </div>
                            </div>
                        </div>

                        <div class="text-end mt-2">
                            <button type="submit" class="premium-btn premium-btn-primary" id="downloadBtn" style="width: auto; padding: 0 3rem;" disabled>
                                <i class="feather icon-download-cloud"></i>
                                {{ __('settings.account_info.download_backup') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: قريباً -->
    <div class="modal fade premium-modal" id="comingSoonModal" tabindex="-1" aria-labelledby="comingSoonModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-gradient-primary">
                    <h5 class="modal-title" id="comingSoonModalLabel">{{ __('settings.account_info.coming_soon') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center p-4">
                    <div class="coming-soon-icon mb-3">
                        <i class="feather icon-clock" style="font-size: 80px; color: #7367f0;"></i>
                    </div>
                    <h3 class="text-primary mb-3">{{ __('settings.account_info.coming_soon_title') }}</h3>
                    <p class="text-muted mb-4" style="font-size: 1rem;">
                        {{ __('settings.account_info.coming_soon_body') }}
                    </p>
                    <div class="alert alert-primary text-start" role="alert">
                        <i class="feather icon-info mr-1"></i>
                        <strong>{{ __('settings.account_info.thanks_label') }}</strong> {{ __('settings.account_info.thanks_body') }}
                    </div>
                </div>
                <div class="modal-footer justify-content-center">
                    <button type="button" class="premium-btn premium-btn-primary" style="width: auto; padding: 0 2rem;" data-bs-dismiss="modal">
                        <i class="feather icon-check"></i>
                        {{ __('settings.account_info.understood') }}
                    </button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <script>
        // عداد الجداول المحددة
        function updateSelectedCount() {
            const checkboxes = document.querySelectorAll('.backup-checkbox:checked');
            const count = checkboxes.length;
            document.getElementById('selectedCount').textContent = count;
            document.getElementById('downloadBtn').disabled = count === 0;
        }

        // تحديد/إلغاء تحديد الكل
        function toggleAll() {
            const checkboxes = document.querySelectorAll('.backup-checkbox');
            const allChecked = Array.from(checkboxes).every(cb => cb.checked);
            
            checkboxes.forEach(checkbox => {
                checkbox.checked = !allChecked;
            });
            
            updateSelectedCount();
        }

        document.addEventListener('DOMContentLoaded', function() {
            const checkboxes = document.querySelectorAll('.backup-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.addEventListener('change', updateSelectedCount);
            });

            if (typeof feather !== 'undefined') {
                feather.replace();
            }

            // تحديث العداد مبدئياً
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
