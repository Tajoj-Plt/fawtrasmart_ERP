@extends('sales::master')

{{-- لوحة إعدادات المبيعات.

     كانت ثماني بطاقات مكتوبة يدوياً في هذا القالب، عنوانها «الاعدادات
     الرواتب» وترويستها «اعدادات الفواتير»، وأيقوناتها `fa-8x` تملأ البطاقة
     فلا يبقى للشرح موضع — ولا شرح أصلاً. وثلاث شاشات قائمة لم تكن فيها.

     ثم صارت تستعير لوحة وحدة الإعدادات (`sitting::components._settings_hub`)،
     فورثت عنها مظهراً يخالف بقيّة شاشات المبيعات ويكسر إن عُطّلت وحدة
     الإعدادات في `modules_statuses.json`.

     الآن: مصدرها `SalesSettingsNavigation` كما كان، وعرضها بلغة تصميم
     «الأذون المخزنية» منقولةً إلى الوحدة في `sales::partials.settings-theme`
     — بلا استعارة قالب من وحدة أخرى. --}}

@section('title', __('sales_settings.hub.title'))

@section('content')
    @include('sales::partials.settings-theme')
    @include('components.hint-tips')

    <div class="content-body sl-scope">

        @include('sales::components.page-head', [
            'title' => __('sales_settings.hub.title'),
            'icon' => 'sliders',
            'sub' => __('sales_settings.hub.subtitle'),
            'crumbs' => [
                __('sales_settings.ui.home') => route('dashboard'),
                __('sales_settings.hub.title') => null,
            ],
        ])

        @include('layouts.alerts.success')
        @include('layouts.alerts.error')
        @include('sales::components.form-errors')

        {{-- ══════════ إرشادُ الشاشة ══════════ --}}
        @include('sales::components.guide', [
            'key' => 'sales-settings.hub',
            'open' => false,
            'title' => __('sales_settings.guide.hub.title'),
            'lead' => __('sales_settings.guide.hub.lead'),
            'blocks' => [
                [
                    __('sales_settings.guide.hub.a'),
                    __('sales_settings.guide.hub.b'),
                    __('sales_settings.guide.hub.c'),
                ],
            ],
        ])

        <div class="sl-stats">
            <div class="sl-stat">
                <div class="sl-stat__icon"><i class="feather icon-sliders"></i></div>
                <div class="sl-stat__body">
                    <p class="sl-stat__value">{{ $screenCount }}</p>
                    <p class="sl-stat__label">{{ __('sales_settings.hub.screens_available') }}</p>
                </div>
            </div>

            <div class="sl-stat">
                <div class="sl-stat__icon sl-stat__icon--info"><i class="feather icon-grid"></i></div>
                <div class="sl-stat__body">
                    <p class="sl-stat__value">{{ count($groups) }}</p>
                    <p class="sl-stat__label">{{ __('sales_settings.hub.groups_count') }}</p>
                </div>
            </div>

            {{-- العملة واللغة كانتا بطاقتَي إحصاء هنا. انتقلتا إلى شريط
                 «إعدادات النظام السارية» أدناه مع أخواتهما الستّ، لأن
                 بطاقة الإحصاء رقمٌ يُقاس وهاتان قيمتان تُفتَح شاشتاهما —
                 والشريط يجعل كل واحدة رابطاً إلى موضع ضبطها. --}}
        </div>

        {{-- إعدادات النظام السارية على شاشات المبيعات كلّها: بأيّ عملة
             تُنسَّق مبالغها، وبأيّ لغة ومظهر، وهل الفترة المحاسبية اليوم
             مفتوحة. كل بندٍ رابطٌ إلى شاشته --}}
        @include('sales::components.system-context')

        {{-- إفصاحٌ عن أثر ما يجري في هذه الشاشات: من يبحث لاحقاً عن «مَن
             غيّر هذا الإعداد؟» يعرف من هنا أين يجد الجواب --}}
        <p class="sl-ctx__hint" style="margin:-.5rem 0 var(--sl-5)">
            <i class="feather icon-activity" aria-hidden="true"></i>
            {{ __('sales_settings.context.logs_hint') }}
        </p>

        <div class="sl-hubsearch">
            <i class="feather icon-search"></i>
            <input type="search" id="slHubSearch" autocomplete="off"
                   placeholder="{{ __('sales_settings.hub.search_placeholder') }}"
                   aria-label="{{ __('sales_settings.hub.search_placeholder') }}">
        </div>

        <div id="slHubGroups">
            @foreach ($groups as $groupName => $group)
                <section class="sl-group" data-sl-group>
                    <div class="sl-group__head">
                        <i class="feather icon-{{ $group['icon'] }}" aria-hidden="true"></i>
                        <h3 class="sl-group__title">{{ $groupName }}</h3>
                    </div>

                    <div class="sl-tiles">
                        @foreach ($group['items'] as $item)
                            {{-- البلاطة رابط، وأبناؤها روابط داخلها: رابطٌ داخل
                                 رابط ترميزٌ غير صالح، فالبلاطة `div` وعنوانها
                                 وحده هو الرابط، والبطاقة كلّها تُفتح بالضغط
                                 عبر `stretched`-مثل في CSS لا بوسم متداخل. --}}
                            <div class="sl-tile" data-sl-item
                                 data-sl-text="{{ Str::lower($item['label'] . ' ' . ($item['hint'] ?? '')) }}">
                                <span class="sl-tile__icon" aria-hidden="true">
                                    <i class="feather icon-{{ $item['icon'] ?? 'settings' }}"></i>
                                </span>

                                <div class="sl-tile__body">
                                    <p class="sl-tile__title">
                                        <a href="{{ $item['url'] }}" style="color:inherit;text-decoration:none">
                                            {{ $item['label'] }}
                                        </a>
                                    </p>

                                    @if (! empty($item['hint']))
                                        <p class="sl-tile__hint">{{ $item['hint'] }}</p>
                                    @endif

                                    @if (! empty($item['children']))
                                        <div class="sl-tile__kids">
                                            @foreach ($item['children'] as $child)
                                                <a class="sl-tile__kid" href="{{ $child['url'] }}">{{ $child['label'] }}</a>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endforeach
        </div>

        <div class="sl-empty" id="slHubEmpty" hidden>
            <div class="sl-empty__icon" aria-hidden="true"><i class="feather icon-search"></i></div>
            <p class="sl-empty__title">{{ __('sales_settings.hub.no_match') }}</p>
        </div>
    </div>

    <script>
        /* بحثٌ في المتصفّح: عدد الشاشات عشرات لا آلاف، فرحلةٌ إلى الخادم
           لكل حرف تُكلّف ولا تُفيد. المجموعة تختفي حين تخلو من بلاطة
           مطابقة، فلا تبقى ترويسة معلّقة فوق فراغ. */
        (function () {
            const input = document.getElementById('slHubSearch');
            const empty = document.getElementById('slHubEmpty');
            if (!input) return;

            const groups = Array.from(document.querySelectorAll('[data-sl-group]'));

            input.addEventListener('input', function () {
                const q = input.value.trim().toLowerCase();
                let shown = 0;

                groups.forEach(function (group) {
                    let visibleInGroup = 0;

                    group.querySelectorAll('[data-sl-item]').forEach(function (tile) {
                        const match = !q || (tile.dataset.slText || '').indexOf(q) !== -1;
                        tile.hidden = !match;
                        if (match) visibleInGroup++;
                    });

                    group.hidden = visibleInGroup === 0;
                    shown += visibleInGroup;
                });

                empty.hidden = shown !== 0;
            });
        })();
    </script>
@endsection
