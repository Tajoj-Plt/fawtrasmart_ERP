{{--
    ══════════════════════════════════════════════════════════════════════
    طبقة «مستند المبيعات» فوق نظام تصميم المبيعات
    ══════════════════════════════════════════════════════════════════════

    `sales::partials.settings-theme` يحمل لغة التصميم الأساسية بالبادئة
    `sl-` — الترويسة والبطاقات والجداول والفلاتر والنماذج. هذا الملف يضيف
    فوقها ما تحتاجه شاشات **المستندات** وحدها: جدول بنودٍ حقولُه داخل
    الخلايا، ولوحة إجماليات، وشريط أدوات، وحقل رفع مرفقات، وشارة رقم.

    نقلٌ عن `purchases::partials.style` (البادئة `po-`) — الشكل نفسه الذي
    تُبنى به شاشة «إنشاء فاتورة مشتريات»، بالبادئة `sl-`. نسخة لا مشاركة:
    قالبٌ في المبيعات يشير إلى `purchases::` ينكسر بمجرّد إطفاء وحدة
    المشتريات في `modules_statuses.json`.

    كل لون وخطّ وزاوية يشير إلى متغيّرات `--app-*` التي تحقنها
    `App\Support\ThemeSettings`. فما يُضبط في «مظهر النظام» — اللون
    الأساسي والخطّ واستدارة الحواف والوضع الليلي — يسري هنا بلا سطر
    يُعدَّل.

    الاتجاه: لا `left`/`right`، بل `inline-start`/`inline-end`، فالشاشة
    تنقلب مع `dir` الذي تضبطه `App\Support\LanguageSettings`.

    الاستعمال:

        @section('content')
            @include('sales::partials.document-theme')
            <div class="sl-scope sl-doc"> … </div>
        @endsection
--}}

@include('sales::partials.settings-theme')
@include('components.hint-tips')

@once
    <style>
        /* ═══════════════ نطاق المستند ═══════════════
           سلّم الخطّ مشتقّ من مقاس الخطّ في «مظهر النظام» بـ`calc` لا
           بـ`em`: `rem` نسبةٌ إلى `<html>` لا إلى الجسم، فمن يرفع المقاس
           في المظهر لا يتغيّر عنده حرف. و`em` تتضاعف مع التعشيش. */

        .sl-doc {
            --sl-fs: var(--app-font-size, 14px);
            --sl-fs-xs: calc(var(--sl-fs) * .86);
            --sl-fs-sm: calc(var(--sl-fs) * .93);
            --sl-fs-lg: calc(var(--sl-fs) * 1.14);

            color: var(--app-text, #4b4b4b);
            font-family: var(--app-font-family, inherit);
            font-size: var(--sl-fs);
        }

        /* ═══════════════ بطاقة المستند ═══════════════
           `overflow: visible` مقصود: `overflow:hidden` كان يقصّ القوائم
           المنسدلة داخل البطاقة — قائمة «المطبوعات» تُرسم ثم تُبتر عند
           حافّة البطاقة فلا يظهر منها شيء. التدوير يُطبَّق على الجدول
           وحده بدلاً منه. */

        .sl-doc-card {
            background: var(--app-surface, #fff);
            border: 1px solid var(--sl-line);
            border-radius: var(--sl-r-lg);
            box-shadow: var(--sl-e2);
            margin-bottom: var(--sl-5);
            overflow: visible;
        }

        .sl-doc-card > .table-responsive:last-child {
            border-end-start-radius: var(--sl-r-lg);
            border-end-end-radius: var(--sl-r-lg);
        }

        .sl-doc .dropdown-menu.show { z-index: 1060; }

        .sl-doc-card__head {
            display: flex; align-items: center; justify-content: space-between;
            gap: var(--sl-3); flex-wrap: wrap;
            /* رأسيّاً أضيق من الأفقي عمداً — بطاقة الهوية والإجماليات تتكرّر
               فوق كل تبويب، فأثقلُ عنصرٍ متكرّر في الشاشة هو حشوتها */
            padding: .65rem var(--sl-5);
            border-bottom: 1px solid var(--sl-line-soft);
        }

        .sl-doc-card__head--flush { padding: 0; border-bottom: 0; }

        .sl-doc-card__title {
            display: flex; align-items: center; gap: var(--sl-2);
            margin: 0; font-size: .98rem; font-weight: 700;
            color: var(--app-text, #4b4b4b);
        }
        .sl-doc-card__title i { color: var(--app-primary, #7367f0); font-size: .95em; }

        /* رأسيّاً أضيق من الأفقي: نفس منطق `__head` أعلاه — طلب المستخدم
           تقليص طول شاشات عرض المستندات (فاتورة/مرتجع/إشعار/عرض سعر) */
        .sl-doc-card__body { padding: 1rem var(--sl-5); }

        .sl-doc-card__foot {
            display: flex; align-items: flex-start; justify-content: space-between;
            gap: var(--sl-4); flex-wrap: wrap;
            padding: .65rem var(--sl-5);
            border-top: 1px solid var(--sl-line-soft);
            background: var(--sl-tint);
            border-end-start-radius: var(--sl-r-lg);
            border-end-end-radius: var(--sl-r-lg);
        }

        .sl-hint {
            display: inline-flex; align-items: center; gap: .35rem;
            font-size: var(--sl-fs-sm); color: var(--app-muted, #6e6b7b);
        }

        /* ═══════════════ حقل في المستند ═══════════════ */

        .sl-field { margin-bottom: 0; }

        .sl-field > label {
            display: block; margin-bottom: .35rem;
            font-size: var(--sl-fs-sm); font-weight: 600;
            color: var(--app-text, #4b4b4b);
        }

        .sl-field .required,
        .sl-doc .required { color: var(--app-danger, #ea5455); font-weight: 700; }

        /* ═══════════════ تبويبات المستند ═══════════════ */

        .sl-doc .nav.sl-doc-tabs {
            display: flex; flex-wrap: wrap; gap: 0;
            border-bottom: 1px solid var(--sl-line-soft);
            padding: 0 var(--sl-3); margin: 0; list-style: none;
        }
        .sl-doc .sl-doc-tabs .nav-link {
            display: inline-flex; align-items: center; gap: .4rem;
            padding: var(--sl-3) var(--sl-4);
            border: 0; border-bottom: 2px solid transparent; border-radius: 0;
            background: transparent; color: var(--app-muted, #6e6b7b);
            font-size: var(--sl-fs-sm); font-weight: 600;
        }
        .sl-doc .sl-doc-tabs .nav-link:hover { color: var(--app-primary, #7367f0); }
        .sl-doc .sl-doc-tabs .nav-link.active {
            color: var(--app-primary, #7367f0);
            border-bottom-color: var(--app-primary, #7367f0);
            background: transparent;
        }

        /* ═══════════════ جدول البنود ═══════════════
           الحقول داخل الخلايا: حدودها تُنزع ويبقى الإطار للخليّة نفسها،
           فالجدول يُقرأ جدولاً لا شبكةَ صناديق. */

        .sl-doc-table { margin: 0; font-size: var(--sl-fs-sm); }

        .sl-doc-table > thead > tr > th {
            padding: var(--sl-3) var(--sl-3);
            background: var(--sl-tint);
            border-bottom: 1px solid var(--sl-line);
            border-top: 0;
            font-size: var(--sl-fs-xs); font-weight: 700;
            color: var(--app-muted, #6e6b7b);
            text-transform: none; letter-spacing: 0;
            white-space: nowrap;
        }

        .sl-doc-table > tbody > tr > td {
            padding: var(--sl-2) var(--sl-3);
            border-top: 1px solid var(--sl-line-soft);
            vertical-align: middle;
        }

        .sl-doc-table > tbody > tr:hover > td {
            background: color-mix(in srgb, var(--app-primary, #7367f0) 3%, transparent);
        }

        .sl-doc-table .num { text-align: center; font-variant-numeric: tabular-nums; }

        .sl-doc-table .sl-row__index {
            display: inline-flex; align-items: center; justify-content: center;
            width: 26px; height: 26px; border-radius: 50%;
            background: var(--app-primary-soft, rgba(115, 103, 240, .12));
            color: var(--app-primary, #7367f0);
            font-size: var(--sl-fs-xs); font-weight: 700;
        }

        /* حقل داخل خليّة — بلا حدّ ولا ظلّ، والتركيز يُبرزه وحده */
        .sl-doc-table .sl-input,
        .sl-doc-table .form-control,
        .sl-doc-table .form-select {
            height: 34px; padding: 0 .5rem;
            border: 1px solid transparent; border-radius: var(--sl-r);
            background: transparent; box-shadow: none;
            font-size: var(--sl-fs-sm); color: var(--app-text, #4b4b4b);
            width: 100%;
        }

        .sl-doc-table textarea.form-control { height: auto; min-height: 34px; padding: .3rem .5rem; }

        .sl-doc-table .sl-input:hover,
        .sl-doc-table .form-control:hover,
        .sl-doc-table .form-select:hover { border-color: var(--sl-line); }

        .sl-doc-table .sl-input:focus,
        .sl-doc-table .form-control:focus,
        .sl-doc-table .form-select:focus {
            border-color: var(--app-primary, #7367f0);
            background: var(--app-surface, #fff);
            box-shadow: 0 0 0 3px var(--app-primary-soft, rgba(115, 103, 240, .12));
            outline: 0;
        }

        /* مجموعةٌ داخل خليّة — «الخصم» قيمةٌ ونوعُها.

           `.input-group` في بوتستراب `flex-wrap: wrap`، وحقولُها هنا
           `width: 100%` بالقاعدة أعلاه، فيأخذ أوّلُها عرض الخليّة كلَّه
           ويهبط النوعُ تحته سطراً ثانياً — وهو ما كان يظهر في عمود «الخصم»
           في شاشتَي عرض السعر وفاتورة البيع معاً: مربّعُ رقمٍ وتحته مربّعُ
           «ر.س». الحقلُ يتمدّد والنوعُ يأخذ قدرَه ولا يلتفّ أيٌّ منهما. */
        .sl-doc-table .input-group { flex-wrap: nowrap; }

        .sl-doc-table .input-group > .form-control,
        .sl-doc-table .input-group > .sl-input {
            flex: 1 1 auto; width: 1%; min-width: 0;
        }

        .sl-doc-table .input-group > select.form-control,
        .sl-doc-table .input-group > select.sl-input {
            flex: 0 0 auto; width: auto; min-width: 3.4rem;
        }

        .sl-doc-table .sl-line-total {
            font-weight: 700; font-variant-numeric: tabular-nums;
            color: var(--app-text, #4b4b4b);
        }

        .sl-row__remove {
            display: inline-flex; align-items: center; justify-content: center;
            width: 30px; height: 30px; padding: 0;
            border: 1px solid transparent; border-radius: var(--sl-r);
            background: transparent; color: var(--app-danger, #ea5455);
            transition: background .15s ease, border-color .15s ease;
        }
        .sl-row__remove:hover {
            background: var(--app-danger-soft, rgba(234, 84, 85, .12));
            border-color: color-mix(in srgb, var(--app-danger, #ea5455) 35%, transparent);
        }

        /* ═══════════════ لوحة الإجماليات ═══════════════ */

        .sl-totals {
            min-width: 280px; margin-inline-start: auto;
            display: flex; flex-direction: column; gap: .2rem;
        }

        .sl-totals__row {
            display: flex; align-items: center; justify-content: space-between;
            gap: var(--sl-4);
            font-size: var(--sl-fs-sm); color: var(--app-muted, #6e6b7b);
        }
        .sl-totals__row > span:last-child {
            font-weight: 600; color: var(--app-text, #4b4b4b);
            font-variant-numeric: tabular-nums;
        }

        .sl-totals__row--grand {
            margin-top: .2rem; padding-top: .35rem;
            border-top: 1px solid var(--sl-line);
            font-size: var(--sl-fs-lg); color: var(--app-text, #4b4b4b); font-weight: 700;
        }
        .sl-totals__row--grand > span:last-child {
            color: var(--app-primary, #7367f0); font-size: 1.1em;
        }

        /* ═══════════════ شريط الأدوات ═══════════════ */

        .sl-toolbar { display: flex; align-items: center; gap: var(--sl-2); flex-wrap: wrap; }
        .sl-toolbar--end { margin-inline-start: auto; }

        /* ═══════════════ حقل رفع المرفقات ═══════════════ */

        .sl-upload {
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            gap: var(--sl-2); padding: var(--sl-5);
            border: 1px dashed var(--sl-line); border-radius: var(--sl-r-lg);
            background: var(--sl-tint); color: var(--app-muted, #6e6b7b);
            text-align: center; cursor: pointer;
            transition: border-color .16s ease, background .16s ease;
        }
        .sl-upload:hover, .sl-upload.is-dragging {
            border-color: var(--app-primary, #7367f0);
            background: var(--app-primary-soft, rgba(115, 103, 240, .12));
        }
        .sl-upload i { font-size: 1.6rem; color: var(--app-primary, #7367f0); }
        .sl-upload__title { font-size: var(--sl-fs-sm); font-weight: 600; color: var(--app-text, #4b4b4b); margin: 0; }
        .sl-upload__hint { font-size: var(--sl-fs-xs); margin: 0; }

        /* ═══════════════ شارة رقم المستند ═══════════════ */

        .sl-code {
            display: inline-flex; align-items: center; gap: .4rem;
            padding: .2rem .55rem; border-radius: var(--sl-r);
            background: var(--app-primary-soft, rgba(115, 103, 240, .12));
            color: var(--app-primary, #7367f0);
            font-size: var(--sl-fs-xs); font-weight: 700;
            font-variant-numeric: tabular-nums; letter-spacing: .02em;
        }

        /* ═══════════════ شريط تنبيه الإعدادات ═══════════════
           يقول للمستخدم بأيّ إعدادات تُحفَظ هذه الفاتورة — العملة،
           والفترة المحاسبية، والترقيم — كلٌّ برابط شاشته. */

        .sl-doc-note {
            display: flex; align-items: flex-start; gap: var(--sl-3);
            padding: var(--sl-3) var(--sl-4); margin-bottom: var(--sl-4);
            border: 1px solid var(--sl-line); border-inline-start: 3px solid var(--app-primary, #7367f0);
            border-radius: var(--sl-r);
            background: var(--sl-tint); font-size: var(--sl-fs-sm);
        }
        .sl-doc-note--warn { border-inline-start-color: var(--app-warning, #ff9f43); }
        .sl-doc-note__icon { flex: none; color: var(--app-primary, #7367f0); font-size: 1rem; line-height: 1.5; }
        .sl-doc-note--warn .sl-doc-note__icon { color: var(--app-warning, #ff9f43); }
        .sl-doc-note__body { min-width: 0; }
        .sl-doc-note__body strong { color: var(--app-text, #4b4b4b); }

        /* ═══════════════ حقائق المستند ═══════════════
           شبكةُ «تسمية ← قيمة» في شاشة العرض. كانت أسطراً من
           `<p><strong>العميل:</strong> …</p>` بلا محاذاةٍ بينها، فتتذبذب
           القيم يميناً ويساراً بطول التسمية. */

        .sl-facts {
            /* الفجوة الرأسية بين الصفوف أضيق من الأفقية بين الأعمدة عمداً:
               هي ما يكرّر ارتفاع البطاقة كلّما زاد عدد «الحقائق» المعروضة */
            display: grid; gap: .4rem var(--sl-5);
            grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
        }

        .sl-fact { min-width: 0; display: flex; flex-direction: column; gap: .1rem; }

        .sl-fact__label {
            font-size: var(--sl-fs-xs); color: var(--app-muted, #6e6b7b);
            text-transform: none; letter-spacing: .01em;
        }

        .sl-fact__value {
            font-size: var(--sl-fs-sm); font-weight: 600; color: var(--app-text, #4b4b4b);
            overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
        }

        /* ═══════════════ بطاقة الهوية المضغوطة ═══════════════
           `sl-doc-card--summary` على بطاقة رأس شاشات العرض (فاتورة، مرتجع،
           إشعار دائن، عرض سعر، أمر بيع، فاتورة دورية، سند). كانت الحقائق
           عمودين في أربعة صفوف وتحتها الشارات بهامش القالب (1.5rem) ثم شريط
           أزرارٍ بارتفاع 38px — فتأخذ البطاقة ثلث الشاشة قبل أوّل تبويب.
           الآن: أعمدةٌ أكثر وأضيق، وأسطرٌ متراصّة، وأزرارٌ أقصر.

           القيمة تلتفّ هنا بدل أن تُقصّ: العمود الأضيق كان سيبتر اسم
           العميل الطويل بـ«…». */

        .sl-doc-card--summary > .sl-doc-card__body { padding: .7rem var(--sl-5) .65rem; }

        .sl-doc-card--summary > .sl-doc-card__body > .row { --bs-gutter-y: .6rem; }

        .sl-doc-card--summary .sl-facts {
            gap: .45rem var(--sl-4);
            grid-template-columns: repeat(auto-fit, minmax(118px, 1fr));
        }

        .sl-doc-card--summary .sl-fact { gap: 0; }
        .sl-doc-card--summary .sl-fact__label { line-height: 1.35; }
        .sl-doc-card--summary .sl-fact__value {
            line-height: 1.4;
            white-space: normal; overflow-wrap: anywhere;
        }

        /* اسم العميل أطول قيم البطاقة — خانتان له فلا يلتفّ ثلاثة أسطر ويمدّ
           صفَّه كلَّه */
        .sl-doc-card--summary .sl-fact--wide { grid-column: span 2; }

        /* على الجوّال عمودان ثابتان: `auto-fit` مع خانةٍ تمتدّ عمودين يولّد
           عموداً ضمنياً بعرض صفر متى لم يتّسع السطر إلّا لعمود */
        @media (max-width: 575.98px) {
            .sl-doc-card--summary .sl-facts { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }

        .sl-doc-card--summary .sl-code { padding: .08rem .45rem; }

        /* سطر التنبيه تحت الشارات: هامشُ القالب (1.5rem فوق و1rem تحت) يفتح
           فراغاً بحجم صفّ حقائقٍ كامل */
        .sl-doc-card--summary .sl-doc-card__body .sl-help {
            margin: .4rem 0 0 !important; line-height: 1.4;
        }

        /* `!important` لأن القوالب تضع `mt-2`/`mt-3` على الحاوية نفسها */
        .sl-doc-card--summary .sl-tags { margin-top: .55rem !important; }

        .sl-doc-card--summary .sl-totals { gap: .1rem; }
        .sl-doc-card--summary .sl-totals__row { line-height: 1.4; }
        .sl-doc-card--summary .sl-totals__row--grand {
            margin-top: .15rem; padding-top: .25rem;
            font-size: var(--sl-fs);
        }

        .sl-doc-card--summary > .sl-doc-card__foot { padding: .45rem var(--sl-5); }

        .sl-doc-card--summary > .sl-doc-card__foot .sl-toolbar > .btn,
        .sl-doc-card--summary > .sl-doc-card__foot .sl-toolbar > .dropdown > .btn,
        .sl-doc-card--summary > .sl-doc-card__foot .sl-toolbar > .btn-group > .btn,
        .sl-doc-card--summary > .sl-doc-card__foot .sl-toolbar > form > .btn {
            /* `.sl-scope .btn` يثبّت الارتفاع 38px — الحشوةُ وحدها لا تقصّره */
            height: 32px; padding: 0 .85rem;
        }

        /* ═══════════════ إطار معاينة المستند ═══════════════ */

        .sl-doc-frame {
            display: block; width: 100%; height: min(78vh, 900px);
            border: 0; background: var(--app-surface, #fff);
        }

        /* ═══════════════ لوحة التوقيع ═══════════════ */

        .sl-signature {
            position: relative; height: 200px;
            border: 1px dashed var(--sl-line); border-radius: var(--sl-r);
            background: #fff; overflow: hidden;
        }

        .sl-signature canvas { width: 100%; height: 100%; touch-action: none; display: block; }

        .sl-signature__guide {
            position: absolute; inset: 0;
            display: flex; align-items: center; justify-content: center;
            color: var(--app-muted, #6e6b7b); font-size: var(--sl-fs-sm);
            pointer-events: none;
        }

        .sl-signature__proof {
            display: block; max-height: 80px; margin-top: var(--sl-2);
            padding: .3rem; background: #fff;
            border: 1px solid var(--sl-line); border-radius: var(--sl-r);
        }

        /* ═══════════════ الخطّ الزمني ═══════════════
           سجلّ نشاط الفاتورة: يومٌ ثم أحداثه، والخطّ الرأسي يصل نقاطه. */

        .sl-timeline { position: relative; margin-bottom: var(--sl-5); }
        .sl-timeline:last-child { margin-bottom: 0; }

        .sl-timeline__day {
            display: flex; align-items: center; gap: var(--sl-2);
            margin: 0 0 var(--sl-3);
            font-size: var(--sl-fs-sm); font-weight: 700; color: var(--app-text, #4b4b4b);
        }
        .sl-timeline__day i { color: var(--app-primary, #7367f0); }
        .sl-timeline__day .sl-num { color: var(--app-muted, #6e6b7b); font-weight: 500; }

        .sl-timeline__item {
            position: relative; display: flex; gap: var(--sl-3);
            padding: 0 0 var(--sl-4) var(--sl-4);
            padding-inline-start: var(--sl-5); padding-inline-end: 0;
        }

        /* الخطّ الواصل: يُرسم على البند نفسه لا على الحاوية، فينتهي عند
           آخر حدثٍ بدل أن يمتدّ في الفراغ تحته */
        .sl-timeline__item::before {
            content: ''; position: absolute; inset-inline-start: 5px; top: 14px; bottom: -4px;
            width: 1px; background: var(--sl-line);
        }
        .sl-timeline__item:last-child::before { display: none; }

        .sl-timeline__dot {
            position: absolute; inset-inline-start: 0; top: 6px;
            width: 11px; height: 11px; border-radius: 50%;
            background: var(--app-primary, #7367f0);
            box-shadow: 0 0 0 3px var(--app-primary-soft, rgba(115, 103, 240, .12));
        }

        .sl-timeline__body { min-width: 0; display: flex; gap: var(--sl-3); flex-wrap: wrap; }

        .sl-timeline__time {
            flex: none; font-size: var(--sl-fs-xs); color: var(--app-muted, #6e6b7b);
            padding-top: .1rem;
        }

        .sl-timeline__text { min-width: 0; font-size: var(--sl-fs-sm); }
        .sl-timeline__text p { margin: 0; }
        .sl-timeline__text strong { color: var(--app-text, #4b4b4b); }

        /* ═══════════════ الشاشات الصغيرة ═══════════════ */

        @media (max-width: 767.98px) {
            .sl-doc-card__body, .sl-doc-card__head, .sl-doc-card__foot { padding: var(--sl-4); }
            .sl-totals { min-width: 100%; }
            .sl-doc-card__foot { flex-direction: column; align-items: stretch; }
        }

        @media print {
            .sl-toolbar, .sl-row__remove, .sl-upload { display: none !important; }
            .sl-doc-card { box-shadow: none; border-color: #ddd; }
        }
            /* ═══════ قائمةٌ بجانبها زرُّ إضافة ═══════

           العميل غير مسجَّل؟ زرٌّ يفتح شاشة إضافته في تبويبٍ جديد بدل إجبار
           المستخدم على مغادرة النموذج الذي يملؤه وضياع ما أدخله. منقولةٌ عن
           `po-select-add` في وحدة المشتريات، بالبادئة `sl-`. */
        .sl-select-add { display: flex; align-items: stretch; gap: .4rem; }
        .sl-select-add > select,
        .sl-select-add > .select2 { flex: 1 1 auto; min-width: 0; }
        .sl-select-add__btn {
            flex: 0 0 auto;
            display: inline-flex; align-items: center; justify-content: center;
            width: 2.35rem; padding: 0;
        }

</style>
@endonce
