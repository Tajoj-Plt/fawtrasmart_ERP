{{-- ═══════════════════════════════════════════════════════════════════════
     نظام تصميم موديول الفروع

     القالب يحمّل ثيم Vuexy (Bootstrap 4) ثم Bootstrap 5 من CDN فوقه. هذا
     التعارض يترك أثراً مرئياً في كل شاشة: ارتفاعات حقول مختلفة في الصفّ
     الواحد، أنصاف أقطار متضاربة، ومفاتيح `custom-switch` مكسورة تماماً.
     هذا الملف يُحمَّل في <head> بعد الملفّين معاً، فهو الفرصة الوحيدة
     لحسم الشكل حسماً واحداً.

     ما فيه ثلاثة أقسام:
       ١) رموز التصميم — لون واحد ونصف قطر واحد وظلّ واحد لكل الموديول
       ٢) تصحيح تعارض BS5/BS4 — الحقول والأزرار والمفاتيح
       ٣) مكوّنات الموديول — البطاقات والجداول والشجرة والحالات الفارغة

     كل شيء مقصور على شاشات الفروع: هذا الملف لا يُحمَّل خارج قالبها.
     ═══════════════════════════════════════════════════════════════════════ --}}
<style>
    /* ═══════════════ ١) رموز التصميم ═══════════════

       الرموز مشتقّة من متغيّرات «مظهر النظام» (`App\Support\ThemeSettings`)
       مع قيمة احتياطية لكل واحد. هذا يجعل الموديول يتبع ما يضبطه المستخدم
       في شاشة المظهر — اللون الأساسي، الخط، حجمه، ونصف القطر — بدل أن
       يفرض قيمه هو.

       سبب الحاجة إلى هذا: قالب الموديول يضمّ `head_rtl` (وفيه أنماط المظهر)
       ثم يفتح رأسه ويحمّل هذه الورقة بعدها، فكانت قِيَمي تلغي إعدادات المظهر
       كلّها. ومقاسات الخط كانت بـ `rem` — نسبةً لجذر الصفحة لا لحجم الخط
       المضبوط على `body` — فلم تكن تتأثر بإعداد «حجم الخط» إطلاقاً، ولهذا
       بدا الخط صغيراً مهما رُفع الإعداد.
       ═══════════════════════════════════════════════ */
    :root {
        --bx-primary: var(--app-primary, #7367f0);
        --bx-primary-soft: var(--app-primary-soft, rgba(115, 103, 240, .1));
        --bx-success: var(--app-success, #28c76f);
        --bx-info: #00cfe8;
        --bx-warning: #ff9f43;
        --bx-danger: var(--app-danger, #ea5455);

        --bx-ink: var(--app-text, #4b4b5a);
        --bx-ink-strong: var(--app-text, #2f2f42);
        --bx-ink-muted: var(--app-muted, #8b8a9e);

        --bx-line: var(--app-border, #ecebf3);
        --bx-line-strong: var(--app-border, #dddbe9);
        --bx-surface: var(--app-surface, #fff);
        --bx-surface-sunken: #f7f7fb;

        --bx-radius: var(--app-radius, 12px);
        --bx-radius-sm: var(--app-radius, 8px);
        --bx-shadow: 0 1px 3px rgba(34, 41, 47, .04), 0 6px 20px rgba(34, 41, 47, .05);
        --bx-shadow-lift: 0 6px 14px rgba(34, 41, 47, .08), 0 14px 34px rgba(34, 41, 47, .07);

        /* ── سلّم الخطوط ──
           كلّه محسوب من حجم الخط المضبوط في «مظهر النظام»، فيكبر النصّ في
           الموديول كلّه حين يرفع المستخدم الإعداد وينقص حين ينقصه. */
        --bx-fs: var(--app-font-size, 14px);
        --bx-fs-xs: calc(var(--bx-fs) * .8);
        --bx-fs-sm: calc(var(--bx-fs) * .88);
        --bx-fs-md: var(--bx-fs);
        --bx-fs-lg: calc(var(--bx-fs) * 1.12);
        --bx-fs-xl: calc(var(--bx-fs) * 1.5);
        --bx-fs-2xl: calc(var(--bx-fs) * 2.1);
    }

    body { color: var(--bx-ink); font-family: var(--app-font-family, inherit); }
    .content-body, .content-header { font-size: var(--bx-fs-md); }

    .content-wrapper { padding-bottom: 1.5rem; }

    /* ═══════════════ ٢) تصحيح تعارض BS5 / Vuexy ═══════════════ */

    /*
        الحقولُ والأزرارُ والشاراتُ والقوائمُ المنسدلة **لا تُعرَّف هنا**.

        كانت معرَّفةً بقياسات هذه الوحدة (حقلٌ 2.55rem، وزرٌّ بظلٍّ بنفسجيّ،
        وشارةٌ مربّعة)، فتخرج شاشاتُ الفروع بمظهرٍ غير مظهر المبيعات مع أن
        المكوّنات واحدة. صارت كلُّها من `branches::layouts.sales-theme` —
        نسخةِ لغة `sl-` — تحت `.sl-scope`، فلا يبقى قياسان لشيءٍ واحد.
    */

    /* ── مفاتيح التبديل ومربّعات الاختيار ──
       `custom-switch` من BS4 وحُذف في BS5. قواعد Vuexy له تضع صندوق المفتاح
       بـ `position:absolute; right:-1.5rem` معتمدةً على `padding-right` من
       `.custom-control`، وقواعد تخطيط BS5 تُفسد الحساب فينزل النصّ تحت
       المفتاح ويتراصّ حرفاً حرفاً. التعريف هنا قائم على flex لا على إزاحات
       اتجاهية، فيعمل في RTL وLTR بلا حساب. */
    .custom-control {
        position: relative; display: inline-flex; align-items: center;
        min-height: 1.5rem; padding: 0; margin: 0;
    }
    .custom-control-input {
        position: absolute; width: 1px; height: 1px;
        opacity: 0; margin: 0; z-index: -1;
    }
    .custom-control-label {
        position: relative; display: inline-flex; align-items: center;
        gap: .55rem; margin: 0; cursor: pointer; line-height: 1.45;
        font-weight: 500; color: var(--bx-ink); user-select: none;
    }
    .custom-control-label:empty { gap: 0; }
    /* `position: static` ضرورية ولا يجوز حذفها: ثيم Vuexy يضع هذا العنصر
       بـ `position:absolute; top:.225rem; right:-1.5rem` معتمداً على
       `padding-right` من `.custom-control`. إن لم تُعَد إلى مجرى التخطيط بقي
       خارجه، فيقف صندوق المفتاح بعيداً والنصّ وحده في السطر — وهو ما كان
       يظهر كتسمية تنزل تحت المفتاح وتتراصّ حرفاً حرفاً. */
    .custom-control-label::before {
        content: ''; flex: 0 0 auto; display: block;
        position: static; inset: auto; margin: 0;
        background: #fff; border: 1px solid var(--bx-line-strong);
        transition: background-color .18s ease, border-color .18s ease;
    }

    .custom-checkbox .custom-control-label::before {
        width: 1.15rem; height: 1.15rem; border-radius: .3rem;
    }
    .custom-checkbox .custom-control-input:checked ~ .custom-control-label::before {
        background-color: var(--bx-primary); border-color: var(--bx-primary);
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 8 8'%3e%3cpath fill='none' stroke='%23fff' stroke-width='1.6' d='M6.6 1.6 3 5.2 1.4 3.6'/%3e%3c/svg%3e");
        background-repeat: no-repeat; background-position: center; background-size: .72rem;
    }
    .custom-checkbox .custom-control-label::after { content: none; }

    .custom-switch .custom-control-label::before {
        width: 2.4rem; height: 1.3rem; border-radius: 1rem;
        background: #e4e6ef; border-color: #e4e6ef;
    }
    /* المقبض: `inset: auto` أولاً لمسح `left/right/top` التي يضعها Vuexy،
       ثم تحديد الموضع بخصائص منطقية تعمل في الاتجاهين. */
    .custom-switch .custom-control-label::after {
        content: ''; position: absolute; inset: auto;
        top: 50%; inset-inline-start: .16rem;
        width: .98rem; height: .98rem; margin: 0;
        border-radius: 50%; background: #fff; border: 0;
        box-shadow: 0 1px 3px rgba(34, 41, 47, .28);
        transform: translateY(-50%); transition: transform .2s ease;
    }
    .custom-switch .custom-control-input:checked ~ .custom-control-label::before {
        background: var(--bx-primary); border-color: var(--bx-primary);
    }
    /* الإزاحة بـ transform لا بـ inset: الخصائص الاتجاهية لا تتحرّك بسلاسة */
    .custom-switch .custom-control-input:checked ~ .custom-control-label::after {
        transform: translateY(-50%) translateX(1.1rem);
    }
    [dir="rtl"] .custom-switch .custom-control-input:checked ~ .custom-control-label::after {
        transform: translateY(-50%) translateX(-1.1rem);
    }

    .custom-control-input:focus-visible ~ .custom-control-label::before {
        box-shadow: 0 0 0 3px var(--bx-primary-soft);
    }
    .custom-control-input:disabled ~ .custom-control-label { opacity: .5; cursor: not-allowed; }

    /* ── صورة المستخدم في الترويسة ──
       `layouts/header.blade.php` يستعمل هذا الصنف وتعريفه موجود داخل قالب
       «الإعدادات» وحده، فتظهر الترويسة هنا بحرف عارٍ بلا الدائرة. */
    .profile-picture-header {
        width: 40px; height: 40px;
        background-color: var(--bx-primary); color: #fff;
        font-size: 18px; font-weight: bold;
        display: flex; align-items: center; justify-content: center;
        border-radius: 50%;
    }

    /* ═══════════════ ٣) مكوّنات الموديول ═══════════════ */

    /* ── ترويسة الصفحة ── */
    .content-header-title {
        font-size: var(--bx-fs-xl); font-weight: 700; color: var(--bx-ink-strong);
        letter-spacing: -.2px;
    }
    .content-header .breadcrumb {
        background: transparent; padding: .35rem 0 0; margin: 0; font-size: var(--bx-fs-sm);
    }
    .content-header .breadcrumb-item, .content-header .breadcrumb-item a { color: var(--bx-ink-muted); }
    .content-header .breadcrumb-item a:hover { color: var(--bx-primary); }
    .content-header .breadcrumb-item.active { color: var(--bx-ink-strong); font-weight: 600; }

    .bx-lede {
        color: var(--bx-ink-muted); max-width: 92ch;
        line-height: 1.85; font-size: var(--bx-fs-md); margin-bottom: 1.25rem;
    }


    /*
        بطاقات الأرقام وصفوف البيان والأكواد **يعرّفها الثيم المشترك**
        (`stock::layouts.partials.stock-theme`) بعد أن وُحِّدت لغتا التصميم.

        وكانت معرَّفةً هنا كذلك — فيفوز تعريفُ هذه الوحدة لأنه يأتي بعده في
        الصفحة، فتبقى الفروع على مظهرها القديم رغم التوحيد. حُذفت لتصير
        الشاشات تابعةً للمشترك فعلاً لا اسماً.
    */

    /*
        قواعد `.bx-stat` حُذفت: المكوّن يُصيّر `stk-metric` من الثيم المشترك
        بعد توحيد لغتَي التصميم، فلم يبق قالبٌ واحد يستعمل الصنف. وقواعدُ CSS
        لا يُطابقها شيء تبقى تُحمَّل مع كل صفحة ويُظنّ أنها حيّة.

        وما كان يميّزها — سطرُ الملاحظة والنبرات — رُفع إلى `stock-theme`
        (`.stk-metric__note` و`.stk-metric--*`) فربحته بقيّةُ الوحدات.
    */


    /* ── الحالة الفارغة ── */
    .bx-empty { text-align: center; padding: 3rem 1.5rem; }
    .bx-empty .bx-empty-icon {
        width: 68px; height: 68px; border-radius: 50%;
        background: var(--bx-surface-sunken); color: #c4c2d4;
        display: inline-flex; align-items: center; justify-content: center;
        margin-bottom: 1rem;
    }
    .bx-empty .bx-empty-icon i { font-size: var(--bx-fs-2xl); }
    .bx-empty h5 { font-weight: 700; color: var(--bx-ink-strong); margin-bottom: .4rem; }
    .bx-empty p { color: var(--bx-ink-muted); font-size: var(--bx-fs-md); margin-bottom: 1rem; max-width: 46ch;
                  margin-inline: auto; line-height: 1.75; }


    /* ── تبويبات جانبية (شاشة الإعدادات) ── */
    .content-body .nav-pills .nav-link {
        border-radius: var(--bx-radius-sm); color: var(--bx-ink);
        padding: .62rem .85rem; margin-bottom: .3rem;
        font-size: var(--bx-fs-md); font-weight: 600;
        border: 1px solid transparent; transition: all .15s ease;
    }
    .content-body .nav-pills .nav-link:hover { background: var(--bx-surface-sunken); }
    .content-body .nav-pills .nav-link.active {
        background: var(--bx-primary); color: #fff;
        box-shadow: 0 3px 10px rgba(115, 103, 240, .3);
    }
    .content-body .nav-pills .nav-link.active .badge {
        background: rgba(255, 255, 255, .25) !important; color: #fff !important;
    }

    /* ═══════════════ الشجرة ═══════════════ */

    /* ── مفتاح طي/فتح فروع الشجرة ── */
    .bx-toggle {
        cursor: pointer; background: none; border: 0; padding: 0;
        width: 22px; color: var(--bx-ink-muted); border-radius: 4px;
    }
    .bx-toggle:hover { color: var(--bx-primary); background: var(--bx-primary-soft); }
    .bx-toggle .ic { transition: transform .18s ease; display: inline-block; }
    .bx-toggle[aria-expanded="true"] .ic { transform: rotate(-90deg); }
    [dir="ltr"] .bx-toggle[aria-expanded="true"] .ic { transform: rotate(90deg); }

    /* ── خطّ الاتصال: يجعل عمق العنصر مقروءاً بدل مسافة بيضاء صامتة ── */
    .bx-indent { display: inline-block; position: relative; height: 1em; }
    .bx-indent::before {
        content: ''; position: absolute; inset-inline-end: 10px; top: -14px; bottom: 50%;
        border-inline-end: 1px solid var(--bx-line-strong);
        border-bottom: 1px solid var(--bx-line-strong);
        width: 12px; border-end-end-radius: 6px;
    }

    /* ── إبراز نتيجة البحث في الشجرة ── */
    #bx-tree tbody tr.is-hit { background: #fffaf0 !important; box-shadow: inset 2px 0 0 var(--bx-warning); }

    /* ── عمود التحديد المثبَّت ──
       الجدول يتجاوز عرض الحاوية حين تعمق الشجرة، وفي RTL يبدأ شريط التمرير
       الأفقي عند الطرف المقابل فيُقصّ العمود الأول تماماً — فيبدو التحديد
       معطّلاً وهو موجود. التثبيت يُبقيه ظاهراً مهما تمّ التمرير. */
    #bx-tree .bx-pin-col {
        position: sticky; inset-inline-start: 0; z-index: 2;
        background: inherit; width: 46px; min-width: 46px;
    }
    #bx-tree thead .bx-pin-col { background: var(--bx-surface-sunken); z-index: 3; }
    #bx-tree tbody .bx-pin-col { background: #fff; }
    #bx-tree tbody tr:hover .bx-pin-col { background: #fafaff; }
    #bx-tree tbody tr.is-hit .bx-pin-col { background: #fffaf0; }
    /* حدّ خفيف يفصل العمود المثبَّت عمّا يمرّ تحته */
    #bx-tree .bx-pin-col::after {
        content: ''; position: absolute; top: 0; bottom: 0;
        inset-inline-end: 0; width: 1px; background: var(--bx-line);
    }

    /* هدف نقر أوسع: مربّع 1.15rem وحده صعب الإصابة في صفّ من جدول */
    .bx-check-lg .custom-control-label { padding: .45rem; margin: -.45rem; }

    /* ── خليّة عقدة الشجرة ── */
    .bx-node { display: flex; align-items: center; gap: .45rem; min-width: 0; }
    .bx-toggle-spacer { display: inline-block; width: 22px; flex: 0 0 auto; }
    .bx-node-icon {
        width: 30px; height: 30px; border-radius: 9px; flex: 0 0 auto;
        display: inline-flex; align-items: center; justify-content: center;
        background: var(--bx-primary-soft); color: var(--bx-primary);
    }
    .bx-node-icon.success { background: rgba(40, 199, 111, .12); color: var(--bx-success); }
    .bx-node-icon.info    { background: rgba(0, 207, 232, .12);  color: var(--bx-info); }
    .bx-node-icon.warning { background: rgba(255, 159, 67, .12); color: var(--bx-warning); }
    .bx-node-icon i { font-size: var(--bx-fs-lg); }
    /* الاسم يُقصّ بنقاط بدل أن يوسّع الجدول ويُخرج عمود التحديد عن الشاشة */
    .bx-node-name {
        font-weight: 600; color: var(--bx-ink-strong); min-width: 0;
        overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
        text-decoration: none;
    }
    .bx-node-name:hover { color: var(--bx-primary); text-decoration: none; }
    .bx-node-count {
        flex: 0 0 auto; font-size: var(--bx-fs-xs); font-weight: 700;
        min-width: 20px; height: 20px; padding: 0 .35rem; border-radius: 10px;
        display: inline-flex; align-items: center; justify-content: center;
        background: var(--bx-surface-sunken); color: var(--bx-ink-muted);
        border: 1px solid var(--bx-line);
    }
    #bx-tree tbody tr.is-picked { background: var(--bx-primary-soft) !important; }
    #bx-tree tbody tr.is-picked .bx-pin-col { background: #efedfe; }

    /* ── ذيلُ بطاقة التصفية: مفتاحُ أنواع العناصر في جهة والأزرارُ في الأخرى ── */
    .sl-filters__foot.bx-filters-foot { justify-content: space-between; align-items: center; flex-wrap: wrap; }
    .bx-type-key {
        display: flex; flex-wrap: wrap; align-items: center; gap: .35rem 1rem;
        font-size: var(--bx-fs-sm); color: var(--bx-ink-muted);
    }
    /* عدّادُ النتائج شارةٌ، فلا تُرسم فارغةً قبل أوّل تصفية */
    .bx-type-key .sl-count:empty { display: none; }
    .bx-tone--primary { color: var(--bx-primary); }
    .bx-tone--success { color: var(--bx-success); }
    .bx-tone--info { color: var(--bx-info); }
    .bx-tone--warning { color: var(--bx-warning); }

    /* ── شريط النقل الجماعي ── */
    .bx-bulk {
        display: flex; flex-wrap: wrap; align-items: flex-end; gap: .85rem;
        padding: 1rem 1.15rem; background: var(--bx-surface-sunken);
        border: 1px dashed var(--bx-line-strong); border-radius: var(--bx-radius-sm);
        margin-bottom: 1.15rem; transition: border-color .15s, background .15s;
    }
    .bx-bulk.is-armed {
        border-style: solid; border-color: var(--bx-primary);
        background: var(--bx-primary-soft);
    }

    /* ── بطاقات اختيار الأب/النوع ── */
    .bx-pick {
        border: 1px solid var(--bx-line-strong); border-radius: var(--bx-radius-sm);
        padding: .95rem; cursor: pointer; margin: 0; background: #fff;
        transition: border-color .14s, background .14s, box-shadow .14s;
    }
    .bx-pick:hover { border-color: var(--bx-primary); box-shadow: var(--bx-shadow); }
    .bx-pick input { position: absolute; opacity: 0; width: 0; height: 0; }
    .bx-pick:has(input:checked) {
        border-color: var(--bx-primary); background: var(--bx-primary-soft);
        box-shadow: 0 0 0 1px var(--bx-primary);
    }
    .bx-field-invalid .bx-pick { border-color: var(--bx-danger); }

    /* أجزاءُ بطاقة الاختيار — كانت `avatar bg-rgba-*` و`text-bold-600`
       مكتوبةً في القالب: ترميزُ ثيم Vuexy الخام لا يقرأ متغيّراتِ الثيم،
       فلا يتبع اللونَ المضبوط في الإعدادات ولا الوضعَ الداكن. */
    .bx-pick__head { display: flex; align-items: center; gap: .45rem; margin-bottom: .25rem; }

    .bx-pick__icon {
        display: inline-flex; align-items: center; justify-content: center;
        width: 28px; height: 28px; flex: 0 0 28px;
        border-radius: var(--bx-radius-sm);
    }
    .bx-pick__icon--primary { background: color-mix(in srgb, var(--bx-primary) 12%, transparent); color: var(--bx-primary); }
    .bx-pick__icon--success { background: color-mix(in srgb, var(--bx-success) 12%, transparent); color: var(--bx-success); }
    .bx-pick__icon--info    { background: color-mix(in srgb, var(--bx-info) 12%, transparent);    color: var(--bx-info); }
    .bx-pick__icon--warning { background: color-mix(in srgb, var(--bx-warning) 12%, transparent); color: var(--bx-warning); }

    .bx-pick__title { font-weight: 600; color: var(--bx-ink-strong); }
    .bx-pick__hint { display: block; color: var(--bx-ink-muted); font-size: var(--bx-fs-sm); line-height: 1.6; }

    /* ── لوحة سياق الأب في نموذج الإضافة ── */
    .bx-parent-ctx {
        background: var(--bx-primary-soft);
        border: 1px solid rgba(115, 103, 240, .25);
        border-radius: var(--bx-radius-sm); padding: 1rem 1.15rem;
    }

    /* ── رقاقةُ الكود المُولَّد في نموذج الإضافة ──
         الكود يُولَّد ولا يُكتب، فهو خبرٌ لا حقل. كان يُعرض `<h3>` عارياً
         بين حقول الإدخال فيُقرأ كأنه عنوانُ قسمٍ لا قيمةَ حقل. */
    .bx-code-chip {
        display: inline-flex; align-items: center; gap: .5rem;
        min-height: calc(var(--bx-fs) * 2.6);
        padding: .45rem .9rem;
        background: var(--bx-surface-sunken);
        border: 1px dashed var(--bx-line-strong);
        border-radius: var(--bx-radius-sm);
        font-weight: 700; font-size: calc(var(--bx-fs) * 1.15);
        color: var(--bx-primary);
        letter-spacing: .04em; font-variant-numeric: tabular-nums;
    }
    .bx-code-chip i { font-size: var(--bx-fs-sm); color: var(--bx-ink-muted); }

    /* ── نموذجٌ مضغوط: الشاشة كانت تُقرأ بالتمرير لا بالنظر ──
         أربعُ بطاقاتٍ بهوامشها وعناوينها لأربعة عشر حقلاً — أكثرُ الصفحة
         فراغٌ بين الحقول. هنا هوامشُ أضيق ومسافاتٌ أقصر داخل هذه الشاشة
         وحدها، فلا تمسّ بقيّة الوحدة. */
    /* `.sl-scope` في كل مُنتقٍ: قواعدُ لغة `sl-` مقصورةٌ عليه، فبدونه
       يغلب هامشُها الواسع هامشَ هذه الشاشة المضغوط. */
    .sl-scope .bx-compact .card { margin-bottom: .85rem; }
    .sl-scope .bx-compact .card > .card-header { padding: .6rem 1rem; }
    .sl-scope .bx-compact .card .card-body { padding: .9rem 1rem; }
    .sl-scope .bx-compact .form-group { margin-bottom: .7rem; }
    .sl-scope .bx-compact .sl-help { margin-top: .18rem; line-height: 1.45; }
    .sl-scope .bx-compact .bx-parent-ctx { padding: .6rem .8rem; }
    .sl-scope .bx-compact .bx-sticky { padding: .6rem 1rem; margin-bottom: .6rem; }
    .sl-scope .bx-compact .stk-choice { padding: .5rem .7rem; }

    /*
        مفتاحُ التبديل داخل بطاقة الاختيار — تثبيتُ الصفّ ولونُ المسار.

        مسارُ المفتاح في نظام التصميم لونُه `color-mix(… , transparent)`،
        ومتصفّحٌ لا يدعم `color-mix` يسقط الإعلان كلَّه فيبقى المسار شفافاً:
        لا يُرى منه شيء إلّا إبهامُه البيضاء، فتبدو بقعةً بيضاء فوق النصّ
        ولا يعرف المستخدم أن ثمّة مفتاحاً أصلاً. اللونُ الصريح هنا يسبقه
        فيسود عند السقوط، ويُستبدَل به عند الدعم.

        أمّا انهيارُ الصفّ نفسه فسببُه `display: block` أعلاه، وقد استُثني منه
        `.stk-switch` هناك — لا هنا، كي يبقى الإصلاح عند مصدر العلّة.
    */
    .stk-choice .stk-switch__track {
        background: #d8dae0;
        background: color-mix(in srgb, var(--app-muted) 28%, transparent);
    }
    .stk-choice .stk-switch > input:checked + .stk-switch__track { background: var(--app-primary); }
    .stk-choice .stk-switch__label { flex: 1 1 auto; min-width: 0; }

    /* بطاقاتُ النوع: صفٌّ واحد، والشرحُ سطرٌ واحد لا فقرة */
    .bx-compact .bx-pick { padding: .55rem .7rem; }
    .bx-compact .bx-pick__head { margin-bottom: .1rem; }
    .bx-compact .bx-pick__icon { width: 1.7rem; height: 1.7rem; }
    .bx-compact .bx-pick__hint {
        font-size: calc(var(--bx-fs) * .76); line-height: 1.35;
        display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;
        overflow: hidden;
    }

    /* ── شريط الحفظ الملتصق ── */
    /* الهيئةُ هيئةُ ذيل النموذج في المبيعات (`sl-form-foot`): أرضيّةٌ خفيفة
       وحدٌّ واستدارةُ البطاقات. وما يزيد عليه الالتصاقُ بأسفل الشاشة —
       النموذجُ أطولُ من الشاشة، وزرُّ الحفظ لا يُطلب بالتمرير. */
    .bx-sticky {
        position: sticky; bottom: 0; z-index: 20;
        background: var(--sl-tint, var(--bx-surface));
        border: 1px solid var(--sl-line, var(--bx-line)); border-radius: var(--sl-r-lg, var(--bx-radius));
        box-shadow: 0 -4px 20px rgba(16, 24, 40, .08); padding: 1rem 1.5rem;
        display: flex; justify-content: space-between; align-items: center;
        flex-wrap: wrap; gap: .7rem; margin-bottom: 1.5rem;
    }

    /* ── صندوق «طبّق على الفروع التابعة» ── */
    .bx-cascade {
        background: rgba(255, 159, 67, .07);
        border: 1px solid rgba(255, 159, 67, .35);
        border-radius: var(--bx-radius-sm); padding: 1rem 1.15rem;
    }
    .bx-cascade:has(input:checked) {
        background: rgba(255, 159, 67, .16); border-color: var(--bx-warning);
    }


    /* ── خليّةُ رقمٍ بتفصيله (شجرة الفهرس): الإجماليُّ كبيراً، وتحته ما
         يفصّله **مكتوباً اسمُه** — «مباشر 5 · تحته 504» لا أيقونتان ورقمان ── */
    .bx-figure { min-width: 120px; }
    .bx-figure:has(.bxd-pair) { min-width: 215px; }
    .bx-figure__total {
        display: block; font-weight: 700; color: var(--bx-ink-strong);
        font-size: var(--bx-fs-lg); line-height: 1.2; font-variant-numeric: tabular-nums;
    }
    .bx-figure__split {
        display: flex; flex-wrap: wrap; gap: .1rem .7rem; margin: .15rem 0 .3rem;
        font-size: var(--bx-fs-xs); color: var(--bx-ink-muted); font-variant-numeric: tabular-nums;
    }
    .bx-figure__split b { color: var(--bx-ink); font-weight: 700; }
    .bx-figure .bxd-pair { flex-wrap: nowrap; white-space: nowrap; }

    /* ── بطاقة أداة قابلة للنقر (اللوحة) ── */
    .bx-tool {
        display: flex; align-items: flex-start; gap: .85rem;
        padding: 1rem; border: 1px solid var(--bx-line);
        border-radius: var(--bx-radius-sm); background: #fff;
        height: 100%; text-decoration: none; color: var(--bx-ink);
        transition: border-color .16s, box-shadow .16s, transform .16s;
    }
    .bx-tool:hover {
        border-color: var(--bx-primary); box-shadow: var(--bx-shadow-lift);
        transform: translateY(-2px); text-decoration: none; color: var(--bx-ink);
    }
    .bx-tool .bx-tool-icon {
        width: 38px; height: 38px; border-radius: 10px; flex: 0 0 auto;
        display: flex; align-items: center; justify-content: center;
        background: var(--bx-primary-soft); color: var(--bx-primary);
    }
    .bx-tool.success .bx-tool-icon { background: rgba(40, 199, 111, .12); color: var(--bx-success); }
    .bx-tool.info .bx-tool-icon    { background: rgba(0, 207, 232, .12);  color: var(--bx-info); }
    .bx-tool.warning .bx-tool-icon { background: rgba(255, 159, 67, .12); color: var(--bx-warning); }
    .bx-tool.danger .bx-tool-icon  { background: rgba(234, 84, 85, .12);  color: var(--bx-danger); }
    .bx-tool .bx-tool-title { font-weight: 700; font-size: var(--bx-fs-md); color: var(--bx-ink-strong); display: block; }
    .bx-tool .bx-tool-hint  { font-size: var(--bx-fs-sm); color: var(--bx-ink-muted); line-height: 1.6; }

    /* ── بطاقة إعداد واحد (شاشة الإعدادات) ── */
    .bx-setting {
        position: relative; height: 100%;
        border: 1px solid var(--bx-line); border-radius: var(--bx-radius-sm);
        background: #fff; padding: 1rem 1.1rem;
        transition: border-color .15s, box-shadow .15s, background .15s;
    }
    .bx-setting:hover { border-color: var(--bx-line-strong); box-shadow: var(--bx-shadow); }
    /* الإعداد المضبوط لهذا الفرع وحده يُعلَّم بشريط جانبي: العين تميّز
       الاستثناءات فوراً بين خمسين إعداداً بلا قراءة كل شارة. */
    .bx-setting.is-own {
        border-color: rgba(115, 103, 240, .35);
        box-shadow: inset 3px 0 0 var(--bx-primary);
    }
    [dir="ltr"] .bx-setting.is-own { box-shadow: inset -3px 0 0 var(--bx-primary); }
    .bx-setting.is-invalid { border-color: var(--bx-danger); box-shadow: inset 3px 0 0 var(--bx-danger); }
    .bx-setting > label:first-of-type,
    .bx-setting .text-bold-600 { font-size: var(--bx-fs-md); }
    .bx-setting .stk-num { font-size: var(--bx-fs-xs); opacity: .75; }

    /* ── بطاقة هوية العنصر (صفحة الفرع) ── */
    .bx-identity {
        background:
            radial-gradient(120% 140% at 100% 0%, rgba(115, 103, 240, .07) 0%, transparent 55%),
            var(--bx-surface);
    }
    .bx-identity-avatar {
        width: 62px; height: 62px; border-radius: 16px; flex: 0 0 auto;
        display: flex; align-items: center; justify-content: center;
        background: var(--bx-primary-soft); color: var(--bx-primary);
    }
    .bx-identity-avatar.success { background: rgba(40, 199, 111, .12); color: var(--bx-success); }
    .bx-identity-avatar.info    { background: rgba(0, 207, 232, .12);  color: var(--bx-info); }
    .bx-identity-avatar.warning { background: rgba(255, 159, 67, .12); color: var(--bx-warning); }
    .bx-identity-avatar i { font-size: var(--bx-fs-2xl); }
    .bx-identity-name {
        font-size: var(--bx-fs-xl); font-weight: 700; color: var(--bx-ink-strong);
        margin: 0; letter-spacing: -.2px;
    }

    /* ── مسار العنصر في الشجرة ── */
    .bx-path {
        display: flex; flex-wrap: wrap; align-items: center; gap: .35rem;
        font-size: var(--bx-fs-sm); padding-top: .35rem;
    }
    .bx-path a {
        color: var(--bx-ink-muted); padding: .12rem .5rem;
        border-radius: 6px; background: var(--bx-surface-sunken);
        border: 1px solid var(--bx-line); text-decoration: none;
        transition: color .14s, border-color .14s;
    }
    .bx-path a:hover { color: var(--bx-primary); border-color: var(--bx-primary); }
    .bx-path-sep { color: #c9c7d8; }
    .bx-path-current {
        font-weight: 700; color: var(--bx-primary);
        padding: .12rem .5rem; border-radius: 6px; background: var(--bx-primary-soft);
    }

    /* ═══════════════ شاشات ضم المجموعات ونقل العملاء ═══════════════

       هذه الأنماط كانت مكرَّرة حرفياً — ١١٣ سطراً متطابقة بايتاً ببايت — في
       `merge_groups` و`merge_logs` و`bulk_merge`، وطبعةً رابعة مختلفة في
       `merge_select`. أي تعديل كان يلزم تكراره أربع مرّات، وقد انحرفت
       الطبعات فعلاً. جُمعت هنا مرّة واحدة وربُطت برموز الموديول بدل الألوان
       المكتوبة يدوياً (#5e5873، #b9b9c3…) حتى تتغيّر مع الثيم لا ضدّه. */

    .merge-container, .bulk-merge-container, .merge-select-container, .logs-container { margin-top: .5rem; }

    .merge-card, .group-card, .log-card, .merge-column, .ms-col {
        background: var(--bx-surface);
        border: 1px solid var(--bx-line);
        border-radius: var(--bx-radius);
        box-shadow: var(--bx-shadow);
        padding: 1.2rem 1.3rem;
        margin-bottom: 1rem;
    }
    .merge-title, .group-title, .ms-col-head {
        font-size: var(--bx-fs-lg); font-weight: 700; color: var(--bx-ink-strong);
        margin: 0 0 .85rem; display: flex; align-items: center; gap: .5rem;
    }
    .merge-row { display: flex; flex-wrap: wrap; gap: 1rem; }
    .merge-column, .ms-col { flex: 1 1 320px; min-width: 0; height: 100%; }

    /* ── سجلّ الضم ── */
    .log-card { border-inline-start: 4px solid var(--bx-primary); transition: box-shadow .16s, transform .16s; }
    .log-card:hover { box-shadow: var(--bx-shadow-lift); transform: translateY(-2px); }
    .log-card.reversed { border-inline-start-color: var(--bx-success); background: #f7fdf9; }
    .log-header {
        display: grid; grid-template-columns: 1fr auto; gap: 1rem;
        align-items: center; margin-bottom: .9rem;
    }
    .log-title { font-size: var(--bx-fs-lg); font-weight: 700; color: var(--bx-ink-strong); }
    .log-arrow { color: var(--bx-primary); margin: 0 .5rem; }
    .log-info {
        display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: .9rem; margin-bottom: .9rem;
    }
    .log-actions { padding-top: .9rem; border-top: 1px solid var(--bx-line); display: flex; gap: .6rem; }

    /* ── عناصر بيان ── */
    .info-item, .stat-item { display: flex; flex-direction: column; }
    .info-label, .stat-label {
        font-size: var(--bx-fs-xs); color: var(--bx-ink-muted); font-weight: 700;
        margin-bottom: .2rem; letter-spacing: .02em;
    }
    .info-value, .stat-value { font-size: var(--bx-fs-md); color: var(--bx-ink-strong); font-weight: 600; }
    .stat-value { font-size: var(--bx-fs-xl); line-height: 1.15; font-variant-numeric: tabular-nums; }

    .info-box, .group-info-box, .info-section {
        background: var(--bx-surface-sunken); border: 1px solid var(--bx-line);
        border-radius: var(--bx-radius-sm); padding: .9rem 1rem;
    }
    .info-circle {
        width: 34px; height: 34px; border-radius: 9px; flex: 0 0 auto;
        background: var(--bx-primary-soft); color: var(--bx-primary);
        display: grid; place-items: center;
    }
    .group-info { font-size: var(--bx-fs-sm); color: var(--bx-ink); line-height: 1.7; }

    /* ── شارات ── */
    .status-badge, .group-tag {
        display: inline-block; padding: .18rem .6rem; border-radius: 20px;
        font-size: var(--bx-fs-xs); font-weight: 700; white-space: nowrap;
    }
    .status-active { background: var(--bx-primary-soft); color: #5e50ee; }
    .status-reversed { background: #e6f8ee; color: #1a8a4d; }
    .group-tag {
        background: var(--bx-surface); color: var(--bx-ink-strong);
        border: 1px solid var(--bx-line-strong);
        display: inline-flex; align-items: center; gap: .4rem;
    }
    .group-tag i {
        cursor: pointer; color: var(--bx-ink-muted);
        font-size: var(--bx-fs-sm); transition: color .14s;
    }
    .group-tag i:hover { color: var(--bx-danger); }
    .selected-count { font-size: var(--bx-fs-sm); font-weight: 700; color: var(--bx-primary); }

    /* ── تنبيه ── */
    .warning-box {
        background: rgba(255, 159, 67, .08); border: 1px solid rgba(255, 159, 67, .4);
        color: #8a5308; border-radius: var(--bx-radius-sm);
        padding: .95rem 1.1rem; font-size: var(--bx-fs-md); line-height: 1.75;
    }

    /* ── اختيار متسلسل للشجرة ── */
    .cascade-col { flex: 1 1 200px; min-width: 180px; margin-bottom: .5rem; }
    .cascade-select { border-radius: var(--bx-radius-sm); width: 100%; }

    /* ── تبويبات بطاقة الضم/النقل: مظهر مفتاح مقسّم لا روابط عارية ── */
    .content-body .nav-tabs.card-header-tabs {
        margin: 0; border: 0; gap: .3rem;
        background: var(--bx-surface-sunken);
        border-radius: var(--bx-radius-sm); padding: .3rem;
    }
    .content-body .nav-tabs.card-header-tabs .nav-link {
        border: 0; border-radius: 6px; color: var(--bx-ink-muted);
        font-weight: 600; font-size: var(--bx-fs-md); padding: .55rem 1.1rem;
        transition: all .15s ease;
    }
    .content-body .nav-tabs.card-header-tabs .nav-link:hover { color: var(--bx-ink-strong); }
    .content-body .nav-tabs.card-header-tabs .nav-link.active {
        background: var(--bx-surface); color: var(--bx-primary);
        box-shadow: 0 1px 4px rgba(34, 41, 47, .1);
    }

    /* ── عمودا المصدر والهدف ──
       العمودان كانا صندوقين رماديين متطابقين، فلا شيء يقول أيهما المصدر
       وأيهما الهدف إلا قراءة العنوان. الآن لكلٍّ ترويسة ملوّنة وحدّ علوي
       يميّزه من نظرة واحدة: المصدر برتقالي (يخرج منه)، والهدف أخضر. */
    .ms-col { position: relative; padding-top: 1.35rem; overflow: hidden; }
    .ms-col::before {
        content: ''; position: absolute; top: 0; inset-inline: 0;
        height: 3px; background: var(--bx-line-strong);
    }
    .ms-col.is-source::before { background: var(--bx-warning); }
    .ms-col.is-target::before { background: var(--bx-success); }
    .ms-col.is-danger::before { background: var(--bx-danger); }
    .ms-col.is-info::before   { background: var(--bx-info); }

    .ms-col-head { padding-bottom: .85rem; border-bottom: 1px solid var(--bx-line); margin-bottom: .95rem; }
    /* أيقونة الترويسة بنفس شكل بقيّة أيقونات الموديول */
    .ms-col-head .avatar {
        width: 34px !important; height: 34px !important;
        border-radius: 9px !important; padding: 0 !important;
        display: inline-flex; align-items: center; justify-content: center;
    }

    .ms-arrow {
        display: flex; align-items: center; justify-content: center;
        color: var(--bx-primary); font-size: var(--bx-fs-lg);
    }
    .ms-arrow i {
        width: 42px; height: 42px; border-radius: 50%;
        background: var(--bx-primary-soft);
        display: inline-flex; align-items: center; justify-content: center;
    }
    @media (max-width: 991px) { .ms-arrow { transform: rotate(-90deg); padding: .75rem 0; } }

    /* ── المسار المختار في شاشة النقل ──
       بعد أربعة مستويات لا يعود المستخدم يذكر ماذا اختار في كل مستوى، فيبقى
       المسار كاملاً أمامه. */
    .bx-picked {
        display: flex; align-items: center; gap: .45rem;
        background: var(--bx-primary-soft);
        border: 1px solid rgba(115, 103, 240, .28);
        border-radius: var(--bx-radius-sm);
        padding: .6rem .85rem; margin-top: .5rem;
        font-size: var(--bx-fs-sm); font-weight: 600;
        color: var(--bx-primary); line-height: 1.7;
    }
    .bx-picked i { flex: 0 0 auto; }

    /* ── قوائم العملاء ── */
    .clients-list-container {
        border: 1px solid var(--bx-line); border-radius: var(--bx-radius-sm);
        margin-top: .75rem; overflow: hidden;
    }
    .select-all-wrapper {
        display: flex; justify-content: space-between; align-items: center;
        padding: .65rem .9rem; border-bottom: 1px solid var(--bx-line);
        background: var(--bx-surface-sunken);
    }
    #clients-list { max-height: 320px; overflow-y: auto; }
    .client-item {
        display: flex; align-items: center; gap: .6rem;
        padding: .55rem .85rem; border-bottom: 1px solid #f4f3f9;
        cursor: pointer; transition: background .12s;
    }
    .client-item:last-child { border-bottom: 0; }
    .client-item:hover { background: rgba(115, 103, 240, .04); }
    .client-item.is-picked { background: var(--bx-primary-soft); }
    .client-checkbox { flex: 0 0 auto; cursor: pointer; }
    .client-info-wrapper { min-width: 0; }
    .client-name { font-size: var(--bx-fs-md); color: var(--bx-ink-strong); font-weight: 600; }
    .client-code { font-size: var(--bx-fs-xs); color: var(--bx-ink-muted); }
    .search-box { margin-top: .75rem; }

    /* ═══════════ نافذة التأكيد ═══════════
       خارج `.sl-scope` — SweetAlert يضعها في آخر الصفحة — فتُعرَّف بلا سياق. */
    .bx-confirm { text-align: start; font-size: var(--bx-fs-md); line-height: 1.8; color: var(--bx-ink); }
    .bx-confirm__q { font-size: var(--bx-fs-lg); font-weight: 700; color: var(--bx-ink-strong); margin: 0 0 .85rem; text-align: center; }
    .bx-confirm__rows {
        margin: 0 0 .85rem; border: 1px solid var(--bx-line);
        border-radius: var(--bx-radius-sm); overflow: hidden;
    }
    .bx-confirm__rows > div {
        display: grid; grid-template-columns: 9.5rem minmax(0, 1fr); gap: .75rem;
        padding: .6rem .85rem; border-bottom: 1px solid var(--bx-line);
    }
    .bx-confirm__rows > div:last-child { border-bottom: 0; }
    .bx-confirm__rows dt {
        margin: 0; font-size: var(--bx-fs-sm); font-weight: 600; color: var(--bx-ink-muted);
        background: none;
    }
    .bx-confirm__rows dd { margin: 0; font-weight: 700; color: var(--bx-ink-strong); word-break: break-word; }
    .bx-confirm__rows ul { margin: 0; padding-inline-start: 1.1rem; max-height: 9.5rem; overflow-y: auto; }
    .bx-confirm__note {
        margin: 0; padding: .6rem .85rem; border-radius: var(--bx-radius-sm);
        background: var(--bx-surface-sunken); color: var(--bx-ink-muted); font-size: var(--bx-fs-sm);
    }

    /* ═══════════ نماذج النقل والضم: بطاقةُ مصدرٍ وبطاقةُ هدف ═══════════

       الجانبان بطاقتان من بطاقات النظام نفسِها، متجاورتان بارتفاعٍ واحد.
       وما يميّز المصدرَ من الهدف شريطٌ علويّ ولونُ أيقونة العنوان — لا
       صندوقٌ ثانٍ داخل بطاقة. */
    .bx-sides > [class*="col-"] { display: flex; }
    .bx-sides > [class*="col-"] > .card { flex: 1 1 auto; min-width: 0; }
    /* البطاقةُ تقصّ ما يخرج عنها، وقائمةُ الاختيار المنسدلة تخرج — فتُترك ظاهرة */
    .sl-scope .card.bx-side { overflow: visible; border-top: 3px solid var(--bx-line-strong); }
    .sl-scope .card.bx-side--warning { border-top-color: var(--bx-warning); }
    .sl-scope .card.bx-side--success { border-top-color: var(--bx-success); }
    .sl-scope .card.bx-side--danger { border-top-color: var(--bx-danger); }
    .sl-scope .card.bx-side--info { border-top-color: var(--bx-info); }
    .sl-scope .card.bx-side--warning .card-title i { color: var(--bx-warning); }
    .sl-scope .card.bx-side--success .card-title i { color: var(--bx-success); }
    .sl-scope .card.bx-side--danger .card-title i { color: var(--bx-danger); }
    .sl-scope .card.bx-side--info .card-title i { color: var(--bx-info); }

    /* مربّعُ الاختيار ونصُّه في سطرٍ واحد: قاعدةٌ عامّةٌ على `label` في الثيم
       المشترك تجعله كتلةً فينزل النصُّ تحت المربّع */
    .sl-scope .custom-control > .custom-control-label { display: inline-flex; margin-bottom: 0; }

    /* مستوياتُ الاختيار المتسلسل: مسافةٌ واحدة بين الحقل والذي يليه */
    .bx-side .cascade-col { margin-bottom: .85rem !important; }
    .bx-side .bx-picked { margin: 0 0 .85rem; }
    .bx-side__facts { margin-top: .5rem; padding-top: .85rem; border-top: 1px dashed var(--bx-line-strong); }

    /* رقمان متجاوران تحت الاختيار — «عدد العناصر / إجمالي العملاء» */
    .bx-mini { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .75rem; margin-top: .85rem; }
    .bx-mini__item {
        display: flex; flex-direction: column; gap: .15rem; padding: .7rem .9rem;
        border: 1px solid var(--bx-line); border-radius: var(--bx-radius-sm);
        background: var(--bx-surface-sunken);
    }
    .bx-mini__label { font-size: var(--bx-fs-xs); color: var(--bx-ink-muted); font-weight: 600; }
    .bx-mini__value {
        font-size: var(--bx-fs-xl); font-weight: 700; line-height: 1.15;
        color: var(--bx-ink-strong); font-variant-numeric: tabular-nums;
    }

    /* الحالةُ الفارغة داخل بطاقة الجانب أصغرُ منها في صفحةٍ كاملة */
    .bx-side .empty-state { padding: 1.4rem 1rem; }
    .bx-side .selected-groups-wrapper { margin-top: .25rem; }

    /* ── تبويباتُ الصفحة: بهيئة تبويبات المبيعات (خطٌّ سفليّ بلون الهوية) ──
       الروابطُ `nav-link` لأن بوتستراب يبدّل اللوحات بها، فالهيئةُ تُعرَّف
       على صنفه النشِط `active`. */
    .bx-tabs {
        display: flex; flex-wrap: wrap; gap: .25rem; margin: 0 0 1.25rem; padding: 0;
        border-bottom: 2px solid var(--bx-line);
    }
    .bx-tabs .nav-link {
        display: inline-flex; align-items: center; gap: .5rem;
        padding: .7rem 1rem; margin-bottom: -2px;
        border: 0; border-bottom: 3px solid transparent; border-radius: 0; background: none;
        color: var(--bx-ink-muted); font-weight: 600; font-size: var(--bx-fs-md);
    }
    .bx-tabs .nav-link:hover { color: var(--bx-primary); }
    .bx-tabs .nav-link.active { color: var(--bx-primary); border-bottom-color: var(--bx-primary); background: none; }

    /* ── قائمةُ الاختيار المتعدّد ──
       الشاراتُ تلتفّ داخل الحقل ويكبر بها، بدل سطرٍ واحدٍ يفيض عن حدّه. */
    .sl-scope .select2-container--default .select2-selection--multiple {
        min-height: 40px; height: auto; padding: .2rem .4rem;
        border: 1px solid var(--bx-line-strong); border-radius: var(--bx-radius-sm);
        background: var(--bx-surface);
    }
    .sl-scope .select2-container--default.select2-container--focus .select2-selection--multiple {
        border-color: var(--bx-primary); box-shadow: 0 0 0 3px var(--bx-primary-soft);
    }
    .sl-scope .select2-container--default .select2-selection--multiple .select2-selection__rendered {
        display: flex; flex-wrap: wrap; gap: .25rem; padding: 0; margin: 0;
    }
    .sl-scope .select2-container--default .select2-selection--multiple .select2-selection__choice {
        float: none; max-width: 100%; margin: 0; padding: .12rem .6rem;
        border: 0; border-radius: 999px; white-space: normal;
        background: var(--bx-primary-soft) !important; color: var(--bx-primary) !important;
        font-size: var(--bx-fs-sm); font-weight: 600;
    }
    .sl-scope .select2-container--default .select2-selection--multiple .select2-selection__choice__remove {
        color: var(--bx-primary) !important; margin-inline-end: .3rem; float: none; border: 0; position: static;
    }
    .sl-scope .select2-container--default .select2-selection--multiple .select2-search__field { margin: 0; height: 28px; }
    .selected-groups-wrapper {
        border: 1px solid var(--bx-line); border-radius: var(--bx-radius-sm);
        background: var(--bx-surface-sunken); padding: .5rem;
        max-height: 320px; overflow-y: auto;
    }

    /* ── حالة فارغة قديمة في شاشات الضم — يُفضَّل مكوّن «empty» في الجديد ── */
    .empty-state { text-align: center; padding: 2.8rem 1.2rem; color: var(--bx-ink-muted); }
    .empty-state i { font-size: var(--bx-fs-2xl); color: #d3cfe6; margin-bottom: .6rem; display: block; opacity: 1; }
    .empty-state p { font-size: var(--bx-fs-md); margin-bottom: .6rem; }

    /* ── أزرار ── */
    .btn-revert {
        flex: 1; padding: .55rem 1rem; border: 1px solid transparent;
        border-radius: var(--bx-radius-sm); background: var(--bx-primary);
        color: #fff; font-weight: 600; font-size: var(--bx-fs-md); cursor: pointer;
        transition: background .15s;
    }
    .btn-revert:hover:not(:disabled) { background: #5e50ee; }
    .btn-revert:disabled { background: #c9c5e0; cursor: not-allowed; }

    .pagination { margin-top: 1.5rem; display: flex; justify-content: center; gap: .3rem; }

    /* ── رسائل التحقّق داخل النموذج ── */
    .error-message { display: none; }
    .error-message:not(:empty) { display: block; }

    /* ── صفّ بيان: تسمية وقيمة ── */
    .bx-facts { display: grid; gap: 0; }

    /* ═══════════ شريط النسبة ═══════════
       يرافق عددَ كل نوعٍ في اللوحة. الأرقام وحدها لا تُظهر أن تسعةَ
       أعشار الشجرة مجموعاتٌ لا فروع — والشريطُ يقولها بلا قراءة.
       كان مكتوباً `style=""` داخل القالب في أربعة مواضع. */
    .bx-bar {
        display: block; height: 4px; margin: .35rem 0 .25rem;
        border-radius: 2px; background: var(--bx-line); overflow: hidden;
    }

    .bx-bar__fill { display: block; height: 100%; border-radius: 2px; }
    .bx-bar__fill--primary { background: var(--bx-primary); }
    .bx-bar__fill--success { background: var(--bx-success); }
    .bx-bar__fill--info { background: var(--bx-info); }
    .bx-bar__fill--warning { background: var(--bx-warning); }


    /* قائمةُ الفروع التابعة تُقصَّر بتمريرٍ داخليّ: عقدةٌ لها أربعون ابناً
       كانت تدفع بطاقتَي الإعدادات والبيانات خارج الشاشة. */
    .bx-scroll-sm { max-height: 300px; overflow-y: auto; }

    /* صفُّ تعريفٍ قابلٌ للنقر — يبقى بلون النصّ لا بلون الروابط، فالصفُّ
       كلُّه هدفُ النقر ولا معنى لتلوينه أزرق. */
    .bx-def-link { text-decoration: none; color: inherit; }
    .bx-def-link:hover { background: var(--bx-primary-soft); text-decoration: none; color: inherit; }
    .bx-def-link dt { font-weight: 600; }


    /* ═══════════════ لوحة الفروع (`bxd-*`) ═══════════════

       اللوحة تعرض الشجرةَ كلَّها بأرقامها، لا أربعةَ أرقامٍ عامّة. وهذا يفرض
       ثلاثة أشياء لا تكفيها مكوّناتُ الجداول العامّة:

         ١) صفٌّ يحمل ستّةَ أرقامٍ ويبقى مقروءاً — فالأرقام تُكتب برتبتَين:
            الإجماليُّ كبيراً، وتفصيلُه تحته صغيراً بلونٍ أهدأ.
         ٢) شبكتان تتكيّفان بأنفسهما (`auto-fit`) بلا كتابةِ مقاسات الشاشات
            في القالب — البطاقاتُ والرسوم تُعاد ترتيبَ نفسِها على الجوّال.
         ٣) شريطُ تحكّمٍ لا يكسر السطر على الشاشات الضيّقة.

       وكلُّ ما يمكن أخذُه من `stk-*` مأخوذٌ منه: البطاقات والجداول والأرقام
       والحالات. ما هنا هو ما لا يوجد هناك فقط.
       ═══════════════════════════════════════════════════ */

    .bxd-legend { display: grid; gap: .45rem; align-content: center; }
    .bxd-legend__row {
        display: grid; align-items: center; gap: .5rem;
        grid-template-columns: 18px minmax(0, 1fr) auto minmax(46px, 76px) 34px;
        font-size: var(--bx-fs-sm);
    }
    .bxd-legend__label {
        color: var(--bx-ink); overflow: hidden;
        text-overflow: ellipsis; white-space: nowrap;
    }
    .bxd-legend__row .bx-bar { margin: 0; }
    .bxd-legend__pct {
        color: var(--bx-ink-muted); font-size: var(--bx-fs-xs);
        text-align: end; font-variant-numeric: tabular-nums;
    }

    /* ── تنبيهاتُ السلامة: قائمةٌ لا فقرةٌ واحدة، فكلُّ خللٍ سطرُه ── */
    .bxd-health { margin: 0; padding-inline-start: 1.1rem; }
    .bxd-health li { margin-bottom: .35rem; }
    .bxd-health li:last-child { margin-bottom: 0; }

    /* ── بطاقاتُ الجذور ── */
    .bxd-roots {
        display: grid; gap: .8rem;
        grid-template-columns: repeat(auto-fit, minmax(min(255px, 100%), 1fr));
    }
    .bxd-root {
        display: flex; flex-direction: column; gap: .5rem;
        padding: .85rem .9rem; text-decoration: none; color: inherit;
        border: 1px solid var(--bx-line); border-radius: var(--bx-radius);
        background: var(--bx-surface); transition: box-shadow .16s, border-color .16s, transform .16s;
        /* الشريطُ الجانبيّ يقول نوعَ الجذر قبل قراءة سطرٍ واحد */
        border-inline-start: 3px solid var(--bx-primary);
    }
    .bxd-root:hover {
        text-decoration: none; color: inherit; transform: translateY(-1px);
        border-color: var(--bx-line-strong); box-shadow: var(--bx-shadow-lift);
    }
    .bxd-root--success { border-inline-start-color: var(--bx-success); }
    .bxd-root--info { border-inline-start-color: var(--bx-info); }
    .bxd-root--warning { border-inline-start-color: var(--bx-warning); }

    .bxd-root__head { display: flex; align-items: center; gap: .55rem; min-width: 0; }
    .bxd-root__icon {
        width: 34px; height: 34px; border-radius: 10px; flex: 0 0 auto;
        display: inline-flex; align-items: center; justify-content: center;
        background: var(--bx-primary-soft); color: var(--bx-primary);
    }
    .bxd-root--success .bxd-root__icon { background: rgba(40, 199, 111, .12); color: var(--bx-success); }
    .bxd-root--info .bxd-root__icon { background: rgba(0, 207, 232, .12); color: var(--bx-info); }
    .bxd-root--warning .bxd-root__icon { background: rgba(255, 159, 67, .12); color: var(--bx-warning); }

    .bxd-root__titles { min-width: 0; display: flex; flex-direction: column; gap: .18rem; }
    .bxd-root__name {
        font-weight: 700; color: var(--bx-ink-strong); font-size: var(--bx-fs-md);
        overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
    }
    .bxd-root__sub { display: flex; flex-wrap: wrap; align-items: center; gap: .3rem; }

    .bxd-root__value {
        font-size: var(--bx-fs-2xl, 1.6rem); font-weight: 700; line-height: 1.1;
        color: var(--bx-ink-strong); font-variant-numeric: tabular-nums;
    }
    .bxd-root__value small {
        font-size: var(--bx-fs-xs); font-weight: 500;
        color: var(--bx-ink-muted); margin-inline-start: .25rem;
    }
    .bxd-root .bx-bar { margin: 0; }

    .bxd-root__facts {
        display: flex; flex-wrap: wrap; gap: .5rem;
        font-size: var(--bx-fs-xs); color: var(--bx-ink-muted);
        font-variant-numeric: tabular-nums;
    }
    .bxd-root__facts i { margin-inline-end: .12rem; }

    /* ── شارةُ النوع: أصغرُ من `sl-tag` لأنها تتكرّر في كل صفّ ── */
    .bxd-type {
        display: inline-flex; align-items: center; padding: .05rem .38rem;
        border-radius: 5px; font-size: var(--bx-fs-xs); font-weight: 600;
        background: var(--bx-primary-soft); color: var(--bx-primary);
        white-space: nowrap;
    }
    .bxd-type--success { background: rgba(40, 199, 111, .12); color: var(--bx-success); }
    .bxd-type--info { background: rgba(0, 207, 232, .12); color: var(--bx-info); }
    .bxd-type--warning { background: rgba(255, 159, 67, .12); color: var(--bx-warning); }
    .bxd-type--main { background: rgba(40, 199, 111, .14); color: var(--bx-success); }
    .bxd-type--off { background: rgba(234, 84, 85, .12); color: var(--bx-danger); }

    /* ── جدولُ الهيكل ──
       الأرقامُ بأرقامٍ متساويةِ العرض (`tabular-nums`): بدونها يتراقص العمود
       سطراً بعد سطر فيصعب مسحُه بالعين. */
    .bxd-table { font-size: var(--bx-fs-sm); }
    .bxd-table td { vertical-align: middle; padding-top: .45rem; padding-bottom: .45rem; }
    .bxd-table th { white-space: nowrap; }
    .bxd-table .stk-num { font-variant-numeric: tabular-nums; }
    .bxd-node-cell { min-width: 260px; }
    #bxd-table tbody tr.is-hit { background: #fffaf0 !important; box-shadow: inset 2px 0 0 var(--bx-warning); }

    .bxd-ident { min-width: 0; display: flex; flex-direction: column; gap: .1rem; }
    .bxd-ident .bx-node-name { display: block; }
    .bxd-ident__meta {
        display: flex; flex-wrap: wrap; align-items: center; gap: .35rem;
        font-size: var(--bx-fs-xs); color: var(--bx-ink-muted);
    }
    .bxd-ident__meta i { margin-inline-end: .1rem; }

    .bxd-clients { min-width: 140px; }
    .bxd-big {
        display: block; font-weight: 700; color: var(--bx-ink-strong);
        font-size: var(--bx-fs-md); line-height: 1.2; font-variant-numeric: tabular-nums;
    }
    .bxd-clients .bx-bar { margin: .25rem 0 .2rem; }
    .bxd-split {
        display: flex; flex-wrap: wrap; align-items: center; gap: .45rem;
        font-size: var(--bx-fs-xs); color: var(--bx-ink-muted);
        font-variant-numeric: tabular-nums;
    }
    .bxd-share { margin-inline-start: auto; font-weight: 600; }

    /* ── النشِط والموقوف: نقطتان ملوّنتان بدل كلمتين تكرّران نفسَهما في كل صفّ ── */
    .bxd-pair { display: inline-flex; align-items: center; gap: .3rem; }
    .bxd-dot {
        display: inline-flex; align-items: center; gap: .2rem;
        padding: .05rem .4rem; border-radius: 10px; font-weight: 600;
        font-size: var(--bx-fs-xs); font-variant-numeric: tabular-nums;
    }
    .bxd-dot::before {
        content: ''; width: 6px; height: 6px; border-radius: 50%; flex: 0 0 auto;
    }
    .bxd-dot--on { background: rgba(40, 199, 111, .1); color: var(--bx-success); }
    .bxd-dot--on::before { background: var(--bx-success); }
    .bxd-dot--off { background: rgba(234, 84, 85, .1); color: var(--bx-danger); }
    .bxd-dot--off::before { background: var(--bx-danger); }

    .bxd-none { color: var(--bx-ink-muted); }

    /* ═══════════ مُنتقي «تابع لـ» داخل شاشات الفروع ═══════════

       القالبُ `reports.partials.affiliation-tree` مشتركٌ مع تقارير العملاء،
       وأصنافُه `rpt-*` تُعرَّف في ورقة كلّ وحدةِ تقارير على حدة. فتُعرَّف
       هنا كذلك — بأقلّ ما يلزم لا بنسخِ الورقة كلّها — وإلّا وصل المُنتقي
       بلا تسميةٍ منسّقةٍ ولا تباعد. */
    .rpt-field { margin-bottom: .75rem; }
    .rpt-field > label {
        display: block; font-size: var(--bx-fs-xs); font-weight: 700;
        color: var(--bx-ink-muted); margin-bottom: .3rem;
    }
    .rpt-field .form-control,
    .rpt-field select { font-size: var(--bx-fs-sm); }

    /* ═══════════ بطاقةُ الجهة المختارة ═══════════ */
    .bxd-focus {
        border-inline-start: 3px solid var(--bx-primary);
        background: linear-gradient(180deg, var(--bx-primary-soft), transparent 70%);
    }
    .bxd-focus--success { border-inline-start-color: var(--bx-success); }
    .bxd-focus--info { border-inline-start-color: var(--bx-info); }
    .bxd-focus--warning { border-inline-start-color: var(--bx-warning); }

    .bxd-focus__head {
        display: flex; flex-wrap: wrap; align-items: center;
        gap: .6rem; margin-bottom: .8rem;
    }
    .bxd-focus__icon {
        width: 44px; height: 44px; border-radius: 12px; flex: 0 0 auto;
        display: inline-flex; align-items: center; justify-content: center;
        background: var(--bx-surface); color: var(--bx-primary);
        border: 1px solid var(--bx-line);
    }
    .bxd-focus__icon i { font-size: var(--bx-fs-xl, 1.25rem); }
    .bxd-focus--success .bxd-focus__icon { color: var(--bx-success); }
    .bxd-focus--info .bxd-focus__icon { color: var(--bx-info); }
    .bxd-focus--warning .bxd-focus__icon { color: var(--bx-warning); }

    .bxd-focus__titles { min-width: 0; display: flex; flex-direction: column; gap: .2rem; }
    .bxd-focus__name {
        font-size: var(--bx-fs-lg); font-weight: 700; color: var(--bx-ink-strong);
        overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
    }
    .bxd-focus__sub {
        display: flex; flex-wrap: wrap; align-items: center; gap: .35rem;
        font-size: var(--bx-fs-xs);
    }
    .bxd-focus__actions {
        display: flex; flex-wrap: wrap; gap: .35rem;
        margin-inline-start: auto;
    }

    /* ── شبكةُ الأرقام: تلتفّ بنفسها فلا تُكتب مقاسات الشاشات في القالب ── */
    .bxd-facts {
        display: grid; gap: .5rem;
        grid-template-columns: repeat(auto-fit, minmax(min(150px, 100%), 1fr));
    }
    .bxd-fact {
        display: flex; align-items: center; gap: .5rem;
        padding: .55rem .65rem; border-radius: var(--bx-radius-sm);
        background: var(--bx-surface); border: 1px solid var(--bx-line);
    }
    .bxd-fact > i { color: var(--bx-primary); flex: 0 0 auto; }
    .bxd-fact--success > i { color: var(--bx-success); }
    .bxd-fact--info > i { color: var(--bx-info); }
    .bxd-fact--warning > i { color: var(--bx-warning); }
    .bxd-fact--danger > i { color: var(--bx-danger); }
    .bxd-fact__body { min-width: 0; display: flex; flex-direction: column; }
    .bxd-fact__value {
        font-weight: 700; color: var(--bx-ink-strong); line-height: 1.15;
        font-variant-numeric: tabular-nums;
    }
    .bxd-fact__label {
        font-size: var(--bx-fs-xs); color: var(--bx-ink-muted);
        overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
    }

    .bxd-focus__kids {
        display: flex; flex-wrap: wrap; align-items: center; gap: .35rem;
        margin-top: .7rem; font-size: var(--bx-fs-xs);
    }

    /* ── إجراءاتُ الصفّ: أيقوناتٌ لا أزرار، فالعمودُ يتكرّر مئةَ مرّة ── */
    .bxd-row-actions { white-space: nowrap; }
    .bxd-row-actions a {
        display: inline-flex; align-items: center; justify-content: center;
        width: 26px; height: 26px; border-radius: 7px; margin: 0 1px;
        color: var(--bx-ink-muted); text-decoration: none;
    }
    .bxd-row-actions a:hover { background: var(--bx-primary-soft); color: var(--bx-primary); }

</style>
