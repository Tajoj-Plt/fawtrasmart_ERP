<!-- علامات التبويب -->
@php
    /* تبويبٌ بلا عمليات لا يظهر — «نظرة عامة» و«التفاصيل» دائماً. تُخفى
       علامة التبويب وحدها؛ لوحاتُها تبقى في الصفحة لأنّ بعضها يحمل مودالات
       وسكربتات تستعملها أزرار الإجراءات. العدّ هنا هو شرط الإخفاء والشارة معاً. */
    $tabCounts = [
        'invoices' => isset($invoices) ? $invoices->count() : 0,
        'payments' => isset($payments) ? $payments->where('type', 'client payments')->count() : 0,
        'visits' => isset($visits) ? $visits->count() : 0,
        'appointments' => $client->appointments->count(),
        'reservations' => isset($reservations) ? $reservations->count() : 0,
        'notes' => isset($ClientRelations) ? $ClientRelations->total() : 0,
        'employee-notes' => $client->employeeNotes->count(),
        // نفس نطاق `InvoiceTransferController::getClientTransfers()`: ما أرسله المستخدم أو أُرسل إليه
        'invoice-delivery' => \App\Models\InvoiceTransfer::where('client_id', $client->id)
            ->where(fn ($q) => $q->where('from_employee_id', auth()->id())->orWhere('to_employee_id', auth()->id()))
            ->count(),
        'product-exchanges' => isset($productExchanges) ? $productExchanges->count() : 0,
        'activity-log' => (isset($activityLogs) ? $activityLogs->flatten()->count() : 0)
            + (isset($statusRequests) ? $statusRequests->where('status', 'pending')->count() : 0),
        'profile-access-log' => isset($profileViewEntries) ? $profileViewEntries->count() : 0,
    ];
@endphp
<div class="cx-tabs-card">
    <ul class="nav nav-tabs" role="tablist">
        <li class="nav-item">
            <a class="nav-link active" data-bs-toggle="tab" href="#overview" role="tab">
                <i class="fa fa-th-large"></i>
                نظرة عامة
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="tab" href="#details" role="tab">
                <i class="fa fa-info-circle"></i>
                التفاصيل
            </a>
        </li>
        @if ($tabCounts['invoices'])
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#invoices" role="tab">
                    <i class="fa fa-file-invoice"></i>
                    الفواتير
                    <span class="badge">{{ $tabCounts['invoices'] }}</span>
                </a>
            </li>
        @endif
        @if ($tabCounts['payments'])
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#payments" role="tab">
                    <i class="fa fa-credit-card"></i>
                    المدفوعات
                    <span class="badge">{{ $tabCounts['payments'] }}</span>
                </a>
            </li>
        @endif
        @if ($tabCounts['visits'])
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#visits" role="tab">
                    <i class="fa fa-walking"></i>
                    الأنشطة والزيارات
                    <span class="badge">{{ $tabCounts['visits'] }}</span>
                </a>
            </li>
        @endif
        @if ($tabCounts['appointments'])
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#appointments" role="tab">
                    <i class="fa fa-calendar-alt"></i>
                    المواعيد
                    <span class="badge">{{ $tabCounts['appointments'] }}</span>
                </a>
            </li>
        @endif
        @if ($tabCounts['reservations'] && hasPermission('reservations.view'))
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#reservations" role="tab">
                    <i class="fa fa-calendar-check"></i>
                    الحجوزات
                    <span class="badge">{{ $tabCounts['reservations'] }}</span>
                </a>
            </li>
        @endif
        @if ($tabCounts['notes'])
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#notes" role="tab">
                    <i class="fa fa-clipboard-list"></i>
                    ملاحظات الزيارات
                    <span class="badge">{{ $tabCounts['notes'] }}</span>
                </a>
            </li>
        @endif
        <!-- تبويب ملاحظات الموظفين — مُصيَّرٌ دائماً ومخفيٌّ بلا ملاحظات: إضافة أوّل
             ملاحظة من «الإجراءات» تُظهره (`loadNotes()` في employee-notes.js) -->
        <li class="nav-item" @unless ($tabCounts['employee-notes']) style="display: none;" @endunless>
            <a class="nav-link" data-bs-toggle="tab" href="#employee-notes" role="tab" id="employee-notes-tab">
                <i class="fa fa-user-tie"></i>
                موظفو العميل
                <span class="badge bg-info" id="employee-notes-count">{{ $tabCounts['employee-notes'] }}</span>
            </a>
        </li>
        <!-- تبويب تسليم واستلام الفواتير — يظهر لمن يملك التسليم ولو بلا تحويلات:
             زرّ «تسليم فاتورة» داخله، فإخفاؤه بلا عمليات كان يُخفي الطريق إلى أوّل تسليم -->
        @if (hasPermission('clients_invoice_transfer_view')
            && ($tabCounts['invoice-delivery'] || hasPermission('clients_invoice_transfer_send')))
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#invoice-delivery" role="tab" id="invoice-delivery-tab">
                    <i class="fa fa-exchange-alt"></i>
                    تسليم الفواتير
                    <span class="badge">{{ $tabCounts['invoice-delivery'] }}</span>
                </a>
            </li>
        @endif
        <!-- تبويب تبديلات المنتجات -->
        @if ($tabCounts['product-exchanges'])
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#product-exchanges" role="tab" id="product-exchanges-tab">
                    <i class="fa fa-sync-alt"></i>
                    التبديلات
                    <span class="badge bg-info">{{ $tabCounts['product-exchanges'] }}</span>
                </a>
            </li>
        @endif
        <!-- تبويب سجل النشاط -->
        @if ($tabCounts['activity-log'])
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#activity-log" role="tab" id="activity-log-tab">
                    <i class="fa fa-history"></i>
                    سجل النشاط
                    @if (isset($statusRequests) && $statusRequests->where('status', 'pending')->count() > 0)
                        <span class="badge bg-danger">{{ $statusRequests->where('status', 'pending')->count() }}</span>
                    @endif
                </a>
            </li>
        @endif
        <!-- تبويب سجل الدخول إلى ملف العميل -->
        @if (! empty($canViewProfileAccessLog) && $tabCounts['profile-access-log'])
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#profile-access-log" role="tab" id="profile-access-log-tab">
                    <i class="fa fa-door-open"></i>
                    سجل الدخول
                    @if (isset($profileViews) && $profileViews->sum('views_count') > 0)
                        <span class="badge">{{ $profileViews->sum('views_count') }}</span>
                    @endif
                </a>
            </li>
        @endif
    </ul>

    <div class="tab-content">
        <!-- تبويب نظرة عامة -->
        @include('client::partials.tabs.overview')

        <!-- تبويب التفاصيل -->
        @include('client::partials.tabs.details')

        <!-- تبويب الفواتير -->
        @include('client::partials.tabs.invoices')

        <!-- تبويب المدفوعات -->
        @include('client::partials.tabs.payments')

        <!-- تبويب المواعيد -->
        @include('client::partials.tabs.appointments')

        <!-- تبويب الحجوزات -->
        @include('client::partials.tabs.reservations')

        <!-- تبويب ملاحظات الزيارات (والمنافسون) -->
        @include('client::partials.tabs.notes')

        <!-- تبويب الزيارات -->
        @include('client::partials.tabs.visits')

        <!-- تبويب ملاحظات الموظفين -->
        @include('client::partials.tabs.employee-notes')

        <!-- تبويب تسليم واستلام الفواتير -->
        @include('client::partials.tabs.invoice-delivery')

        <!-- تبويب تبديلات المنتجات -->
        @include('client::partials.tabs.product-exchanges')

        <!-- تبويب سجل النشاط -->
        @include('client::partials.tabs.activity-log')

        <!-- تبويب سجل الدخول إلى ملف العميل -->
        @include('client::partials.tabs.profile-views')
    </div>
</div>

<script>
    // أزرار «عرض الكل» داخل تبويب النظرة العامة تنقل المستخدم لتبويب حقيقي
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.cx-jump-tab').forEach(function (el) {
            // تبويبٌ مخفيٌّ بلا عمليات — لا «عرض الكل» يقود إليه
            if (!document.querySelector('.cx-tabs-card .nav-tabs a[href="' + el.getAttribute('data-target') + '"]')) {
                el.style.display = 'none';
            }
            el.addEventListener('click', function (e) {
                e.preventDefault();
                var target = this.getAttribute('data-target');
                var trigger = document.querySelector('.cx-tabs-card .nav-tabs a[href="' + target + '"]');
                if (trigger && typeof bootstrap !== 'undefined' && bootstrap.Tab) {
                    bootstrap.Tab.getOrCreateInstance(trigger).show();
                    trigger.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });
                }
            });
        });
    });
</script>
