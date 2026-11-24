{{--
    شريط بطاقات الملخّص.

    الأعداد تُنسَّق بفواصل اللغة النشطة لا بتنسيق ثابت.

    @param array<int, array{icon: string, label: string, value: int|float}> $cards
--}}
<div class="po-stats">
    @foreach ($cards as $card)
        <div class="po-stat">
            <span class="po-stat__icon"><i class="fas {{ $card['icon'] }}"></i></span>
            <div>
                <div class="po-stat__value">
                    {{ \App\Support\LanguageSettings::formatNumber($card['value'] ?? 0, 0) }}
                </div>
                <div class="po-stat__label">{{ $card['label'] }}</div>
            </div>
        </div>
    @endforeach
</div>
