{{-- لوحة شاشات قابلة للبحث — يشترك فيها مركز الإعدادات العامّة ومركز
     إعدادات المبيعات.

     كانت هذه الأنماط ومعها سكربت البحث مكتوبةً داخل قالب لوحة الإعدادات
     وحدها، فلمّا لزمت اللوحةَ الثانية كان الخياران: نسخها فتتباعد اللوحتان
     مع أوّل تعديل، أو فصلها هنا. وهي مفصولة.

     يتوقّع:
       $groups            مخرَج `…SettingsNavigation::visible()`
       $hubSearchLabel    نصّ حقل البحث
       $hubCountLabel     تسمية العدّاد
       $hubEmptyLabel     نصّ «لا نتيجة»

     يتطلّب متغيّرات `--ax-*` — ضمِّن `sitting::components._account_ui` قبله. --}}

@php
    $hubSearchLabel ??= __('settings.dashboard.search_placeholder');
    $hubCountLabel ??= __('settings.dashboard.screens_available');
    $hubEmptyLabel ??= __('settings.dashboard.no_match');
    // معرّف فريد لكلّ استدعاء: لو وُضعت لوحتان في صفحة واحدة يوماً لم
    // يتشبّث سكربت الأولى بعناصر الثانية
    $hubId = 'hub'.substr(md5($hubSearchLabel), 0, 6);
@endphp

<style>
    /* ══ شريط الأدوات ══ */
    .sd-bar {
        position: sticky; top: 0; z-index: 5;
        display: flex; flex-wrap: wrap; align-items: center; gap: .7rem;
        background: rgba(255,255,255,.92); backdrop-filter: blur(6px);
        border: 1px solid var(--ax-line); border-radius: 12px;
        padding: .6rem .75rem; margin-bottom: 1.1rem; box-shadow: var(--ax-shadow);
    }
    .sd-find { position: relative; flex: 1 1 260px; min-width: 0; }
    .sd-find > i.lead {
        position: absolute; top: 50%; transform: translateY(-50%); inset-inline-start: .7rem;
        color: var(--ax-ink-3); font-size: .95rem; pointer-events: none;
    }
    .sd-find input {
        width: 100%; height: 40px; border: 1px solid #dcd9ea; border-radius: 9px;
        padding-inline: 2.2rem; font-size: .88rem; color: var(--ax-ink); background: #fff;
    }
    .sd-find input:focus { outline: 0; border-color: var(--ax-brand); box-shadow: 0 0 0 3px rgba(115,103,240,.14); }
    .sd-find .clear {
        position: absolute; top: 50%; transform: translateY(-50%); inset-inline-end: .5rem;
        border: 0; background: transparent; color: var(--ax-ink-3); font-size: 1.05rem;
        line-height: 1; padding: .25rem .35rem; border-radius: 6px; cursor: pointer; display: none;
    }
    .sd-find .clear:hover { background: var(--ax-bg); color: var(--ax-ink); }
    .sd-find .kbd {
        position: absolute; top: 50%; transform: translateY(-50%); inset-inline-end: .6rem;
        font-size: .68rem; color: var(--ax-ink-3); border: 1px solid var(--ax-line);
        border-radius: 5px; padding: .05rem .35rem; background: var(--ax-bg); pointer-events: none;
    }
    .sd-count { font-size: .78rem; color: var(--ax-ink-2); white-space: nowrap; }

    /* ══ الأقسام ══ */
    .sd-group { margin-bottom: 1.5rem; }
    .sd-group > h3 {
        display: flex; align-items: center; gap: .55rem; margin: 0 0 .75rem;
        font-size: .92rem; font-weight: 700; color: var(--ax-ink);
    }
    .sd-group > h3 .gic {
        width: 26px; height: 26px; border-radius: 8px; display: grid; place-items: center;
        font-size: .82rem; background: var(--g-soft); color: var(--g); flex: 0 0 auto;
    }
    .sd-group > h3 .line { flex: 1; height: 1px; background: var(--ax-line); }
    .sd-group > h3 .n {
        font-size: .72rem; font-weight: 700; color: var(--ax-ink-3);
        background: var(--ax-bg); border: 1px solid var(--ax-line);
        border-radius: 20px; padding: .05rem .5rem;
    }

    /* ══ بطاقة الشاشة ══ */
    .sd-grid {
        display: grid; grid-template-columns: repeat(auto-fill, minmax(268px, 1fr));
        gap: .8rem; align-items: stretch;
    }
    /* عمود لا صفّ: الأيقونة والعنوان أعلى، والشرح تحتهما، والشارات
       في القاع — فتستوي حواف البطاقات مهما اختلف طول الشرح */
    .sd-card {
        position: relative; display: flex; flex-direction: column;
        background: #fff; border: 1px solid var(--ax-line); border-radius: 14px;
        padding: 1rem 1.05rem 1.05rem; text-decoration: none; color: inherit;
        box-shadow: 0 1px 2px rgba(47,44,64,.05);
        transition: border-color .18s, box-shadow .18s, transform .18s;
    }
    .sd-card:hover {
        border-color: var(--g); color: inherit; text-decoration: none;
        transform: translateY(-2px);
        box-shadow: 0 10px 26px -8px rgba(47,44,64,.22), 0 2px 6px rgba(47,44,64,.06);
    }
    .sd-card:focus-visible { outline: 2px solid var(--g); outline-offset: 3px; }

    .sd-card .top { display: flex; align-items: center; gap: .7rem; }
    .sd-card .ic {
        flex: 0 0 auto; width: 40px; height: 40px; border-radius: 12px;
        display: grid; place-items: center; font-size: 1.05rem;
        background: var(--g-soft); color: var(--g);
        box-shadow: inset 0 0 0 1px var(--g-ring);
        transition: background .18s, color .18s, box-shadow .18s;
    }
    /* الأيقونة تمتلئ بلون القسم عند المرور: إشارة واحدة واضحة
       تغني عن شريط جانبي وظلّ وحدّ ملوّن مجتمعة */
    .sd-card:hover .ic {
        background: var(--g); color: #fff;
        box-shadow: 0 4px 12px -2px var(--g-glow);
    }
    .sd-card b {
        flex: 1; min-width: 0; font-size: .94rem; font-weight: 600;
        color: var(--ax-ink); line-height: 1.45;
    }
    /* شرح سطرين بالضبط: أطول منها يقصّ، وأقصر يحجز مكانه —
       فتبقى الشارات على خطّ واحد في الصفّ كلّه */
    .sd-card small {
        display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;
        overflow: hidden; margin-top: .6rem; min-height: 2.4em;
        color: var(--ax-ink-3); font-size: .78rem; line-height: 1.6;
    }
    /* السهم يشير إلى جهة التقدّم: يساراً في RTL ويميناً في LTR،
       فالأيقونة تُقلَب بالاتجاه لا بالدوران حتى لا تشير إلى الخلف */
    .sd-card .go {
        flex: 0 0 auto; color: var(--g); font-size: .95rem;
        opacity: 0; transform: translateX(-5px); transition: opacity .18s, transform .18s;
    }
    [dir="ltr"] .sd-card .go { transform: translateX(5px) rotate(180deg); }
    .sd-card:hover .go { opacity: 1; transform: none; }
    [dir="ltr"] .sd-card:hover .go { opacity: 1; transform: rotate(180deg); }

    .sd-card .kids {
        margin-top: auto; padding-top: .7rem; display: flex; flex-wrap: wrap; gap: .3rem;
    }
    .sd-kid {
        font-size: .715rem; background: var(--ax-bg); color: var(--ax-ink-2);
        border: 1px solid var(--ax-line); border-radius: 7px; padding: .12rem .5rem;
        text-decoration: none; transition: background .18s, color .18s, border-color .18s;
    }
    .sd-card:hover .sd-kid { background: var(--g-soft); border-color: var(--g-ring); color: var(--g); }

    /* ألوان الأقسام: تمييز بصري يجعل المسح بالعين أسرع من ستّ كتل متطابقة.
       `ring` حدّ خفيف و`glow` هالة الأيقونة عند المرور — قيم صريحة لا
       color-mix، فتلك تسقط في المتصفحات الأقدم فتضيع الحدود معها */
    .g1 { --g: #7367f0; --g-soft: #f0eefe; --g-ring: #7367f038; --g-glow: #7367f073; }
    .g2 { --g: #1a8a4d; --g-soft: #e6f8ee; --g-ring: #1a8a4d38; --g-glow: #1a8a4d73; }
    .g3 { --g: #0b7fa8; --g-soft: #e3f5fb; --g-ring: #0b7fa838; --g-glow: #0b7fa873; }
    .g4 { --g: #a35f06; --g-soft: #fff2e2; --g-ring: #a35f0638; --g-glow: #a35f0673; }
    .g5 { --g: #b03232; --g-soft: #fdeaea; --g-ring: #b0323238; --g-glow: #b0323273; }
    .g6 { --g: #0f8f83; --g-soft: #e2f6f4; --g-ring: #0f8f8338; --g-glow: #0f8f8373; }

    .sd-none { display: none !important; }
    .sd-empty { display: none; text-align: center; padding: 2.5rem 1rem; color: var(--ax-ink-3); }
    .sd-empty i { font-size: 2.1rem; color: #d3cfe6; display: block; margin-bottom: .55rem; }
    .sd-empty b { display: block; color: var(--ax-ink-2); font-size: .95rem; }

    @media (max-width: 575px) {
        .sd-grid { grid-template-columns: 1fr; }
        .sd-find .kbd { display: none; }
    }
    @media (prefers-reduced-motion: reduce) {
        .sd-card, .sd-card .go { transition: none; }
        .sd-card:hover { transform: none; }
    }
</style>

<div class="sd-bar">
    <div class="sd-find">
        <i class="feather icon-search lead"></i>
        <input type="search" id="{{ $hubId }}Search" autocomplete="off"
            aria-label="{{ $hubSearchLabel }}" placeholder="{{ $hubSearchLabel }}">
        <span class="kbd" id="{{ $hubId }}Kbd">/</span>
        <button type="button" class="clear" id="{{ $hubId }}Clear" aria-label="{{ __('settings.common.cancel') }}">&times;</button>
    </div>
    <span class="sd-count" id="{{ $hubId }}Count" aria-live="polite">
        {{ $hubCountLabel }}: {{ collect($groups)->sum(fn ($group) => count($group['items'])) }}
    </span>
</div>

<div id="{{ $hubId }}List">
    @foreach ($groups as $groupName => $group)
        @php $tone = 'g'.($loop->index % 6 + 1); @endphp
        <section class="sd-group {{ $tone }}" data-group data-name="{{ $groupName }}">
            <h3>
                <span class="gic"><i class="feather icon-{{ $group['icon'] }}"></i></span>
                {{ $groupName }}
                <span class="n">{{ count($group['items']) }}</span>
                <span class="line"></span>
            </h3>
            <div class="sd-grid">
                @foreach ($group['items'] as $item)
                    <a href="{{ $item['url'] }}" class="sd-card"
                        data-search="{{ $item['label'] }} {{ $item['hint'] ?? '' }} {{ collect($item['children'] ?? [])->pluck('label')->implode(' ') }} {{ $groupName }}">
                        <span class="top">
                            <span class="ic"><i class="feather icon-{{ $item['icon'] }}"></i></span>
                            <b>{{ $item['label'] }}</b>
                            <i class="feather icon-chevron-left go"></i>
                        </span>
                        <small>{{ $item['hint'] ?? '' }}</small>
                        @if (! empty($item['children']))
                            <span class="kids">
                                @foreach ($item['children'] as $child)
                                    <span class="sd-kid">{{ $child['label'] }}</span>
                                @endforeach
                            </span>
                        @endif
                    </a>
                @endforeach
            </div>
        </section>
    @endforeach
</div>

<div class="sd-empty" id="{{ $hubId }}Empty">
    <i class="feather icon-search"></i>
    <b>{{ $hubEmptyLabel }}</b>
</div>

<script>
    (function () {
        var search = document.getElementById('{{ $hubId }}Search');
        if (!search) return;

        var scope = document.getElementById('{{ $hubId }}List');
        var clear = document.getElementById('{{ $hubId }}Clear');
        var kbd = document.getElementById('{{ $hubId }}Kbd');
        var counter = document.getElementById('{{ $hubId }}Count');
        var empty = document.getElementById('{{ $hubId }}Empty');
        var cards = Array.prototype.slice.call(scope.querySelectorAll('.sd-card'));
        var groups = Array.prototype.slice.call(scope.querySelectorAll('[data-group]'));
        var label = counter ? counter.textContent.split(':')[0].trim() : '';
        var total = cards.length;

        /**
         * توحيد العربية قبل المطابقة: «الاعدادات» و«الإعدادات» نصّان
         * مختلفان بايتاً ومتطابقان قصداً، ومن يكتب بلا همزة كان يخرج
         * بلا نتائج. تُوحَّد الألف والياء والتاء المربوطة ويُسقَط التشكيل.
         */
        function normalize(value) {
            return String(value)
                .toLowerCase()
                .replace(/[ً-ْٰـ]/g, '')
                .replace(/[آأإٱ]/g, 'ا')
                .replace(/ى/g, 'ي')
                .replace(/ة/g, 'ه')
                .replace(/ؤ/g, 'و')
                .replace(/ئ/g, 'ي')
                .replace(/\s+/g, ' ')
                .trim();
        }

        cards.forEach(function (card) {
            card.dataset.norm = normalize(card.dataset.search);
        });

        /**
         * مطابقة بالكلمات لا بالجملة: «لغة ترجمة» تجد البطاقة وإن تباعدت
         * الكلمتان فيها. وأداة التعريف تُتجاوَز لأن من يكتب «الاعدادات»
         * يقصد بطاقة «إعدادات» — والفرق بينهما حرفان لا معنى.
         */
        function matches(hay, tokens) {
            return tokens.every(function (token) {
                if (hay.indexOf(token) !== -1) return true;

                return token.length > 3 && token.slice(0, 2) === 'ال' && hay.indexOf(token.slice(2)) !== -1;
            });
        }

        function apply() {
            var term = normalize(search.value);
            var tokens = term ? term.split(' ').filter(Boolean) : [];
            var shown = 0;

            cards.forEach(function (card) {
                var match = !tokens.length || matches(card.dataset.norm, tokens);
                card.classList.toggle('sd-none', !match);
                if (match) shown++;
            });

            // القسم يختفي حين لا يبقى فيه بطاقة — وإلا بقي عنوانه معلّقاً
            groups.forEach(function (group) {
                group.classList.toggle('sd-none', !group.querySelector('.sd-card:not(.sd-none)'));
            });

            empty.style.display = shown ? 'none' : 'block';
            clear.style.display = search.value ? 'block' : 'none';
            if (kbd) kbd.style.display = search.value ? 'none' : '';
            if (counter) counter.textContent = label + ': ' + shown + (term ? ' / ' + total : '');
        }

        search.addEventListener('input', apply);

        search.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                search.value = '';
                apply();
            }
        });

        clear.addEventListener('click', function () {
            search.value = '';
            apply();
            search.focus();
        });

        // «/» تنقل التركيز إلى البحث ما لم يكن المستخدم يكتب في حقل آخر
        document.addEventListener('keydown', function (event) {
            if (event.key !== '/' || event.ctrlKey || event.metaKey || event.altKey) return;

            var tag = (event.target.tagName || '').toLowerCase();
            if (tag === 'input' || tag === 'textarea' || tag === 'select' || event.target.isContentEditable) return;

            event.preventDefault();
            search.focus();
            search.select();
        });
    })();
</script>
