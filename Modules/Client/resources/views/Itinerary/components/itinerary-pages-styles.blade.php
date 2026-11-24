<style>
    /* لوحة التحكم وملاحظات اليوم — إضافاتٌ على `route-builder-styles` و`itinerary-index-styles` */

    #route-builder .rb-grid-2 { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1.5fr); gap: 1rem; }
    #route-builder .rb-grid-2--even { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    #route-builder .rb-chart { direction: ltr; min-height: 290px; padding: .5rem 1rem 1rem; }

    #route-builder .rb-ranges { display: flex; flex-wrap: wrap; gap: .35rem; }
    #route-builder .rb-chip { font-size: .75rem; font-weight: 700; padding: .25rem .65rem; border-radius: 999px; border: 1px solid var(--rb-border); color: var(--rb-text); text-decoration: none; background: #fff; }
    #route-builder .rb-chip:hover { border-color: var(--rb-primary); color: var(--rb-primary); }
    #route-builder .rb-chip.is-active { background: var(--rb-primary); border-color: var(--rb-primary); color: #fff; }

    #route-builder .rb-dot { display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: #6366f1; flex: none; }
    #route-builder .rb-dot--completed { background: #16a34a; }
    #route-builder .rb-dot--started { background: #d97706; }
    #route-builder .rb-dot--missed { background: #e11d48; }
    #route-builder .rb-dot--cancelled { background: #94a3b8; }
    #route-builder .rb-dot--overdue { background: #f97316; }

    #route-builder .rb-timeline { padding: .75rem 1rem 1rem; display: flex; flex-direction: column; gap: .65rem; max-height: 440px; overflow: auto; }
    #route-builder .rb-timeline__rep { border: 1px solid var(--rb-border); border-radius: 12px; padding: .6rem .75rem; }
    #route-builder .rb-timeline__head { display: flex; justify-content: space-between; align-items: center; gap: .5rem; font-weight: 800; margin-bottom: .45rem; font-size: .88rem; }
    #route-builder .rb-timeline__head a { color: var(--rb-text); text-decoration: none; }
    #route-builder .rb-timeline__list { display: flex; flex-wrap: wrap; gap: .35rem; margin: 0; padding: 0; list-style: none; }
    #route-builder .rb-dot-item { display: inline-flex; align-items: center; gap: .3rem; font-size: .74rem; padding: .15rem .55rem; border-radius: 999px; background: #f8fafc; border: 1px solid var(--rb-border); }
    #route-builder .rb-dot-item small { color: var(--rb-muted); }

    #route-builder .rb-card .rb-table-wrap { max-height: 460px; }
    #route-builder .rb-num { text-align: center; }

    /* ملاحظات اليوم */
    #route-builder .rb-date-nav { display: flex; align-items: center; gap: .35rem; }
    #route-builder .rb-date-nav .rb-select { flex: 1; }
    #route-builder .rb-date-nav .rb-select input { padding-inline: .6rem; }
    #route-builder .rb-live { display: flex; align-items: center; gap: .45rem; font-size: .8rem; padding: .55rem .7rem; border-radius: 10px; background: #f8fafc; border: 1px solid var(--rb-border); color: var(--rb-muted); }
    #route-builder .rb-live.is-live { background: #f0fdf4; border-color: #bbf7d0; color: #15803d; }
    #route-builder .rb-live.is-stale { background: #fffbeb; border-color: #fde68a; color: #b45309; }

    #route-builder .rb-reps-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(230px, 1fr)); gap: .75rem; padding: 1rem; }
    #route-builder .rb-rep-card { display: flex; flex-direction: column; gap: .4rem; padding: .85rem 1rem; border: 1px solid var(--rb-border); border-radius: 12px; text-decoration: none; color: var(--rb-text); background: #fff; }
    #route-builder .rb-rep-card:hover { border-color: var(--rb-primary); box-shadow: 0 0 0 3px var(--rb-primary-soft); color: var(--rb-text); }
    #route-builder .rb-rep-card strong { font-size: .95rem; }
    #route-builder .rb-rep-card small { color: var(--rb-muted); }

    #route-builder .rb-today-body { padding: .75rem; }
    #route-builder .rb-today-body[data-view="list"] .rb-pane--map { display: none; }
    #route-builder .rb-today-body[data-view="map"] .rb-visit-list { display: none; }
    #route-builder .rb-pane--map .rb-map,
    #route-builder .rb-pane--map .rb-map-notice { min-height: 520px; }

    #route-builder .rb-visit-list { display: flex; flex-direction: column; gap: .75rem; }
    #route-builder .rb-visit-card { border: 1px solid var(--rb-border); border-inline-start: 4px solid #6366f1; border-radius: 14px; background: #fff; overflow: hidden; }
    #route-builder .rb-visit-card--completed { border-inline-start-color: #16a34a; }
    #route-builder .rb-visit-card--started { border-inline-start-color: #d97706; }
    #route-builder .rb-visit-card--missed { border-inline-start-color: #e11d48; }
    #route-builder .rb-visit-card--cancelled { border-inline-start-color: #94a3b8; }
    #route-builder .rb-visit-card__head { display: flex; align-items: flex-start; gap: .75rem; padding: .8rem 1rem; flex-wrap: wrap; }
    #route-builder .rb-visit-card__main { flex: 1; min-width: 220px; }
    #route-builder .rb-visit-card__title { display: flex; align-items: center; gap: .45rem; flex-wrap: wrap; font-weight: 800; font-size: .98rem; }
    #route-builder .rb-visit-card__title a { color: var(--rb-text); text-decoration: none; }
    #route-builder .rb-visit-card__title a:hover { color: var(--rb-primary); }
    #route-builder .rb-visit-card__meta { display: flex; flex-wrap: wrap; gap: .35rem .9rem; font-size: .78rem; color: var(--rb-muted); margin-top: .3rem; }
    #route-builder .rb-visit-card__side { display: flex; flex-wrap: wrap; justify-content: flex-end; align-items: center; gap: .4rem; }
    #route-builder .rb-balance { font-weight: 800; display: inline-flex; gap: .3rem; align-items: center; font-size: .9rem; }
    #route-builder .rb-visit-card__notes { border-top: 1px solid var(--rb-border); background: #fbfcfe; padding: .75rem 1rem; }
    #route-builder .rb-notes-head { display: flex; align-items: center; gap: .6rem; flex-wrap: wrap; margin-bottom: .5rem; font-size: .85rem; }
    #route-builder .rb-notes-head .rb-link { margin-inline-start: auto; font-size: .78rem; font-weight: 700; color: var(--rb-primary); text-decoration: none; background: none; border: 0; cursor: pointer; font-family: inherit; }

    #route-builder .rb-legend { display: flex; flex-wrap: wrap; gap: .5rem .9rem; font-size: .76rem; color: var(--rb-muted); }
    #route-builder .rb-legend span { display: inline-flex; align-items: center; gap: .3rem; }
    #route-builder .rb-print-only { display: none; }

    @media (max-width: 1199.98px) {
        #route-builder .rb-grid-2,
        #route-builder .rb-grid-2--even { grid-template-columns: minmax(0, 1fr); }
    }

    @media print {
        .main-menu, .header-navbar, .header-navbar-shadow, .footer, .customizer, .buy-now,
        #route-builder .rb-picker, #route-builder .rb-head__actions, #route-builder .rb-tabs,
        #route-builder .rb-pane--map, #route-builder .rb-daybar, #route-builder .rb-link { display: none !important; }
        html body .app-content, html body .content { margin: 0 !important; padding: 0 !important; }
        body { background: #fff !important; }
        #route-builder { background: #fff; padding: 0; }
        #route-builder .rb-layout { grid-template-columns: minmax(0, 1fr); }
        #route-builder .rb-today-body[data-view] .rb-visit-list { display: flex !important; }
        #route-builder .rb-visit-card { break-inside: avoid; }
        #route-builder .rb-print-only { display: block; }
    }
</style>
