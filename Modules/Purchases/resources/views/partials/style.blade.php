{{--
    نظام التصميم الخاصّ بقسم «طلبات الشراء».

    كل لون وخطّ وزاوية هنا يشير إلى متغيّرات المظهر التي تحقنها
    `App\Support\ThemeSettings` في ترويسة الصفحة، فما يُضبط في شاشة
    «مظهر النظام» — اللون الأساسي، الخط، استدارة الحواف، الوضع الليلي —
    يسري على هذا القسم بلا تعديل في القالب.

    القيم الاحتياطية بعد الفاصلة هي ألوان القالب الأصلية، فتبقى الشاشة
    سليمة قبل أن يضبط المسؤول المظهر أصلاً.
--}}
<style>
    .po-page {
        --po-surface: var(--app-surface, #ffffff);
        --po-text: var(--app-text, #4b4b4b);
        --po-muted: var(--app-muted, #7b8194);
        --po-border: var(--app-border, #e6e9f0);
        --po-primary: var(--app-primary, #7367f0);
        --po-primary-soft: var(--app-primary-soft, rgba(115, 103, 240, .12));
        --po-radius: var(--app-radius, .5rem);
        --po-shadow: var(--app-card-shadow, 0 2px 10px rgba(34, 41, 47, .06));

        /* سلّم الخطّ — مشتقّ من مقاس الخطّ في «مظهر النظام».
           كانت المقاسات كلّها بـ`rem`، و`rem` نسبةٌ إلى `<html>` لا إلى
           الجسم: فمن يرفع مقاس الخطّ في المظهر من 14px إلى 18px لا يتغيّر
           في شاشات المشتريات حرف. الدرجات هنا تُحسَب من `--app-font-size`
           بـ`calc` لا بـ`em`، فلا تتضاعف مع التعشيش. */
        --po-fs: var(--app-font-size, 14px);
        --po-fs-xs: calc(var(--po-fs) * .86);
        --po-fs-sm: calc(var(--po-fs) * .93);
        --po-fs-md: var(--po-fs);
        --po-fs-lg: calc(var(--po-fs) * 1.14);
        --po-fs-xl: calc(var(--po-fs) * 1.43);
        --po-fs-2xl: calc(var(--po-fs) * 1.85);

        color: var(--po-text);
        font-family: var(--app-font-family, inherit);
        font-size: var(--po-fs);
    }

    /* ===== الترويسة ===== */
    .po-head {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: 1.25rem;
    }

    .po-head__titles {
        display: flex;
        align-items: center;
        gap: .75rem;
        min-width: 0;
    }

    .po-head__titles-text {
        min-width: 0;
    }

    /* أيقونة الترويسة — على قياس `sl-page-head__icon` فى المبيعات:
       مربّع بلون النظام الأساسي إلى جانب العنوان. اختياريّة: الشاشات
       التي لا تمرّر `icon` تبقى كما كانت، بلا فراغ فى مكانها. */
    .po-head__icon {
        flex: none;
        width: 44px;
        height: 44px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: var(--po-radius);
        background: var(--po-primary);
        color: var(--app-on-primary, #fff);
        font-size: 1.05rem;
        box-shadow: 0 4px 12px var(--app-primary-glow, rgba(115, 103, 240, .3));
    }

    .po-head__titles h2 {
        margin: 0;
        font-size: var(--po-fs-xl);
        font-weight: var(--app-heading-weight, 600);
        color: var(--po-text);
    }

    .po-head__sub {
        margin: .15rem 0 0;
        font-size: var(--po-fs-sm);
        color: var(--po-muted);
    }

    .po-head__crumbs {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: .4rem;
        margin-top: .35rem;
        font-size: var(--po-fs-sm);
        color: var(--po-muted);
    }

    .po-head__crumbs a {
        color: var(--po-muted);
        text-decoration: none;
    }

    .po-head__crumbs a:hover {
        color: var(--po-primary);
    }

    .po-head__crumbs .sep {
        opacity: .5;
    }

    .po-head__actions {
        display: flex;
        flex-wrap: wrap;
        gap: .5rem;
    }

    /* ===== البطاقات ===== */
    /* `overflow: hidden` كان يقصّ القوائم المنسدلة داخل البطاقة — قائمة
       «المطبوعات» كانت تُرسم ثم تُبتر عند حافة البطاقة فلا يظهر منها شيء.
       التدوير يُطبَّق على الجدول وحده بدلاً منه. */
    .po-card {
        background: var(--po-surface);
        border: 1px solid var(--po-border);
        border-radius: var(--po-radius);
        box-shadow: var(--po-shadow);
        margin-bottom: 1.25rem;
        overflow: visible;
    }

    .po-card > .table-responsive:last-child {
        border-end-start-radius: var(--po-radius);
        border-end-end-radius: var(--po-radius);
    }

    /* القائمة المنسدلة فوق كل شيء داخل الصفحة */
    .po-page .dropdown-menu.show {
        z-index: 1060;
    }

    /* قائمة المطبوعات تحمل كل قوالب الإعدادات — عشرون قالباً تغطّي الصفحة
       كاملة. تُحدَّد بارتفاع الشاشة وتُمرَّر داخل نفسها، ويثبت عنوانها في
       الأعلى أثناء التمرير. */
    .po-print-menu {
        max-height: min(60vh, 420px);
        overflow-y: auto;
        overscroll-behavior: contain;
        min-width: 260px;
    }

    .po-print-menu .dropdown-header {
        position: sticky;
        top: 0;
        z-index: 1;
        background: var(--po-surface);
        border-bottom: 1px solid var(--po-border);
    }

    .po-print-menu .dropdown-item {
        white-space: normal;
        line-height: 1.35;
    }

    .po-card__head {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: .75rem;
        padding: .85rem 1.1rem;
        border-bottom: 1px solid var(--po-border);
        background: var(--app-card-header-bg, transparent);
    }

    .po-card__title {
        display: flex;
        align-items: center;
        gap: .5rem;
        margin: 0;
        font-size: var(--po-fs-md);
        font-weight: 600;
        color: var(--po-text);
    }

    .po-card__title i {
        color: var(--po-primary);
    }

    .po-card__body {
        padding: 1.1rem;
    }

    /* غلاف بنيوي بلا تنسيق — يقابل `card-content` في قالب Vuexy، ويبقى
       ليحتفظ المحتوى بتعشيشه دون أن يرث تنسيق بطاقة القالب القديم */
    .po-card__content {
        display: block;
    }

    .po-card__foot {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: .75rem;
        padding: .75rem 1.1rem;
        border-top: 1px solid var(--po-border);
    }

    /* ===== شريط الملخّص ===== */
    .po-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
        gap: .85rem;
        margin-bottom: 1.25rem;
    }

    .po-stat {
        display: flex;
        align-items: center;
        gap: .75rem;
        padding: .85rem 1rem;
        background: var(--po-surface);
        border: 1px solid var(--po-border);
        border-radius: var(--po-radius);
        box-shadow: var(--po-shadow);
    }

    .po-stat__icon {
        display: grid;
        place-items: center;
        width: 40px;
        height: 40px;
        flex: 0 0 40px;
        border-radius: var(--po-radius);
        background: var(--po-primary-soft);
        color: var(--po-primary);
    }

    .po-stat__value {
        font-size: var(--po-fs-lg);
        font-weight: 700;
        line-height: 1.2;
    }

    .po-stat__label {
        font-size: var(--po-fs-sm);
        color: var(--po-muted);
    }

    /* ===== الحقول ===== */
    .po-field {
        display: flex;
        flex-direction: column;
        gap: .35rem;
    }

    .po-field > label {
        margin: 0;
        font-size: var(--po-fs-sm);
        font-weight: 500;
        color: var(--po-muted);
    }

    .po-field .required {
        color: var(--app-danger, #ea5455);
    }

    .po-hint {
        font-size: var(--po-fs-sm);
        color: var(--po-muted);
    }

    /* ===== الحقول المقفلة =====
       حقلان لا يُكتبان: رقم المستند — يولّده «تسلسل أرقام المستندات» —
       وبنودٌ جاءت من مستندٍ سابق بالتحويل. القفل وحده لا يُرى: حقلٌ
       يبدو كغيره ولا يستجيب يُقرأ «معطوب» لا «مقفل»، فيُلوَّن ويحمل
       قفلاً ومؤشّراً يقول إنه للقراءة. */
    .po-page .po-locked,
    .po-page .po-locked:focus {
        background-color: var(--app-surface-muted, #f5f6fa);
        color: var(--po-muted);
        cursor: not-allowed;
        box-shadow: none;
    }

    /* السلكت المقفل لا يُعطَّل — المعطَّل لا يُرسَل مع النموذج فيضيع البند —
       بل تُنزع خياراته الأخرى فلا يبقى ما يُختار، ويُمنع فتحه بالمؤشّر */
    .po-page select.po-locked {
        pointer-events: none;
    }

    .po-page .po-row--locked > td {
        background-color: var(--app-surface-muted, #fbfbfd);
    }

    /* شارة البند المحوَّل — تُلصق بجانب رقم الصف */
    .po-row__lock {
        display: inline-flex;
        margin-inline-start: .25rem;
        font-size: var(--po-fs-xs);
        color: var(--po-muted);
    }

    /* سلكتٌ مرتبط بجدول بيانات خارجي (صنف، مورد، ...) مع زرّ إضافة سريعة
       يفتح شاشة الإنشاء في تبويبٍ جديد دون فقدان ما أُدخل في هذا النموذج */
    .po-select-add {
        display: flex;
        align-items: stretch;
        gap: .4rem;
    }

    .po-select-add > select,
    .po-select-add > .select2-container {
        flex: 1 1 auto;
        min-width: 0;
    }

    .po-select-add__btn {
        flex: 0 0 auto;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 2.35rem;
        padding: 0;
    }

    /* ===== الجداول ===== */
    .po-table {
        width: 100%;
        margin: 0;
        border-collapse: separate;
        border-spacing: 0;
    }

    .po-table thead th {
        padding: .7rem .85rem;
        font-size: var(--po-fs-xs);
        font-weight: 600;
        letter-spacing: .02em;
        text-transform: uppercase;
        color: var(--app-table-header-text, var(--po-muted));
        background: var(--app-table-header-bg, var(--po-primary-soft));
        border-bottom: 1px solid var(--po-border);
        white-space: nowrap;
    }

    .po-table tbody td {
        padding: .75rem .85rem;
        border-bottom: 1px solid var(--po-border);
        vertical-align: middle;
    }

    .po-table tbody tr:last-child td {
        border-bottom: 0;
    }

    .po-table tbody tr:hover {
        background: var(--po-primary-soft);
    }

    .po-table .num {
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }

    /* ===== جدول البنود =====
       `po-table` أعلاه مكتوبٌ لجداول العرض: صفوفٌ تُقرأ ولا تُملأ. وجدول
       البنود نموذجُ إدخالٍ في هيئة جدول، فيحتاج ما لا يحتاجه ذاك — حقولاً
       داخل خلاياه، وسطر تلميحٍ تحت بعضها، وزرَّ حذفٍ لا يزاحم الرقم.

       القيم هنا هي قيم `sl-doc-table` في شاشة «إنشاء فاتورة مبيعات» بعد
       طبقة `sl-doc--po`: ارتفاع الحقل ٣٨ وحدُّه ظاهرٌ وخلفيتُه سطح. فما
       يراه المستخدم في الشاشتين واحد. */
    .po-page .po-doc-table td > .form-control,
    .po-page .po-doc-table td > .form-select,
    .po-page .po-doc-table td > div > .form-control,
    .po-page .po-doc-table td > div > .form-select {
        height: 38px;
        padding: 0 .75rem;
        border: 1px solid var(--po-border);
        border-radius: var(--po-radius);
        background: var(--app-input-bg, var(--po-surface));
        color: var(--po-text);
        font-size: var(--po-fs-sm);
        box-shadow: none;
    }

    .po-page .po-doc-table td > textarea.form-control {
        height: auto;
        min-height: 38px;
        padding: .45rem .75rem;
    }

    /* رقم الصف رقمٌ عاديّ لا قرصٌ ملوّن — كما في الأصل */
    .po-page .po-doc-table .po-row__index {
        color: var(--po-muted);
        font-size: var(--po-fs-sm);
        font-weight: 600;
    }

    .po-page .po-doc-table .po-row__total {
        font-weight: 700;
        font-variant-numeric: tabular-nums;
        color: var(--po-text);
    }

    /* زرُّ الحذف بلا إطار: عمودٌ عرضُه ٤٦ بكسل، وزرٌّ محاطٌ فيه يبتلع
       الخليّة. الإطار يظهر عند المرور وحده. */
    .po-page .po-doc-table .po-row__remove {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 30px;
        height: 30px;
        padding: 0;
        border: 1px solid transparent;
        border-radius: var(--po-radius);
        background: transparent;
        color: var(--app-danger, #ea5455);
        transition: background .15s ease, border-color .15s ease;
    }

    .po-page .po-doc-table .po-row__remove:hover:not(:disabled) {
        background: var(--app-danger-soft, rgba(234, 84, 85, .12));
        border-color: var(--app-danger, #ea5455);
    }

    .po-page .po-doc-table .po-row__remove:disabled {
        color: var(--po-muted);
        opacity: .55;
        cursor: not-allowed;
    }

    /* سطر تلميحٍ تحت حقلٍ في خليّة — «= ٢٤٠٠ حبة» تحت الوحدة مثلاً.
       سطرٌ واحدٌ مضغوط يُقصّ بنقاطٍ إن ضاق العمود ويظهر كاملاً في
       `title`، فلا يلتفّ النصُّ ولا يتفاوت ارتفاع الصفوف. */
    .po-page .po-doc-table .po-hint-line {
        display: block;
        margin-top: .15rem;
        font-size: calc(var(--po-fs) * .68);
        line-height: 1.3;
        color: var(--po-muted);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        font-variant-numeric: tabular-nums;
    }

    /* صنفٌ بلا وحدات: شرطةٌ مكان القائمة المخفيّة. الخليّة الفارغة في
       عمودٍ له عنوان تُقرأ «لم يُحمَّل بعد» لا «لا وحدة لهذا الصنف». */
    .po-page .po-doc-table .po-unit-none {
        color: var(--po-muted);
        font-size: var(--po-fs-sm);
    }

    /* ===== رمز الطلب ===== */
    .po-code {
        display: flex;
        align-items: center;
        gap: .65rem;
    }

    .po-code__badge {
        display: grid;
        place-items: center;
        width: 38px;
        height: 38px;
        flex: 0 0 38px;
        border-radius: var(--po-radius);
        background: var(--po-primary-soft);
        color: var(--po-primary);
        font-weight: 700;
        font-size: var(--po-fs-sm);
    }

    .po-code__value {
        font-weight: 600;
        line-height: 1.25;
    }

    .po-code__meta {
        font-size: var(--po-fs-xs);
        color: var(--po-muted);
    }

    /* ===== شارات الحالة ===== */
    .po-status {
        display: inline-flex;
        align-items: center;
        gap: .35rem;
        padding: .28rem .6rem;
        border-radius: 999px;
        font-size: var(--po-fs-xs);
        font-weight: 600;
        white-space: nowrap;
    }

    .po-status--review {
        background: var(--app-warning-soft, rgba(255, 159, 67, .14));
        color: var(--app-warning, #ff9f43);
    }

    .po-status--approved {
        background: var(--app-success-soft, rgba(40, 199, 111, .14));
        color: var(--app-success, #28c76f);
    }

    .po-status--rejected {
        background: var(--app-danger-soft, rgba(234, 84, 85, .14));
        color: var(--app-danger, #ea5455);
    }

    .po-status--quotation {
        background: var(--po-primary-soft);
        color: var(--po-primary);
    }

    /* ===== بطاقة رصيد المورّد =====
       كانت ألوانها مكتوبة في السمة `style` (أزرق ثابت ونصّ رمادي)، فلا
       تتبدّل مع «مظهر النظام» ولا تصلح في الوضع الليلي. صارت من المتغيّرات
       نفسها التي يتبعها القسم كلّه. */
    .po-balance-card {
        background: var(--po-primary-soft);
        border: 1px solid var(--po-primary);
        border-radius: var(--po-radius);
    }

    .po-balance-card__body {
        padding: 1.1rem 1.25rem;
    }

    .po-balance-card__name {
        font-weight: 600;
        color: var(--po-text);
        margin: 0;
    }

    .po-balance-card__value {
        font-size: var(--po-fs-xl);
        font-weight: 700;
        line-height: 1.1;
        color: var(--po-text);
        font-variant-numeric: tabular-nums;
    }

    .po-balance-card__bar {
        width: 4px;
        height: 40px;
        margin-top: .6rem;
        border-radius: 2px;
        background: var(--app-success, #28c76f);
    }

    /* ===== الحالة الفارغة ===== */
    .po-empty {
        padding: 3rem 1rem;
        text-align: center;
        color: var(--po-muted);
    }

    .po-empty i {
        font-size: calc(var(--po-fs) * 2.6);
        opacity: .35;
        margin-bottom: .85rem;
        display: block;
    }

    .po-empty h5 {
        color: var(--po-text);
        font-size: var(--po-fs-lg);
        margin-bottom: .35rem;
    }

    /* ===== قائمة التفاصيل ===== */
    .po-details {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
        gap: 1rem 1.5rem;
    }

    .po-detail__label {
        display: flex;
        align-items: center;
        gap: .4rem;
        font-size: var(--po-fs-sm);
        color: var(--po-muted);
        margin-bottom: .2rem;
    }

    .po-detail__value {
        font-weight: 600;
        word-break: break-word;
    }

    /* ===== الإجماليات ===== */
    .po-totals {
        margin-inline-start: auto;
        width: min(340px, 100%);
    }

    .po-totals__row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        padding: .45rem 0;
        font-size: var(--po-fs-md);
        color: var(--po-muted);
    }

    .po-totals__row span:last-child {
        font-variant-numeric: tabular-nums;
        color: var(--po-text);
        font-weight: 600;
    }

    .po-totals__row--grand {
        margin-top: .35rem;
        padding-top: .7rem;
        border-top: 1px solid var(--po-border);
        font-size: var(--po-fs-lg);
        color: var(--po-text);
        font-weight: 700;
    }

    .po-totals__row--grand span:last-child {
        color: var(--po-primary);
        font-size: var(--po-fs-lg);
    }

    /* ===== منطقة الرفع ===== */
    .po-upload {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: .5rem;
        padding: 1.6rem 1rem;
        border: 2px dashed var(--po-border);
        border-radius: var(--po-radius);
        cursor: pointer;
        transition: border-color .15s, background .15s;
        text-align: center;
    }

    .po-upload:hover {
        border-color: var(--po-primary);
        background: var(--po-primary-soft);
    }

    .po-upload i {
        font-size: calc(var(--po-fs) * 1.7);
        color: var(--po-primary);
    }

    /* ===== التبويبات ===== */
    .po-tabs {
        display: flex;
        flex-wrap: wrap;
        gap: .25rem;
        padding: 0 1.1rem;
        border-bottom: 1px solid var(--po-border);
    }

    .po-tabs .nav-link {
        border: 0;
        border-bottom: 2px solid transparent;
        border-radius: 0;
        padding: .7rem .9rem;
        font-size: var(--po-fs-md);
        font-weight: 500;
        color: var(--po-muted);
        background: transparent;
    }

    .po-tabs .nav-link.active {
        color: var(--po-primary);
        border-bottom-color: var(--po-primary);
        background: transparent;
    }

    /* ===== جدول العرض =====
       الصنف `modern-table` غير معرَّف في أي ورقة أنماط في المشروع، فجدول
       شاشة «سجلّ النشاطات» يرث تنسيق `.table` من قالب النظام وحده. لا
       تُضاف هنا قواعد تغيّر شكله، وإلا اختلف الجدولان — وهذا هو المطلوب:
       أن يكون الجدولان واحداً.

       ما يبقى فقط ما لا يمسّ الشكل: عرض الأرقام بأرقام متساوية العرض. */
    .po-page .modern-table .num {
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }

    /* مربّع التحديد وزرّ الخيارات — يقابلان `custom-checkbox` و`bg-gradient-info`
       في شاشة «سجلّ النشاطات». تلك أصنافُ قالب Bootstrap 4، وواجهةُ المشتريات
       تحمّل Bootstrap 5 فوقها، فيُعرَّفان هنا ليخرج الشكل واحداً في الشاشتين. */
    .po-check {
        width: 16px;
        height: 16px;
        accent-color: var(--po-primary);
        cursor: pointer;
        vertical-align: middle;
    }

    .po-page .bg-gradient-info {
        background-image: linear-gradient(47deg, var(--app-info, #00cfe8), var(--po-primary));
        border: 0;
        color: #fff;
        width: 32px;
        height: 32px;
        line-height: 1;
        padding: 0;
        border-radius: var(--app-button-radius, var(--po-radius));
    }

    .po-page .bg-gradient-info::after {
        display: none;
    }

    .po-page .bg-gradient-info:hover {
        filter: brightness(1.06);
        color: #fff;
    }

    /* شريط الترقيم المشترك يأتي بحشوه الخاص */
    #pagination-container .pagination-info {
        font-size: var(--po-fs-sm);
    }

    /* ===== دقائق ===== */
    .po-toolbar {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: .5rem;
    }

    /* ===== شريط الإجراءات =====
       أزرارٌ متساوية المقاس مهما اختلف وسمُها.

       ثلاثة أشكال تتجاور في هذا الشريط: `<a class="btn">` للتنقّل،
       و`<button>` داخل `<form>` لِما يحتاج POST (الاعتماد والحذف
       والإلغاء)، و`div.dropdown` للمطبوعات. والشبكة `flex`
       بمحاذاتها الافتراضية `stretch`، فكان:

         • الروابط تُمطّ إلى ارتفاع أطول عنصرٍ في السطر (٥٣px) لا إلى
           ارتفاعها الطبيعي (٣٩px)
         • والزرّ داخل `<form>` يبقى ٣٩px، لأن الممطوط هو الـ`form`
           وحده لا الزرّ الذي بداخله — فيتجاور زرّان بفارق ١٤ بكسل
         • و`.d-inline` على الـ`form` يؤول إلى `display: block` هنا،
           فيكسر السطر ويهبط الزرّ وحده سطراً رغم اتّساع ما بعده

       فالمحاذاة إلى الوسط تُلغي المطّ، والـ`form` يصير `inline-flex`
       بمقاس زرّه فلا يزاحمه ولا يكسر السطر، و`min-height` يوحّد
       الارتفاع حتى لو اختلف محتوى الزرّ (أيقونةٌ وحدها أو نصٌّ طويل). */
    .po-actions-grid {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: .5rem;
    }

    /* الـ`form` غلافُ إرسالٍ لا عنصرُ تخطيط: بمقاس زرّه تماماً */
    .po-actions-grid > form,
    .po-toolbar > form,
    .po-card__foot > form {
        display: inline-flex;
        margin: 0;
    }

    .po-actions-grid .btn,
    .po-toolbar .btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: .45rem;
        min-height: 38px;
        line-height: 1.35;
    }

    /* الحشوة الرأسية مضبوطة هنا لا متروكة للقالب.

       في ورقة النظام قاعدتان تتنازعان زرّ الشريط:

         .btn[class*="btn-outline-"]                    ← ١١٫٦px
         .dropdown .btn:not(.btn-sm):not(.btn-lg)       ← ١١٫٩px (اختصار)

       والثانية أخصُّ (٠٫٤٫٠) من قاعدة الشريط أعلاه (٠٫٢٫٠)، فتغلبها في
       زرّ «المطبوعات» وحده — فيخرج أطول من جيرانه بسبعة بكسل. ولأنها
       تكتب `padding` اختصاراً فهي تمسح الرأسيّ والأفقيّ معاً.

       فتُكرَّر هنا بأخصَّ منها (٠٫٦٫٠) للحالة المنسدلة، وتبقى الحشوة
       الأفقية على حالها فعرضُ الزرّ يتبع نصَّه. */
    .po-page .po-actions-grid .btn,
    .po-page .po-toolbar .btn,
    .po-page .po-actions-grid .dropdown .btn:not(.btn-sm):not(.btn-lg),
    .po-page .po-toolbar .dropdown .btn:not(.btn-sm):not(.btn-lg) {
        padding-top: .5rem;
        padding-bottom: .5rem;
    }

    .po-doc-frame {
        background: #fff;
        border: 1px solid var(--po-border);
        border-radius: var(--po-radius);
        padding: 1rem;
        overflow-x: auto;
    }

    @media (max-width: 767.98px) {
        .po-head__actions .btn,
        .po-card__foot .btn {
            flex: 1 1 auto;
            justify-content: center;
        }

        .po-card__body {
            padding: .85rem;
        }

        .po-table thead {
            display: none;
        }

        .po-table tbody td {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: .55rem .85rem;
            border-bottom: 0;
        }

        .po-table tbody td::before {
            content: attr(data-label);
            font-size: var(--po-fs-xs);
            font-weight: 600;
            color: var(--po-muted);
        }

        .po-table tbody tr {
            display: block;
            border-bottom: 1px solid var(--po-border);
            padding: .4rem 0;
        }
    }
</style>

<style>
    /* select2 داخل القسم — يرث ألوان المظهر وارتفاع الحقول بدل شكله الافتراضي */
    .po-page .select2-container--default .select2-selection--single,
    .po-page .select2-container--default .select2-selection--multiple {
        background: var(--app-input-bg, var(--po-surface));
        border: 1px solid var(--po-border);
        border-radius: var(--po-radius);
        min-height: calc(1.5em + .75rem + 2px);
        padding: .15rem .3rem;
    }

    .po-page .select2-container--default .select2-selection--single .select2-selection__rendered {
        color: var(--po-text);
        line-height: 1.9;
    }

    /* الوسوم المختارة في الاختيار المتعدّد — ظاهرة بلون النظام الأساسي */
    .po-page .select2-container--default .select2-selection--multiple .select2-selection__choice {
        background: var(--po-primary-soft);
        border: 1px solid var(--po-primary);
        color: var(--po-primary);
        border-radius: 999px;
        padding: .1rem .55rem;
        margin: .18rem .2rem;
    }

    .po-page .select2-container--default .select2-selection--multiple .select2-selection__choice__remove {
        color: var(--po-primary);
        margin-inline-end: .35rem;
    }

    .po-page .select2-dropdown {
        border-color: var(--po-border);
        border-radius: var(--po-radius);
    }

    .po-page .select2-container--default .select2-results__option--highlighted[aria-selected] {
        background: var(--po-primary);
        color: var(--app-on-primary, #fff);
    }

    /* ═══════════ الخطّ الزمني ═══════════
       سجلّ نشاط المستند — نظير `sl-timeline` في المبيعات، بالبادئة `po-`.
       نسخةٌ لا مشاركة: قالبٌ في المشتريات يشير إلى `sales::` ينكسر بمجرّد
       إطفاء وحدة المبيعات في `modules_statuses.json`. */

    .po-timeline {
        position: relative;
        padding-inline-start: 1.25rem;
        margin-bottom: 1.25rem;
    }

    .po-timeline:last-child { margin-bottom: 0; }

    .po-timeline__day {
        font-size: .8rem;
        font-weight: 700;
        color: var(--app-muted, #6e6b7b);
        margin: 0 0 .65rem;
    }

    .po-timeline__day .num { margin-inline-start: .35rem; font-weight: 400; }

    .po-timeline__item {
        position: relative;
        display: flex;
        gap: .75rem;
        padding: 0 0 .9rem;
    }

    /* الخيط الواصل — يقف عند آخر عنصر فلا يتدلّى في الفراغ */
    .po-timeline__item::before {
        content: '';
        position: absolute;
        inset-inline-start: -.8rem;
        top: .45rem;
        bottom: -.45rem;
        width: 1px;
        background: var(--po-line, #e4e6eb);
    }

    .po-timeline__item:last-child::before { display: none; }

    .po-timeline__dot {
        position: absolute;
        inset-inline-start: -1.05rem;
        top: .3rem;
        width: .5rem;
        height: .5rem;
        border-radius: 50%;
        background: var(--po-primary, #7367f0);
    }

    .po-timeline__time {
        flex: 0 0 auto;
        font-size: .75rem;
        color: var(--app-muted, #6e6b7b);
        min-width: 2.6rem;
    }

    .po-timeline__text { font-size: .85rem; line-height: 1.8; min-width: 0; }
    .po-timeline__text p { margin: 0; }

    /* ═══════════════ شريطُ سياق المستند ═══════════════

       نظيرُ `.sl-doc-note` في شاشة إنشاء فاتورة المبيعات، طلبَ المستخدم
       أن تكون شاشاتُ إنشاء المشتريات على قياسها. العملةُ والترقيمُ كانا
       يجريان في الخلفية بلا ذِكر، فيُفاجَأ المستخدم برقمٍ غير الذي رآه
       أو بعملةٍ غير التي توقّع. */

    .po-doc-note {
        display: flex;
        align-items: flex-start;
        gap: .75rem;
        padding: .75rem 1rem;
        margin-bottom: 1rem;
        border: 1px solid var(--po-border);
        border-inline-start: 3px solid var(--po-primary, #7367f0);
        border-radius: var(--po-radius);
        background: color-mix(in srgb, var(--po-primary, #7367f0) 4%, var(--app-surface, #fff));
        font-size: var(--po-fs-sm);
    }

    .po-doc-note--warn { border-inline-start-color: var(--app-warning, #ff9f43); }

    .po-doc-note__icon {
        flex: none;
        color: var(--po-primary, #7367f0);
        font-size: 1rem;
        line-height: 1.5;
    }

    .po-doc-note--warn .po-doc-note__icon { color: var(--app-warning, #ff9f43); }

    .po-doc-note__body { min-width: 0; }
    .po-doc-note__body strong { color: var(--po-text); }

    .po-doc-note .po-code-inline {
        font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
        font-weight: 700;
        background: color-mix(in srgb, var(--po-primary, #7367f0) 12%, transparent);
        color: var(--po-primary, #7367f0);
        border-radius: 4px;
        padding: .05rem .4rem;
    }

    /* ═══════════════ شارات داخل شريط السياق ═══════════════
       موردو طلب التسعير في شاشة عرض السعر: كلٌّ رابطٌ يفتح الشاشة على
       ذلك المورّد، والمسجَّل عرضُه يُعلَّم بعلامة بدل زائد. */
    .po-doc-note__chips {
        display: flex;
        flex-wrap: wrap;
        gap: .35rem;
        margin-top: .5rem;
    }

    .po-chip {
        display: inline-flex;
        align-items: center;
        gap: .3rem;
        padding: .25rem .6rem;
        border: 1px solid var(--po-border);
        border-radius: 999px;
        background: var(--po-surface);
        color: var(--po-text);
        font-size: var(--po-fs-sm);
        line-height: 1.6;
        text-decoration: none;
        transition: border-color .15s ease, background .15s ease, color .15s ease;
    }

    .po-chip:hover {
        border-color: var(--po-primary);
        color: var(--po-primary);
        text-decoration: none;
    }

    .po-chip i { font-size: .9em; }

    /* عرضُه مسجَّل — لا يُمنع تكرارُه، لكن يُعرَف أنّه تمّ */
    .po-chip--done {
        border-color: color-mix(in srgb, var(--app-success, #28c76f) 40%, transparent);
        background: color-mix(in srgb, var(--app-success, #28c76f) 8%, var(--po-surface));
        color: var(--app-success, #28c76f);
    }

    /* المورّد المفتوحة عليه الشاشة الآن */
    .po-chip--current {
        border-color: var(--po-primary);
        background: var(--po-primary-soft);
        color: var(--po-primary);
        font-weight: 600;
    }
</style>
