@extends('hr::master')

@section('title', 'سجل العمولات المصروفة')

@section('content')

        {{-- من أجل `.sl-guide` وحدها؛ كلُّ قواعدها `.sl-`ـيّة فلا تمسّ
             أنماطَ هذه الشاشة --}}
        @include('sales::partials.settings-theme')
        @include('components.hint-tips')

        {{-- ══════════ إرشادُ الشاشة ══════════ --}}
        @include('sales::components.guide', [
            'key' => 'sales-commissions.history',
            'title' => __('sales_settings.guide.commissions.title'),
            'lead' => __('sales_settings.guide.commissions.lead'),
            'open' => false,
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
    <div class="card">
        <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="fas fa-history"></i> سجل العمولات الأسبوعية المصروفة</h5>
            <a href="{{ route('sales.weekly-commissions.index') }}" class="btn btn-light btn-sm">
                <i class="fas fa-arrow-right"></i> رجوع
            </a>
        </div>
        <div class="card-body">
            <!-- فلتر التاريخ -->
            <form method="GET" class="row mb-4">
                <div class="col-md-4">
                    <label class="form-label">من تاريخ</label>
                    <input type="date" name="from_date" class="form-control" value="{{ request('from_date') }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">إلى تاريخ</label>
                    <input type="date" name="to_date" class="form-control" value="{{ request('to_date') }}">
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="fas fa-filter"></i> تصفية
                    </button>
                    <a href="{{ route('sales.weekly-commissions.history') }}" class="btn btn-secondary">
                        <i class="fas fa-redo"></i> إعادة تعيين
                    </a>
                </div>
            </form>

            @if($commissions->count() > 0)
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>#</th>
                            <th>الهدف</th>
                            <th>المبلغ</th>
                            <th>تاريخ الصرف</th>
                            <th>رقم القيد</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($commissions as $index => $commission)
                        <tr>
                            <td>{{ $commissions->firstItem() + $index }}</td>
                            <td>{{ $commission->salesTarget->name ?? '-' }}</td>
                            <td>
                                <span class="badge bg-success fs-6">
                                    {{ number_format($commission->commission_amount, 2) }} ر.س
                                </span>
                            </td>
                            <td>{{ $commission->direct_paid_at ? \Carbon\Carbon::parse($commission->direct_paid_at)->format('Y-m-d H:i') : '-' }}</td>
                            <td>
                                @if($commission->direct_journal_entry_id)
                                    <a href="{{ route('journal.show', $commission->direct_journal_entry_id) }}" class="btn btn-sm btn-outline-info" target="_blank">
                                        <i class="fas fa-eye"></i> #{{ $commission->direct_journal_entry_id }}
                                    </a>
                                @else
                                    -
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="d-flex justify-content-center mt-3">
                {{ $commissions->withQueryString()->links() }}
            </div>

            <!-- Summary -->
            <div class="alert alert-success mt-3">
                <strong>إجمالي المصروف:</strong> {{ number_format($commissions->sum('commission_amount'), 2) }} ر.س
                ({{ $commissions->total() }} عمولة)
            </div>
            @else
            <div class="alert alert-info text-center">
                <i class="fas fa-info-circle fa-2x mb-2"></i>
                <h5>لا توجد عمولات مصروفة</h5>
                <p class="mb-0">ستظهر هنا سجلات العمولات بعد صرفها</p>
            </div>
            @endif
        </div>
    </div>
</div>
@endsection
