@extends('purchases::master')

{{-- إعدادات المشتريات — خيارات الأقسام كلها في شاشة واحدة.

     على قياس شاشة «إعدادات الفواتير» في المبيعات حرفاً بحرف: الورقةُ
     `sales::partials.settings-theme` (البادئة `sl-`)، وترويسةُ
     `sales::components.page-head`، وبطاقتا الإحصاء، وشريطُ «إعدادات النظام
     السارية»، ثمّ الأقسامُ تبويباتٍ في بطاقةٍ واحدة كلُّ مفتاحٍ فيها صفُّ
     `sl-switch-row`، وذيلُ الحفظ `sl-form-foot`. فمن ضبط المبيعات وجد
     المشتريات على الهيئة نفسها ولم يتعلّم شاشةً ثانية.

     سقطت النسخة السابقة بلوحها المصمَت وحلقتها وشريطها الجانبي وأنماطها
     الخاصّة (`ps-*`) — ثلاثةُ أنظمةٍ بصرية لشاشتَي إعداد متجاورتين أكثر
     من اللازم.

     بقي من الشاشة القديمة ما يُفيد: البحثُ عبر الأقسام كلِّها (يوحّد
     الهمزة والياء والتاء المربوطة)، ومؤشّرُ «تغييرات لم تُحفظ»، وزرُّ
     الإلغاء الذي يعيد المفاتيح إلى آخر حالةٍ محفوظة بلا إعادة تحميل، ولوحةُ
     الإرشاد أعلى الشاشة ولوحةٌ أصغر في كلّ قسم بشارات «ما يترتّب فعلاً».

     المفتاح هنا `purchases::components.switch` لا نظيره في المبيعات: النموذج
     يرسل مصفوفةً واحدة `settings[]` قيمةُ كلِّ مربّعٍ اسمُ مفتاحه، والحقلُ
     المخفيّ `0` الذي يسبق مفتاح المبيعات كان سيصل `settings[] = 0` فتردّه
     قاعدة `in:` في المتحكّم. --}}

@section('title')
    {{ __('purchase_settings.page.title') }}
@stop

@section('content')
    @include('sales::partials.settings-theme')
    @include('components.hint-tips')

    @php
        /*
         * «ما يترتّب فعلاً» — أسطرُ الإرشاد التي تقرأ القيمة الجارية لا
         * النظرية. `$active` من `PurchaseSettings::all()` مدموجةٌ مع
         * الافتراضيات، فكلّ مفتاح في السجلّ له قيمة هنا.
         */
        $fact = static fn (string $key): string => __(
            'purchase_settings.guide.facts.'.$key.'_'.(($active[$key] ?? false) ? 'on' : 'off')
        );

        /* حقول المورد الإلزامية والممنوع تكرارها — تُجمَع أسماؤها في سطرٍ
           واحد بدل سطرٍ لكلّ مفتاح */
        $supplierFieldFact = static function (string $prefix, string $on, string $none) use ($active): string {
            $fields = [];

            foreach (['name', 'mobile', 'tax_number', 'commercial_registration', 'email'] as $field) {
                if ($active['supplier_'.$prefix.'_'.$field] ?? false) {
                    $fields[] = __('purchase_settings.guide.supplier_fields.'.$field);
                }
            }

            return $fields === []
                ? __('purchase_settings.guide.facts.'.$none)
                : __('purchase_settings.guide.facts.'.$on, [
                    'fields' => implode(app()->getLocale() === 'ar' ? '، ' : ', ', $fields),
                ]);
        };

        /* أسطر كلّ قسم: المفاتيح التي تُقرأ حالتها، و`supplier_*` سطران مجمَّعان */
        $groupFacts = [
            'invoices' => ['default_received_invoices', 'default_paid_invoices', 'auto_payment', 'manual_invoice_status'],
            'orders' => ['manual_purchase_orders', 'manual_purchase_orders_status'],
            'quotations' => ['manual_quote_orders', 'manual_purchase_quotes'],
            'returns' => ['return_default_received', 'allow_multiple_returns'],
            'notices' => ['enable_debit_notice', 'notice_default_received'],
            'suppliers' => ['supplier_block_inactive'],
            'documents' => ['enable_settlement', 'total_discounts'],
        ];

        $factsFor = static function (string $groupKey) use ($fact, $groupFacts, $supplierFieldFact): array {
            $lines = array_map($fact, $groupFacts[$groupKey] ?? []);

            if ($groupKey === 'suppliers') {
                array_unshift(
                    $lines,
                    $supplierFieldFact('require', 'supplier_required', 'supplier_none_required'),
                    $supplierFieldFact('unique', 'supplier_unique', 'supplier_none_unique'),
                );
            }

            return $lines;
        };

        /* فقرات القسم: `a` و`b` ثابتان، و`c` لمن له سطرٌ ثالث */
        $groupLines = static function (string $groupKey): array {
            $lines = [];

            foreach (['a', 'b', 'c'] as $letter) {
                $key = 'purchase_settings.guide.groups.'.$groupKey.'.'.$letter;

                if (\Illuminate\Support\Facades\Lang::has($key)) {
                    $lines[] = __($key);
                }
            }

            return $lines;
        };

        /* مفتاحٌ يتبع مفتاحاً — نظيرُ `data-requires` في شاشة المبيعات:
           «مسلَّم افتراضياً» للإشعار المدين لا معنى له والإشعارُ نفسُه
           معطَّل، فيُعتَّم صفُّه حتى يُشعَل الأصل. تعتيمٌ لا تعطيل: المربّع
           المعطَّل لا يُرسَل، فتضيع قيمتُه عند الحفظ. */
        $requires = [
            'notice_default_received' => 'enable_debit_notice',
        ];

        $appliesLabel = __('purchase_settings.page.applies_label');
    @endphp

    <div class="content-body sl-scope">

        {{-- يُعرَّف قبل استدعاء الترويسة: `yieldContent` تقرأ ما سُجّل حتى تلك اللحظة --}}
        @section('page-actions')
            @permission('purchases.settings.edit')
                <button type="submit" form="psForm" class="btn btn-primary">
                    <i class="feather icon-save"></i> {{ __('purchase_settings.page.save') }}
                </button>
            @endpermission
        @endsection

        @include('sales::components.page-head', [
            'title' => __('purchase_settings.page.title'),
            'icon' => 'settings',
            'sub' => __('purchase_settings.page.subtitle'),
            'crumbs' => [
                __('sales_settings.ui.home') => route('dashboard'),
                __('purchase_settings.page.breadcrumb') => null,
            ],
        ])

        @include('purchases::layouts.alerts.success')
        @include('purchases::layouts.alerts.error')
        @include('sales::components.form-errors')

        {{-- ══════════ الإحصاء ══════════ --}}
        <div class="sl-stats">
            <div class="sl-stat">
                <div class="sl-stat__icon"><i class="feather icon-toggle-right"></i></div>
                <div class="sl-stat__body">
                    <p class="sl-stat__value sl-num">
                        <span id="psActiveCount">{{ $activeCount }}</span> / {{ $totalCount }}
                    </p>
                    <p class="sl-stat__label">{{ __('purchase_settings.page.active_count') }}</p>
                </div>
            </div>

            <div class="sl-stat">
                <div class="sl-stat__icon sl-stat__icon--info"><i class="feather icon-grid"></i></div>
                <div class="sl-stat__body">
                    <p class="sl-stat__value sl-num">{{ count($catalog) }}</p>
                    <p class="sl-stat__label">{{ __('purchase_settings.page.sections') }}</p>
                </div>
            </div>
        </div>

        {{-- إعدادات النظام التي تعمل بها هذه الشاشة: العملة التي تُنسَّق بها
             فاتورة الشراء، وطرق الدفع التي يُسدَّد منها «مدفوعة افتراضياً»،
             والفترة المحاسبية التي قد تردّ الحفظ. بنودُ المبيعات الخاصّة
             (حساباتها وإشعاراتها) لا تُعرَض هنا --}}
        @include('sales::components.system-context', ['only' => ['currency', 'payments', 'period']])

        {{-- المسار PUT. `id` على النموذج ليصل إليه زرّ الحفظ في ترويسة
             الصفحة عبر `form=` — الزرّ خارج النموذج فلا يُرسله بغير ذلك --}}
        <form id="psForm" action="{{ route('purchases.settings.update') }}" method="POST">
            @csrf
            @method('PUT')

            {{-- ══════════ الإرشادات ══════════
                 مطويّةٌ عند فتح الصفحة: الشاشة نفسُها هي المقصود، والشرح
                 يُطلَب عند الحاجة إليه. شاراتُ «ما يترتّب فعلاً» تقرأ القيم
                 الجارية لا الافتراضية --}}
            @include('purchases::components.guide', [
                'key' => 'purchase-settings.hub',
                'open' => false,
                'title' => __('purchase_settings.guide.hub.title'),
                'lead' => __('purchase_settings.guide.hub.lead'),
                'blocks' => [
                    __('purchase_settings.guide.hub.groups.title') => [
                        __('purchase_settings.guide.hub.groups.a'),
                        __('purchase_settings.guide.hub.groups.b'),
                        __('purchase_settings.guide.hub.groups.c'),
                    ],
                    __('purchase_settings.guide.hub.first.title') => [
                        __('purchase_settings.guide.hub.first.a'),
                        __('purchase_settings.guide.hub.first.b'),
                        __('purchase_settings.guide.hub.first.c'),
                    ],
                    __('purchase_settings.guide.hub.save.title') => [
                        __('purchase_settings.guide.hub.save.a'),
                    ],
                ],
                'facts' => [
                    __('purchase_settings.guide.facts.active', ['on' => $activeCount, 'total' => $totalCount]),
                    $fact('default_received_invoices'),
                    $fact('default_paid_invoices'),
                    $fact('allow_multiple_returns'),
                    $fact('enable_debit_notice'),
                    $supplierFieldFact('require', 'supplier_required', 'supplier_none_required'),
                    $fact('enable_settlement'),
                ],
            ])

            {{-- بحثٌ يتجاوز التبويب كلَّه لمن يعرف ما يريد — على هيئة بحث
                 لوحة إعدادات المبيعات --}}
            <div class="sl-hubsearch">
                <i class="feather icon-search"></i>
                <input type="search" id="psSearch" autocomplete="off"
                       placeholder="{{ __('purchase_settings.page.search') }}"
                       aria-label="{{ __('purchase_settings.page.search') }}">
            </div>

            {{-- ══════════ الأقسام في تبويبات ══════════
                 سبعة أقسام بثلاثةٍ وعشرين مفتاحاً مكدَّسةً تطول حتى يضيع
                 أوّلها عن آخرها. التبويب يُبقي القسم الواحد في شاشة واحدة،
                 والحفظ يسري عليها كلِّها لأنّها نموذجٌ واحد --}}
            <div class="card" id="psCard">
                <div class="card-header p-0">
                    <ul class="nav sl-doc-tabs w-100" role="tablist">
                        @foreach ($catalog as $groupKey => $group)
                            <li class="nav-item">
                                <a class="nav-link {{ $loop->first ? 'active' : '' }}" data-bs-toggle="tab"
                                   href="#section-{{ $groupKey }}" role="tab" data-tab="{{ $groupKey }}">
                                    <i class="fas {{ $group['icon'] }}"></i>
                                    {{ __('purchase_settings.groups.'.$groupKey) }}
                                    <span class="badge badge-light-secondary sl-num" data-badge="{{ $groupKey }}">
                                        {{ \Modules\Purchases\Services\PurchaseSettings::activeIn($group['items']) }}/{{ count($group['items']) }}
                                    </span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>

                <div class="tab-content">
            @foreach ($catalog as $groupKey => $group)
                <div class="tab-pane {{ $loop->first ? 'active' : '' }}" id="section-{{ $groupKey }}" role="tabpanel"
                     data-panel="{{ $groupKey }}">
                    <div class="card-body">
                        <p class="sl-help" style="margin-top:0;margin-bottom:var(--sl-4)">
                            {{ __('purchase_settings.groups_hint.'.$groupKey) }}
                        </p>

                        {{-- إرشادُ القسم: ما يفعله كلُّ مفتاحٍ فيه وما يترتّب
                             على قيمته الجارية — كما تشرح شاشة إعدادات
                             الفواتير أقسامَها. يختفي في وضع البحث حيث تصير
                             النتائج قائمةً واحدة --}}
                        <div data-section-guide>
                            @include('purchases::components.guide', [
                                'key' => 'purchase-settings.'.$groupKey,
                                'open' => false,
                                'title' => __('purchase_settings.guide.groups.title'),
                                'blocks' => [$groupLines($groupKey)],
                                'facts' => $factsFor($groupKey),
                            ])
                        </div>

                        @foreach ($group['items'] as $key => $item)
                            @php $on = (bool) ($active[$key] ?? $item['default']); @endphp

                            <div class="sl-switch-row" data-option data-in="{{ $groupKey }}"
                                 @isset ($requires[$key]) data-requires="{{ $requires[$key] }}" @endisset
                                 data-search="{{ $item['label'] }} {{ $item['hint'] }} {{ __('purchase_settings.groups.'.$groupKey) }}">
                                @include('purchases::components.switch', [
                                    'id' => 'sw_'.$key,
                                    'name' => 'settings[]',
                                    'value' => $key,
                                    'hidden' => false,
                                    'checked' => $on,
                                    'title' => $item['label'],
                                    'badge' => __('purchase_settings.groups.'.$groupKey),
                                    'hint' => $item['hint'],
                                    'meta' => $appliesLabel.': '.$item['applies'],
                                ])
                            </div>
                        @endforeach

                        @if ($groupKey === 'employees')
                            {{-- الأقسامُ التي تُغذّي قوائمَ الموظفين — قيمةٌ لا مفتاح،
                                 تُحفَظ في `value` على صفّ المفتاح وتبقى محفوظةً وهو مطفأ --}}
                            <div class="sl-switch-row" data-option data-in="{{ $groupKey }}"
                                 data-requires="{{ \Modules\Purchases\Services\PurchaseSettings::EMPLOYEE_DEPARTMENTS_KEY }}"
                                 data-search="{{ __('purchase_settings.page.departments_label') }} {{ __('purchase_settings.groups.employees') }}">
                                <div class="sl-switch__label" style="flex:1 1 100%">
                                    <span class="sl-switch__title">{{ __('purchase_settings.page.departments_label') }}</span>
                                    <span class="sl-switch__hint">{{ __('purchase_settings.page.departments_hint') }}</span>
                                    <select name="departments[]" id="psDepartments" class="form-control select2" multiple
                                            data-placeholder="{{ __('purchase_settings.page.departments_placeholder') }}" style="margin-top:.5rem">
                                        @foreach ($departments as $department)
                                            <option value="{{ $department->id }}" @selected(in_array($department->id, $selectedDepartments, true))>
                                                {{ $department->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
                </div>

                <div class="sl-empty" id="psEmpty" hidden>
                    <div class="sl-empty__icon" aria-hidden="true"><i class="feather icon-search"></i></div>
                    <p class="sl-empty__title">{{ __('purchase_settings.page.no_match') }}</p>
                </div>
            </div>

            <div class="sl-form-foot">
                <span class="sl-help" style="margin:0" id="psUnsaved" hidden>
                    <i class="feather icon-alert-circle"></i> {{ __('purchase_settings.page.unsaved') }}
                </span>

                <div class="d-flex" style="gap:.5rem">
                    {{-- التراجع يعيد المربّعات إلى آخر حالة محفوظة — لا يعيد
                         تحميل الصفحة، فالتحميل يُفقد موضع التمرير والتبويب المفتوح --}}
                    <button type="button" class="btn btn-outline-secondary" id="psRevert">
                        {{ __('sales_settings.ui.cancel') }}
                    </button>

                    @permission('purchases.settings.edit')
                        <button type="submit" class="btn btn-primary">
                            <i class="feather icon-save"></i> {{ __('purchase_settings.page.save') }}
                        </button>
                    @endpermission
                </div>
            </div>
        </form>

        {{-- إعادة التعيين نموذج مستقلّ: زرّه داخل نموذج الحفظ كان سيُرسل
             الخيارات معه، فتُحفظ ثم تُستبدل في طلب واحد --}}
        <form action="{{ route('purchases.settings.reset') }}" method="POST" class="sl-form-foot"
              onsubmit="return confirm('{{ __('purchase_settings.page.reset_confirm') }}')">
            @csrf
            <button type="submit" class="btn btn-outline-secondary btn-sm">
                <i class="feather icon-rotate-ccw"></i> {{ __('purchase_settings.page.reset') }}
            </button>
        </form>
    </div>

    <style>
        /* المفتاح التابع لمفتاحه — يبقى معروضاً وهو مطفأ ليُعرف أنّه
           موجود، ويُعتَّم فلا يُظنّ أنّ تشغيله يكفي دون تشغيل الأصل */
        .sl-switch-row.is-off { opacity: .5; }

        /* الصفّ `display:flex` فيغلب `[hidden]` المتصفّح — البحث يخفي به */
        .sl-switch-row[hidden] { display: none; }

        /* شارة القسم بجانب العنوان — تظهر في وضع البحث وحده: خارج تبويبه
           يفقد الخيار سياقه، ونتيجةٌ بلا سياق تُربك أكثر مما تُفيد */
        .sl-switch__badge { display: none; margin-inline-start: .35rem; vertical-align: middle; }
        .sl-switch__meta { opacity: .85; }

        /* البحث يفتح الألواح كلّها ويطوي إرشاداتها، فتصير النتائج قائمة واحدة */
        #psCard.is-searching .tab-content > .tab-pane { display: block; }
        #psCard.is-searching .tab-content > .tab-pane[hidden] { display: none; }
        #psCard.is-searching [data-section-guide] { display: none; }
        #psCard.is-searching .sl-switch__badge { display: inline-flex; }
        #psCard.is-searching .sl-doc-tabs .nav-link.active { opacity: .55; }

        .sl-scope .sl-empty[hidden] { display: none; }
    </style>
@endsection

@section('scripts')
    <script>
        (function () {
            var form = document.getElementById('psForm');
            var card = document.getElementById('psCard');
            if (!form || !card) return;

            /* صفوفُ المفاتيح وحدَها: صفُّ «الأقسام» يحمل `data-option` ليدخل
               البحثَ والتبويب لكنه بلا مربّع — وقراءةُ `.checked` عليه كانت
               تُسقط السكربتَ كلَّه عند التحميل فلا يُحفَظ شيء */
            var options = Array.prototype.slice.call(form.querySelectorAll('[data-option]'))
                .filter(function (option) { return option.querySelector('input[type="checkbox"]'); });
            var panels = Array.prototype.slice.call(form.querySelectorAll('[data-panel]'));
            var tabs = Array.prototype.slice.call(card.querySelectorAll('[data-tab]'));
            var activeCount = document.getElementById('psActiveCount');
            var search = document.getElementById('psSearch');
            var empty = document.getElementById('psEmpty');
            var unsaved = document.getElementById('psUnsaved');
            var revert = document.getElementById('psRevert');

            function box(option) { return option.querySelector('input[type="checkbox"]'); }

            function state() {
                return options.map(function (option) { return box(option).checked ? 1 : 0; }).join('');
            }

            // الحالة المحفوظة تُلتقط عند التحميل: بها يُعرَف التغيير غير
            // المحفوظ. ولا تكفي مقارنة العدد — إطفاء خيار وإشعال آخر يُبقي
            // العدد كما هو والتغيير قائم
            var saved = state();

            /** العدّاد وشارات الأقسام ومؤشّر «لم يُحفظ». */
            function sync() {
                var on = 0;
                var per = {};

                options.forEach(function (option) {
                    var input = box(option);
                    var group = option.dataset.in;

                    per[group] = per[group] || { on: 0, all: 0 };
                    per[group].all++;

                    if (input.checked) { on++; per[group].on++; }
                });

                if (activeCount) activeCount.textContent = on;

                tabs.forEach(function (tab) {
                    var badge = tab.querySelector('[data-badge]');
                    var counts = per[tab.dataset.tab];
                    if (badge && counts) badge.textContent = counts.on + '/' + counts.all;
                });

                if (unsaved) unsaved.hidden = state() === saved;
            }

            options.forEach(function (option) {
                box(option).addEventListener('change', sync);
            });

            if (revert) {
                revert.addEventListener('click', function () {
                    options.forEach(function (option, index) {
                        box(option).checked = saved[index] === '1';
                    });
                    options.forEach(function (option) {
                        box(option).dispatchEvent(new Event('change'));
                    });
                    sync();
                });
            }

            form.addEventListener('submit', function () {
                saved = state();
                if (unsaved) unsaved.hidden = true;
            });

            window.addEventListener('beforeunload', function (event) {
                if (state() === saved) return;
                event.preventDefault();
                event.returnValue = '';
            });

            /* ربط المفتاح بأصله — نظيرُ `.sl-number-row[data-requires]` في
               شاشة المبيعات: تعتيمٌ لا تعطيل، فالمربّع المعطَّل لا يُرسَل */
            form.querySelectorAll('.sl-switch-row[data-requires]').forEach(function (row) {
                var toggle = document.getElementById('sw_' + row.dataset.requires);
                if (!toggle) return;

                var dim = function () { row.classList.toggle('is-off', !toggle.checked); };

                toggle.addEventListener('change', dim);
                dim();
            });

            // ══ التبويبات ══ — الضغط على لسانٍ يخرج من وضع البحث
            tabs.forEach(function (tab) {
                tab.addEventListener('click', function () {
                    if (search && search.value) { search.value = ''; filter(); }
                });
            });

            // ══ البحث ══
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
                    .replace(/\s+/g, ' ')
                    .trim();
            }

            options.forEach(function (option) {
                option.dataset.norm = normalize(option.dataset.search);
            });

            function filter() {
                var tokens = normalize(search ? search.value : '').split(' ').filter(Boolean);
                var shown = 0;

                card.classList.toggle('is-searching', tokens.length > 0);

                options.forEach(function (option) {
                    var match = !tokens.length || tokens.every(function (token) {
                        return option.dataset.norm.indexOf(token) !== -1;
                    });

                    option.hidden = !match;
                    if (match) shown++;
                });

                // لوحٌ لم يبقَ فيه خيار يُطوى، وإلا بقيت بطاقته فارغةً.
                // خارج البحث تعود التبويبات: اللوح المفتوح يحدّده اللسان النشط
                panels.forEach(function (panel) {
                    panel.hidden = tokens.length > 0 && !panel.querySelector('[data-option]:not([hidden])');
                });

                if (empty) empty.hidden = shown !== 0;
            }

            if (search) {
                search.addEventListener('input', filter);
                search.addEventListener('keydown', function (event) {
                    if (event.key === 'Escape') { search.value = ''; filter(); }
                    if (event.key === 'Enter') { event.preventDefault(); }
                });
            }

            sync();
        })();
    </script>
@endsection
