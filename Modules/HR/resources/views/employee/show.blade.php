@extends('hr::master')

@section('title')
    عرض الموظف
@stop

@section('css')
<link rel="stylesheet" href="{{ asset('assets/css/logs.css') }}">
<style>
    .employee-show .hero-card {
        border: 0;
        border-radius: 14px;
        box-shadow: 0 4px 20px rgba(34, 41, 47, 0.07);
        background: linear-gradient(135deg, var(--app-primary, #7367f0) 0%, color-mix(in srgb, var(--app-primary, #7367f0) 62%, #fff) 60%, color-mix(in srgb, var(--app-primary, #7367f0) 42%, #fff) 100%);
        color: #fff;
        overflow: hidden;
        position: relative;
        margin-bottom: 1.25rem;
    }
    .employee-show .hero-card::after {
        content: '';
        position: absolute;
        right: -80px; top: -80px;
        width: 240px; height: 240px;
        background: radial-gradient(circle, rgba(255,255,255,0.18) 0%, transparent 70%);
    }
    .employee-show .hero-card .card-body { padding: 1.5rem; position: relative; z-index: 1; }
    .employee-show .avatar-circle {
        width: 96px; height: 96px;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.18);
        backdrop-filter: blur(8px);
        color: #fff;
        font-size: 2.4rem;
        font-weight: 700;
        display: flex; align-items: center; justify-content: center;
        border: 3px solid rgba(255,255,255,0.35);
        flex-shrink: 0;
    }
    /* الصورةُ تملأ الدائرة نفسَها: الإطارُ واحدٌ سواءٌ حملت صورةً أم حرفاً،
       فلا يقفز التخطيطُ بين موظفٍ له صورةٌ وآخرَ بلا صورة */
    .employee-show .avatar-circle img {
        width: 100%; height: 100%;
        border-radius: 50%;
        object-fit: cover;
        display: block;
    }
    .employee-show .avatar-circle.has-photo { padding: 0; background: rgba(255, 255, 255, 0.28); }
    .employee-show .hero-name { font-size: 1.5rem; font-weight: 700; margin-bottom: 0.2rem; color: #fff; }
    .employee-show .hero-meta { color: rgba(255,255,255,0.88); font-size: 0.9rem; margin-bottom: 0.5rem; }
    .employee-show .hero-chips { display: flex; flex-wrap: wrap; gap: 0.4rem; }
    .employee-show .hero-chip {
        background: rgba(255,255,255,0.18);
        color: #fff;
        padding: 0.25rem 0.7rem;
        border-radius: 20px;
        font-size: 0.78rem;
        font-weight: 500;
        border: 1px solid rgba(255,255,255,0.25);
    }
    .employee-show .hero-chip i { margin-left: 0.35rem; }

    /* فروعُ الموظف في الترويسة — تظهر عند أكثر من فرع كما في التصميم */
    .employee-show .hero-branches {
        display: flex;
        flex-wrap: wrap;
        gap: 0.4rem;
        margin-top: 0.65rem;
    }
    .employee-show .hero-branch-pill {
        display: inline-flex;
        align-items: center;
        background: rgba(255,255,255,0.16);
        color: #fff;
        padding: 0.28rem 0.75rem;
        border-radius: 20px;
        font-size: 0.78rem;
        font-weight: 500;
        border: 1px solid rgba(255,255,255,0.22);
        line-height: 1.3;
    }
    .employee-show .hero-branch-pill i { margin-left: 0.35rem; font-size: 0.78rem; opacity: 0.9; }
    .employee-show .hero-branch-pill.is-primary {
        background: rgba(255,255,255,0.28);
        border-color: rgba(255,255,255,0.4);
        font-weight: 600;
    }

    .employee-show .hero-actions .btn {
        background: rgba(255,255,255,0.22);
        border: 1px solid rgba(255,255,255,0.30);
        color: #fff;
        font-size: 0.82rem;
        padding: 0.4rem 0.85rem;
        border-radius: 8px;
        margin-bottom: 0.4rem;
    }
    .employee-show .hero-actions .btn:hover { background: rgba(255,255,255,0.32); color: #fff; }
    .employee-show .hero-actions .btn i { margin-left: 0.35rem; }

    .employee-show .quick-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
        gap: 0.85rem;
        margin-top: 1.25rem;
    }
    .employee-show .stat-tile {
        background: rgba(255,255,255,0.12);
        border: 1px solid rgba(255,255,255,0.20);
        border-radius: 10px;
        padding: 0.75rem 0.9rem;
        color: #fff;
    }
    .employee-show .stat-tile .stat-label { font-size: 0.75rem; opacity: 0.85; }
    .employee-show .stat-tile .stat-value { font-size: 1.15rem; font-weight: 700; margin-top: 0.15rem; }
    .employee-show .stat-tile .stat-value i { font-size: 0.95rem; opacity: 0.85; margin-left: 0.3rem; }

    .employee-show .quick-actions-row {
        background: var(--app-surface, #fff);
        border-radius: 12px;
        padding: 0.85rem 1rem;
        box-shadow: 0 2px 12px rgba(34, 41, 47, 0.06);
        margin-bottom: 1.25rem;
        display: flex; flex-wrap: wrap; gap: 0.5rem;
    }
    .employee-show .quick-actions-row .btn { font-size: 0.85rem; padding: 0.45rem 0.85rem; border-radius: 8px; font-weight: 500; }
    .employee-show .quick-actions-row .btn i { margin-left: 0.35rem; }

    /* تبويبات المحتوى */
    .employee-show .emp-tabs-card {
        border: 0;
        border-radius: 12px;
        box-shadow: 0 2px 12px rgba(34, 41, 47, 0.06);
        overflow: hidden;
        margin-bottom: 1.25rem;
    }
    .employee-show .emp-tabs {
        display: flex;
        flex-wrap: wrap;
        gap: 0.15rem;
        padding: 0.65rem 0.85rem 0;
        background: linear-gradient(180deg, color-mix(in srgb, var(--app-primary, #7367f0) 5%, var(--app-surface, #fff)) 0%, var(--app-surface, #fff) 100%);
        border-bottom: 1px solid var(--app-border, #ebe9f1);
        margin: 0;
        list-style: none;
    }
    .employee-show .emp-tabs .nav-item { margin: 0; }
    .employee-show .emp-tabs .nav-link {
        border: 0;
        border-radius: 8px 8px 0 0;
        color: var(--app-muted, #5e5873);
        font-weight: 500;
        font-size: 0.88rem;
        padding: 0.65rem 1rem;
        background: transparent;
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        white-space: nowrap;
    }
    .employee-show .emp-tabs .nav-link i { font-size: 0.95rem; opacity: 0.85; }
    .employee-show .emp-tabs .nav-link:hover {
        color: var(--app-primary, #7367f0);
        background: color-mix(in srgb, var(--app-primary, #7367f0) 6%, transparent);
    }
    .employee-show .emp-tabs .nav-link.active {
        color: var(--app-primary, #7367f0);
        background: var(--app-surface, #fff);
        box-shadow: 0 -1px 0 var(--app-surface, #fff), 0 1px 0 var(--app-border, #ebe9f1) inset;
        font-weight: 600;
        position: relative;
    }
    .employee-show .emp-tabs .nav-link.active::after {
        content: '';
        position: absolute;
        right: 12px; left: 12px; bottom: -1px;
        height: 2px;
        background: var(--app-primary, #7367f0);
        border-radius: 2px 2px 0 0;
    }
    .employee-show .emp-tabs .nav-link .tab-count {
        background: color-mix(in srgb, var(--app-primary, #7367f0) 12%, transparent);
        color: var(--app-primary, #7367f0);
        font-size: 0.72rem;
        font-weight: 600;
        padding: 0.1rem 0.45rem;
        border-radius: 10px;
        min-width: 1.4rem;
        text-align: center;
    }
    .employee-show .emp-tabs .nav-link.active .tab-count {
        background: var(--app-primary, #7367f0);
        color: #fff;
    }
    .employee-show .emp-tab-content {
        padding: 1.15rem 1.25rem 1.35rem;
        background: var(--app-surface, #fff);
    }
    .employee-show .emp-tab-content .info-card {
        box-shadow: none;
        margin-bottom: 1rem;
    }
    .employee-show .emp-tab-content .info-card:last-child { margin-bottom: 0; }

    .employee-show .info-card {
        border: 0;
        border-radius: 12px;
        box-shadow: 0 2px 12px rgba(34, 41, 47, 0.06);
        margin-bottom: 1.25rem;
        overflow: hidden;
        border-right: 4px solid var(--app-primary, #7367f0);
    }
    .employee-show .info-card .card-header {
        background: linear-gradient(90deg, color-mix(in srgb, var(--app-primary, #7367f0) 6%, var(--app-surface, #fff)) 0%, var(--app-surface, #ffffff) 100%);
        border-bottom: 1px solid var(--app-border, #ebe9f1);
        padding: 0.85rem 1.15rem !important;
        display: flex; align-items: center;
        font-weight: 600 !important;
        font-size: 0.95rem;
        color: var(--app-muted, #5e5873);
    }
    .employee-show .info-card .section-icon {
        width: 32px; height: 32px;
        border-radius: 8px;
        background: rgba(115, 103, 240, 0.12);
        color: var(--app-primary, #7367f0);
        display: inline-flex; align-items: center; justify-content: center;
        margin-left: 0.6rem;
        font-size: 0.95rem;
    }
    .employee-show .info-card .card-body { padding: 1.1rem 1.25rem; }

    .employee-show .accent-info     { border-right-color: var(--app-info, #00cfe8); }
    .employee-show .accent-info .section-icon     { background: rgba(0,207,232,0.12); color: var(--app-info, #00cfe8); }
    .employee-show .accent-personal { border-right-color: var(--app-warning, #ff9f43); }
    .employee-show .accent-personal .section-icon { background: rgba(255,159,67,0.12); color: var(--app-warning, #ff9f43); }
    .employee-show .accent-contact  { border-right-color: var(--app-success, #28c76f); }
    .employee-show .accent-contact .section-icon  { background: rgba(40,199,111,0.12); color: var(--app-success, #28c76f); }
    .employee-show .accent-address  { border-right-color: var(--app-danger, #ea5455); }
    .employee-show .accent-address .section-icon  { background: rgba(234,84,85,0.12); color: var(--app-danger, #ea5455); }
    .employee-show .accent-job      { border-right-color: var(--app-primary, #7367f0); }
    .employee-show .accent-finance  { border-right-color: #f6b500; }
    .employee-show .accent-finance .section-icon  { background: rgba(246,181,0,0.14); color: #f6b500; }
    .employee-show .accent-files    { border-right-color: var(--app-info, #00cfe8); }
    .employee-show .accent-files .section-icon    { background: rgba(0,207,232,0.12); color: var(--app-info, #00cfe8); }
    .employee-show .accent-activity { border-right-color: var(--app-muted, #82868b); }
    .employee-show .accent-activity .section-icon { background: rgba(130,134,139,0.18); color: var(--app-muted, #5e5873); }

    .employee-show .info-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 0.6rem 1.25rem;
    }
    .employee-show .info-item { padding: 0.4rem 0; border-bottom: 1px dashed #f0eef7; }
    .employee-show .info-item:last-child { border-bottom: 0; }
    .employee-show .info-label { color: var(--app-muted, #82868b); font-size: 0.78rem; margin-bottom: 0.15rem; }
    .employee-show .info-value { color: var(--app-muted, #5e5873); font-weight: 500; font-size: 0.92rem; word-break: break-word; }
    .employee-show .info-value .badge { font-size: 0.72rem; }

    .employee-show .badge-soft-success { background: rgba(40,199,111,0.14); color: var(--app-success, #28c76f); }
    .employee-show .badge-soft-danger  { background: rgba(234,84,85,0.14);  color: var(--app-danger, #ea5455); }
    .employee-show .badge-soft-info    { background: rgba(0,207,232,0.14);  color: var(--app-info, #00cfe8); }
    .employee-show .badge-soft-warning { background: rgba(255,159,67,0.14); color: var(--app-warning, #ff9f43); }
    .employee-show .badge-soft-primary { background: rgba(115,103,240,0.14); color: var(--app-primary, #7367f0); }

    .employee-show .branch-pill {
        display: inline-flex; align-items: center;
        background: rgba(0,207,232,0.10);
        color: #00a4be;
        padding: 0.25rem 0.65rem;
        border-radius: 16px;
        font-size: 0.8rem;
        margin: 0.15rem 0.2rem 0.15rem 0;
    }
    .employee-show .branch-pill i { margin-left: 0.3rem; font-size: 0.75rem; }
    .employee-show .branch-pill.is-primary {
        background: rgba(115,103,240,0.12);
        color: var(--app-primary, #7367f0);
        font-weight: 600;
    }

    .employee-show .account-card {
        background: linear-gradient(135deg, color-mix(in srgb, var(--app-warning, #f59e0b) 12%, var(--app-surface, #fff)) 0%, var(--app-surface, #ffffff) 100%);
        border: 1px solid rgba(246,181,0,0.30);
        border-radius: 10px;
        padding: 0.8rem 1rem;
        margin-bottom: 0.65rem;
    }
    .employee-show .account-card.is-asset {
        background: linear-gradient(135deg, color-mix(in srgb, var(--app-success, #10b981) 10%, var(--app-surface, #fff)) 0%, var(--app-surface, #ffffff) 100%);
        border-color: rgba(40,199,111,0.30);
    }
    .employee-show .account-card .acc-name { font-weight: 600; color: var(--app-muted, #5e5873); }
    .employee-show .account-card .acc-meta { font-size: 0.78rem; color: var(--app-muted, #82868b); margin-top: 0.15rem; }

    .employee-show .att-list { list-style: none; padding: 0; margin: 0; }
    .employee-show .att-list li {
        display: flex; align-items: center;
        background: var(--app-surface, #fff);
        border: 1px solid var(--app-border, #ebe9f1);
        border-radius: 10px;
        padding: 0.65rem 0.9rem;
        margin-bottom: 0.5rem;
        transition: border-color 0.15s, box-shadow 0.15s;
    }
    .employee-show .att-list li:hover { border-color: rgba(115,103,240,0.35); box-shadow: 0 2px 10px rgba(34,41,47,0.05); }
    .employee-show .att-list .file-icon {
        width: 38px; height: 38px;
        border-radius: 9px;
        display: inline-flex; align-items: center; justify-content: center;
        margin-left: 0.7rem;
        font-size: 1.05rem; flex-shrink: 0;
    }
    .employee-show .att-list .file-icon.icon-image  { background: rgba(40,199,111,0.12); color: var(--app-success, #28c76f); }
    .employee-show .att-list .file-icon.icon-pdf    { background: rgba(234,84,85,0.12);  color: var(--app-danger, #ea5455); }
    .employee-show .att-list .file-icon.icon-word   { background: rgba(0,207,232,0.12);  color: var(--app-info, #00cfe8); }
    .employee-show .att-list .file-icon.icon-excel  { background: rgba(40,199,111,0.12); color: var(--app-success, #28c76f); }
    .employee-show .att-list .file-icon.icon-other  { background: rgba(115,103,240,0.12); color: var(--app-primary, #7367f0); }
    .employee-show .att-list .file-name { font-weight: 500; color: var(--app-muted, #5e5873); word-break: break-all; font-size: 0.9rem; }
    .employee-show .att-list .file-meta { color: var(--app-muted, #82868b); font-size: 0.78rem; margin-top: 0.15rem; }

    .employee-show .empty-block {
        text-align: center;
        color: var(--app-muted, #82868b);
        padding: 1.25rem 0.5rem;
        font-size: 0.88rem;
    }
    .employee-show .empty-block i { font-size: 1.6rem; opacity: 0.55; margin-bottom: 0.4rem; display: block; }
</style>
@endsection

@section('content')
@php
    $arabic_countries = [
        1=>'المملكة العربية السعودية',2=>'الإمارات العربية المتحدة',3=>'الكويت',4=>'قطر',
        5=>'البحرين',6=>'سلطنة عمان',7=>'مصر',8=>'الأردن',9=>'لبنان',10=>'سوريا',
        11=>'العراق',12=>'فلسطين',13=>'اليمن',14=>'الجزائر',15=>'المغرب',16=>'تونس',
        17=>'ليبيا',18=>'السودان',19=>'موريتانيا',20=>'جيبوتي',21=>'الصومال',22=>'جزر القمر',
    ];
    $months_ar = [1=>'يناير',2=>'فبراير',3=>'مارس',4=>'أبريل',5=>'مايو',6=>'يونيو',7=>'يوليو',8=>'أغسطس',9=>'سبتمبر',10=>'أكتوبر',11=>'نوفمبر',12=>'ديسمبر'];

    $firstLetter = mb_substr($employee->full_name ?: '?', 0, 1, 'UTF-8');
    $hireDays = $employee->hire_date ? \Carbon\Carbon::parse($employee->hire_date)->diffInDays(now()) : null;
    $age = $employee->date_of_birth ? \Carbon\Carbon::parse($employee->date_of_birth)->age : null;

    // مشروطةٌ: عرضُ البطاقة مرّتين في طلبٍ واحد (أو دالّةٌ عامّة بالاسم نفسه) يُسقطها بإعادة التعريف
    if (! function_exists('fileIconClasses')) {
    function fileIconClasses($name, $mime) {
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (str_starts_with((string)$mime, 'image/') || in_array($ext, ['png','jpg','jpeg','gif','webp','bmp','svg'])) return ['icon-image','fa-image'];
        if ($ext === 'pdf') return ['icon-pdf','fa-file-pdf-o'];
        if (in_array($ext, ['doc','docx'])) return ['icon-word','fa-file-word-o'];
        if (in_array($ext, ['xls','xlsx','csv'])) return ['icon-excel','fa-file-excel-o'];
        return ['icon-other','fa-file-o'];
    }
    }
    if (! function_exists('formatBytes')) {
    function formatBytes($bytes) {
        if (!$bytes) return '0 B';
        if ($bytes < 1024) return $bytes . ' B';
        if ($bytes < 1024*1024) return number_format($bytes/1024, 1) . ' KB';
        return number_format($bytes/1024/1024, 2) . ' MB';
    }
    }
@endphp

{{-- إجراءاتُ الموظف في الترويسة بترتيب شاشات العرض كلّها:
     تعديل ← الوثائق ← المزيد (الحساب والدخول، ثمّ الحالة، والإجراءاتُ الخطِرة آخرها) --}}
@section('page-actions')
    @php
        $empCanEdit = hasPermission('employees.edit');
        $empCanDestroy = hasPermission('employees.destroy');
        $empCanPassword = hasPermission('employee_accounts.password');
        $empCanAccess = hasPermission('employee_accounts.access');
        $empCanTerminate = hasPermission('employees.terminate') && ! $employee->hasEndedService();
        $empCanToggle = $empCanEdit && ! $employee->hasEndedService();
        $empHasAccountItems = $empCanPassword || $empCanAccess;
        $empHasStatusItems = $empCanToggle || $empCanTerminate || $empCanDestroy;
    @endphp
    @if ($empCanEdit)
        <a href="{{ route('employee.edit', $employee->id) }}" class="btn btn-primary">
            <i class="fas fa-edit"></i> تعديل
        </a>
    @endif
    @permission('employee_documents.index')
        <a href="{{ route('employee.documents.index', $employee->id) }}" class="btn btn-outline-primary">
            <i class="fas fa-file-alt"></i> الوثائق
        </a>
    @endpermission
    {{-- «المزيد» لا يظهر فارغاً: كلُّ بندٍ فيه بصلاحيته --}}
    @if ($empHasAccountItems || $empHasStatusItems)
    <div class="btn-group">
        <button type="button" class="btn btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
            <i class="fas fa-ellipsis-h"></i> المزيد
        </button>
        <ul class="dropdown-menu dropdown-menu-end">
            @if ($empHasAccountItems)
                <li><h6 class="dropdown-header">الحساب والدخول</h6></li>
            @endif
            @if ($empCanPassword)
                <li>
                    <a class="dropdown-item" href="#" data-toggle="modal" data-target="#ChangePassword">
                        <i class="fas fa-lock me-2 text-info"></i>تغيير كلمة المرور
                    </a>
                </li>
                @if ($user)
                    <li>
                        <a class="dropdown-item" href="{{ route('employee.send_email', $employee->id) }}">
                            <i class="fas fa-paper-plane me-2 text-success"></i>إرسال بيانات الدخول
                        </a>
                    </li>
                @endif
            @endif
            @if ($empCanAccess)
                <li>
                    <a class="dropdown-item" href="{{ route('employee.toggleSystemAccess', $employee->id) }}">
                        <i class="fas {{ $employee->allow_system_access ? 'fa-lock text-warning' : 'fa-unlock text-success' }} me-2"></i>{{ $employee->allow_system_access ? 'تعطيل الدخول للنظام' : 'تفعيل الدخول للنظام' }}
                    </a>
                </li>
            @endif
            {{-- زرُّ التبديل وإنهاءُ الخدمة يختفيان لمن انتهت خدمتُه: العودةُ توظيفٌ جديد لا نقرة --}}
            @if ($empCanToggle && $employee->isActive() === false)
                <li><hr class="dropdown-divider"></li>
                <li>
                    <a class="dropdown-item" href="{{ route('employee.updateStatus', $employee->id) }}">
                        <i class="fas fa-check me-2 text-success"></i>تفعيل الموظف
                    </a>
                </li>
            @endif
            @if (($empCanToggle && $employee->isActive()) || $empCanTerminate || $empCanDestroy)
                <li><hr class="dropdown-divider"></li>
                @if ($empCanToggle && $employee->isActive())
                    <li>
                        <a class="dropdown-item text-danger" href="{{ route('employee.updateStatus', $employee->id) }}">
                            <i class="fas fa-ban me-2"></i>تعطيل الموظف
                        </a>
                    </li>
                @endif
                @if ($empCanTerminate)
                    <li>
                        <button type="button" class="dropdown-item text-danger" data-toggle="modal" data-target="#terminateEmployeeModal">
                            <i class="fas fa-user-times me-2"></i>إنهاء الخدمة
                        </button>
                    </li>
                @endif
                {{-- يحذف حقّاً، ويقول المودالُ قبل التأكيد إن كان الحذفُ ممكناً أصلاً --}}
                @if ($empCanDestroy)
                    <li>
                        <a class="dropdown-item text-danger" href="#" data-toggle="modal" data-target="#modal_DELETE{{ $employee->id }}">
                            <i class="fas fa-trash me-2"></i>حذف
                        </a>
                    </li>
                @endif
            @endif
        </ul>
    </div>
    @endif
@endsection

{{-- ترويسةُ نظام التصميم — كانت مكتوبةً يدوياً بستّة مستويات
     من `div` وفتاتِ تنقّلٍ رابطُه الأوّل فارغ. --}}
@include('stock::components.page-head', [
    'title' => 'الموظفين',
    'crumbs' => ['الموظفين' => route('employee.index'), 'عرض' => null],
])

<div class="content-body employee-show">
    @include('layouts.alerts.error')
    @include('layouts.alerts.success')
    {{-- كان القالبان أعلاه وحدهما، فرسالةُ «حُفِظ الموظف لكن لم يخرج
         البريد» تُبعَث ولا تُعرَض — تُقرأ العمليةُ نجاحاً تامّاً. --}}
    @include('layouts.alerts.warning')

    <div class="container" style="max-width: 1280px">

        {{-- ===== Hero Card ===== --}}
        <div class="card hero-card">
            <div class="card-body">
                <div class="d-flex flex-wrap align-items-center" style="gap: 1.25rem;">
                    {{-- الصورةُ إن رُفعت، وإلّا حرفُ الاسم الأوّل كما كان.
                         الشاشةُ كانت تعرض الحرفَ دائماً ولا تنظر إلى
                         `employee_photo` إطلاقاً، فلا تظهر صورةٌ مهما رُفعت. --}}
                    <div class="avatar-circle {{ $employee->employee_photo ? 'has-photo' : '' }}">
                        @if ($employee->employee_photo)
                            <img src="{{ asset('assets/uploads/employee/'.$employee->employee_photo) }}"
                                 alt="صورة {{ $employee->full_name }}">
                        @else
                            {{ $firstLetter }}
                        @endif
                    </div>
                    <div class="flex-grow-1">
                        <div class="hero-name">{{ $employee->full_name }}</div>
                        <div class="hero-meta">
                            <i class="fa fa-id-badge ml-1"></i> #{{ $employee->id }}
                            <span class="mx-2">·</span>
                            <i class="fa fa-clock-o ml-1"></i> {{ $employee->created_at?->format('d M Y') }}
                            @if($employee->job_title)
                                <span class="mx-2">·</span>
                                <i class="feather icon-briefcase ml-1"></i> {{ $employee->job_title->name ?? '' }}
                            @endif
                        </div>
                        <div class="hero-chips">
                            <span class="hero-chip"><i class="feather icon-user"></i>{{ $employee->employee_type == 1 ? 'موظف' : 'مستخدم' }}</span>
                            {{-- الحالةُ من [\App\Enums\EmploymentStatus]: كانت تُقرأ «نشط» أو
                                 «غير نشط» فقط، فيستوي الموقوفُ والمستقيلُ ومنتهي الخدمة --}}
                            @php
                                $employmentStatus = $employee->employmentStatus();
                            @endphp
                            <span class="hero-chip">
                                <i class="fa {{ $employmentStatus->isActive() ? 'fa-check-circle' : 'fa-ban' }}"></i>
                                {{ $employmentStatus->label() }}
                            </span>
                            @if ($employee->termination_date)
                                <span class="hero-chip">
                                    <i class="feather icon-calendar"></i>
                                    انتهت الخدمة: {{ $employee->termination_date->format('Y-m-d') }}
                                </span>
                            @endif
                            @if (canAny(['eos.show', 'eos.create']) && $employee->hasEndedService())
                                @php $eos = $employee->endOfServiceBenefits()->latest('id')->first(); @endphp
                                @if ($eos && hasPermission('eos.show'))
                                    <a class="hero-chip" href="{{ route('hr.eos.show', $eos->id) }}">مكافأة نهاية الخدمة</a>
                                @elseif (! $eos && hasPermission('eos.create'))
                                    <form action="{{ route('hr.eos.store') }}" method="POST" class="d-inline">
                                        @csrf
                                        <input type="hidden" name="employee_id" value="{{ $employee->id }}">
                                        <button type="submit" class="hero-chip" style="border:0">حساب مكافأة نهاية الخدمة</button>
                                    </form>
                                @endif
                            @endif
                            @if($employee->job_role)
                                <span class="hero-chip"><i class="feather icon-shield"></i>{{ $employee->job_role->name ?? '' }}</span>
                            @elseif($employee->job_title)
                                <span class="hero-chip"><i class="feather icon-briefcase"></i>{{ $employee->job_title->name ?? '' }}</span>
                            @endif
                        </div>

                        {{-- عند أكثر من فرع: شريطُ أوسمةٍ بمسامير المواقع كما في التصميم --}}
                        @if($employeeBranches->count() > 1)
                            <div class="hero-branches" aria-label="فروع الموظف">
                                @foreach($employeeBranches as $b)
                                    @php
                                        $isDefault = $primaryBranch && (int) $primaryBranch->id === (int) $b->id;
                                    @endphp
                                    <span class="hero-branch-pill {{ $isDefault ? 'is-primary' : '' }}">
                                        <i class="feather icon-map-pin"></i>{{ $b->name }}
                                    </span>
                                @endforeach
                            </div>
                        @elseif($employeeBranches->count() === 1)
                            <div class="hero-branches">
                                <span class="hero-branch-pill is-primary">
                                    <i class="feather icon-map-pin"></i>{{ $employeeBranches->first()->name }}
                                </span>
                            </div>
                        @endif
                    </div>
                    <div class="hero-actions text-md-left">
                        @if($employee->email)
                            <a href="mailto:{{ $employee->email }}" class="btn btn-sm"><i class="feather icon-mail"></i>{{ $employee->email }}</a><br>
                        @endif
                        @if($employee->mobile_number || $employee->phone_number)
                            <a href="tel:{{ $employee->mobile_number ?: $employee->phone_number }}" class="btn btn-sm"><i class="feather icon-phone"></i>{{ $employee->mobile_number ?: $employee->phone_number }}</a><br>
                        @endif
                        @if($user)
                            @permission('employee_accounts.login_as')
                                <a href="{{ route('employee.login_to', $user->id) }}" class="btn btn-sm"><i class="fa fa-sign-in"></i>تسجيل الدخول كهذا الموظف</a>
                            @endpermission
                        @endif
                    </div>
                </div>

                <div class="quick-stats">
                    <div class="stat-tile">
                        <div class="stat-label">المرفقات</div>
                        <div class="stat-value">{{ $employee->attachments->count() }} <i class="fa fa-paperclip"></i></div>
                    </div>
                    <div class="stat-tile">
                        <div class="stat-label">أيام منذ التوظيف</div>
                        <div class="stat-value">{{ $hireDays !== null ? number_format($hireDays) : '—' }} <i class="feather icon-calendar"></i></div>
                    </div>
                    <div class="stat-tile">
                        <div class="stat-label">العمر</div>
                        <div class="stat-value">{{ $age !== null ? $age . ' سنة' : '—' }} <i class="feather icon-gift"></i></div>
                    </div>
                    <div class="stat-tile">
                        <div class="stat-label">فروع الوصول</div>
                        <div class="stat-value">{{ $accessBranchesCount ?? ($employeeBranches->count() + $allowedBranches->count()) }} <i class="feather icon-map-pin"></i></div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ===== تبويبات المحتوى ===== --}}
        <div class="card emp-tabs-card">
            <ul class="nav emp-tabs" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" id="tab-overview" data-toggle="tab" href="#pane-overview" role="tab" aria-controls="pane-overview" aria-selected="true">
                        <i class="feather icon-info"></i> عام
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="tab-personal" data-toggle="tab" href="#pane-personal" role="tab" aria-controls="pane-personal" aria-selected="false">
                        <i class="feather icon-user"></i> شخصي
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="tab-job" data-toggle="tab" href="#pane-job" role="tab" aria-controls="pane-job" aria-selected="false">
                        <i class="feather icon-briefcase"></i> الوظيفة
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="tab-branches" data-toggle="tab" href="#pane-branches" role="tab" aria-controls="pane-branches" aria-selected="false">
                        <i class="feather icon-git-branch"></i> الفروع
                        <span class="tab-count">{{ $employeeBranches->count() + $allowedBranches->count() }}</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="tab-finance" data-toggle="tab" href="#pane-finance" role="tab" aria-controls="pane-finance" aria-selected="false">
                        <i class="fa fa-money"></i> المالي
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="tab-files" data-toggle="tab" href="#pane-files" role="tab" aria-controls="pane-files" aria-selected="false">
                        <i class="fa fa-paperclip"></i> المرفقات
                        <span class="tab-count">{{ $employee->attachments->count() }}</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="tab-activity" data-toggle="tab" href="#pane-activity" role="tab" aria-controls="pane-activity" aria-selected="false">
                        <i class="feather icon-rotate-ccw"></i> النشاطات
                    </a>
                </li>
            </ul>

            <div class="tab-content emp-tab-content">

                {{-- عام --}}
                <div class="tab-pane fade show active" id="pane-overview" role="tabpanel" aria-labelledby="tab-overview">
                    <div class="card info-card accent-info">
                        <div class="stk-table-head">
                            <span class="section-icon"><i class="feather icon-info"></i></span> معلومات عامة
                        </div>
                        <div class="card-body">
                            <div class="info-grid">
                                <div class="info-item">
                                    <div class="info-label">البريد الإلكتروني</div>
                                    <div class="info-value">{{ $employee->email ?: '—' }}</div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">الجوال</div>
                                    <div class="info-value">{{ $employee->mobile_number ?: $employee->phone_number ?: '—' }}</div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">الدور الوظيفي</div>
                                    <div class="info-value">{{ $employee->job_role->name ?? '—' }}</div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">لغة العرض</div>
                                    <div class="info-value">{{ $employee->language == 1 ? 'العربية' : 'الإنجليزية' }}</div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">السماح بالدخول للنظام</div>
                                    <div class="info-value">
                                        <span class="badge {{ $employee->allow_system_access ? 'badge-soft-success' : 'badge-soft-danger' }}">
                                            {{ $employee->allow_system_access ? 'مفعّل' : 'معطّل' }}
                                        </span>
                                    </div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">إرسال بيانات الدخول للبريد</div>
                                    <div class="info-value">
                                        <span class="badge {{ $employee->send_credentials ? 'badge-soft-success' : 'badge-soft-danger' }}">
                                            {{ $employee->send_credentials ? 'سبق الإرسال' : 'لم تُرسَل' }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card info-card accent-contact">
                        <div class="stk-table-head">
                            <span class="section-icon"><i class="feather icon-phone-call"></i></span> جهة الطوارئ
                        </div>
                        <div class="card-body">
                            @if ($employee->emergency_contact_name || $employee->emergency_contact_phone)
                                <div class="info-grid">
                                    <div class="info-item">
                                        <div class="info-label">الاسم</div>
                                        <div class="info-value">{{ $employee->emergency_contact_name ?: '—' }}</div>
                                    </div>
                                    <div class="info-item">
                                        <div class="info-label">الهاتف</div>
                                        <div class="info-value">{{ $employee->emergency_contact_phone ?: '—' }}</div>
                                    </div>
                                    <div class="info-item">
                                        <div class="info-label">صلة القرابة</div>
                                        <div class="info-value">{{ $employee->emergency_contact_relation ?: '—' }}</div>
                                    </div>
                                </div>
                            @else
                                <div class="empty-block"><i class="feather icon-phone-off"></i> لم تُسجَّل جهةُ اتصالٍ للطوارئ.</div>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- شخصي --}}
                <div class="tab-pane fade" id="pane-personal" role="tabpanel" aria-labelledby="tab-personal">
                    <div class="card info-card accent-personal">
                        <div class="stk-table-head">
                            <span class="section-icon"><i class="feather icon-user"></i></span> البيانات الشخصية
                        </div>
                        <div class="card-body">
                            <div class="info-grid">
                                <div class="info-item">
                                    <div class="info-label">البلد</div>
                                    <div class="info-value">{{ $arabic_countries[$employee->country] ?? '—' }}</div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">حالة الموطنة</div>
                                    <div class="info-value">
                                        @php $ns = $employee->nationality_status; @endphp
                                        {{ $ns == 1 ? 'مواطن' : ($ns == 2 ? 'مقيم' : 'زائر') }}
                                    </div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">تاريخ الميلاد</div>
                                    <div class="info-value">{{ $employee->date_of_birth ? \Carbon\Carbon::parse($employee->date_of_birth)->format('Y-m-d') : '—' }}</div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">العمر</div>
                                    <div class="info-value">{{ $age !== null ? $age . ' سنة' : '—' }}</div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">النوع</div>
                                    <div class="info-value">{{ $employee->gender == 1 ? 'ذكر' : 'أنثى' }}</div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">البريد الشخصي</div>
                                    <div class="info-value">{{ $employee->personal_email ?: '—' }}</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card info-card accent-address">
                        <div class="stk-table-head">
                            <span class="section-icon"><i class="feather icon-map-pin"></i></span> العنوان
                        </div>
                        <div class="card-body">
                            <div class="info-grid">
                                <div class="info-item">
                                    <div class="info-label">العنوان الأول</div>
                                    <div class="info-value">{{ $employee->current_address_1 ?: '—' }}</div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">العنوان الثاني</div>
                                    <div class="info-value">{{ $employee->current_address_2 ?: '—' }}</div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">المدينة</div>
                                    <div class="info-value">{{ $employee->city ?: '—' }}</div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">المنطقة</div>
                                    <div class="info-value">{{ $employee->region ?: '—' }}</div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">الرمز البريدي</div>
                                    <div class="info-value">{{ $employee->postal_code ?: '—' }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- الوظيفة --}}
                <div class="tab-pane fade" id="pane-job" role="tabpanel" aria-labelledby="tab-job">
                    <div class="card info-card accent-job">
                        <div class="stk-table-head">
                            <span class="section-icon"><i class="feather icon-briefcase"></i></span> بيانات الوظيفة
                        </div>
                        <div class="card-body">
                            <div class="info-grid">
                                <div class="info-item">
                                    <div class="info-label">القسم</div>
                                    <div class="info-value">{{ $employee->department->name ?? '—' }}</div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">المسمى الوظيفي</div>
                                    <div class="info-value">{{ $employee->job_title->name ?? '—' }}</div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">المستوى الوظيفي</div>
                                    <div class="info-value">{{ $employee->job_level->name ?? '—' }}</div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">نوع الوظيفة</div>
                                    <div class="info-value">{{ $employee->job_type->name ?? '—' }}</div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">المدير المباشر</div>
                                    <div class="info-value">{{ $employee->direct_manager?->full_name ?? '—' }}</div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">تاريخ التوظيف</div>
                                    <div class="info-value">{{ $employee->hire_date ? \Carbon\Carbon::parse($employee->hire_date)->format('Y-m-d') : '—' }}</div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">التاريخ المالي</div>
                                    <div class="info-value">
                                        @if($employee->custom_financial_month || $employee->custom_financial_day)
                                            {{ ($employee->custom_financial_day ?? '?') }} - {{ $months_ar[$employee->custom_financial_month] ?? '?' }}
                                        @else
                                            افتراضي
                                        @endif
                                    </div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">سقف الائتمان</div>
                                    <div class="info-value">{{ $employee->credit_limit !== null ? \App\Support\CurrencySettings::format($employee->credit_limit) : '—' }}</div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">حد التبديل اليومي</div>
                                    <div class="info-value">{{ $employee->daily_qty_limit ?? '—' }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- الفروع --}}
                <div class="tab-pane fade" id="pane-branches" role="tabpanel" aria-labelledby="tab-branches">
                    <div class="card info-card accent-contact">
                        <div class="stk-table-head">
                            <span class="section-icon"><i class="feather icon-git-branch"></i></span> الفروع والصلاحيات
                        </div>
                        <div class="card-body">
                            <div class="info-label">حساب الدخول</div>
                            <div class="mb-2">
                                @if($user)
                                    <span class="branch-pill"><i class="feather icon-mail"></i>{{ $user->email }}</span>
                                @else
                                    <span class="text-danger small">
                                        <i class="feather icon-alert-triangle"></i>
                                        لا يوجد حساب دخول مرتبط بهذا الموظف — لن يستطيع تسجيل الدخول إطلاقاً.
                                    </span>
                                @endif
                            </div>

                            <div class="info-label mt-2">الدور وصلاحياته</div>
                            <div class="mb-2">
                                @if($user && $loginRole)
                                    <span class="branch-pill is-primary"><i class="feather icon-shield"></i>{{ $loginRole->name }}</span>
                                    @if($loginPermissionsCount > 0)
                                        <span class="branch-pill"><i class="feather icon-check-circle"></i>{{ $loginPermissionsCount }} صلاحية</span>
                                    @else
                                        <div class="text-danger small mt-1">
                                            <i class="feather icon-alert-triangle"></i>
                                            هذا الدور لا يحمل أي صلاحية، فكل شاشة سترفض الموظف.
                                            امنح الدور صلاحياته من الإعدادات ← المستخدمون والأدوار.
                                        </div>
                                    @endif
                                @elseif($user)
                                    <div class="text-danger small">
                                        <i class="feather icon-alert-triangle"></i>
                                        حسابُ الدخول بلا دور — سيرى «ليس لديك صلاحية» في كل شاشة
                                        مهما كان «الدور الوظيفي» أعلاه. افتح «تعديل» واحفظ الموظف
                                        ليُسنَد الدورُ إلى حسابه.
                                    </div>
                                @endif

                                @if($user && $loginRole && $employee->Job_role_id && (int) $employee->Job_role_id !== (int) $loginRole->id)
                                    <div class="text-warning small mt-1">
                                        <i class="feather icon-alert-circle"></i>
                                        الدور الوظيفي في ملفّ الموظف يخالف دور حساب الدخول
                                        ({{ $employee->job_role->name ?? $employee->Job_role_id }}) — احفظ التعديل لتوحيدهما.
                                    </div>
                                @endif
                            </div>

                            <div class="info-label mt-2">فروع الموظف ({{ $employeeBranches->count() }})</div>
                            <div class="mb-2">
                                @forelse($employeeBranches as $b)
                                    @php
                                        $isDefault = $primaryBranch && (int) $primaryBranch->id === (int) $b->id;
                                    @endphp
                                    <span class="branch-pill {{ $isDefault ? 'is-primary' : '' }}">
                                        <i class="feather {{ $isDefault ? 'icon-star' : 'icon-map-pin' }}"></i>{{ $b->name }}{{ $isDefault ? ' (الافتراضي)' : '' }}
                                    </span>
                                @empty
                                    <span class="text-muted">— غير محدد —</span>
                                @endforelse
                            </div>
                            @if($employeeBranches->count() > 1)
                                <div class="text-muted small mb-2">
                                    <i class="feather icon-info"></i>
                                    يبدّل الموظف بين فروعه من مبدّل الفروع في الشريط العلوي، والافتراضي هو ما يفتح عليه بعد الدخول.
                                </div>
                            @endif
                            <div class="info-label mt-2">المواقع التابعة لفروعه ({{ $allowedBranches->count() }})</div>
                            <div>
                                @forelse($allowedBranches as $b)
                                    <span class="branch-pill"><i class="feather icon-link"></i>{{ $b->name }}</span>
                                @empty
                                    <span class="text-muted small">لا توجد مواقع تابعة.</span>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>

                {{-- المالي --}}
                <div class="tab-pane fade" id="pane-finance" role="tabpanel" aria-labelledby="tab-finance">
                    @if($liabilityAccount || $advanceAccount || $employee->treasuryEmployee || $defaultStorehouses->count() > 0)
                        <div class="card info-card accent-finance">
                            <div class="stk-table-head">
                                <span class="section-icon"><i class="fa fa-money"></i></span> الحسابات والربط المالي
                            </div>
                            <div class="card-body">
                                @if($liabilityAccount)
                                    <div class="account-card">
                                        <div class="acc-name"><i class="fa fa-file-text-o ml-1"></i> حساب الموظف (الخصوم)</div>
                                        <div class="acc-meta">
                                            {{ $liabilityAccount->name }} ·
                                            كود: <strong>{{ $liabilityAccount->code }}</strong> ·
                                            رصيد: {{ number_format($liabilityAccount->balance, 2) }}
                                        </div>
                                    </div>
                                @endif
                                @if($advanceAccount)
                                    <div class="account-card is-asset">
                                        <div class="acc-name"><i class="fa fa-hand-o-up ml-1"></i> حساب السلف (الأصول)</div>
                                        <div class="acc-meta">
                                            {{ $advanceAccount->name }} ·
                                            كود: <strong>{{ $advanceAccount->code }}</strong> ·
                                            رصيد: {{ number_format($advanceAccount->balance, 2) }}
                                        </div>
                                        @if($advanceAccount->parent)
                                            <div class="acc-meta mt-1">
                                                <i class="feather icon-git-branch ml-1"></i>
                                                الحساب الأب: <strong>{{ $advanceAccount->parent->name }}</strong>
                                                · كود: <strong>{{ $advanceAccount->parent->code }}</strong>
                                            </div>
                                        @endif
                                    </div>
                                @endif
                                @if($employee->treasuryEmployee && $employee->treasuryEmployee->treasury)
                                    <div class="account-card is-asset">
                                        <div class="acc-name"><i class="fa fa-archive ml-1"></i> الخزينة الافتراضية</div>
                                        <div class="acc-meta">{{ $employee->treasuryEmployee->treasury->name ?? '' }}</div>
                                    </div>
                                @endif
                                @foreach($defaultStorehouses as $sh)
                                    @if($sh->storehouse)
                                        <div class="account-card is-asset">
                                            <div class="acc-name"><i class="fa fa-cube ml-1"></i> مستودع افتراضي</div>
                                            <div class="acc-meta">{{ $sh->storehouse->name }}</div>
                                            @if($sh->storehouse->account)
                                                <div class="acc-meta mt-1">
                                                    <i class="fa fa-file-text-o ml-1 text-success"></i>
                                                    الحساب المحاسبي: <strong>{{ $sh->storehouse->account->name }}</strong>
                                                    · كود: <strong>{{ $sh->storehouse->account->code }}</strong>
                                                    · رصيد: {{ number_format($sh->storehouse->account->balance ?? 0, 2) }}
                                                </div>
                                            @else
                                                <div class="acc-meta mt-1 text-muted">
                                                    <i class="feather icon-alert-circle ml-1"></i>
                                                    لا يوجد حساب محاسبي مربوط بهذا المستودع.
                                                </div>
                                            @endif
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    @else
                        <div class="empty-block"><i class="fa fa-money"></i> لا توجد حسابات أو ربط مالي مسجَّل.</div>
                    @endif

                    @permission('employees.sensitive')
                        <div class="card info-card accent-finance mt-3">
                            <div class="stk-table-head">
                                <span class="section-icon"><i class="feather icon-credit-card"></i></span> الحساب البنكي
                            </div>
                            <div class="card-body">
                                @if ($employee->iban || $employee->bank_name)
                                    <div class="info-grid">
                                        <div class="info-item">
                                            <div class="info-label">البنك</div>
                                            <div class="info-value">{{ $employee->bank_name ?: '—' }}</div>
                                        </div>
                                        <div class="info-item">
                                            <div class="info-label">صاحب الحساب</div>
                                            <div class="info-value">{{ $employee->bank_account_holder ?: '—' }}</div>
                                        </div>
                                        <div class="info-item">
                                            <div class="info-label">الآيبان</div>
                                            <div class="info-value"><code>{{ $employee->maskedIban() ?: '—' }}</code></div>
                                        </div>
                                    </div>
                                @else
                                    <div class="empty-block"><i class="feather icon-credit-card"></i> لم يُسجَّل حسابٌ بنكي — ويلزم للتحويل البنكي للرواتب.</div>
                                @endif
                            </div>
                        </div>
                    @endpermission

                    {{-- مكافآت كتالوج المكافآت: حافزٌ لمرّةٍ واحدة من النقاط — لا بدلٌ متكرّر --}}
                    @if(($rewardRedemptions ?? collect())->isNotEmpty())
                        <div class="card info-card accent-finance mt-3">
                            <div class="stk-table-head">
                                <span class="section-icon"><i class="feather icon-gift"></i></span> مكافآت الكتالوج (استبدال النقاط)
                                <i class="feather icon-help-circle stk-tip" title="المكافأة النقدية تُضاف لمرّةٍ واحدة على قسيمة الراتب التالية وتُصرف معها؛ ومكافأة الإجازة تُضاف أيامها إلى رصيد الإجازات. ليست بدلاً من بنود الراتب."></i>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-sm mb-0">
                                        <thead>
                                            <tr>
                                                <th>الطلب</th>
                                                <th>المكافأة</th>
                                                <th class="text-end">القيمة</th>
                                                <th>الحالة</th>
                                                <th>التنفيذ</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($rewardRedemptions as $rr)
                                                @php
                                                    $rrType = $rr->reward?->reward_type?->value;
                                                    $rrMeta = $rr->metadata ?? [];
                                                @endphp
                                                <tr>
                                                    <td>
                                                        @if(Route::has('ecosystem.points.redemptions.show'))
                                                            <a href="{{ route('ecosystem.points.redemptions.show', $rr) }}">{{ $rr->redemption_number }}</a>
                                                        @else
                                                            {{ $rr->redemption_number }}
                                                        @endif
                                                        <div class="text-muted small">{{ $rr->created_at?->format('Y-m-d') }}</div>
                                                    </td>
                                                    <td>{{ $rr->reward->name ?? '—' }}</td>
                                                    <td class="text-end">
                                                        @if($rrType === 'leave_day')
                                                            {{ (int) round((float) $rr->reward_value) }} يوم
                                                        @else
                                                            {{ number_format((float) $rr->reward_value, 2) }}
                                                        @endif
                                                    </td>
                                                    <td>{{ $rr->status?->label() ?? '—' }}</td>
                                                    <td class="small">
                                                        @if($rrType === 'cash' && $rr->payroll_run_id)
                                                            صُرفت مع الراتب — {{ $rrMeta['payroll_run_code'] ?? ('#'.$rr->payroll_run_id) }}
                                                        @elseif($rrType === 'cash' && ($rrMeta['payroll_status'] ?? null) === 'pending')
                                                            بانتظار صرفة الراتب التالية
                                                        @elseif($rrType === 'cash' && ! empty($rrMeta['journal_entry_id']))
                                                            صُرفت من الخزينة (قبل ربطها بالرواتب)
                                                        @elseif($rrType === 'leave_day' && ! empty($rrMeta['leave_balance_id']))
                                                            أُضيفت إلى رصيد الإجازات ({{ $rrMeta['leave_year'] ?? '' }})
                                                        @else
                                                            —
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>

                {{-- المرفقات --}}
                <div class="tab-pane fade" id="pane-files" role="tabpanel" aria-labelledby="tab-files">
                    <div class="card info-card accent-files">
                        <div class="stk-table-head d-flex justify-content-between align-items-center">
                            <span><span class="section-icon"><i class="fa fa-paperclip"></i></span> المرفقات ({{ $employee->attachments->count() }})</span>
                            @if($employee->attachments->count() > 0)
                                    <a href="{{ route('employee.attachments.download_zip', $employee->id) }}" class="btn btn-primary btn-sm">
                                        <i class="feather icon-download ml-1"></i> تنزيل الكل (ZIP)
                                    </a>
                            @endif
                        </div>
                        <div class="card-body">
                            @if($employee->attachments->count() > 0)
                                <ul class="att-list">
                                    @foreach($employee->attachments as $att)
                                        @php [$cls,$fa] = fileIconClasses($att->original_name, $att->mime_type); @endphp
                                        <li>
                                            <span class="file-icon {{ $cls }}"><i class="fa {{ $fa }}"></i></span>
                                            <div class="flex-grow-1 min-width-0">
                                                <div class="file-name">{{ $att->original_name }}</div>
                                                <div class="file-meta">{{ formatBytes($att->size_bytes) }} · {{ $att->created_at?->format('Y-m-d H:i') }}</div>
                                            </div>
                                            {{-- التنزيلُ مع العرض (`employees.show`) — ومن بلغ هذه الصفحة يملكه --}}
                                            <div class="dropdown">
                                                <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false" aria-label="خيارات">
                                                    <i class="fa fa-ellipsis-v"></i>
                                                </button>
                                                <div class="dropdown-menu dropdown-menu-end">
                                                        <a class="dropdown-item" href="{{ route('employee.attachments.download', $att->id) }}"><i class="fa fa-download me-2 text-primary"></i>تنزيل</a>
                                                    @permission('employees.edit')
                                                        <div class="dropdown-divider"></div>
                                                        <form action="{{ route('employee.attachments.delete', $att->id) }}" method="POST" class="m-0" onsubmit="return confirm('هل أنت متأكد من حذف هذا المرفق؟');">
                                                            @csrf @method('DELETE')
                                                            <button type="submit" class="dropdown-item text-danger"><i class="fa fa-trash me-2"></i>حذف</button>
                                                        </form>
                                                    @endpermission
                                                </div>
                                            </div>
                                        </li>
                                    @endforeach
                                </ul>
                            @else
                                <div class="empty-block"><i class="fa fa-folder-open-o"></i> لا توجد مرفقات.</div>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- النشاطات --}}
                <div class="tab-pane fade" id="pane-activity" role="tabpanel" aria-labelledby="tab-activity">
                    <div class="card info-card accent-activity">
                        <div class="stk-table-head">
                            <span class="section-icon"><i class="feather icon-rotate-ccw"></i></span> سجل النشاطات
                        </div>
                        <div class="card-body" style="max-height: 640px; overflow-y: auto;">
                            @if ($logs && count($logs) > 0)
                                @foreach ($logs as $date => $dayLogs)
                                    @php $currentDate = \Carbon\Carbon::parse($date); @endphp
                                    <div class="timeline-day">
                                        {{ $currentDate->locale('ar')->translatedFormat('l') }} · {{ $currentDate->format('Y-m-d') }}
                                    </div>
                                    <ul class="timeline">
                                        @foreach ($dayLogs as $log)
                                            @if ($log)
                                                <li class="timeline-item">
                                                    <div class="timeline-content">
                                                        <div class="time">
                                                            <i class="feather icon-clock"></i>
                                                            {{ $log->created_at->format('H:i:s') }}
                                                        </div>
                                                        <div>
                                                            <strong>{{ $log->user->name ?? 'مستخدم غير معروف' }}</strong>
                                                            {!! Str::markdown($log->description ?? 'لا يوجد وصف') !!}
                                                            <div class="text-muted small">
                                                                {{ $log->user->branch->name ?? '' }}
                                                            </div>
                                                        </div>
                                                    </div>
                                                </li>
                                            @endif
                                        @endforeach
                                    </ul>
                                @endforeach
                            @else
                                <div class="empty-block"><i class="fa fa-clock-o"></i> لا توجد سجلات نشاط حتى الآن.</div>
                            @endif
                        </div>
                    </div>
                </div>

            </div>{{-- /tab-content --}}
        </div>{{-- /emp-tabs-card --}}

    </div>{{-- /container --}}

    {{-- ===== Modals ===== --}}
    {{--
        مودالُ الحذف — ينقسم على جواب `EmployeeDeletionGuard`:

        فإن خلا الموظفُ من كلّ ارتباطٍ وحركة عُرض الحذفُ النهائي، وإن كان
        له أثرٌ سُمّي المانعُ باسمه. والمانعُ يُحسب في `show()` — فلا يُطلب
        من المستخدم أن يضغط ليعرف أنه لا يستطيع.

        وكان الفرعُ الثاني يعرض «أرشفة بدل الحذف»، والأرشفةُ معلَّقةٌ بطلب —
        فصار يدلّ على «تعطيل» و«إنهاء الخدمة»، وكلاهما في شريط الإجراءات.
    --}}
    <div class="modal fade text-left" id="modal_DELETE{{ $employee->id }}" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-scrollable" role="document">
            <div class="modal-content">
                <div class="modal-header" style="background-color: var(--app-danger, #EA5455) !important;">
                    <h4 class="modal-title" style="color: #FFFFFF">
                        {{ empty($deletionBlockers) ? 'حذف' : 'تعذّر حذف' }} {{ $employee->full_name }}
                    </h4>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true" style="color: #fff">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    @if (empty($deletionBlockers))
                        <strong>هل أنت متأكد من حذف هذا الموظف نهائياً؟</strong>
                        <p class="text-muted mt-1 mb-0">
                            لا ارتباطَ له في النظام ولا حركة — فلا شيء يُمحى معه. يُحذف صفُّه
                            وحسابُ دخوله وإعداداتُه، ولا يُسترجع.
                        </p>
                    @else
                        <strong class="text-danger">لا يمكن حذف هذا الموظف — له ارتباطاتٌ وحركةٌ في النظام:</strong>
                        <ul class="mt-2 mb-2">
                            @foreach ($deletionBlockers as $blocker)
                                <li>{{ $blocker['label'] }} <span class="text-muted">({{ $blocker['count'] }})</span></li>
                            @endforeach
                        </ul>
                        <p class="text-muted mb-0">
                            حذفُه يمحو هذه البيانات معه، فلا يقع ما دامت قائمة. ولإخراجه
                            من الخدمة مع بقاء سجلّه: «تعطيل» يمنع دخولَه، و«إنهاء الخدمة»
                            يُغلق ملفَّه ويحتسب مستحقّاته — وكلاهما في شريط الإجراءات أعلى
                            الصفحة.
                        </p>
                    @endif
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light waves-effect waves-light" data-dismiss="modal">
                        {{-- «إلغاء» حين يكون ثمّة ما يُلغى، و«إغلاق» حين لا فعلَ في المودال --}}
                        {{ empty($deletionBlockers) ? 'إلغاء' : 'إغلاق' }}
                    </button>
                    @if (empty($deletionBlockers))
                        @permission('employees.destroy')
                            <form action="{{ route('employee.delete', $employee->id) }}" method="POST" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-danger waves-effect waves-light">تأكيد الحذف النهائي</button>
                            </form>
                        @endpermission
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade text-left" id="ChangePassword" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-scrollable" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">تغيير كلمة المرور</h4>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                @permission('employee_accounts.password')
                    <form class="form" action="{{ route('employee.updatePassword', $employee->id) }}" method="POST">
                        @csrf
                        <div class="modal-body">
                            <div class="form-group">
                                <label>كلمة المرور الجديدة</label>
                                <input type="password" class="form-control" name="password" placeholder="كلمة المرور الجديدة" value="{{ old('password') }}">
                                @error('password') <span class="text-danger small">{{ $message }}</span> @enderror
                            </div>
                            <div class="form-group">
                                <label>تأكيد كلمة المرور</label>
                                <input type="password" class="form-control" name="password_confirmation" placeholder="تأكيد كلمة المرور">
                                @error('password_confirmation') <span class="text-danger small">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-dismiss="modal">إلغاء</button>
                            <button type="submit" class="btn btn-primary">حفظ</button>
                        </div>
                    </form>
                @endpermission
            </div>
        </div>
    </div>

    {{--
        نافذةُ إنهاء الخدمة — منفصلةٌ عن التعطيل عمداً.

        الحقولُ الثلاثة إلزامية لأنها ما يُبنى عليه لاحقاً: التاريخُ لحساب
        نهاية الخدمة، وآخرُ يوم عملٍ لقطع الراتب، والسببُ للرجوع إليه.
        والحارسُ على الخادم (`employees.terminate`) لا على إخفاء الزرّ.
    --}}
    @permission('employees.terminate')
        @unless ($employee->hasEndedService())
            <div class="modal fade" id="terminateEmployeeModal" tabindex="-1" role="dialog" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered" role="document">
                    <div class="modal-content">
                        <form action="{{ route('employee.terminate', $employee->id) }}" method="POST">
                            @csrf
                            <div class="modal-header">
                                <h5 class="modal-title">إنهاء خدمة {{ $employee->full_name }}</h5>
                                <button type="button" class="close" data-dismiss="modal" aria-label="إغلاق">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>

                            <div class="modal-body">
                                <div class="alert alert-warning">
                                    لا يُصرف راتبٌ بعد إنهاء الخدمة، ولا تُبدَّل الحالة من شاشة الموظف بعدها.
                                </div>

                                <div class="form-group">
                                    <label for="termination_type" class="stk-req">نوع الإنهاء</label>
                                    <select name="status" id="termination_type" class="form-control" required>
                                        <option value="">اختر…</option>
                                        @foreach (\App\Enums\EmploymentStatus::ending() as $case)
                                            <option value="{{ $case->value }}" @selected(old('status') == $case->value)>{{ $case->label() }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="termination_date" class="stk-req">تاريخ إنهاء الخدمة</label>
                                    <input type="date" name="termination_date" id="termination_date"
                                        class="form-control" value="{{ old('termination_date', date('Y-m-d')) }}" required>
                                </div>

                                <div class="form-group">
                                    <label for="last_working_day">آخر يوم عمل</label>
                                    <input type="date" name="last_working_day" id="last_working_day"
                                        class="form-control" value="{{ old('last_working_day') }}">
                                    <small class="text-muted">يُترك فارغاً فيساوي تاريخ الإنهاء — وقد يتأخّر عنه بمدّة الإشعار</small>
                                </div>

                                <div class="form-group">
                                    <label for="termination_reason" class="stk-req">السبب</label>
                                    <textarea name="termination_reason" id="termination_reason" rows="3"
                                        class="form-control" required>{{ old('termination_reason') }}</textarea>
                                </div>
                            </div>

                            <div class="modal-footer">
                                <button type="button" class="btn btn-light" data-dismiss="modal">إلغاء</button>
                                <button type="submit" class="btn btn-danger">تأكيد إنهاء الخدمة</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endunless
    @endpermission

</div><!-- /content-body -->

@include('hr::components.table-menu')
@endsection

@section('scripts')
<script>
    (function () {
        var $tabs = $('.employee-show .emp-tabs a[data-toggle="tab"]');
        if (!$tabs.length) return;

        function activateFromHash() {
            var hash = window.location.hash;
            if (!hash) return;
            var $link = $tabs.filter('[href="' + hash + '"]');
            if ($link.length) {
                $link.tab('show');
            }
        }

        $tabs.on('shown.bs.tab', function (e) {
            var href = $(e.target).attr('href');
            if (href && history.replaceState) {
                history.replaceState(null, '', href);
            }
        });

        activateFromHash();
        $(window).on('hashchange', activateFromHash);
    })();
</script>
@endsection
