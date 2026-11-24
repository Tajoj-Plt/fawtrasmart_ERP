@extends('client::reservations.master')

@section('title', 'تقويم الحجوزات')

{{--
    تقويمُ الحجوزات — أُعيد بناؤه من الصفر (2026-09-29، طلب المستخدم: «غير
    مرتّب… يحتاج مسح وبناء من جديد نفس الصورة بالضبط»).

    لا FullCalendar: عرضُ «اليوم» في الصورة أعمدةُ موظفين، وهذا في المكتبة
    ميزةٌ مدفوعة. فالشبكةُ مرسومةٌ هنا بأربعة عروض:
      • اليوم   — عمودٌ لكلّ موظّفٍ يُحجز له، وما خارج ورديته مظلَّل.
      • الأسبوع — سبعةُ أيامٍ من السبت.
      • الشهر   — خلايا بأوّل ثلاثة حجوزات و«+ أخرى».
      • أجندة   — قائمةُ الأسبوع مجمّعةً باليوم.
    وحولها: بطاقاتُ الأرقام، والفلاتر، وقائمةُ حجوزات اليوم، وتقويمٌ مصغّر.

    المصدرُ واحد: `Reservations.calendar.feed` يردّ الأحداثَ وأعمدةَ اليوم
    (الموظفون بورديتهم) بالفلاتر نفسِها التي تحكم القائمة.
    العرضُ والتاريخُ في رابط الصفحة (`?view=&date=`) فيبقيان بعد التحديث.
--}}

@section('content')
    @php
        $legend = collect($statuses)->filter(fn ($s) => in_array($s->value, ['confirmed', 'pending', 'completed', 'cancelled'], true));
        $canCreate = hasPermission('reservations.create');
    @endphp

    <style>
        .rc { --rc-line: #e6eaf2; --rc-soft: #f5f7fb; --rc-muted: #7a8599; --rc-text: #1f2a44; --rc-pri: var(--app-primary, #1e5bd6); --rc-hour: 64px; }
        .rc * { box-sizing: border-box; }
        .rc-crumbs { display: flex; gap: .45rem; align-items: center; font-size: .82rem; color: var(--rc-muted); margin-bottom: .6rem; }
        .rc-crumbs a { color: var(--rc-muted); text-decoration: none; }
        .rc-crumbs a:hover { color: var(--rc-pri); }
        .rc-crumbs i { font-size: .8rem; }

        .rc-card { background: var(--app-surface, #fff); border: 1px solid var(--rc-line); border-radius: 14px; box-shadow: 0 1px 2px rgba(16, 24, 40, .04); }

        /* ═══ الترويسة: العنوان + بطاقات الأرقام ═══ */
        .rc-top { display: flex; align-items: stretch; gap: 1rem; margin-bottom: 1rem; flex-wrap: wrap; }
        .rc-title { display: flex; align-items: center; gap: .7rem; margin-inline-end: auto; padding: .4rem .2rem; }
        .rc-title i { font-size: 1.7rem; color: var(--rc-pri); }
        .rc-title h2 { margin: 0; font-size: 1.55rem; font-weight: 800; color: var(--rc-text); }
        .rc-stats { display: flex; gap: .8rem; flex-wrap: wrap; }
        .rc-stat { min-width: 175px; display: flex; align-items: center; justify-content: space-between; gap: .9rem; padding: .85rem 1.1rem; border-radius: 12px; background: var(--tone-bg); border: 1px solid transparent; }
        .rc-stat__label { font-size: .86rem; font-weight: 700; color: var(--tone); }
        .rc-stat__num { font-size: 1.35rem; font-weight: 800; color: var(--rc-text); line-height: 1.2; }
        .rc-stat__icon { width: 34px; height: 34px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; background: var(--tone); color: #fff; font-size: 1rem; flex: none; }
        .rc-stat--total { background: var(--app-surface, #fff); border-color: var(--rc-line); min-width: 230px; }
        .rc-stat--total .rc-stat__label { color: var(--rc-text); font-size: .95rem; }
        .rc-stat--total .rc-stat__num { font-size: 1.7rem; }
        .rc-stat--total .rc-stat__icon { background: transparent; color: var(--rc-pri); font-size: 1.6rem; width: auto; }

        /* ═══ الجسم: عمودٌ جانبيّ + التقويم ═══ */
        .rc-body { display: grid; grid-template-columns: 380px minmax(0, 1fr); gap: 1rem; align-items: start; }
        .rc-side { display: flex; flex-direction: column; gap: 1rem; }
        .rc-main { display: flex; flex-direction: column; gap: 1rem; min-width: 0; }

        /* أزرارُ العرض */
        .rc-views { display: flex; gap: .35rem; padding: .35rem; }
        .rc-views button { flex: 1; min-width: 0; white-space: nowrap; border: 0; background: transparent; border-radius: 9px; padding: .6rem .4rem; font-weight: 700; font-size: .88rem; color: var(--rc-text); display: inline-flex; align-items: center; justify-content: center; gap: .4rem; }
        .rc-views button:hover { background: var(--rc-soft); }
        .rc-views button.is-active { background: var(--rc-pri); color: #fff; box-shadow: 0 4px 12px color-mix(in srgb, var(--rc-pri) 30%, transparent); }

        /* الفلاتر: حقولٌ متساوية العرض في صفٍّ واحد على الشاشة العريضة */
        .rc-filter-row { row-gap: .8rem; }

        /* شريطُ التنقّل في بطاقة التقويم */
        .rc-bar { display: flex; align-items: center; gap: .8rem; padding: .8rem 1rem; border-bottom: 1px solid var(--rc-line); flex-wrap: wrap; }
        .rc-quick { display: inline-flex; border: 1px solid var(--rc-line); border-radius: 10px; overflow: hidden; }
        .rc-quick button { border: 0; background: var(--app-surface, #fff); padding: .45rem 1rem; font-weight: 700; font-size: .84rem; color: var(--rc-text); border-inline-start: 1px solid var(--rc-line); }
        .rc-quick button:first-child { border-inline-start: 0; }
        .rc-quick button.is-active { background: var(--rc-pri); color: #fff; }
        .rc-nav { display: inline-flex; align-items: center; gap: .6rem; margin: 0 auto; }
        .rc-nav__arrow { width: 34px; height: 34px; border: 0; border-radius: 8px; background: transparent; color: var(--rc-text); font-size: 1.1rem; display: inline-flex; align-items: center; justify-content: center; }
        .rc-nav__arrow:hover { background: var(--rc-soft); }
        .rc-nav__label { font-weight: 800; font-size: 1rem; color: var(--rc-text); display: inline-flex; align-items: center; gap: .5rem; position: relative; }
        .rc-nav__label i { color: var(--rc-muted); }
        .rc-nav__label input[type=date] { position: absolute; inset: 0; opacity: 0; cursor: pointer; }

        /* ═══ شبكة الوقت (اليوم والأسبوع) ═══ */
        .rc-grid-wrap { overflow: auto; max-height: 680px; position: relative; }
        .rc-grid { display: grid; min-width: 100%; }
        .rc-head { position: sticky; top: 0; z-index: 5; background: var(--app-surface, #fff); border-bottom: 1px solid var(--rc-line); padding: .7rem .6rem; display: flex; align-items: center; justify-content: center; gap: .6rem; border-inline-start: 1px solid var(--rc-line); min-height: 64px; }
        .rc-head:first-child { border-inline-start: 0; }
        .rc-head__icon { width: 30px; height: 30px; border-radius: 50%; border: 1.5px solid var(--rc-text); display: inline-flex; align-items: center; justify-content: center; font-size: .85rem; color: var(--rc-text); flex: none; }
        .rc-head__name { font-weight: 800; font-size: .92rem; color: var(--rc-text); line-height: 1.25; }
        .rc-head__sub { font-size: .74rem; color: var(--rc-muted); font-weight: 600; }
        .rc-head.is-today { background: color-mix(in srgb, var(--rc-pri) 8%, var(--app-surface, #fff)); }
        .rc-head.is-today .rc-head__name { color: var(--rc-pri); }
        .rc-head--axis { position: sticky; inset-inline-end: 0; z-index: 6; }
        .rc-col { position: relative; border-inline-start: 1px solid var(--rc-line); background-image: repeating-linear-gradient(to bottom, transparent 0, transparent calc(var(--rc-hour) - 1px), var(--rc-line) calc(var(--rc-hour) - 1px), var(--rc-line) var(--rc-hour)); }
        .rc-col:first-of-type { border-inline-start: 0; }
        .rc-col.is-today { background-color: color-mix(in srgb, var(--rc-pri) 3%, transparent); }
        .rc-col__off { position: absolute; inset-inline: 0; background: repeating-linear-gradient(45deg, #f4f5f8, #f4f5f8 6px, #eceef3 6px, #eceef3 12px); opacity: .75; pointer-events: none; }
        .rc-col__slot { position: absolute; inset-inline: 0; cursor: pointer; }
        .rc-col__slot:hover { background: color-mix(in srgb, var(--rc-pri) 7%, transparent); }
        .rc-axis { position: sticky; inset-inline-end: 0; z-index: 4; background: var(--app-surface, #fff); border-inline-start: 1px solid var(--rc-line); }
        .rc-axis span { position: absolute; inset-inline: 0; text-align: center; font-size: .76rem; font-weight: 700; color: var(--rc-muted); transform: translateY(6px); direction: ltr; }
        .rc-now { position: absolute; inset-inline: 0; height: 2px; background: #ef4444; z-index: 3; pointer-events: none; }
        .rc-now::before { content: ''; position: absolute; inset-inline-start: -5px; top: -4px; width: 10px; height: 10px; border-radius: 50%; background: #ef4444; }

        /* بطاقةُ الحجز */
        .rc-ev { position: absolute; z-index: 2; border-radius: 8px; padding: .4rem .55rem; overflow: hidden; text-decoration: none !important; background: color-mix(in srgb, var(--ev) 12%, #fff); border: 1px solid color-mix(in srgb, var(--ev) 45%, #fff); box-shadow: 0 1px 2px rgba(16, 24, 40, .06); display: flex; flex-direction: column; gap: 1px; transition: box-shadow .12s, transform .12s; }
        .rc-ev:hover { box-shadow: 0 6px 16px rgba(16, 24, 40, .14); transform: translateY(-1px); z-index: 3; }
        .rc-ev__top { display: flex; align-items: center; justify-content: space-between; gap: .4rem; min-width: 0; }
        .rc-ev__client { font-weight: 800; font-size: .82rem; color: var(--rc-text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .rc-ev__status { font-size: .7rem; font-weight: 700; color: var(--ev); display: inline-flex; align-items: center; gap: .25rem; white-space: nowrap; flex: none; }
        .rc-ev__status::before { content: ''; width: 7px; height: 7px; border-radius: 50%; background: var(--ev); }
        .rc-ev__service { font-size: .74rem; color: var(--rc-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .rc-ev__time { font-size: .72rem; font-weight: 700; color: var(--rc-text); direction: ltr; text-align: end; margin-top: auto; }
        .rc-ev.is-short .rc-ev__service, .rc-ev.is-short .rc-ev__time { display: none; }

        /* ═══ الشهر ═══ */
        .rc-month { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); }
        .rc-month__dow { padding: .6rem; text-align: center; font-weight: 800; font-size: .82rem; color: var(--rc-muted); border-bottom: 1px solid var(--rc-line); }
        .rc-month__cell { min-height: 118px; border-bottom: 1px solid var(--rc-line); border-inline-start: 1px solid var(--rc-line); padding: .35rem; display: flex; flex-direction: column; gap: 3px; cursor: pointer; }
        .rc-month__cell:nth-child(7n + 1) { border-inline-start: 0; }
        .rc-month__cell.is-out { background: var(--rc-soft); }
        .rc-month__cell.is-out .rc-month__num { color: #b3bac8; }
        .rc-month__cell.is-today .rc-month__num { background: var(--rc-pri); color: #fff; }
        .rc-month__num { align-self: flex-start; min-width: 26px; height: 26px; padding: 0 6px; border-radius: 13px; font-weight: 800; font-size: .82rem; color: var(--rc-text); display: inline-flex; align-items: center; justify-content: center; }
        .rc-chip { display: flex; gap: .3rem; align-items: center; font-size: .72rem; font-weight: 700; padding: 2px 6px; border-radius: 6px; background: color-mix(in srgb, var(--ev) 13%, #fff); color: var(--rc-text); text-decoration: none !important; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; border-inline-start: 3px solid var(--ev); }
        .rc-chip b { direction: ltr; color: var(--ev); font-weight: 800; }
        .rc-more { font-size: .72rem; font-weight: 800; color: var(--rc-pri); padding: 0 4px; }

        /* ═══ الأجندة وقائمة اليوم ═══ */
        .rc-agenda__day { padding: .55rem 1rem; background: var(--rc-soft); font-weight: 800; font-size: .86rem; color: var(--rc-text); border-bottom: 1px solid var(--rc-line); }
        .rc-row { display: grid; grid-template-columns: 70px 1.3fr 1fr 1fr 110px; gap: .6rem; align-items: center; padding: .6rem 1rem; border-bottom: 1px solid var(--rc-line); text-decoration: none !important; color: var(--rc-text); font-size: .84rem; }
        .rc-row:hover { background: var(--rc-soft); }
        .rc-row__time { font-weight: 800; direction: ltr; text-align: end; }
        .rc-row__client { font-weight: 800; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .rc-row__muted { color: var(--rc-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .rc-badge { display: inline-flex; align-items: center; gap: .3rem; padding: .2rem .55rem; border-radius: 999px; font-size: .72rem; font-weight: 800; color: var(--ev); background: color-mix(in srgb, var(--ev) 13%, #fff); white-space: nowrap; justify-self: start; }
        .rc-badge::before { content: ''; width: 7px; height: 7px; border-radius: 50%; background: var(--ev); }

        .rc-side__head { display: flex; align-items: center; gap: .5rem; padding: 1rem 1rem .6rem; font-weight: 800; color: var(--rc-text); }
        .rc-side__head i { color: var(--rc-pri); font-size: 1.1rem; }
        .rc-search { margin: 0 1rem .6rem; position: relative; }
        .rc-search input { width: 100%; border: 1px solid var(--rc-line); border-radius: 9px; padding: .5rem .8rem; padding-inline-start: 2.1rem; font-size: .84rem; outline: none; }
        .rc-search input:focus { border-color: var(--rc-pri); }
        .rc-search i { position: absolute; inset-inline-start: .75rem; top: 50%; transform: translateY(-50%); color: var(--rc-muted); }
        .rc-today { border-top: 1px solid var(--rc-line); }
        .rc-today__head, .rc-today__row { display: grid; grid-template-columns: 52px 1.2fr 1fr 96px 14px; gap: .45rem; align-items: center; padding: .55rem 1rem; font-size: .8rem; }
        .rc-today__head { background: var(--rc-soft); font-weight: 800; color: var(--rc-muted); font-size: .76rem; }
        .rc-today__row { border-top: 1px solid var(--rc-line); color: var(--rc-text); text-decoration: none !important; }
        .rc-today__row:hover { background: var(--rc-soft); }
        .rc-today__row i { color: var(--rc-muted); font-size: .8rem; }
        .rc-today__list { max-height: 440px; overflow: auto; }
        .rc-side__foot { display: flex; align-items: center; gap: .35rem; padding: .75rem 1rem; border-top: 1px solid var(--rc-line); font-weight: 800; font-size: .82rem; color: var(--rc-pri); text-decoration: none; }
        .rc-empty { padding: 1.4rem 1rem; text-align: center; color: var(--rc-muted); font-size: .84rem; }

        /* التقويمُ المصغّر */
        .rc-mini { padding: .8rem 1rem 1rem; }
        .rc-mini__head { display: flex; align-items: center; justify-content: space-between; font-weight: 800; color: var(--rc-text); margin-bottom: .6rem; }
        .rc-mini__head button { border: 0; background: transparent; width: 30px; height: 30px; border-radius: 8px; color: var(--rc-text); }
        .rc-mini__head button:hover { background: var(--rc-soft); }
        .rc-mini__grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 2px; text-align: center; }
        .rc-mini__dow { font-size: .7rem; font-weight: 700; color: var(--rc-muted); padding: .3rem 0; }
        .rc-mini__day { position: relative; height: 34px; border: 0; background: transparent; border-radius: 50%; font-size: .8rem; font-weight: 700; color: var(--rc-text); width: 34px; margin: 0 auto; }
        .rc-mini__day:hover { background: var(--rc-soft); }
        .rc-mini__day.is-out { color: #c1c7d3; }
        .rc-mini__day.is-today { box-shadow: inset 0 0 0 1.5px var(--rc-pri); }
        .rc-mini__day.is-picked { background: var(--rc-pri); color: #fff; }
        .rc-mini__day.has-dot::after { content: ''; position: absolute; bottom: 3px; left: 50%; width: 4px; height: 4px; margin-left: -2px; border-radius: 50%; background: var(--rc-pri); }
        .rc-mini__day.is-picked.has-dot::after { background: #fff; }

        /* التذييل */
        .rc-foot { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: .75rem 1rem; border-top: 1px solid var(--rc-line); flex-wrap: wrap; }
        .rc-legend { display: flex; gap: 1.2rem; flex-wrap: wrap; font-size: .8rem; font-weight: 700; color: var(--rc-text); }
        .rc-legend span { display: inline-flex; align-items: center; gap: .4rem; }
        .rc-legend i { width: 9px; height: 9px; border-radius: 50%; display: inline-block; }
        .rc-foot__btn { border: 1px solid var(--rc-line); background: var(--app-surface, #fff); border-radius: 9px; padding: .45rem .9rem; font-weight: 700; font-size: .82rem; color: var(--rc-text); display: inline-flex; gap: .4rem; align-items: center; }
        .rc-loading { position: absolute; inset: 0; background: rgba(255, 255, 255, .55); display: none; align-items: center; justify-content: center; z-index: 10; font-weight: 800; color: var(--rc-muted); }
        .rc-loading.is-on { display: flex; }

        @media (max-width: 1199px) {
            .rc-body { grid-template-columns: 1fr; }
            .rc-side { order: 2; }
        }
        @media (max-width: 767px) {
            .rc-stat { min-width: calc(50% - .4rem); flex: 1; }
            .rc-row { grid-template-columns: 60px 1fr 90px; }
            .rc-row > :nth-child(3), .rc-row > :nth-child(4) { display: none; }
        }
    </style>

    <div class="rc" id="rc">
        <nav class="rc-crumbs" aria-label="مسار التنقّل">
            <a href="{{ route('dashboard') }}"><i class="feather icon-home"></i> الرئيسية</a>
            <i class="feather icon-chevron-left"></i>
            <a href="{{ route('Reservations.index') }}">الحجوزات</a>
            <i class="feather icon-chevron-left"></i>
            <span>التقويم</span>
        </nav>

        @include('client::reservations.partials.alerts')

        {{-- ═══════════ العنوان وبطاقاتُ الأرقام ═══════════ --}}
        <div class="rc-top">
            <div class="rc-title">
                <i class="feather icon-calendar"></i>
                <h2>الحجوزات</h2>
            </div>

            <div class="rc-stats">
                <div class="rc-stat" style="--tone:#e5484d; --tone-bg:#fdecec">
                    <div><div class="rc-stat__label">ملغي</div><div class="rc-stat__num" data-stat="cancelled">0</div></div>
                    <span class="rc-stat__icon"><i class="feather icon-x"></i></span>
                </div>
                <div class="rc-stat" style="--tone:#2f6fed; --tone-bg:#eaf1fe">
                    <div><div class="rc-stat__label">مكتمل</div><div class="rc-stat__num" data-stat="completed">0</div></div>
                    <span class="rc-stat__icon"><i class="feather icon-check"></i></span>
                </div>
                <div class="rc-stat" style="--tone:#f59e0b; --tone-bg:#fff6e5">
                    <div><div class="rc-stat__label">قيد الانتظار</div><div class="rc-stat__num" data-stat="pending">0</div></div>
                    <span class="rc-stat__icon"><i class="feather icon-clock"></i></span>
                </div>
                <div class="rc-stat" style="--tone:#22a06b; --tone-bg:#e9f7ef">
                    <div><div class="rc-stat__label">مؤكّد</div><div class="rc-stat__num" data-stat="confirmed">0</div></div>
                    <span class="rc-stat__icon"><i class="feather icon-check"></i></span>
                </div>
                <div class="rc-stat rc-stat--total">
                    <div><div class="rc-stat__label" id="rc-total-label">إجمالي حجوزات اليوم</div><div class="rc-stat__num" data-stat="total">0</div></div>
                    <span class="rc-stat__icon"><i class="feather icon-calendar"></i></span>
                </div>
            </div>
        </div>

        <div class="rc-body">
            {{-- ═══════════ العمود الجانبي ═══════════ --}}
            <aside class="rc-side">
                <div class="rc-card rc-views" role="tablist" aria-label="عرض التقويم">
                    <button type="button" data-view="day"><i class="feather icon-calendar"></i> اليوم</button>
                    <button type="button" data-view="week">الأسبوع</button>
                    <button type="button" data-view="month">الشهر</button>
                    <button type="button" data-view="agenda"><i class="feather icon-list"></i> أجندة</button>
                </div>

                <div class="rc-card">
                    <div class="rc-side__head"><i class="feather icon-file-text"></i> <span id="rc-list-title">قائمة حجوزات اليوم</span></div>
                    <div class="rc-search">
                        <i class="feather icon-search"></i>
                        <input type="search" id="rc-search" placeholder="ابحث باسم العميل أو رقم الحجز…" autocomplete="off">
                    </div>
                    <div class="rc-today">
                        <div class="rc-today__head">
                            <span>الوقت</span><span>العميل</span><span>الخدمة</span><span>الحالة</span><span></span>
                        </div>
                        <div class="rc-today__list" id="rc-today-list"></div>
                    </div>
                    <a class="rc-side__foot" id="rc-all-link" href="{{ route('Reservations.index') }}">
                        عرض جميع حجوزات اليوم <i class="feather icon-chevron-left"></i>
                    </a>
                </div>

                <div class="rc-card rc-mini">
                    <div class="rc-mini__head">
                        <button type="button" data-mini="-1" aria-label="الشهر السابق"><i class="feather icon-chevron-right"></i></button>
                        <span id="rc-mini-title"></span>
                        <button type="button" data-mini="1" aria-label="الشهر التالي"><i class="feather icon-chevron-left"></i></button>
                    </div>
                    <div class="rc-mini__grid" id="rc-mini-grid"></div>
                </div>
            </aside>

            {{-- ═══════════ الفلاتر والتقويم ═══════════ --}}
            <section class="rc-main">
                {{-- الفلاتر على قياس شاشات النظام: بطاقة `rsv-filters` وحقولٌ بـselect2 كقائمة الحجوزات --}}
                <div class="card rsv-filters mb-0">
                    <div class="card-body">
                        <div class="rsv-filters__head">
                            <h6 class="rsv-filters__title"><i class="feather icon-filter"></i> تصفية التقويم</h6>
                            <div class="d-flex align-items-center" style="gap:.5rem">
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="rc-reset">
                                    <i class="feather icon-rotate-ccw"></i> إعادة تعيين
                                </button>
                                @if ($canCreate)
                                    <a href="{{ route('Reservations.create') }}" class="btn btn-sm btn-primary">
                                        <i class="feather icon-plus"></i> حجز جديد
                                    </a>
                                @endif
                            </div>
                        </div>

                        <div class="row rc-filter-row">
                            <div class="col-xl col-md-4 col-sm-6">
                                <div class="form-group mb-0">
                                    <label for="rc-f-node">تابع لـ</label>
                                    <select id="rc-f-node" class="form-control rc-f" data-key="tree_node">
                                        <option value="">الكل</option>
                                        @foreach ($nodeOptions as $node)
                                            <option value="{{ $node['id'] }}">{{ $node['label'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-xl col-md-4 col-sm-6">
                                <div class="form-group mb-0">
                                    <label for="rc-f-employee">الموظّف</label>
                                    <select id="rc-f-employee" class="form-control rc-f" data-key="employee_id">
                                        <option value="">الكل</option>
                                        @foreach ($employees as $employee)
                                            <option value="{{ $employee->id }}">{{ $employee->full_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-xl col-md-4 col-sm-6">
                                <div class="form-group mb-0">
                                    <label for="rc-f-status">حالة الحجز</label>
                                    <select id="rc-f-status" class="form-control rc-f" data-key="status[]">
                                        <option value="">الكل</option>
                                        @foreach ($statuses as $status)
                                            <option value="{{ $status->value }}">{{ $status->label() }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-xl col-md-6 col-sm-6">
                                <div class="form-group mb-0">
                                    <label for="rc-f-service">نوع الخدمة</label>
                                    <select id="rc-f-service" class="form-control rc-f" data-key="product_id">
                                        <option value="">الكل</option>
                                        @foreach ($services as $service)
                                            <option value="{{ $service->id }}">{{ $service->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-xl col-md-6 col-sm-12">
                                <div class="form-group mb-0">
                                    <label for="rc-f-client">العميل</label>
                                    {{-- بحثٌ حيّ — يُهيّأ أدناه بـAJAX فيُستثنى من التهيئة العامّة --}}
                                    <select id="rc-f-client" class="form-control rc-f" data-key="client_id" data-no-select2>
                                        <option value="">الكل</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="rc-card" style="position:relative">
                    <div class="rc-bar">
                        <div class="rc-quick" id="rc-quick">
                            <button type="button" data-jump="0">اليوم</button>
                            <button type="button" data-jump="1">غداً</button>
                            <button type="button" data-jump="-1">أمس</button>
                        </div>
                        <div class="rc-nav">
                            <button type="button" class="rc-nav__arrow" data-step="-1" aria-label="السابق"><i class="feather icon-chevron-right"></i></button>
                            <span class="rc-nav__label">
                                <i class="feather icon-calendar"></i>
                                <span id="rc-label"></span>
                                <input type="date" id="rc-date" aria-label="اختر التاريخ">
                            </span>
                            <button type="button" class="rc-nav__arrow" data-step="1" aria-label="التالي"><i class="feather icon-chevron-left"></i></button>
                        </div>
                    </div>

                    <div id="rc-stage"></div>
                    <div class="rc-loading" id="rc-loading">جارٍ التحميل…</div>

                    <div class="rc-foot">
                        <div class="rc-legend">
                            @foreach ($legend as $status)
                                <span><i style="background:{{ $status->color() }}"></i> {{ $status->label() }}</span>
                            @endforeach
                        </div>
                        <button type="button" class="rc-foot__btn" data-jump="0"><i class="feather icon-calendar"></i> عرض اليوم</button>
                    </div>
                </div>
            </section>
        </div>
    </div>
@endsection

@section('scripts')
    <script>
        (function () {
            const cfg = {
                feed: @json(route('Reservations.calendar.feed')),
                clients: @json(route('Reservations.clients')),
                index: @json(route('Reservations.index')),
                create: @json($canCreate ? route('Reservations.create') : null),
                view: @json($defaultView),
            };

            const $ = window.jQuery;
            const el = id => document.getElementById(id);
            const stage = el('rc-stage');

            // ─────────── التواريخ ───────────
            const pad = n => String(n).padStart(2, '0');
            const iso = d => d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());
            const parse = s => { const [y, m, d] = s.split('-').map(Number); return new Date(y, m - 1, d); };
            const addDays = (d, n) => { const x = new Date(d); x.setDate(x.getDate() + n); return x; };
            const today = iso(new Date());
            // الأسبوعُ يبدأ السبت (getDay: السبت = 6)
            const weekStart = d => addDays(d, -((d.getDay() + 1) % 7));
            const toMin = t => { const [h, m] = t.split(':').map(Number); return h * 60 + m; };
            const toTime = m => pad(Math.floor(m / 60)) + ':' + pad(m % 60);

            const fmtLong = new Intl.DateTimeFormat('ar-u-nu-latn', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
            const fmtMonth = new Intl.DateTimeFormat('ar-u-nu-latn', { month: 'long', year: 'numeric' });
            const fmtDay = new Intl.DateTimeFormat('ar-u-nu-latn', { weekday: 'long' });
            const fmtShort = new Intl.DateTimeFormat('ar-u-nu-latn', { day: 'numeric', month: 'long' });
            const DOW = ['السبت', 'الأحد', 'الإثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة'];

            const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

            // ─────────── الحالة ───────────
            const qs = new URLSearchParams(location.search);
            const state = {
                view: ['day', 'week', 'month', 'agenda'].includes(qs.get('view')) ? qs.get('view') : cfg.view,
                date: /^\d{4}-\d{2}-\d{2}$/.test(qs.get('date') || '') ? qs.get('date') : today,
                miniMonth: null,
                events: [],
                columns: [],
            };

            const filters = Array.from(document.querySelectorAll('.rc-f'));

            const range = () => {
                const d = parse(state.date);
                if (state.view === 'day') return [d, d];
                if (state.view === 'month') {
                    const first = new Date(d.getFullYear(), d.getMonth(), 1);
                    const last = new Date(d.getFullYear(), d.getMonth() + 1, 0);
                    return [weekStart(first), addDays(weekStart(last), 6)];
                }
                const s = weekStart(d);
                return [s, addDays(s, 6)];
            };

            const filterParams = () => {
                const p = new URLSearchParams();
                filters.forEach(s => { if (s.value) p.set(s.dataset.key, s.value); });
                return p;
            };

            // ─────────── الجلب ───────────
            let token = 0;
            const load = () => {
                const [from, to] = range();
                const p = filterParams();
                p.set('start', iso(from));
                p.set('end', iso(to));
                if (state.view === 'day') p.set('columns', '1');

                const mine = ++token;
                el('rc-loading').classList.add('is-on');

                // ما في الشريط الجانبيّ يخصّ اليوم المختار — قد يقع خارج مدى الشهر المعروض؟ لا: المدى يحويه دائماً
                fetch(cfg.feed + '?' + p.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' } })
                    .then(r => r.json())
                    .then(data => {
                        if (mine !== token) return;
                        state.events = data.events || [];
                        state.columns = data.columns || [];
                        render();
                    })
                    .catch(() => {
                        if (mine !== token) return;
                        stage.innerHTML = '<div class="rc-empty">تعذّر تحميل الحجوزات — حدّث الصفحة.</div>';
                    })
                    .finally(() => { if (mine === token) el('rc-loading').classList.remove('is-on'); });

                loadMini();
                syncUrl();
            };

            const syncUrl = () => {
                const u = new URL(location.href);
                u.searchParams.set('view', state.view);
                u.searchParams.set('date', state.date);
                history.replaceState(null, '', u);
            };

            // ─────────── الرسم ───────────
            const render = () => {
                document.querySelectorAll('.rc-views button').forEach(b => b.classList.toggle('is-active', b.dataset.view === state.view));
                const offset = Math.round((parse(state.date) - parse(today)) / 864e5);
                document.querySelectorAll('#rc-quick button').forEach(b => b.classList.toggle('is-active', Number(b.dataset.jump) === offset));
                el('rc-date').value = state.date;

                const d = parse(state.date);
                if (state.view === 'day') {
                    el('rc-label').textContent = fmtLong.format(d);
                } else if (state.view === 'month') {
                    el('rc-label').textContent = fmtMonth.format(d);
                } else {
                    const [s, e] = range();
                    el('rc-label').textContent = fmtShort.format(s) + ' – ' + fmtShort.format(e) + ' ' + e.getFullYear();
                }

                ({ day: renderDay, week: renderWeek, month: renderMonth, agenda: renderAgenda })[state.view]();
                renderStats();
                renderSideList();
            };

            const eventsIn = () => {
                if (state.view === 'day') return state.events.filter(e => e.date === state.date);
                if (state.view === 'month') {
                    const d = parse(state.date);
                    const prefix = d.getFullYear() + '-' + pad(d.getMonth() + 1);
                    return state.events.filter(e => e.date.startsWith(prefix));
                }
                return state.events;
            };

            const renderStats = () => {
                const list = eventsIn();
                const count = st => list.filter(e => st.includes(e.status)).length;
                const set = (k, v) => { document.querySelector(`[data-stat="${k}"]`).textContent = v; };
                set('total', list.length);
                set('confirmed', count(['confirmed', 'checked_in', 'in_progress']));
                set('pending', count(['pending', 'draft', 'rescheduled']));
                set('completed', count(['completed']));
                set('cancelled', count(['cancelled', 'rejected', 'no_show']));
                el('rc-total-label').textContent = {
                    day: state.date === today ? 'إجمالي حجوزات اليوم' : 'إجمالي حجوزات ' + fmtDay.format(parse(state.date)),
                    week: 'إجمالي حجوزات الأسبوع', agenda: 'إجمالي حجوزات الأسبوع', month: 'إجمالي حجوزات الشهر',
                }[state.view];
            };

            // بطاقةُ حجزٍ في شبكة الوقت
            const card = (e, top, height, left, width, extra) => {
                const short = height < 52 ? ' is-short' : '';
                return `<a class="rc-ev${short}" href="${esc(e.url)}" title="${esc(e.code + ' — ' + e.client + (e.employee ? ' — ' + e.employee : ''))}"
                    style="--ev:${esc(e.color)}; top:${top}px; height:${Math.max(height - 3, 22)}px; inset-inline-start:calc(${left}% + 4px); width:calc(${width}% - 8px)">
                    <div class="rc-ev__top"><span class="rc-ev__client">${esc(e.client || e.code)}</span><span class="rc-ev__status">${esc(e.status_label)}</span></div>
                    <div class="rc-ev__service">${esc(extra || e.service || '—')}</div>
                    <div class="rc-ev__time">${esc(e.start)} - ${esc(e.end)}</div>
                </a>`;
            };

            // الحجوزاتُ المتداخلة في عمودٍ واحد تتجاور بدل أن تتراكب
            const layout = list => {
                const sorted = [...list].sort((a, b) => toMin(a.start) - toMin(b.start) || toMin(b.end) - toMin(a.end));
                const out = [];
                let cluster = [], clusterEnd = -1;
                const flush = () => {
                    const lanes = [];
                    cluster.forEach(e => {
                        let lane = lanes.findIndex(end => end <= toMin(e.start));
                        if (lane === -1) { lane = lanes.length; lanes.push(0); }
                        lanes[lane] = toMin(e.end);
                        e._lane = lane;
                    });
                    cluster.forEach(e => out.push({ e, lane: e._lane, lanes: lanes.length }));
                    cluster = [];
                };
                sorted.forEach(e => {
                    if (toMin(e.start) >= clusterEnd && cluster.length) flush();
                    cluster.push(e);
                    clusterEnd = Math.max(clusterEnd, toMin(e.end));
                });
                if (cluster.length) flush();
                return out;
            };

            // مدى الساعات: من أبكر وردية/حجز إلى آخرها، ولا يقلّ عن 08:00–18:00
            const hourSpan = (list, shifts) => {
                let from = 8 * 60, to = 18 * 60;
                list.forEach(e => { from = Math.min(from, toMin(e.start)); to = Math.max(to, toMin(e.end)); });
                shifts.forEach(s => { if (s) { from = Math.min(from, toMin(s.start)); to = Math.max(to, toMin(s.end)); } });
                return [Math.floor(from / 60), Math.min(24, Math.ceil(to / 60))];
            };

            const H = 64;
            const timeGrid = (cols, list, keyOf, isToday) => {
                const [h0, h1] = hourSpan(list, cols.map(c => c.shift));
                const height = (h1 - h0) * H;
                const px = t => (toMin(t) - h0 * 60) / 60 * H;
                const nowMin = new Date().getHours() * 60 + new Date().getMinutes();

                let html = `<div class="rc-grid-wrap"><div class="rc-grid" style="grid-template-columns:repeat(${cols.length}, minmax(190px, 1fr)) 64px">`;

                cols.forEach(c => {
                    html += `<div class="rc-head${isToday(c) ? ' is-today' : ''}">
                        <div style="text-align:center;min-width:0"><div class="rc-head__name">${esc(c.name)}</div>${c.sub ? `<div class="rc-head__sub">${esc(c.sub)}</div>` : ''}</div>
                        ${c.icon ? `<span class="rc-head__icon"><i class="feather icon-user"></i></span>` : ''}
                    </div>`;
                });
                html += `<div class="rc-head rc-head--axis"></div>`;

                cols.forEach(c => {
                    const mine = list.filter(e => keyOf(e) === c.key);
                    html += `<div class="rc-col${isToday(c) ? ' is-today' : ''}" style="height:${height}px" data-date="${esc(c.date)}" data-employee="${esc(c.employee || '')}">`;

                    // ما خارج الوردية مظلَّل — وموظّفٌ بلا وردية ذلك اليوم عمودُه كلُّه مظلَّل
                    if (c.hasShift) {
                        if (!c.shift) {
                            html += `<div class="rc-col__off" style="top:0;height:${height}px"></div>`;
                        } else {
                            const a = px(c.shift.start), b = px(c.shift.end);
                            if (a > 0) html += `<div class="rc-col__off" style="top:0;height:${a}px"></div>`;
                            if (b < height) html += `<div class="rc-col__off" style="top:${b}px;height:${height - b}px"></div>`;
                        }
                    }

                    // خاناتُ نصف ساعة داخل الوردية: نقرةٌ تفتح «حجز جديد» معبّأً
                    if (cfg.create) {
                        const s0 = c.shift ? toMin(c.shift.start) : h0 * 60;
                        const s1 = c.shift ? toMin(c.shift.end) : h1 * 60;
                        if (!c.hasShift || c.shift) {
                            for (let m = s0; m + 30 <= s1; m += 30) {
                                html += `<div class="rc-col__slot" data-time="${toTime(m)}" style="top:${(m - h0 * 60) / 60 * H}px;height:${H / 2}px" title="حجز جديد ${toTime(m)}"></div>`;
                            }
                        }
                    }

                    layout(mine).forEach(({ e, lane, lanes }) => {
                        const top = px(e.start);
                        html += card(e, top, px(e.end) - top, lane * 100 / lanes, 100 / lanes, c.extra ? c.extra(e) : null);
                    });

                    if (isToday(c) && nowMin >= h0 * 60 && nowMin <= h1 * 60) {
                        html += `<div class="rc-now" style="top:${(nowMin - h0 * 60) / 60 * H}px"></div>`;
                    }
                    html += `</div>`;
                });

                html += `<div class="rc-axis" style="height:${height}px">`;
                for (let h = h0; h < h1; h++) {
                    html += `<span style="top:${(h - h0) * H}px">${pad(h)}:00</span>`;
                }
                html += `</div></div></div>`;
                return html;
            };

            const renderDay = () => {
                const list = state.events.filter(e => e.date === state.date);
                const cols = state.columns.map(c => ({
                    key: c.id, name: c.name, sub: c.title, icon: true, date: state.date,
                    employee: c.id, shift: c.shift, hasShift: true,
                }));

                // حجوزاتٌ بلا موظّف (قديمة) في عمودٍ خاصّ كي لا تختفي
                if (list.some(e => !e.employee_id)) {
                    cols.push({ key: null, name: 'بلا موظّف', sub: '', icon: true, date: state.date, employee: '', shift: null, hasShift: false });
                }

                if (!cols.length) {
                    stage.innerHTML = '<div class="rc-empty">لا يوجد موظفون يُحجز لهم — راجع الورديات في الموارد البشرية وتبويب «الموظفون» في إعدادات الحجوزات.</div>';
                    return;
                }

                stage.innerHTML = timeGrid(cols, list, e => e.employee_id || null, () => state.date === today);
            };

            const renderWeek = () => {
                const [s] = range();
                const cols = [...Array(7)].map((_, i) => {
                    const d = addDays(s, i);
                    return { key: iso(d), name: DOW[i], sub: fmtShort.format(d), date: iso(d), hasShift: false, shift: null, extra: e => (e.service || '—') + (e.employee ? ' · ' + e.employee : '') };
                });
                stage.innerHTML = timeGrid(cols, state.events, e => e.date, c => c.date === today);
            };

            const renderMonth = () => {
                const d = parse(state.date);
                const [s, e] = range();
                const byDate = {};
                state.events.forEach(ev => (byDate[ev.date] = byDate[ev.date] || []).push(ev));

                let html = '<div class="rc-month">' + DOW.map(n => `<div class="rc-month__dow">${n}</div>`).join('');
                for (let x = s; x <= e; x = addDays(x, 1)) {
                    const key = iso(x);
                    const list = byDate[key] || [];
                    const cls = (x.getMonth() !== d.getMonth() ? ' is-out' : '') + (key === today ? ' is-today' : '');
                    html += `<div class="rc-month__cell${cls}" data-goto="${key}"><span class="rc-month__num">${x.getDate()}</span>`;
                    list.slice(0, 3).forEach(ev => {
                        html += `<a class="rc-chip" style="--ev:${esc(ev.color)}" href="${esc(ev.url)}" title="${esc(ev.status_label + ' — ' + ev.client)}"><b>${esc(ev.start)}</b> ${esc(ev.client)}</a>`;
                    });
                    if (list.length > 3) html += `<span class="rc-more">+${list.length - 3} أخرى</span>`;
                    html += '</div>';
                }
                stage.innerHTML = html + '</div>';
            };

            const row = e => `<a class="rc-row" href="${esc(e.url)}">
                    <span class="rc-row__time">${esc(e.start)}</span>
                    <span class="rc-row__client">${esc(e.client)} <small class="rc-row__muted">${esc(e.code)}</small></span>
                    <span class="rc-row__muted">${esc(e.service || '—')}</span>
                    <span class="rc-row__muted">${esc(e.employee || '—')}</span>
                    <span class="rc-badge" style="--ev:${esc(e.color)}">${esc(e.status_label)}</span>
                </a>`;

            const renderAgenda = () => {
                const [s] = range();
                let html = '';
                for (let i = 0; i < 7; i++) {
                    const key = iso(addDays(s, i));
                    const list = state.events.filter(e => e.date === key);
                    if (!list.length) continue;
                    html += `<div class="rc-agenda__day">${esc(fmtLong.format(parse(key)))} <small class="rc-row__muted">(${list.length})</small></div>` + list.map(row).join('');
                }
                stage.innerHTML = html || '<div class="rc-empty">لا حجوزات في هذا الأسبوع.</div>';
            };

            // ─────────── قائمة حجوزات اليوم ───────────
            const renderSideList = () => {
                const term = el('rc-search').value.trim().toLowerCase();
                const list = state.events
                    .filter(e => e.date === state.date)
                    .filter(e => !term || (e.client + ' ' + e.code).toLowerCase().includes(term));

                el('rc-list-title').textContent = state.date === today ? 'قائمة حجوزات اليوم' : 'حجوزات ' + fmtLong.format(parse(state.date));
                el('rc-all-link').href = cfg.index + '?date_from=' + state.date + '&date_to=' + state.date;

                el('rc-today-list').innerHTML = list.length
                    ? list.map(e => `<a class="rc-today__row" href="${esc(e.url)}">
                            <b style="direction:ltr;text-align:end">${esc(e.start)}</b>
                            <b style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis">${esc(e.client)}</b>
                            <span class="rc-row__muted">${esc(e.service || '—')}</span>
                            <span class="rc-badge" style="--ev:${esc(e.color)}">${esc(e.status_label)}</span>
                            <i class="feather icon-chevron-left"></i>
                        </a>`).join('')
                    : '<div class="rc-empty">لا حجوزات في هذا اليوم.</div>';
            };

            // ─────────── التقويم المصغّر ───────────
            let miniToken = 0;
            const loadMini = () => {
                const d = parse(state.date);
                state.miniMonth = state.miniMonth || new Date(d.getFullYear(), d.getMonth(), 1);
                const m = state.miniMonth;
                const s = weekStart(m);
                const e = addDays(weekStart(new Date(m.getFullYear(), m.getMonth() + 1, 0)), 6);

                const p = filterParams();
                p.set('mode', 'counts');
                p.set('start', iso(s));
                p.set('end', iso(e));

                const mine = ++miniToken;
                renderMini({});
                fetch(cfg.feed + '?' + p.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' } })
                    .then(r => r.json())
                    .then(data => { if (mine === miniToken) renderMini(data.counts || {}); })
                    .catch(() => {});
            };

            const renderMini = counts => {
                const m = state.miniMonth;
                el('rc-mini-title').textContent = fmtMonth.format(m);
                const s = weekStart(m);
                const e = addDays(weekStart(new Date(m.getFullYear(), m.getMonth() + 1, 0)), 6);
                let html = DOW.map(n => `<div class="rc-mini__dow">${n}</div>`).join('');
                for (let x = s; x <= e; x = addDays(x, 1)) {
                    const key = iso(x);
                    const cls = (x.getMonth() !== m.getMonth() ? ' is-out' : '')
                        + (key === today ? ' is-today' : '')
                        + (key === state.date ? ' is-picked' : '')
                        + (counts[key] ? ' has-dot' : '');
                    html += `<button type="button" class="rc-mini__day${cls}" data-goto="${key}" title="${counts[key] ? counts[key] + ' حجز' : ''}">${x.getDate()}</button>`;
                }
                el('rc-mini-grid').innerHTML = html;
            };

            // ─────────── التفاعل ───────────
            const go = (date, view) => {
                state.date = date;
                if (view) state.view = view;
                const d = parse(date);
                state.miniMonth = new Date(d.getFullYear(), d.getMonth(), 1);
                load();
            };

            document.querySelectorAll('.rc-views button').forEach(b => b.addEventListener('click', () => go(state.date, b.dataset.view)));
            document.querySelectorAll('[data-jump]').forEach(b => b.addEventListener('click', () => go(iso(addDays(parse(today), Number(b.dataset.jump))))));
            document.querySelectorAll('[data-step]').forEach(b => b.addEventListener('click', () => {
                const d = parse(state.date);
                const n = Number(b.dataset.step);
                if (state.view === 'day') go(iso(addDays(d, n)));
                else if (state.view === 'month') go(iso(new Date(d.getFullYear(), d.getMonth() + n, 1)));
                else go(iso(addDays(d, 7 * n)));
            }));
            el('rc-date').addEventListener('change', e => { if (e.target.value) go(e.target.value); });
            el('rc-search').addEventListener('input', renderSideList);

            document.querySelectorAll('[data-mini]').forEach(b => b.addEventListener('click', () => {
                const m = state.miniMonth;
                state.miniMonth = new Date(m.getFullYear(), m.getMonth() + Number(b.dataset.mini), 1);
                loadMini();
            }));
            el('rc-mini-grid').addEventListener('click', e => {
                const b = e.target.closest('[data-goto]');
                if (b) go(b.dataset.goto, state.view === 'month' ? 'day' : null);
            });

            stage.addEventListener('click', e => {
                // خليّةُ شهرٍ ← عرضُ ذلك اليوم (والنقرُ على حجزٍ يفتحه كرابط)
                const cell = e.target.closest('.rc-month__cell');
                if (cell && !e.target.closest('a')) { go(cell.dataset.goto, 'day'); return; }

                // خانةٌ فارغة ← «حجز جديد» بالموظّف والتاريخ والوقت
                const slot = e.target.closest('.rc-col__slot');
                if (slot && cfg.create) {
                    const col = slot.closest('.rc-col');
                    const p = new URLSearchParams({ reservation_date: col.dataset.date, start_time: slot.dataset.time });
                    if (col.dataset.employee) p.set('employee_id', col.dataset.employee);
                    location.href = cfg.create + '?' + p.toString();
                }
            });

            // الفلاتر يلبسها select2 (`partials/select_scripts`) وتُعلن تغييرَها بـjQuery — فالربطُ بـjQuery
            const refilter = () => { state.miniMonth = null; load(); };
            if ($) {
                $(filters).on('change', refilter);
                if ($.fn.select2) {
                    $('#rc-f-client').select2({
                        dir: 'rtl', width: '100%', allowClear: true, placeholder: 'الكل', minimumInputLength: 0,
                        ajax: { url: cfg.clients, dataType: 'json', delay: 250, data: p => ({ q: p.term }), processResults: d => ({ results: d.results }) },
                    });
                }
            } else {
                filters.forEach(s => s.addEventListener('change', refilter));
            }

            el('rc-reset').addEventListener('click', () => {
                filters.forEach(s => { s.value = ''; if ($ && $(s).data('select2')) $(s).trigger('change.select2'); });
                refilter();
            });

            // خطُّ «الآن» يتقدّم كلَّ دقيقة
            setInterval(() => { if (state.view === 'day' || state.view === 'week') render(); }, 60000);

            load();

            // التمريرُ إلى الآن أو إلى أوّل حجز في عرض اليوم
            const firstScroll = new MutationObserver(() => {
                const wrap = stage.querySelector('.rc-grid-wrap');
                const target = stage.querySelector('.rc-now') || stage.querySelector('.rc-ev');
                if (wrap && target) {
                    wrap.scrollTop = Math.max(0, target.offsetTop - 80);
                    firstScroll.disconnect();
                }
            });
            firstScroll.observe(stage, { childList: true });
        })();
    </script>
@endsection
