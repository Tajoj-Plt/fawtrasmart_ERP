{{--
    ══════════════════════════════════════════════════════════════════════
    مطابقة شاشات إنشاء وتعديل مستندات المشتريات لـ«إنشاء فاتورة مبيعات»
    ══════════════════════════════════════════════════════════════════════

    **لماذا هذا الملفّ أصلاً؟**

    طلب المستخدم (٢٠٢٦-٠٩-١٩) أن تكون شاشاتُ «فاتورة الشراء» و«أمر
    الشراء» و«عرض السعر» و«الإشعار» و«مرتجع الشراء» — إنشاءً وتعديلاً —
    على تصميم «إنشاء فاتورة مبيعات» بالضبط.

    والشاشتان متشابهتان بنيةً منذ كُتبتا: الترتيب نفسه (بيانات المستند ←
    البنود ← التفاصيل الإضافية في تبويبات ← الملاحظات وأزرار الحفظ)،
    وجدول البنود نفسه، ولوحة الإجماليات نفسها — انظر ترويسة
    `sales::partials.document-theme-po` التي تحكي الاتجاه المعاكس: شاشاتُ
    المبيعات رُدَّت إلى قياس المشتريات قبل ذلك.

    فالفارق لم يكن في الهيكل بل في القيم، وقياسُه بالفروق المحسوبة
    (`getComputedStyle`) بين الشاشتين أخرج ثلاثةً وعشرين فرقاً:

      • الاستدارة   المبيعات `--sl-r` = نصف القطر + ٢px، والمشتريات نصف
                    القطر كما هو — والأزرار والحقول في المشتريات على
                    استدارة بوتستراب الافتراضية أصلاً (٥٫٢٥px)
      • الأزرار     لا تُصاغ في نطاق `po-page` أبداً: ارتفاعُها وخطُّها
                    ووزنُها وظلُّها من القالب لا من نظام التصميم
      • الحقول      كذلك — ٣٨px بخطّ ١٤px، والمبيعات ٤٠px بخطّ ٠٫٨٧٥rem
      • الترويسة    العنوان أكبر بنقطة، والفتات بوسمٍ آخر ومقاسٍ آخر
      • الجدول      خليّةُ البنود ١٦px، وفاصلُها أعلى الخليّة لا أسفلها
      • الإجمالي    سطر «الإجمالي النهائي» كلُّه بمقاس `lg` لا قيمتُه وحدها

    **لماذا طبقةٌ مستقلّة لا تعديلٌ في `partials/style`؟**

    لأن `partials/style` يخدم — إلى جانب شاشات الإنشاء — شاشاتِ العرض
    وقائمةَ المطبوعات وبطاقاتِ الإحصاء وكشفَ المورد. وتعديلُه فيها كلَّها
    لم يُطلَب. فالمطابقة معلّقةٌ على صنف `po-doc--sl` على غلاف الصفحة،
    ولا تسري إلّا حيث يُوضع.

    **الترتيب مهمّ:** يُستدعى **بعد** `purchases::partials.style`، فالبادئة
    `.po-page.po-doc--sl` أعلى تخصيصاً من `.po-page` على أيّ حال، وقواعدُ
    الجدول تحمل درجةً ثالثة كي لا تغلبها قواعدُ الورقة الأصلية.

    **لا وسم يُمسّ، ولا مُعرِّف، ولا صنف يقرؤه**
    `public/assets/js/purchase-orders-form.js` — `po-input` و`po-calc`
    و`po-row` و`po-tax-rate` وأخواتها كما هي، فعقد الـDOM محفوظ والحساب
    كما كان.

    الاستعمال:

        @section('css')
            @include('purchases::partials.style')
            @include('purchases::partials.doc-theme-sl')
        @endsection

        <div class="po-page po-doc--sl"> … </div>
--}}

@once
    <style id="po-doc-sl-parity">
        /* ═══════════════ الرموز ═══════════════
           `--po-r` نظيرُ `--sl-r`: نصفُ القطر المضبوط في «مظهر النظام»
           زائد نقطتين. لا يُغيَّر `--po-radius` نفسُه فتبقى بقيّةُ شاشات
           القسم — العرضُ والكشوف والمطبوعات — على استدارتها. */
        .po-page.po-doc--sl {
            --po-r: calc(var(--app-radius, 6px) + 2px);
            --po-e1: 0 1px 2px rgba(16, 24, 40, .04);
            --po-e3: 0 4px 12px rgba(16, 24, 40, .07), 0 2px 4px rgba(16, 24, 40, .04);
        }

        /* ═══════════════ ترويسة الصفحة ═══════════════ */

        .po-page.po-doc--sl .po-head { margin-bottom: 1.5rem; }

        .po-page.po-doc--sl .po-head__icon {
            border-radius: var(--po-r);
            font-size: 1.15rem;
        }

        .po-page.po-doc--sl .po-head__titles h2 {
            font-size: 1.35rem;
            font-weight: var(--app-heading-weight, 700);
            line-height: 1.25;
            letter-spacing: -.01em;
            margin: 0 0 .15rem;
        }

        .po-page.po-doc--sl .po-head__sub {
            font-size: .82rem;
            margin: 0;
        }

        /* الفتات بوسم بوتستراب نفسِه الذي تستعمله ترويسةُ المبيعات
           (`ol.breadcrumb`)، فيأخذ فاصلَ القالب ومقاسَه — انظر
           `partials/head` ووسيطَها `crumbStyle`. */
        .po-page.po-doc--sl .po-head .breadcrumb {
            margin: .15rem 0 0;
            padding: 0;
            background: none;
            font-size: .78rem;
        }

        .po-page.po-doc--sl .po-head .breadcrumb-item + .breadcrumb-item::before {
            color: var(--app-muted, #6e6b7b);
            opacity: .5;
        }

        /* ═══════════════ الأزرار ═══════════════
           نقلٌ حرفيّ عن `.sl-scope .btn` — حجمٌ واحد ووزنٌ واحد ومسافةُ
           أيقونةٍ واحدة. كانت أزرارُ شاشات المشتريات كلُّها من القالب:
           ٤٦px في الذيل و٣٢px في الجدول، بخطّ ١٤px ووزن ٤٠٠. */
        .po-page.po-doc--sl .btn {
            --po-btn-bg: transparent;
            --po-btn-fg: var(--app-text, #4b4b4b);
            --po-btn-bd: var(--po-border);
            display: inline-flex; align-items: center; justify-content: center; gap: .45rem;
            height: 38px; min-height: 0; padding: 0 1rem;
            font-size: .86rem; font-weight: 600; line-height: 1;
            border: 1px solid var(--po-btn-bd);
            border-radius: var(--po-r);
            background: var(--po-btn-bg); color: var(--po-btn-fg);
            box-shadow: var(--po-e1);
            transition: background .16s ease, border-color .16s ease, color .16s ease,
                        box-shadow .16s ease, transform .16s ease;
            white-space: nowrap;
        }

        .po-page.po-doc--sl .btn > i,
        .po-page.po-doc--sl .btn > svg { font-size: .95em; line-height: 1; }

        /* الهوامش المكتوبة على الأيقونات (`me-1` وأخواتها) تُلغى: الفجوة
           من `gap` وحدها، وإلا صارت مسافتان */
        .po-page.po-doc--sl .btn i.me-1,
        .po-page.po-doc--sl .btn i.me-2,
        .po-page.po-doc--sl .btn i.mr-1,
        .po-page.po-doc--sl .btn i.mr-50 { margin: 0 !important; }

        .po-page.po-doc--sl .btn:hover { transform: translateY(-1px); box-shadow: var(--po-e3); }
        .po-page.po-doc--sl .btn:active { transform: translateY(0); box-shadow: var(--po-e1); }
        .po-page.po-doc--sl .btn:focus-visible { outline: 2px solid var(--po-primary); outline-offset: 2px; }

        .po-page.po-doc--sl .btn-sm { height: 32px; padding: 0 .75rem; font-size: .8rem; }
        .po-page.po-doc--sl .btn-lg { height: 44px; padding: 0 1.5rem; font-size: .92rem; }

        .po-page.po-doc--sl .btn-primary,
        .po-page.po-doc--sl .btn-primary:not(:disabled):not(.disabled) {
            --po-btn-bg: var(--po-primary);
            --po-btn-fg: var(--app-on-primary, #fff);
            --po-btn-bd: var(--po-primary);
        }

        .po-page.po-doc--sl .btn-primary:hover,
        .po-page.po-doc--sl .btn-primary:focus,
        .po-page.po-doc--sl .btn-primary:not(:disabled):not(.disabled):active {
            background: var(--app-primary-hover, #5e50ee) !important;
            border-color: var(--app-primary-hover, #5e50ee) !important;
            color: var(--app-on-primary, #fff) !important;
            box-shadow: 0 6px 16px var(--app-primary-glow, rgba(115, 103, 240, .3)) !important;
        }

        .po-page.po-doc--sl .btn-success { --po-btn-bg: var(--app-success, #28c76f); --po-btn-fg: var(--app-on-primary, #fff); --po-btn-bd: var(--app-success, #28c76f); }
        .po-page.po-doc--sl .btn-danger  { --po-btn-bg: var(--app-danger, #ea5455);  --po-btn-fg: var(--app-on-primary, #fff); --po-btn-bd: var(--app-danger, #ea5455); }
        .po-page.po-doc--sl .btn-info    { --po-btn-bg: var(--app-info, #00cfe8);    --po-btn-fg: var(--app-on-primary, #fff); --po-btn-bd: var(--app-info, #00cfe8); }
        .po-page.po-doc--sl .btn-warning { --po-btn-bg: var(--app-warning, #ff9f43); --po-btn-fg: var(--app-on-primary, #fff); --po-btn-bd: var(--app-warning, #ff9f43); }
        .po-page.po-doc--sl .btn-secondary,
        .po-page.po-doc--sl .btn-light   { --po-btn-bg: var(--app-surface, #fff); --po-btn-fg: var(--app-text, #4b4b4b); }

        .po-page.po-doc--sl .btn-outline-primary   { --po-btn-fg: var(--po-primary); --po-btn-bd: color-mix(in srgb, var(--po-primary) 40%, transparent); }
        .po-page.po-doc--sl .btn-outline-secondary { --po-btn-fg: var(--po-muted);   --po-btn-bd: var(--po-border); }
        .po-page.po-doc--sl .btn-outline-danger    { --po-btn-fg: var(--app-danger, #ea5455);  --po-btn-bd: color-mix(in srgb, var(--app-danger, #ea5455) 40%, transparent); }
        .po-page.po-doc--sl .btn-outline-success   { --po-btn-fg: var(--app-success, #28c76f); --po-btn-bd: color-mix(in srgb, var(--app-success, #28c76f) 40%, transparent); }
        .po-page.po-doc--sl .btn-outline-info      { --po-btn-fg: var(--app-info, #00cfe8);    --po-btn-bd: color-mix(in srgb, var(--app-info, #00cfe8) 40%, transparent); }
        .po-page.po-doc--sl .btn-outline-warning   { --po-btn-fg: var(--app-warning, #ff9f43); --po-btn-bd: color-mix(in srgb, var(--app-warning, #ff9f43) 40%, transparent); }

        .po-page.po-doc--sl .btn-outline-primary:hover   { background: var(--po-primary); color: var(--app-on-primary, #fff); border-color: var(--po-primary); }
        .po-page.po-doc--sl .btn-outline-secondary:hover { background: var(--app-body-bg, #f8f8f8); color: var(--app-text, #4b4b4b); }
        .po-page.po-doc--sl .btn-outline-danger:hover    { background: var(--app-danger, #ea5455);  color: var(--app-on-primary, #fff); border-color: var(--app-danger, #ea5455); }
        .po-page.po-doc--sl .btn-outline-success:hover   { background: var(--app-success, #28c76f); color: var(--app-on-primary, #fff); border-color: var(--app-success, #28c76f); }
        .po-page.po-doc--sl .btn-outline-info:hover      { background: var(--app-info, #00cfe8);    color: var(--app-on-primary, #fff); border-color: var(--app-info, #00cfe8); }
        .po-page.po-doc--sl .btn-outline-warning:hover   { background: var(--app-warning, #ff9f43); color: var(--app-on-primary, #fff); border-color: var(--app-warning, #ff9f43); }

        .po-page.po-doc--sl .btn-group > .btn { box-shadow: none; }
        .po-page.po-doc--sl .btn-group > .btn:hover { transform: none; }

        /* ═══════════════ الحقول ═══════════════
           نقلٌ حرفيّ عن `.sl-scope .form-control` — أربعون بكسلاً بخطّ
           ٠٫٨٧٥rem واستدارةِ `--po-r`، والسطحُ أبيض لا رماديّ القالب. */
        .po-doc--sl .form-control,
        .po-doc--sl .custom-select,
        .po-doc--sl .form-select,
        .po-doc--sl .select2-container--default .select2-selection--single {
            height: 40px;
            padding: 0 .75rem;
            background: var(--po-surface);
            border: 1px solid var(--po-border);
            border-radius: var(--po-r);
            color: var(--po-text);
            font-size: .875rem;
            box-shadow: none;
            transition: border-color .15s ease, box-shadow .15s ease;
        }

        .po-doc--sl textarea.form-control {
            height: auto;
            padding: .75rem;
            min-height: 90px;
        }

        .po-doc--sl .form-control:focus,
        .po-doc--sl .custom-select:focus,
        .po-doc--sl .form-select:focus,
        .po-doc--sl .select2-container--default.select2-container--focus .select2-selection--single {
            border-color: var(--po-primary);
            box-shadow: 0 0 0 3px var(--po-primary-soft);
            outline: 0;
        }

        .po-doc--sl .form-control::placeholder { color: var(--po-muted); opacity: .7; }
        .po-doc--sl .form-control.is-invalid { border-color: var(--app-danger, #ea5455); }

        .po-page.po-doc--sl .input-group-text {
            background: color-mix(in srgb, var(--po-primary) 4%, var(--po-surface));
            border: 1px solid var(--po-border);
            color: var(--po-muted);
            border-radius: var(--po-r);
        }

        .po-page.po-doc--sl .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 38px; color: var(--po-text); padding: 0;
        }
        .po-page.po-doc--sl .select2-container--default .select2-selection--single .select2-selection__arrow { height: 38px; }

        /* ═══════════════ شريط السياق وشارة الرقم ═══════════════ */

        .po-page.po-doc--sl .po-doc-note { border-radius: var(--po-r); }

        /* نظيرُ `sl-code`: شارةٌ لا نصٌّ ملوّن — حشوةٌ ووزنٌ وتباعدٌ حرفيّ */
        .po-page.po-doc--sl .po-code-inline {
            display: inline-flex; align-items: center; gap: .4rem;
            padding: .35rem .6rem;
            border-radius: var(--app-radius, 6px);
            background: var(--po-primary-soft);
            color: var(--po-primary);
            font-size: var(--po-fs-sm); font-weight: 700;
            font-variant-numeric: tabular-nums; letter-spacing: .02em;
        }

        /* ═══════════════ البطاقة ═══════════════ */

        .po-page.po-doc--sl .po-card__title i { font-size: .95em; }

        .po-page.po-doc--sl .po-card__foot {
            border-end-start-radius: var(--app-radius, 6px);
            border-end-end-radius: var(--app-radius, 6px);
        }

        /* ═══════════════ التبويبات ═══════════════ */

        .po-page.po-doc--sl .po-tabs { padding: 0 1.1rem; }

        .po-page.po-doc--sl .po-tabs .nav-link {
            display: inline-flex; align-items: center; gap: .4rem;
        }

        /* ═══════════════ جدول البنود ═══════════════
           درجةٌ ثالثة في التخصيص كي لا تغلبها قواعدُ `partials/style`
           ولا القواعدُ العامّة أعلاه: حقلُ الخليّة ٣٨px لا ٤٠. */

        .po-page.po-doc--sl .po-doc-table > thead > tr > th {
            border-top: 0;
        }

        .po-page.po-doc--sl .po-doc-table > tbody > tr > td {
            font-size: .875rem;
            border-top: 0;
            border-bottom: 1px solid var(--po-border);
        }

        .po-page.po-doc--sl .po-doc-table > tbody > tr:last-child > td {
            border-bottom: 0;
        }

        .po-page.po-doc--sl .po-doc-table .po-row__index {
            display: inline-flex; align-items: center; justify-content: center;
        }

        .po-page.po-doc--sl .po-doc-table .form-control,
        .po-page.po-doc--sl .po-doc-table .form-select,
        .po-page.po-doc--sl .po-doc-table .custom-select,
        .po-page.po-doc--sl .po-doc-table .po-input {
            height: 38px;
            min-height: 0;
            padding: 0 .75rem;
            border-radius: var(--po-r);
        }

        .po-page.po-doc--sl .po-doc-table textarea.form-control {
            height: auto; min-height: 38px; padding: .45rem .75rem;
        }

        /* زرُّ الحذف ٣٠×٣٠ بلا إطار — هو `.sl-row__remove` في «إنشاء فاتورة
           مبيعات» حرفياً؛ كان هنا `width:auto` فاتّسع إلى ٤٤px وبدا زرّاً آخر */
        .po-page.po-doc--sl .po-row__remove { width: 30px; height: 30px; }

        /* عمودُ الوحدة فارغٌ حتى يُختار صنفٌ له وحدات — كما في المبيعات،
           لا شرطةً تشغل الخليّة */
        .po-page.po-doc--sl .po-doc-table .po-unit-none { display: none !important; }

        /* ═══════════════ الإجماليات ═══════════════
           سطرُ «الإجمالي النهائي» بمقاس الخطّ الأساس، وقيمتُه وحدها أكبر */

        .po-page.po-doc--sl .po-totals__row--grand { font-size: var(--po-fs); }

        .po-page.po-doc--sl .po-totals__row--grand > span:last-child {
            font-size: var(--po-fs-lg);
        }

        /* ═══════════════ رفع المرفقات ═══════════════ */

        .po-page.po-doc--sl .po-upload {
            margin-bottom: .4rem;
            font-size: .82rem;
            font-weight: 600;
            line-height: 1.5;
            justify-content: center;
        }

        /* ═══════════════ قائمةٌ بجانبها زرُّ إضافة ═══════════════ */

        .po-page.po-doc--sl .po-select-add__btn {
            flex: 0 0 auto;
            width: 2.35rem;
        }
    </style>
@endonce
