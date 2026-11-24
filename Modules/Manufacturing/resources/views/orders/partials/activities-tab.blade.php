{{-- سجلّ نشاط أمر التصنيع --}}

<div class="tab-pane" id="activities" role="tabpanel">
    <div class="card">
        <div class="card-body">
            @if (isset($logs) && count($logs) > 0)
                <ul class="stk-timeline">
                    @foreach ($logs as $date => $dayLogs)
                        @php $day = \Illuminate\Support\Carbon::parse($date); @endphp

                        <li class="stk-timeline__day">
                            <span>{{ $day->translatedFormat('l، d F Y') }}</span>
                        </li>

                        @foreach ($dayLogs as $log)
                            @continue (! $log)

                            <li class="stk-timeline__item">
                                <span class="stk-timeline__dot stk-timeline__dot--log">
                                    <i class="feather icon-edit-3"></i>
                                </span>

                                <div class="stk-timeline__body">
                                    <p class="stk-timeline__head">
                                        <strong>{{ $log->user->name ?? 'مستخدم غير معروف' }}</strong>
                                        <span class="stk-timeline__time stk-num">
                                            <i class="feather icon-clock"></i>
                                            {{ $log->created_at->format('H:i:s') }}
                                        </span>
                                    </p>

                                    <div class="stk-timeline__text">
                                        @include('manufacturing::partials.log-description', ['log' => $log])
                                    </div>

                                    <p class="stk-timeline__meta">
                                        <span>
                                            <i class="feather icon-map-pin"></i>
                                            {{ $log->user->branch->name ?? 'فرع غير معروف' }}
                                        </span>
                                    </p>
                                </div>
                            </li>
                        @endforeach
                    @endforeach
                </ul>
            @else
                <div class="stk-empty">
                    <div class="stk-empty__icon"><i class="feather icon-activity"></i></div>
                    <h5 class="stk-empty__title">لا نشاط مسجَّل</h5>
                    <p class="stk-empty__hint">تظهر هنا كل عملية على هذا الأمر</p>
                </div>
            @endif
        </div>
    </div>
</div>
