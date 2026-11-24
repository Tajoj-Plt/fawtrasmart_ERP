{{--
    إضافات قسم العقارات فوق `stock-theme` — على متغيّرات `--app-*` نفسها، فلا
    لونٌ مكتوبٌ باليد ولا مظهرٌ ثانٍ للنظام. ما يصلح من `stk-*` يُستعمل كما
    هو؛ وهنا ما لا مقابل له هناك: بطاقات العقار، شبكة المؤشرات، لوحة كانبان،
    ومعالج الخطوات.
--}}
<style id="realestate-theme">
    .re-kpis { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 1rem; margin-bottom: 1.25rem; }
    .re-kpi { display: flex; align-items: center; gap: .85rem; padding: 1rem 1.1rem; background: var(--app-surface, #fff);
        border: 1px solid var(--app-border, #ebe9f1); border-radius: var(--app-radius, .6rem); text-decoration: none; color: inherit;
        transition: box-shadow .15s, transform .15s; }
    a.re-kpi:hover { box-shadow: 0 4px 18px rgba(34, 41, 47, .08); transform: translateY(-1px); color: inherit; }
    .re-kpi__icon { flex: 0 0 42px; height: 42px; border-radius: 50%; display: grid; place-items: center; font-size: 1.15rem; }
    .re-kpi__value { font-size: 1.35rem; font-weight: 700; line-height: 1.2; font-variant-numeric: tabular-nums; }
    .re-kpi__label { font-size: .82rem; color: var(--app-muted, #6e6b7b); }
    .re-kpi__note { font-size: .74rem; color: var(--app-muted, #6e6b7b); opacity: .85; }

    .re-cards { display: grid; grid-template-columns: repeat(auto-fill, minmax(290px, 1fr)); gap: 1rem; padding: 1rem; }
    .re-card { border: 1px solid var(--app-border, #ebe9f1); border-radius: var(--app-radius, .6rem); overflow: hidden;
        background: var(--app-surface, #fff); display: flex; flex-direction: column; }
    .re-card__cover { height: 140px; background: var(--app-soft, #f3f2f7) center/cover no-repeat; display: grid; place-items: center;
        color: var(--app-muted, #b9b9c3); font-size: 2.2rem; position: relative; }
    .re-card__cover .badge { position: absolute; top: .6rem; inset-inline-start: .6rem; }
    .re-card__body { padding: .9rem 1rem; flex: 1; }
    .re-card__title { font-weight: 700; font-size: 1rem; margin: 0 0 .15rem; display: block; color: inherit; }
    .re-card__sub { font-size: .8rem; color: var(--app-muted, #6e6b7b); }
    .re-card__stats { display: grid; grid-template-columns: repeat(3, 1fr); text-align: center; border-top: 1px solid var(--app-border, #ebe9f1); }
    .re-card__stats > div { padding: .55rem .25rem; }
    .re-card__stats > div + div { border-inline-start: 1px solid var(--app-border, #ebe9f1); }
    .re-card__stats strong { display: block; font-variant-numeric: tabular-nums; }
    .re-card__stats span { font-size: .72rem; color: var(--app-muted, #6e6b7b); }

    .re-progress { height: 6px; border-radius: 3px; background: var(--app-soft, #f3f2f7); overflow: hidden; }
    .re-progress > span { display: block; height: 100%; background: var(--app-primary, #7367f0); border-radius: 3px; }

    .re-gallery { display: flex; flex-wrap: wrap; gap: .5rem; }
    .re-gallery a, .re-gallery__item { width: 92px; height: 72px; border-radius: .4rem; background: var(--app-soft, #f3f2f7) center/cover no-repeat;
        border: 1px solid var(--app-border, #ebe9f1); display: block; position: relative; }
    .re-gallery__item label { position: absolute; inset-inline-end: 4px; top: 4px; margin: 0; background: rgba(255, 255, 255, .92);
        border-radius: 3px; padding: 0 5px; font-size: .68rem; cursor: pointer; }
    .re-files { list-style: none; padding: 0; margin: 0; }
    .re-files li { display: flex; align-items: center; gap: .5rem; padding: .35rem 0; border-bottom: 1px dashed var(--app-border, #ebe9f1); font-size: .85rem; }

    /* معالج الخطوات: كلّ الخطوات في نموذجٍ واحد يُرسَل مرّةً واحدة */
    .re-steps { display: flex; gap: .35rem; flex-wrap: wrap; margin-bottom: 1.1rem; counter-reset: step; }
    .re-steps__item { flex: 1 1 130px; display: flex; align-items: center; gap: .5rem; padding: .6rem .8rem; border-radius: var(--app-radius, .6rem);
        border: 1px solid var(--app-border, #ebe9f1); background: var(--app-surface, #fff); cursor: pointer; font-size: .85rem;
        color: var(--app-muted, #6e6b7b); text-align: start; }
    .re-steps__item::before { counter-increment: step; content: counter(step); flex: 0 0 24px; height: 24px; border-radius: 50%;
        display: grid; place-items: center; background: var(--app-soft, #f3f2f7); font-size: .78rem; font-weight: 700; }
    .re-steps__item.is-active { border-color: var(--app-primary, #7367f0); color: var(--app-primary, #7367f0); font-weight: 600; }
    .re-steps__item.is-active::before { background: var(--app-primary, #7367f0); color: #fff; }
    .re-steps__item.is-invalid { border-color: var(--app-danger, #ea5455); color: var(--app-danger, #ea5455); }
    .re-step { display: none; }
    .re-step.is-active { display: block; }
    .re-step-nav { display: flex; justify-content: space-between; gap: .5rem; margin-top: 1rem; }

    .re-summary { position: sticky; top: 90px; }

    /* كانبان المهام */
    .re-kanban { display: grid; grid-template-columns: repeat(5, minmax(230px, 1fr)); gap: .85rem; overflow-x: auto; padding-bottom: .5rem; }
    .re-kanban__col { background: var(--app-soft, #f8f8f8); border-radius: var(--app-radius, .6rem); padding: .6rem; min-height: 220px; }
    .re-kanban__col.is-over { outline: 2px dashed var(--app-primary, #7367f0); outline-offset: -2px; }
    .re-kanban__head { display: flex; justify-content: space-between; align-items: center; font-weight: 700; font-size: .85rem; padding: .25rem .35rem .6rem; }
    .re-task { background: var(--app-surface, #fff); border: 1px solid var(--app-border, #ebe9f1); border-radius: .5rem; padding: .65rem .75rem;
        margin-bottom: .55rem; cursor: grab; font-size: .85rem; }
    .re-task.is-late { border-inline-start: 3px solid var(--app-danger, #ea5455); }
    .re-task__title { font-weight: 600; margin-bottom: .3rem; }
    .re-task__meta { display: flex; flex-wrap: wrap; gap: .4rem .7rem; font-size: .74rem; color: var(--app-muted, #6e6b7b); }

    .re-attention { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: .85rem; }
    .re-attention__item { border: 1px solid var(--app-border, #ebe9f1); border-radius: var(--app-radius, .6rem); padding: .8rem .9rem; background: var(--app-surface, #fff); }
    .re-attention__head { display: flex; justify-content: space-between; align-items: center; gap: .5rem; margin-bottom: .45rem; font-weight: 600; font-size: .88rem; }
    .re-attention__rows { list-style: none; margin: 0; padding: 0; font-size: .8rem; color: var(--app-muted, #6e6b7b); }
    .re-attention__rows li { padding: .2rem 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

    /* اهتمامات العملاء: بطاقة لكل عميل */
    .re-rel-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 1rem; padding: 1rem; }
    .re-rel-card { display: flex; flex-direction: column; background: var(--app-surface, #fff); border: 1px solid var(--app-border, #ebe9f1); border-radius: var(--app-radius, .75rem); overflow: hidden; transition: box-shadow .15s, transform .15s; }
    .re-rel-card:hover { box-shadow: 0 6px 22px rgba(34, 41, 47, .09); transform: translateY(-1px); }
    .re-rel-card.is-follow { border-top: 3px solid var(--bs-warning, #ff9f43); }
    .re-rel-card__head { display: flex; align-items: center; gap: .75rem; padding: .9rem 1rem .6rem; }
    .re-rel-avatar { flex: 0 0 44px; height: 44px; border-radius: 50%; display: grid; place-items: center; font-weight: 700; font-size: 1.05rem; color: #fff; background: linear-gradient(135deg, #7367f0, #9e95f5); }
    .re-rel-card__name { font-weight: 700; font-size: .98rem; line-height: 1.3; color: inherit; display: block; }
    .re-rel-card__sub { font-size: .78rem; color: var(--app-muted, #6e6b7b); }
    .re-rel-card__roles { display: flex; flex-wrap: wrap; gap: .3rem; padding: 0 1rem .6rem; }
    .re-rel-list { list-style: none; margin: 0; padding: 0 1rem; flex: 1; }
    .re-rel-item { display: flex; align-items: flex-start; gap: .6rem; padding: .6rem 0; border-top: 1px dashed var(--app-border, #ebe9f1); }
    .re-rel-item__icon { flex: 0 0 32px; height: 32px; border-radius: .5rem; display: grid; place-items: center; background: rgba(115, 103, 240, .1); color: #7367f0; font-size: .95rem; }
    .re-rel-item__body { flex: 1; min-width: 0; }
    .re-rel-item__title { font-weight: 600; font-size: .87rem; line-height: 1.35; }
    .re-rel-item__meta { display: flex; flex-wrap: wrap; align-items: center; gap: .35rem .6rem; margin-top: .2rem; font-size: .76rem; color: var(--app-muted, #6e6b7b); }
    .re-rel-item__actions { display: flex; gap: .2rem; opacity: .35; transition: opacity .15s; }
    .re-rel-item:hover .re-rel-item__actions, .re-rel-item__actions:focus-within { opacity: 1; }
    .re-rel-card__foot { display: flex; justify-content: space-between; align-items: center; gap: .5rem; padding: .6rem 1rem; margin-top: .3rem; background: rgba(130, 134, 139, .06); font-size: .76rem; color: var(--app-muted, #6e6b7b); }
    .re-rel-tabs { display: inline-flex; gap: .25rem; padding: .25rem; background: rgba(130, 134, 139, .1); border-radius: .75rem; margin-bottom: 1rem; }
    .re-rel-tabs .nav-link { border-radius: .55rem; padding: .4rem .95rem; font-weight: 600; font-size: .85rem; color: inherit; }
    .re-rel-tabs .nav-link.active { background: var(--app-surface, #fff); color: #7367f0; box-shadow: 0 1px 6px rgba(34, 41, 47, .12); }
    .re-rel-tabs .badge { margin-inline-start: .35rem; }
    .re-rel-hint { display: flex; gap: .6rem; align-items: flex-start; padding: .7rem .9rem; margin-bottom: 1rem; border-radius: .6rem; background: rgba(0, 207, 232, .08); border: 1px solid rgba(0, 207, 232, .25); font-size: .84rem; }
    .re-rel-chips { display: flex; flex-wrap: wrap; gap: .4rem; margin-bottom: .75rem; }
    .re-rel-chips .btn { border-radius: 2rem; padding: .25rem .8rem; font-size: .8rem; }

    .re-row-link { cursor: pointer; }
    .re-num { font-variant-numeric: tabular-nums; white-space: nowrap; }
    .re-view-toggle .btn.active { background: var(--app-primary, #7367f0); color: #fff; border-color: var(--app-primary, #7367f0); }

    @media (max-width: 767px) { .re-kanban { grid-template-columns: repeat(5, 260px); } .re-summary { position: static; } }
    @media print { .re-no-print, .main-menu, .header-navbar, .stk-page-head__actions, .stk-filters { display: none !important; } .app-content { margin: 0 !important; } }
</style>
