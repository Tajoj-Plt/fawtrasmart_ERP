{{--
    لوحة الإرشادات — «ما هذه الشاشة، ولماذا أستعملها؟»

    ══════════════════ لماذا؟ ══════════════════

    شاشات النظام تعرض حقولاً بعناوين مختصرة («الوسم»، «الميزانية»،
    «الحالة») ولا موضع فيها لشرح ما يفعله القسم كلُّه ولا متى يُستعمَل ولا
    ما الفرق بينه وبين ما يجاوره. فالمستخدم الجديد يملأ الحقول لأنّها
    أمامه، لا لأنّه يعرف ماذا يترتّب على ملئها.

    اللوحة `<details>` أصلاً: تُطوى بلا سطر سكربت، وتُفتح أوّل مرّة ثمّ
    تتذكّر اختيار المستخدم في `localStorage` — فالشرح لا يزاحم من قرأه،
    ولا يختفي عمّن لم يقرأه بعد.

    ══════════════════ الوسائط ══════════════════

      key     مفتاح فريد للوحة — عليه يُبنى تذكّر الفتح والطيّ (مطلوب)
      title   عنوان اللوحة
      lead    فقرةٌ تمهيدية تُعرَض قبل الأقسام (اختياري)
      blocks  ['عنوان القسم' => 'نصّ' أو ['سطر', 'سطر']] (اختياري)
      facts   أسطرٌ قصيرة تُعرَض شاراتٍ في الأسفل — «ما يترتّب فعلاً» (اختياري)
      open    أتُفتَح أوّل مرّة؟ (افتراضياً نعم)

    ══════════════════ مثال ══════════════════

      @include('client::reservations.components.guide', [
          'key' => 'invoices.index',
          'open' => false,
          'title' => '',
          'lead' => '',
          'blocks' => [
              __('…عنوان') => [__('…سطر'), __('…سطر')],
          ],
          'facts' => [__('…')],
      ])
--}}

@php
    // `@include` يمرّر متغيّرات القالب الأب كلَّها لا ما مُرِّر في المصفوفة
    // وحده — ففحصُ النوع يمنع متغيّراً محليّاً في الأب من أن يصير وسيطاً هنا
    $guideKey = (isset($key) && is_string($key) && $key !== '') ? $key : null;

    // بلا عنوان لا لوحة: `<summary>` فارغٌ يعطي مثلّثاً بلا نصّ لا يُعرف
    // ما يفتحه، ولوحةٌ بعنوانٍ مستعارٍ من وحدةٍ أخرى أسوأ من غيابها
    $guideTitle = (isset($title) && is_string($title) && $title !== '') ? $title : null;

    $lead = (isset($lead) && is_string($lead) && $lead !== '') ? $lead : null;
    $blocks = (isset($blocks) && is_array($blocks)) ? $blocks : [];
    $facts = (isset($facts) && is_array($facts)) ? $facts : [];
    $open = ! isset($open) || $open;
@endphp

@if ($guideTitle)
<details class="rsv-guide"
         @if ($guideKey) data-guide="{{ $guideKey }}" @endif
         @if ($open) open @endif>

    <summary>
        <i class="feather icon-help-circle" aria-hidden="true"></i>
        {{ $guideTitle }}
    </summary>

    <div class="rsv-guide__body">
        @if ($lead)
            <p>{{ $lead }}</p>
        @endif

        @foreach ($blocks as $heading => $lines)
            {{-- مفتاحٌ رقميّ يعني سطراً بلا عنوان: الكاتب أراد فقرةً لا قسماً --}}
            @if (! is_int($heading))
                <h6>{{ $heading }}</h6>
            @endif

            @foreach ((array) $lines as $line)
                <p>{{ $line }}</p>
            @endforeach
        @endforeach

        @if (! empty($facts))
            {{-- «ما يترتّب فعلاً» — لا شرحاً نظرياً: ما الذي يتغيّر في
                 النظام حين يضغط المستخدم «حفظ» --}}
            <div class="rsv-tags" style="margin-top:.6rem">
                @foreach ($facts as $fact)
                    <span class="rsv-guide__fact">
                        <i class="feather icon-corner-down-left" aria-hidden="true"></i>
                        {{ $fact }}
                    </span>
                @endforeach
            </div>
        @endif
    </div>
</details>
@endif

@once
    @push('scripts')
        <script>
            /* تذكّر فتح اللوحة وطيّها لكلّ شاشة على حدة.
               كلُّ قراءةٍ وكتابة داخل `try`: المتصفّح في وضع التصفّح الخاصّ
               أو بمنعٍ لتخزين المواقع يرمي عند مجرّد لمس `localStorage`،
               ولا يجوز أن يُسقط ذلك بقيّةَ سكربتات الصفحة. */
            (function () {
                var PREFIX = 'rsv-guide:';

                function read(key) {
                    try { return window.localStorage.getItem(PREFIX + key); }
                    catch (e) { return null; }
                }

                function write(key, value) {
                    try { window.localStorage.setItem(PREFIX + key, value); }
                    catch (e) { /* لا شيء: التذكّر رفاهيةٌ لا وظيفة */ }
                }

                document.querySelectorAll('details.rsv-guide[data-guide]').forEach(function (panel) {
                    var key = panel.dataset.guide;
                    var stored = read(key);

                    /* لا قيمة محفوظة = أوّل زيارة: يبقى ما قرّره القالب */
                    if (stored === 'open') { panel.open = true; }
                    if (stored === 'closed') { panel.open = false; }

                    panel.addEventListener('toggle', function () {
                        write(key, panel.open ? 'open' : 'closed');
                    });
                });
            })();
        </script>
    @endpush
@endonce
