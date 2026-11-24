@extends('hr::master')

@section('title', 'مراجعة طلب تصحيح حضور')

@php
    $tone = ['pending' => 'warning', 'approved' => 'success'][$correction->status] ?? 'danger';
    $dateLabel = \Carbon\Carbon::parse($correction->attendance_date)->locale('ar')->translatedFormat('l, d/m/Y');
@endphp

@section('page-actions')
    @if ($correction->status === 'pending')
        {{-- الزرّان ينقلان إلى نموذجَي القرار أسفل الصفحة: الاعتمادُ يقبل ملاحظة، والرفضُ يستلزم سبباً --}}
        <a href="#decision-approve" class="btn btn-success">
            <i class="feather icon-check"></i> اعتماد
        </a>
        <a href="#decision-reject" class="btn btn-outline-danger">
            <i class="feather icon-x"></i> رفض
        </a>
    @endif
@endsection

@section('content')
<div class="content-body">
    @include('stock::components.page-head', [
        'title' => $correction->employee->full_name ?? '—',
        'icon' => 'check-square',
        'crumbs' => ['الرئيسية' => url('/'), 'اعتماد تصحيحات الحضور' => route('hr.attendance-correction-approvals.index'), 'طلب #'.$correction->id => null],
    ])

    @include('layouts.alerts.success')
    @include('layouts.alerts.error')
    {{-- تنبيهُ الخصميّة المسوّاة بعد الاعتماد يصل هنا — ولولاه ضاع بلا أثر --}}
    @include('layouts.alerts.warning')

    <div class="row">
        <div class="col-lg-4 col-12">
            <div class="card overflow-hidden">
                <div class="card-body text-center">
                    <span class="stk-page-head__icon mx-auto mb-1" aria-hidden="true"><i class="feather icon-check-square"></i></span>
                    <h4 class="mb-25">{{ $correction->employee->full_name ?? '—' }}</h4>
                    <p class="stk-help mt-0 mb-1">{{ $dateLabel }}</p>
                    <span class="badge badge-{{ $tone }}">
                        {{ $correction->status === 'pending' ? 'بانتظار قرارك' : $correction->statusLabel() }}
                    </span>
                </div>
                <div class="stk-metrics" style="margin: 0; border-width: 1px 0 0; border-radius: 0; box-shadow: none">
                    <div class="stk-metric">
                        <span class="stk-metric__label">الحالة المطلوبة</span>
                        <span class="stk-metric__value">{{ $correction->requestedStatusLabel() }}</span>
                    </div>
                    <div class="stk-metric">
                        <span class="stk-metric__label">الحالة الحالية</span>
                        <span class="stk-metric__value">{{ $correction->currentStatusLabel() ?? '—' }}</span>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h4 class="card-title"><i class="feather icon-file-text"></i> التفاصيل</h4></div>
                <div class="card-body">
                    <dl class="stk-def">
                        <div class="stk-def__row">
                            <dt>سبب الطلب</dt>
                            <dd>{{ $correction->reason }}</dd>
                        </div>
                        <div class="stk-def__row">
                            <dt>مقدِّم الطلب</dt>
                            <dd>{{ $correction->requestedBy->name ?? '—' }} — <span class="stk-num">{{ $correction->created_at->format('Y-m-d H:i') }}</span></dd>
                        </div>
                        @if ($correction->status !== 'pending')
                            <div class="stk-def__row">
                                <dt>{{ $correction->status === 'approved' ? 'اعتمده' : 'رفضه' }}</dt>
                                <dd>{{ $correction->approvedBy->name ?? '—' }} — <span class="stk-num">{{ optional($correction->approved_at)->format('Y-m-d H:i') }}</span></dd>
                            </div>
                            @if ($correction->status === 'rejected')
                                <div class="stk-def__row">
                                    <dt>سبب الرفض</dt>
                                    <dd>{{ $correction->rejection_reason }}</dd>
                                </div>
                            @endif
                            @if ($correction->admin_notes)
                                <div class="stk-def__row">
                                    <dt>ملاحظاتك</dt>
                                    <dd>{{ $correction->admin_notes }}</dd>
                                </div>
                            @endif
                        @endif
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-lg-8 col-12">
            <div class="card stk-table-card">
                <div class="stk-table-head">
                    <h6 class="stk-table-head__title"><i class="feather icon-repeat"></i> قبل / بعد</h6>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover table-striped mb-0">
                        <thead>
                            <tr>
                                <th></th>
                                <th>الحالي (وقت تقديم الطلب)</th>
                                <th>المطلوب</th>
                            </tr>
                        </thead>
                        <tbody>
                            {{-- تسمياتٌ عربيّة لا مفاتيحَ خام: القرارُ يُتَّخذ على ما يُقرأ --}}
                            <tr>
                                <td>الحالة</td>
                                <td>{{ $correction->currentStatusLabel() ?? '—' }}</td>
                                <td><strong>{{ $correction->requestedStatusLabel() }}</strong></td>
                            </tr>
                            <tr>
                                <td>بداية الوردية</td>
                                <td class="stk-num">{{ $correction->current_start_shift ?? '—' }}</td>
                                <td class="stk-num"><strong>{{ $correction->requested_start_shift ?? '—' }}</strong></td>
                            </tr>
                            <tr>
                                <td>نهاية الوردية</td>
                                <td class="stk-num">{{ $correction->current_end_shift ?? '—' }}</td>
                                <td class="stk-num"><strong>{{ $correction->requested_end_shift ?? '—' }}</strong></td>
                            </tr>
                            <tr>
                                <td>وقت الحضور</td>
                                <td class="stk-num">{{ $correction->current_check_in_time ?? '—' }}</td>
                                <td class="stk-num"><strong>{{ $correction->requested_check_in_time ?? '—' }}</strong></td>
                            </tr>
                            <tr>
                                <td>وقت الانصراف</td>
                                <td class="stk-num">{{ $correction->current_check_out_time ?? '—' }}</td>
                                <td class="stk-num"><strong>{{ $correction->requested_check_out_time ?? '—' }}</strong></td>
                            </tr>
                            <tr>
                                <td>نوع الإجازة</td>
                                <td>{{ \App\Models\AttendanceCorrectionRequest::absenceTypeLabel($correction->current_absence_type) ?? '—' }}</td>
                                <td><strong>{{ \App\Models\AttendanceCorrectionRequest::absenceTypeLabel($correction->requested_absence_type) ?? '—' }}</strong></td>
                            </tr>
                            <tr>
                                <td>رصيد الإجازة</td>
                                <td class="stk-num">{{ $correction->current_absence_balance ?? '—' }}</td>
                                <td class="stk-num"><strong>{{ $correction->requested_absence_balance ?? '—' }}</strong></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            @if ($correction->status === 'pending')
                <div class="row">
                    <div class="col-md-6" id="decision-approve">
                        <div class="card">
                            <div class="card-header"><h4 class="card-title text-success"><i class="feather icon-check"></i> اعتماد</h4></div>
                            <div class="card-body">
                                <form action="{{ route('hr.attendance-correction-approvals.approve', $correction->id) }}" method="POST"
                                      onsubmit="return confirm('سيُطبَّق هذا فوراً على سجل الحضور — هل أنت متأكد؟');">
                                    @csrf
                                    <div class="mb-3">
                                        <label class="form-label">ملاحظات (اختياري)</label>
                                        <textarea name="admin_notes" class="form-control" rows="2"></textarea>
                                    </div>
                                    <button type="submit" class="btn btn-success w-100">
                                        <i class="feather icon-check"></i> اعتماد وتطبيق
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6" id="decision-reject">
                        <div class="card">
                            <div class="card-header"><h4 class="card-title text-danger"><i class="feather icon-x"></i> رفض</h4></div>
                            <div class="card-body">
                                <form action="{{ route('hr.attendance-correction-approvals.reject', $correction->id) }}" method="POST">
                                    @csrf
                                    <div class="mb-3">
                                        <label class="form-label stk-req">سبب الرفض</label>
                                        <textarea name="rejection_reason" class="form-control" rows="2" required></textarea>
                                    </div>
                                    <button type="submit" class="btn btn-danger w-100">
                                        <i class="feather icon-x"></i> رفض الطلب
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            {{--
                سجلُّ التدقيق: من فعل ماذا ومتى، بترتيب وقوعه.

                الصفُّ يحمل القرارَ الأخير (`approved_by`/`approved_at`)؛ وهذا
                يحمل سردَه كاملاً — تقديماً واعتماداً ورفضاً وإلغاء. ونوعُه
                محميٌّ من تنظيف سجل النشاطات، فلا يذهب المسوّغُ ويبقى الخصم
                (انظر `Log::protectedTypes()`).
            --}}
            <div class="card">
                <div class="card-header"><h4 class="card-title"><i class="feather icon-activity"></i> سجل التدقيق</h4></div>
                <div class="card-body">
                    @forelse ($auditTrail as $entry)
                        <div class="d-flex justify-content-between align-items-start border-bottom py-2">
                            <div>
                                <i class="feather icon-clock"></i>
                                {{ $entry->description }}
                            </div>
                            <div class="text-muted text-nowrap" style="font-size:.76rem">
                                {{ $entry->user->name ?? 'النظام' }} · {{ $entry->created_at?->format('Y-m-d H:i') }}
                            </div>
                        </div>
                    @empty
                        <div class="stk-empty">
                            <div class="stk-empty__icon"><i class="feather icon-activity"></i></div>
                            <p class="stk-empty__hint mb-0">لا توجد قيود تدقيق لهذا الطلب.</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
