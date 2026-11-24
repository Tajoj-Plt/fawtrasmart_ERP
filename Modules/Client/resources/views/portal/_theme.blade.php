{{--
    نظام تصميم بوابة العميل — البادئة `cp-` (client portal).

    ══════════════════ لماذا نظامٌ مستقل؟ ══════════════════

    البوابة يفتحها **زبونٌ لا موظّف**: مرّةً في الشهر، على هاتفه غالباً،
    ليعرف كم عليه ومتى. وقالب النظام الإداري مبنيٌّ لعكس ذلك تماماً —
    قائمةٌ جانبية بعشرين قسماً لا يخصّه منها شيء، وشريطُ إشعاراتٍ ومهامّ،
    وjQuery ستّ مرات وDataTables ثلاثاً وTinyMCE وخرائط Google. ثِقلٌ
    يُحمَّل على من لن يستعمل منه سطراً.

    فهنا: بلا قائمةٍ جانبية، والتنقّل شريطُ تبويباتٍ يُشتقّ من الصلاحيات
    المفعَّلة وحدها (`ClientPortalPermissions::navItems()`).

    ══════════════════ الألوان ══════════════════

    من متغيّرات «مظهر النظام» (`--app-*`) متى وُجدت، وإلّا فقيمٌ احتياطية.
    فتغييرُ لون المنشأة في الإعدادات يصل البوابة كما يصل بقيّة الشاشات،
    ولا تصير البوابة جزيرةً بلونٍ آخر.

    ══════════════════ الأرقام ══════════════════

    `font-variant-numeric: tabular-nums` على كل رقمٍ ماليّ: بدونه تختلف
    عروض الخانات فلا تصطفّ المبالغ تحت بعضها في العمود، وهو أوّل ما تفقده
    عينُ من يقرأ كشف حساب.
--}}
<style>
    /* مقياسُ البوابة: جذرُ الصفحة ١٤ بكسل لا ١٦.

       كلُّ مقاساتِ النظام بـ`rem`، فخفضُ الجذر يضغط الصفحةَ كلَّها معاً —
       الخطَّ والحشوَ والأيقونات والحواف — بدل تعديل ثلاثين قاعدة يدوياً،
       فتبقى النسبُ بينها كما صُمّمت. (طلب المستخدم 2026-09-19: أحجامٌ أصغر
       كالتصميم المرفق.) والشريطُ الجانبي وحدَه بالبكسل لأنه عرضٌ ثابت. */
    /* القاعدةُ على `html` مباشرةً: هذا الملفُّ لا يُضمَّن إلّا في قالب
       البوابة، فنطاقُه هو البوابة وحدها — و`:has()` لا تعمل على كل متصفّح. */
    html { font-size: 14px; }

    .cp {
        /* المسافات */
        --cp-1: .25rem;  --cp-2: .5rem;   --cp-3: .7rem;
        --cp-4: .9rem;   --cp-5: 1.15rem; --cp-6: 1.6rem;

        /* عرضُ الشريط الجانبي — بالبكسل: عرضٌ ثابتٌ لا يتبع الخطّ */
        --cp-side-w: 216px;

        /* الحواف والظلال */
        --cp-r: var(--app-radius, .625rem);
        --cp-r-lg: 1rem;
        --cp-line: color-mix(in srgb, var(--app-text, #4b4b4b) 10%, transparent);
        --cp-e1: 0 1px 2px rgba(16, 24, 40, .05);
        --cp-e2: 0 4px 16px rgba(16, 24, 40, .07);
        --cp-e3: 0 12px 32px rgba(16, 24, 40, .1);

        /* الألوان — من مظهر النظام متى وُجد */
        --cp-primary: var(--app-primary, #7367f0);
        --cp-primary-soft: color-mix(in srgb, var(--cp-primary) 12%, transparent);
        --cp-success: var(--app-success, #28c76f);
        --cp-danger: var(--app-danger, #ea5455);
        --cp-warning: var(--app-warning, #ff9f43);
        --cp-info: var(--app-info, #00cfe8);
        --cp-text: var(--app-text, #2f2b3d);
        --cp-muted: var(--app-muted, #6e6b7b);
        --cp-surface: var(--app-surface, #fff);
        --cp-bg: var(--app-body-bg, #f5f7fb);
        /* كحليُّ التصميم المرفق — الشريطُ الجانبي وبطاقةُ الترحيب */
        --cp-nav-bg: #12315b;
        --cp-nav-text: #ffffff;
        --cp-on-primary: var(--app-on-primary, #ffffff);

        background: var(--cp-bg);
        color: var(--cp-text);
        font-family: 'Cairo', system-ui, -apple-system, 'Segoe UI', sans-serif;
        min-height: 100vh;
    }

    .cp *, .cp *::before, .cp *::after { box-sizing: border-box; }

    /* ═══════════════ ترويسة الصفحة ═══════════════ */

    .cp-head { padding: var(--cp-6) 0 var(--cp-5); }
    .cp-head__title { margin: 0; font-size: 1.4rem; font-weight: 700; }
    .cp-head__sub { margin: var(--cp-1) 0 0; font-size: .85rem; color: var(--cp-muted); }

    /* ═══════════════ البطاقات ═══════════════ */

    .cp-card {
        background: var(--cp-surface);
        border: 1px solid var(--cp-line);
        border-radius: var(--cp-r-lg);
        box-shadow: var(--cp-e2);
        margin-bottom: var(--cp-5);
        overflow: hidden;
    }

    .cp-card__head {
        display: flex; align-items: center; gap: var(--cp-2);
        padding: var(--cp-4) var(--cp-5);
        border-bottom: 1px solid var(--cp-line);
    }

    .cp-card__title { margin: 0; font-size: .9rem; font-weight: 700; flex: 1 1 auto; }
    .cp-card__title i { color: var(--cp-primary); margin-inline-end: var(--cp-2); }
    .cp-card__body { padding: var(--cp-5); }
    .cp-card__body--flush { padding: 0; }

    /* ═══════════════ بطاقات الأرقام ═══════════════ */

    .cp-stats {
        display: grid; gap: var(--cp-4);
        grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
        margin-bottom: var(--cp-5);
    }

    .cp-stat {
        background: var(--cp-surface);
        border: 1px solid var(--cp-line);
        border-radius: var(--cp-r-lg);
        box-shadow: var(--cp-e2);
        padding: var(--cp-5);
        display: flex; align-items: center; gap: var(--cp-4);
    }

    .cp-stat__icon {
        flex: none; width: 40px; height: 40px; border-radius: var(--cp-r);
        display: flex; align-items: center; justify-content: center;
        font-size: 1rem;
        background: var(--cp-primary-soft); color: var(--cp-primary);
    }

    .cp-stat__icon--success { background: color-mix(in srgb, var(--cp-success) 13%, transparent); color: var(--cp-success); }
    .cp-stat__icon--danger  { background: color-mix(in srgb, var(--cp-danger) 13%, transparent);  color: var(--cp-danger); }
    .cp-stat__icon--warning { background: color-mix(in srgb, var(--cp-warning) 15%, transparent); color: var(--cp-warning); }
    .cp-stat__icon--info    { background: color-mix(in srgb, var(--cp-info) 13%, transparent);    color: var(--cp-info); }

    .cp-stat__body { min-width: 0; }

    .cp-stat__value {
        margin: 0; font-size: 1.5rem; font-weight: 700; line-height: 1.15;
        font-variant-numeric: tabular-nums;
    }

    .cp-stat__label { margin: var(--cp-1) 0 0; font-size: .8rem; color: var(--cp-muted); }

    /* ═══════════════ بطاقات التنقّل السريع ═══════════════ */

    /* ‏١٦٨ بكسل: ستُّ بطاقاتٍ تسع صفّاً واحداً على الحاسب (كانت ٢٢٠ فتنزل
       الأخيرةُ وحدها ويبدو الصفُّ ناقصاً)، وتتراصّ على الهاتف بالتفافها */
    .cp-tiles {
        display: grid; gap: var(--cp-3);
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    }

    .cp-tile {
        display: flex; align-items: center; gap: var(--cp-3);
        padding: var(--cp-5);
        background: var(--cp-surface);
        border: 1px solid var(--cp-line);
        border-radius: var(--cp-r-lg);
        box-shadow: var(--cp-e1);
        color: inherit; text-decoration: none;
        transition: transform .15s, box-shadow .15s, border-color .15s;
    }

    .cp-tile:hover {
        transform: translateY(-2px);
        box-shadow: var(--cp-e3);
        border-color: color-mix(in srgb, var(--cp-primary) 35%, transparent);
        color: inherit; text-decoration: none;
    }

    .cp-tile__icon {
        flex: none; width: 38px; height: 38px; border-radius: var(--cp-r);
        display: flex; align-items: center; justify-content: center;
        background: var(--cp-primary-soft); color: var(--cp-primary); font-size: .95rem;
    }

    .cp-tile > div { min-width: 0; }
    .cp-tile__title { margin: 0; font-weight: 700; font-size: .92rem; }
    .cp-tile__desc { margin: var(--cp-1) 0 0; font-size: .78rem; color: var(--cp-muted); }

    /* ═══════════════ الجداول ═══════════════ */

    .cp-tablewrap { overflow-x: auto; }

    .cp-table { width: 100%; border-collapse: collapse; font-size: .87rem; }

    .cp-table thead th {
        text-align: start; font-weight: 700; font-size: .78rem;
        color: var(--cp-muted); text-transform: none;
        padding: var(--cp-3) var(--cp-5);
        background: color-mix(in srgb, var(--cp-text) 3%, transparent);
        border-bottom: 1px solid var(--cp-line);
        white-space: nowrap;
    }

    .cp-table tbody td {
        padding: var(--cp-4) var(--cp-5);
        border-bottom: 1px solid var(--cp-line);
        vertical-align: middle;
    }

    .cp-table tbody tr:last-child td { border-bottom: 0; }
    .cp-table tbody tr:hover td { background: color-mix(in srgb, var(--cp-primary) 3%, transparent); }

    .cp-num { font-variant-numeric: tabular-nums; white-space: nowrap; }
    .cp-strong { font-weight: 700; }

    /* ═══════════════ الشارات ═══════════════ */

    .cp-badge {
        display: inline-flex; align-items: center; gap: var(--cp-1);
        padding: .15rem .55rem; border-radius: 999px;
        font-size: .74rem; font-weight: 700; white-space: nowrap;
    }

    .cp-badge--success { background: color-mix(in srgb, var(--cp-success) 14%, transparent); color: var(--cp-success); }
    .cp-badge--danger  { background: color-mix(in srgb, var(--cp-danger) 14%, transparent);  color: var(--cp-danger); }
    .cp-badge--warning { background: color-mix(in srgb, var(--cp-warning) 16%, transparent); color: var(--cp-warning); }
    .cp-badge--muted   { background: color-mix(in srgb, var(--cp-muted) 12%, transparent);   color: var(--cp-muted); }

    /* ═══════════════ الأزرار ═══════════════ */

    .cp-btn {
        display: inline-flex; align-items: center; justify-content: center; gap: var(--cp-2);
        padding: .5rem 1rem; border-radius: var(--cp-r);
        font-size: .85rem; font-weight: 600; font-family: inherit;
        border: 1px solid transparent; cursor: pointer;
        text-decoration: none; transition: filter .15s, background .15s;
    }

    .cp-btn:hover { text-decoration: none; filter: brightness(.96); }

    /* نصُّ الزرّ محسوبٌ على اللون الأساسي في «مظهر النظام» لا أبيضَ ثابتاً:
       منشأةٌ لونُها فاتحٌ كان نصُّ أزرارها يختفي. */
    .cp-btn--primary { background: var(--cp-primary); color: var(--cp-on-primary); }
    .cp-btn--primary:hover { color: var(--cp-on-primary); }

    .cp-btn--ghost {
        background: transparent; color: var(--cp-muted);
        border-color: var(--cp-line);
    }

    .cp-btn--ghost:hover { color: var(--cp-primary); border-color: var(--cp-primary); }
    .cp-btn--sm { padding: .3rem .7rem; font-size: .78rem; }

    /* ═══════════════ الحقول ═══════════════ */

    .cp-grid { display: grid; gap: var(--cp-4); grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); }

    .cp-field label {
        display: block; font-size: .8rem; font-weight: 600;
        color: var(--cp-muted); margin-bottom: var(--cp-1);
    }

    .cp-field input, .cp-field textarea, .cp-field select {
        width: 100%; padding: .55rem .75rem;
        border: 1px solid var(--cp-line); border-radius: var(--cp-r);
        background: var(--cp-surface); color: var(--cp-text);
        font-family: inherit; font-size: .88rem;
    }

    .cp-field input:focus, .cp-field textarea:focus, .cp-field select:focus {
        outline: none; border-color: var(--cp-primary);
        box-shadow: 0 0 0 3px var(--cp-primary-soft);
    }

    .cp-field input[readonly], .cp-field input:disabled {
        background: color-mix(in srgb, var(--cp-text) 4%, transparent);
        color: var(--cp-muted); cursor: not-allowed;
    }

    .cp-field__hint { display: block; font-size: .74rem; color: var(--cp-muted); margin-top: var(--cp-1); }

    /* ═══════════════ التنبيهات ═══════════════ */

    .cp-alert {
        display: flex; align-items: flex-start; gap: var(--cp-3);
        padding: var(--cp-4) var(--cp-5); border-radius: var(--cp-r);
        margin-bottom: var(--cp-4); font-size: .87rem;
        border-inline-start: 4px solid;
    }

    /* التنبيهاتُ مشتقّةٌ من ألوان المظهر: سطحٌ ممزوجٌ بلونها ونصٌّ داكنٌ
       منها — لا ثلاثةُ ألوانٍ مكتوبةٍ لا علاقة لها بألوان المنشأة. */
    .cp-alert--success {
        background: color-mix(in srgb, var(--cp-success) 10%, var(--cp-surface));
        border-color: var(--cp-success);
        color: color-mix(in srgb, var(--cp-success) 72%, #000);
    }
    .cp-alert--danger {
        background: color-mix(in srgb, var(--cp-danger) 10%, var(--cp-surface));
        border-color: var(--cp-danger);
        color: color-mix(in srgb, var(--cp-danger) 72%, #000);
    }
    .cp-alert--info {
        background: color-mix(in srgb, var(--cp-info) 10%, var(--cp-surface));
        border-color: var(--cp-info);
        color: color-mix(in srgb, var(--cp-info) 72%, #000);
    }

    /* ═══════════════ الحالة الفارغة ═══════════════ */

    .cp-empty { padding: var(--cp-6) var(--cp-5); text-align: center; color: var(--cp-muted); }
    .cp-empty i { font-size: 2rem; opacity: .35; display: block; margin-bottom: var(--cp-3); }
    .cp-empty p { margin: 0; font-size: .88rem; }

    /* ═══════════════ الهيكل: شريطٌ جانبيّ ثابت ومحتوى ═══════════════

       كان التنقّل شريطَ تبويباتٍ أفقيّاً فوق المحتوى؛ صار شريطاً جانبياً
       (طلب المستخدم 2026-09-19 بتصميمٍ مرفق). المصدرُ لم يتغيّر: البنودُ
       من `ClientPortalPermissions::navItems()` — أي من المفاتيح المفعَّلة
       وحدها، فلا يظهر بندٌ يردّ صاحبَه. */

    .cp__wrap { display: flex; min-height: 100vh; }

    .cp-side {
        flex: none; width: var(--cp-side-w);
        background: var(--cp-nav-bg);
        color: var(--cp-nav-text);
        display: flex; flex-direction: column;
        position: sticky; top: 0; align-self: flex-start; height: 100vh;
        z-index: 40;
    }

    .cp-side__brand {
        display: flex; align-items: center; gap: var(--cp-3);
        padding: var(--cp-5) var(--cp-4);
        border-bottom: 1px solid color-mix(in srgb, var(--cp-nav-text) 12%, transparent);
    }

    .cp-side__logo {
        flex: none; width: 40px; height: 40px; border-radius: .7rem;
        display: flex; align-items: center; justify-content: center;
        background: var(--cp-primary); color: var(--cp-on-primary); font-size: 1.05rem;
    }

    .cp-side__name { margin: 0; font-size: .98rem; font-weight: 700; line-height: 1.2; }
    .cp-side__tag { margin: 0; font-size: .72rem; opacity: .65; }

    .cp-side__nav { flex: 1 1 auto; overflow-y: auto; padding: var(--cp-4) var(--cp-3); }

    .cp-side__item {
        display: flex; align-items: center; gap: var(--cp-3);
        padding: .5rem var(--cp-3); margin-bottom: .1rem;
        border-radius: var(--cp-r);
        color: color-mix(in srgb, var(--cp-nav-text) 78%, transparent); text-decoration: none;
        font-size: .875rem; font-weight: 600;
        transition: background .15s, color .15s;
    }

    .cp-side__item i { width: 1.05rem; text-align: center; font-size: .88rem; }
    .cp-side__item:hover {
        background: color-mix(in srgb, var(--cp-nav-text) 10%, transparent);
        color: var(--cp-nav-text); text-decoration: none;
    }

    /* البندُ النشط باللون الأساسي لا بأبيضَ ثابت: الأبيضُ يختفي على شريطٍ
       فاتح، واللونُ الأساسيُّ يُقرأ على الشريطين معاً بنصِّه المحسوب. */
    .cp-side__item.is-active {
        background: var(--cp-primary); color: var(--cp-on-primary);
        box-shadow: var(--cp-e1);
    }

    .cp-side__foot { padding: var(--cp-3); border-top: 1px solid color-mix(in srgb, var(--cp-nav-text) 12%, transparent); }

    .cp-side__out {
        display: flex; align-items: center; gap: var(--cp-3); width: 100%;
        padding: .65rem var(--cp-3); border: 0; border-radius: var(--cp-r);
        background: transparent; color: color-mix(in srgb, var(--cp-nav-text) 75%, transparent);
        font-family: inherit; font-size: .85rem; font-weight: 600; cursor: pointer;
    }

    .cp-side__out:hover {
        background: color-mix(in srgb, var(--cp-nav-text) 10%, transparent);
        color: var(--cp-nav-text);
    }

    .cp__main { flex: 1 1 auto; min-width: 0; display: flex; flex-direction: column; }

    /* ═══════════════ الشريط العلوي ═══════════════ */

    .cp-top {
        background: var(--cp-surface);
        border-bottom: 1px solid var(--cp-line);
        position: sticky; top: 0; z-index: 30;
    }

    .cp-top__inner {
        padding: var(--cp-3) var(--cp-5);
        display: flex; align-items: center; gap: var(--cp-3);
    }

    .cp-burger {
        display: none; flex: none;
        width: 38px; height: 38px; border-radius: var(--cp-r);
        border: 1px solid var(--cp-line); background: var(--cp-surface);
        color: var(--cp-text); font-size: 1rem; cursor: pointer;
    }

    .cp-search { flex: 1 1 auto; max-width: 420px; position: relative; }

    .cp-search input {
        width: 100%; padding: .55rem 2.25rem .55rem .9rem;
        border: 1px solid var(--cp-line); border-radius: 2rem;
        background: var(--cp-bg); color: var(--cp-text);
        font-family: inherit; font-size: .84rem;
    }

    .cp-search input:focus { outline: none; border-color: var(--cp-primary); background: var(--cp-surface); }

    .cp-search i {
        position: absolute; inset-inline-start: .85rem; top: 50%; transform: translateY(-50%);
        color: var(--cp-muted); font-size: .85rem; pointer-events: none;
    }

    .cp-top__tools { display: flex; align-items: center; gap: var(--cp-2); margin-inline-start: auto; }

    .cp-iconbtn {
        position: relative; width: 38px; height: 38px; border-radius: 50%;
        display: inline-flex; align-items: center; justify-content: center;
        border: 1px solid var(--cp-line); background: var(--cp-surface);
        color: var(--cp-muted); font-size: .9rem; cursor: pointer; text-decoration: none;
    }

    .cp-iconbtn:hover { color: var(--cp-primary); border-color: var(--cp-primary); text-decoration: none; }

    .cp-iconbtn__dot {
        position: absolute; top: -2px; inset-inline-end: -2px;
        min-width: 17px; height: 17px; padding: 0 4px; border-radius: 9px;
        background: var(--cp-danger); color: var(--cp-surface);
        font-size: .65rem; font-weight: 700; line-height: 17px; text-align: center;
    }

    .cp-who { display: flex; align-items: center; gap: var(--cp-2); padding-inline-start: var(--cp-2); }

    .cp-who__avatar {
        width: 36px; height: 36px; border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        background: linear-gradient(135deg, var(--cp-primary), var(--cp-nav-bg));
        color: var(--cp-on-primary); font-weight: 700; font-size: .95rem;
    }

    .cp-who__hi { margin: 0; font-size: .68rem; color: var(--cp-muted); line-height: 1.2; }
    .cp-who__name {
        margin: 0; font-size: .82rem; font-weight: 600; line-height: 1.3;
        max-width: 150px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
    }

    /* مبدّلُ اللغة */
    .cp-lang { position: relative; }

    .cp-lang__menu {
        position: absolute; top: calc(100% + .4rem); inset-inline-end: 0; z-index: 40;
        min-width: 130px; padding: .3rem;
        background: var(--cp-surface); border: 1px solid var(--cp-line);
        border-radius: var(--cp-r); box-shadow: var(--cp-e2);
    }

    .cp-lang__item {
        display: block; padding: .4rem .6rem; border-radius: calc(var(--cp-r) - 2px);
        font-size: .82rem; text-decoration: none; color: var(--cp-text);
    }

    .cp-lang__item:hover { background: var(--cp-bg); text-decoration: none; }
    .cp-lang__item.is-active { color: var(--cp-primary); font-weight: 600; }

    .cp__shell { flex: 1 1 auto; max-width: 1240px; width: 100%; margin: 0 auto; padding: 0 var(--cp-5) var(--cp-6); }

    /* ═══════════════ بطاقة الترحيب ═══════════════ */

    .cp-hero {
        margin: var(--cp-5) 0 var(--cp-4);
        padding: var(--cp-5) var(--cp-6);
        border-radius: var(--cp-r-lg);
        /* كحليُّ الشريط كالتصميم المرفق، ونصُّه هو النصُّ المحسوب لذلك
           اللون في «مظهر النظام» — فلا يُكتب فاتحٌ على فاتح. */
        background: linear-gradient(135deg, var(--cp-nav-bg), color-mix(in srgb, var(--cp-nav-bg) 62%, var(--cp-primary)));
        color: var(--cp-nav-text);
        display: flex; align-items: center; gap: var(--cp-5); flex-wrap: wrap;
        box-shadow: var(--cp-e2);
    }

    .cp-hero__avatar {
        flex: none; width: 52px; height: 52px; border-radius: .8rem;
        display: flex; align-items: center; justify-content: center;
        background: color-mix(in srgb, var(--cp-nav-text) 16%, transparent); font-size: 1.6rem;
    }

    .cp-hero__body { flex: 1 1 260px; min-width: 0; }
    .cp-hero__name { margin: 0; font-size: 1.1rem; font-weight: 700; }
    .cp-hero__meta { margin: var(--cp-2) 0 0; font-size: .8rem; opacity: .85; display: flex; gap: var(--cp-4); flex-wrap: wrap; }
    .cp-hero__welcome { flex: 1 1 240px; }
    .cp-hero__welcome h2 { margin: 0; font-size: 1.05rem; font-weight: 700; }
    .cp-hero__welcome p { margin: var(--cp-2) 0 0; font-size: .82rem; opacity: .8; line-height: 1.7; }

    .cp-chip {
        display: inline-flex; align-items: center; gap: .35rem;
        padding: .2rem .6rem; border-radius: 1rem;
        background: color-mix(in srgb, var(--cp-nav-text) 18%, transparent); font-size: .72rem; font-weight: 600;
    }

    /* ═══════════════ الصفوف والقوائم ═══════════════ */

    .cp-cols { display: grid; gap: var(--cp-4); grid-template-columns: repeat(auto-fit, minmax(330px, 1fr)); }

    .cp-list { display: flex; flex-direction: column; }

    .cp-list__item {
        display: flex; align-items: center; gap: var(--cp-3);
        padding: var(--cp-3) var(--cp-5);
        border-bottom: 1px solid var(--cp-line);
        text-decoration: none; color: inherit;
    }

    .cp-list__item:last-child { border-bottom: 0; }
    .cp-list__item:hover { background: color-mix(in srgb, var(--cp-primary) 3%, transparent); text-decoration: none; }

    .cp-list__icon {
        flex: none; width: 36px; height: 36px; border-radius: .6rem;
        display: flex; align-items: center; justify-content: center;
        background: var(--cp-primary-soft); color: var(--cp-primary); font-size: .9rem;
    }

    .cp-list__body { flex: 1 1 auto; min-width: 0; }
    .cp-list__title { margin: 0; font-size: .85rem; font-weight: 600; }
    .cp-list__sub { margin: .15rem 0 0; font-size: .74rem; color: var(--cp-muted); }
    .cp-list__side { flex: none; text-align: end; font-size: .85rem; font-weight: 700; font-variant-numeric: tabular-nums; }

    /* ═══════════════ ملخّص الحساب ═══════════════ */

    .cp-figures { display: grid; gap: var(--cp-3); }

    .cp-figure {
        display: flex; align-items: center; gap: var(--cp-3);
        padding: var(--cp-4);
        border-radius: var(--cp-r);
        background: var(--cp-bg);
    }

    .cp-figure--primary { background: color-mix(in srgb, var(--cp-primary) 8%, transparent); }
    .cp-figure--success { background: color-mix(in srgb, var(--cp-success) 9%, transparent); }
    .cp-figure--danger  { background: color-mix(in srgb, var(--cp-danger) 8%, transparent); }

    .cp-figure__icon {
        flex: none; width: 38px; height: 38px; border-radius: .6rem;
        display: flex; align-items: center; justify-content: center;
        background: var(--cp-surface); color: var(--cp-primary); font-size: .9rem;
        box-shadow: var(--cp-e1);
    }

    .cp-figure__label { margin: 0; font-size: .76rem; color: var(--cp-muted); }
    .cp-figure__value { margin: .1rem 0 0; font-size: 1.05rem; font-weight: 700; font-variant-numeric: tabular-nums; }


    /* ═══════════════ بطاقاتُ الوصول السريع — على قياس التصميم المرفق ═══════════════

       دائرةٌ ملوّنة ثمّ عنوانٌ ووصف، وسهمٌ في الحافّة المنتهية. اللونُ لكلّ
       بطاقةٍ من دورها لا من ترتيبها: المالُ أخضر، والمرتجع أحمر، والموعد
       بنفسجيّ — فيتعرّف العميل على البطاقة قبل أن يقرأها. */

    .cp-tile {
        position: relative;
        padding-inline-end: 2.2rem;
    }

    .cp-tile__go {
        position: absolute; inset-inline-end: .9rem; top: 50%; transform: translateY(-50%);
        color: var(--cp-muted); font-size: .8rem; transition: transform .15s;
    }

    .cp-tile:hover .cp-tile__go { transform: translateY(-50%) translateX(-3px); }

    .cp-tile__icon--success { background: color-mix(in srgb, var(--cp-success) 14%, transparent); color: var(--cp-success); }
    .cp-tile__icon--danger  { background: color-mix(in srgb, var(--cp-danger) 14%, transparent);  color: var(--cp-danger); }
    .cp-tile__icon--warning { background: color-mix(in srgb, var(--cp-warning) 16%, transparent); color: var(--cp-warning); }
    .cp-tile__icon--info    { background: color-mix(in srgb, var(--cp-info) 14%, transparent);    color: var(--cp-info); }

    /* ═══════════════ بطاقة الرصيد الداكنة ═══════════════ */

    .cp-balance {
        border-radius: var(--cp-r-lg);
        background: linear-gradient(140deg, var(--cp-nav-bg), color-mix(in srgb, var(--cp-nav-bg) 78%, #000));
        color: var(--cp-nav-text);
        padding: var(--cp-5);
        box-shadow: var(--cp-e2);
        display: flex; flex-direction: column; justify-content: space-between; gap: var(--cp-4);
        min-height: 100%;
    }

    .cp-balance__head { display: flex; align-items: center; gap: var(--cp-2); font-weight: 700; }
    .cp-balance__head i { opacity: .85; }
    .cp-balance__label { margin: 0; font-size: .78rem; opacity: .75; }

    .cp-balance__value {
        margin: .2rem 0 0; font-size: 1.6rem; font-weight: 700;
        font-variant-numeric: tabular-nums; line-height: 1.2;
    }

    .cp-balance__tag {
        display: inline-flex; align-items: center; gap: .3rem;
        font-size: .74rem; opacity: .8;
    }

    .cp-balance__split { display: grid; grid-template-columns: 1fr 1fr; gap: var(--cp-3); }

    .cp-balance__box {
        background: color-mix(in srgb, var(--cp-nav-text) 10%, transparent);
        border-radius: var(--cp-r);
        padding: var(--cp-3);
        display: flex; align-items: center; justify-content: space-between; gap: var(--cp-2);
    }

    .cp-balance__box p { margin: 0; }
    .cp-balance__box .v { font-size: .95rem; font-weight: 700; font-variant-numeric: tabular-nums; }
    .cp-balance__box .l { font-size: .7rem; opacity: .75; }

    .cp-balance__arrow {
        flex: none; width: 28px; height: 28px; border-radius: 50%;
        display: flex; align-items: center; justify-content: center; font-size: .7rem;
    }

    .cp-balance__arrow--up { background: color-mix(in srgb, var(--cp-success) 30%, transparent); }
    .cp-balance__arrow--down { background: color-mix(in srgb, var(--cp-danger) 32%, transparent); }

    /* ═══════════════ بطاقة الدعوة إلى الحجز ═══════════════ */

    .cp-promo {
        border-radius: var(--cp-r-lg);
        background: color-mix(in srgb, var(--cp-primary) 8%, var(--cp-surface));
        border: 1px solid color-mix(in srgb, var(--cp-primary) 18%, transparent);
        padding: var(--cp-5);
        display: flex; align-items: center; gap: var(--cp-4); flex-wrap: wrap;
    }

    .cp-promo__icon {
        flex: none; width: 54px; height: 54px; border-radius: var(--cp-r);
        display: flex; align-items: center; justify-content: center;
        background: color-mix(in srgb, var(--cp-primary) 16%, transparent);
        color: var(--cp-primary); font-size: 1.4rem;
    }

    .cp-promo__body { flex: 1 1 180px; min-width: 0; }
    .cp-promo__title { margin: 0; font-size: .95rem; font-weight: 700; }
    .cp-promo__desc { margin: .2rem 0 0; font-size: .78rem; color: var(--cp-muted); line-height: 1.7; }

    /* ═══════════════ شريط القيم في الذيل ═══════════════ */

    .cp-foot {
        margin-top: var(--cp-5);
        border-radius: var(--cp-r-lg);
        background: var(--cp-surface);
        border: 1px solid var(--cp-line);
        padding: var(--cp-4) var(--cp-5);
        display: flex; align-items: center; gap: var(--cp-5); flex-wrap: wrap;
    }

    .cp-foot__brand { display: flex; align-items: center; gap: var(--cp-2); font-weight: 700; font-size: .9rem; }
    .cp-foot__brand i { color: var(--cp-primary); }
    .cp-foot__items { display: flex; gap: var(--cp-5); flex-wrap: wrap; margin-inline-start: auto; }
    .cp-foot__item { display: flex; align-items: center; gap: var(--cp-3); }

    .cp-foot__icon {
        width: 34px; height: 34px; border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        background: var(--cp-bg); color: var(--cp-primary); font-size: .85rem;
    }

    .cp-foot__t { margin: 0; font-size: .82rem; font-weight: 600; }
    .cp-foot__s { margin: 0; font-size: .72rem; color: var(--cp-muted); }

    /* ═══════════════ شبكاتُ الصفحة الرئيسية ═══════════════ */

    /* عمودان بنسبة الثلث/الثلثين كالتصميم، وينهاران إلى عمودٍ على الهاتف */
    .cp-grid-2 { display: grid; gap: var(--cp-4); grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); }
    .cp-grid-3 { display: grid; gap: var(--cp-4); grid-template-columns: minmax(0, 1.05fr) minmax(0, 1.25fr) minmax(0, 1fr); }

    @media (max-width: 1100px) {
        .cp-grid-3 { grid-template-columns: minmax(0, 1fr); }
    }

    @media (max-width: 760px) {
        .cp-grid-2 { grid-template-columns: minmax(0, 1fr); }
        .cp-balance__split { grid-template-columns: 1fr; }
        .cp-foot__items { margin-inline-start: 0; }
    }

    /* ═══════════════ الهاتف ═══════════════ */

    @media (max-width: 980px) {
        .cp-side {
            position: fixed; inset-block: 0; inset-inline-start: 0;
            transform: translateX(105%);
            transition: transform .2s ease;
        }
        [dir="ltr"] .cp-side { transform: translateX(-105%); }
        .cp.is-nav-open .cp-side { transform: none; }
        .cp-burger { display: inline-flex; align-items: center; justify-content: center; }
        .cp__shell { padding-inline: var(--cp-4); }
    }

    @media (max-width: 640px) {
        .cp-head { padding: var(--cp-5) 0 var(--cp-4); }
        .cp-head__title { font-size: 1.15rem; }
        .cp-card__body, .cp-table tbody td, .cp-table thead th { padding-inline: var(--cp-4); }
        .cp-stat { padding: var(--cp-4); }
        .cp-stat__value { font-size: 1.25rem; }
        .cp-search { display: none; }
        .cp-hero { padding: var(--cp-4); }
        .cp-who__name { display: none; }
    }

    /* ═══════════════ الطباعة ═══════════════ */

    @media print {
        .cp-top, .cp-side, .cp-noprint { display: none !important; }
        .cp { background: #fff; }
        .cp__wrap { display: block; }
        .cp-card { box-shadow: none; border-color: #ddd; break-inside: avoid; }
        .cp-hero { background: none; color: #000; box-shadow: none; }
    }
</style>
