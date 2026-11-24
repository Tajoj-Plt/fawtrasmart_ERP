{{-- سجلّ نشاط سجلٍّ واحد — من `logs` المركزي. يُمرَّر فارغاً لمن لا يملك `realestate.logs.view`. --}}
@if ($logs->isEmpty())
    @include('rentalmanagement::realestate.components.empty', ['icon' => 'activity', 'title' => 'لا نشاط مسجَّل', 'hint' => hasPermission('realestate.logs.view') ? 'لم تُسجَّل عمليات على هذا السجلّ بعد' : 'عرض السجلّ يتطلّب صلاحية «سجلّ نشاط العقارات»'])
@else
    <ul class="stk-timeline">
        @foreach ($logs->groupBy(fn ($log) => $log->created_at->toDateString()) as $date => $dayLogs)
            <li class="stk-timeline__day"><span>{{ \Illuminate\Support\Carbon::parse($date)->translatedFormat('l، d F Y') }}</span></li>
            @foreach ($dayLogs as $log)
                <li class="stk-timeline__item">
                    <span class="stk-timeline__dot stk-timeline__dot--log"><i class="feather icon-edit-3"></i></span>
                    <div class="stk-timeline__body">
                        <p class="stk-timeline__head">
                            <strong>{{ $log->user->name ?? 'النظام' }}</strong>
                            <span class="stk-timeline__time stk-num"><i class="feather icon-clock"></i> {{ $log->created_at->format('H:i') }}</span>
                        </p>
                        <div class="stk-timeline__text">{!! \Illuminate\Support\Str::markdown($log->description ?? '', ['html_input' => 'escape', 'allow_unsafe_links' => false]) !!}</div>
                    </div>
                </li>
            @endforeach
        @endforeach
    </ul>
@endif
