{{--
    بطاقات الملخّص على قياس شاشة «سجلّ النشاطات».

    أصنافها من `public/css/online-store.css` — وهي الورقة التي تلينك بها تلك
    الشاشة — فالبطاقة هنا وهناك واحدة: `stats-row` شبكةً، و`stat-card-modern`
    بلونها (primary / success / warning / danger)، ثم أيقونة فعنوان فقيمة
    فسطر `stat-trend` أسفلها.

    السطر الأخير: البطاقة الأولى «كل السجلات»، وما بعدها نسبتها من الإجمالي —
    فالخانة التي كانت في الشاشة الأصلية مجرّد تعليق تحمل هنا معلومة.

    الأعداد والنِّسب تُنسَّق بفواصل اللغة النشطة لا بتنسيق ثابت.

    @param array<int, array{tone?: string, icon: string, label: string, value: int|float, is_total?: bool}> $cards
--}}
@php
    use App\Support\LanguageSettings;

    // إجمالي القسم: من البطاقة المعلَّمة `is_total`، وإلّا فمجموع البطاقات
    $statsTotal = 0;

    foreach ($cards as $card) {
        if (! empty($card['is_total'])) {
            $statsTotal = (float) ($card['value'] ?? 0);
            break;
        }
    }
@endphp

<div class="stats-row">
    @foreach ($cards as $card)
        @php
            $value = (float) ($card['value'] ?? 0);
            $isTotal = ! empty($card['is_total']);
            $share = (! $isTotal && $statsTotal > 0) ? round($value / $statsTotal * 100) : null;
        @endphp

        <div class="stat-card-modern {{ $card['tone'] ?? 'primary' }}">
            <div class="stat-icon">
                <i class="fas {{ $card['icon'] }}"></i>
            </div>

            <div class="stat-label">{{ $card['label'] }}</div>

            <div class="stat-value">{{ LanguageSettings::formatNumber($value, 0) }}</div>

            <div class="stat-trend up">
                @if ($share === null)
                    <i class="fas fa-list"></i>
                    <span>{{ __('purchase_cycle.stats.all_records') }}</span>
                @else
                    <i class="fas fa-percent"></i>
                    <span>{{ __('purchase_cycle.stats.share', ['percent' => LanguageSettings::formatNumber($share, 0)]) }}</span>
                @endif
            </div>
        </div>
    @endforeach
</div>
