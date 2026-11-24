{{--
    ══════════════════════════════════════════════════════════════════════
    مطابقة شاشات الإنشاء لـ«إنشاء فاتورة مشتريات»
    ══════════════════════════════════════════════════════════════════════

    **لماذا هذا الملف أصلاً؟**

    شاشات إنشاء مستندات المبيعات الأربع — الفاتورة وعرض السعر والإشعار
    الدائن والمرتجع — مبنيّةٌ منذ كُتبت على قياس
    `purchases::purchases.invoices_purchase.create`: الترتيب نفسه (بيانات
    المستند ← البنود ← التفاصيل الإضافية في تبويبات ← الملاحظات وأزرار
    الحفظ)، وجدول البنود نفسه، ولوحة الإجماليات نفسها. لكنّ `sl-doc-*`
    نقلٌ عن `po-*` لا مشاركةٌ له، والنقل انحرف عن أصله في القيم: حشوةٌ
    أوسع بنحو ٣٥٪، وحوافّ أشدّ استدارةً بستّ نقاط، وذيلُ بطاقةٍ ملوّن لا
    وجود له في الأصل، وحقولُ بنودٍ بلا حدود، وتبويباتٌ أضيق. فالشاشتان
    تتشابهان بنيةً وتفترقان مظهراً — وهو ما يراه المستخدم.

    هذه الطبقة تردّ تلك القيم إلى قيم `po-*` حرفاً بحرف.

    **لماذا طبقةٌ مستقلّة لا تعديلٌ في `document-theme`؟**

    لأن `document-theme` يخدم — إلى جانب شاشات الإنشاء — شاشاتِ التعديل
    والعرض والفواتير الدورية والأقساط. وتعديلُه فيها كلَّها لم يُطلَب.
    فالمطابقة معلّقةٌ على صنف `sl-doc--po` على غلاف الصفحة، ولا تسري إلّا
    حيث يُوضع.

    **الأثر:** أربع شاشات إنشاءٍ وحدها. لا وسم يُمسّ، ولا مُعرِّف، ولا
    صنف يقرؤه `public/assets/js/invoice-calculator.js` المشترك — فعقد
    الـDOM كما هو، والحساب كما هو.

    **ما لا تبلغه طبقةُ أنماط:** «شارة الرقم» في المشتريات مربّعٌ ٣٨px
    بجانبه الرقم وسطرُ وصفٍ تحته (`po-code__badge` + `__value` + `__meta`)،
    وفي المبيعات شريطٌ واحد (`sl-code`). قُرِّبت هيئتُه إلى هيئة المربّع،
    وتوحيدُهما تماماً يقتضي تغيير الوسم. وكذلك جدولُ البنود على الجوّال:
    المشتريات تقلبه بطاقاتٍ عبر `data-label` على كل خليّة، وخلايا المبيعات
    بلا هذه السمة — فبقي الجدول جدولاً بتمرير أفقي.

    الاستعمال:

        @include('sales::partials.document-theme')
        @include('sales::partials.document-theme-po')
        <div class="content-body sl-scope sl-doc sl-doc--po"> … </div>
--}}

@once
    <style id="sl-doc-po-parity">
        /* ═══════════════ الرموز ═══════════════
           سلّم الخطّ في النظامين واحدٌ أصلاً (.86 / .93 / 1 / 1.14 من
           `--app-font-size`)، فلا يُعاد. المختلف اثنان:

             الاستدارة  `--sl-r-lg` = نصف القطر + ٦px،  و`--po-radius`
                        نصف القطر كما هو
             الفاصل     `--sl-line-soft` = ٥٥٪ شفافية، و`--po-border`
                        الحدّ كاملاً

           ويُعاد ضبطهما هنا لا في أصلهما، فيبقى ما عدا شاشات الإنشاء
           على حاله. */
        .sl-doc.sl-doc--po {
            --sl-r-lg: var(--app-radius, .5rem);
            --sl-line-soft: var(--sl-line);
        }

        /* `--sl-tint` لا يُلغى هنا رغم أنّ الأصل بلا خلفياتٍ ملوّنة:
           فهو خلفيةُ «لوحة التنبيه» (`sl-doc-note`) أيضاً، وإلغاؤه
           للجميع يُطفئ تنبيهاً لا علاقة له بالمطابقة. فالمواضع الثلاثة
           التي تخصّها — ذيلُ البطاقة ورأسُ الجدول وصندوقُ الرفع —
           تُلغى كلٌّ في موضعه أدناه. */

        /* ═══════════════ البطاقة ═══════════════ */
        .sl-doc.sl-doc--po .sl-doc-card {
            border-radius: var(--app-radius, .5rem);
            box-shadow: var(--app-card-shadow, 0 2px 10px rgba(34, 41, 47, .06));
            margin-bottom: 1.25rem;
        }

        .sl-doc.sl-doc--po .sl-doc-card > .table-responsive:last-child {
            border-end-start-radius: var(--app-radius, .5rem);
            border-end-end-radius: var(--app-radius, .5rem);
        }

        .sl-doc.sl-doc--po .sl-doc-card__head {
            gap: .75rem;
            padding: .85rem 1.1rem;
            border-bottom: 1px solid var(--sl-line);
            background: var(--app-card-header-bg, transparent);
        }

        .sl-doc.sl-doc--po .sl-doc-card__head--flush {
            padding: 0;
            border-bottom: 0;
            background: transparent;
        }

        /* العنوان في الأصل ٦٠٠ بمقاس الخطّ الأساس، لا ٧٠٠ بمقاسٍ مكتوب.
           و`.98rem` كانت تتجاهل «مقاس الخطّ» في «مظهر النظام» أصلاً. */
        .sl-doc.sl-doc--po .sl-doc-card__title {
            gap: .5rem;
            font-size: var(--sl-fs);
            font-weight: 600;
        }

        .sl-doc.sl-doc--po .sl-doc-card__body {
            padding: 1.1rem;
        }

        /* ذيلُ البطاقة في الأصل بلا خلفيةٍ ولا استدارةٍ ملوّنة، ومحاذاته
           إلى الوسط لا إلى الأعلى */
        .sl-doc.sl-doc--po .sl-doc-card__foot {
            align-items: center;
            gap: .75rem;
            padding: .75rem 1.1rem;
            border-top: 1px solid var(--sl-line);
            background: transparent;
            border-end-start-radius: var(--app-radius, .5rem);
            border-end-end-radius: var(--app-radius, .5rem);
        }

        /* ═══════════════ الحقول ═══════════════
           `.sl-field` كان `margin-bottom: 0` وحده — بلا تخطيطٍ ولا تباعد،
           والتباعد مضافٌ على العنوان. و`.po-field` عمودٌ مرن بفجوة ٣٥.
           والعنوان في الأصل ثانويّ: وزن ٥٠٠ بلون الرمادي، لا ٦٠٠ بلون
           النصّ. */
        .sl-doc.sl-doc--po .sl-field {
            display: flex;
            flex-direction: column;
            gap: .35rem;
        }

        .sl-doc.sl-doc--po .sl-field > label {
            display: block;
            margin: 0;
            font-size: var(--sl-fs-sm);
            font-weight: 500;
            color: var(--app-muted, #7b8194);
        }

        .sl-doc.sl-doc--po .sl-hint {
            display: inline;
            font-size: var(--sl-fs-sm);
            color: var(--app-muted, #7b8194);
        }

        /* ═══════════════ التبويبات ═══════════════ */
        .sl-doc.sl-doc--po .nav.sl-doc-tabs {
            gap: .25rem;
            padding: 0 1.1rem;
            border-bottom: 1px solid var(--sl-line);
        }

        .sl-doc.sl-doc--po .sl-doc-tabs .nav-link {
            padding: .7rem .9rem;
            font-size: var(--sl-fs);
            font-weight: 500;
        }

        /* ═══════════════ جدول البنود ═══════════════ */
        .sl-doc.sl-doc--po .sl-doc-table {
            font-size: var(--sl-fs);
        }

        /* رأس الجدول في الأصل بأحرفٍ كبيرة وتباعدٍ حرفيّ ووزن ٦٠٠،
           ولونُه وخلفيتُه من مفاتيح الجدول في «مظهر النظام» */
        .sl-doc.sl-doc--po .sl-doc-table > thead > tr > th {
            padding: .7rem .85rem;
            font-size: var(--sl-fs-xs);
            font-weight: 600;
            letter-spacing: .02em;
            text-transform: uppercase;
            color: var(--app-table-header-text, var(--app-muted, #7b8194));
            background: var(--app-table-header-bg, var(--app-primary-soft, rgba(115, 103, 240, .12)));
            border-bottom: 1px solid var(--sl-line);
        }

        /* الفاصل في الأصل أسفلَ الخليّة لا أعلاها، ويسقط عن آخر صفّ */
        .sl-doc.sl-doc--po .sl-doc-table > tbody > tr > td {
            padding: .75rem .85rem;
            border-top: 0;
            border-bottom: 1px solid var(--sl-line);
        }

        .sl-doc.sl-doc--po .sl-doc-table > tbody > tr:last-child > td {
            border-bottom: 0;
        }

        .sl-doc.sl-doc--po .sl-doc-table > tbody > tr:hover > td {
            background: var(--app-primary-soft, rgba(115, 103, 240, .12));
        }

        /* الأرقام في الأصل تتبع اتجاه الخليّة ولا تُتوسَّط */
        .sl-doc.sl-doc--po .sl-doc-table .num {
            text-align: inherit;
            white-space: nowrap;
        }

        /* الترقيم في الأصل رقمٌ عاديّ في عمودٍ، لا قرصٌ ملوّن */
        .sl-doc.sl-doc--po .sl-doc-table .sl-row__index {
            width: auto;
            height: auto;
            border-radius: 0;
            background: transparent;
            color: var(--app-muted, #7b8194);
            font-size: var(--sl-fs-sm);
            font-weight: 600;
        }

        /* ═══════════════ حقول البنود ═══════════════
           هذا أظهرُ الفروق: الحقل في المبيعات كان شفّافاً بلا حدّ حتى
           تمرّ عليه الفأرة — يقرأ الجدولَ ورقةَ فاتورةٍ لا نموذجَ إدخال،
           فلا يعرف المُدخِل أين يكتب. وفي المشتريات حقلٌ عاديّ بحدٍّ
           ظاهر وخلفيةِ سطح. */
        .sl-doc.sl-doc--po .sl-doc-table .sl-input,
        .sl-doc.sl-doc--po .sl-doc-table .form-control,
        .sl-doc.sl-doc--po .sl-doc-table .form-select {
            height: 38px;
            padding: 0 .75rem;
            border: 1px solid var(--sl-line);
            border-radius: var(--sl-r);
            background: var(--app-surface, #fff);
            font-size: var(--sl-fs-sm);
        }

        .sl-doc.sl-doc--po .sl-doc-table textarea.form-control {
            height: auto;
            min-height: 38px;
            padding: .45rem .75rem;
        }

        .sl-doc.sl-doc--po .sl-doc-table .sl-input:hover,
        .sl-doc.sl-doc--po .sl-doc-table .form-control:hover,
        .sl-doc.sl-doc--po .sl-doc-table .form-select:hover {
            border-color: var(--sl-line);
        }

        /* ═══════════════ الإجماليات ═══════════════ */
        .sl-doc.sl-doc--po .sl-totals {
            display: block;
            width: min(340px, 100%);
            min-width: 0;
        }

        .sl-doc.sl-doc--po .sl-totals__row {
            gap: 1rem;
            padding: .45rem 0;
            font-size: var(--sl-fs);
        }

        .sl-doc.sl-doc--po .sl-totals__row--grand {
            padding-top: .7rem;
            border-top: 1px solid var(--sl-line);
        }

        .sl-doc.sl-doc--po .sl-totals__row--grand > span:last-child {
            font-size: var(--sl-fs-lg);
        }

        /* ═══════════════ رفع المرفقات ═══════════════
           الإطار في الأصل متقطّعٌ بسمك نقطتين وبلا خلفية */
        .sl-doc.sl-doc--po .sl-upload {
            gap: .5rem;
            padding: 1.6rem 1rem;
            border: 2px dashed var(--sl-line);
            border-radius: var(--app-radius, .5rem);
            background: transparent;
        }

        .sl-doc.sl-doc--po .sl-upload i {
            font-size: calc(var(--sl-fs) * 1.7);
        }

        /* ═══════════════ شارة الرقم ═══════════════
           تُقرَّب من مربّع `po-code__badge`: الاستدارة نفسها والمقاس نفسه.
           وبقيّة بنيته — الرقم بجانب المربّع وسطرُ وصفٍ تحته — وسمٌ لا
           نمط، فلا تبلغها هذه الطبقة. */
        .sl-doc.sl-doc--po .sl-code {
            padding: .35rem .6rem;
            border-radius: var(--app-radius, .5rem);
            font-size: var(--sl-fs-sm);
        }

        /* ═══════════════ شريط الأدوات ═══════════════ */
        .sl-doc.sl-doc--po .sl-toolbar {
            gap: .5rem;
        }

        @media (max-width: 767.98px) {
            .sl-doc.sl-doc--po .sl-doc-card__body,
            .sl-doc.sl-doc--po .sl-doc-card__head,
            .sl-doc.sl-doc--po .sl-doc-card__foot {
                padding: .85rem;
            }

            /* أزرارُ الذيل تتمدّد بالتساوي كما في الأصل */
            .sl-doc.sl-doc--po .sl-doc-card__foot .btn {
                flex: 1 1 auto;
                justify-content: center;
            }
        }
    </style>
@endonce
