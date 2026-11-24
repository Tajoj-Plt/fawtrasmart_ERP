@extends('client::portal.layout')

{{--
    مواعيد العميل وزياراتنا له.

    القادم أوّلاً ثم الماضي: من يفتح «مواعيدي» يسأل عن الغد لا عن الأمس.
--}}

@section('title', 'مواعيدي')
@section('subtitle', 'زياراتنا لك ومواعيدك المسجَّلة')

@section('content')
    @php
        $today = now()->startOfDay();

        /* التاريخُ والوقتُ عمودان: `appointment_date` مصبوغٌ datetime بلا وقت،
           فكان عمودُ «الوقت» يطبع 00:00 لكل موعد. */
        $when = function ($appointment) {
            if (! $appointment->appointment_date) {
                return null;
            }

            return \Illuminate\Support\Carbon::parse(
                \Illuminate\Support\Carbon::parse($appointment->appointment_date)->toDateString().' '.($appointment->time ?: '00:00')
            );
        };
    @endphp

    <div class="cp-card">
        <div class="cp-card__head">
            <h2 class="cp-card__title"><i class="fa fa-calendar-alt"></i>المواعيد</h2>
            <span class="cp-badge cp-badge--muted">{{ number_format($appointments->total()) }} موعد</span>
        </div>

        <div class="cp-card__body cp-card__body--flush">
            @if ($appointments->isEmpty())
                <div class="cp-empty">
                    <i class="fa fa-calendar-alt"></i>
                    <p>لا توجد مواعيد مسجَّلة لك.</p>
                </div>
            @else
                <div class="cp-tablewrap">
                    <table class="cp-table">
                        <thead>
                            <tr>
                                <th>التاريخ</th>
                                <th>الوقت</th>
                                <th>الإجراء</th>
                                <th>مع</th>
                                <th>ملاحظات</th>
                                <th>الحالة</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($appointments as $appointment)
                                @php
                                    $at = $when($appointment);
                                    $upcoming = $at && $at->copy()->startOfDay()->gte($today);
                                @endphp

                                <tr>
                                    <td class="cp-num">{{ $at?->format('Y-m-d') ?? '—' }}</td>
                                    <td class="cp-num">{{ $at?->format('H:i') ?? '—' }}</td>
                                    <td>{{ $appointment->action_type ?: '—' }}</td>
                                    <td>{{ $appointment->employeeNames() ?? '—' }}</td>
                                    <td style="max-width:320px">{{ trim(strip_tags((string) $appointment->notes)) ?: '—' }}</td>
                                    <td>
                                        @if ($upcoming)
                                            <span class="cp-badge cp-badge--success">قادم</span>
                                        @else
                                            <span class="cp-badge cp-badge--muted">منتهٍ</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    @if ($appointments->hasPages())
        <div class="cp-noprint" style="display:flex;justify-content:center;gap:.5rem">
            @if ($appointments->onFirstPage())
                <span class="cp-btn cp-btn--ghost cp-btn--sm" style="opacity:.5">السابق</span>
            @else
                <a href="{{ $appointments->previousPageUrl() }}" class="cp-btn cp-btn--ghost cp-btn--sm">السابق</a>
            @endif

            <span class="cp-btn cp-btn--ghost cp-btn--sm" style="pointer-events:none">
                {{ $appointments->currentPage() }} / {{ $appointments->lastPage() }}
            </span>

            @if ($appointments->hasMorePages())
                <a href="{{ $appointments->nextPageUrl() }}" class="cp-btn cp-btn--ghost cp-btn--sm">التالي</a>
            @else
                <span class="cp-btn cp-btn--ghost cp-btn--sm" style="opacity:.5">التالي</span>
            @endif
        </div>
    @endif
@endsection
