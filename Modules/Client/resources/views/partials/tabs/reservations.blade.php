{{-- تبويب «الحجوزات» — حجوزات العميل من `reservations` (مستند الحجز الحقيقي)،
     بتصميم تبويب «المواعيد» نفسِه (`cl-table-card` + شارات `cl-state`). كان
     المتحكّم يحمّل `$bookings` من النموذج القديم ولا يعرضها تبويب. الحالةُ
     ولونُها من التعداد `ReservationStatus` فلا يفترق التمثيلان عن شاشة الحجوزات.
     التبويبُ لا يظهر إلّا لمن يملك `reservations.view` — وهي صلاحيةُ شاشة
     الحجوزات نفسها — وبالحجوزات لا بدونها (انظر `tabs-container`). --}}
@php
    $rsRows = $reservations ?? collect();
    $rsTones = [
        'draft' => 'off',
        'pending' => 'warning',
        'confirmed' => 'neutral',
        'checked_in' => 'neutral',
        'in_progress' => 'neutral',
        'completed' => 'on',
        'cancelled' => 'danger',
        'no_show' => 'danger',
        'rescheduled' => 'warning',
        'rejected' => 'danger',
    ];
    $rsCanOpen = \Illuminate\Support\Facades\Route::has('Reservations.show') && hasPermission('reservations.show');
@endphp
<div class="tab-pane" id="reservations" role="tabpanel">
    <div class="card cl-table-card">
        <div class="cl-table-head">
            <h6 class="cl-table-head__title">
                <i class="fas fa-calendar-check"></i>
                الحجوزات
                <span class="cl-count">{{ $rsRows->count() }} حجز</span>
            </h6>

            @if ($rsRows->isNotEmpty())
                <div class="btn-group btn-group-sm" role="group" aria-label="تصفية الحجوزات">
                    <button type="button" class="btn btn-outline-primary filter-reservations active" data-filter="all">
                        الكل · {{ $rsRows->count() }}
                    </button>
                    <button type="button" class="btn btn-outline-primary filter-reservations" data-filter="open">
                        قائمة · {{ $rsRows->filter(fn ($r) => in_array($r->status->value, ['pending', 'confirmed', 'checked_in', 'in_progress', 'rescheduled'], true))->count() }}
                    </button>
                    <button type="button" class="btn btn-outline-primary filter-reservations" data-filter="completed">
                        مكتملة · {{ $rsRows->filter(fn ($r) => $r->status->value === 'completed')->count() }}
                    </button>
                </div>
            @endif
        </div>

        @if ($rsRows->isNotEmpty())
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover table-sm mb-0">
                        <thead>
                            <tr>
                                <th class="text-center" style="width: 50px;">#</th>
                                <th>{{ __('reservations.columns.code') }}</th>
                                <th>{{ __('reservations.columns.date') }}</th>
                                <th>{{ __('reservations.columns.from') }}</th>
                                <th>{{ __('reservations.columns.service') }}</th>
                                <th>{{ __('reservations.columns.employee') }}</th>
                                <th class="text-end">{{ __('reservations.columns.total') }}</th>
                                <th class="text-center">{{ __('reservations.columns.status') }}</th>
                                <th class="text-center" style="width: 70px;">خيارات</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rsRows as $reservation)
                                @php
                                    $rsStatus = $reservation->status;
                                    $rsGroup = match ($rsStatus->value) {
                                        'completed' => 'completed',
                                        'pending', 'confirmed', 'checked_in', 'in_progress', 'rescheduled' => 'open',
                                        default => 'closed',
                                    };
                                @endphp
                                <tr data-group="{{ $rsGroup }}">
                                    <td class="text-center cl-num">{{ $loop->iteration }}</td>
                                    <td class="cl-num">
                                        @if ($rsCanOpen)
                                            <a href="{{ route('Reservations.show', $reservation->id) }}"><strong>{{ $reservation->code ?: '#'.$reservation->id }}</strong></a>
                                        @else
                                            <strong>{{ $reservation->code ?: '#'.$reservation->id }}</strong>
                                        @endif
                                    </td>
                                    <td class="cl-num">
                                        @if ($reservation->reservation_date)
                                            <strong>{{ $reservation->reservation_date->format('Y-m-d') }}</strong>
                                            <small class="text-muted d-block">{{ $reservation->reservation_date->locale('ar')->translatedFormat('l') }}</small>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="cl-num">
                                        @if ($reservation->start_time)
                                            <bdi dir="ltr">{{ substr((string) $reservation->start_time, 0, 5) }}</bdi>
                                            @if ($reservation->end_time)
                                                <small class="text-muted d-block"><bdi dir="ltr">→ {{ substr((string) $reservation->end_time, 0, 5) }}</bdi></small>
                                            @endif
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td>{{ $reservation->service_name ?: '—' }}</td>
                                    <td>{{ $reservation->employee?->full_name ?: 'غير محدد' }}</td>
                                    <td class="text-end cl-num">{{ number_format((float) $reservation->total, 2) }}</td>
                                    <td class="text-center">
                                        <span class="cl-state cl-state--{{ $rsTones[$rsStatus->value] ?? 'off' }}">{{ $rsStatus->label() }}</span>
                                    </td>
                                    <td class="text-center">
                                        @if ($rsCanOpen)
                                            <a class="btn btn-sm btn-outline-secondary" href="{{ route('Reservations.show', $reservation->id) }}" title="{{ __('reservations.actions.view') }}">
                                                <i class="fa fa-eye"></i>
                                            </a>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @else
            <div class="cl-empty">
                <span class="cl-empty__icon"><i class="fas fa-calendar-check"></i></span>
                <p class="cl-empty__title">لا توجد حجوزات</p>
                <p class="cl-empty__hint">لم يُسجَّل أي حجز لهذا العميل بعد</p>
            </div>
        @endif
    </div>
</div>

<script>
    // تصفية صفوف الحجوزات: الكل / القائمة / المكتملة
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('#reservations .filter-reservations');
        if (!btn) return;
        var filter = btn.getAttribute('data-filter');
        document.querySelectorAll('#reservations .filter-reservations').forEach(function (b) {
            b.classList.toggle('active', b === btn);
        });
        document.querySelectorAll('#reservations tr[data-group]').forEach(function (row) {
            row.hidden = !(filter === 'all' || row.getAttribute('data-group') === filter);
        });
    });
</script>
