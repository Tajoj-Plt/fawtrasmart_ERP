{{--
    عرض تفاصيل موعد — بنظام تصميم الوحدة الحقيقي (`.rsv-scope` + page-head)
    بدل نظامٍ مخترَع. لم يكن لهذا الراوت عرضٌ إطلاقاً من قبل (500 دائم).
--}}
@extends('client::reservations.master')

@section('title', 'تفاصيل الموعد')

@section('content')

<div class="rsv-scope">
    @php
        $statusMap = [
            1 => ['label' => 'قيد الانتظار', 'state' => 'rsv-state--neutral'],
            2 => ['label' => 'مكتمل', 'state' => 'rsv-state--on'],
            3 => ['label' => 'ملغي', 'state' => 'rsv-state--off'],
            4 => ['label' => 'معاد جدولته', 'state' => 'rsv-state--neutral'],
        ];
        $st = $statusMap[$appointment->status] ?? ['label' => 'غير معروف', 'state' => 'rsv-state--off'];
        $pageActions = '<a href="'.route('appointments.index').'" class="btn btn-outline-secondary"><i class="fa fa-arrow-right"></i> <span>رجوع للقائمة</span></a>';
    @endphp

    @include('client::reservations.components.page-head', [
        'title' => 'موعد مع '.($client->trade_name ?? 'عميل'),
        'icon' => 'calendar',
        'sub' => \Carbon\Carbon::parse($appointment->appointment_date)->format('Y-m-d').' — '.$appointment->time,
        'crumbs' => [
            'الرئيسية' => route('dashboard'),
            'المواعيد' => route('appointments.index'),
            'تفاصيل' => null,
        ],
        'actions' => $pageActions,
    ])

    <div class="row">
        <div class="col-lg-8 col-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title"><i class="feather icon-info text-primary"></i> معلومات الموعد</h4>
                    <span class="rsv-state {{ $st['state'] }}">{{ $st['label'] }}</span>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <tbody>
                                <tr><th style="width:35%">العميل</th><td>{{ $client->display_name ?? '—' }} ({{ $client->code ?? '' }})</td></tr>
                                <tr><th>التاريخ</th><td>{{ \Carbon\Carbon::parse($appointment->appointment_date)->format('Y-m-d') }}</td></tr>
                                <tr><th>الوقت</th><td>{{ $appointment->time }}</td></tr>
                                <tr><th>المدّة</th><td>{{ $appointment->duration ? $appointment->duration.' دقيقة' : '—' }}</td></tr>
                                <tr><th>نوع الإجراء</th><td>{{ $appointment->action_type ?? '—' }}</td></tr>
                                <tr><th>الموظفون المسؤولون</th><td>{{ $appointment->employeeNames() ?? '—' }}</td></tr>
                                <tr><th>أنشأه</th><td>{{ $appointment->createdBy->name ?? '—' }}</td></tr>
                                <tr><th>مشاركة مع العميل</th><td>{{ $appointment->share_with_client ? 'نعم — بريد وإشعار وبوابة العميل' : 'لا' }}</td></tr>
                                @if ($appointment->is_recurring)
                                    <tr><th>تكرار</th><td>{{ \App\Models\Appointment::RECURRENCE_LABELS[$appointment->recurrence_type] ?? 'نعم' }} — حتى {{ $appointment->recurrence_date ?? '—' }}</td></tr>
                                @elseif ($appointment->recurrenceParent)
                                    <tr><th>تكرار</th><td>مولَّد من <a href="{{ route('appointments.show', $appointment->recurrence_parent_id) }}">الموعد الأصلي #{{ $appointment->recurrence_parent_id }}</a></td></tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            @if ($appointment->notes)
                <div class="card">
                    <div class="card-header"><h4 class="card-title"><i class="feather icon-file-text text-primary"></i> الملاحظات</h4></div>
                    <div class="card-body">
                        <p style="white-space: pre-wrap; margin: 0;">{{ $appointment->notes }}</p>
                    </div>
                </div>
            @endif
        </div>

        <div class="col-lg-4 col-12">
            <div class="card">
                <div class="card-header"><h4 class="card-title"><i class="feather icon-user text-primary"></i> بيانات العميل</h4></div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <tbody>
                                <tr><th>الاسم التجاري</th><td>{{ $client->trade_name ?? '—' }}</td></tr>
                                <tr><th>الهاتف</th><td>{{ $client->phone ?? '—' }}</td></tr>
                                <tr><th>المدينة</th><td>{{ $client->city ?? '—' }}</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <a href="{{ route('clients.show', $client->id) }}" class="btn btn-outline-primary w-100">
                        <i class="feather icon-external-link"></i> فتح ملف العميل
                    </a>
                </div>
            </div>

            @if ($appointment->reservation_id)
                {{-- موعدُ حجز: صورةٌ عن حجزه — لا تعديل ولا حذف ولا تغييرَ حالةٍ من هنا --}}
                <div class="card">
                    <div class="card-header"><h4 class="card-title"><i class="feather icon-lock text-info"></i> {{ __('reservations.links.from_reservation') }}</h4></div>
                    <div class="card-body d-flex flex-column gap-2">
                        <p class="text-muted mb-1">{{ __('reservations.links.appointment_locked_hint', ['code' => $reservationCode ?? '#'.$appointment->reservation_id]) }}</p>
                        @if (Route::has('Reservations.show'))
                            <a href="{{ route('Reservations.show', $appointment->reservation_id) }}" class="btn btn-outline-info btn-sm">
                                <i class="fa fa-calendar-check"></i> {{ __('reservations.links.open_reservation') }}
                            </a>
                        @endif
                    </div>
                </div>
            @else
                @permission('client_follow_up_edit_delete_notes_attachments_appointments_all|client_follow_up_edit_delete_notes_attachments_appointments_assigned|client_follow_up_edit_delete_notes_attachments_appointments_own')
                <div class="card">
                    <div class="card-header"><h4 class="card-title"><i class="feather icon-zap text-warning"></i> إجراءات سريعة</h4></div>
                    <div class="card-body d-flex flex-column gap-2">
                        <a href="{{ route('appointments.edit', $appointment->id) }}" class="btn btn-outline-primary btn-sm"><i class="fa fa-edit"></i> تعديل</a>
                        {{-- مسارُ الحالة POST: كانت روابطَ GET يردّها الخادم 405 --}}
                        <form method="POST" action="{{ route('update-status', ['id' => $appointment->id, 'status' => 2]) }}" class="m-0">@csrf
                            <button type="submit" class="btn btn-outline-success btn-sm w-100"><i class="fa fa-check"></i> تعليم كمكتمل</button>
                        </form>
                        <form method="POST" action="{{ route('update-status', ['id' => $appointment->id, 'status' => 3]) }}" class="m-0">@csrf
                            <button type="submit" class="btn btn-outline-danger btn-sm w-100"><i class="fa fa-times"></i> إلغاء</button>
                        </form>
                        <form method="POST" action="{{ route('update-status', ['id' => $appointment->id, 'status' => 4]) }}" class="m-0">@csrf
                            <button type="submit" class="btn btn-outline-info btn-sm w-100"><i class="fa fa-redo"></i> إعادة جدولة</button>
                        </form>
                        <form method="POST" action="{{ route('appointments.destroy', $appointment->id) }}" class="m-0 mt-2"
                              onsubmit="return confirm('هل أنت متأكد من حذف هذا الموعد؟ لا يمكن التراجع عن هذه العملية.');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger btn-sm w-100"><i class="fa fa-trash"></i> حذف الموعد</button>
                        </form>
                    </div>
                </div>
                @endpermission
            @endif
        </div>
    </div>
</div>
@endsection
