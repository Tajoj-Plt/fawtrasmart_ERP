{{--
    شريطُ «الحجز لن يُقبل حتى تُكمل…» — فوق «حجز جديد» واللوحة.

    يظهر للناقص الذي يمنع الحجزَ نفسَه وحده (`blockers()`)، فلا يُزعج بما
    يعمل بدونه. والمتغيّرُ باسمٍ لا يصطدم بمتغيّرات الصفحة التي تُضمّنه.
--}}
@php
    $rsvGaps = app(\Modules\Client\Reservations\Services\ReservationReadiness::class)->blockers();
@endphp

@if ($rsvGaps)
    <div class="alert alert-warning d-flex align-items-start gap-2" role="alert">
        <i class="feather icon-alert-triangle mt-1"></i>
        <div>
            <strong>{{ __('reservations.start.banner', ['items' => implode('، ', array_column($rsvGaps, 'title'))]) }}</strong>
            <ul class="mb-1 mt-1" style="padding-inline-start:1.1rem">
                @foreach ($rsvGaps as $gap)
                    <li>
                        {{ $gap['detail'] }}
                        @if ($gap['fix_url'])
                            <a href="{{ $gap['fix_url'] }}" class="alert-link">{{ __('reservations.start.fix') }}</a>
                        @endif
                    </li>
                @endforeach
            </ul>
            @if (Route::has('Reservations.guide'))
                <a href="{{ route('Reservations.guide') }}" class="alert-link">
                    <i class="feather icon-compass"></i> {{ __('reservations.start.open_guide') }}
                </a>
            @endif
        </div>
    </div>
@endif
