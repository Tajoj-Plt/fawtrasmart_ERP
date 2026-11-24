{{--
    لوحة الإرشادات — «ما هذه الشاشة، ولماذا أستعملها؟»

    ══════════════════ لماذا؟ ══════════════════

    شاشات دورة المشتريات تعرض حقولاً بعناوين مختصرة («التسوية»، «الاستلام»،
    «مهلة السداد») ولا موضع فيها لشرح ما يفعله القسم كلُّه، ولا متى يُستعمَل،
    ولا ما يترتّب على الحفظ. فالمستخدم الجديد يملأ الحقول لأنّها أمامه لا
    لأنّه يعرف أثر ملئها — ثمّ يكتشف الأثر في المخزون وحساب المورد.

    اللوحة `<details>` أصلاً: تُطوى بلا سطر سكربت، وتُفتح أوّل مرّة ثمّ
    تتذكّر اختيار المستخدم في `localStorage` — فالشرح لا يزاحم من قرأه،
    ولا يختفي عمّن لم يقرأه بعد.

    ══════════════════ لماذا نسخةٌ في «المشتريات»؟ ══════════════════

    نظيرتها في `sales::components.guide` تعتمد متغيّرات `--sl-*` التي تحقنها
    ورقة أنماط المبيعات وحدها. وشاشات المشتريات على نظامين: الفهرسة في نطاق
    `sl-scope`، والإنشاء والعرض في نطاق `po-page` بمتغيّرات `--po-*`. فهذه
    النسخة لا تعتمد أيّاً منهما: تقرأ `--app-*` التي تحقنها
    `App\Support\ThemeSettings` في كل صفحة، بقيمٍ احتياطية بعدها — فتظهر
    على قياسٍ واحد في الشاشتين معاً.

    وتزيد عليها أنّ `**نصّ**` يخرج غليظاً هنا فعلاً — في نسخة المبيعات
    تُطبَع النجمتان كما هما لأن النصّ يُخرَج بلا معالجة.

    ══════════════════ الوسائط ══════════════════

      key     مفتاح فريد للوحة — عليه يُبنى تذكّر الفتح والطيّ (مطلوب)
      title   عنوان اللوحة (مطلوب — بلا عنوانٍ لا لوحة)
      lead    فقرةٌ تمهيدية تُعرَض قبل الأقسام (اختياري)
      blocks  ['عنوان القسم' => 'نصّ' أو ['سطر', 'سطر']] (اختياري)
      facts   أسطرٌ قصيرة تُعرَض شاراتٍ في الأسفل — «ما يترتّب فعلاً» (اختياري)
      open    أتُفتَح أوّل مرّة؟ (افتراضياً نعم)
--}}

@php
    // `@include` يمرّر متغيّرات القالب الأب كلَّها لا ما مُرِّر في المصفوفة
    // وحده — ففحصُ النوع يمنع متغيّراً محليّاً في الأب من أن يصير وسيطاً هنا
    $guideKey = (isset($key) && is_string($key) && $key !== '') ? $key : null;

    // بلا عنوان لا لوحة: `<summary>` فارغٌ يعطي مثلّثاً بلا نصّ لا يُعرف ما يفتحه
    $guideTitle = (isset($title) && is_string($title) && $title !== '') ? $title : null;

    $guideLead = (isset($lead) && is_string($lead) && $lead !== '') ? $lead : null;
    $guideBlocks = (isset($blocks) && is_array($blocks)) ? $blocks : [];
    $guideFacts = (isset($facts) && is_array($facts)) ? $facts : [];
    $guideOpen = ! isset($open) || $open;

    /*
     * النجمتان المزدوجتان تصيران وسم `strong`.
     *
     * الترتيب مقصود: يُهرَّب النصّ أوّلاً بـ`e()` ثمّ تُحوَّل النجمتان — فما
     * يأتي من ملفّ اللغة لا يستطيع حقن وسمٍ مهما احتوى، والوسم الوحيد الذي
     * يخرج هو ما تولّده هذه الدالة. والعكس (تحويلٌ ثمّ تهريب) يُهرِّب الوسم
     * الذي ولّدناه فيُطبع نصّاً، وتركُ التهريب أصلاً ثغرةُ حقنٍ كاملة.
     */
    $guideRich = function (string $text): string {
        return preg_replace('/\*\*(.+?)\*\*/u', '<strong>$1</strong>', e($text));
    };
@endphp

@if ($guideTitle)
    <details class="po-guide"
        @if ($guideKey) data-guide="{{ $guideKey }}" @endif
        @if ($guideOpen) open @endif>

        <summary>
            <i class="fas fa-question-circle" aria-hidden="true"></i>
            {{ $guideTitle }}
        </summary>

        <div class="po-guide__body">
            @if ($guideLead)
                <p>{!! $guideRich($guideLead) !!}</p>
            @endif

            @foreach ($guideBlocks as $heading => $lines)
                {{-- مفتاحٌ رقميّ يعني سطراً بلا عنوان: الكاتب أراد فقرةً لا قسماً --}}
                @if (! is_int($heading))
                    <h6>{{ $heading }}</h6>
                @endif

                @foreach ((array) $lines as $line)
                    <p>{!! $guideRich($line) !!}</p>
                @endforeach
            @endforeach

            @if (! empty($guideFacts))
                {{-- «ما يترتّب فعلاً» — لا شرحاً نظرياً: ما الذي يتغيّر في
                     النظام حين يضغط المستخدم «حفظ» --}}
                <div class="po-guide__facts">
                    @foreach ($guideFacts as $fact)
                        <span class="po-guide__fact">
                            <i class="fas fa-level-down-alt" aria-hidden="true"></i>
                            {{ $fact }}
                        </span>
                    @endforeach
                </div>
            @endif
        </div>
    </details>
@endif

@once
    {{-- الأنماط داخل الجسم لا في قسم `css`: هذا القالب يُستدعى من شاشاتٍ
         يملك بعضُها ذلك القسم وبعضُها لا، ولوحةٌ بلا أنماط أسوأ من لوحةٍ
         بأنماطٍ في غير الترويسة --}}
    <style>
        .po-guide {
            background: var(--app-primary-soft, rgba(115, 103, 240, .08));
            border: 1px solid var(--app-border, #e6e9f0);
            border-inline-start: 3px solid var(--app-primary, #7367f0);
            border-radius: var(--app-radius, .5rem);
            margin-bottom: 1.25rem;
        }

        .po-guide > summary {
            display: flex; align-items: center; gap: .5rem;
            padding: .75rem 1.25rem;
            font-weight: 700; font-size: .9rem;
            color: var(--app-text, #4b4b4b);
            cursor: pointer;
            list-style: none;
        }

        /* المثلّث الافتراضي يختلف بين المتصفّحات ولا ينقلب مع اتجاه الصفحة */
        .po-guide > summary::-webkit-details-marker { display: none; }
        .po-guide > summary::marker { content: ''; }

        .po-guide > summary::after {
            content: '';
            inline-size: .5rem; block-size: .5rem;
            margin-inline-start: auto;
            border-inline-end: 2px solid var(--app-muted, #6e6b7b);
            border-block-end: 2px solid var(--app-muted, #6e6b7b);
            transform: rotate(45deg);
            transition: transform .2s ease;
        }

        .po-guide[open] > summary::after { transform: rotate(-135deg); }

        .po-guide__body {
            padding: 0 1.25rem 1rem;
            font-size: .85rem; line-height: 1.9;
            color: var(--app-muted, #6e6b7b);
        }

        .po-guide__body h6 {
            font-size: .82rem; font-weight: 700;
            color: var(--app-text, #4b4b4b);
            margin: .75rem 0 .2rem;
        }

        .po-guide__body p { margin: 0 0 .35rem; }
        .po-guide__body strong { color: var(--app-text, #4b4b4b); font-weight: 700; }

        .po-guide__facts {
            display: flex; flex-wrap: wrap; gap: .4rem;
            margin-top: .6rem;
        }

        .po-guide__fact {
            display: inline-flex; align-items: center; gap: .4rem;
            background: var(--app-surface, #fff);
            border: 1px solid var(--app-border, #e6e9f0);
            border-radius: .35rem;
            padding: .3rem .6rem;
            font-size: .78rem; font-weight: 600;
            color: var(--app-text, #4b4b4b);
        }

        /* إرشاد الحقل الواحد — تحت الحقل مباشرةً */
        .po-help {
            display: block;
            font-size: .75rem;
            color: var(--app-muted, #6e6b7b);
            margin-top: .3rem;
        }
    </style>

    @push('scripts')
        <script>
            /* تذكّر فتح اللوحة وطيّها لكلّ شاشة على حدة.
               كلُّ قراءةٍ وكتابة داخل `try`: المتصفّح في وضع التصفّح الخاصّ
               أو بمنعٍ لتخزين المواقع يرمي عند مجرّد لمس `localStorage`،
               ولا يجوز أن يُسقط ذلك بقيّةَ سكربتات الصفحة. */
            (function () {
                var PREFIX = 'po-guide:';

                function read(key) {
                    try { return window.localStorage.getItem(PREFIX + key); }
                    catch (e) { return null; }
                }

                function write(key, value) {
                    try { window.localStorage.setItem(PREFIX + key, value); }
                    catch (e) { /* لا شيء: التذكّر رفاهيةٌ لا وظيفة */ }
                }

                document.querySelectorAll('details.po-guide[data-guide]').forEach(function (panel) {
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
