{{--
    «ابدأ من هنا» — دليل قسم العملاء أعلى لوحة العملاء.

    خطواتٌ مرقّمة في مسارٍ واحد من الأعلى إلى الأسفل — لا أعمدة: القارئ
    الجديد يحتاج ترتيباً واحداً يتبعه. كل خطوة: عنوانٌ هو فعلٌ واضح، سطرٌ
    واحد «لماذا؟» بلغةٍ بسيطة، حالتها من النظام، وزرٌّ يسمّي الشاشة.
    المستخدم رفض نسخةً طويلة (جداول وأسئلة) ونسخةً مختصرة بعبارات مقتضبة —
    المطلوب بينهما: قليلٌ وواضح.

    ما يُقرأ من النظام لا يُكتب ثابتاً:
    - حالة كل خطوة تجهيز — عدٌّ على مستوى النظام كلِّه.
    - «المطلوب الآن» — من `ClientSettingsHelper` نفسِه الذي يقرؤه `ClientRequest`.
    - الزرّ بصلاحية المسار؛ بلا صلاحية يظهر «يقوم بها المدير» ولا تختفي الخطوة.

    أسماء الأصناف كلّها `sg-*`: لوحة العملاء تستعمل `cd-*` (و`cd-num` لخلايا
    الأرقام) — تصادمُ اسمٍ واحد حوّل أرقام الجداول إلى دوائر.

    مطويٌّ عند فتح الصفحة دائماً.
--}}
@php
    $guideUser = auth()->user();
    $guideCan = fn (string $permission) => $guideUser && $guideUser->can($permission);
    $guideRoute = fn (string $name, bool $allowed) => ($allowed && \Illuminate\Support\Facades\Route::has($name)) ? $name : null;
    $safe = function (callable $fn, $fallback = null) {
        try { return $fn(); } catch (\Throwable $e) { return $fallback; }
    };

    $countBranches = $safe(fn () => \App\Models\Branch::withoutGlobalScopes()->count());
    $countEmployees = $safe(fn () => \App\Models\Employee::withoutGlobalScopes()->count());
    $countCategories = $safe(fn () => \App\Models\CategoriesClient::count());
    $countClients = $safe(fn () => \App\Models\Client::withoutGlobalScopes()->count());
    $countUnassigned = $safe(fn () => \App\Models\Client::withoutGlobalScopes()->whereDoesntHave('employees')->count());

    // الحقول الإلزامية الآن — بنفس ترتيب نموذج الإضافة
    $locationOn = (bool) $safe(fn () => \App\Models\GeneralClientSetting::where('key', 'location')->value('is_active'), false);
    $requiredFields = array_keys(array_filter([
        'الاسم التجاري' => $safe(fn () => \App\Helpers\ClientSettingsHelper::clientRequireTradeName(), false),
        'تابع لـ' => true,
        'الهاتف' => $safe(fn () => \App\Helpers\ClientSettingsHelper::clientRequirePhone(), false),
        'الجوال' => $safe(fn () => \App\Helpers\ClientSettingsHelper::clientRequireMobile(), false),
        'البريد' => $safe(fn () => \App\Helpers\ClientSettingsHelper::clientRequireEmail(), false),
        'التصنيف' => $safe(fn () => \App\Helpers\ClientSettingsHelper::clientRequireCategory(), false),
        'وضع العميل' => $safe(fn () => \App\Helpers\ClientSettingsHelper::clientRequireRegistrationStage(), false),
        'الرقم الضريبي' => $safe(fn () => \App\Helpers\ClientSettingsHelper::clientRequireTaxNumber(), false),
        'السجل التجاري' => $safe(fn () => \App\Helpers\ClientSettingsHelper::clientRequireCommercialRegistration(), false),
        'الموقع' => $locationOn && $safe(fn () => \App\Helpers\ClientSettingsHelper::isLocationRequired(), false),
        'الصورة' => $safe(fn () => \App\Helpers\ClientSettingsHelper::isImageFieldEnabled(), false),
    ]));

    /* خطوة = عنوان، لماذا، حالة [نوع ok|no، نص] أو null، زرّ [مسار أو null، نص الزرّ أو null] */
    $status = fn (?int $n, string $unit) => $n === null ? null
        : ($n > 0 ? ['ok', 'موجود عندك: '.number_format($n).' '.$unit] : ['no', 'لم تُضف بعد — ابدأ بها']);

    $onceSteps = [
        [
            'أضف الفروع والمناطق',
            'كل عميل يجب أن يتبع فرعاً أو منطقة. بدونها لا يمكن حفظ أي عميل.',
            $status($countBranches, 'فرع ومنطقة'),
            [$guideRoute('branches.create', $guideCan('branches.create')), 'اذهب إلى الفروع'],
        ],
        [
            'أضف الموظفين (المندوبين)',
            'حتى تختار من يتابع العميل ويزوره.',
            $status($countEmployees, 'موظف'),
            [$guideRoute('employee.create', hasPermission('employees.create|employees_add')), 'اذهب إلى الموظفين'],
        ],
        [
            'أضف تصنيفات العملاء',
            'مثل: بقالة، محطة، تجزئة، أو غيرها — لتفرز عملاءك في التقارير.',
            $status($countCategories, 'تصنيف'),
            [$guideRoute('categoriesClient.index', hasPermission('settings_customer_categories|clients_edit_client_settings')), 'اذهب إلى التصنيفات'],
        ],
        [
            'اضبط إعدادات وحدات العملاء',
            'تحدّد فيها ما يلزم عند إضافة العميل (الجوال، الموقع، الصورة، التصنيف…)، ونوع العملاء، والزيارات وخط السير، ومن أيّ أقسام يُختار المندوب.',
            null,
            [$guideRoute('clients.module_settings', hasPermission('clients_module_settings_view')), 'اذهب إلى إعدادات وحدات العملاء'],
        ],
        [
            'أضف العملاء',
            'الحقول المطلوبة الآن حسب إعداداتك: '.implode('، ', $requiredFields).'.',
            $status($countClients, 'عميل'),
            [$guideRoute('clients.create', $guideCan('clients_add_client')), 'أضف عميلاً'],
        ],
        [
            'أسند كل عميل لمندوب',
            'المندوب يرى ويزور العملاء المسندين إليه فقط.',
            $countUnassigned === null ? null
                : ($countUnassigned > 0 ? ['no', number_format($countUnassigned).' عميل بلا مندوب'] : ['ok', 'كل العملاء لهم مندوب']),
            [$guideRoute('clients.bulk-assignment.index', hasPermission('clients_assign_employees|client_follow_up_assign_clients_to_employees')), 'اذهب إلى الإسناد'],
        ],
        [
            'ارسم خط السير لكل مندوب',
            'حدّد العملاء الذين يزورهم في كل يوم من أيام الأسبوع.',
            null,
            [$guideRoute('itinerary.create', hasPermission('clients_itinerary_view|clients_view_by_daily_itinerary')), 'اذهب إلى خط السير'],
        ],
    ];

    $dailySteps = [
        [
            'المندوب: افتح «خط سيري اليومي»',
            'تجد عملاء اليوم بالترتيب. عند كل عميل: ابدأ الزيارة، سجّل الملاحظة أو الفاتورة، ثم اضغط «إنهاء الزيارة».',
            null,
            [$guideRoute('itinerary.daily', hasPermission('clients_daily_itinerary_view|clients_daily_itinerary_add_for_others')), 'خط سيري اليومي'],
        ],
        [
            'المدير: راجع هذه اللوحة',
            'ابدأ بـ«مركز التنبيهات» في الأسفل: المتأخرون، ومن يحتاج زيارة. اضغط أي رقم لترى أسماء العملاء.',
            null,
            [null, null],
        ],
    ];
@endphp

<style>
    #client-dashboard .sg {
        background: var(--cd-surface); border: 1px solid var(--cd-border);
        border-inline-start: 4px solid var(--cd-primary);
        border-radius: calc(var(--cd-radius) + 4px); margin-bottom: 1.25rem;
    }
    #client-dashboard .sg > summary {
        list-style: none; cursor: pointer; padding: .8rem 1.25rem;
        display: flex; align-items: center; gap: .6rem; color: var(--cd-text);
        background: color-mix(in srgb, var(--cd-primary) 7%, transparent);
        border-radius: calc(var(--cd-radius) + 4px); outline: none;
    }
    #client-dashboard .sg > summary:focus-visible { box-shadow: 0 0 0 2px color-mix(in srgb, var(--cd-primary) 45%, transparent); }
    #client-dashboard .sg[open] > summary { border-radius: calc(var(--cd-radius) + 4px) calc(var(--cd-radius) + 4px) 0 0; }
    #client-dashboard .sg > summary::-webkit-details-marker { display: none; }
    #client-dashboard .sg > summary::marker { content: ''; }
    #client-dashboard .sg__icon {
        width: 32px; height: 32px; border-radius: 50%; flex: none;
        display: inline-flex; align-items: center; justify-content: center;
        background: color-mix(in srgb, var(--cd-primary) 12%, transparent); color: var(--cd-primary);
    }
    #client-dashboard .sg__head { flex: 1; min-width: 0; font-weight: 700; font-size: .95rem; }
    #client-dashboard .sg__head small { display: block; font-weight: 500; font-size: .78rem; color: var(--cd-muted); }
    #client-dashboard .sg__toggle {
        font-size: .8rem; font-weight: 700; white-space: nowrap; color: #fff; background: var(--cd-primary);
        border-radius: var(--app-button-radius, 6px); padding: .4rem .9rem;
    }
    #client-dashboard .sg[open] .sg__toggle .when-closed,
    #client-dashboard .sg:not([open]) .sg__toggle .when-open { display: none; }

    #client-dashboard .sg__body { padding: 1rem 1.25rem 1.1rem; max-width: 900px; }
    #client-dashboard .sg__group {
        font-size: .8rem; font-weight: 700; color: var(--cd-primary);
        margin: 0 0 .7rem; display: flex; align-items: center; gap: .4rem;
    }
    #client-dashboard .sg__group:not(:first-child) { margin-top: 1.3rem; }

    /* الخطّ الرأسي يصل الأرقام — يُقرأ مساراً واحداً لا قائمةً مبعثرة */
    #client-dashboard .sg-steps { list-style: none; margin: 0; padding: 0; }
    #client-dashboard .sg-step { position: relative; display: flex; gap: .85rem; padding-bottom: 1rem; }
    #client-dashboard .sg-step:last-child { padding-bottom: 0; }
    #client-dashboard .sg-step:not(:last-child)::after {
        content: ''; position: absolute; top: 2rem; bottom: .2rem; inset-inline-start: calc(1rem - 1px);
        width: 2px; background: var(--cd-border);
    }
    #client-dashboard .sg-step__num {
        width: 2rem; height: 2rem; border-radius: 50%; flex: none; z-index: 1;
        display: inline-flex; align-items: center; justify-content: center;
        font-size: .85rem; font-weight: 700; color: #fff; background: var(--cd-primary);
    }
    #client-dashboard .sg-step--ok .sg-step__num { background: var(--cd-success); }
    #client-dashboard .sg-step--no .sg-step__num { background: var(--cd-danger); }
    #client-dashboard .sg-step__main { flex: 1; min-width: 0; display: flex; flex-wrap: wrap; align-items: flex-start; gap: .4rem 1rem; }
    #client-dashboard .sg-step__text { flex: 1 1 320px; min-width: 0; }
    #client-dashboard .sg-step__title { display: block; font-size: .9rem; font-weight: 700; color: var(--cd-text); margin-top: .25rem; }
    #client-dashboard .sg-step__why { margin: .15rem 0 0; font-size: .8rem; color: var(--cd-muted); line-height: 1.7; }
    #client-dashboard .sg-step__state { display: inline-flex; align-items: center; gap: .25rem; margin-top: .3rem; font-size: .75rem; font-weight: 700; }
    #client-dashboard .sg-step--ok .sg-step__state { color: var(--cd-success); }
    #client-dashboard .sg-step--no .sg-step__state { color: var(--cd-danger); }
    #client-dashboard .sg-step__btn {
        flex: none; align-self: center; display: inline-flex; align-items: center; gap: .3rem;
        font-size: .78rem; font-weight: 600; text-decoration: none; white-space: nowrap;
        color: var(--cd-primary); border: 1px solid var(--cd-primary);
        border-radius: var(--app-button-radius, 6px); padding: .35rem .8rem;
    }
    #client-dashboard .sg-step__btn:hover { background: var(--cd-primary); color: #fff; }
    #client-dashboard .sg-step__admin { flex: none; align-self: center; font-size: .75rem; color: var(--cd-muted); white-space: nowrap; }
</style>

<details class="sg">
    <summary>
        <span class="sg__icon"><i class="feather icon-compass"></i></span>
        <span class="sg__head">
            ابدأ من هنا — كيف تشغّل قسم العملاء؟
            <small>خطوات مرقّمة بالترتيب، وأمام كل خطوة زرّ يأخذك إلى مكانها.</small>
        </span>
        <span class="sg__toggle">
            <span class="when-closed">اعرض الخطوات <i class="feather icon-chevron-down"></i></span>
            <span class="when-open">إخفاء <i class="feather icon-chevron-up"></i></span>
        </span>
    </summary>

    <div class="sg__body">
        @php $stepNo = 0; @endphp
        @foreach ([['أولاً: جهّز النظام — مرة واحدة فقط', 'settings', $onceSteps], ['ثانياً: كل يوم', 'sun', $dailySteps]] as [$groupTitle, $groupIcon, $groupSteps])
            <p class="sg__group"><i class="feather icon-{{ $groupIcon }}"></i> {{ $groupTitle }}</p>
            <ol class="sg-steps">
                @foreach ($groupSteps as [$title, $why, $state, $button])
                    @php $stepNo++; @endphp
                    <li class="sg-step {{ $state ? 'sg-step--'.$state[0] : '' }}">
                        <span class="sg-step__num">
                            @if ($state && $state[0] === 'ok')<i class="feather icon-check"></i>@else{{ $stepNo }}@endif
                        </span>
                        <div class="sg-step__main">
                            <div class="sg-step__text">
                                <span class="sg-step__title">{{ $stepNo }}. {{ $title }}</span>
                                <p class="sg-step__why">{{ $why }}</p>
                                @if ($state)
                                    <span class="sg-step__state">
                                        <i class="feather icon-{{ $state[0] === 'ok' ? 'check-circle' : 'alert-circle' }}"></i> {{ $state[1] }}
                                    </span>
                                @endif
                            </div>
                            @if ($button[0])
                                <a href="{{ route($button[0]) }}" class="sg-step__btn">{{ $button[1] }} <i class="feather icon-arrow-left"></i></a>
                            @elseif ($button[1])
                                <span class="sg-step__admin"><i class="feather icon-lock"></i> يقوم بها المدير</span>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ol>
        @endforeach
    </div>
</details>
