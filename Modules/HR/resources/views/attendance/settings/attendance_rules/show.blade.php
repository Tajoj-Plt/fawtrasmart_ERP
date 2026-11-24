@extends('hr::master')

@section('title', 'تفاصيل قاعدة الحضور')

@section('css')
    <link rel="stylesheet" href="{{ asset('assets/css/logs.css') }}">
@endsection

@section('page-actions')
    @permission('attendance_rules.edit')
        <a href="{{ route('attendance-rules.edit', $attendanceRule->id) }}" class="btn btn-primary">
            <i class="fa fa-edit"></i>
            <span>تعديل</span>
        </a>
    @endpermission
    <div class="btn-group">
        <button type="button" class="btn btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
            <i class="fa fa-ellipsis-h"></i>
            <span>المزيد</span>
        </button>
        <ul class="dropdown-menu dropdown-menu-end">
            <li>
                <a class="dropdown-item" href="{{ route('attendance-rules.index') }}">
                    <i class="fa fa-list me-2 text-primary"></i>العودة للقائمة
                </a>
            </li>
            @permission('attendance_rules.destroy')
                <li><hr class="dropdown-divider"></li>
                <li>
                    <a class="dropdown-item text-danger" href="#" data-toggle="modal" data-target="#modal_DELETE{{ $attendanceRule->id }}">
                        <i class="fa fa-trash me-2"></i>حذف
                    </a>
                </li>
            @endpermission
        </ul>
    </div>
@endsection

@section('content')
    @include('hr::components.table-menu')
    @include('hr::attendance._partials.styles')

@php
    $ruleActive = $attendanceRule->status == 'active';
    $ruleLogsCount = $logs ? collect($logs)->flatten()->count() : 0;
@endphp

<div class="content-body">
    @include('stock::components.page-head', [
        'title' => $attendanceRule->name,
        'icon' => 'clipboard',
        'crumbs' => ['الرئيسية' => url('/'), 'قواعد الحضور' => route('attendance-rules.index'), $attendanceRule->name => null],
    ])

    @include('layouts.alerts.error')
    @include('layouts.alerts.success')

    <div class="row">
        {{-- ══════════ بطاقة الهوية والتفاصيل ══════════ --}}
        <div class="col-lg-4 col-12">
            <div class="card overflow-hidden">
                <div class="card-body text-center">
                    <span class="stk-page-head__icon mx-auto mb-1" aria-hidden="true" style="background: {{ $attendanceRule->color }}22; color: {{ $attendanceRule->color }}"><i class="feather icon-clipboard"></i></span>
                    <h4 class="mb-25">{{ $attendanceRule->name }}</h4>
                    <p class="stk-help mt-0 mb-1">قاعدة حضور رقم #{{ $attendanceRule->id }}</p>
                    <span class="stk-state stk-state--{{ $ruleActive ? 'on' : 'off' }}">{{ $attendanceRule->status_label }}</span>
                </div>
                <div class="stk-metrics" style="margin: 0; border-width: 1px 0 0; border-radius: 0; box-shadow: none">
                    <div class="stk-metric">
                        <span class="stk-metric__label">الوردية</span>
                        <span class="stk-metric__value" style="font-size:1rem">{{ $attendanceRule->shift->name ?? '—' }}</span>
                    </div>
                    <div class="stk-metric">
                        <span class="stk-metric__label">سجلات النشاط</span>
                        <span class="stk-metric__value">{{ $ruleLogsCount }}</span>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h4 class="card-title"><i class="feather icon-file-text"></i> التفاصيل</h4></div>
                <div class="card-body">
                    <dl class="stk-def">
                        <div class="stk-def__row">
                            <dt>اسم القاعدة</dt>
                            <dd>{{ $attendanceRule->name }}</dd>
                        </div>
                        <div class="stk-def__row">
                            <dt>الوصف</dt>
                            <dd>{{ $attendanceRule->description ?? 'لا يوجد وصف' }}</dd>
                        </div>
                        <div class="stk-def__row">
                            <dt>اللون</dt>
                            <dd>
                                <span class="d-inline-flex align-items-center">
                                    <span style="width: 18px; height: 18px; background-color: {{ $attendanceRule->color }}; border-radius: 4px; margin-left: 8px; border: 1px solid var(--app-border, #dee2e6); display:inline-block"></span>
                                    <code>{{ $attendanceRule->color }}</code>
                                </span>
                            </dd>
                        </div>
                        <div class="stk-def__row">
                            <dt>الوردية المرتبطة</dt>
                            <dd>{{ $attendanceRule->shift->name ?? '—' }}</dd>
                        </div>
                        <div class="stk-def__row">
                            <dt>الحالة</dt>
                            <dd>{{ $attendanceRule->status_label }}</dd>
                        </div>
                        <div class="stk-def__row">
                            <dt>تاريخ الإنشاء</dt>
                            <dd class="stk-num">
                                {{ $attendanceRule->created_at->format('d/m/Y H:i') }}
                                <small class="stk-help d-block">{{ $attendanceRule->created_at->diffForHumans() }}</small>
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>
        </div>

        {{-- ══════════ الإعدادات والسجل ══════════ --}}
        <div class="col-lg-8 col-12">
            <div class="card">
                <div class="card-body pb-0">
                    <ul class="nav nav-tabs stk-tabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" id="details-tab" data-toggle="tab" href="#details" role="tab"
                                aria-controls="details" aria-selected="true">
                                <i class="feather icon-settings"></i> إعدادات القاعدة
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="activity-log-tab" data-toggle="tab" href="#activity-log" role="tab"
                                aria-controls="activity-log" aria-selected="false">
                                <i class="feather icon-rotate-ccw"></i> سجل النشاطات
                                <span class="stk-count">{{ $ruleLogsCount }}</span>
                            </a>
                        </li>
                    </ul>
                </div>

                <div class="tab-content">
                    <!-- إعدادات القاعدة -->
                    <div class="tab-pane fade show active" id="details" role="tabpanel" aria-labelledby="details-tab">
                        @php
                            /* ما تضبطه القاعدةُ فعلاً — لا نصُّ «الصيغة» الخام الذي لا يقرؤه إلّا المحرّك */
                            $ruleOverrides = app(\App\Services\Attendance\AttendanceRuleOverrides::class);
                            $ruleFields = \App\Services\Attendance\AttendanceRuleOverrides::fields();
                            $ruleValues = $ruleOverrides->valuesOf($attendanceRule->formula, $attendanceRule->condition);
                            $ruleLeftover = $ruleOverrides->leftover($attendanceRule->formula, $attendanceRule->condition);
                        @endphp
                        <div class="card-body">
                            @if ($ruleValues)
                                <dl class="stk-def">
                                    @foreach ($ruleFields as $key => $meta)
                                        @if (isset($ruleValues[$key]))
                                            <div class="stk-def__row">
                                                <dt>{{ $meta['label'] }}</dt>
                                                <dd>
                                                    <span class="stk-num fw-bold">{{ $ruleValues[$key] }}</span> {{ $meta['unit'] }}
                                                    <div class="text-muted" style="font-size:.78rem;">{{ $meta['hint'] }}</div>
                                                </dd>
                                            </div>
                                        @endif
                                    @endforeach
                                </dl>
                            @else
                                <div class="alert alert-warning mb-0" role="alert">
                                    <strong><i class="feather icon-alert-triangle"></i> هذه القاعدة لا تضبط شيئاً.</strong>
                                    <div class="mt-25" style="font-size:.82rem;">
                                        لا قيمةَ محفوظةً فيها يقرؤها محرّكُ الحضور، فوجودُها «نشطةً» لا أثر له.
                                        افتح «تعديل» واضبط ما تريده.
                                    </div>
                                </div>
                            @endif

                            @if ($ruleLeftover)
                                <div class="alert alert-warning mt-1 mb-0" role="alert">
                                    <strong><i class="feather icon-file-text"></i> نصٌّ محفوظٌ لم يكن له أثر:</strong>
                                    <div class="mt-25" style="font-size:.82rem;">{{ $ruleLeftover }}</div>
                                </div>
                            @endif
                        </div>
                    </div>

                    <!-- سجل النشاطات -->
                    <div class="tab-pane fade" id="activity-log" role="tabpanel" aria-labelledby="activity-log-tab">
                        <div class="card-body">
                            @if ($logs && count($logs) > 0)
                                @php
                                    $previousDate = null;
                                @endphp

                                @foreach ($logs as $date => $dayLogs)
                                    @php
                                        $currentDate = \Carbon\Carbon::parse($date);
                                        $diffInDays = $previousDate
                                            ? $previousDate->diffInDays($currentDate)
                                            : 0;
                                    @endphp

                                    @if ($diffInDays > 7)
                                        <div class="timeline-date">
                                            <h4>{{ $currentDate->format('Y-m-d') }}</h4>
                                        </div>
                                    @endif

                                    <div class="timeline-day">
                                        {{ $currentDate->locale('ar')->translatedFormat('l، d F Y') }}
                                    </div>

                                    <ul class="timeline">
                                        @foreach ($dayLogs as $log)
                                            @if ($log)
                                                <li class="timeline-item">
                                                    <div class="timeline-content">
                                                        <div class="timeline-header d-flex justify-content-between align-items-center">
                                                            <div class="time">
                                                                <i class="feather icon-clock me-1"></i>
                                                                {{ $log->created_at->format('H:i:s') }}
                                                            </div>
                                                            <span class="badge badge-{{ $log->log_level ?? 'info' }}">
                                                                {{ $log->log_name ?? 'نشاط' }}
                                                            </span>
                                                        </div>
                                                        <div class="timeline-body mt-2">
                                                            <div class="user-info mb-2">
                                                                <strong>
                                                                    <i class="feather icon-user me-1"></i>
                                                                    {{ $log->user->name ?? 'مستخدم غير معروف' }}
                                                                </strong>
                                                                <span class="text-muted ms-2">
                                                                    <i class="fa fa-building me-1"></i>
                                                                    {{ $log->user->branch->name ?? 'فرع غير معروف' }}
                                                                </span>
                                                            </div>
                                                            <div class="activity-description">
                                                                {!! Str::markdown($log->description ?? 'لا يوجد وصف') !!}
                                                            </div>
                                                            @if($log->properties && count($log->properties) > 0)
                                                                <div class="activity-properties mt-2">
                                                                    <small class="stk-help">
                                                                        <i class="feather icon-info me-1"></i>
                                                                        تفاصيل إضافية متاحة
                                                                    </small>
                                                                </div>
                                                            @endif
                                                        </div>
                                                    </div>
                                                </li>
                                            @endif
                                        @endforeach
                                    </ul>

                                    @php
                                        $previousDate = $currentDate;
                                    @endphp
                                @endforeach
                            @else
                                <div class="stk-empty">
                                    <div class="stk-empty__icon"><i class="feather icon-rotate-ccw"></i></div>
                                    <h5 class="stk-empty__title">لا توجد سجلات نشاط</h5>
                                    <p class="stk-empty__hint">لا توجد سجلات نشاط لهذه القاعدة حتى الآن!</p>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal حذف -->
    <div class="modal fade" id="modal_DELETE{{ $attendanceRule->id }}" tabindex="-1" role="dialog"
         aria-labelledby="myModalLabel1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content text-right">
                <div class="modal-header">
                    <h5 class="modal-title" id="myModalLabel1">حذف {{ $attendanceRule->name }}</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="إغلاق">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p class="mb-1">
                        هل أنت متأكد من حذف قاعدة الحضور
                        <strong>"{{ $attendanceRule->name }}"</strong>؟
                    </p>
                    <p class="stk-empty__hint mb-0">لا يمكن التراجع عن الحذف.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">إلغاء</button>
                    {{-- زرّ التأكيد كان مفقوداً: النافذة تُفتح ولا شيء يحذف —
                         يُرسل النموذجَ المخفيّ أسفل الصفحة --}}
                    @permission('attendance_rules.destroy')
                        <button type="submit" form="delete-form-{{ $attendanceRule->id }}" class="btn btn-danger">
                            <i class="feather icon-trash-2"></i> تأكيد الحذف
                        </button>
                    @endpermission
                </div>
            </div>
        </div>
    </div>

    <!-- نموذج الحذف المخفي -->
    <form id="delete-form-{{ $attendanceRule->id }}"
          action="{{ route('attendance-rules.destroy', $attendanceRule->id) }}"
          method="POST" style="display: none;">
        @csrf
        @method('DELETE')
    </form>
</div>

@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
$(document).ready(function() {
    // تفعيل tooltips
    $('[title]').tooltip();

    // إضافة تأثيرات للتابات
    // مقصورٌ على تابات هذه الصفحة: '.nav-link' وحدها تلتقط روابط الشريط
    // العلوي أيضاً، فيؤخذ href الحقيقي («http://…») محدِّدَ jQuery فيرمي
    // استثناءً وتُطفَأ كل الألواح عند كل نقرةٍ على القائمة.
    $('.nav-tabs a.nav-link[data-bs-toggle="tab"], .nav-tabs a.nav-link[data-toggle="tab"]').on('click', function() {
        const target = $(this).attr('href');
        if (!target || target.charAt(0) !== '#') return;
        $('.tab-pane').removeClass('show active');
        $(target).addClass('show active');
    });

    // تأثيرات hover للجداول
    $('.table tbody tr').hover(
        function() {
            $(this).addClass('table-active');
        },
        function() {
            $(this).removeClass('table-active');
        }
    );

    // Display messages
    @if(session('success'))
        Swal.fire({
            icon: 'success',
            title: 'تم بنجاح!',
            text: '{{ session('success') }}',
            timer: 3000,
            showConfirmButton: false,
            toast: true,
            position: 'top-end'
        });
    @endif

    @if(session('error'))
        Swal.fire({
            icon: 'error',
            title: 'خطأ!',
            text: '{{ session('error') }}',
            confirmButtonText: 'موافق'
        });
    @endif
});

// دالة تغيير حالة القاعدة من صفحة التفاصيل
function toggleRuleStatus(ruleId, currentStatus) {
    const newStatus = currentStatus === 'active' ? 'inactive' : 'active';
    const statusText = newStatus === 'active' ? 'تفعيل' : 'إلغاء تفعيل';

    Swal.fire({
        title: 'هل أنت متأكد؟',
        text: `سيتم ${statusText} قاعدة الحضور`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: `نعم، ${statusText}`,
        cancelButtonText: 'إلغاء',
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: `/attendance-rules/${ruleId}/toggle-status`,
                method: 'PATCH',
                data: {
                    _token: $('input[name="_token"]').val()
                },
                success: function(response) {
                    if (response.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'تم بنجاح!',
                            text: response.message,
                            timer: 2000,
                            showConfirmButton: false
                        });

                        // إعادة تحميل الصفحة لتحديث الحالة
                        setTimeout(() => {
                            location.reload();
                        }, 1500);
                    } else {
                        Swal.fire('خطأ!', response.message, 'error');
                    }
                },
                error: function() {
                    Swal.fire('خطأ!', 'حدث خطأ أثناء تغيير الحالة', 'error');
                }
            });
        }
    });
}
</script>
@endpush