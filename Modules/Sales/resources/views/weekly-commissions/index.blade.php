@extends('hr::master')

@section('title', 'عمولاتي الأسبوعية')

@section('content')
@php
    // الصرفُ: صلاحيةُ المبيعات و«صرف العمولة» معاً — كما على المسار
    $canPayout = hasPermission('sales.commissions.payout') && hasPermission('commissions.disburse');
@endphp

        {{-- من أجل `.sl-guide` وحدها؛ كلُّ قواعدها `.sl-`ـيّة فلا تمسّ
             أنماطَ هذه الشاشة --}}
        @include('sales::partials.settings-theme')
        @include('components.hint-tips')

        {{-- ══════════ إرشادُ الشاشة ══════════ --}}
        @include('sales::components.guide', [
            'key' => 'sales-commissions.index',
            'open' => false,
            'title' => __('sales_settings.guide.commissions.title'),
            'lead' => __('sales_settings.guide.commissions.lead'),
            'blocks' => [
                [
                    __('sales_settings.guide.commissions.a'),
                    __('sales_settings.guide.commissions.b'),
                    __('sales_settings.guide.commissions.c'),
                    __('sales_settings.guide.commissions.d'),
                ],
            ],
        ])
<div class="container-fluid">
    <!-- الإحصائيات -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card border-primary">
                <div class="card-body text-center">
                    <i class="fas fa-clock fa-2x text-primary mb-2"></i>
                    <h5 class="card-title">عمولات معلقة</h5>
                    <h3 class="text-primary">{{ number_format($totalPending, 2) }} <small>ر.س</small></h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-success">
                <div class="card-body text-center">
                    <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                    <h5 class="card-title">تم صرفها (آخر 30 يوم)</h5>
                    <h3 class="text-success">{{ number_format($totalPaidThisMonth, 2) }} <small>ر.س</small></h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-info">
                <div class="card-body text-center">
                    <i class="fas fa-wallet fa-2x text-info mb-2"></i>
                    <h5 class="card-title">خزينتي</h5>
                    <h4 class="text-info">{{ $treasury->name ?? 'غير محددة' }}</h4>
                </div>
            </div>
        </div>
    </div>

    @if(isset($noEmployee) && $noEmployee)
    <!-- رسالة عدم وجود حساب موظف -->
    <div class="card">
        <div class="card-body">
            <div class="alert alert-danger text-center mb-0">
                <i class="fas fa-user-times fa-3x mb-3"></i>
                <h4>ليس لديك حساب موظف مرتبط</h4>
                <p class="mb-0">يرجى التواصل مع الإدارة لربط حسابك بملف الموظف الخاص بك</p>
            </div>
        </div>
    </div>
    @else
    <!-- العمولات المعلقة -->
    <div class="card">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="fas fa-coins"></i> العمولات الأسبوعية المعلقة</h5>
            @if($canPayout && $pendingCommissions->count() > 0)
            <form action="{{ route('sales.weekly-commissions.payout-all') }}" method="POST" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-light btn-sm" onclick="return confirm('هل أنت متأكد من صرف جميع العمولات؟')">
                    <i class="fas fa-money-bill-wave"></i> صرف الكل ({{ number_format($totalPending, 2) }} ر.س)
                </button>
            </form>
            @endif
        </div>
        <div class="card-body">
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle"></i> {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif
            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-circle"></i> {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif
            @if(session('warning'))
                <div class="alert alert-warning alert-dismissible fade show">
                    <i class="fas fa-exclamation-triangle"></i> {{ session('warning') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @if($treasury)
                @if($pendingCommissions->count() > 0)
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>#</th>
                                <th>الهدف</th>
                                <th>المبلغ</th>
                                <th>تاريخ الإنشاء</th>
                                <th>إجراء</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($pendingCommissions as $index => $commission)
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td>{{ $commission->salesTarget->name ?? '-' }}</td>
                                <td>
                                    <span class="badge bg-primary fs-6">
                                        {{ number_format($commission->commission_amount, 2) }} ر.س
                                    </span>
                                </td>
                                <td>{{ $commission->created_at->format('Y-m-d H:i') }}</td>
                                <td>
                                    @if ($canPayout)
                                    <form action="{{ route('sales.weekly-commissions.payout', $commission->id) }}" method="POST" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-success btn-sm" onclick="return confirm('هل أنت متأكد من صرف هذه العمولة؟')">
                                            <i class="fas fa-money-bill-wave"></i> صرف
                                        </button>
                                    </form>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="table-primary">
                                <td colspan="2"><strong>الإجمالي</strong></td>
                                <td colspan="3">
                                    <strong>{{ number_format($totalPending, 2) }} ر.س</strong>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                @else
                <div class="alert alert-info text-center">
                    <i class="fas fa-info-circle fa-2x mb-2"></i>
                    <h5>لا توجد عمولات معلقة</h5>
                    <p class="mb-0">ستظهر هنا العمولات الأسبوعية عند تحقيق الأهداف</p>
                </div>
                @endif
            @else
                <div class="alert alert-warning text-center">
                    <i class="fas fa-exclamation-triangle fa-2x mb-2"></i>
                    <h5>لا توجد خزينة مرتبطة بحسابك</h5>
                    <p class="mb-0">يجب ربط خزينة بحسابك لتتمكن من صرف العمولات</p>
                </div>
            @endif
        </div>
    </div>

    <!-- العمولات المصروفة مؤخراً -->
    @if($paidCommissions->count() > 0)
    <div class="card mt-4">
        <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="fas fa-history"></i> العمولات المصروفة (آخر 30 يوم)</h5>
            <a href="{{ route('sales.weekly-commissions.history') }}" class="btn btn-light btn-sm">
                <i class="fas fa-list"></i> عرض الكل
            </a>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>الهدف</th>
                            <th>المبلغ</th>
                            <th>تاريخ الصرف</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($paidCommissions as $index => $commission)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td>{{ $commission->salesTarget->name ?? '-' }}</td>
                            <td>
                                <span class="badge bg-success">
                                    {{ number_format($commission->commission_amount, 2) }} ر.س
                                </span>
                            </td>
                            <td>{{ $commission->direct_paid_at ? \Carbon\Carbon::parse($commission->direct_paid_at)->format('Y-m-d H:i') : '-' }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif
    @endif {{-- end noEmployee check --}}
</div>
@endsection
