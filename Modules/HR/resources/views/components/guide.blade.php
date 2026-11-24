{{--
    لوحة الإرشادات — «ما هذه الشاشة، وما الذي يترتّب على ما فيها؟»

    ══════════════════ لماذا؟ ══════════════════

    شاشات الموارد البشرية تعرض حقولاً بعناوين مختصرة («البدل»، «الاستقطاع»،
    «الحالة») ولا موضع فيها لشرح ما يفعله القسم كلُّه ولا متى يُستعمَل ولا ما
    الذي يترتّب على الحفظ. وأثرُ هذه الوحدة نقديٌّ في أكثره — راتبٌ يُصرَف،
    وسلفةٌ تُقتطَع، ومكافأةُ نهاية خدمةٍ تُحتسَب — فالجهلُ بما يترتّب أغلى
    منه في غيرها.

    ══════════════════ نظيرُها في المبيعات ══════════════════

    مبنيّةٌ على `sales::components.guide` بالضبط: نفسُ الوسائط ونفسُ البنية
    ونفسُ سلوك التذكّر. ولم تُضَمّ تلك مباشرةً لأنّ أنماطها (`.sl-guide`)
    تعيش في `sales::partials.settings-theme` بمتغيّرات `--sl-*`، و`hr::master`
    لا يضمّ ذلك الملفّ — فضمُّه لأجل لوحةٍ واحدة يُعيد تنسيق ما حولها.
    فالأنماطُ هنا مستقلّةٌ ببادئة `hg-` ومتغيّرات `--app-*` وحدها.

    ══════════════════ الوسائط ══════════════════

      key     مفتاح فريد للوحة — عليه يُبنى تذكّر الفتح والطيّ (مطلوب)
      title   عنوان اللوحة (مطلوب — بلا عنوانٍ لا لوحة)
      lead    فقرةٌ تمهيدية تُعرَض قبل الأقسام (اختياري)
      blocks  ['عنوان القسم' => 'نصّ' أو ['سطر', 'سطر']] (اختياري)
      facts   أسطرٌ قصيرة تُعرَض شاراتٍ في الأسفل — «ما يترتّب فعلاً» (اختياري)
      open    (مُهمَل) — اللوحة تُفتح مطويّةً دائماً بطلب صاحب النظام (2026-09-24)

    ══════════════════ مثال ══════════════════

      @include('hr::components.guide', [
          'key' => 'hr-employees.index',
          'title' => 'ما هذه الشاشة، وما الذي يترتّب على ما فيها؟',
          'lead' => '…',
          'blocks' => ['عنوان' => ['سطر', 'سطر']],
          'facts' => ['…'],
      ])
--}}

@php
    // `@include` يمرّر متغيّرات القالب الأب كلَّها لا ما مُرِّر في المصفوفة
    // وحده — ففحصُ النوع يمنع متغيّراً محليّاً في الأب من أن يصير وسيطاً هنا
    $guideKey = (isset($key) && is_string($key) && $key !== '') ? $key : null;

    // بلا عنوان لا لوحة: `<summary>` فارغٌ يعطي سهماً بلا نصّ لا يُعرف ما يفتحه
    $guideTitle = (isset($title) && is_string($title) && $title !== '') ? $title : null;

    $guideLead = (isset($lead) && is_string($lead) && $lead !== '') ? $lead : null;
    $guideBlocks = (isset($blocks) && is_array($blocks)) ? $blocks : [];
    $guideFacts = (isset($facts) && is_array($facts)) ? $facts : [];

    // مطويّةٌ افتراضياً — بخلاف نظيرتها في المبيعات: من فتح شاشةَ عملٍ جاء
    // لعمله، ولوحةُ شرحٍ مفتوحةٌ فوقها تدفع المحتوى خارج الشاشة الأولى
    // وبطلب صاحب النظام (2026-09-24): مطويّةٌ **دائماً** عند فتح الصفحة — لا `open` من الشاشة
    // ولا تذكُّرٌ لفتحٍ سابق؛ من أرادها ضغط عنوانها
    $guideOpen = false;
@endphp

@if ($guideTitle)
    <details class="hg-guide" @if ($guideKey) data-guide="{{ $guideKey }}" @endif @if ($guideOpen) open @endif>
        <summary>
            <i class="feather icon-help-circle" aria-hidden="true"></i>
            {{ $guideTitle }}
        </summary>

        <div class="hg-guide__body">
            @if ($guideLead)
                <p>{{ $guideLead }}</p>
            @endif

            @foreach ($guideBlocks as $heading => $lines)
                {{-- مفتاحٌ رقميّ يعني سطراً بلا عنوان: الكاتب أراد فقرةً لا قسماً --}}
                @if (! is_int($heading))
                    <h6>{{ $heading }}</h6>
                @endif

                @foreach ((array) $lines as $line)
                    <p>{{ $line }}</p>
                @endforeach
            @endforeach

            @if (! empty($guideFacts))
                {{-- «ما يترتّب فعلاً» لا شرحاً نظرياً: ما الذي يتغيّر في
                     النظام حين يُضغَط «حفظ» --}}
                <div class="hg-guide__facts">
                    @foreach ($guideFacts as $fact)
                        <span class="hg-guide__fact">
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
    @push('styles')
        <style>
            /* كلُّ لونٍ من متغيّرات الثيم، والقيم الاحتياطية لِما لم يُضبَط بعد */
            .hg-guide {
                background: color-mix(in srgb, var(--app-primary, #7367f0) 7%, transparent);
                border: 1px solid var(--app-border, #e6e9f0);
                border-inline-start: 3px solid var(--app-primary, #7367f0);
                border-radius: calc(var(--app-radius, 8px) + 4px);
                margin-bottom: 1.25rem;
                font-family: var(--app-font-family, 'Cairo', sans-serif);
            }

            .hg-guide > summary {
                display: flex;
                align-items: center;
                gap: .5rem;
                padding: .8rem 1.15rem;
                font-weight: 700;
                font-size: .9rem;
                color: var(--app-text, #4b4b4b);
                cursor: pointer;
                list-style: none;
            }

            /* السهمُ الافتراضي يختلف بين المتصفّحات ولا ينقلب مع `dir` */
            .hg-guide > summary::-webkit-details-marker { display: none; }
            .hg-guide > summary::marker { content: ''; }

            .hg-guide > summary::after {
                content: '';
                inline-size: .5rem;
                block-size: .5rem;
                margin-inline-start: auto;
                border-inline-end: 2px solid var(--app-muted, #6e6b7b);
                border-block-end: 2px solid var(--app-muted, #6e6b7b);
                transform: rotate(45deg);
                transition: transform .2s ease;
            }

            .hg-guide[open] > summary::after { transform: rotate(-135deg); }

            .hg-guide__body {
                padding: 0 1.15rem 1rem;
                font-size: .85rem;
                line-height: 1.9;
                color: var(--app-muted, #6e6b7b);
            }

            .hg-guide__body h6 {
                font-size: .82rem;
                font-weight: 700;
                color: var(--app-text, #4b4b4b);
                margin: .8rem 0 .2rem;
            }

            .hg-guide__body p { margin: 0 0 .35rem; }

            .hg-guide__facts {
                display: flex;
                flex-wrap: wrap;
                gap: .4rem;
                margin-top: .6rem;
            }

            .hg-guide__fact {
                display: inline-flex;
                align-items: center;
                gap: .4rem;
                background: var(--app-surface, #fff);
                border: 1px solid var(--app-border, #e6e9f0);
                border-radius: var(--app-radius, 8px);
                padding: .3rem .6rem;
                font-weight: 600;
                font-size: .78rem;
                color: var(--app-text, #4b4b4b);
            }
        </style>
    @endpush

    @push('scripts')
        <script>
            /* تذكّر فتح اللوحة وطيّها لكلّ شاشة على حدة.
               كلُّ قراءةٍ وكتابة داخل `try`: المتصفّح في وضع التصفّح الخاصّ
               أو بمنعٍ لتخزين المواقع يرمي عند مجرّد لمس `localStorage`،
               ولا يجوز أن يُسقط ذلك بقيّةَ سكربتات الصفحة. */
            (function () {
                var PREFIX = 'hg-guide:';

                function read(key) {
                    try { return window.localStorage.getItem(PREFIX + key); }
                    catch (e) { return null; }
                }

                function write(key, value) {
                    try { window.localStorage.setItem(PREFIX + key, value); }
                    catch (e) { /* لا شيء: التذكّر رفاهيةٌ لا وظيفة */ }
                }

                document.querySelectorAll('details.hg-guide[data-guide]').forEach(function (panel) {
                    var key = panel.dataset.guide;
                    var stored = read(key);

                    /* تُفتح الصفحةُ واللوحةُ مطويّة دائماً — لا يُستعاد فتحٌ سابق،
                       ويُمحى ما حُفظ قديماً فلا يبقى له أثر. */
                    if (stored !== null) { write(key, 'closed'); }
                    panel.open = false;
                });
            })();
        </script>
    @endpush
@endonce
