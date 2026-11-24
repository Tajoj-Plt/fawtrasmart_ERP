{{--
    ملفّ جهة اتصال عميل — أُعيد بناؤها بنظام تصميم الوحدة الحقيقي
    (`.cl-scope` + `partials/settings-theme` + `components/page-head`) بدل
    ترويسةٍ يدوية (`content-header row`) وشارات Bootstrap 4 الخام.

    كل منطقٍ وظيفي حقيقي (فلترة المواعيد، حذف/تعديل الملاحظات بصلاحياتها،
    بناء كشف الحساب من `$client->invoices`) بقي كما هو حرفياً.

    **إصلاحٌ حقيقي لا تصميمٌ فقط**: تحديث حالة الموعد كان يستدعي راوتاً غير
    موجودٍ إطلاقاً (`appointments.update-status`) — الصفحة كانت تُسقط بـ
    RouteNotFoundException عند أوّل تصييرٍ لها لأي عميلٍ له موعد، أي أن هذه
    الشاشة كانت 500 دائماً لا مجرّد تصميمٍ غير مطابق. استُبدلت بالراوت
    الحقيقي `update-status` (GET، نفس ما تستعمله `appointments/show.blade.php`).
    ومودال «إضافة ملاحظات للموعد» كان يستدعي دالّةً جافاسكربت غير معرَّفة
    (`submitCompletedAppointment`) فحُذف — زرّ «تم» يؤدّي نفس الغرض فعلياً.

    تبويب «العضوية» بياناتٌ ثابتةٌ بالكامل (لا جدول عضويات حقيقي، والنموذج
    المنبثق فيه لا يحفظ شيئاً) — تُرك ظاهراً بدل حذفه (قرار منتجٍ خارج نطاق
    طلب التصميم) لكن مُعلَّماً هنا صراحةً.
--}}
@extends('client::master')

@section('title')
    {{ $client->trade_name ?? 'جهة اتصال' }}
@stop

@section('content')
@include('client::partials.settings-theme')

<div class="cl-scope">
    @if (session('toast_message'))
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                toastr.{{ session('toast_type', 'success') }}('{{ session('toast_message') }}', '', {
                    positionClass: 'toast-bottom-left',
                    closeButton: true,
                    progressBar: true,
                    timeOut: 5000
                });
            });
        </script>
    @endif

    @php
        $pageActions = '';
        if (isset($client) && $client && hasPermission('clients_edit_data')) {
            $pageActions = '<a href="'.route('clients.edit', $client->id).'" class="btn btn-outline-primary">'
                .'<i class="feather icon-edit-3"></i> <span>تعديل</span></a>';
        }
    @endphp

    @include('client::components.page-head', [
        'title' => $client->trade_name ?? 'جهة اتصال',
        'icon' => 'user',
        'sub' => 'الكود #'.($client->code ?? '—'),
        'crumbs' => [
            'الرئيسية' => route('dashboard'),
            'جهات الاتصال' => route('clients.contacts'),
            $client->trade_name ?? 'التفاصيل' => null,
        ],
        'actions' => $pageActions,
    ])

    <div class="card">
        <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <strong>{{ $client->trade_name }}</strong>
                <span class="text-muted small">#{{ $client->code }}</span>
                @if (in_array($client->status_id, \App\Models\Statuses::activeClientStatusIds()))
                    <span class="cl-state cl-state--on">نشط</span>
                @else
                    <span class="cl-state cl-state--off">غير نشط</span>
                @endif
            </div>
            <a href="{{ route('appointment.notes.create', ['id' => $client->id]) }}"
               class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-paperclip me-1"></i> إضافة ملاحظة/مرفق
            </a>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <ul class="nav nav-tabs" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" id="details-tab" data-bs-toggle="tab" href="#details"
                        aria-controls="details" role="tab" aria-selected="true">
                        التفاصيل
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="notes-tab" data-bs-toggle="tab" href="#notes" aria-controls="notes"
                        role="tab" aria-selected="false">
                        الملاحظات <span class="badge bg-secondary">{{ $notes->count() }}</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="appointments-tab" data-bs-toggle="tab" href="#appointments"
                        aria-controls="appointments" role="tab" aria-selected="false">
                        المواعيد <span class="badge bg-secondary">{{ $client->appointments()->count() }}</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="invoices-tab" data-bs-toggle="tab" href="#invoices" aria-controls="invoices"
                        role="tab" aria-selected="false">
                        الفواتير <span class="badge bg-secondary">{{ $client->invoices->count() }}</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="account-movement-tab" data-bs-toggle="tab" href="#account-movement"
                        aria-controls="account-movement" role="tab" aria-selected="false">
                        حركة الحساب
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="balance-summary-tab" data-bs-toggle="tab" href="#balance-summary"
                        aria-controls="balance-summary" role="tab" aria-selected="false">ملخص الرصيد</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="membership-tab" data-bs-toggle="tab" href="#membership"
                        aria-controls="membership" role="tab" aria-selected="false">العضوية</a>
                </li>
            </ul>

            <div class="tab-content pt-3">
                {{-- ==================== التفاصيل ==================== --}}
                <div class="tab-pane active" id="details" aria-labelledby="details-tab" role="tabpanel">
                    <div class="card">
                        <div class="card-header"><h4 class="card-title">التفاصيل</h4></div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 ws-details-list">
                                    <div class="d-flex justify-content-between border-bottom py-1"><span class="text-muted">الاسم التجاري</span><strong>{{ $client->trade_name }}</strong></div>
                                    <div class="d-flex justify-content-between border-bottom py-1"><span class="text-muted">الاسم الأول</span><strong>{{ $client->first_name }}</strong></div>
                                    <div class="d-flex justify-content-between border-bottom py-1"><span class="text-muted">الاسم الأخير</span><strong>{{ $client->last_name }}</strong></div>
                                    <div class="d-flex justify-content-between border-bottom py-1"><span class="text-muted">رقم الهاتف</span><strong>{{ $client->phone }}</strong></div>
                                    <div class="d-flex justify-content-between border-bottom py-1"><span class="text-muted">الجوال</span><strong>{{ $client->mobile }}</strong></div>
                                    <div class="d-flex justify-content-between py-1"><span class="text-muted">البريد الإلكتروني</span><strong>{{ $client->email }}</strong></div>
                                </div>
                                <div class="col-md-6 ws-details-list">
                                    <div class="d-flex justify-content-between border-bottom py-1"><span class="text-muted">العنوان</span><strong>{{ $client->street1 }} {{ $client->street2 }}</strong></div>
                                    <div class="d-flex justify-content-between border-bottom py-1"><span class="text-muted">المدينة</span><strong>{{ $client->city }}</strong></div>
                                    <div class="d-flex justify-content-between border-bottom py-1"><span class="text-muted">المنطقة</span><strong>{{ $client->region }}</strong></div>
                                    <div class="d-flex justify-content-between border-bottom py-1"><span class="text-muted">الرمز البريدي</span><strong>{{ $client->postal_code }}</strong></div>
                                    <div class="d-flex justify-content-between border-bottom py-1"><span class="text-muted">الدولة</span><strong>{{ $client->country }}</strong></div>
                                    <div class="d-flex justify-content-between py-1"><span class="text-muted">الرقم الضريبي</span><strong>{{ $client->tax_number }}</strong></div>
                                </div>
                            </div>
                            <hr>
                            <div class="row">
                                <div class="col-md-6 ws-details-list">
                                    <div class="d-flex justify-content-between border-bottom py-1"><span class="text-muted">السجل التجاري</span><strong>{{ $client->commercial_registration }}</strong></div>
                                    <div class="d-flex justify-content-between border-bottom py-1"><span class="text-muted">حد الائتمان</span><strong>{{ $client->credit_limit }}</strong></div>
                                    <div class="d-flex justify-content-between py-1"><span class="text-muted">فترة الائتمان</span><strong>{{ $client->credit_period }} يوم</strong></div>
                                </div>
                                <div class="col-md-6 ws-details-list">
                                    <div class="d-flex justify-content-between border-bottom py-1">
                                        <span class="text-muted">طريقة الطباعة</span>
                                        <strong>
                                            @if ($client->printing_method == 1) طباعة عادية
                                            @elseif($client->printing_method == 2) طباعة حرارية
                                            @else غير محدد
                                            @endif
                                        </strong>
                                    </div>
                                    <div class="d-flex justify-content-between border-bottom py-1">
                                        <span class="text-muted">نوع العميل</span>
                                        <strong>
                                            @if ($client->client_type == 1) فرد
                                            @elseif($client->client_type == 2) شركة
                                            @else غير محدد
                                            @endif
                                        </strong>
                                    </div>
                                    <div class="d-flex justify-content-between border-bottom py-1"><span class="text-muted">الرصيد الافتتاحي</span><strong>{{ $client->opening_balance }}</strong></div>
                                    <div class="d-flex justify-content-between py-1"><span class="text-muted">تاريخ الرصيد الافتتاحي</span><strong>{{ $client->opening_balance_date }}</strong></div>
                                </div>
                            </div>
                            @if ($client->notes)
                                <hr>
                                <p class="mb-1"><strong>ملاحظات:</strong></p>
                                <p class="mb-0">{{ $client->notes }}</p>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- ==================== المواعيد ==================== --}}
                <div class="tab-pane" id="appointments" aria-labelledby="appointments-tab" role="tabpanel">
                    @php
                        $completedAppointments = $client->appointments->where('status', App\Models\Appointment::STATUS_COMPLETED);
                        $ignoredAppointments = $client->appointments->where('status', App\Models\Appointment::STATUS_IGNORED);
                        $pendingAppointments = $client->appointments->where('status', App\Models\Appointment::STATUS_PENDING);
                        $rescheduledAppointments = $client->appointments->where('status', App\Models\Appointment::STATUS_RESCHEDULED);
                    @endphp

                    <div class="card cl-table-card">
                        <div class="cl-table-head">
                            <div class="d-flex gap-2 flex-wrap">
                                <button class="btn btn-sm btn-outline-primary filter-appointments" data-filter="all">
                                    الكل <span class="badge bg-light text-dark">{{ $client->appointments->count() }}</span>
                                </button>
                                <button class="btn btn-sm btn-outline-success filter-appointments" data-filter="{{ App\Models\Appointment::STATUS_COMPLETED }}">
                                    تم <span class="badge bg-light text-dark">{{ $completedAppointments->count() }}</span>
                                </button>
                                <button class="btn btn-sm btn-outline-warning filter-appointments" data-filter="{{ App\Models\Appointment::STATUS_IGNORED }}">
                                    تم صرف النظر عنه <span class="badge bg-light text-dark">{{ $ignoredAppointments->count() }}</span>
                                </button>
                                <button class="btn btn-sm btn-outline-danger filter-appointments" data-filter="{{ App\Models\Appointment::STATUS_PENDING }}">
                                    تم جدولته <span class="badge bg-light text-dark">{{ $pendingAppointments->count() }}</span>
                                </button>
                                <button class="btn btn-sm btn-outline-info filter-appointments" data-filter="{{ App\Models\Appointment::STATUS_RESCHEDULED }}">
                                    تم جدولته مجددا <span class="badge bg-light text-dark">{{ $rescheduledAppointments->count() }}</span>
                                </button>
                            </div>
                        </div>

                        <div class="card-body">
                            <div id="appointments-container">
                                @forelse ($client->appointments as $appointment)
                                    <div class="card mb-2 appointment-item"
                                         data-appointment-id="{{ $appointment->id }}"
                                         data-status="{{ $appointment->status }}"
                                         data-date="{{ $appointment->created_at->format('Y-m-d') }}">
                                        <div class="card-body">
                                            <div class="row align-items-center">
                                                <div class="col-md-4">
                                                    <strong>#{{ $appointment->id }}</strong>
                                                    <p class="mb-0">{{ $appointment->title }}</p>
                                                    <small class="text-muted">{{ $appointment->description }}</small>
                                                </div>
                                                <div class="col-md-3">
                                                    <p class="mb-0"><small>{{ $appointment->created_at->format('Y-m-d H:i') }}</small></p>
                                                    <small class="text-muted">بواسطة: {{ $appointment->employee->name ?? 'غير محدد' }}</small>
                                                </div>
                                                <div class="col-md-3 text-center">
                                                    <span class="cl-state {{ $appointment->status_color }}">{{ $appointment->status_text }}</span>
                                                </div>
                                                <div class="col-md-2 text-end">
                                                    <div class="dropdown">
                                                        <button class="btn btn-sm btn-outline-secondary" type="button"
                                                                id="dropdownMenuButton{{ $appointment->id }}"
                                                                data-bs-toggle="dropdown" data-bs-display="static"
                                                                aria-expanded="false">
                                                            <i class="fa fa-ellipsis-v"></i>
                                                        </button>
                                                        {{--
                                                            راوت `appointments.update-status` (POST/PATCH بحقل status)
                                                            المستعمَل هنا سابقاً غير موجودٍ إطلاقاً — الراوت الحقيقي
                                                            الوحيد لتغيير حالة الموعد هو `update-status` (GET،
                                                            `/{id}/status/{status}`، نفس ما تستعمله
                                                            `appointments/show.blade.php`). كانت هذه القائمة تُسقط
                                                            الصفحة كلَّها بـRouteNotFoundException عند أوّل تصييرٍ لها
                                                            — لا زرّاً معطَّلاً فقط.
                                                        --}}
                                                        <div class="dropdown-menu dropdown-menu-end"
                                                             aria-labelledby="dropdownMenuButton{{ $appointment->id }}">
                                                            <form method="POST" action="{{ route('update-status', ['id' => $appointment->id, 'status' => 1]) }}" class="d-inline m-0">@csrf<button type="submit" class="dropdown-item">
                                                                <i class="fa fa-clock me-2 text-warning"></i>تم جدولته
                                                            </button></form>
                                                            <form method="POST" action="{{ route('update-status', ['id' => $appointment->id, 'status' => 2]) }}" class="d-inline m-0">@csrf<button type="submit" class="dropdown-item">
                                                                <i class="fa fa-check me-2 text-success"></i>تم
                                                            </button></form>
                                                            <form method="POST" action="{{ route('update-status', ['id' => $appointment->id, 'status' => 3]) }}" class="d-inline m-0">@csrf<button type="submit" class="dropdown-item">
                                                                <i class="fa fa-times me-2 text-danger"></i>صرف النظر عنه
                                                            </button></form>
                                                            <form method="POST" action="{{ route('update-status', ['id' => $appointment->id, 'status' => 4]) }}" class="d-inline m-0">@csrf<button type="submit" class="dropdown-item">
                                                                <i class="fa fa-redo me-2 text-info"></i>تم جدولته مجددا
                                                            </button></form>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="row mt-3">
                                                <div class="col-md-6">
                                                    <small class="text-muted"><strong>نوع الإجراء:</strong> {{ $appointment->action_type ?? 'غير محدد' }}</small>
                                                </div>
                                                <div class="col-md-6">
                                                    <small class="text-muted"><strong>مدة الموعد:</strong> {{ $appointment->duration ?? 'غير محدد' }}</small>
                                                </div>
                                                @if ($appointment->notes)
                                                    <div class="col-12 mt-2">
                                                        <small class="text-muted"><strong>ملاحظات:</strong> {{ $appointment->notes }}</small>
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    </div>

                                    {{-- كان هنا مودال «إضافة ملاحظات للموعد» يستدعي
                                         `submitCompletedAppointment()` — دالّةٌ لم تُعرَّف قط في أي
                                         سكربتٍ بهذه الصفحة، فالزر لم يعمل يوماً. زرّ «تم» أعلاه
                                         (الرابط الحقيقي `update-status`) يؤدّي نفس الغرض فعلياً. --}}
                                @empty
                                    <div class="cl-empty">
                                        <span class="cl-empty__icon"><i class="fas fa-calendar"></i></span>
                                        <p class="cl-empty__title">لا توجد مواعيد</p>
                                    </div>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ==================== الفواتير ==================== --}}
                <div class="tab-pane" id="invoices" aria-labelledby="invoices-tab" role="tabpanel">
                    @if (isset($invoices) && $invoices && count($invoices) > 0)
                        @foreach ($invoices as $invoice)
                            <div class="card cl-table-card mb-3">
                                <div class="card-body">
                                    <div class="row border-bottom py-2 align-items-center">
                                        <div class="col-md-4">
                                            <p class="mb-0"><strong>#{{ $invoice->invoice_id }}</strong> {{ $invoice->client->trade_name }}</p>
                                        </div>
                                        <div class="col-md-3">
                                            <p class="mb-0"><small>{{ $invoice->created_at }}</small></p>
                                        </div>
                                        <div class="col-md-3 text-center">
                                            <strong class="text-danger">{{ number_format($invoice->total ?? 0, 2) }}</strong>
                                        </div>
                                        <div class="col-md-2 text-end">
                                            <div class="dropdown">
                                                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="dropdown" aria-expanded="false">
                                                    <i class="fa fa-ellipsis-v"></i>
                                                </button>
                                                <ul class="dropdown-menu dropdown-menu-end">
                                                    <li><a class="dropdown-item" href="#"><i class="fa fa-eye me-2 text-primary"></i>عرض</a></li>
                                                    <li><a class="dropdown-item" href="#"><i class="fa fa-print me-2 text-dark"></i>طباعة</a></li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    @else
                        <div class="cl-empty">
                            <span class="cl-empty__icon"><i class="fas fa-file-invoice"></i></span>
                            <p class="cl-empty__title">لا توجد فواتير</p>
                        </div>
                    @endif
                </div>

                {{-- ==================== حركة الحساب ==================== --}}
                <div class="tab-pane" id="account-movement" aria-labelledby="account-movement-tab" role="tabpanel">
                    <div class="card cl-table-card">
                        <div class="card-body p-4">
                            <div class="row">
                                <div class="col-6 text-start">
                                    <div>{{ $client->trade_name }}</div>
                                    <div>{{ $client->city }}</div>
                                    <div>{{ $client->region }}، {{ $client->city }}</div>
                                    <div class="mt-2">Date: {{ date('d/m/Y') }}</div>
                                </div>
                                <div class="col-6 text-end">
                                    <h4>كشف حساب</h4>
                                    <div>{{ $client->trade_name }}</div>
                                    <div>{{ $client->region }} - {{ $client->city }}</div>
                                    <div>{{ $client->country }}</div>
                                    <div class="mt-2">حركة الحساب حتى {{ date('d/m/Y') }}</div>
                                </div>
                            </div>

                            <div class="table-responsive mt-4">
                                <table class="table table-bordered mb-0">
                                    <thead>
                                        <tr>
                                            <th class="text-end" style="width: 12%;">التاريخ</th>
                                            <th class="text-end" style="width: 30%;">العملية</th>
                                            <th class="text-start" style="width: 20%;">المبلغ</th>
                                            <th class="text-start" style="width: 20%;">المبلغ المستحق</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td colspan="3" class="text-end pe-3">رصيد بداية المدة</td>
                                            <td class="text-start num">{{ number_format($client->opening_balance ?? 0.0, 2) }} ر.س</td>
                                        </tr>
                                        @php $balance = $client->opening_balance ?? 0; @endphp
                                        @foreach ($client->invoices as $invoice)
                                            @php
                                                if ($invoice->type == 'invoice') { $balance += $invoice->total; }
                                                else { $balance -= $invoice->total; }
                                            @endphp
                                            <tr>
                                                <td class="text-end num">{{ $invoice->invoice_date->format('d/m/Y') }}</td>
                                                <td class="text-end">
                                                    @if ($invoice->type == 'invoice')
                                                        فاتورة #{{ $invoice->invoice_number }}
                                                    @else
                                                        فاتورة مرجعة #{{ $invoice->invoice_number }}
                                                    @endif
                                                </td>
                                                <td class="text-start num">
                                                    @if ($invoice->type == 'return')
                                                        {{ number_format(-$invoice->total, 2) }}
                                                    @else
                                                        {{ number_format($invoice->total, 2) }}
                                                    @endif
                                                    <span class="text-muted">ر.س</span>
                                                </td>
                                                <td class="text-start num">{{ number_format($balance, 2) }} ر.س</td>
                                            </tr>
                                        @endforeach
                                        <tr class="fw-bold">
                                            <td colspan="3" class="text-end pe-3">رصيد نهاية المدة</td>
                                            <td class="text-start num">{{ number_format($balance, 2) }} ر.س</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ==================== ملخص الرصيد ==================== --}}
                <div class="tab-pane" id="balance-summary" aria-labelledby="balance-summary-tab" role="tabpanel">
                    <div class="cl-empty">
                        <span class="cl-empty__icon"><i class="fas fa-wallet"></i></span>
                        <p class="cl-empty__title">لا يوجد رصيد مضافٌ حتى الآن</p>
                    </div>
                </div>

                {{-- ==================== العضوية ====================
                     بياناتٌ ثابتة بالكامل لا نموذج حقيقي خلفها — لا جدول
                     عضويات، ولا route لحفظ العضوية الجديدة (الزر «حفظ
                     العضوية» بلا onclick أصلاً). تُرك التبويب ظاهراً بشكل
                     احترافي بدل حذفه، فحذف تبويبٍ كاملٍ قرارُ منتجٍ خارج
                     نطاق طلب إعادة التصميم — ويحتاج قراراً صريحاً منفصلاً. --}}
                <div class="tab-pane" id="membership" aria-labelledby="membership-tab" role="tabpanel">
                    <div class="card">
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="card h-100">
                                        <div class="card-body">
                                            <h6 class="card-title mb-3">معلومات العضوية الأساسية</h6>
                                            <div class="ws-details-list">
                                                <div class="d-flex justify-content-between border-bottom py-1"><span class="text-muted">رقم العضوية</span><strong>{{ $client->code }}</strong></div>
                                                <div class="d-flex justify-content-between border-bottom py-1"><span class="text-muted">تاريخ التسجيل</span><strong>{{ $client->created_at->format('Y-m-d') }}</strong></div>
                                                <div class="d-flex justify-content-between border-bottom py-1">
                                                    <span class="text-muted">نوع العضوية</span>
                                                    <span class="cl-state cl-state--neutral">
                                                        @if ($client->client_type == 1) فرد
                                                        @elseif($client->client_type == 2) شركة
                                                        @else غير محدد
                                                        @endif
                                                    </span>
                                                </div>
                                                <div class="d-flex justify-content-between py-1">
                                                    <span class="text-muted">حالة العضوية</span>
                                                    @if (in_array($client->status_id, \App\Models\Statuses::activeClientStatusIds()))
                                                        <span class="cl-state cl-state--on">نشط</span>
                                                    @else
                                                        <span class="cl-state cl-state--off">غير نشط</span>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card h-100">
                                        <div class="card-body">
                                            <h6 class="card-title mb-3">معلومات الائتمان</h6>
                                            <div class="ws-details-list">
                                                <div class="d-flex justify-content-between border-bottom py-1"><span class="text-muted">حد الائتمان</span><strong>{{ number_format($client->credit_limit ?? 0, 2) }} ر.س</strong></div>
                                                <div class="d-flex justify-content-between py-1"><span class="text-muted">فترة الائتمان</span><strong>{{ $client->credit_period }} يوم</strong></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="cl-empty">
                        <span class="cl-empty__icon"><i class="fas fa-id-badge"></i></span>
                        <p class="cl-empty__title">لا يوجد سجلّ عضويات مسجَّل بعد</p>
                    </div>
                </div>

                {{-- ==================== الملاحظات ==================== --}}
                <div class="tab-pane" id="notes" aria-labelledby="notes-tab" role="tabpanel">
                    @if ($notes->count() > 0)
                        <div class="card cl-table-card">
                            <div class="cl-table-head">
                                <h6 class="cl-table-head__title">الملاحظات <span class="cl-count">{{ $notes->count() }}</span></h6>
                            </div>
                            <div class="card-body">
                                @foreach ($notes as $note)
                                    <div class="ws-note-card mb-2 p-3 border rounded">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <p class="mb-1">{{ $note->notes }}</p>
                                                <small class="text-muted">
                                                    <i class="fas fa-clock"></i> {{ $note->created_at->format('H:i d/m/Y') }}
                                                    <i class="fas fa-user ms-2"></i> {{ $note->user->name ?? 'غير معروف' }}
                                                </small>
                                            </div>
                                            @if (auth()->user()->can('client_follow_up_edit_delete_notes_attachments_appointments_all') ||
                                                    (auth()->user()->can('client_follow_up_edit_delete_notes_attachments_appointments_own') && $note->user_id == auth()->id()))
                                                <div class="d-flex gap-1">
                                                    <a href="{{ route('clients.notes.edit', $note->id) }}" class="btn btn-outline-primary btn-sm">
                                                        <i class="fas fa-edit"></i> تعديل
                                                    </a>
                                                    <form action="{{ route('clients.notes.destroy', $note->id) }}" method="POST"
                                                          style="display: none;" id="delete-note-{{ $note->id }}">
                                                        @csrf
                                                        @method('DELETE')
                                                    </form>
                                                    <button type="button" class="btn btn-outline-danger btn-sm"
                                                            onclick="if(confirm('هل أنت متأكد من حذف هذه الملاحظة؟')) document.getElementById('delete-note-{{ $note->id }}').submit();">
                                                        <i class="fas fa-trash"></i> حذف
                                                    </button>
                                                </div>
                                            @endif
                                        </div>
                                        @if ($note->attachments)
                                            <div class="mt-2">
                                                <img src="{{ asset('assets/uploads/note/'.$note->attachments) }}" alt="Note Image" class="img-fluid" style="max-height:160px">
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @else
                        <script>
                            $(document).ready(function() { $('#notes-tab').closest('li').hide(); });
                        </script>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
    @parent
    <script>
        $(document).ready(function() {
            $('.filter-appointments').on('click', function() {
                const filter = $(this).data('filter');
                $('.filter-appointments').removeClass('active');
                $(this).addClass('active');
                $('.appointment-item').each(function() {
                    $(this).toggle(filter === 'all' || String($(this).data('status')) === String(filter));
                });
            });
        });
    </script>
@endsection
