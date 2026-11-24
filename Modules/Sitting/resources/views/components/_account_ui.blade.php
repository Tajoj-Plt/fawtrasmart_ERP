{{-- نظام بصري واحد لشاشتَي الحساب والاشتراك، مبنيّ على بنفسجي فوترة.

     كانت شاشة الحساب تحمل نظامين متنازعين: متغيّرات `--primary: #7367f0`
     مع `.premium-card` من القالب الأصلي، وبطاقات `.st-*` رمادية أضفتُها
     فوقها. نظامان على صفحة واحدة يقرأان كأن أحدهما دخيل. --}}

<style>
    :root {
        --ax-brand: #7367f0;
        --ax-brand-dark: #5e50ee;
        --ax-brand-soft: #f0eefe;
        --ax-brand-line: #ddd8fb;
        --ax-ink: #2f2c40;
        --ax-ink-2: #6e6b82;
        --ax-ink-3: #a3a0b3;
        --ax-line: #e9e7f2;
        --ax-bg: #fbfbfe;
        --ax-ok: #28c76f;
        --ax-warn: #ff9f43;
        --ax-danger: #ea5455;
        --ax-shadow: 0 1px 2px rgba(47, 44, 64, .04), 0 8px 24px rgba(47, 44, 64, .05);
    }

    /* ══ الهيكل: محتوى + قائمة أقسام جانبية ══ */
    .ax-shell { display: grid; grid-template-columns: 250px 1fr; gap: 1.25rem; align-items: start; }
    @media (max-width: 991px) { .ax-shell { grid-template-columns: 1fr; } }

    .ax-nav {
        background: #fff; border: 1px solid var(--ax-line); border-radius: 14px;
        padding: .5rem; box-shadow: var(--ax-shadow); position: sticky; top: 1rem;
    }
    @media (max-width: 991px) {
        .ax-nav { position: static; display: flex; overflow-x: auto; gap: .3rem; }
    }
    .ax-nav-item {
        display: flex; align-items: center; gap: .65rem; width: 100%;
        padding: .62rem .8rem; border: 0; background: none; border-radius: 10px;
        color: var(--ax-ink-2); font: inherit; font-size: .91rem; font-weight: 600;
        text-align: right; cursor: pointer; white-space: nowrap;
        transition: background .14s, color .14s;
    }
    .ax-nav-item:hover { background: var(--ax-bg); color: var(--ax-ink); }
    .ax-nav-item.active { background: var(--ax-brand-soft); color: var(--ax-brand-dark); }
    .ax-nav-item i { font-size: 1rem; flex: 0 0 auto; }
    .ax-nav-item .tick { margin-right: auto; font-size: .7rem; color: var(--ax-brand); opacity: 0; }
    .ax-nav-item.active .tick { opacity: 1; }
    .ax-nav-item:focus-visible { outline: 2px solid var(--ax-brand); outline-offset: 2px; }
    .ax-nav-sep { height: 1px; background: var(--ax-line); margin: .4rem .6rem; }

    /* ══ اللوحة ══ */
    .ax-panel {
        background: #fff; border: 1px solid var(--ax-line); border-radius: 14px;
        box-shadow: var(--ax-shadow); overflow: hidden; margin-bottom: 1rem;
    }
    /* `.head` مرادف لـ`<header>` — الشاشات التي تستعمل `div` بدل الوسم
       الدلالي تأخذ النمط نفسه بلا إعادة كتابة قوالبها */
    .ax-panel > header,
    .ax-panel > .head {
        padding: 1rem 1.3rem; border-bottom: 1px solid var(--ax-line);
        display: flex; align-items: center; gap: .7rem; flex-wrap: wrap;
    }
    .ax-panel > .head h4 { margin: 0; font-size: 1.02rem; font-weight: 700; color: var(--ax-ink); }
    .ax-panel > .head .count {
        font-size: .74rem; font-weight: 700; color: var(--ax-brand-dark);
        background: var(--ax-brand-soft); border-radius: 20px; padding: .1rem .55rem;
    }
    .ax-panel > .head .spacer { margin-right: auto; }
    .ax-panel > header .ic,
    .ax-panel > .head .ic {
        width: 34px; height: 34px; border-radius: 9px; flex: 0 0 auto;
        background: var(--ax-brand-soft); color: var(--ax-brand);
        display: grid; place-items: center;
    }
    .ax-panel > header h3 { margin: 0; font-size: 1.02rem; font-weight: 700; color: var(--ax-ink); }
    .ax-panel > header p { margin: .1rem 0 0; font-size: .8rem; color: var(--ax-ink-3); width: 100%; }
    .ax-panel > .body { padding: 1.3rem; }
    .ax-panel > .foot {
        padding: .9rem 1.3rem; border-top: 1px solid var(--ax-line);
        background: var(--ax-bg); display: flex; align-items: center; gap: .7rem;
    }
    .ax-panel > .foot .note { font-size: .8rem; color: var(--ax-ink-3); margin-left: auto; }

    /* ══ الحقول ══ */
    .ax-field { margin-bottom: 1.05rem; }
    .ax-field > label {
        display: block; font-size: .84rem; font-weight: 600;
        color: var(--ax-ink-2); margin-bottom: .35rem;
    }
    .ax-field > label .req { color: var(--ax-danger); }
    .ax-field .hint { font-size: .76rem; color: var(--ax-ink-3); margin-top: .3rem; }
    .ax-control { position: relative; }
    .ax-control > i {
        position: absolute; left: .8rem; top: 50%; transform: translateY(-50%);
        color: var(--ax-ink-3); font-size: .95rem; pointer-events: none;
    }
    .ax-input {
        width: 100%; border: 1px solid #dcd9ea; border-radius: 9px;
        padding: .58rem .85rem; padding-left: 2.3rem; font-size: .92rem;
        color: var(--ax-ink); background: #fff; transition: border-color .14s, box-shadow .14s;
    }
    .ax-input:focus {
        outline: 0; border-color: var(--ax-brand);
        box-shadow: 0 0 0 3px rgba(115, 103, 240, .13);
    }
    .ax-input:disabled, .ax-input[readonly] { background: var(--ax-bg); color: var(--ax-ink-3); }
    .ax-input.is-invalid { border-color: var(--ax-danger); }
    select.ax-input { appearance: none; padding-left: 2.3rem; cursor: pointer; }
    textarea.ax-input { padding-left: .85rem; min-height: 86px; resize: vertical; }
    .ax-err { font-size: .78rem; color: var(--ax-danger); margin-top: .3rem; }

    /* ══ مفتاح تشغيل ══ */
    /* `position:relative` يحبس المدخلات المطلقة داخل المفتاح نفسه — بدونه
       تُحسَب نسبةً إلى أقرب سلف موضَّع فتخرج عن حدوده */
    .ax-switch { position: relative; display: flex; align-items: center; gap: .7rem; margin: 0; cursor: pointer; padding: .6rem 0; }
    .ax-switch + .ax-switch { border-top: 1px solid var(--ax-line); }
    .ax-switch input { position: absolute; opacity: 0; width: 0; height: 0; }
    .ax-switch .track {
        position: relative; flex: 0 0 auto; width: 42px; height: 23px;
        background: #d7d4e4; border-radius: 999px; transition: background .18s;
    }
    .ax-switch .track::after {
        content: ''; position: absolute; top: 3px; right: 3px; width: 17px; height: 17px;
        background: #fff; border-radius: 50%; transition: transform .18s;
        box-shadow: 0 1px 3px rgba(0, 0, 0, .25);
    }
    /* `~` لا `+`: ترتيب العناصر داخل المفتاح هو
       input[hidden] → input[checkbox] → span.txt → span.track — أي أن
       الشقيق المباشر لمربّع الاختيار هو النصّ لا المسار. ومع `+` لم تنطبق
       القاعدة قطّ، فبقي المفتاح لا يتحرّك مهما ضُغط: مربّع الاختيار مخفيّ
       (`opacity:0`) والمرئيّ هو `.track` وحده */
    .ax-switch input:checked ~ .track { background: var(--ax-brand); }
    .ax-switch input:checked ~ .track::after { transform: translateX(-19px); }
    .ax-switch input:focus-visible ~ .track { outline: 2px solid var(--ax-brand); outline-offset: 2px; }
    .ax-switch .txt b { display: block; font-size: .91rem; color: var(--ax-ink); font-weight: 600; }
    .ax-switch .txt small { color: var(--ax-ink-3); font-size: .78rem; }
    .ax-switch .track { margin-right: auto; }

    /* ══ أزرار ══ */
    .ax-btn {
        display: inline-flex; align-items: center; gap: .45rem; border-radius: 9px;
        padding: .52rem 1.05rem; font-size: .89rem; font-weight: 600;
        border: 1px solid transparent; cursor: pointer; text-decoration: none;
        transition: background .14s, border-color .14s, color .14s;
    }
    .ax-btn-primary { background: var(--ax-brand); color: #fff; }
    .ax-btn-primary:hover { background: var(--ax-brand-dark); color: #fff; text-decoration: none; }
    .ax-btn-ghost { background: #fff; border-color: #dcd9ea; color: var(--ax-ink-2); }
    .ax-btn-ghost:hover { border-color: var(--ax-brand); color: var(--ax-brand); text-decoration: none; }
    .ax-btn:focus-visible { outline: 2px solid var(--ax-brand); outline-offset: 2px; }

    /* ══ رفع صورة ══ */
    .ax-upload {
        border: 1.5px dashed #d5d1ea; border-radius: 11px; padding: 1rem;
        text-align: center; background: var(--ax-bg); cursor: pointer; display: block;
        transition: border-color .14s, background .14s;
    }
    .ax-upload:hover { border-color: var(--ax-brand); background: var(--ax-brand-soft); }
    .ax-upload input { display: none; }
    .ax-upload img { max-height: 74px; max-width: 100%; border-radius: 7px; margin-bottom: .4rem; }
    .ax-upload .ph { color: var(--ax-ink-3); font-size: .82rem; }
    .ax-upload .ph i { display: block; font-size: 1.5rem; margin-bottom: .3rem; color: #c8c4dd; }

    /* ══ شارات ونصّ لاتيني ══ */
    .ax-badge {
        display: inline-block; font-size: .72rem; font-weight: 700;
        padding: .15rem .55rem; border-radius: 20px; white-space: nowrap;
    }
    .ax-badge.brand { background: var(--ax-brand-soft); color: var(--ax-brand-dark); }
    .ax-badge.ok { background: #e6f8ee; color: #1a8a4d; }
    .ax-badge.warn { background: #fff2e2; color: #a35f06; }
    .ax-badge.dang { background: #fdeaea; color: #b03232; }
    .ax-badge.off { background: #f1f0f6; color: #6e6b82; }
    .ax-mono {
        direction: ltr; display: inline-block; unicode-bidi: embed;
        font-family: Consolas, Monaco, 'Courier New', monospace; font-size: .8rem;
        background: var(--ax-bg); color: #4a4760; padding: .1rem .45rem;
        border-radius: 5px; border: 1px solid var(--ax-line);
    }

    /* ══ صفّ بيان ══ */
    .ax-def { display: flex; justify-content: space-between; gap: 1rem; padding: .6rem 0; border-bottom: 1px solid var(--ax-line); }
    .ax-def:last-child { border-bottom: 0; }
    .ax-def dt { margin: 0; font-size: .85rem; color: var(--ax-ink-2); font-weight: 500; }
    .ax-def dd { margin: 0; font-weight: 600; color: var(--ax-ink); text-align: left; }

    /* ══ تنبيه ══ */
    .ax-note { display: flex; gap: .8rem; align-items: flex-start; border-radius: 11px; padding: .9rem 1.1rem; margin-bottom: 1rem; border: 1px solid; }
    .ax-note.brand { background: var(--ax-brand-soft); border-color: var(--ax-brand-line); color: #453e8f; }
    .ax-note.warn { background: #fff6ea; border-color: #ffd9ac; color: #8a5308; }
    .ax-note.dang { background: #fdeeee; border-color: #f6c9c9; color: #932a2a; }
    .ax-note p { margin: .2rem 0 0; font-size: .87rem; }

    /* جداول الاشتراك */
    .ax-table { width: 100%; border-collapse: collapse; }
    .ax-table th, .ax-table td { padding: .7rem .95rem; text-align: right; border-bottom: 1px solid var(--ax-line); vertical-align: middle; }
    .ax-table th { background: var(--ax-bg); font-size: .78rem; font-weight: 700; color: var(--ax-ink-2); white-space: nowrap; }
    .ax-table tbody tr:last-child td { border-bottom: 0; }
    .ax-table tbody tr:hover { background: var(--ax-bg); }
    .ax-table td.num { font-variant-numeric: tabular-nums; }

    .ax-empty { text-align: center; padding: 2.4rem 1rem; color: var(--ax-ink-3); }
    .ax-empty i { font-size: 2.1rem; color: #d3cfe6; display: block; margin-bottom: .5rem; }

    /* بطاقات إحصاء */
    .ax-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(195px, 1fr)); gap: .85rem; margin-bottom: 1.25rem; }
    .ax-stat {
        background: #fff; border: 1px solid var(--ax-line); border-radius: 13px;
        padding: 1rem 1.1rem; display: flex; align-items: center; gap: .9rem; box-shadow: var(--ax-shadow);
    }
    .ax-stat .ic { flex: 0 0 auto; width: 44px; height: 44px; border-radius: 11px; display: grid; place-items: center; font-size: 1.15rem; background: var(--ax-brand-soft); color: var(--ax-brand); }
    .ax-stat .ic.success { background: #e6f8ee; color: #1a8a4d; }
    .ax-stat .ic.warning { background: #fff2e2; color: #a35f06; }
    .ax-stat .ic.danger  { background: #fdeaea; color: #b03232; }
    .ax-stat .val { font-size: 1.5rem; font-weight: 700; line-height: 1.1; color: var(--ax-ink); font-variant-numeric: tabular-nums; }
    .ax-stat .lbl { font-size: .8rem; color: var(--ax-ink-2); margin-top: .1rem; }

    .ax-hide { display: none !important; }
</style>
