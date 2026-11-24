{{-- أنماط مشتركة لشاشات الإعدادات: الضرائب، طرق الدفع، مفاتيح الربط،
     التطبيقات. مفصولة في ملفّ واحد كي لا تتباعد الشاشات الأربع مع
     التعديل — وهو ما حدث قبلاً حين حمل كل قالب نسخته من الأنماط. --}}
<link rel="stylesheet" type="text/css" href="{{ asset('css/online-store.css') }}">

<style>
    /* ── بطاقات الإحصاء ── */
    .st-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: .85rem; margin-bottom: 1.25rem; }
    .st-stat {
        background: #fff; border: 1px solid #e6e9ef; border-radius: 12px;
        padding: 1rem 1.1rem; display: flex; align-items: center; gap: .9rem;
        box-shadow: 0 1px 2px rgba(20,26,35,.04), 0 6px 16px rgba(20,26,35,.04);
    }
    .st-stat .ic {
        flex: 0 0 auto; width: 44px; height: 44px; border-radius: 11px;
        display: grid; place-items: center; font-size: 1.15rem;
    }
    .st-stat .ic.primary { background: #eaf1fb; color: #2b6cb0; }
    .st-stat .ic.success { background: #e7f6ee; color: #1b7a4b; }
    .st-stat .ic.warning { background: #fdf3df; color: #96700f; }
    .st-stat .ic.danger  { background: #fceceb; color: #a3342a; }
    .st-stat .ic.info    { background: #e8f2f5; color: #216b7c; }
    .st-stat .val { font-size: 1.5rem; font-weight: 700; line-height: 1.1; color: #1b2430; }
    .st-stat .lbl { font-size: .8rem; color: #6b7280; margin-top: .1rem; }

    /* ── بطاقة القسم ── */
    .st-card {
        background: #fff; border: 1px solid #e6e9ef; border-radius: 12px;
        margin-bottom: 1.1rem; overflow: hidden;
        box-shadow: 0 1px 2px rgba(20,26,35,.04), 0 6px 16px rgba(20,26,35,.04);
    }
    .st-card > .head {
        display: flex; align-items: center; gap: .6rem; flex-wrap: wrap;
        padding: .85rem 1.1rem; border-bottom: 1px solid #eef0f4; background: #fafbfc;
    }
    .st-card > .head h4 { margin: 0; font-size: 1rem; font-weight: 700; color: #1b2430; }
    .st-card > .head .count {
        font-size: .74rem; font-weight: 700; color: #4b5563;
        background: #eef1f5; border-radius: 20px; padding: .1rem .55rem;
    }
    .st-card > .head .spacer { margin-right: auto; }
    .st-card > .body { padding: 1.1rem; }
    .st-card > .body.flush { padding: 0; }
    .st-card > .foot { padding: .8rem 1.1rem; border-top: 1px solid #eef0f4; background: #fafbfc; }

    /* ── مفتاح تشغيل أفقي (مفاتيح Bootstrap تنكسر في RTL) ── */
    .st-switch { display: flex; align-items: center; gap: .55rem; margin: 0; cursor: pointer; }
    .st-switch input { position: absolute; opacity: 0; width: 0; height: 0; }
    .st-switch .track {
        position: relative; flex: 0 0 auto; width: 42px; height: 23px;
        background: #d7dce4; border-radius: 999px; transition: background .18s;
    }
    .st-switch .track::after {
        content: ''; position: absolute; top: 3px; right: 3px;
        width: 17px; height: 17px; background: #fff; border-radius: 50%;
        transition: transform .18s; box-shadow: 0 1px 3px rgba(0,0,0,.25);
    }
    .st-switch input:checked + .track { background: #28c76f; }
    .st-switch input:checked + .track::after { transform: translateX(-19px); }
    .st-switch input:focus-visible + .track { outline: 2px solid #2b6cb0; outline-offset: 2px; }
    .st-switch input:disabled + .track { opacity: .5; cursor: not-allowed; }

    /* ── صفّ وحدة قابلة للتفعيل ── */
    .st-toggle-row {
        display: flex; align-items: center; gap: .8rem;
        padding: .6rem .2rem; border-bottom: 1px solid #f2f4f7;
    }
    .st-toggle-row:last-child { border-bottom: 0; }
    .st-toggle-row .txt { min-width: 0; }
    .st-toggle-row .txt b { display: block; font-weight: 600; font-size: .93rem; color: #1b2430; }
    .st-toggle-row .txt small { color: #8a93a0; font-size: .78rem; }
    .st-toggle-row .st-switch { margin-right: auto; }

    /* ── وحدة خارج الباقة: تُغطّى ولا تُخفى ──
       الإخفاء يُنتج سؤال «أين ذهبت الوحدة؟» بلا جواب. والغطاء هو جوابه:
       الاسم يبقى مقروءاً تحته باهتاً، والغطاء يقول لماذا لا يُنقر. */
    .st-toggle-row.locked { position: relative; }
    .st-toggle-row.locked .txt,
    .st-toggle-row.locked .st-switch { opacity: .3; filter: grayscale(1); }
    .st-lock {
        position: absolute; inset: .1rem .1rem; z-index: 2;
        display: flex; align-items: center; justify-content: center; gap: .4rem;
        border-radius: 8px; text-decoration: none;
        background: rgba(253, 247, 232, .95); border: 1px dashed #e8cd93;
        color: #7d5f0e; font-size: .78rem; font-weight: 600; text-align: center;
    }
    .st-lock:hover, .st-lock:focus { background: #fdf3df; color: #6a5009; text-decoration: none; }
    .st-lock i { font-size: .92rem; }

    /* ── جدول ── */
    .st-table { width: 100%; border-collapse: collapse; }
    .st-table th, .st-table td { padding: .7rem .9rem; text-align: right; border-bottom: 1px solid #eef0f4; vertical-align: middle; }
    .st-table th { background: #fafbfc; font-size: .78rem; font-weight: 700; color: #6b7280; white-space: nowrap; }
    .st-table tbody tr:last-child td { border-bottom: 0; }
    .st-table tbody tr:hover { background: #fbfcfd; }
    .st-table td.num { font-variant-numeric: tabular-nums; }

    /* ── شارات ── */
    .st-badge {
        display: inline-block; font-size: .72rem; font-weight: 700;
        padding: .15rem .55rem; border-radius: 20px; white-space: nowrap;
    }
    .st-badge.on   { background: #e7f6ee; color: #1b7a4b; }
    .st-badge.off  { background: #f0f1f3; color: #6b7280; }
    .st-badge.warn { background: #fdf3df; color: #96700f; }
    .st-badge.dang { background: #fceceb; color: #a3342a; }
    .st-badge.info { background: #e8f2f5; color: #216b7c; }

    /* ── نصّ لاتيني (مفاتيح، رموز) — ينقلب في RTL بلا هذا ── */
    .st-mono {
        direction: ltr; display: inline-block; unicode-bidi: embed;
        font-family: Consolas, Monaco, 'Courier New', monospace; font-size: .8rem;
        background: #f4f5f7; color: #384250; padding: .12rem .45rem;
        border-radius: 5px; border: 1px solid #e6e9ef; max-width: 100%;
        overflow-x: auto; white-space: nowrap; vertical-align: middle;
    }

    /* ── تنبيه ── */
    .st-note { display: flex; gap: .75rem; align-items: flex-start; padding: .85rem 1rem; border-radius: 10px; border: 1px solid; margin-bottom: 1rem; }
    .st-note.info { background: #eef5fb; border-color: #c6ddf1; color: #23557f; }
    .st-note.warn { background: #fdf7e8; border-color: #f0dcae; color: #7d5f0e; }
    .st-note.dang { background: #fdeeec; border-color: #f2c4bd; color: #8f2f22; }
    .st-note p { margin: .2rem 0 0; font-size: .88rem; }

    .st-empty { text-align: center; padding: 2.5rem 1rem; color: #8a93a0; }
    .st-empty i { font-size: 2.2rem; color: #ccd2da; display: block; margin-bottom: .6rem; }

    .st-actions { display: inline-flex; gap: .3rem; }
    .st-actions .btn { padding: .28rem .5rem; line-height: 1; }
</style>
