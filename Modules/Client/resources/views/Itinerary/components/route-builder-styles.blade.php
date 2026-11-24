<style>
    /*
        شاشة تخطيط خط السير. كلُّ صنفٍ هنا تحت `#route-builder` فلا يمسّ
        الشريط الجانبي ولا الرأس. اللون الأساسي من لون النظام
        (`--app-primary`) مع بديلٍ ثابت.
    */
    #route-builder {
        --rb-primary: var(--app-primary, #2563eb);
        --rb-primary-soft: color-mix(in srgb, var(--rb-primary) 12%, #fff);
        --rb-success: #16a34a;
        --rb-danger: #e11d48;
        --rb-text: #1e293b;
        --rb-muted: #64748b;
        --rb-border: #e5e9f2;
        --rb-bg: #f3f6fb;
        --rb-radius: 14px;
        font-family: var(--app-font-family, 'Cairo', system-ui, sans-serif);
        color: var(--rb-text);
        background: var(--rb-bg);
        padding: 1rem;
        border-radius: 18px;
    }

    #route-builder *,
    #route-builder *::before,
    #route-builder *::after { box-sizing: border-box; }

    #route-builder [hidden] { display: none !important; }

    #route-builder .rb-head { display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap; margin-bottom: 1rem; }
    #route-builder .rb-head__title { display: flex; align-items: center; gap: .75rem; }
    #route-builder .rb-head__icon { width: 46px; height: 46px; border-radius: 12px; background: var(--rb-primary-soft); color: var(--rb-primary); display: grid; place-items: center; font-size: 1.3rem; }
    #route-builder .rb-head h1 { font-size: 1.35rem; font-weight: 800; margin: 0; color: var(--rb-text); }
    #route-builder .rb-head p { margin: .15rem 0 0; color: var(--rb-muted); font-size: .85rem; }
    #route-builder .rb-head__actions { display: flex; gap: .5rem; flex-wrap: wrap; }

    #route-builder .rb-btn { display: inline-flex; align-items: center; justify-content: center; gap: .45rem; border: 1px solid transparent; border-radius: 10px; padding: .55rem 1.1rem; font-weight: 700; font-size: .9rem; cursor: pointer; text-decoration: none; line-height: 1.2; white-space: nowrap; transition: filter .15s, background-color .15s; }
    #route-builder .rb-btn--light { background: #fff; border-color: var(--rb-border); color: var(--rb-text); }
    #route-builder .rb-btn--light:hover { background: #f8fafc; color: var(--rb-text); }
    #route-builder .rb-btn--primary { background: var(--rb-primary); color: #fff; }
    #route-builder .rb-btn--success { background: var(--rb-success); color: #fff; }
    #route-builder .rb-btn--primary:hover,
    #route-builder .rb-btn--success:hover { color: #fff; filter: brightness(.94); }
    #route-builder .rb-btn--block { width: 100%; }
    #route-builder .rb-btn[disabled],
    #route-builder .rb-btn.is-disabled { opacity: .55; cursor: not-allowed; pointer-events: none; }

    #route-builder .rb-alert { display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap; padding: .75rem 1rem; border-radius: 12px; margin-bottom: 1rem; font-weight: 600; background: #fff7ed; border: 1px solid #fed7aa; color: #9a3412; }
    #route-builder .rb-alert--danger { background: #fff1f2; border-color: #fecdd3; color: #9f1239; }

    #route-builder .rb-layout { display: grid; grid-template-columns: 290px minmax(0, 1fr); gap: 1rem; align-items: start; }
    #route-builder .rb-card { background: #fff; border: 1px solid var(--rb-border); border-radius: var(--rb-radius); box-shadow: 0 1px 2px rgba(15, 23, 42, .04); }

    #route-builder .rb-picker { padding: 1rem; display: flex; flex-direction: column; gap: .7rem; position: sticky; top: 1rem; }
    #route-builder .rb-picker__title { font-weight: 800; font-size: 1rem; display: flex; align-items: center; gap: .5rem; padding-bottom: .6rem; border-bottom: 1px solid var(--rb-border); }
    #route-builder .rb-picker__title i { color: var(--rb-primary); }

    #route-builder .rb-field { display: flex; flex-direction: column; gap: .3rem; margin: 0; min-width: 0; }
    #route-builder .rb-field__label { font-size: .8rem; color: var(--rb-muted); font-weight: 600; }
    #route-builder .rb-select { position: relative; display: flex; align-items: center; background: #fff; border: 1px solid var(--rb-border); border-radius: 10px; min-height: 42px; }
    #route-builder .rb-select > i { position: absolute; inset-inline-start: .8rem; color: var(--rb-muted); pointer-events: none; font-size: .9rem; }
    #route-builder .rb-select select,
    #route-builder .rb-select input { border: 0; background: transparent; width: 100%; height: 40px; padding-block: 0; padding-inline: 2.3rem .7rem; font: inherit; font-size: .9rem; color: var(--rb-text); outline: none; min-width: 0; }
    #route-builder .rb-select select:disabled { color: var(--rb-muted); cursor: not-allowed; }
    #route-builder .rb-select:focus-within { border-color: var(--rb-primary); box-shadow: 0 0 0 3px var(--rb-primary-soft); }
    #route-builder .rb-input { border: 1px solid var(--rb-border); border-radius: 10px; height: 42px; padding: 0 .8rem; font: inherit; font-size: .9rem; width: 100%; color: var(--rb-text); background: #fff; }
    #route-builder .rb-input:focus { border-color: var(--rb-primary); outline: none; box-shadow: 0 0 0 3px var(--rb-primary-soft); }
    #route-builder .rb-help,
    #route-builder .rb-note { font-size: .75rem; color: var(--rb-muted); margin: 0; }
    #route-builder .rb-pill { display: inline-flex; align-items: center; min-height: 42px; padding: 0 .9rem; border-radius: 10px; background: #fff7ed; color: #c2410c; font-weight: 700; font-size: .85rem; border: 1px solid #fed7aa; width: fit-content; }

    #route-builder .rb-search { position: relative; }
    #route-builder .rb-search input { width: 100%; height: 42px; border: 1px solid var(--rb-border); border-radius: 10px; padding-inline: .8rem 2.3rem; font: inherit; font-size: .88rem; }
    #route-builder .rb-search input:focus { border-color: var(--rb-primary); outline: none; box-shadow: 0 0 0 3px var(--rb-primary-soft); }
    #route-builder .rb-search i { position: absolute; inset-inline-end: .8rem; top: 50%; transform: translateY(-50%); color: var(--rb-muted); pointer-events: none; }

    #route-builder .rb-check { display: flex; align-items: center; gap: .5rem; margin: 0; font-size: .88rem; cursor: pointer; font-weight: 600; }
    #route-builder .rb-check input,
    #route-builder .rb-client input { width: 18px; height: 18px; accent-color: var(--rb-primary); flex: none; margin: 0; }

    #route-builder .rb-client-list { max-height: 340px; overflow-y: auto; border: 1px solid var(--rb-border); border-radius: 10px; padding: .25rem; }
    #route-builder .rb-client { display: flex; align-items: center; gap: .6rem; padding: .5rem .55rem; border-radius: 8px; cursor: pointer; margin: 0; }
    #route-builder .rb-client + .rb-client { border-top: 1px solid #f1f5f9; }
    #route-builder .rb-client:hover { background: #f8fafc; }
    #route-builder .rb-client.is-added { opacity: .65; cursor: default; }
    #route-builder .rb-client__body { flex: 1; min-width: 0; display: flex; flex-direction: column; }
    #route-builder .rb-client__name { font-weight: 700; font-size: .88rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    #route-builder .rb-client__code { font-size: .75rem; color: var(--rb-muted); }
    #route-builder .rb-client__icon { color: #94a3b8; flex: none; }

    #route-builder .rb-badge { display: inline-block; font-size: .68rem; font-weight: 700; padding: .05rem .45rem; border-radius: 999px; background: var(--rb-primary-soft); color: var(--rb-primary); vertical-align: middle; }
    #route-builder .rb-badge--warn { background: #fff7ed; color: #c2410c; }
    #route-builder .rb-empty { color: var(--rb-muted); text-align: center; padding: 1.25rem .5rem; font-size: .85rem; margin: 0; }

    #route-builder .rb-main { display: flex; flex-direction: column; gap: 1rem; min-width: 0; }
    #route-builder .rb-form { padding: 1rem; display: grid; grid-template-columns: 1.3fr .7fr 1.5fr 1fr; gap: .85rem; align-items: start; }
    #route-builder .rb-stat__hint { display: block; font-size: .72rem; color: var(--rb-muted); }
    #route-builder .rb-badge--muted { background: #f1f5f9; color: var(--rb-muted); }
    /* «من أضاف العميل» — نفسُ ألوان «خط سيري اليومي»: برتقاليٌّ للمندوب، بنفسجيٌّ لغيره */
    #route-builder .rb-src { display: inline-flex; align-items: center; gap: .3rem; font-size: .7rem; font-weight: 700; padding: .1rem .5rem; border-radius: 999px; white-space: nowrap; vertical-align: middle; }
    #route-builder .rb-src--self { background: #fef3c7; color: #b45309; }
    #route-builder .rb-src--other { background: #ede9fe; color: #6d28d9; }
    #route-builder .rb-client-link { color: inherit; text-decoration: none; }
    #route-builder .rb-client-link:hover { color: var(--rb-primary); text-decoration: underline; }
    /* لونُ العميل في يومه (مُضاف/من الخطة × زيارة) — DailyItineraryService::VISIT_TONES */
    #route-builder .rb-tone { display: inline-flex; align-items: center; gap: .3rem; font-size: .7rem; font-weight: 700; padding: .1rem .5rem; border-radius: 999px; white-space: nowrap; vertical-align: middle; color: var(--tone); background: #f8fafc; background: color-mix(in srgb, var(--tone) 12%, #fff); border: 1px solid var(--tone); }
    #route-builder .rb-tone::before { content: ''; width: 8px; height: 8px; border-radius: 50%; background: var(--tone); flex: none; }
    #route-builder tr.rb-tone-row > td:first-child { border-inline-start: 4px solid var(--tone); }
    #route-builder .rb-visit-card.rb-has-tone { border-inline-start-color: var(--tone); }
    #route-builder .rb-tone-legend { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: .5rem; margin: 0 0 .75rem; }
    #route-builder .rb-tone-legend:empty { display: none; }
    #route-builder .rb-tone-legend__item { display: flex; align-items: flex-start; gap: .5rem; padding: .5rem .7rem; border: 1px solid var(--rb-border); border-inline-start: 4px solid var(--tone); border-radius: 10px; background: #fff; }
    #route-builder .rb-tone-legend__item strong { display: block; font-size: .8rem; color: var(--tone); }
    #route-builder .rb-tone-legend__item small { display: block; font-size: .72rem; line-height: 1.4; color: var(--rb-muted); }
    #route-builder .rb-tone-legend__count { margin-inline-start: auto; font-size: 1.1rem; font-weight: 800; color: var(--tone); }

    #route-builder .rb-daybar { display: flex; align-items: center; justify-content: space-between; gap: .75rem; flex-wrap: wrap; padding: .6rem .75rem; }
    #route-builder .rb-days { display: flex; gap: .4rem; flex-wrap: wrap; }
    #route-builder .rb-day { border: 1px solid var(--rb-border); background: #fff; border-radius: 10px; padding: .45rem .8rem; font-weight: 700; font-size: .88rem; cursor: pointer; display: inline-flex; align-items: center; gap: .4rem; font-family: inherit; color: var(--rb-text); }
    #route-builder .rb-day:hover { border-color: var(--rb-primary); }
    #route-builder .rb-day i { font-size: .75rem; color: #be123c; }
    #route-builder .rb-day__count { min-width: 22px; height: 22px; border-radius: 999px; background: #f1f5f9; color: var(--rb-muted); font-size: .75rem; display: inline-grid; place-items: center; padding: 0 .35rem; }
    #route-builder .rb-day.has-clients .rb-day__count { background: var(--rb-primary-soft); color: var(--rb-primary); }
    #route-builder .rb-day.is-active { background: var(--rb-primary); border-color: var(--rb-primary); color: #fff; }
    #route-builder .rb-day.is-active i { color: #fff; }
    #route-builder .rb-day.is-active .rb-day__count { background: rgba(255, 255, 255, .25); color: #fff; }
    #route-builder .rb-day-actions { display: flex; gap: .4rem; flex-wrap: wrap; }
    #route-builder .rb-btn--sm { padding: .42rem .75rem; font-size: .82rem; }
    #route-builder .rb-btn--danger-light { background: #fff1f2; border-color: #fecdd3; color: #be123c; }
    #route-builder .rb-btn--danger-light:hover { color: #be123c; filter: brightness(.97); }
    #route-builder .rb-icon[disabled] { opacity: .35; cursor: not-allowed; }

    /* حالةُ العميل بلون «حالات العملاء» — `--rb-status` يُضبط لكل شارة */
    #route-builder .rb-status { --rb-status: #64748b; display: inline-flex; align-items: center; gap: .3rem; font-size: .75rem; font-weight: 700; padding: .12rem .55rem; border-radius: 999px; color: var(--rb-status); background: color-mix(in srgb, var(--rb-status) 13%, #fff); border: 1px solid color-mix(in srgb, var(--rb-status) 30%, #fff); white-space: nowrap; vertical-align: middle; }
    #route-builder .rb-status::before { content: ''; width: 6px; height: 6px; border-radius: 50%; background: var(--rb-status); }
    #route-builder .rb-client__name .rb-status { font-size: .68rem; padding: 0 .4rem; }
    #route-builder .rb-muted { color: var(--rb-muted); }

    #route-builder .rb-stats { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 1rem; }
    #route-builder .rb-stat { display: flex; align-items: center; justify-content: space-between; gap: .75rem; padding: 1rem 1.1rem; background: #fff; border: 1px solid var(--rb-border); border-radius: var(--rb-radius); }
    #route-builder .rb-stat__label { display: block; font-size: .82rem; color: var(--rb-muted); font-weight: 600; }
    #route-builder .rb-stat strong { font-size: 1.4rem; font-weight: 800; line-height: 1.35; }
    #route-builder .rb-stat__icon { width: 48px; height: 48px; border-radius: 50%; display: grid; place-items: center; font-size: 1.15rem; flex: none; }
    #route-builder .rb-stat--green .rb-stat__icon { background: #dcfce7; color: #15803d; }
    #route-builder .rb-stat--blue .rb-stat__icon { background: #dbeafe; color: #1d4ed8; }
    #route-builder .rb-stat--red .rb-stat__icon { background: #ffe4e6; color: #e11d48; }
    #route-builder .rb-stat--navy .rb-stat__icon { background: #e0e7ff; color: #1e3a8a; }

    #route-builder .rb-work { overflow: hidden; }
    #route-builder .rb-tabs { display: flex; gap: .5rem; padding: .75rem .75rem 0; justify-content: flex-end; }
    #route-builder .rb-tab { border: 1px solid var(--rb-border); background: #f8fafc; color: var(--rb-text); border-radius: 10px; padding: .6rem 1.4rem; font-weight: 700; cursor: pointer; display: inline-flex; gap: .45rem; align-items: center; font-family: inherit; }
    #route-builder .rb-tab.is-active { background: var(--rb-primary); border-color: var(--rb-primary); color: #fff; }
    #route-builder .rb-work__body { display: grid; grid-template-columns: minmax(0, 1.15fr) minmax(0, 1fr); gap: 1rem; padding: .75rem; }
    #route-builder .rb-work__body[data-view="map"] { grid-template-columns: minmax(0, 1fr); }
    #route-builder .rb-work__body[data-view="map"] .rb-pane--table { display: none; }

    #route-builder .rb-pane { display: flex; flex-direction: column; min-width: 0; border: 1px solid var(--rb-border); border-radius: 12px; overflow: hidden; background: #fff; }
    #route-builder .rb-table-wrap { overflow: auto; max-height: 520px; flex: 1; }
    #route-builder .rb-table { width: 100%; border-collapse: collapse; font-size: .87rem; margin: 0; }
    #route-builder .rb-table th { position: sticky; top: 0; background: #f8fafc; color: var(--rb-muted); font-weight: 700; padding: .7rem .6rem; text-align: right; white-space: nowrap; border-bottom: 1px solid var(--rb-border); z-index: 1; }
    #route-builder .rb-table td { padding: .6rem; border-bottom: 1px solid #eef2f7; vertical-align: middle; white-space: nowrap; }
    #route-builder .rb-table tbody tr:hover { background: #fafcff; }
    #route-builder .rb-table tr.sortable-ghost { background: var(--rb-primary-soft); }
    #route-builder .rb-order-cell { display: flex; align-items: center; gap: .45rem; }
    #route-builder .rb-grip { color: #94a3b8; cursor: grab; padding: .15rem; }
    #route-builder .rb-grip.is-off { cursor: default; opacity: .35; }
    #route-builder .rb-order { width: 28px; height: 28px; border-radius: 8px; border: 1px solid var(--rb-border); display: grid; place-items: center; font-weight: 700; background: #fff; }
    #route-builder .rb-client-cell strong { display: block; font-weight: 700; }
    #route-builder .rb-client-cell small { color: var(--rb-muted); }
    #route-builder .rb-icon { border: 0; background: transparent; width: 30px; height: 30px; border-radius: 8px; cursor: pointer; display: inline-grid; place-items: center; }
    #route-builder .rb-icon:hover { background: #f1f5f9; }
    #route-builder .rb-icon--edit { color: var(--rb-primary); }
    #route-builder .rb-icon--delete { color: var(--rb-danger); }

    #route-builder .rb-pane__foot { display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap; padding: .75rem 1rem; background: #f8fafc; border-top: 1px solid var(--rb-border); font-size: .87rem; }
    #route-builder .rb-pane__foot > span > i { color: var(--rb-muted); margin-inline-end: .3rem; }
    #route-builder .rb-hint { margin: 0; padding: 0 1rem .7rem; font-size: .78rem; color: var(--rb-muted); text-align: center; background: #f8fafc; }

    #route-builder .rb-map,
    #route-builder .rb-map-notice { min-height: 430px; flex: 1; }
    #route-builder .rb-map { background: #e2e8f0; }
    #route-builder .rb-map-notice { display: grid; place-items: center; text-align: center; padding: 2rem; color: var(--rb-muted); background: repeating-linear-gradient(45deg, #f8fafc, #f8fafc 10px, #f1f5f9 10px, #f1f5f9 20px); }
    #route-builder .rb-map-notice code { direction: ltr; unicode-bidi: embed; }
    #route-builder .rb-metric { display: flex; align-items: center; gap: .6rem; }
    #route-builder .rb-metric > i { font-size: 1.1rem; color: var(--rb-muted); }
    #route-builder .rb-metric span { display: block; font-size: .75rem; color: var(--rb-muted); }
    #route-builder .rb-metric strong { font-size: 1.05rem; }

    #route-builder .rb-modal { position: fixed; inset: 0; background: rgba(15, 23, 42, .45); display: grid; place-items: center; z-index: 1080; padding: 1rem; }
    #route-builder .rb-modal__dialog { background: #fff; border-radius: 16px; width: min(460px, 100%); padding: 1.25rem; display: flex; flex-direction: column; gap: .8rem; max-height: 90vh; overflow: auto; }
    #route-builder .rb-modal__dialog h3 { margin: 0; font-size: 1.1rem; font-weight: 800; }
    #route-builder .rb-modal__actions { display: flex; gap: .5rem; }
    #route-builder .rb-copy-list { display: flex; flex-direction: column; gap: .45rem; }
    #route-builder .rb-source { display: flex; justify-content: space-between; align-items: center; gap: .75rem; width: 100%; text-align: right; border: 1px solid var(--rb-border); background: #fff; border-radius: 10px; padding: .65rem .8rem; cursor: pointer; font: inherit; color: var(--rb-text); }
    #route-builder .rb-source:hover { border-color: var(--rb-primary); background: var(--rb-primary-soft); }
    #route-builder .rb-source small { color: var(--rb-muted); }
    #route-builder .rb-source-group { font-size: .8rem; font-weight: 700; color: var(--rb-muted); margin: .4rem 0 0; }

    @media (max-width: 1399.98px) {
        #route-builder .rb-form { grid-template-columns: repeat(3, minmax(0, 1fr)); }
    }

    @media (max-width: 1199.98px) {
        #route-builder .rb-layout { grid-template-columns: minmax(0, 1fr); }
        #route-builder .rb-picker { position: static; }
        #route-builder .rb-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        #route-builder .rb-work__body { grid-template-columns: minmax(0, 1fr); }
        #route-builder .rb-work__body[data-view="split"] .rb-pane--map { display: none; }
    }

    @media (max-width: 575.98px) {
        #route-builder .rb-form,
        #route-builder .rb-stats { grid-template-columns: minmax(0, 1fr); }
        #route-builder .rb-head__actions { width: 100%; }
        #route-builder .rb-head__actions .rb-btn { flex: 1; }
    }
</style>
