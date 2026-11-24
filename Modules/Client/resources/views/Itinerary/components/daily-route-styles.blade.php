<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">
<style>
    /*
        «خط سيري اليومي». كلُّ صنفٍ تحت `#daily-route` فلا يمسّ الشريط الجانبي
        ولا الرأس. الألوان نفسُ لوحة شاشة التخطيط (`route-builder-styles`).
    */
    #daily-route {
        --dr-primary: var(--app-primary, #2563eb);
        --dr-primary-soft: color-mix(in srgb, var(--dr-primary) 12%, #fff);
        --dr-success: #16a34a;
        --dr-success-soft: #dcfce7;
        --dr-danger: #e11d48;
        --dr-danger-soft: #ffe4e6;
        --dr-orange: #f59e0b;
        --dr-orange-soft: #fef3c7;
        --dr-purple: #7c3aed;
        --dr-purple-soft: #ede9fe;
        --dr-grey: #64748b;
        --dr-grey-soft: #f1f5f9;
        --dr-text: #1e293b;
        --dr-muted: #64748b;
        --dr-border: #e5e9f2;
        --dr-bg: #f3f6fb;
        --dr-radius: 14px;
        font-family: var(--app-font-family, 'Cairo', system-ui, sans-serif);
        color: var(--dr-text);
        background: var(--dr-bg);
        padding: 1rem;
        border-radius: 18px;
    }

    #daily-route *,
    #daily-route *::before,
    #daily-route *::after { box-sizing: border-box; }
    #daily-route [hidden] { display: none !important; }
    #daily-route h1, #daily-route h2, #daily-route h3, #daily-route h4, #daily-route p { margin: 0; }

    #daily-route .dr-card { background: #fff; border: 1px solid var(--dr-border); border-radius: var(--dr-radius); box-shadow: 0 1px 2px rgba(15, 23, 42, .04); }

    /* ───── الأزرار ───── */
    #daily-route .dr-btn { display: inline-flex; align-items: center; justify-content: center; gap: .45rem; border: 1px solid transparent; border-radius: 10px; padding: .6rem 1rem; font: inherit; font-weight: 700; font-size: .88rem; cursor: pointer; text-decoration: none; line-height: 1.2; white-space: nowrap; transition: filter .15s, background-color .15s; }
    #daily-route .dr-btn--primary { background: var(--dr-primary); color: #fff; }
    #daily-route .dr-btn--success { background: var(--dr-success); color: #fff; }
    #daily-route .dr-btn--primary:hover, #daily-route .dr-btn--success:hover { color: #fff; filter: brightness(.94); }
    #daily-route .dr-btn--light { background: #fff; border-color: var(--dr-border); color: var(--dr-text); }
    #daily-route .dr-btn--light:hover { background: #f8fafc; color: var(--dr-text); }
    #daily-route .dr-btn--danger-light { background: #fff1f2; border-color: #fecdd3; color: #be123c; }
    #daily-route .dr-btn--danger-light:hover { color: #be123c; filter: brightness(.97); }
    #daily-route .dr-btn--block { width: 100%; }
    #daily-route .dr-btn--lg { padding: .8rem 1rem; font-size: 1rem; border-radius: 12px; }
    #daily-route .dr-btn[disabled] { opacity: .55; cursor: not-allowed; }

    /* ───── الصفّ العلوي ───── */
    #daily-route .dr-top { display: grid; grid-template-columns: minmax(280px, 1.7fr) repeat(4, minmax(0, 1fr)); gap: 1rem; margin-bottom: 1rem; align-items: stretch; }
    #daily-route .dr-title { padding: .75rem 1rem; display: flex; flex-direction: column; justify-content: center; gap: .5rem; min-width: 0; }
    #daily-route .dr-title__row { display: flex; align-items: center; justify-content: space-between; gap: .75rem; min-width: 0; }
    #daily-route .dr-title__text { min-width: 0; }
    #daily-route .dr-title h1 { font-size: 1.15rem; font-weight: 800; color: var(--dr-text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    #daily-route .dr-title p { color: var(--dr-muted); font-size: .82rem; margin-top: .1rem; white-space: nowrap; }
    #daily-route .dr-title__icon { width: 40px; height: 40px; border-radius: 12px; background: var(--dr-primary-soft); color: var(--dr-primary); display: grid; place-items: center; font-size: 1.15rem; flex: none; }
    #daily-route .dr-title__controls { display: flex; gap: .5rem; flex-wrap: nowrap; min-width: 0; }
    #daily-route .dr-title__controls .dr-control:has(input[type="date"]) { flex: 0 0 138px; }

    /* القائمة تأخذ عرض أطول اسمٍ فيها ما لم يُصفَّر حدُّها الأدنى — فتلتفّ البطاقة وتطول */
    #daily-route .dr-control { position: relative; display: inline-flex; align-items: center; gap: .4rem; border: 1px solid var(--dr-border); border-radius: 10px; background: #fff; min-height: 34px; padding: 0 .6rem; margin: 0; flex: 1 1 0; min-width: 0; }
    #daily-route .dr-control > i { color: var(--dr-muted); font-size: .8rem; }
    #daily-route .dr-control select,
    #daily-route .dr-control input { border: 0; background: transparent; font: inherit; font-size: .85rem; color: var(--dr-text); outline: none; width: 100%; min-width: 0; height: 34px; }
    #daily-route .dr-control:focus-within { border-color: var(--dr-primary); box-shadow: 0 0 0 3px var(--dr-primary-soft); }
    #daily-route .dr-control--wide { display: flex; min-height: 42px; }

    #daily-route .dr-stat { display: flex; align-items: center; justify-content: space-between; gap: .75rem; padding: 1rem 1.1rem; }
    #daily-route .dr-stat__label { display: block; font-size: .82rem; color: var(--dr-muted); font-weight: 600; }
    #daily-route .dr-stat strong { display: block; font-size: 1.35rem; font-weight: 800; line-height: 1.4; white-space: nowrap; }
    #daily-route .dr-stat__icon { width: 46px; height: 46px; border-radius: 50%; display: grid; place-items: center; font-size: 1.1rem; flex: none; }
    #daily-route .dr-stat__icon--green { background: var(--dr-success-soft); color: var(--dr-success); }
    #daily-route .dr-stat__icon--blue { background: #dbeafe; color: #1d4ed8; }
    #daily-route .dr-stat__icon--orange { background: var(--dr-orange-soft); color: #d97706; }
    #daily-route .dr-ring { --p: 0; width: 60px; height: 60px; border-radius: 50%; display: grid; place-items: center; flex: none; background: conic-gradient(var(--dr-success) calc(var(--p) * 1%), #e5e7eb 0); }
    #daily-route .dr-ring > span { width: 46px; height: 46px; border-radius: 50%; background: #fff; display: grid; place-items: center; font-weight: 800; font-size: .9rem; }

    #daily-route .dr-alert { padding: .7rem 1rem; border-radius: 12px; margin-bottom: 1rem; font-weight: 600; background: #fff1f2; border: 1px solid #fecdd3; color: #9f1239; }

    /* ───── الأعمدة الثلاثة ───── */
    #daily-route .dr-main { display: grid; grid-template-columns: 310px minmax(0, 1fr) 310px; gap: 1rem; height: max(640px, calc(100vh - 250px)); }
    #daily-route .dr-main > .dr-card { display: flex; flex-direction: column; min-height: 0; overflow: hidden; }
    #daily-route .dr-panel-head { display: flex; align-items: center; justify-content: space-between; gap: .5rem; padding: .85rem 1rem; border-bottom: 1px solid var(--dr-border); }
    #daily-route .dr-panel-head h2 { font-size: 1rem; font-weight: 800; }
    #daily-route .dr-count { min-width: 26px; height: 26px; border-radius: 999px; background: var(--dr-grey-soft); color: var(--dr-muted); font-size: .8rem; font-weight: 700; display: inline-grid; place-items: center; padding: 0 .4rem; }
    #daily-route .dr-seq { width: 30px; height: 30px; border-radius: 50%; background: var(--dr-primary); color: #fff; font-weight: 800; display: grid; place-items: center; }

    #daily-route .dr-empty { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: .5rem; padding: 2rem 1rem; color: var(--dr-muted); text-align: center; font-size: .88rem; }
    #daily-route .dr-empty > i { font-size: 1.8rem; color: #cbd5e1; }

    /* ───── قائمة الزيارات ───── */
    #daily-route .dr-stops { list-style: none; margin: 0; padding: .35rem .5rem; overflow-y: auto; flex: 1; }
    #daily-route .dr-stop { position: relative; display: flex; align-items: flex-start; gap: .6rem; padding: .7rem .5rem; border-radius: 12px; cursor: pointer; }
    #daily-route .dr-stop:hover { background: #f8fafc; }
    #daily-route .dr-stop.is-selected { background: var(--dr-primary-soft); }
    #daily-route .dr-stop:not(:last-child)::after { content: ''; position: absolute; inset-inline-start: calc(.5rem + 14px); top: calc(.7rem + 30px); bottom: calc(-.7rem + 2px); border-inline-start: 2px dashed #dbe2ec; }
    #daily-route .dr-stop__num { position: relative; z-index: 1; width: 30px; height: 30px; border-radius: 50%; display: grid; place-items: center; font-weight: 800; font-size: .85rem; color: #fff; background: var(--dr-grey); flex: none; }
    #daily-route .dr-stop__body { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: .15rem; }
    #daily-route .dr-stop__name { font-weight: 800; font-size: .9rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    #daily-route .dr-stop__line { font-size: .76rem; color: var(--dr-muted); display: flex; align-items: center; gap: .3rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    #daily-route .dr-stop__line > i { width: 12px; text-align: center; color: #94a3b8; }
    #daily-route .dr-stop__aside { display: flex; flex-direction: column; align-items: flex-end; gap: .3rem; flex: none; }

    #daily-route .dr-state { display: inline-block; font-size: .7rem; font-weight: 700; padding: .15rem .55rem; border-radius: 999px; white-space: nowrap; }
    #daily-route .dr-state--done { background: var(--dr-success-soft); color: #15803d; }
    #daily-route .dr-state--next,
    #daily-route .dr-state--active { background: #dbeafe; color: #1d4ed8; }
    #daily-route .dr-state--planned { background: var(--dr-grey-soft); color: var(--dr-grey); }
    #daily-route .dr-state--late { background: var(--dr-danger-soft); color: #be123c; }
    #daily-route .dr-state--cancelled { background: var(--dr-grey-soft); color: #94a3b8; text-decoration: line-through; }

    #daily-route .dr-stop--done .dr-stop__num { background: var(--dr-success); }
    #daily-route .dr-stop--next .dr-stop__num,
    #daily-route .dr-stop--active .dr-stop__num { background: var(--dr-primary); box-shadow: 0 0 0 4px var(--dr-primary-soft); }
    #daily-route .dr-stop--late .dr-stop__num { background: var(--dr-danger); }
    #daily-route .dr-stop--cancelled .dr-stop__num { background: #cbd5e1; }
    #daily-route .dr-stop--cancelled .dr-stop__name { color: #94a3b8; }

    /* من أضاف العميل */
    #daily-route .dr-by { display: inline-flex; align-items: center; gap: .3rem; font-size: .72rem; font-weight: 700; padding: .15rem .5rem; border-radius: 8px; width: fit-content; max-width: 100%; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-top: .15rem; }
    #daily-route .dr-by--self { background: var(--dr-orange-soft); color: #b45309; }
    #daily-route .dr-by--other { background: var(--dr-purple-soft); color: #6d28d9; }

    /* خارج بطاقة القائمة قد تُصغّر قواعدُ القالب عرضَ هذا الشريط حين تُفتح القائمة الجانبية؛ نثبّت أبعاده */
    #daily-route .dr-list__foot { flex: none; width: auto; margin: 0 !important; position: static; padding: .75rem; border-top: 1px solid var(--dr-border); }
    #daily-route .dr-list__foot .dr-btn { max-width: 100%; }

    /* ───── الوسط: الخريطة والجدول ───── */
    #daily-route .dr-center { position: relative; }
    #daily-route .dr-tabs { position: absolute; top: .75rem; left: 50%; transform: translateX(-50%); z-index: 500; display: inline-flex; background: #fff; border: 1px solid var(--dr-border); border-radius: 10px; padding: 3px; box-shadow: 0 2px 8px rgba(15, 23, 42, .08); }
    #daily-route .dr-tab { border: 0; background: transparent; color: var(--dr-text); border-radius: 8px; padding: .4rem 1.4rem; font: inherit; font-weight: 700; font-size: .85rem; cursor: pointer; }
    #daily-route .dr-tab.is-active { background: var(--dr-primary); color: #fff; }
    #daily-route .dr-view { position: relative; flex: 1; min-height: 0; }
    #daily-route .dr-map,
    #daily-route .dr-map-notice { position: absolute; inset: 0; direction: ltr; }
    #daily-route .dr-map { background: #e2e8f0; z-index: 1; }
    #daily-route .dr-map-notice { display: grid; place-items: center; text-align: center; padding: 2rem; color: var(--dr-muted); direction: rtl; z-index: 2; background: repeating-linear-gradient(45deg, #f8fafc, #f8fafc 10px, #f1f5f9 10px, #f1f5f9 20px); }
    #daily-route .dr-map-btn { position: absolute; z-index: 500; border: 1px solid var(--dr-border); background: #fff; color: var(--dr-text); border-radius: 10px; font: inherit; font-size: .8rem; font-weight: 700; cursor: pointer; box-shadow: 0 2px 6px rgba(15, 23, 42, .12); display: inline-flex; align-items: center; gap: .35rem; }
    #daily-route .dr-map-btn--corner { top: .75rem; right: .75rem; padding: .45rem .7rem; }
    #daily-route .dr-map-btn--locate { bottom: 1rem; left: 1rem; width: 40px; height: 40px; justify-content: center; border-radius: 50%; font-size: 1rem; }
    #daily-route .dr-map-chip { position: absolute; z-index: 500; bottom: 1rem; right: 1rem; background: #fff; border: 1px solid var(--dr-border); border-radius: 999px; padding: .3rem .75rem; font-size: .75rem; color: var(--dr-muted); }
    #daily-route .dr-view--map:fullscreen { background: #fff; }

    #daily-route .leaflet-control-zoom { margin-top: 3.5rem; }
    #daily-route .dr-pin { width: 32px; height: 32px; border-radius: 50%; display: grid; place-items: center; color: #fff; font-weight: 800; font-size: .85rem; border: 3px solid #fff; box-shadow: 0 2px 6px rgba(15, 23, 42, .35); position: relative; font-family: var(--app-font-family, 'Cairo', system-ui, sans-serif); }
    #daily-route .dr-pin--done { background: var(--dr-success); }
    #daily-route .dr-pin--next,
    #daily-route .dr-pin--active { background: var(--dr-primary); }
    #daily-route .dr-pin--planned { background: var(--dr-grey); }
    #daily-route .dr-pin--late { background: var(--dr-danger); }
    #daily-route .dr-pin--cancelled { background: #cbd5e1; }
    #daily-route .dr-pin.is-selected { transform: scale(1.18); box-shadow: 0 0 0 5px color-mix(in srgb, var(--dr-primary) 30%, transparent); }
    #daily-route .dr-pin__by { position: absolute; top: -5px; right: -5px; width: 13px; height: 13px; border-radius: 50%; border: 2px solid #fff; }
    #daily-route .dr-pin__by--self { background: var(--dr-orange); }
    #daily-route .dr-pin__by--other { background: var(--dr-purple); }
    #daily-route .dr-pin-label { font-family: var(--app-font-family, 'Cairo', system-ui, sans-serif); font-weight: 700; font-size: .75rem; padding: 2px 8px; border-radius: 8px; border: 0; box-shadow: 0 1px 4px rgba(15, 23, 42, .2); direction: rtl; }
    #daily-route .dr-me { width: 16px; height: 16px; border-radius: 50%; background: #0ea5e9; border: 3px solid #fff; box-shadow: 0 0 0 6px rgba(14, 165, 233, .25); }

    #daily-route .dr-view--table { padding: 3.75rem .75rem .75rem; overflow: hidden; display: flex; }
    #daily-route .dr-table-wrap { overflow: auto; flex: 1; border: 1px solid var(--dr-border); border-radius: 12px; }
    #daily-route .dr-table { width: 100%; border-collapse: collapse; font-size: .85rem; margin: 0; }
    #daily-route .dr-table th { position: sticky; top: 0; background: #f8fafc; color: var(--dr-muted); font-weight: 700; padding: .65rem .6rem; text-align: right; white-space: nowrap; border-bottom: 1px solid var(--dr-border); }
    #daily-route .dr-table td { padding: .6rem; border-bottom: 1px solid #eef2f7; vertical-align: middle; white-space: nowrap; }
    #daily-route .dr-table tbody tr { cursor: pointer; }
    #daily-route .dr-table tbody tr:hover { background: #fafcff; }
    #daily-route .dr-table tbody tr.is-selected { background: var(--dr-primary-soft); }
    #daily-route .dr-table small { color: var(--dr-muted); display: block; }

    #daily-route .dr-legend { display: flex; flex-wrap: wrap; justify-content: center; gap: .35rem 1.1rem; padding: .6rem 1rem; border-top: 1px solid var(--dr-border); font-size: .76rem; color: var(--dr-muted); background: #fff; }
    #daily-route .dr-legend span { display: inline-flex; align-items: center; gap: .35rem; }
    #daily-route .dr-dot { width: 10px; height: 10px; border-radius: 50%; display: inline-block; }
    #daily-route .dr-dot--done { background: var(--dr-success); }
    #daily-route .dr-dot--next { background: var(--dr-primary); }
    #daily-route .dr-dot--planned { background: var(--dr-grey); }
    #daily-route .dr-dot--late { background: var(--dr-danger); }
    #daily-route .dr-dot--self { background: var(--dr-orange); }
    #daily-route .dr-dot--other { background: var(--dr-purple); }

    /* ───── العميل التالي ───── */
    #daily-route .dr-next__body { padding: .9rem 1rem 1rem; overflow-y: auto; flex: 1; display: flex; flex-direction: column; gap: .8rem; }
    #daily-route .dr-next__card { display: flex; gap: .75rem; align-items: flex-start; }
    #daily-route .dr-next__info { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: .15rem; }
    #daily-route .dr-next__info h3 { font-size: 1.05rem; font-weight: 800; line-height: 1.35; }
    #daily-route .dr-code { font-size: .78rem; color: var(--dr-muted); direction: ltr; text-align: right; }
    #daily-route .dr-client-status { font-size: .78rem; font-weight: 700; color: var(--dr-success); }
    #daily-route .dr-facts { list-style: none; margin: .35rem 0 0; padding: 0; display: flex; flex-direction: column; gap: .25rem; font-size: .8rem; color: var(--dr-muted); }
    #daily-route .dr-facts li { display: flex; align-items: center; gap: .4rem; }
    #daily-route .dr-facts i { width: 14px; text-align: center; color: #94a3b8; }
    #daily-route .dr-next__photo { width: 96px; height: 104px; flex: none; border-radius: 12px; overflow: hidden; background: var(--dr-grey-soft); display: grid; place-items: center; color: #cbd5e1; font-size: 2rem; }
    #daily-route .dr-next__photo a { display: block; width: 100%; height: 100%; }
    #daily-route .dr-next__photo img { width: 100%; height: 100%; object-fit: cover; display: block; }

    #daily-route .dr-quick { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: .5rem; }
    #daily-route .dr-quick__btn { display: flex; flex-direction: column; align-items: center; gap: .3rem; padding: .6rem .3rem; border: 1px solid var(--dr-border); border-radius: 12px; background: #fff; color: var(--dr-text); font-size: .76rem; font-weight: 700; text-decoration: none; cursor: pointer; text-align: center; }
    #daily-route .dr-quick__btn i { color: var(--dr-primary); font-size: 1rem; }
    #daily-route .dr-quick__btn:hover { border-color: var(--dr-primary); color: var(--dr-text); }
    #daily-route .dr-quick__btn.is-disabled { opacity: .45; pointer-events: none; }

    #daily-route .dr-section { border-top: 1px solid var(--dr-border); padding-top: .75rem; }
    #daily-route .dr-section h4 { font-size: .88rem; font-weight: 800; display: flex; align-items: center; gap: .4rem; margin-bottom: .45rem; }
    #daily-route .dr-section h4 i { color: var(--dr-muted); }
    #daily-route .dr-section__head { display: flex; align-items: center; justify-content: space-between; }
    #daily-route .dr-note { font-size: .82rem; color: #475569; line-height: 1.7; white-space: pre-line; }
    #daily-route .dr-note small { display: block; color: #94a3b8; margin-top: .2rem; }
    #daily-route .dr-gallery__nav { display: inline-flex; gap: .25rem; margin-bottom: .45rem; }
    #daily-route .dr-gallery__nav button { width: 26px; height: 26px; border-radius: 8px; border: 1px solid var(--dr-border); background: #fff; color: var(--dr-muted); cursor: pointer; font-size: .7rem; }
    #daily-route .dr-gallery { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: .45rem; }
    #daily-route .dr-gallery a { display: block; aspect-ratio: 1; border-radius: 10px; overflow: hidden; background: var(--dr-grey-soft); }
    #daily-route .dr-gallery img { width: 100%; height: 100%; object-fit: cover; display: block; }
    #daily-route .dr-gallery__empty { grid-column: 1 / -1; font-size: .8rem; color: var(--dr-muted); }

    /* ───── نافذة الإضافة ───── */
    #daily-route .dr-modal { position: fixed; inset: 0; background: rgba(15, 23, 42, .45); display: grid; place-items: center; z-index: 1080; padding: 1rem; }
    #daily-route .dr-modal__dialog { background: #fff; border-radius: 16px; width: min(560px, 100%); padding: 1.1rem 1.25rem; display: flex; flex-direction: column; gap: .75rem; max-height: 90vh; }
    #daily-route .dr-modal__head { display: flex; align-items: center; justify-content: space-between; }
    #daily-route .dr-modal__head h3 { font-size: 1.1rem; font-weight: 800; display: flex; align-items: center; gap: .5rem; }
    #daily-route .dr-modal__head h3 i { color: var(--dr-primary); }
    #daily-route .dr-modal__close { border: 0; background: transparent; font-size: 1.6rem; line-height: 1; color: var(--dr-muted); cursor: pointer; }
    #daily-route .dr-modal__actions { display: flex; justify-content: flex-end; gap: .5rem; }
    #daily-route .dr-help { font-size: .8rem; color: var(--dr-muted); }
    #daily-route .dr-help strong { color: var(--dr-text); }
    #daily-route .dr-field { display: flex; flex-direction: column; gap: .3rem; margin: 0; }
    #daily-route .dr-field__label { font-size: .8rem; color: var(--dr-muted); font-weight: 600; }
    #daily-route .dr-search { position: relative; }
    #daily-route .dr-search input { width: 100%; height: 42px; border: 1px solid var(--dr-border); border-radius: 10px; padding-inline: .8rem 2.3rem; font: inherit; font-size: .88rem; }
    #daily-route .dr-search input:focus { border-color: var(--dr-primary); outline: none; box-shadow: 0 0 0 3px var(--dr-primary-soft); }
    #daily-route .dr-search i { position: absolute; inset-inline-end: .8rem; top: 50%; transform: translateY(-50%); color: var(--dr-muted); pointer-events: none; }
    #daily-route .dr-add-list { border: 1px solid var(--dr-border); border-radius: 12px; overflow-y: auto; min-height: 200px; max-height: 42vh; padding: .25rem; }
    #daily-route .dr-pick { display: flex; align-items: center; gap: .6rem; padding: .55rem .6rem; border-radius: 8px; cursor: pointer; margin: 0; }
    #daily-route .dr-pick + .dr-pick { border-top: 1px solid #f1f5f9; }
    #daily-route .dr-pick:hover { background: #f8fafc; }
    #daily-route .dr-pick input { width: 18px; height: 18px; accent-color: var(--dr-primary); flex: none; margin: 0; }
    #daily-route .dr-pick.is-in-route { opacity: .6; cursor: default; }
    #daily-route .dr-pick__body { flex: 1; min-width: 0; display: flex; flex-direction: column; }
    #daily-route .dr-pick__name { font-weight: 700; font-size: .88rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    #daily-route .dr-pick__meta { font-size: .74rem; color: var(--dr-muted); }
    #daily-route .dr-add-list .dr-empty { padding: 2.5rem 1rem; }

    @media (max-width: 1399.98px) {
        #daily-route .dr-main { grid-template-columns: 280px minmax(0, 1fr) 280px; }
        #daily-route .dr-stat { padding: .85rem .9rem; }
        #daily-route .dr-stat strong { font-size: 1.15rem; }
        #daily-route .dr-stat__icon { width: 40px; height: 40px; font-size: 1rem; }
    }

    @media (max-width: 1199.98px) {
        #daily-route .dr-top { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        #daily-route .dr-title { grid-column: 1 / -1; }
        #daily-route .dr-main { grid-template-columns: minmax(0, 1fr); height: auto; }
        #daily-route .dr-list .dr-stops { max-height: 420px; }
        #daily-route .dr-center { height: 520px; }
    }

    @media (max-width: 575.98px) {
        #daily-route { padding: .6rem; }
        #daily-route .dr-top { grid-template-columns: minmax(0, 1fr); }
    }
</style>
