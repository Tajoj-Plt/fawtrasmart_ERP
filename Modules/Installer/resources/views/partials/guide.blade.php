{{--
    صندوق إرشادات الخطوة — **مطويّ افتراضياً**.

    مشترك بين الخطوات كي يبقى شكل الإرشاد واحداً: المستخدم يتعلّم مكانه مرةً
    ثم يعرف أين يجده في كل شاشة.

    والطيّ مقصود: الإرشاد يُقرأ مرّة ثم يصير عائقاً بين المستخدم وحقوله في كل
    زيارة تالية. `details` يجعله سطراً واحداً حتى يُطلَب — بلا JavaScript.

    @param string $title
    @param array<int, string> $items  بنود مرقّمة — أو
    @param string $text               فقرة واحدة
    @param bool $open                 افتحه ابتداءً (للخطوات الحسّاسة)
--}}
<details class="guide" @if ($open ?? false) open @endif>
    <summary>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
             stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/>
        </svg>
        {{ $title ?? 'إرشادات' }}
    </summary>

    @isset($text)
        <p>{{ $text }}</p>
    @endisset

    @isset($items)
        <ul>
            @foreach ($items as $item)
                <li>{!! $item !!}</li>
            @endforeach
        </ul>
    @endisset
</details>
