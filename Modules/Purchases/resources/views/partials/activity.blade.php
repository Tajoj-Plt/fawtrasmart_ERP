{{--
    سجل النشاطات — نفس تصميم الخطّ الزمني في شاشة طلب الشراء، بأصناف
    `assets/css/logs.css` نفسها.

    الوقت يتبع صيغة اللغة النشطة ومنطقتها الزمنية.

    @param \Illuminate\Support\Collection $logs   مجمَّعة بمفتاح اليوم
--}}
@php use App\Support\LanguageSettings; @endphp

<div class="row mt-4">
    <div class="col-12">
        @if ($logs && count($logs) > 0)
            @php $previousDate = null; @endphp

            @foreach ($logs as $date => $dayLogs)
                @php
                    $currentDate = \Illuminate\Support\Carbon::parse($date);
                    $diffInDays = $previousDate ? $previousDate->diffInDays($currentDate) : 0;
                @endphp

                @if ($diffInDays > 7)
                    <div class="timeline-date">
                        <h4>{{ LanguageSettings::formatDate($currentDate) }}</h4>
                    </div>
                @endif

                <div class="timeline-day">{{ $currentDate->translatedFormat('l') }}</div>

                <ul class="timeline">
                    @foreach ($dayLogs as $log)
                        @if ($log)
                            <li class="timeline-item">
                                <div class="timeline-content">
                                    <div class="time">
                                        <i class="far fa-clock"></i>
                                        {{ $log->created_at->timezone(LanguageSettings::timezone())->format(LanguageSettings::timeFormat()) }}
                                    </div>
                                    <div>
                                        <strong>{{ $log->user->name ?? __('purchase_orders.empty.unknown_user') }}</strong>
                                        {!! \Illuminate\Support\Str::markdown($log->description ?? '') !!}
                                        <div class="text-muted">
                                            {{ $log->user->branch->name ?? __('purchase_orders.empty.unknown_branch') }}
                                        </div>
                                    </div>
                                </div>
                            </li>
                        @endif
                    @endforeach
                </ul>

                @php $previousDate = $currentDate; @endphp
            @endforeach
        @else
            <div class="alert alert-info text-center" role="alert">
                <i class="fas fa-info-circle me-2"></i>
                <p class="mb-0">{{ __('purchase_orders.empty.no_activity') }}</p>
            </div>
        @endif
    </div>
</div>
