{{--
    سجلّ نشاط المنتج — يُحمَّل بـAjax عبر `loadActivityLogs(page)`.

    السجلّات مجمَّعة تحت رؤوس أيام: القارئ يبحث عن «ماذا جرى أمس» لا عن
    السجلّ رقم كذا، فالتاريخ هو المِفصل الطبيعي.
--}}

@php
    $rows = ($logs && is_object($logs) && method_exists($logs, 'count')) ? $logs : collect();
    $lastDay = null;
@endphp

@if ($rows->count() > 0)
    <ul class="stk-timeline">
        @foreach ($rows as $log)
            @php
                $at = \Illuminate\Support\Carbon::parse($log->created_at);
                $day = $at->toDateString();
                $newDay = $day !== $lastDay;
                $lastDay = $day;
            @endphp

            @if ($newDay)
                <li class="stk-timeline__day">
                    <span>{{ $at->translatedFormat('l، d F Y') }}</span>
                </li>
            @endif

            <li class="stk-timeline__item">
                <span class="stk-timeline__dot stk-timeline__dot--log">
                    <i class="feather icon-edit-3"></i>
                </span>

                <div class="stk-timeline__body">
                    <p class="stk-timeline__head">
                        <strong>{{ $log->user->name ?? 'مستخدم غير معروف' }}</strong>
                        <span class="stk-timeline__time stk-num">
                            <i class="feather icon-clock"></i> {{ $at->format('H:i:s') }}
                        </span>
                    </p>

                    <div class="stk-timeline__text">
                        {!! Str::markdown($log->description ?? 'لا يوجد وصف') !!}
                    </div>

                    <p class="stk-timeline__meta">
                        <span><i class="feather icon-map-pin"></i> {{ $log->user->branch->name ?? 'فرع غير معروف' }}</span>
                    </p>
                </div>
            </li>
        @endforeach
    </ul>

    @if (method_exists($rows, 'hasPages'))
        @include('stock::components.js-pagination', [
            'paginator' => $rows,
            'callback' => 'loadActivityLogs',
            'unit' => 'سجل',
        ])
    @endif
@else
    <div class="stk-empty">
        <div class="stk-empty__icon"><i class="feather icon-activity"></i></div>
        <h5 class="stk-empty__title">لا يوجد نشاط مسجَّل</h5>
        <p class="stk-empty__hint">تظهر هنا كل حركة على هذا المنتج فور حدوثها</p>
    </div>
@endif
