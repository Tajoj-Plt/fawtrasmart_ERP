@extends('sitting::master')

@section('title', 'الاشتراك والتفعيل')

@section('content')
    @include('sitting::components._account_ui')

    <style>
        .fw-hero { border-radius: 14px; padding: 1.5rem 1.7rem; color: #fff; margin-bottom: 1.25rem; }
        .fw-hero.ok      { background: linear-gradient(135deg, #5e50ee 0%, #8b7ff5 100%); }
        .fw-hero.warn    { background: linear-gradient(135deg, #b5710a 0%, #e0952a 100%); }
        .fw-hero.danger  { background: linear-gradient(135deg, #b03232 0%, #e06a5c 100%); }
        .fw-hero .plan   { font-size: .82rem; opacity: .85; letter-spacing: .04em; }
        .fw-hero .days   { font-size: 3rem; font-weight: 700; line-height: 1; font-variant-numeric: tabular-nums; }
        .fw-hero .unit   { font-size: 1rem; font-weight: 600; opacity: .9; }
        .fw-hero .meta   { font-size: .88rem; opacity: .92; }
        .fw-card { border: 1px solid #e6e9f0; border-radius: 12px; padding: 1.1rem 1.25rem; margin-bottom: 1rem; background: #fff; }
        .fw-card h6 { font-weight: 600; margin-bottom: .9rem; padding-bottom: .55rem; border-bottom: 1px solid #eef1f6; }

        /* شريط الاستهلاك: اللون يتبع النسبة لا الميزة — الأحمر يعني «توقّف» */
        .fw-meter { height: 8px; background: #eef1f6; border-radius: 999px; overflow: hidden; }
        .fw-meter span { display: block; height: 100%; border-radius: 999px; background: #28c76f; transition: width .3s; }
        .fw-meter.warn span { background: #ff9f43; }
        .fw-meter.danger span { background: #ea5455; }
        .fw-usage-row { padding: .65rem 0; border-bottom: 1px solid #f2f4f8; }
        .fw-usage-row:last-child { border-bottom: 0; }
        .fw-num { font-variant-numeric: tabular-nums; }

        .fw-perm { display: flex; align-items: center; gap: .5rem; padding: .35rem 0; font-size: .87rem; }
        .fw-perm .dot { width: 9px; height: 9px; border-radius: 50%; flex: none; }
        .fw-perm .dot.on { background: #28c76f; }
        .fw-perm .dot.off { background: #d5d9e2; }
        .fw-perm.off { color: #98a2b3; }

        .fw-chip {
            border-radius: 999px; padding: .13rem .55rem;
            font-size: .72rem; font-weight: 600;
            background: #eef2ff; color: #3843a8;
        }
        .fw-chip.ok  { background: #ecfdf3; color: #1f7a46; }
        .fw-chip.warn { background: #fff8eb; color: #a86707; }
        .fw-chip.err  { background: #fff0f0; color: #b91c1c; }

        /* رأس بطاقة القسم: عنوان + شارات ملخص في سطر واحد */
        .fw-sec-head {
            display: flex; justify-content: space-between; align-items: center;
            gap: .75rem; margin-bottom: .8rem;
            padding-bottom: .55rem; border-bottom: 1px solid #eef1f6;
        }
        .fw-sec-title { font-size: .93rem; font-weight: 700; color: #1f2937; }
        .fw-sec-badges { display: flex; gap: .4rem; align-items: center; flex-shrink: 0; }

        .fw-perm-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(190px, 1fr));
            gap: .2rem .75rem;
        }

        .fw-stale { background: #fff8e6; border-right: 4px solid #ff9f43; padding: .8rem 1rem; border-radius: 8px; }

        /* ===== شاشة التفعيل (غير مربوط) ===== */
        .fw-activate {
            background: #fff; border: 1px solid #e6e9f0; border-radius: 16px;
            overflow: hidden; box-shadow: 0 10px 30px rgba(18, 24, 40, .06);
        }
        .fw-activate-head {
            display: flex; align-items: center; gap: 1rem; color: #fff;
            padding: 1.4rem 1.6rem;
            background: linear-gradient(135deg, #5e50ee 0%, #8b7ff5 100%);
        }
        .fw-activate-head .mark {
            width: 48px; height: 48px; border-radius: 14px; flex: none;
            display: flex; align-items: center; justify-content: center;
            background: rgba(255, 255, 255, .18); border: 1px solid rgba(255, 255, 255, .28);
        }
        .fw-activate-head .mark svg { width: 24px; height: 24px; }
        .fw-activate-head h5 { font-weight: 700; margin: 0 0 .2rem; font-size: 1.05rem; }
        .fw-activate-head p { margin: 0; font-size: .85rem; opacity: .92; }

        .fw-activate-form { padding: 1.4rem 1.6rem; }
        .fw-activate-side {
            padding: 1.4rem 1.6rem; background: #fafbfd;
            border-inline-start: 1px solid #eef1f6;
        }
        .fw-activate-side h6 { font-size: .85rem; font-weight: 700; color: #374151; margin-bottom: .6rem; }
        .fw-benefits { list-style: none; padding: 0; margin: 0; }
        .fw-benefits li {
            display: flex; align-items: flex-start; gap: .5rem;
            font-size: .84rem; color: #4b5563; padding: .28rem 0;
        }
        .fw-benefits li span { color: #28c76f; font-weight: 700; flex: none; }
        .fw-note { font-size: .79rem; color: #6b7280; line-height: 1.75; margin: 0; }
        .fw-side-link {
            display: inline-block; margin-top: .9rem; font-size: .82rem; font-weight: 600;
            color: #5e50ee; text-decoration: none;
        }

        /* سجلّ المحاولات: التسجيل الذي لا يُرى لا يُستعمل */
        .fw-attempts { border-top: 1px solid #eef1f6; padding: 1rem 1.6rem 1.2rem; }
        .fw-attempts h6 { font-size: .82rem; font-weight: 700; color: #374151; margin-bottom: .6rem; }
        .fw-attempts h6 small { font-weight: 400; color: #9aa3af; font-size: .74rem; }
        .fw-attempt {
            display: flex; align-items: baseline; gap: .55rem;
            font-size: .8rem; color: #4b5563; padding: .3rem 0;
            border-bottom: 1px dashed #f0f2f6;
        }
        .fw-attempt:last-child { border-bottom: 0; }
        .fw-attempt .dot { width: 7px; height: 7px; border-radius: 50%; flex: none; }
        .fw-attempt.ok .dot { background: #28c76f; }
        .fw-attempt.bad .dot { background: #ea5455; }
        .fw-attempt .txt { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .fw-attempt .at { color: #9aa3af; font-size: .74rem; flex: none; }
        code.fw-mono { direction: ltr; display: inline-block; font-size: .85em; }
    </style>

    <div class="container-fluid">

        {{-- **رسائل النتيجة.** كانت غائبة عن هذه الشاشة وحدها: يضغط المستخدم
             «تحقّق واربط» فيُرفض المفتاح أو يسقط التحقّق، ويعود إلى الصفحة
             نفسها بلا كلمة — فيظنّ الزرّ معطّلاً وهو يعمل ويقول شيئاً لا
             يُعرض. القالب الأمّ لا يعرضها، فكل شاشة تعرضها بنفسها --}}
        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        @if (session('warning'))
            <div class="alert alert-warning">{{ session('warning') }}</div>
        @endif

        @if (session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0" style="padding-inline-start:1.1rem">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- ═══════════ غير مربوط: شاشة التفعيل ═══════════ --}}
        @unless ($linked)
            {{-- الشاشة الوحيدة التي يراها من لا اشتراك له، فهي واجهة النظام
                 عنده: ترويسةٌ تقول ما سيحدث، ونموذجٌ إلى جانبه ما يفتحه
                 الربط، وتحتهما سجلّ المحاولات — فلا يبحث عن سبب الرفض في
                 شاشة أخرى --}}
            <div class="fw-activate">
                <div class="fw-activate-head">
                    <div class="mark">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"
                             stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <rect x="3" y="11" width="18" height="11" rx="2"/>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                        </svg>
                    </div>
                    <div>
                        <h5>تفعيل النظام وربطه بلوحة تحكّم فوترة</h5>
                        <p>
                            بالربط يجلب النظام باقتك وصلاحياتها وحدود استخدامك وفواتيرك،
                            ويُحدّثها تلقائياً كل {{ $syncInterval }} دقيقة.
                        </p>
                    </div>
                </div>

                <div class="row no-gutters">
                    <div class="col-lg-7 fw-activate-form">
                        @include('sitting::fawtra._link_form', [
                            'credentials' => $credentials,
                            'suggestedDomain' => $suggestedDomain,
                        ])
                    </div>

                    <div class="col-lg-5 fw-activate-side">
                        <h6>ما يفتحه الربط</h6>
                        <ul class="fw-benefits">
                            <li><span>&check;</span> باقتك وصلاحياتها الكاملة</li>
                            <li><span>&check;</span> حدود الاستخدام والإضافات المشتراة</li>
                            <li><span>&check;</span> فواتير الاشتراك وتاريخ التجديد</li>
                            <li><span>&check;</span> تنبيهات قرب الانتهاء وبلوغ الحدود</li>
                        </ul>

                        <h6 class="mt-3">أمان المفاتيح</h6>
                        <p class="fw-note">
                            السرّ يُحفظ في ملف البيئة على خادمك، <b>ولا يُخزَّن في قاعدة البيانات</b>
                            ولا يظهر في أي شاشة أو سجلّ بعد حفظه. وكل طلبٍ إلى فوترة موقَّع
                            بـHMAC-SHA256 وينتهي أثره خلال خمس دقائق.
                        </p>

                        <a class="fw-side-link" href="{{ rtrim(config('fawtra.base_url'), '/') }}"
                           target="_blank" rel="noopener">افتح لوحة فوترة &nearr;</a>
                    </div>
                </div>

                @if ($attempts->isNotEmpty())
                    <div class="fw-attempts">
                        <h6>آخر المحاولات <small>مسجَّلة في سجلّ الأحداث</small></h6>
                        @foreach ($attempts as $attempt)
                            @php $ok = str_contains($attempt->action, '.ok'); @endphp
                            <div class="fw-attempt {{ $ok ? 'ok' : 'bad' }}">
                                <span class="dot"></span>
                                <span class="txt">{{ $attempt->description }}</span>
                                <span class="at">{{ $attempt->created_at?->diffForHumans() }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @else

            {{-- ═══════════ التنبيهات ═══════════ --}}
            @foreach ($alerts as $alert)
                <div class="alert alert-{{ $alert->severityClass() }} d-flex justify-content-between align-items-start">
                    <div>
                        <strong>{{ $alert->title ?: $alert->typeLabel() }}</strong>
                        @if ($alert->body)
                            <div style="font-size:.88rem">{{ $alert->body }}</div>
                        @endif
                        <small class="text-muted">{{ $alert->typeLabel() }} · {{ $alert->remote_created_at?->diffForHumans() }}</small>
                    </div>
                    <form method="POST" action="{{ route('Fawtra.alert.dismiss', $alert) }}">
                        @csrf
                        <button class="btn btn-sm btn-link text-muted">إخفاء</button>
                    </form>
                </div>
            @endforeach

            @if ($record?->isStale())
                <div class="fw-stale mb-3">
                    <strong>البيانات المعروضة قديمة.</strong>
                    آخر مزامنة ناجحة {{ $record->last_synced_at?->diffForHumans() ?? 'لم تتم' }}.
                    @if ($record->last_error)
                        السبب: {{ $record->last_error }}
                    @endif
                </div>
            @endif

            {{-- ═══════════ البطاقة الرئيسية ═══════════ --}}
            @php
                $days = $record?->days_remaining;
                $tone = match (true) {
                    ! $record || $record->is_expired => 'danger',
                    $days !== null && $days <= 7 => 'danger',
                    $days !== null && $days <= 30 => 'warn',
                    default => 'ok',
                };
            @endphp

            <div class="fw-hero {{ $tone }}">
                <div class="row align-items-center">
                    <div class="col-md-5">
                        <div class="plan">{{ $record?->plan_name ?: 'بلا باقة' }}</div>
                        <div class="d-flex align-items-baseline" style="gap:.4rem">
                            <span class="days">{{ $days !== null ? max(0, $days) : '—' }}</span>
                            <span class="unit">يوماً متبقياً</span>
                        </div>
                        <div class="meta mt-1">
                            الحالة: <b>{{ $record?->statusLabel() ?? '—' }}</b>
                            @if ($record?->is_trial) · تجريبي @endif
                        </div>
                    </div>
                    <div class="col-md-7">
                        <div class="row meta">
                            <div class="col-6 mb-2">ينتهي في: <b>{{ $record?->end_date?->format('Y-m-d') ?: '—' }}</b></div>
                            <div class="col-6 mb-2">التجديد التلقائي: <b>{{ $record?->auto_renew ? 'مفعّل' : 'غير مفعّل' }}</b></div>
                            <div class="col-6 mb-2">الحساب: <b>{{ $account['company'] ?? $account['name'] ?? '—' }}</b></div>
                            <div class="col-6 mb-2">آخر مزامنة: <b>{{ $record?->last_synced_at?->diffForHumans() ?? 'لم تتم' }}</b></div>
                            {{-- المشترك نفسه: مَن سجّل الاشتراك وبريده وجواله — السؤال
                                 الأول عند أي مراجعة مع فوترة، ولم يكن يظهر في أي مكان --}}
                            <div class="col-6">المشترك: <b>{{ $account['name'] ?? '—' }}</b></div>
                            <div class="col-6">البريد: <b dir="ltr" style="unicode-bidi:embed">{{ $account['email'] ?? '—' }}</b></div>
                            @if (! empty($account['phone']))
                                <div class="col-6 mt-2">الجوال: <b dir="ltr" style="unicode-bidi:embed">{{ $account['phone'] }}</b></div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap" style="gap:.5rem">
                <div class="text-muted" style="font-size:.85rem">
                    تُحدَّث البيانات تلقائياً كل {{ $syncInterval }} دقيقة عبر المهمة المجدولة
                    <code class="fw-mono">fawtra:sync</code>
                </div>
                <div class="d-flex" style="gap:.5rem">
                    <form method="POST" action="{{ route('Fawtra.sync') }}">
                        @csrf
                        <button class="btn btn-primary btn-sm">زامن الآن</button>
                    </form>
                    <a class="btn btn-outline-secondary btn-sm" href="#link-section">تغيير المفتاح</a>
                </div>
            </div>

            <div class="row">
                {{-- ═══════════ حدود الاستخدام ═══════════ --}}
                <div class="col-lg-7">

                        @forelse ($usageSections as $section)
                            @php
                                $sectionItems = $section['items'] ?? [];
                                $sectionCount = count($sectionItems);
                                $sectionExhausted = count(array_filter($sectionItems, fn ($it) => (bool) ($it['exhausted'] ?? false)));
                            @endphp
                            <div class="fw-card">
                                <div class="fw-sec-head">
                                    <span class="fw-sec-title">{{ $section['section_label'] ?? ($section['section'] ?? 'عام') }}</span>
                                    <div class="fw-sec-badges">
                                        <span class="fw-chip">{{ $sectionCount }} عنصر</span>
                                        @if ($sectionExhausted > 0)
                                            <span class="fw-chip err">{{ $sectionExhausted }} مستنفد</span>
                                        @else
                                            <span class="fw-chip ok">✓ سليم</span>
                                        @endif
                                    </div>
                                </div>

                                @foreach (($section['items'] ?? []) as $item)
                                    @php
                                        $unlimited = (bool) ($item['unlimited'] ?? false);
                                        $percent   = (int) ($item['percent'] ?? 0);
                                        $meterTone = $percent >= 100 ? 'danger' : ($percent >= 80 ? 'warn' : '');
                                    @endphp
                                    <div class="fw-usage-row">
                                        <div class="d-flex justify-content-between align-items-baseline mb-1">
                                            <div>
                                                <b>{{ $item['label'] ?? $item['key'] ?? '—' }}</b>
                                                <small class="text-muted">· {{ $item['period'] ?? '' }}</small>
                                            </div>
                                            <div class="fw-num" style="font-size:.88rem">
                                                @if ($unlimited)
                                                    <span class="fw-chip ok">بلا حدّ</span>
                                                @else
                                                    {{ $item['used'] ?? 0 }} / {{ $item['allowance'] ?? 0 }}
                                                    <span class="text-muted">({{ $percent }}%)</span>
                                                @endif
                                            </div>
                                        </div>
                                        @unless ($unlimited)
                                            <div class="fw-meter {{ $meterTone }}">
                                                <span style="width: {{ min(100, $percent) }}%"></span>
                                            </div>
                                            <small class="text-muted">
                                                المتبقّي: {{ $item['remaining'] ?? 0 }}
                                                @if ((int) ($item['addon_quantity'] ?? 0) > 0)
                                                    · منها {{ $item['addon_quantity'] }} إضافة
                                                @endif
                                                @if ($item['exhausted'] ?? false)
                                                    · <span class="text-danger fw-bold">مستنفد</span>
                                                @endif
                                            </small>
                                        @endunless
                                    </div>
                                @endforeach
                            </div>
                        @empty
                            <div class="fw-card"><p class="text-muted mb-0">لا توجد حدود مُتابَعة بعد. اضغط «زامن الآن».</p></div>
                        @endforelse

                    {{-- ═══════════ الإضافات المشتراة ═══════════ --}}
                    <div class="fw-card">
                        <h6>الإضافات المشتراة</h6>

                        @forelse ($addons as $addon)
                            <div class="d-flex justify-content-between fw-usage-row">
                                <div>
                                    <b>{{ $addon['label'] ?? $addon['key'] }}</b>
                                    <small class="text-muted d-block">{{ $addon['period'] ?? '' }}</small>
                                </div>
                                <div class="fw-num text-end">
                                    <span class="badge bg-primary">+{{ $addon['addon_quantity'] }}</span>
                                    <small class="text-muted d-block">
                                        الباقة {{ $addon['included'] ?? 0 }} ← الإجمالي {{ $addon['allowance'] ?? 0 }}
                                    </small>
                                </div>
                            </div>
                        @empty
                            <p class="text-muted mb-0">لا إضافات فوق الباقة.</p>
                        @endforelse
                    </div>
                </div>

                {{-- ═══════════ العمود الجانبي ═══════════ --}}
                <div class="col-lg-5">

                    {{-- صلاحيات الباقة —— كل قسم بطاقة مستقلة --}}
                    @forelse ($permissionSections as $section)
                        @php
                            $sectionEnabled = (int) ($section['enabled'] ?? 0);
                            $sectionTotal   = max(1, (int) ($section['total'] ?? 0));
                            $sectionRate    = (int) round(($sectionEnabled / $sectionTotal) * 100);
                        @endphp
                        <div class="fw-card">
                            <div class="fw-sec-head">
                                <span class="fw-sec-title">{{ $section['section_label'] ?? ($section['section'] ?? 'عام') }}</span>
                                <div class="fw-sec-badges">
                                    <span class="fw-chip ok">{{ $sectionEnabled }} / {{ $section['total'] ?? 0 }}</span>
                                    <span class="fw-chip">{{ $sectionRate }}%</span>
                                </div>
                            </div>
                            <div class="fw-meter" style="height:5px; margin-bottom:.7rem">
                                <span style="width:{{ max(5, min(100, $sectionRate)) }}%; background:{{ $sectionRate >= 100 ? '#28c76f' : ($sectionRate >= 60 ? '#ff9f43' : '#5e50ee') }}"></span>
                            </div>
                            <div class="fw-perm-grid">
                                @foreach (($section['permissions'] ?? []) as $permission)
                                    @php
                                        $enabled = (bool) ($permission['enabled'] ?? false);
                                        $label   = $permission['label'] ?? ($permissionLabels[$permission['key'] ?? ''] ?? ($permission['key'] ?? '—'));
                                    @endphp
                                    <div class="fw-perm {{ $enabled ? '' : 'off' }}">
                                        <span class="dot {{ $enabled ? 'on' : 'off' }}"></span>
                                        <span>{{ $label }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @empty
                        <div class="fw-card"><p class="text-muted mb-0">لم تصل صلاحيات الباقة بعد.</p></div>
                    @endforelse

                    {{-- الفوترة --}}
                    <div class="fw-card">
                        <h6>الفوترة</h6>

                        @if ($billing !== [])
                            <table class="table table-sm mb-0">
                                <tbody class="fw-num">
                                <tr><td class="text-muted">قيمة الباقة</td><td>{{ number_format((float) ($billing['base_amount'] ?? 0), 2) }} {{ $billing['currency'] ?? '' }}</td></tr>
                                <tr><td class="text-muted">الإضافات</td><td>{{ number_format((float) ($billing['addons_amount'] ?? 0), 2) }}</td></tr>
                                <tr><td class="text-muted">الضريبة ({{ $billing['tax_percent'] ?? 0 }}%)</td><td>{{ number_format((float) ($billing['tax_amount'] ?? 0), 2) }}</td></tr>
                                <tr class="fw-bold"><td>الإجمالي</td><td>{{ number_format((float) ($billing['total_amount'] ?? 0), 2) }} {{ $billing['currency'] ?? '' }}</td></tr>
                                </tbody>
                            </table>

                            @if ((int) ($billing['pending_payments'] ?? 0) > 0)
                                <div class="alert alert-warning mt-2 mb-0" style="font-size:.87rem">
                                    {{ $billing['pending_payments'] }} دفعة بانتظار السداد.
                                </div>
                            @endif

                            @if ($last = ($billing['last_payment'] ?? null))
                                <hr>
                                <div style="font-size:.87rem">
                                    <div class="text-muted mb-1">آخر دفعة</div>
                                    <div>فاتورة <code class="fw-mono">{{ $last['invoice_number'] ?? $last['number'] ?? '—' }}</code></div>
                                    <div class="fw-num">{{ number_format((float) ($last['amount'] ?? 0), 2) }} {{ $last['currency'] ?? '' }}</div>
                                    <small class="text-muted">
                                        {{ $last['paid_at'] ? \Carbon\Carbon::parse($last['paid_at'])->format('Y-m-d') : '' }}
                                    </small>
                                </div>
                            @endif
                        @else
                            <p class="text-muted mb-0">لا بيانات فوترة بعد.</p>
                        @endif
                    </div>

                    {{-- بيانات الربط --}}
                    <div class="fw-card" id="link-section">
                        <h6>بيانات الربط</h6>

                        <table class="table table-sm">
                            <tbody>
                            <tr><td class="text-muted">لوحة فوترة</td><td><code class="fw-mono">{{ $credentials['base_url'] }}</code></td></tr>
                            <tr><td class="text-muted">معرّف العميل</td><td><code class="fw-mono">{{ $credentials['client_id'] }}</code></td></tr>
                            <tr><td class="text-muted">الدومين</td><td><code class="fw-mono">{{ $credentials['domain'] }}</code></td></tr>
                            <tr>
                                <td class="text-muted">السرّ</td>
                                <td>
                                    <span class="badge bg-secondary">محفوظ في .env</span>
                                    <small class="text-muted d-block">لا يُعرض ولا يُخزَّن في قاعدة البيانات</small>
                                </td>
                            </tr>
                            @if ($record?->activation_serial)
                                <tr><td class="text-muted">رقم التفعيل</td><td><code class="fw-mono">{{ $record->activation_serial }}</code></td></tr>
                            @endif
                            </tbody>
                        </table>

                        <details>
                            <summary class="text-primary" style="cursor:pointer">تغيير المفتاح</summary>
                            <div class="mt-3">
                                @include('sitting::fawtra._link_form', ['credentials' => $credentials, 'suggestedDomain' => $suggestedDomain])
                            </div>
                        </details>

                        <form method="POST" action="{{ route('Fawtra.unlink') }}" class="mt-2"
                              onsubmit="return confirm('سيتوقّف جلب بيانات الاشتراك. البيانات المحفوظة تبقى. أتريد المتابعة؟')">
                            @csrf
                            <button class="btn btn-outline-danger btn-sm">قطع الربط</button>
                        </form>
                    </div>

                    {{-- حالة المزامنة --}}
                    <div class="fw-card">
                        <h6>حالة المزامنة</h6>
                        <table class="table table-sm mb-0">
                            <tbody>
                            <tr><td class="text-muted">آخر نجاح</td><td>{{ $record?->last_synced_at?->format('Y-m-d H:i') ?? '—' }}</td></tr>
                            <tr><td class="text-muted">آخر محاولة</td><td>{{ $record?->last_attempt_at?->format('Y-m-d H:i') ?? '—' }}</td></tr>
                            {{-- الرفع نصف المزامنة الآخر: بدونه تبقى أرقام
                                 الاستهلاك عند فوترة صفراً مهما عمل النظام --}}
                            <tr>
                                <td class="text-muted">آخر رفع للاستهلاك</td>
                                <td>{{ $record?->last_usage_report_at?->format('Y-m-d H:i') ?? '—' }}</td>
                            </tr>
                            @if (($record?->consecutive_failures ?? 0) > 0)
                                <tr>
                                    <td class="text-muted">إخفاقات متتالية</td>
                                    <td class="text-danger">{{ $record->consecutive_failures }}</td>
                                </tr>
                                <tr>
                                    <td class="text-muted">المحاولة التالية</td>
                                    <td>{{ $record->retry_after?->format('H:i') ?? '—' }}</td>
                                </tr>
                            @endif
                            </tbody>
                        </table>

                        @if ($record?->last_error)
                            <div class="alert alert-danger mt-2 mb-0" style="font-size:.85rem">{{ $record->last_error }}</div>
                        @endif
                    </div>
                </div>
            </div>
        @endunless
    </div>
@endsection
