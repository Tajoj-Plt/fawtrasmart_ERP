@extends('sitting::master')

@section('title', __('settings.accounting_settings.title'))

@section('content')
    @include('sitting::components._settings_styles')

    @php
        $canEdit = hasPermission('settings.accounting_settings.edit');
    @endphp

    <style>
        /* ── صفّ الإعداد ── */
        .as-name b { display: block; font-weight: 600; font-size: .93rem; color: #1b2430; }
        .as-desc { color: #6b7280; font-size: .82rem; }

        /* الحساب المربوط: السطر الأول اسمه والثاني تنبيه إن كان معطَّلاً */
        .as-account { display: flex; flex-direction: column; gap: .15rem; }
        .as-account .warn { font-size: .74rem; color: #96700f; }
        .as-account .none { color: #a3342a; font-size: .84rem; font-weight: 600; }

        /* ── منتقي الحساب ── */
        .as-picker { position: relative; }
        .as-results {
            list-style: none; margin: .4rem 0 0; padding: 0;
            border: 1px solid #e6e9ef; border-radius: 8px; background: #fff;
            max-height: 240px; overflow-y: auto; display: none;
        }
        .as-results.open { display: block; }
        .as-results li {
            padding: .5rem .7rem; cursor: pointer; font-size: .87rem;
            border-bottom: 1px solid #f2f4f7; display: flex; gap: .5rem; align-items: center;
        }
        .as-results li:last-child { border-bottom: 0; }
        .as-results li:hover, .as-results li.active { background: #eaf1fb; }
        .as-results li .code {
            direction: ltr; font-family: Consolas, Monaco, monospace; font-size: .78rem;
            background: #f4f5f7; border: 1px solid #e6e9ef; border-radius: 5px;
            padding: .05rem .4rem; flex: 0 0 auto;
        }
        .as-results .empty { color: #8a93a0; cursor: default; justify-content: center; }
        .as-results .empty:hover { background: #fff; }

        .as-chosen {
            display: flex; align-items: center; gap: .5rem; margin-top: .5rem;
            padding: .5rem .7rem; border-radius: 8px;
            background: #e7f6ee; border: 1px solid #bfe6d1; color: #1b7a4b;
            font-size: .87rem; font-weight: 600;
        }
        .as-chosen.empty { background: #fdeeec; border-color: #f2c4bd; color: #8f2f22; }

        .as-help { font-size: .76rem; color: #9aa2ae; margin-top: .25rem; display: block; }

        /* فاصل «أو» بين البحث والإدخال اليدوي */
        .as-or { display: flex; align-items: center; gap: .6rem; margin: .8rem 0 .6rem; }
        .as-or::before, .as-or::after { content: ''; flex: 1; height: 1px; background: #eef0f4; }
        .as-or span { font-size: .74rem; color: #9aa2ae; font-weight: 600; }

        .as-sublabel { font-size: .82rem; font-weight: 600; color: #4b5563; margin-bottom: .3rem; display: block; }

        /* إقرار تغيير الكود — لا يُلمح إليه بحاشية صغيرة */
        .as-confirm {
            display: flex; align-items: flex-start; gap: .55rem; margin: 0; cursor: pointer;
            font-size: .84rem; color: #8f2f22; font-weight: 600; line-height: 1.6;
        }
        .as-confirm input { margin-top: .25rem; flex: 0 0 auto; }

        .as-form-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: .9rem; }

        /* ── موضع الحساب من الشجرة ── */
        .as-tree {
            background: #fafbfc; border: 1px solid #eef0f4; border-radius: 9px;
            padding: .6rem .8rem; margin-bottom: 1rem;
        }
        .as-tree .row-line { display: flex; gap: .6rem; align-items: baseline; padding: .18rem 0; }
        .as-tree .lbl { font-size: .76rem; color: #8a93a0; flex: 0 0 auto; min-width: 108px; }
        .as-tree .val { font-size: .83rem; color: #1b2430; font-weight: 600; }
        .as-tree .val .sib {
            direction: ltr; unicode-bidi: embed; display: inline-block;
            font-family: Consolas, Monaco, monospace; font-size: .74rem; font-weight: 600;
            background: #eef1f5; color: #4b5563; border-radius: 4px;
            padding: .02rem .32rem; margin-inline-end: .22rem;
        }
        .as-tree .muted { color: #9aa2ae; font-weight: 400; }

        /* ── رقائق الاقتراح ── */
        .as-suggest { display: flex; flex-wrap: wrap; gap: .45rem; }
        .as-chip {
            display: inline-flex; flex-direction: column; gap: .1rem; align-items: flex-start;
            border: 1px solid #bfe6d1; background: #e7f6ee; color: #1b7a4b;
            border-radius: 9px; padding: .4rem .65rem; cursor: pointer;
            font-family: inherit; text-align: start; transition: background .15s, border-color .15s;
        }
        .as-chip:hover, .as-chip:focus-visible { background: #d6efe1; border-color: #99d8b6; }
        .as-chip .c {
            direction: ltr; unicode-bidi: embed;
            font-family: Consolas, Monaco, monospace; font-weight: 700; font-size: .92rem;
        }
        .as-chip .r { font-size: .68rem; color: #2f7a55; font-weight: 500; }
        .as-suggest .as-empty { font-size: .8rem; color: #9aa2ae; }

        .as-prefix-hint { font-size: .74rem; margin-top: .28rem; display: block; }
        .as-prefix-hint.ok { color: #1b7a4b; }
        .as-prefix-hint.warn { color: #96700f; }

        /* حالة الكود: شاغر / محجوز ولمن */
        .as-code-status { font-size: .78rem; font-weight: 600; margin-top: .35rem; display: block; }
        .as-code-status.ok { color: #1b7a4b; }
        .as-code-status.busy { color: #a3342a; }
        .as-code-status.neutral { color: #8a93a0; font-weight: 500; }

        /* ── لوح الشرح ── */
        .as-guide { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.1rem; }
        .as-guide h5 {
            font-size: .9rem; font-weight: 700; color: #1b2430;
            margin: 0 0 .4rem; display: flex; align-items: center; gap: .45rem;
        }
        .as-guide h5 i { color: #2b6cb0; }
        .as-guide p { font-size: .85rem; color: #4b5563; margin: 0; line-height: 1.75; }
        .as-guide ol { margin: 0; padding-inline-start: 1.1rem; }
        .as-guide ol li { font-size: .85rem; color: #4b5563; line-height: 1.9; }
        .as-guide code {
            direction: ltr; display: inline-block; unicode-bidi: embed;
            font-family: Consolas, Monaco, monospace; font-size: .78rem;
            background: #f4f5f7; color: #384250; padding: .05rem .35rem;
            border-radius: 4px; border: 1px solid #e6e9ef;
        }

        /* ── أمثلة القيود ── */
        .as-ops { display: grid; grid-template-columns: repeat(auto-fit, minmax(340px, 1fr)); gap: 1rem; }
        .as-op { border: 1px solid #e6e9ef; border-radius: 10px; overflow: hidden; background: #fff; }
        .as-op > .t {
            padding: .6rem .85rem; background: #fafbfc; border-bottom: 1px solid #eef0f4;
            font-size: .88rem; font-weight: 700; color: #1b2430;
            display: flex; align-items: center; gap: .45rem;
        }
        .as-op > .t i { color: #2b6cb0; }
        .as-op table { width: 100%; border-collapse: collapse; }
        .as-op td { padding: .45rem .85rem; border-bottom: 1px solid #f2f4f7; font-size: .83rem; vertical-align: top; }
        .as-op tr:last-child td { border-bottom: 0; }

        /* الطرف شارة لا كلمة: العين تلتقط اللون قبل أن تقرأ */
        .as-side {
            display: inline-block; font-size: .7rem; font-weight: 700;
            padding: .1rem .45rem; border-radius: 20px; white-space: nowrap; min-width: 42px; text-align: center;
        }
        .as-side.debit { background: #eaf1fb; color: #2b6cb0; }
        .as-side.credit { background: #fdf3df; color: #96700f; }

        .as-op .acct { font-weight: 600; color: #1b2430; display: block; }
        .as-op .stg { color: #8a93a0; font-size: .76rem; }
        .as-op .miss { color: #a3342a; font-weight: 600; }
        .as-op .nt { color: #6b7280; font-size: .78rem; }

        /* موضع الإعداد من العمليات — يظهر في صفّه بالجدول */
        .as-used { margin-top: .3rem; display: flex; flex-wrap: wrap; gap: .25rem; }
        .as-used span {
            font-size: .68rem; font-weight: 600; color: #216b7c;
            background: #e8f2f5; border-radius: 20px; padding: .05rem .45rem;
        }

        /* شريط التصفية فوق الجداول */
        .as-filter { position: relative; margin-bottom: 1.1rem; }
        /* الصفّ المقصود من رابطٍ خارجي (#as-key-…) — يُبرَز كي لا يُبحث عنه بين ثمانين */
        .as-row:target, .as-row.as-targeted { background: rgba(255, 159, 67, .14); }
        .as-row:target td:first-child, .as-row.as-targeted td:first-child { box-shadow: inset -3px 0 0 #ff9f43; }
        .as-group { scroll-margin-top: 90px; }
        .as-row { scroll-margin-top: 120px; }
        .as-filter input { padding-inline-start: 2.2rem; }
        .as-filter i {
            position: absolute; top: 50%; transform: translateY(-50%);
            inset-inline-start: .75rem; color: #9aa2ae;
        }
        .as-hidden { display: none !important; }
    </style>

    <div class="sl-page-head">
        <div class="sl-page-head__titles">
            <span class="sl-page-head__icon" aria-hidden="true"><i class="feather icon-book-open"></i></span>
            <div style="min-width:0">
                <h2 class="sl-page-head__title">{{ __('settings.accounting_settings.title') }}</h2>
                <nav>
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('settings.common.home') }}</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('SittingAccount.dashboard') }}">{{ __('settings.common.settings') }}</a></li>
                        <li class="breadcrumb-item active">{{ __('settings.accounting_settings.breadcrumb') }}</li>

                    </ol>
                </nav>
            </div>
        </div>
    </div>

    <div class="content-body">
        @include('layouts.alerts.success')
        @include('layouts.alerts.error')

        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0 pr-3">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- التنبيه مقصود: الشاشة تبدو وكأنها تتحكّم بالقيود، وهي لا تفعل بعد.
             من يغيّر حساب المبيعات هنا ثم يرى القيد على حسابه القديم يظنّه خللاً --}}
        <div class="st-note info">
            <i class="feather icon-info"></i>
            <div>
                <b>{{ __('settings.accounting_settings.note_title') }}</b>
                <p>{{ __('settings.accounting_settings.note_body') }}</p>
            </div>
        </div>

        {{-- الشرح داخل الشاشة لا خارجها: من يفتحها يرى ثمانين اسم حساب بلا
             سياق، ولا يعرف أن «حساب المبيعات» هو الطرف الدائن في قيد فاتورة
             البيع مقابل حساب العميل. مطويّ افتراضياً كي لا يزاحم الجدول --}}
        <div class="st-card">
            <div class="head">
                <h4><i class="feather icon-help-circle"></i> {{ __('settings.accounting_settings.guide.title') }}</h4>
                <span class="spacer"></span>
                <button type="button" class="btn btn-sm btn-outline-primary" id="asGuideToggle"
                    data-show="{{ __('settings.accounting_settings.guide.show') }}"
                    data-hide="{{ __('settings.accounting_settings.guide.hide') }}">
                    {{ __('settings.accounting_settings.guide.show') }}
                </button>
            </div>
            <div class="body as-hidden" id="asGuideBody">
                <div class="as-guide">
                    <div>
                        <h5><i class="feather icon-alert-circle"></i> {{ __('settings.accounting_settings.guide.problem_title') }}</h5>
                        <p>{{ __('settings.accounting_settings.guide.problem_body') }}</p>
                    </div>
                    <div>
                        <h5><i class="feather icon-check-circle"></i> {{ __('settings.accounting_settings.guide.solution_title') }}</h5>
                        <p>{{ __('settings.accounting_settings.guide.solution_body') }}</p>
                    </div>
                    <div>
                        <h5><i class="feather icon-edit-2"></i> {{ __('settings.accounting_settings.guide.steps_title') }}</h5>
                        <ol>
                            <li>{{ __('settings.accounting_settings.guide.step1') }}</li>
                            <li>{{ __('settings.accounting_settings.guide.step2') }}</li>
                            <li>{{ __('settings.accounting_settings.guide.step3') }}</li>
                        </ol>
                    </div>
                    <div>
                        <h5><i class="feather icon-git-branch"></i> {{ __('settings.accounting_settings.guide.wiring_title') }}</h5>
                        <p>{{ __('settings.accounting_settings.guide.wiring_body') }}</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- أمثلة القيود تُبنى من الإعدادات المضبوطة الآن: من غيّر حساب
             المبيعات يرى أثر تغييره في قيد الفاتورة فوراً، بلا أن يفتح فاتورة --}}
        <div class="st-card">
            <div class="head">
                <h4><i class="feather icon-book-open"></i> {{ __('settings.accounting_settings.operations.title') }}</h4>
                <span class="count">{{ count($operations) }}</span>
            </div>
            <div class="body">
                <p class="as-desc mb-1">{{ __('settings.accounting_settings.operations.intro') }}</p>

                <div class="as-ops">
                    @foreach ($operations as $operation)
                        <div class="as-op">
                            <div class="t"><i class="feather icon-file-text"></i> {{ $operation['title'] }}</div>
                            <table>
                                <tbody>
                                    @foreach ($operation['lines'] as $line)
                                        <tr>
                                            <td style="width:1%">
                                                <span class="as-side {{ $line['side'] }}">
                                                    {{ $line['side'] === 'debit'
                                                        ? __('settings.accounting_settings.operations.debit')
                                                        : __('settings.accounting_settings.operations.credit') }}
                                                </span>
                                            </td>
                                            <td>
                                                @if ($line['account'])
                                                    <span class="acct">{{ $line['account'] }}</span>
                                                @else
                                                    <span class="acct miss">{{ __('settings.accounting_settings.operations.unset') }}</span>
                                                @endif
                                                <span class="stg">{{ $line['setting'] }}</span>
                                            </td>
                                            <td class="nt">{{ $line['note'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="st-stats">
            <div class="st-stat">
                <div class="ic primary"><i class="feather icon-list"></i></div>
                <div>
                    <div class="val">{{ $total }}</div>
                    <div class="lbl">{{ __('settings.accounting_settings.stats.total') }}</div>
                </div>
            </div>
            <div class="st-stat">
                <div class="ic success"><i class="feather icon-check-circle"></i></div>
                <div>
                    <div class="val">{{ $mapped }}</div>
                    <div class="lbl">{{ __('settings.accounting_settings.stats.mapped') }}</div>
                </div>
            </div>
            <div class="st-stat">
                <div class="ic danger"><i class="feather icon-alert-circle"></i></div>
                <div>
                    <div class="val">{{ $unmapped }}</div>
                    <div class="lbl">{{ __('settings.accounting_settings.stats.unmapped') }}</div>
                </div>
            </div>
            <div class="st-stat">
                <div class="ic warning"><i class="feather icon-slash"></i></div>
                <div>
                    <div class="val">{{ $inactive }}</div>
                    <div class="lbl">{{ __('settings.accounting_settings.stats.inactive') }}</div>
                </div>
            </div>
        </div>

        <div class="as-filter">
            <i class="feather icon-search"></i>
            <input type="text" class="form-control" id="asFilter"
                placeholder="{{ __('settings.accounting_settings.filter_placeholder') }}" autocomplete="off">
        </div>

        <div id="asNoMatch" class="st-empty as-hidden">
            <i class="feather icon-search"></i>
            {{ __('settings.accounting_settings.no_match') }}
        </div>

        @forelse ($groups as $groupName => $settings)
            {{-- مرساةٌ لكلّ مجموعة: شاشاتُ الرواتب تفتح هذه الشاشة على `#as-group-payroll` أو على صفٍّ بعينه `#as-key-…` --}}
            <div class="st-card as-group"
                id="{{ $groupName === \App\Support\PayrollAccounts::GROUP ? 'as-group-payroll' : 'as-group-'.$loop->iteration }}">
                <div class="head">
                    <h4>{{ $groupName }}</h4>
                    <span class="count">{{ $settings->count() }}</span>
                </div>
                <div class="body flush">
                    <table class="st-table">
                        <thead>
                            <tr>
                                <th>{{ __('settings.accounting_settings.table.name') }}</th>
                                <th>{{ __('settings.accounting_settings.table.description') }}</th>
                                <th>{{ __('settings.accounting_settings.table.account') }}</th>
                                <th>{{ __('settings.accounting_settings.table.code') }}</th>
                                <th>{{ __('settings.accounting_settings.table.status') }}</th>
                                <th>{{ __('settings.accounting_settings.table.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($settings as $setting)
                                <tr class="as-row" id="as-key-{{ $setting->key }}"
                                    data-search="{{ Str::lower($setting->name.' '.$setting->key.' '.($setting->description ?? '').' '.($setting->account_code ?? '').' '.optional($setting->account)->name) }}">
                                    <td class="as-name">
                                        <b>{{ $setting->name }}</b>
                                        <span class="st-mono">{{ $setting->key }}</span>

                                        {{-- أي عملية تمسّ هذا الحساب — الجواب على
                                             «وهذا الإعداد، أين يظهر أصلاً؟» --}}
                                        @if (! empty($usage[$setting->key]))
                                            <span class="as-used">
                                                @foreach ($usage[$setting->key] as $operationTitle)
                                                    <span>{{ $operationTitle }}</span>
                                                @endforeach
                                            </span>
                                        @endif
                                    </td>
                                    <td class="as-desc">{{ $setting->description ?: '—' }}</td>
                                    <td>
                                        <div class="as-account">
                                            @if ($setting->account)
                                                <span>{{ ($setting->account->code ?? '—') . ' - ' . $setting->account->name }}</span>
                                                @unless ($setting->account->is_active)
                                                    <span class="warn">
                                                        <i class="feather icon-alert-triangle"></i>
                                                        {{ __('settings.accounting_settings.account_disabled') }}
                                                    </span>
                                                @endunless
                                            @elseif ($setting->account_id)
                                                <span class="none">{{ __('settings.accounting_settings.account_missing') }}</span>
                                            @else
                                                <span class="none">{{ __('settings.accounting_settings.no_account') }}</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="num">
                                        @if ($setting->account_code)
                                            <span class="st-mono">{{ $setting->account_code }}</span>
                                        @else
                                            <span class="as-desc">{{ __('settings.accounting_settings.no_code') }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="st-badge {{ $setting->is_active ? 'on' : 'off' }}">
                                            {{ $setting->is_active ? __('settings.common.active') : __('settings.common.inactive') }}
                                        </span>
                                    </td>
                                    <td>
                                        @if ($canEdit)
                                            <span class="st-actions">
                                                <button type="button" class="btn btn-outline-primary as-edit"
                                                    data-id="{{ $setting->id }}"
                                                    title="{{ __('settings.common.edit') }}">
                                                    <i class="feather icon-edit-2"></i> {{ __('settings.common.edit') }}
                                                </button>

                                                {{-- تغيير كود الحساب زرّ منفصل بلون منفصل: هو
                                                     الإجراء الوحيد في الشاشة الذي يخرج من طبقة
                                                     الإعدادات إلى شجرة الحسابات --}}
                                                @if ($setting->account)
                                                    <button type="button" class="btn btn-outline-danger as-recode"
                                                        data-id="{{ $setting->id }}"
                                                        data-account="{{ ($setting->account->code ?? '—') . ' - ' . $setting->account->name }}"
                                                        data-code="{{ $setting->account->code }}"
                                                        data-account-id="{{ $setting->account->id }}"
                                                        title="{{ __('settings.accounting_settings.recode.action') }}">
                                                        <i class="feather icon-hash"></i>
                                                    </button>
                                                @endif
                                            </span>
                                        @else
                                            <span class="as-desc">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @empty
            <div class="st-card">
                <div class="body">
                    <div class="st-empty">
                        <i class="feather icon-inbox"></i>
                        {{ __('settings.common.no_data') }}
                        <div class="as-help">php artisan db:seed --class=AccountingSettingsSeeder</div>
                    </div>
                </div>
            </div>
        @endforelse
    </div>

    @if ($canEdit)
        <div class="modal fade" id="asEditModal" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content">
                    <form method="POST" id="asEditForm" action="">
                        @csrf
                        @method('PUT')

                        <div class="modal-header">
                            <h5 class="modal-title" id="asEditTitle">{{ __('settings.accounting_settings.edit_title') }}</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>

                        <div class="modal-body">
                            <div class="form-group">
                                <label for="asName">{{ __('settings.accounting_settings.fields.name') }} *</label>
                                <input type="text" class="form-control" id="asName" name="name" maxlength="255" required>
                                <span class="as-help st-mono" id="asKey"></span>
                            </div>

                            <div class="form-group">
                                <label for="asDescription">{{ __('settings.accounting_settings.fields.description') }}</label>
                                <input type="text" class="form-control" id="asDescription" name="description" maxlength="500">
                            </div>

                            <div class="form-group as-picker">
                                <label for="asAccountSearch">{{ __('settings.accounting_settings.fields.account') }} *</label>

                                {{-- المستخدم لا يكتب معرّفاً: يبحث بالكود أو بالاسم ويختار،
                                     والمعرّف يُملأ في الحقل المخفي من الاختيار وحده --}}
                                <input type="text" class="form-control" id="asAccountSearch" autocomplete="off"
                                    placeholder="{{ __('settings.accounting_settings.search_placeholder') }}">
                                <input type="hidden" name="account_id" id="asAccountId">

                                <ul class="as-results" id="asAccountResults"></ul>

                                <span class="as-help">{{ __('settings.accounting_settings.search_hint') }}</span>

                                {{-- الإدخال اليدوي: من يحفظ الأكواد عن ظهر قلب لا
                                     يريد قائمة بحث. الخادم يقبل الكود بديلاً عن
                                     المعرّف، فالحقل يعمل حتى لو تعطّلت الجافاسكربت --}}
                                <div class="as-or"><span>{{ __('settings.accounting_settings.or') }}</span></div>

                                <label for="asCodeInput" class="as-sublabel">
                                    {{ __('settings.accounting_settings.code_input_label') }}
                                </label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="asCodeInput" name="account_code"
                                        inputmode="numeric" maxlength="10" autocomplete="off"
                                        placeholder="{{ __('settings.accounting_settings.code_input_placeholder') }}">
                                    <div class="input-group-append">
                                        <button type="button" class="btn btn-outline-primary" id="asCodeApply">
                                            {{ __('settings.accounting_settings.code_apply') }}
                                        </button>
                                    </div>
                                </div>
                                <span class="as-help">{{ __('settings.accounting_settings.code_input_hint') }}</span>

                                <div class="as-chosen" id="asChosen">
                                    <i class="feather icon-check-circle"></i>
                                    <span id="asChosenLabel"></span>
                                </div>
                            </div>

                            <div class="form-group mb-0">
                                <label class="st-switch">
                                    <input type="hidden" name="is_active" value="0">
                                    <input type="checkbox" id="asActive" name="is_active" value="1">
                                    <span class="track"></span>
                                    <span>{{ __('settings.accounting_settings.fields.status') }}</span>
                                </label>
                                <span class="as-help">{{ __('settings.accounting_settings.fields.status_hint') }}</span>
                            </div>
                        </div>

                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">
                                {{ __('settings.common.cancel') }}
                            </button>
                            <button type="submit" class="btn btn-primary" id="asSubmit">
                                <i class="feather icon-save"></i> {{ __('settings.common.save') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- نافذة مستقلّة لا قسماً داخل نافذة التعديل: النموذج داخل نموذج غير
             صالح في HTML، والأهمّ أن خلط الإجراءين يجعل تصحيح وصف إعداد
             يغيّر شجرة الحسابات سهواً --}}
        <div class="modal fade" id="asRecodeModal" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content">
                    <form method="POST" id="asRecodeForm" action="">
                        @csrf
                        @method('PUT')

                        <div class="modal-header">
                            <h5 class="modal-title">
                                <i class="feather icon-hash"></i>
                                {{ __('settings.accounting_settings.recode.title') }}
                            </h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>

                        <div class="modal-body">
                            <div class="st-note dang">
                                <i class="feather icon-alert-triangle"></i>
                                <div>
                                    <b>{{ __('settings.accounting_settings.recode.warn_title') }}</b>
                                    <p>{{ __('settings.accounting_settings.recode.warn_body') }}</p>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>{{ __('settings.accounting_settings.recode.account') }}</label>
                                <div class="as-chosen" id="asRecodeAccount"></div>
                            </div>

                            {{-- موضع الحساب من الشجرة: بلا هذا يخمّن المستخدم
                                 كوداً لا صلة له بأبيه ولا بإخوته --}}
                            <div class="as-tree" id="asTree">
                                <div class="row-line">
                                    <span class="lbl">{{ __('settings.accounting_settings.suggest.parent') }}</span>
                                    <span class="val" id="asTreeParent"></span>
                                </div>
                                <div class="row-line">
                                    <span class="lbl">{{ __('settings.accounting_settings.suggest.siblings') }}</span>
                                    <span class="val" id="asTreeSiblings"></span>
                                </div>
                            </div>

                            <div class="as-form-row">
                                <div class="form-group">
                                    <label>{{ __('settings.accounting_settings.recode.current_code') }}</label>
                                    <div><span class="st-mono" id="asRecodeCurrent"></span></div>
                                </div>
                                <div class="form-group">
                                    <label for="asRecodeNew">{{ __('settings.accounting_settings.recode.new_code') }} *</label>
                                    <input type="text" class="form-control" id="asRecodeNew" name="code" required
                                        inputmode="numeric" maxlength="10" autocomplete="off"
                                        placeholder="{{ __('settings.accounting_settings.recode.placeholder') }}">
                                    <span class="as-code-status" id="asCodeStatus"></span>
                                    <span class="as-prefix-hint" id="asPrefixHint"></span>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="as-sublabel">
                                    <i class="feather icon-zap"></i>
                                    {{ __('settings.accounting_settings.suggest.title') }}
                                </label>
                                <div class="as-suggest" id="asSuggest"></div>
                                <span class="as-help">{{ __('settings.accounting_settings.suggest.intro') }}</span>
                            </div>

                            <div class="form-group mb-0">
                                <label class="as-confirm">
                                    <input type="checkbox" name="confirm" value="1" id="asRecodeConfirm" required>
                                    <span>{{ __('settings.accounting_settings.recode.confirm_label') }}</span>
                                </label>
                            </div>
                        </div>

                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">
                                {{ __('settings.common.cancel') }}
                            </button>
                            <button type="submit" class="btn btn-danger" id="asRecodeSubmit">
                                <i class="feather icon-hash"></i> {{ __('settings.accounting_settings.recode.submit') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
@endsection

@section('scripts')
    <script>
        (function () {
            // ── طيّ لوح الشرح ──
            var guideToggle = document.getElementById('asGuideToggle');
            var guideBody = document.getElementById('asGuideBody');

            if (guideToggle) {
                guideToggle.addEventListener('click', function () {
                    var hidden = guideBody.classList.toggle('as-hidden');
                    guideToggle.textContent = hidden
                        ? guideToggle.dataset.show
                        : guideToggle.dataset.hide;
                });
            }

            // ── الوصول من رابطٍ خارجي (#as-key-… أو #as-group-…) ──
            // الترويسة الثابتة تحجب القفزة الأصلية، فيُمرَّر إلى العنصر ويُبرَز
            if (location.hash && /^#as-(key|group)-[\w-]+$/.test(location.hash)) {
                var targetEl = document.getElementById(location.hash.slice(1));
                if (targetEl) {
                    if (targetEl.classList.contains('as-row')) targetEl.classList.add('as-targeted');
                    setTimeout(function () { targetEl.scrollIntoView({ block: 'center' }); }, 60);
                }
            }

            // ── تصفية الجداول في المتصفح ──
            // ثمانون إعداداً في أربع عشرة بطاقة: البحث في الصفحة أسرع من
            // رحلة إلى الخادم، والبيانات كلّها محمَّلة سلفاً
            var filter = document.getElementById('asFilter');
            var rows = Array.prototype.slice.call(document.querySelectorAll('.as-row'));
            var groups = Array.prototype.slice.call(document.querySelectorAll('.as-group'));
            var noMatch = document.getElementById('asNoMatch');

            if (filter) {
                filter.addEventListener('input', function () {
                    var term = filter.value.trim().toLowerCase();
                    var shown = 0;

                    rows.forEach(function (row) {
                        var hit = term === '' || (row.dataset.search || '').indexOf(term) !== -1;
                        row.classList.toggle('as-hidden', !hit);
                        if (hit) shown++;
                    });

                    // البطاقة التي خلت من الصفوف تُخفى بترويستها — وإلا بقيت
                    // أربع عشرة ترويسة فارغة معلّقة فوق نتيجة واحدة
                    groups.forEach(function (group) {
                        var visible = group.querySelectorAll('.as-row:not(.as-hidden)').length;
                        group.classList.toggle('as-hidden', visible === 0);
                    });

                    noMatch.classList.toggle('as-hidden', shown !== 0);
                });
            }

            @if ($canEdit)
                var form = document.getElementById('asEditForm');
                var nameInput = document.getElementById('asName');
                var descInput = document.getElementById('asDescription');
                var keyLabel = document.getElementById('asKey');
                var activeInput = document.getElementById('asActive');
                var search = document.getElementById('asAccountSearch');
                var results = document.getElementById('asAccountResults');
                var hidden = document.getElementById('asAccountId');
                var chosen = document.getElementById('asChosen');
                var chosenLabel = document.getElementById('asChosenLabel');
                var submit = document.getElementById('asSubmit');
                var codeInput = document.getElementById('asCodeInput');
                var codeApply = document.getElementById('asCodeApply');

                var editUrl = @json(route('AccountingSettings.edit', ['accountingSetting' => '__ID__']));
                var searchUrl = @json(route('AccountingSettings.accounts'));
                var noAccountText = @json(__('settings.accounting_settings.no_account'));
                var noResultsText = @json(__('settings.common.no_results'));
                var loadingText = @json(__('settings.common.loading'));
                var loadFailedText = @json(__('settings.accounting_settings.errors.load_failed'));
                var searchFailedText = @json(__('settings.accounting_settings.errors.search_failed'));
                var chkInactiveAccount = @json(__('settings.accounting_settings.check.inactive_account', ['code' => '__C__', 'account' => '__A__']));
                var codeNotFoundText = @json(__('settings.accounting_settings.errors.code_not_found', ['code' => '__CODE__']));
                var codeApplyText = @json(__('settings.accounting_settings.code_apply'));
                var codeCheckingText = @json(__('settings.accounting_settings.code_checking'));

                // ── فتح النافذة ──
                document.querySelectorAll('.as-edit').forEach(function (button) {
                    button.addEventListener('click', function () {
                        // البيانات تُقرأ من الخادم لا من الصفّ: قد يكون غيرُه
                        // بدّل الربط منذ فتح الصفحة، فيُحفظ القديم فوق الجديد
                        fetch(editUrl.replace('__ID__', button.dataset.id), {
                            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                            credentials: 'same-origin'
                        })
                            .then(function (response) {
                                if (!response.ok) throw new Error(response.status);
                                return response.json();
                            })
                            .then(function (data) {
                                form.action = data.update_url;
                                nameInput.value = data.name || '';
                                descInput.value = data.description || '';
                                keyLabel.textContent = data.key || '';
                                activeInput.checked = !!data.is_active;

                                setAccount(data.account_id, data.account_label);
                                clearResults();
                                search.value = '';
                                codeInput.value = data.account_code || '';

                                $('#asEditModal').modal('show');
                            })
                            .catch(function () {
                                alert(loadFailedText);
                            });
                    });
                });

                // ── البحث في شجرة الحسابات ──
                var timer = null;
                var lastTerm = null;

                search.addEventListener('input', function () {
                    var term = search.value.trim();

                    window.clearTimeout(timer);

                    if (term.length < 2) {
                        clearResults();
                        return;
                    }

                    // تأخير قصير: بلا هذا يرحل طلب لكل حرف، وترجع الردود
                    // بغير ترتيب إرسالها فتُعرض نتيجة حرف سابق فوق اللاحق
                    timer = window.setTimeout(function () {
                        lastTerm = term;
                        renderMessage(loadingText);

                        fetch(searchUrl + '?q=' + encodeURIComponent(term), {
                            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                            credentials: 'same-origin'
                        })
                            .then(function (response) {
                                if (!response.ok) throw new Error(response.status);
                                return response.json();
                            })
                            .then(function (data) {
                                if (lastTerm !== term) return;
                                renderResults(data.results || []);
                            })
                            .catch(function () {
                                renderMessage(searchFailedText);
                            });
                    }, 250);
                });

                // الضغط خارج القائمة يغلقها — قائمة معلّقة فوق بقية الحقول تعوقها
                document.addEventListener('click', function (event) {
                    if (!results.contains(event.target) && event.target !== search) {
                        clearResults();
                    }
                });

                function renderResults(items) {
                    results.innerHTML = '';

                    if (items.length === 0) {
                        renderMessage(noResultsText);
                        return;
                    }

                    items.forEach(function (item) {
                        var li = document.createElement('li');

                        var code = document.createElement('span');
                        code.className = 'code';
                        code.textContent = item.code === null ? '—' : item.code;

                        var name = document.createElement('span');
                        name.textContent = item.name;

                        li.appendChild(code);
                        li.appendChild(name);

                        li.addEventListener('click', function () {
                            setAccount(item.id, item.label);
                            search.value = '';
                            clearResults();
                        });

                        results.appendChild(li);
                    });

                    results.classList.add('open');
                }

                function renderMessage(text) {
                    results.innerHTML = '';
                    var li = document.createElement('li');
                    li.className = 'empty';
                    li.textContent = text;
                    results.appendChild(li);
                    results.classList.add('open');
                }

                function clearResults() {
                    results.innerHTML = '';
                    results.classList.remove('open');
                }

                function setAccount(id, label) {
                    hidden.value = id || '';
                    chosenLabel.textContent = label || noAccountText;
                    chosen.classList.toggle('empty', !id);
                }

                // ── تطبيق كود مكتوب يدوياً ──
                // الخادم يقبل الكود على أي حال، لكن الحلّ هنا يُري المستخدم اسم
                // الحساب قبل الحفظ — فلا يكتشف بعد الحفظ أنه ربط الإعداد بغيره
                function applyCode() {
                    var code = codeInput.value.trim();

                    if (code === '') return;

                    codeApply.disabled = true;
                    codeApply.textContent = codeCheckingText;

                    // يُسأل فاحص الكود لا البحث: البحث يصفّي المعطَّل فيقول
                    // «لا يوجد حساب بهذا الكود» عن حساب موجود ومعطَّل، ثم
                    // يرفضه الخادم برسالة مختلفة — رسالتان لسبب واحد
                    fetch(checkUrl + '?code=' + encodeURIComponent(code), {
                        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                        credentials: 'same-origin'
                    })
                        .then(function (response) {
                            if (!response.ok) throw new Error(response.status);
                            return response.json();
                        })
                        .then(function (data) {
                            if (data.found && data.account.is_active) {
                                setAccount(data.account.id, data.account.label);
                                clearResults();
                                search.value = '';
                            } else if (data.found) {
                                // موجود لكنه معطَّل: يُسمّى الحساب كي يعرف
                                // المستخدم أنه أصاب الكود وأخطأ الحساب
                                setAccount(null, chkInactiveAccount
                                    .replace('__C__', data.code)
                                    .replace('__A__', data.account.name));
                            } else {
                                // الكود المرفوض لا يُبقي الاختيار السابق معروضاً:
                                // من كتب كوداً خاطئاً وحفظ يظنّ أنه حفظه
                                setAccount(null, codeNotFoundText.replace('__CODE__', code));
                            }
                        })
                        .catch(function () {
                            setAccount(null, searchFailedText);
                        })
                        .finally(function () {
                            codeApply.disabled = false;
                            codeApply.textContent = codeApplyText;
                        });
                }

                codeApply.addEventListener('click', applyCode);

                // Enter داخل حقل الكود يطبّق الكود ولا يرسل النموذج
                codeInput.addEventListener('keydown', function (event) {
                    if (event.key === 'Enter') {
                        event.preventDefault();
                        applyCode();
                    }
                });

                // ── تغيير كود الحساب في الشجرة ──
                var recodeForm = document.getElementById('asRecodeForm');
                var recodeUrl = @json(route('AccountingSettings.account-code', ['accountingSetting' => '__ID__']));

                var suggestUrl = @json(route('AccountingSettings.code-suggestions', ['accountingSetting' => '__ID__']));
                var suggestBox = document.getElementById('asSuggest');
                var newCodeInput = document.getElementById('asRecodeNew');
                var prefixHint = document.getElementById('asPrefixHint');
                var treeParent = document.getElementById('asTreeParent');
                var treeSiblings = document.getElementById('asTreeSiblings');

                var sugLoadingText = @json(__('settings.accounting_settings.suggest.loading'));
                var sugFailedText = @json(__('settings.accounting_settings.suggest.failed'));
                var sugNoneText = @json(__('settings.accounting_settings.suggest.none'));
                var noParentText = @json(__('settings.accounting_settings.suggest.no_parent'));
                var sibMoreText = @json(__('settings.accounting_settings.suggest.siblings_more', ['count' => '__N__']));
                var prefixOkText = @json(__('settings.accounting_settings.suggest.prefix_ok', ['prefix' => '__P__']));
                var prefixWarnText = @json(__('settings.accounting_settings.suggest.prefix_warning', ['prefix' => '__P__']));

                var activePrefix = null;

                document.querySelectorAll('.as-recode').forEach(function (button) {
                    button.addEventListener('click', function () {
                        var data = button.dataset;

                        recodeForm.action = recodeUrl.replace('__ID__', data.id);
                        document.getElementById('asRecodeAccount').textContent = data.account;
                        document.getElementById('asRecodeCurrent').textContent = data.code || '—';
                        newCodeInput.value = '';
                        document.getElementById('asRecodeConfirm').checked = false;

                        activePrefix = null;
                        activeAccountId = data.accountId || null;
                        setStatus('', '', false);
                        prefixHint.textContent = '';
                        prefixHint.className = 'as-prefix-hint';
                        treeParent.textContent = '';
                        treeSiblings.textContent = '';
                        suggestBox.textContent = sugLoadingText;

                        loadSuggestions(data.id);

                        $('#asRecodeModal').modal('show');
                    });
                });

                function loadSuggestions(id) {
                    fetch(suggestUrl.replace('__ID__', id), {
                        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                        credentials: 'same-origin'
                    })
                        .then(function (response) {
                            if (!response.ok) throw new Error(response.status);
                            return response.json();
                        })
                        .then(renderSuggestions)
                        .catch(function () {
                            suggestBox.textContent = sugFailedText;
                        });
                }

                function renderSuggestions(data) {
                    activePrefix = data.prefix || null;

                    // موضع الحساب: الأب ثم الأشقاء
                    if (data.parent) {
                        treeParent.textContent = (data.parent.code === null ? '—' : data.parent.code) + ' - ' + data.parent.name;
                    } else {
                        treeParent.innerHTML = '';
                        treeParent.appendChild(muted(noParentText));
                    }

                    treeSiblings.innerHTML = '';
                    var siblings = data.siblings || [];

                    if (siblings.length === 0) {
                        treeSiblings.appendChild(muted('—'));
                    } else {
                        siblings.forEach(function (sibling) {
                            var chip = document.createElement('span');
                            chip.className = 'sib';
                            chip.textContent = sibling.code === null ? '—' : sibling.code;
                            chip.title = sibling.name;
                            treeSiblings.appendChild(chip);
                        });

                        var hidden = (data.siblings_total || 0) - siblings.length;
                        if (hidden > 0) {
                            treeSiblings.appendChild(muted(sibMoreText.replace('__N__', hidden)));
                        }
                    }

                    // الاقتراحات — كل رقاقة تملأ الحقل بضغطة
                    suggestBox.innerHTML = '';
                    var suggestions = data.suggestions || [];

                    if (suggestions.length === 0) {
                        var empty = document.createElement('span');
                        empty.className = 'as-empty';
                        empty.textContent = data.error || sugNoneText;
                        suggestBox.appendChild(empty);
                        return;
                    }

                    suggestions.forEach(function (item) {
                        // زرّ لا div: يُبلَغ عنه كزرّ ويُدرَك بلوحة المفاتيح
                        var chip = document.createElement('button');
                        chip.type = 'button';
                        chip.className = 'as-chip';

                        var code = document.createElement('span');
                        code.className = 'c';
                        code.textContent = item.code;

                        var reason = document.createElement('span');
                        reason.className = 'r';
                        reason.textContent = item.reason;

                        chip.appendChild(code);
                        chip.appendChild(reason);

                        chip.addEventListener('click', function () {
                            newCodeInput.value = item.code;
                            newCodeInput.dispatchEvent(new Event('input'));
                            newCodeInput.focus();
                        });

                        suggestBox.appendChild(chip);
                    });
                }

                function muted(text) {
                    var span = document.createElement('span');
                    span.className = 'muted';
                    span.textContent = text;
                    return span;
                }

                // تنبيه البادئة إرشادي لا مانع: 19% من أبناء الشجرة الحالية
                // لا تتبع بادئة أبيها، ومنعُها يوقف تعديلات مشروعة
                function checkPrefix() {
                    var value = newCodeInput.value.trim();

                    if (!activePrefix || value === '') {
                        prefixHint.textContent = '';
                        prefixHint.className = 'as-prefix-hint';
                        return;
                    }

                    var follows = value.indexOf(activePrefix) === 0;
                    prefixHint.textContent = (follows ? prefixOkText : prefixWarnText).replace('__P__', activePrefix);
                    prefixHint.className = 'as-prefix-hint ' + (follows ? 'ok' : 'warn');
                }

                newCodeInput.addEventListener('input', checkPrefix);

                // ── فحص حيّ: هل الكود شاغر، ولمن هو إن كان محجوزاً؟ ──
                var checkUrl = @json(route('AccountingSettings.code-check'));
                var codeStatus = document.getElementById('asCodeStatus');
                var recodeSubmit = document.getElementById('asRecodeSubmit');
                var checkTimer = null;
                var checkSeq = 0;
                var activeAccountId = null;

                var chkChecking = @json(__('settings.accounting_settings.check.checking'));
                var chkAvailable = @json(__('settings.accounting_settings.check.available', ['code' => '__C__']));
                var chkTaken = @json(__('settings.accounting_settings.check.taken', ['code' => '__C__', 'account' => '__A__']));
                var chkTakenOff = @json(__('settings.accounting_settings.check.taken_inactive', ['code' => '__C__', 'account' => '__A__']));
                var chkSelf = @json(__('settings.accounting_settings.check.self'));
                var chkInvalid = @json(__('settings.accounting_settings.check.invalid'));
                var chkFailed = @json(__('settings.accounting_settings.check.failed'));

                function setStatus(text, kind, blocked) {
                    codeStatus.textContent = text;
                    codeStatus.className = 'as-code-status' + (kind ? ' ' + kind : '');
                    // منع الإرسال راحةٌ لا حماية — الخادم يرفض الكود المحجوز
                    // على أي حال، وقيد التفرّد في القاعدة خلفه
                    recodeSubmit.disabled = !!blocked;
                }

                newCodeInput.addEventListener('input', function () {
                    var value = newCodeInput.value.trim();

                    window.clearTimeout(checkTimer);

                    if (value === '') {
                        setStatus('', '', false);
                        return;
                    }

                    if (!/^\d{1,10}$/.test(value)) {
                        setStatus(chkInvalid, 'busy', true);
                        return;
                    }

                    setStatus(chkChecking, 'neutral', false);

                    checkTimer = window.setTimeout(function () {
                        var seq = ++checkSeq;

                        fetch(checkUrl + '?code=' + encodeURIComponent(value) + '&except=' + (activeAccountId || 0), {
                            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                            credentials: 'same-origin'
                        })
                            .then(function (response) {
                                if (!response.ok) throw new Error(response.status);
                                return response.json();
                            })
                            .then(function (data) {
                                // ردّ قديم وصل بعد أحدث منه لا يُعرض
                                if (seq !== checkSeq) return;

                                if (!data.valid) {
                                    setStatus(chkInvalid, 'busy', true);
                                } else if (data.is_self) {
                                    setStatus(chkSelf, 'neutral', false);
                                } else if (data.found) {
                                    var template = data.account.is_active ? chkTaken : chkTakenOff;
                                    setStatus(
                                        template.replace('__C__', data.code).replace('__A__', data.account.name),
                                        'busy',
                                        true
                                    );
                                } else {
                                    setStatus(chkAvailable.replace('__C__', data.code), 'ok', false);
                                }
                            })
                            .catch(function () {
                                if (seq !== checkSeq) return;
                                setStatus(chkFailed, 'neutral', false);
                            });
                    }, 250);
                });

                recodeForm.addEventListener('submit', function () {
                    recodeSubmit.disabled = true;
                });

                // الحفظ بلا حساب مرفوض في الخادم على أي حال — منعه هنا يوفّر
                // رحلة كاملة تنتهي برسالة خطأ فوق صفحة أُعيد تحميلها
                form.addEventListener('submit', function (event) {
                    if (!hidden.value) {
                        event.preventDefault();
                        search.focus();
                        renderMessage(noAccountText);
                        return;
                    }

                    submit.disabled = true;
                });
            @endif
        })();
    </script>
@endsection
