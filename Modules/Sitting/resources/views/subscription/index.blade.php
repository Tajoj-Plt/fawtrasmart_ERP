@extends('sitting::master')

@section('title', __('settings.subscription.title'))

@section('content')
    @include('sitting::components._account_ui')

    <style>
        /* بطاقة المدّة: أبرز عنصر في الشاشة، فتأخذ لوناً كاملاً لا حدّاً */
        .sub-hero { border-radius: 14px; padding: 1.5rem 1.7rem; color: #fff; margin-bottom: 1.25rem; }
        .sub-hero.ok     { background: linear-gradient(135deg, #5e50ee 0%, #8b7ff5 100%); }
        .sub-hero.warn   { background: linear-gradient(135deg, #b5710a 0%, #e0952a 100%); }
        .sub-hero.danger { background: linear-gradient(135deg, #b03232 0%, #e06a5c 100%); }
        .sub-hero .plan { font-size: .82rem; opacity: .85; letter-spacing: .04em; }
        .sub-hero .days { font-size: 3rem; font-weight: 700; line-height: 1; font-variant-numeric: tabular-nums; }
        .sub-hero .unit { font-size: 1rem; font-weight: 600; opacity: .9; }
        .sub-hero .meta { font-size: .88rem; opacity: .92; }
        .sub-bar { height: 8px; background: rgba(255,255,255,.25); border-radius: 999px; overflow: hidden; margin-top: 1rem; }
        .sub-bar span { display: block; height: 100%; background: #fff; border-radius: 999px; }

        .sub-def { display: flex; justify-content: space-between; gap: 1rem; padding: .6rem 0; border-bottom: 1px dashed #eef0f4; }
        .sub-def:last-child { border-bottom: 0; }
        .sub-def dt { margin: 0; color: #6b7280; font-size: .85rem; font-weight: 500; }
        .sub-def dd { margin: 0; font-weight: 600; text-align: left; color: #1b2430; }

        .sub-usage { display: flex; align-items: center; gap: .75rem; padding: .6rem 0; border-bottom: 1px solid #f2f4f7; }
        .sub-usage:last-child { border-bottom: 0; }
        .sub-usage .ic { flex: 0 0 auto; width: 34px; height: 34px; border-radius: 9px; background: #eef1f5; color: #4b5563; display: grid; place-items: center; }
        .sub-usage .name { font-size: .9rem; font-weight: 600; color: #1b2430; }
        .sub-usage .fig { margin-right: auto; text-align: left; font-variant-numeric: tabular-nums; }
        .sub-usage .fig b { font-size: 1.05rem; }
        .sub-meter { height: 5px; background: #eef1f5; border-radius: 999px; overflow: hidden; margin-top: .3rem; width: 120px; }
        .sub-meter span { display: block; height: 100%; border-radius: 999px; background: #2b6cb0; }
        .sub-meter span.warn { background: #c08a1a; }
        .sub-meter span.full { background: #a3342a; }

        .sub-sec-head {
            display: flex; justify-content: space-between; align-items: center;
            gap: .75rem; margin-bottom: .6rem;
            padding-bottom: .5rem; border-bottom: 1px solid #eef0f4;
        }
        .sub-sec-title { margin: 0; font-size: .93rem; font-weight: 700; color: #1f2937; }
        .sub-sec-badges { display: flex; gap: .4rem; flex-shrink: 0; }
        .sub-pill {
            border-radius: 999px; padding: .13rem .52rem;
            font-size: .72rem; font-weight: 600;
            background: #eef2ff; color: #3843a8;
        }
        .sub-pill.ok  { background: #ecfdf3; color: #1f7a46; }
        .sub-pill.err { background: #fff0f0; color: #b91c1c; }
        .sub-perm-grid { display: flex; flex-wrap: wrap; gap: .4rem; margin-top: .45rem; }

    </style>

    <div class="sl-page-head">
        <div class="sl-page-head__titles">
            <span class="sl-page-head__icon" aria-hidden="true"><i class="feather icon-award"></i></span>
            <div style="min-width:0">
                <h2 class="sl-page-head__title">{{ __('settings.subscription.title') }}</h2>
                <nav>
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('settings.common.home') }}</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('SittingAccount.dashboard') }}">{{ __('settings.common.settings') }}</a></li>
                        <li class="breadcrumb-item active">{{ __('settings.subscription.title') }}</li>

                    </ol>
                </nav>
            </div>
        </div>
        <div class="sl-page-head__actions">
            @permission('settings.subscription.manage')
                {{-- **التجديد والترقية يقعان في لوحة فوترة لا هنا.**
                     هذا النظام مشترٍ لا بائع في هذه العلاقة: الدفع والفوترة
                     وتغيير الباقة كلّها عندهم، وزرٌّ يفتح نموذجاً محلياً يوهم
                     المستخدم أنه جدّد وهو لم يفعل. والروابط تصل من اللقطة
                     نفسها فلا يُركّب هنا عنوان --}}
                @php
                    // الرابط من اللقطة إن وصل، وإلا صفحة اللوحة نفسها من `.env`
                    // — ولا يُركَّب مسار داخلي بالتخمين
                    $renewUrl = $dashboardLinks['renew'] ?? ($fawtraLinked ? rtrim((string) config('fawtra.base_url'), '/') : null);
                @endphp

                @if ($renewUrl)
                    <a class="btn btn-primary" href="{{ $renewUrl }}" target="_blank" rel="noopener">
                        <i class="feather icon-refresh-cw"></i> {{ __('settings.subscription.renew') }}
                        <i class="feather icon-external-link" style="font-size:.8em"></i>
                    </a>
                    @if (! empty($dashboardLinks['upgrade']))
                        <a class="btn btn-outline-primary" href="{{ $dashboardLinks['upgrade'] }}" target="_blank" rel="noopener">
                            <i class="feather icon-trending-up"></i> ترقية الباقة
                        </a>
                    @endif
                    @if (! empty($dashboardLinks['addons']))
                        <a class="btn btn-outline-secondary" href="{{ $dashboardLinks['addons'] }}" target="_blank" rel="noopener">
                            <i class="feather icon-plus-circle"></i> شراء إضافات
                        </a>
                    @endif
                @else
                    {{-- لا اشتراك: الوجهة الوحيدة صفحة الأسعار في اللوحة.
                         ولا نموذج تسجيل هنا — الاشتراك يُدار خارج هذا النظام --}}
                    <a class="btn btn-primary" href="{{ $pricingUrl }}" target="_blank" rel="noopener">
                        <i class="feather icon-shopping-cart"></i> اشترك الآن
                        <i class="feather icon-external-link" style="font-size:.8em"></i>
                    </a>
                @endif
            @endpermission

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

        {{-- ══ مدّة الاشتراك ══ --}}
        @if (! $subscription)
            {{-- **لا شيء يُعرض بلا اشتراك.** كانت الصفحة تُظهر مدّةً وسعراً
                 وحجم استعمال من سجلٍّ محلّي كتبه المسؤول بيده، فيقرؤها من
                 لا اشتراك له اشتراكاً قائماً. الآن: سطرٌ يقول الحقيقة، وزرٌّ
                 إلى صفحة الأسعار، ولا رقم واحد --}}
            <div class="ax-panel">
                <div class="body" style="text-align:center;padding:2.5rem 1.5rem">
                    <i class="feather icon-lock" style="font-size:2.4rem;color:#b7bec8"></i>
                    <h4 style="margin:.9rem 0 .4rem">لا يوجد اشتراك مسجَّل لهذا النظام</h4>
                    <p style="color:#7b8194;max-width:520px;margin:0 auto 1.2rem">
                        الاشتراكات والباقات والتجديد كلّها تُدار في لوحة تحكّم فوترة، لا من داخل النظام.
                        اختر باقتك من صفحة الأسعار، ثم اربط النظام بمفتاح التكامل — وستظهر هنا
                        باقتك وحدود استخدامك وفواتيرك تلقائياً.
                    </p>
                    <div style="display:flex;gap:.6rem;justify-content:center;flex-wrap:wrap">
                        <a class="btn btn-primary" href="{{ $pricingUrl }}" target="_blank" rel="noopener">
                            <i class="feather icon-shopping-cart"></i> تصفّح الباقات والأسعار
                        </a>
                        <a class="btn btn-outline-secondary" href="{{ route('Fawtra.index') }}">
                            <i class="feather icon-link"></i> ربط النظام بمفتاح التكامل
                        </a>
                    </div>
                </div>
            </div>
        @else
            @php
                $days = $subscription->daysRemaining();
                $urgency = $subscription->urgency();
            @endphp

            {{-- مصدر البيانات يُقال صراحةً في كل مرّة: هذه الشاشة مرآةٌ لا
                 سجلّ، ولا يُعدَّل منها شيء --}}
            @unless ($subscription->exists)
                <div class="alert alert-info" style="margin-bottom:1rem">
                    هذه البيانات مقروءة من <b>لوحة تحكّم فوترة</b> وتُحدَّث تلقائياً — لا تُعدَّل من هنا.
                    <a href="{{ route('Fawtra.index') }}">افتح شاشة الاشتراك المرتبط</a>
                    لعرض الباقة وصلاحياتها وحدود الاستخدام والإضافات.
                </div>
            @endunless

            <div class="sub-hero {{ $urgency }}">
                <div class="row align-items-center">
                    <div class="col-md-5 col-12">
                        <div class="plan">{{ __('settings.subscription.current_plan') }}</div>
                        <h3 style="color:#fff;font-weight:700;margin:.2rem 0 .5rem">{{ $subscription->plan_name }}</h3>
                        <div class="meta">
                            <span class="ax-badge" style="background:rgba(255,255,255,.22);color:#fff">
                                {{ $subscription->statusLabel() }}
                            </span>
                            <span style="margin-right:.4rem">{{ $subscription->cycleLabel() }}</span>
                            @if ($subscription->price > 0)
                                — {{ number_format((float) $subscription->price, 2) }} {{ $subscription->currency }}
                            @endif
                        </div>
                    </div>

                    <div class="col-md-4 col-12 text-center">
                        @if ($subscription->isPerpetual())
                            <div class="days" style="font-size:1.8rem">{{ __('settings.subscription.perpetual_short') }}</div>
                            <div class="unit">{{ __('settings.subscription.no_expiry') }}</div>
                        @elseif ($days < 0)
                            <div class="days">{{ abs($days) }}</div>
                            <div class="unit">{{ __('settings.subscription.days_since_expiry') }}</div>
                        @else
                            <div class="days">{{ $days }}</div>
                            <div class="unit">{{ $days === 1 ? __('settings.subscription.day_left') : __('settings.subscription.days_left') }}</div>
                        @endif
                    </div>

                    <div class="col-md-3 col-12">
                        <div class="meta">
                            <div>{{ __('settings.subscription.from') }} {{ $subscription->started_at?->format('Y-m-d') }}</div>
                            <div>{{ __('settings.subscription.to') }} {{ $subscription->expires_at?->format('Y-m-d') ?? '—' }}</div>
                        </div>
                    </div>
                </div>

                @if (! $subscription->isPerpetual())
                    <div class="sub-bar">
                        <span style="width:{{ max(2, min(100, (int) $subscription->elapsedPercent())) }}%"></span>
                    </div>
                    <div class="meta" style="margin-top:.4rem;font-size:.8rem">
                        @if ($days < 0)
                            {{ __('settings.subscription.expired_msg') }}
                        @elseif ($days <= 7)
                            {{ __('settings.subscription.expiring_week') }}
                        @elseif ($days <= 30)
                            {{ __('settings.subscription.expiring_month') }}
                        @else
                            {{ __('settings.subscription.elapsed', ['percent' => $subscription->elapsedPercent()]) }}
                        @endif
                    </div>
                @endif
            </div>
        @endif

        <div class="row">
            {{-- ══ المشترك ══ --}}
            {{-- مَن صاحب الاشتراك فعلاً؟ الاسم والبريد والجوال المسجَّلة في
                 لوحة فوترة — لا بيانات المنشأة أدناه (تلك ما يكتبه المسؤول
                 لفواتيره). كان السؤال «بأي بريد اشتركنا؟» بلا جواب في النظام --}}
            <div class="col-lg-7 col-12">
                <div class="ax-panel">
                    <div class="head">
                        <span class="ic"><i class="feather icon-user"></i></span>
                        <h4>{{ __('settings.subscription.subscriber') }}</h4>
                        <span class="spacer"></span>
                        <span class="count">{{ __('settings.subscription.from_fawtra') }}</span>
                    </div>
                    <div class="body">
                        @if (empty($subscriber))
                            <div class="ax-note warn" style="margin-bottom:0">
                                <i class="feather icon-alert-triangle" style="margin-top:.15rem"></i>
                                <div>
                                    <strong>{{ __('settings.subscription.subscriber_empty') }}</strong>
                                    <p>{!! __('settings.subscription.subscriber_empty_hint', ['link' => '<a href="'.route('Fawtra.index').'">'.__('settings.subscription.subscriber_sync') .'</a>']) !!}</p>
                                </div>
                            </div>
                        @else
                            <dl class="mb-0">
                                @foreach ([
                                    [__('settings.subscription.subscriber_name'), $subscriber['name'] ?? null, false],
                                    [__('settings.common.email'), $subscriber['email'] ?? null, true],
                                    [__('settings.common.phone'), $subscriber['phone'] ?? null, true],
                                    [__('settings.subscription.subscriber_company'), $subscriber['company'] ?? null, false],
                                    [__('settings.subscription.subscriber_domain'), $subscriber['domain'] ?? (isset($subscriber['subdomain']) ? $subscriber['subdomain'].'.fawtra.com' : null), true],
                                ] as [$label, $value, $mono])
                                    <div class="ax-def">
                                        <dt>{{ $label }}</dt>
                                        <dd style="max-width:62%">
                                            @if ($value)
                                                @if ($mono)
                                                    <span class="ax-mono" dir="ltr">{{ $value }}</span>
                                                @else
                                                    {{ $value }}
                                                @endif
                                            @else
                                                <span style="color:#c3c0d3">—</span>
                                            @endif
                                        </dd>
                                    </div>
                                @endforeach
                            </dl>
                        @endif
                    </div>
                    <div class="foot">
                        <a href="{{ route('Fawtra.index') }}" class="btn btn-sm btn-outline-secondary">
                            <i class="feather icon-link"></i> {{ __('settings.subscription.subscriber_linked_screen') }}
                        </a>
                        @if (! empty($dashboardLinks['subscription'] ?? null))
                            <a href="{{ $dashboardLinks['subscription'] }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary">
                                <i class="feather icon-external-link"></i> {{ __('settings.subscription.subscriber_dashboard') }}
                            </a>
                        @endif
                    </div>
                </div>
            </div>

            {{-- ══ بيانات المنشأة ══ --}}
            <div class="col-lg-5 col-12">
                <div class="ax-panel">
                    <div class="head">
                        <span class="ic"><i class="feather icon-home"></i></span>
                        <h4>{{ __('settings.subscription.establishment') }}</h4>
                        <span class="spacer"></span>
                        <span class="count">{{ __('settings.subscription.from_account') }}</span>
                    </div>
                    <div class="body">
                        {{-- المصدر واحد: ما يُكتب في شاشة معلومات الحساب هو ما
                             يظهر هنا. وإن لم يُملأ بعد، تُعرَض بيانات الفرع
                             الرئيسي احتياطاً مع تنبيه صريح --}}
                        @unless ($establishment['from_account'] ?? false)
                            <div class="ax-note warn" style="margin-bottom:1rem">
                                <i class="feather icon-alert-triangle" style="margin-top:.15rem"></i>
                                <div>
                                    <strong>{{ __('settings.subscription.account_empty') }}</strong>
                                    <p>{!! __('settings.subscription.account_empty_hint', ['link' => '<a href="'.route('SittingAccount.index').'">'.__('settings.subscription.complete_account').'</a>']) !!}</p>
                                </div>
                            </div>
                        @endunless

                        <dl class="mb-0">
                            @foreach ([
                                [__('settings.account.trade_name'), $establishment['name'] ?? null, false],
                                [__('settings.common.code'), $establishment['code'] ?? null, true],
                                [__('settings.account.commercial_registration'), $establishment['commercial_registration'] ?? null, true],
                                [__('settings.account.tax_number'), $establishment['tax_number'] ?? null, true],
                                [__('settings.common.phone'), $establishment['phone'] ?? null, true],
                                [__('settings.common.email'), $establishment['email'] ?? null, true],
                                [__('settings.common.address'), $establishment['address'] ?? null, false],
                                [__('settings.common.currency'), $establishment['currency'] ?? null, false],
                                [__('settings.subscription.timezone'), $establishment['timezone'] ?? null, true],
                            ] as [$label, $value, $mono])
                                <div class="ax-def">
                                    <dt>{{ $label }}</dt>
                                    <dd style="max-width:62%">
                                        @if ($value)
                                            @if ($mono)
                                                <span class="ax-mono">{{ $value }}</span>
                                            @else
                                                {{ $value }}
                                            @endif
                                        @else
                                            <span style="color:#c3c0d3">—</span>
                                        @endif
                                    </dd>
                                </div>
                            @endforeach
                        </dl>
                    </div>
                    <div class="foot">
                        <a href="{{ route('SittingAccount.index') }}" class="btn btn-sm btn-outline-secondary">
                            <i class="feather icon-edit-2"></i> {{ __('settings.subscription.edit_account') }}
                        </a>
                    </div>
                </div>
            </div>

            {{-- ══ حجم الاستعمال —— كل قسم بطاقة مستقلة ══ --}}
            <div class="col-12">
                <div class="ax-panel">
                    <div class="head">
                        <i class="feather icon-bar-chart-2" style="color:#2b6cb0"></i>
                        <h4>{{ __('settings.subscription.usage') }}</h4>
                        <span class="spacer"></span>
                        <span class="count">{{ count($usage) }} عنصر</span>
                    </div>
                    <div class="body">
                        @if ($usageSections)
                        <div class="row">
                        @forelse ($usageSections as $section)
                            @php
                                $sectionItems     = $section['items'] ?? [];
                                $sectionCount     = count($sectionItems);
                                $sectionExhausted = count(array_filter($sectionItems, fn ($it) => (bool) ($it['exhausted'] ?? false)));
                            @endphp
                            <div class="col-lg-6 col-12">
                                <div class="ax-panel" style="margin-bottom:1rem">
                                    <div class="head">
                                        <span class="ic"><i class="feather icon-layers"></i></span>
                                        <h4 style="font-size:.9rem">{{ $section['section_label'] ?? ($section['section'] ?? 'عام') }}</h4>
                                        <span class="spacer"></span>
                                        <span class="count">{{ $sectionCount }}</span>
                                        @if ($sectionExhausted > 0)
                                            <span class="sub-pill err" style="margin-inline-start:.3rem">⚠ {{ $sectionExhausted }} مستنفد</span>
                                        @else
                                            <span class="sub-pill ok" style="margin-inline-start:.3rem">✓</span>
                                        @endif
                                    </div>
                                    <div class="body">
                                        @foreach ($sectionItems as $row)
                                            <div class="sub-usage">
                                                <div class="ic"><i class="feather icon-{{ $row['icon'] ?? 'award' }}"></i></div>
                                                <div class="name">
                                                    {{ $row['label'] }}
                                                    @if (! empty($row['period']))
                                                        <span style="color:#8a93a0;font-size:.72rem">· {{ $row['period'] }}</span>
                                                    @endif
                                                    @if (! empty($row['addon_quantity']))
                                                        <span class="ax-badge" style="background:#eef7ee;color:#2f7a3f;font-size:.68rem">
                                                            +{{ $row['addon_quantity'] }} إضافة
                                                        </span>
                                                    @endif
                                                </div>
                                                <div class="fig">
                                                    <b>{{ number_format($row['value']) }}</b>
                                                    @if ($row['limit'])
                                                        <span style="color:#8a93a0;font-size:.82rem">/ {{ number_format($row['limit']) }}</span>
                                                        <div class="sub-meter">
                                                            <span class="{{ $row['percent'] >= 100 ? 'full' : ($row['percent'] >= 80 ? 'warn' : '') }}"
                                                                style="width:{{ max(2, $row['percent']) }}%"></span>
                                                        </div>
                                                    @else
                                                        <span style="color:#b7bec8;font-size:.78rem">بلا حدّ</span>
                                                    @endif
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="col-12">
                                <div class="ax-empty">
                                    <i class="feather icon-info"></i>
                                    لا توجد بيانات استخدام حالياً.
                                </div>
                            </div>
                        @endforelse
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>


        {{-- ══ ما تفتحه الباقة ══ --}}
        {{-- الصلاحيات والإضافات مقروءتان من لقطة الاشتراك لا من قائمة مكتوبة:
             ترقية الباقة عند فوترة تظهر هنا في المزامنة التالية بلا نشر --}}
        @if ($planPermissions !== [])
            <div class="ax-panel">
                <div class="head">
                    <i class="feather icon-unlock" style="color:#2b6cb0"></i>
                    <h4>ما تفتحه الباقة</h4>
                    <span class="count">{{ count(array_keys(array_filter($planPermissions))) }} من {{ count($planPermissions) }}</span>
                </div>
                <div class="body">
                    @if (! empty($permissionSections))
                        <div class="row">
                        @foreach ($permissionSections as $section)
                            @php
                                $permEnabled = count(array_filter($section['permissions'] ?? [], fn ($p) => (bool) ($p['enabled'] ?? false)));
                                $permTotal   = max(1, count($section['permissions'] ?? []));
                                $permRate    = (int) round(($permEnabled / $permTotal) * 100);
                            @endphp
                            <div class="col-lg-6 col-12" style="margin-bottom:1rem">
                                <div class="ax-panel" style="margin-bottom:0">
                                    <div class="head">
                                        <span class="ic"><i class="feather icon-unlock"></i></span>
                                        <h4 style="font-size:.9rem">{{ $section['section_label'] ?? ($section['section'] ?? 'عام') }}</h4>
                                        <span class="spacer"></span>
                                        <span class="count">{{ $permEnabled }} / {{ count($section['permissions'] ?? []) }}</span>
                                    </div>
                                    <div class="body">
                                        <div class="sub-meter" style="width:100%;margin-bottom:.65rem">
                                            <span class="{{ $permRate >= 100 ? 'full' : ($permRate >= 80 ? 'warn' : '') }}" style="width:{{ max(5, min(100, $permRate)) }}%"></span>
                                        </div>
                                        <div class="sub-perm-grid">
                                            @foreach (($section['permissions'] ?? []) as $permission)
                                                @php
                                                    $allowed = (bool) ($permission['enabled'] ?? false);
                                                    $label   = $permission['label'] ?? ($permissionLabels[$permission['key'] ?? ''] ?? ($permission['key'] ?? '—'));
                                                @endphp
                                                <span class="ax-badge"
                                                      style="background:{{ $allowed ? '#eef7ee' : '#f6f7f9' }};color:{{ $allowed ? '#2f7a3f' : '#98a1ad' }}">
                                                    {{ $allowed ? '✔' : '✘' }} {{ $label }}
                                                </span>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                        </div>
                    @else
                        <div class="sub-perm-grid">
                            @foreach ($planPermissions as $key => $allowed)
                                <span class="ax-badge"
                                      style="background:{{ $allowed ? '#eef7ee' : '#f6f7f9' }};color:{{ $allowed ? '#2f7a3f' : '#98a1ad' }}">
                                    {{ $allowed ? '✔' : '✘' }} {{ $permissionLabels[$key] ?? $key }}
                                </span>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        @endif

        @if ($planAddons !== [])
            <div class="ax-panel">
                <div class="head">
                    <i class="feather icon-plus-circle" style="color:#2b6cb0"></i>
                    <h4>الإضافات المشتراة</h4>
                    <span class="count">{{ count($planAddons) }}</span>
                </div>
                <div class="body">
                    <dl style="margin:0">
                        @foreach ($planAddons as $addon)
                            <div class="sub-usage">
                                <div class="name">{{ $addon['label'] ?? $addon['key'] ?? '—' }}</div>
                                <div class="fig">
                                    <b>+{{ number_format((int) ($addon['addon_quantity'] ?? 0)) }}</b>
                                    <span style="color:#8a93a0;font-size:.82rem">
                                        فوق {{ number_format((int) ($addon['included'] ?? 0)) }} في الباقة
                                    </span>
                                </div>
                            </div>
                        @endforeach
                    </dl>
                </div>
            </div>
        @endif

        {{-- ══ الفواتير والمدفوعات ══ --}}
        {{-- فواتير الاشتراك تصدرها فوترة لا هذا النظام: تُعرض من اللقطة،
             ويُفتح أصلها عندهم للطباعة والتنزيل. والجدول المحلّي يبقى لمن
             لم يربط نظامه بلوحة --}}
        @if ($dashboardInvoices !== [])
            <div class="ax-panel">
                <div class="head">
                    <i class="feather icon-file-text" style="color:#2b6cb0"></i>
                    <h4>{{ __('settings.subscription.invoices') }}</h4>
                    <span class="count">{{ count($dashboardInvoices) }}</span>
                    <span class="spacer"></span>
                    @if (! empty($dashboardLinks['invoices']))
                        <a href="{{ $dashboardLinks['invoices'] }}" target="_blank" rel="noopener"
                           style="font-size:.82rem">كلّها في لوحة فوترة ↗</a>
                    @endif
                </div>
                <div class="body" style="padding:0">
                    <div class="table-responsive">
                        <table class="ax-table">
                            <thead>
                                <tr>
                                    <th>{{ __('settings.subscription.invoice_number') }}</th>
                                    <th>{{ __('settings.subscription.price') }}</th>
                                    <th>{{ __('settings.subscription.issued_at') }}</th>
                                    <th>المدّة</th>
                                    <th class="text-center">{{ __('settings.common.status') }}</th>
                                    <th class="text-center">المستند</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($dashboardInvoices as $invoice)
                                    @php
                                        $status = $invoice['status'] ?? '';
                                        $tone = match ($status) {
                                            'completed', 'paid' => 'on',
                                            'pending' => 'warn',
                                            'failed', 'cancelled' => 'dang',
                                            default => 'off',
                                        };
                                        $statusLabel = match ($status) {
                                            'completed' => 'مدفوعة',
                                            'paid' => 'مدفوعة',
                                            'pending' => 'بانتظار السداد',
                                            'failed' => 'أخفقت',
                                            'cancelled' => 'ملغاة',
                                            default => $status ?: '—',
                                        };
                                    @endphp
                                    <tr>
                                        <td><span class="ax-mono">{{ $invoice['number'] ?? '—' }}</span></td>
                                        <td class="num">
                                            <b>{{ number_format((float) ($invoice['amount'] ?? 0), 2) }}</b>
                                            {{ $invoice['currency'] ?? '' }}
                                        </td>
                                        <td class="num">
                                            {{ ! empty($invoice['issued_at']) ? \Illuminate\Support\Carbon::parse($invoice['issued_at'])->format('Y-m-d') : '—' }}
                                        </td>
                                        <td class="num">
                                            @if (! empty($invoice['period_from']))
                                                {{ $invoice['period_from'] }} ← {{ $invoice['period_to'] ?? '—' }}
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td class="text-center"><span class="ax-badge {{ $tone }}">{{ $statusLabel }}</span></td>
                                        <td class="text-center">
                                            @if (! empty($invoice['download_url']))
                                                <a href="{{ $invoice['download_url'] }}" target="_blank" rel="noopener"
                                                   title="تنزيل الإيصال"><i class="feather icon-download"></i></a>
                                            @endif
                                            @if (! empty($invoice['print_url']))
                                                <a href="{{ $invoice['print_url'] }}" target="_blank" rel="noopener"
                                                   title="طباعة" style="margin-inline-start:.5rem"><i class="feather icon-printer"></i></a>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @else
            <div class="ax-panel">
                <div class="head">
                    <i class="feather icon-file-text" style="color:#2b6cb0"></i>
                    <h4>{{ __('settings.subscription.invoices') }}</h4>
                    <span class="count">0</span>
                </div>
                <div class="body">
                    <div class="ax-empty">
                        <i class="feather icon-file-text"></i>
                        لا توجد فواتير اشتراك واردة من لوحة فوترة حتى الآن.
                    </div>
                </div>
            </div>
        @endif

        {{-- ══ سجلّ المدد ══ --}}
        @if ($history->count() > 1)
            <div class="ax-panel">
                <div class="head">
                    <i class="feather icon-clock" style="color:#2b6cb0"></i>
                    <h4>{{ __('settings.subscription.history') }}</h4>
                    <span class="count">{{ $history->count() }}</span>
                </div>
                <div class="body" style="padding:0">
                    <div class="table-responsive">
                        <table class="ax-table">
                            <thead>
                                <tr>
                                    <th>{{ __('settings.subscription.plan') }}</th>
                                    <th>{{ __('settings.subscription.from') }}</th>
                                    <th>{{ __('settings.subscription.to') }}</th>
                                    <th>{{ __('settings.subscription.cycle') }}</th>
                                    <th>{{ __('settings.subscription.price') }}</th>
                                    <th class="text-center">{{ __('settings.common.status') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($history as $period)
                                    <tr>
                                        <td><b>{{ $period->plan_name }}</b></td>
                                        <td class="num">{{ $period->started_at?->format('Y-m-d') }}</td>
                                        <td class="num">{{ $period->expires_at?->format('Y-m-d') ?? __('settings.subscription.perpetual_short') }}</td>
                                        <td>{{ $period->cycleLabel() }}</td>
                                        <td class="num">{{ number_format((float) $period->price, 2) }} {{ $period->currency }}</td>
                                        <td class="text-center">
                                            <span class="ax-badge {{ $period->statusColor() }}">{{ $period->statusLabel() }}</span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif
    </div>

    {{-- **حُذف نموذج تسجيل الاشتراك من هذا النظام.**
         لا يُنشأ اشتراك ولا يُجدَّد ولا يُفعَّل من هنا بحال: كلّه في لوحة
         تحكّم فوترة. وبقاء نموذجٍ يكتب صفّاً محلياً كان يعني مستخدماً يظنّ
         أنه اشترك وهو لم يفعل شيئاً يعرفه أحد. --}}

@endsection
