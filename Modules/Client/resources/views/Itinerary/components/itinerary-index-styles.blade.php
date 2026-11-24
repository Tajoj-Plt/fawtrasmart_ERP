<style>
    /* شاشة عرض خطوط السير — إضافاتٌ على `route-builder-styles` (المتغيّرات والبطاقات والأزرار منه) */

    #route-builder .rb-filters { display: flex; flex-direction: column; gap: .7rem; }
    #route-builder .rb-filters__dates { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .5rem; }
    #route-builder .rb-filters__dates .rb-select input { padding-inline: .6rem; }

    #route-builder .rb-weeks { overflow-x: auto; flex-wrap: nowrap; padding-bottom: .15rem; max-width: 100%; }
    #route-builder .rb-week { text-decoration: none; white-space: nowrap; }
    #route-builder .rb-week__text { display: inline-flex; flex-direction: column; line-height: 1.15; text-align: right; }
    #route-builder .rb-week__text small { font-size: .7rem; font-weight: 600; opacity: .75; }
    #route-builder .rb-week:hover { color: var(--rb-text); }
    #route-builder .rb-week.is-active:hover { color: #fff; }
    #route-builder .rb-week-summary { display: flex; align-items: center; gap: .75rem; font-size: .85rem; color: var(--rb-muted); white-space: nowrap; }
    #route-builder .rb-week-summary strong { color: var(--rb-text); }

    #route-builder .rb-progress { display: inline-block; width: 90px; height: 7px; border-radius: 999px; background: #e2e8f0; overflow: hidden; vertical-align: middle; }
    #route-builder .rb-progress > span { display: block; height: 100%; border-radius: inherit; background: var(--rb-success); }
    #route-builder .rb-rate { display: inline-flex; align-items: center; gap: .4rem; }
    #route-builder .rb-rate .rb-progress { width: 64px; }

    #route-builder .rb-section-head { display: flex; align-items: center; justify-content: space-between; gap: .75rem; flex-wrap: wrap; padding: .85rem 1rem; border-bottom: 1px solid var(--rb-border); }
    #route-builder .rb-section-head h2 { margin: 0; font-size: 1rem; font-weight: 800; display: flex; align-items: center; gap: .5rem; flex-wrap: wrap; }
    #route-builder .rb-section-head h2 > i { color: var(--rb-primary); }
    #route-builder .rb-section-head h2 small { color: var(--rb-muted); font-weight: 600; font-size: .8rem; }

    #route-builder .rb-empty--block { padding: 2.5rem 1rem; display: flex; flex-direction: column; align-items: center; gap: .35rem; }
    #route-builder .rb-empty--block p { margin: 0; }

    #route-builder .rb-table-wrap--grid { max-height: none; }
    #route-builder .rb-grid th,
    #route-builder .rb-grid td { text-align: center; }
    #route-builder .rb-grid th:first-child,
    #route-builder .rb-grid td:first-child,
    #route-builder .rb-grid th:nth-child(2),
    #route-builder .rb-grid td:nth-child(2) { text-align: right; }
    #route-builder .rb-grid__day small { display: block; font-weight: 600; font-size: .7rem; }
    #route-builder .rb-grid .is-today { background: color-mix(in srgb, var(--rb-primary) 6%, #fff); }
    #route-builder .rb-grid th.is-today { color: var(--rb-primary); }
    #route-builder .rb-grid tfoot td { background: #f8fafc; font-weight: 800; border-top: 1px solid var(--rb-border); }

    #route-builder .rb-cell { min-width: 48px; border: 1px solid var(--rb-border); background: #fff; border-radius: 10px; padding: .3rem .45rem; cursor: pointer; display: inline-flex; flex-direction: column; align-items: center; line-height: 1.15; font-family: inherit; color: var(--rb-text); transition: border-color .15s, box-shadow .15s; }
    #route-builder .rb-cell strong { font-size: .95rem; }
    #route-builder .rb-cell small { font-size: .66rem; color: var(--rb-success); font-weight: 700; }
    #route-builder .rb-cell:hover:not(:disabled) { border-color: var(--rb-primary); }
    #route-builder .rb-cell.is-done { background: #f0fdf4; border-color: #bbf7d0; }
    #route-builder .rb-cell.is-empty { border-style: dashed; color: #cbd5e1; cursor: default; background: transparent; }
    #route-builder .rb-cell.is-active { border-color: var(--rb-primary); box-shadow: 0 0 0 3px var(--rb-primary-soft); background: var(--rb-primary-soft); }

    #route-builder .rb-check-input { width: 17px; height: 17px; accent-color: var(--rb-primary); margin: 0; }

    /* حالةُ الزيارة */
    #route-builder .rb-vstate { display: inline-block; font-size: .72rem; font-weight: 700; padding: .12rem .55rem; border-radius: 999px; white-space: nowrap; }
    #route-builder .rb-vstate--planned { background: #eef2ff; color: #4338ca; }
    #route-builder .rb-vstate--started { background: #fffbeb; color: #b45309; }
    #route-builder .rb-vstate--completed { background: #dcfce7; color: #15803d; }
    #route-builder .rb-vstate--missed { background: #ffe4e6; color: #be123c; }
    #route-builder .rb-vstate--cancelled { background: #f1f5f9; color: #64748b; }

    #route-builder .rb-move-list { max-height: 240px; }
    #route-builder .rb-move-targets { display: grid; grid-template-columns: .8fr 1.6fr 1fr; gap: .5rem; }

    #route-builder a.rb-icon { text-decoration: none; }

    @media (max-width: 1199.98px) {
        #route-builder .rb-weeks { flex-wrap: nowrap; }
        #route-builder .rb-week-summary { width: 100%; }
    }

    @media (max-width: 575.98px) {
        #route-builder .rb-move-targets { grid-template-columns: minmax(0, 1fr); }
    }
</style>
