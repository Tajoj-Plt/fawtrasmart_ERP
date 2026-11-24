{{--
    سجلُّ السلفة — ما كُتب في `logs` (salary_advance_request_log) مرتَّباً.

    الوسائط: $logs (Collection<Log>)
--}}
@if ($logs->isNotEmpty())
    <div class="stk-timeline">
        @foreach ($logs as $log)
            <div class="stk-timeline__item">
                <span class="stk-timeline__dot stk-timeline__dot--log">
                    <i class="feather icon-activity"></i>
                </span>
                <div class="stk-timeline__body">
                    <div class="stk-timeline__text">{{ $log->description }}</div>
                    <div class="stk-timeline__meta">
                        <span class="stk-timeline__day">{{ $log->created_at?->format('Y-m-d') }}</span>
                        <span class="stk-timeline__time">{{ $log->created_at?->format('H:i') }}</span>
                        @if ($log->user)
                            <span class="text-muted">— {{ $log->user->name }}</span>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@else
    <div class="stk-empty">
        <div class="stk-empty__icon"><i class="feather icon-activity"></i></div>
        <h6 class="stk-empty__title">لا نشاط مسجَّل</h6>
        <p class="stk-empty__hint">كلُّ اعتمادٍ وصرفٍ وتحصيلٍ وإلغاءٍ يُسجَّل هنا.</p>
    </div>
@endif
