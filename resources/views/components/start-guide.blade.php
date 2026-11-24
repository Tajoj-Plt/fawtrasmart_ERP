{{--
    «ابدأ من هنا» — خطواتُ تشغيل قسمٍ من نظامٍ فارغ، أعلى لوحة مؤشّراته.

    الشكلُ نفسُه الذي استقرّ في لوحة العملاء
    (`client::dashboard.client._start-guide`): مسارٌ واحد مرقّم من الأعلى إلى
    الأسفل، وكل خطوة عنوانٌ هو فعلٌ واضح، سطرُ «لماذا؟»، حالتُها من النظام،
    وزرٌّ يسمّي الشاشة. مطويٌّ عند فتح الصفحة دائماً.

    هذا القالب يرسم فقط؛ الخطواتُ وعدُّها يأتيان من قالب القسم
    (`sales::dashboard.partials.start-guide` ونظيره في المشتريات).

    ══════════════════ الوسائط ══════════════════

      startGuideTitle   عنوان اللوحة (مطلوب)
      startGuideHint    سطرٌ تحت العنوان (اختياري)
      startGuideGroups  [[عنوان المجموعة، أيقونة feather، خطوات]]
                        والخطوة: [عنوان، لماذا، حالة، زرّ]
                          حالة: ['ok'|'no', نصّ] أو null
                          زرّ:  [اسم المسار، مسموح؟، نصّ الزرّ] أو null
                        زرٌّ بلا صلاحية يظهر «يقوم بها المدير» ولا تختفي الخطوة.

    الأصناف كلُّها `stg-*` وتقرأ `--app-*` مباشرةً: القالب يُضمَّن في لوحاتٍ
    لكلٍّ منها بادئتُها (`sd-`، `pd-`) ومتغيّراتُها.
--}}
@php
    // `@include` يمرّر متغيّرات القالب الأب كلَّها — ففحصُ النوع يمنع متغيّراً
    // محليّاً في الأب من أن يصير وسيطاً هنا
    $stgTitle = (isset($startGuideTitle) && is_string($startGuideTitle) && $startGuideTitle !== '') ? $startGuideTitle : null;
    $stgHint = (isset($startGuideHint) && is_string($startGuideHint)) ? $startGuideHint : null;
    $stgGroups = (isset($startGuideGroups) && is_array($startGuideGroups)) ? $startGuideGroups : [];
    $stgNo = 0;
@endphp

@if ($stgTitle && $stgGroups)
    <details class="stg">
        <summary>
            <span class="stg__icon"><i class="feather icon-compass"></i></span>
            <span class="stg__head">
                {{ $stgTitle }}
                @if ($stgHint)
                    <small>{{ $stgHint }}</small>
                @endif
            </span>
            <span class="stg__toggle">
                <span class="when-closed">اعرض الخطوات <i class="feather icon-chevron-down"></i></span>
                <span class="when-open">إخفاء <i class="feather icon-chevron-up"></i></span>
            </span>
        </summary>

        <div class="stg__body">
            @foreach ($stgGroups as [$stgGroupTitle, $stgGroupIcon, $stgSteps])
                <p class="stg__group"><i class="feather icon-{{ $stgGroupIcon }}"></i> {{ $stgGroupTitle }}</p>
                <ol class="stg-steps">
                    @foreach ($stgSteps as [$stgStepTitle, $stgWhy, $stgState, $stgButton])
                        @php
                            $stgNo++;
                            // المسار يُبنى فقط إن كان مسجَّلاً: وحدةٌ معطَّلة لا تُسقط اللوحة
                            $stgHref = ($stgButton && $stgButton[1] && \Illuminate\Support\Facades\Route::has($stgButton[0]))
                                ? route($stgButton[0]) : null;
                        @endphp
                        <li class="stg-step {{ $stgState ? 'stg-step--'.$stgState[0] : '' }}">
                            <span class="stg-step__num">
                                @if ($stgState && $stgState[0] === 'ok')<i class="feather icon-check"></i>@else{{ $stgNo }}@endif
                            </span>
                            <div class="stg-step__main">
                                <div class="stg-step__text">
                                    <span class="stg-step__title">{{ $stgNo }}. {{ $stgStepTitle }}</span>
                                    <p class="stg-step__why">{{ $stgWhy }}</p>
                                    @if ($stgState)
                                        <span class="stg-step__state">
                                            <i class="feather icon-{{ $stgState[0] === 'ok' ? 'check-circle' : 'alert-circle' }}"></i> {{ $stgState[1] }}
                                        </span>
                                    @endif
                                </div>
                                @if ($stgHref)
                                    <a href="{{ $stgHref }}" class="stg-step__btn">{{ $stgButton[2] }} <i class="feather icon-arrow-left"></i></a>
                                @elseif ($stgButton)
                                    <span class="stg-step__admin"><i class="feather icon-lock"></i> يقوم بها المدير</span>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ol>
            @endforeach
        </div>
    </details>

    @once
        {{-- الأنماط داخل الجسم: القالب يُستدعى من لوحاتٍ تختلف أقسامُ أنماطها --}}
        <style>
            .stg {
                --stg-primary: var(--app-primary, #7367f0);
                --stg-border: var(--app-border, #e6e9f0);
                --stg-text: var(--app-text, #4b4b4b);
                --stg-muted: var(--app-muted, #7b8194);
                --stg-radius: calc(var(--app-radius, 8px) + 4px);
                background: var(--app-surface, #fff); border: 1px solid var(--stg-border);
                border-inline-start: 4px solid var(--stg-primary);
                border-radius: var(--stg-radius); margin-bottom: 1.25rem;
            }
            .stg > summary {
                list-style: none; cursor: pointer; padding: .8rem 1.25rem;
                display: flex; align-items: center; gap: .6rem; color: var(--stg-text);
                background: color-mix(in srgb, var(--stg-primary) 7%, transparent);
                border-radius: var(--stg-radius); outline: none;
            }
            .stg > summary:focus-visible { box-shadow: 0 0 0 2px color-mix(in srgb, var(--stg-primary) 45%, transparent); }
            .stg[open] > summary { border-radius: var(--stg-radius) var(--stg-radius) 0 0; }
            .stg > summary::-webkit-details-marker { display: none; }
            .stg > summary::marker { content: ''; }
            .stg__icon {
                width: 32px; height: 32px; border-radius: 50%; flex: none;
                display: inline-flex; align-items: center; justify-content: center;
                background: color-mix(in srgb, var(--stg-primary) 12%, transparent); color: var(--stg-primary);
            }
            .stg__head { flex: 1; min-width: 0; font-weight: 700; font-size: .95rem; }
            .stg__head small { display: block; font-weight: 500; font-size: .78rem; color: var(--stg-muted); }
            .stg__toggle {
                font-size: .8rem; font-weight: 700; white-space: nowrap; color: #fff; background: var(--stg-primary);
                border-radius: var(--app-button-radius, 6px); padding: .4rem .9rem;
            }
            .stg[open] .stg__toggle .when-closed,
            .stg:not([open]) .stg__toggle .when-open { display: none; }

            .stg__body { padding: 1rem 1.25rem 1.1rem; max-width: 900px; }
            .stg__group {
                font-size: .8rem; font-weight: 700; color: var(--stg-primary);
                margin: 0 0 .7rem; display: flex; align-items: center; gap: .4rem;
            }
            .stg__group:not(:first-child) { margin-top: 1.3rem; }

            /* الخطّ الرأسي يصل الأرقام — يُقرأ مساراً واحداً لا قائمةً مبعثرة */
            .stg-steps { list-style: none; margin: 0; padding: 0; }
            .stg-step { position: relative; display: flex; gap: .85rem; padding-bottom: 1rem; }
            .stg-step:last-child { padding-bottom: 0; }
            .stg-step:not(:last-child)::after {
                content: ''; position: absolute; top: 2rem; bottom: .2rem; inset-inline-start: calc(1rem - 1px);
                width: 2px; background: var(--stg-border);
            }
            .stg-step__num {
                width: 2rem; height: 2rem; border-radius: 50%; flex: none; z-index: 1;
                display: inline-flex; align-items: center; justify-content: center;
                font-size: .85rem; font-weight: 700; color: #fff; background: var(--stg-primary);
            }
            .stg-step--ok .stg-step__num { background: var(--app-success, #28c76f); }
            .stg-step--no .stg-step__num { background: var(--app-danger, #ea5455); }
            .stg-step__main { flex: 1; min-width: 0; display: flex; flex-wrap: wrap; align-items: flex-start; gap: .4rem 1rem; }
            .stg-step__text { flex: 1 1 320px; min-width: 0; }
            .stg-step__title { display: block; font-size: .9rem; font-weight: 700; color: var(--stg-text); margin-top: .25rem; }
            .stg-step__why { margin: .15rem 0 0; font-size: .8rem; color: var(--stg-muted); line-height: 1.7; }
            .stg-step__state { display: inline-flex; align-items: center; gap: .25rem; margin-top: .3rem; font-size: .75rem; font-weight: 700; }
            .stg-step--ok .stg-step__state { color: var(--app-success, #28c76f); }
            .stg-step--no .stg-step__state { color: var(--app-danger, #ea5455); }
            .stg-step__btn {
                flex: none; align-self: center; display: inline-flex; align-items: center; gap: .3rem;
                font-size: .78rem; font-weight: 600; text-decoration: none; white-space: nowrap;
                color: var(--stg-primary); border: 1px solid var(--stg-primary);
                border-radius: var(--app-button-radius, 6px); padding: .35rem .8rem;
            }
            .stg-step__btn:hover { background: var(--stg-primary); color: #fff; }
            .stg-step__admin { flex: none; align-self: center; font-size: .75rem; color: var(--stg-muted); white-space: nowrap; }
        </style>
    @endonce
@endif
