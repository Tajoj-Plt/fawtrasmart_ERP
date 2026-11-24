@extends('client')

@section('title', 'لوحة التحكم')

@section('content')
    {{--
        ملاحظة: هذا الملف غير موصول بأي مسار داخل وحدة المتجر الإلكتروني —
        استدعاء `view('dashboard.client', ...)` من
        `Modules\Client\Http\Controllers\ClientSettingController::personal()`
        (المسار `clients.personal`) يُحلّ فعلياً إلى
        `resources/views/dashboard/client.blade.php` (بلا بادئة وحدة)، لا
        إلى هذه النسخة. أُبقيت النسخة متوافقة بصرياً مع تلك الشاشة تحسّباً
        لأي استخدام مستقبلي، بلا أي حراسة صلاحية لأنها شاشة عميل وليست
        شاشة إدارية.
    --}}
    @include('stock::layouts.partials.stock-theme')

    <div class="content-body">

        @include('stock::components.page-head', [
            'title' => 'لوحة التحكم',
            'icon' => 'home',
            'sub' => 'فواتيرك ومستحقاتك في مكان واحد',
            'crumbs' => [
                'الرئيسية' => null,
            ],
        ])

        <div class="card mb-3">
            <div class="card-body">
                <div class="stk-media">
                    <span class="stk-media__thumb stk-avatar-lg"><i class="feather icon-user"></i></span>
                    <span class="stk-media__body">
                        <span class="stk-media__title">{{ auth()->user()->name }}</span>
                        <span class="stk-media__sub">
                            <i class="feather icon-phone"></i>
                            {{ auth()->user()->phone ?: 'لا رقم مسجَّل' }}
                        </span>
                    </span>
                </div>
            </div>
        </div>

        <div class="stk-metrics">
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-file-text"></i></span>
                <span class="stk-metric__label">عدد الفواتير</span>
                <span class="stk-metric__value stk-num">{{ $invoices_count ?? 0 }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-alert-circle"></i></span>
                <span class="stk-metric__label">المستحق عليك</span>
                <span class="stk-metric__value">@money($invoices_due_value ?? 0)</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-check-circle"></i></span>
                <span class="stk-metric__label">إجمالي الفواتير</span>
                <span class="stk-metric__value">
                    @money(collect($invoices ?? [])->sum('grand_total'))
                </span>
            </div>
        </div>

        <div class="card stk-table-card">
            <div class="stk-table-head">
                <h6 class="stk-table-head__title">
                    <i class="feather icon-file-text"></i> فواتيرك
                    <span class="stk-count">{{ collect($invoices ?? [])->count() }}</span>
                </h6>
            </div>

            @if (collect($invoices ?? [])->count())
                <div class="table-responsive">
                    <table class="table table-hover table-striped mb-0">
                        <thead>
                            <tr>
                                <th>رقم الفاتورة</th>
                                <th class="text-center">الإجمالي</th>
                            </tr>
                        </thead>

                        <tbody>
                            @foreach ($invoices as $invoice)
                                <tr>
                                    <td class="stk-num">{{ $invoice->code ?? '—' }}</td>
                                    <td class="text-center">
                                        <strong>@money($invoice->grand_total ?? 0)</strong>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="stk-empty">
                    <span class="stk-empty__icon"><i class="feather icon-file-text"></i></span>
                    <h6 class="stk-empty__title">لا فواتير بعد</h6>
                    <p class="stk-empty__hint">ستظهر فواتيرك هنا فور إصدارها.</p>
                </div>
            @endif
        </div>
    </div>
@endsection

@section('scripts')
@endsection
