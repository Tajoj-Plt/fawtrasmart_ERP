@extends('account::master')

@section('title', 'لوحة الحسابات')

@section('content')
    @php
        /*
            لوحة الحسابات.

            **اللون هنا دلالةٌ لا زينة.** كانت البطاقات الأربع تُلوَّن
            بالترتيب — أخضر، أحمر، أزرق، أصفر — بلا صلةٍ بما تحمله: «صافي
            الدخل» أزرقُ سواءٌ أكان ربحاً أم خسارة، و«رصيد الخزائن» أصفرُ
            دائماً وإن كان ممتازاً. فاللون يزيّن ولا يُخبر.

            صار لكل رقمٍ لونٌ يتبع **حاله** لا موضعَه: الأخضر لِما هو سليم،
            الأحمر لِما يحتاج تصرّفاً، الأصفر لِما يحتاج انتباهاً، والأزرق
            لخبرٍ محايد لا حكم فيه.
        */
        $money = fn ($v) => number_format((float) $v, 2);

        // التواريخ تُخزَّن بوقتٍ صفري، وطباعتُه «00:00:00» في كل سطر ضجيج
        $day = function ($v) {
            if (! $v) {
                return '—';
            }

            try {
                return \Carbon\Carbon::parse($v)->format('Y-m-d');
            } catch (\Throwable $e) {
                return (string) $v;
            }
        };

        // حال الأرقام الأربعة الكبرى — منها يُشتقّ اللون
        $netTone = $netIncome > 0 ? 'success' : ($netIncome < 0 ? 'danger' : 'info');
        $treasuryTone = $totalTreasuryBalance > 0 ? 'success' : ($totalTreasuryBalance < 0 ? 'danger' : 'warning');

        // الأصناف المحاسبية وألوانها — واحدة في كل الشاشة
        $typeTones = [
            'assets' => 'info',
            'liabilities' => 'warning',
            'equity' => 'primary',
            'revenues' => 'success',
            'expenses' => 'danger',
        ];

        $typeNames = [
            'assets' => 'أصول',
            'liabilities' => 'خصوم',
            'equity' => 'حقوق ملكية',
            'revenues' => 'إيرادات',
            'expenses' => 'مصروفات',
        ];

        $entryStates = [
            0 => ['معلّق', 'warning'],
            1 => ['معتمد', 'success'],
            2 => ['مرفوض', 'danger'],
        ];

        $hasIssues = ! $equation['balanced'] || $unbalanced['count'] > 0;
    @endphp

    @include('stock::components.page-head', [
        'title' => 'لوحة الحسابات',
        'icon' => 'pie-chart',
        'sub' => \Carbon\Carbon::now()->translatedFormat('l، d F Y')
            . ' — ' . number_format($totalEntries) . ' قيداً'
            . ($fullFinancialView ? ' على ' . number_format($totalAccounts) . ' حساباً' : ''),
        'crumbs' => [
            'الرئيسية' => url('/'),
            'الحسابات' => null,
        ],
    ])

    <div class="content-body">
        <section id="accounts-dashboard">

            {{-- ══════════════ سلامة الدفاتر ══════════════ --}}
            {{--
                أوّل ما ينبغي أن يراه المحاسب، ولم يكن في اللوحة إطلاقاً:
                هل الدفاتر متوازنة؟ قيدٌ مدينُه لا يساوي دائنَه يفسد كل
                تقريرٍ بعده بصمت، ولا تُظهره شاشة القيود — إذ تعرض المستندات
                لا مجاميعها.
            --}}
            {{-- سلامة الدفاتر شأنُ المنشأة كلِّها — لا تُعرض للمقيَّد --}}
            @if ($fullFinancialView)
            <div class="alert alert-{{ $hasIssues ? 'danger' : 'success' }} d-flex flex-wrap align-items-center justify-content-between gap-2">
                <div>
                    <i class="feather icon-{{ $hasIssues ? 'alert-octagon' : 'check-circle' }}"></i>
                    @if ($hasIssues)
                        <strong>الدفاتر غير متوازنة.</strong>
                        @if ($unbalanced['count'] > 0)
                            {{ $unbalanced['count'] }} قيداً مدينُه لا يساوي دائنَه.
                        @endif
                        @unless ($equation['balanced'])
                            فرق معادلة الميزانية {{ $money(abs($equation['difference'])) }}.
                        @endunless
                    @else
                        <strong>الدفاتر متوازنة.</strong>
                        الأصول تساوي الخصوم وحقوق الملكية وصافي النتيجة.
                    @endif
                </div>

                {{-- الفترةُ التي تُقرأ عليها اللوحة: الأرقامُ كلُّها منسوبةٌ إليها --}}
                @if (! empty($accountingPeriod['label']))
                    <span class="badge {{ empty($accountingPeriod['unconfigured']) ? 'bg-info' : 'bg-warning' }}">
                        <i class="feather icon-calendar"></i> {{ $accountingPeriod['label'] }}
                    </span>
                @endif

                @if ($periodStatus['enforced'])
                    <span class="badge bg-primary">
                        <i class="feather icon-lock"></i>
                        مقفل قبل {{ $periodStatus['lock_before'] ?? '—' }}
                    </span>
                @else
                    <span class="badge bg-secondary">
                        <i class="feather icon-unlock"></i> إقفال الفترات غير مفعَّل
                    </span>
                @endif
            </div>

            {{--
                ══════════ ما خرج عن التقويم ══════════

                مستندٌ تاريخُه لا يقع في أيّ فترة يُحفَظ بفترةٍ فارغة. والشاشاتُ
                تُقرأ على فترة، فلا يظهر في أيٍّ منها — لا في القوائم ولا في
                التقارير ولا في الإقرار — وهو قائمٌ في الدفتر ورصيدُ الحساب
                يحمله. فبقاؤه مخفيّاً أسوأُ من رفضه، ويُعلَن هنا حتى يُلحَق.
            --}}
            @if (($periodStatus['orphans']['total'] ?? 0) > 0)
                <div class="alert alert-warning d-flex flex-wrap align-items-center" style="gap:.75rem">
                    <i class="feather icon-alert-triangle"></i>

                    <span>
                        <strong>{{ number_format($periodStatus['orphans']['total']) }}</strong>
                        مستنداً خارج التقويم المحاسبي — لا يظهر في القوائم ولا في التقارير:
                        @foreach ($periodStatus['orphans']['tables'] as $orphan)
                            {{ $orphan['label'] }} ({{ number_format($orphan['count']) }}){{ ! $loop->last ? '، ' : '' }}
                        @endforeach
                    </span>

                    <span class="text-muted">
                        ولّد الفترات ثم نفّذ <code>php artisan accounting:refile-periods</code> لإلحاقها،
                        أو اخترْ «بلا فترة» في فلتر أيّ شاشة لمراجعتها.
                    </span>

                    @if (Route::has('AccountingPeriods.index'))
                        <a href="{{ route('AccountingPeriods.index') }}" class="btn btn-sm btn-warning">
                            <i class="feather icon-calendar"></i> اضبط الفترات
                        </a>
                    @endif
                </div>
            @endif
            @endif {{-- fullFinancialView: سلامة الدفاتر + ما خرج عن التقويم --}}

            {{-- ══════════════ الأرقام الكبرى ══════════════ --}}
            {{--
                شريط المقاييس (`stk-metrics`) بدل أربع بطاقاتٍ عامّة.

                كانت البطاقات `card stat-card` من قالب النظام القديم: أربع
                علبٍ متباعدة بظلالٍ وهوامش، تأكل ثلث الشاشة لأربعة أرقام،
                وتختلف عن كل جدولٍ تحتها في الحدود ونصف القطر واللون. صارت
                شريطاً واحداً من نظام التصميم نفسه الذي تستعمله بقيّة
                الشاشة، فيقرأ الناظر الأرقام في سطرٍ واحد ويستمرّ.
            --}}
            @php
                $metrics = [
                    [
                        'label' => 'الإيرادات',
                        'value' => $money($totalReceipts),
                        'hint' => 'دائن ' . $money($revenueSides['credit']),
                        'icon' => 'trending-up',
                        'tone' => $totalReceipts > 0 ? 'success' : 'muted',
                    ],
                    [
                        'label' => 'المصروفات',
                        'value' => $money($totalExpenses),
                        'hint' => $totalExpenses < 0 ? 'صافيها دائن' : 'مدين ' . $money($expenseSides['debit']),
                        'icon' => 'trending-down',
                        'tone' => $totalExpenses > 0 ? 'danger' : 'muted',
                    ],
                    [
                        'label' => $netIncome >= 0 ? 'صافي الربح' : 'صافي الخسارة',
                        'value' => $money(abs($netIncome)),
                        'hint' => 'الشهر: ' . $money($monthNet),
                        'icon' => $netIncome >= 0 ? 'award' : 'alert-triangle',
                        'tone' => $netTone,
                    ],
                    [
                        'label' => 'الخزائن والبنوك',
                        'value' => $money($totalTreasuryBalance),
                        'hint' => $treasuryStats->cash . ' خزينة · ' . $treasuryStats->banks . ' بنك',
                        'icon' => 'credit-card',
                        'tone' => $treasuryTone,
                    ],
                ];

                // الضريبة والذمم أرقام منشأة — لغير المقيَّد وحده
                if ($fullFinancialView) {
                    $metrics[] = [
                        'label' => 'صافي الضريبة',
                        'value' => $vat['net'] === null ? '—' : $money($vat['net']),
                        'hint' => $vat['net'] === null
                            ? 'حسابا الضريبة غير مضبوطَين'
                            : ($vat['net'] > 0 ? 'مستحقّة عليك' : 'لك على الهيئة'),
                        'icon' => 'percent',
                        'tone' => ($vat['net'] ?? 0) > 0 ? 'warning' : 'info',
                    ];
                    $metrics[] = [
                        'label' => 'صافي الذمم',
                        'value' => $ledger['receivable'] === null ? '—'
                            : $money(($ledger['receivable'] ?? 0) - ($ledger['payable'] ?? 0)),
                        'hint' => 'مدينة ' . ($ledger['receivable'] === null ? '—' : $money($ledger['receivable'])),
                        'icon' => 'users',
                        'tone' => 'info',
                    ];
                }
            @endphp

            <div class="stk-metrics">
                @foreach ($metrics as $metric)
                    <div class="stk-metric">
                        <span class="stk-metric__icon text-{{ $metric['tone'] }}">
                            <i class="feather icon-{{ $metric['icon'] }}"></i>
                        </span>
                        <span class="stk-metric__value stk-num text-{{ $metric['tone'] }}">{{ $metric['value'] }}</span>
                        <span class="stk-metric__label">{{ $metric['label'] }}</span>
                        <span class="stk-metric__label">{{ $metric['hint'] }}</span>
                    </div>
                @endforeach
            </div>

            {{-- ══════════════ معادلة الميزانية ══════════════ --}}
            @if ($fullFinancialView)
            <h4 class="section-title mt-3">معادلة الميزانية</h4>
            <div class="row">
                <div class="col-lg-8 col-12">
                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title">
                                <i class="feather icon-sliders"></i> الأصول = الخصوم + حقوق الملكية + صافي النتيجة
                            </h4>
                            <span class="badge bg-{{ $equation['balanced'] ? 'success' : 'danger' }}">
                                {{ $equation['balanced'] ? 'متوازنة' : 'فرق ' . $money($equation['difference']) }}
                            </span>
                        </div>
                        <div class="card-body">
                            @php
                                $terms = [
                                    ['الأصول', $equation['assets'], 'info'],
                                    ['الخصوم', $equation['liabilities'], 'warning'],
                                    ['حقوق الملكية', $equation['equity'], 'primary'],
                                    ['الإيرادات', $equation['revenues'], 'success'],
                                    ['المصروفات', $equation['expenses'], 'danger'],
                                ];
                                $scale = max(array_map(fn ($t) => abs($t[1]), $terms)) ?: 1;
                            @endphp

                            @foreach ($terms as [$name, $value, $tone])
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <span style="width:8rem">{{ $name }}</span>
                                    <div class="progress flex-grow-1" style="height:10px;">
                                        <div class="progress-bar bg-{{ $tone }}" role="progressbar"
                                             style="width:{{ min(100, abs($value) / $scale * 100) }}%"
                                             aria-valuenow="{{ abs($value) }}"
                                             aria-valuemin="0" aria-valuemax="{{ $scale }}"></div>
                                    </div>
                                    <span class="stk-num text-{{ $tone }}" style="width:9rem;text-align:end">
                                        {{ $money($value) }}
                                    </span>
                                </div>
                            @endforeach

                            <hr>

                            <div class="d-flex justify-content-between">
                                <span>الطرف الأيمن (الأصول)</span>
                                <span class="stk-num fw-bold">{{ $money($equation['left']) }}</span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span>الطرف الأيسر (خصوم + ملكية + نتيجة)</span>
                                <span class="stk-num fw-bold">{{ $money($equation['right']) }}</span>
                            </div>
                            <div class="d-flex justify-content-between mt-1">
                                <span class="fw-bold">الفرق</span>
                                <span class="stk-num fw-bold text-{{ $equation['balanced'] ? 'success' : 'danger' }}">
                                    {{ $money($equation['difference']) }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4 col-12">
                    <div class="card h-100">
                        <div class="card-header">
                            <h4 class="card-title"><i class="feather icon-users"></i> الذمم</h4>
                        </div>
                        <div class="card-body">
                            <div class="d-flex justify-content-between mb-2">
                                <span>
                                    ذمم مدينة
                                    <small class="d-block text-muted">{{ $ledger['receivable_account'] ?: 'غير مضبوط' }}</small>
                                </span>
                                {{-- ذمّةٌ مدينة سالبة تعني قبضاً يفوق المستحقّ: حالةٌ تستحقّ لوناً يُنبّه --}}
                                <span class="stk-num fw-bold text-{{ ($ledger['receivable'] ?? 0) < 0 ? 'danger' : 'info' }}">
                                    {{ $ledger['receivable'] === null ? '—' : $money($ledger['receivable']) }}
                                </span>
                            </div>

                            <div class="d-flex justify-content-between">
                                <span>
                                    ذمم دائنة
                                    <small class="d-block text-muted">{{ $ledger['payable_account'] ?: 'غير مضبوط' }}</small>
                                </span>
                                <span class="stk-num fw-bold text-{{ ($ledger['payable'] ?? 0) > 0 ? 'warning' : 'secondary' }}">
                                    {{ $ledger['payable'] === null ? '—' : $money($ledger['payable']) }}
                                </span>
                            </div>

                            @if ($ledger['receivable'] !== null && $ledger['payable'] !== null)
                                <hr>
                                @php $netLedger = $ledger['receivable'] - $ledger['payable']; @endphp
                                <div class="d-flex justify-content-between">
                                    <span class="fw-bold">صافي الذمم</span>
                                    <span class="stk-num fw-bold text-{{ $netLedger >= 0 ? 'success' : 'danger' }}">
                                        {{ $money($netLedger) }}
                                    </span>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            @endif {{-- fullFinancialView: معادلة الميزانية + الذمم --}}

            {{-- ══════════════ القيود غير المتوازنة ══════════════ --}}
            @if ($fullFinancialView && $unbalanced['count'] > 0)
                <div class="card stk-table-card mt-2">
                    <div class="stk-table-head">
                        <h6 class="stk-table-head__title">
                            <i class="feather icon-alert-octagon text-danger"></i> قيود غير متوازنة
                            <span class="stk-count">{{ $unbalanced['count'] }}</span>
                        </h6>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th style="width:16%">رقم القيد</th>
                                    <th style="width:12%">التاريخ</th>
                                    <th>البيان</th>
                                    <th class="text-end" style="width:13%">مدين</th>
                                    <th class="text-end" style="width:13%">دائن</th>
                                    <th class="text-end" style="width:12%">الفرق</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($unbalanced['rows'] as $row)
                                    <tr>
                                        <td>
                                            <a href="{{ route('journal.show', $row->id) }}" class="stk-num">
                                                {{ $row->reference_number ?: '#' . $row->id }}
                                            </a>
                                        </td>
                                        <td class="stk-num">{{ $day($row->date) }}</td>
                                        <td>{{ \Illuminate\Support\Str::limit($row->description, 60) ?: '—' }}</td>
                                        <td class="text-end stk-num">{{ $money($row->debit) }}</td>
                                        <td class="text-end stk-num">{{ $money($row->credit) }}</td>
                                        <td class="text-end stk-num text-danger fw-bold">{{ $money($row->difference) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            {{-- ══════════════ الاتجاه والتوزيع ══════════════ --}}
            <div class="row mt-2">
                {{-- الاتجاه منطاقٌ بالمنشئ، فيبقى للجميع — ويتّسع حين يغيب جاره --}}
                <div class="col-lg-{{ $fullFinancialView ? 8 : 12 }} col-12">
                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title">الاتجاه المالي (آخر 12 شهراً)</h4>
                        </div>
                        <div class="card-body">
                            <div id="financial-trend-chart" class="chart-container"></div>
                        </div>
                    </div>
                </div>

                @if ($fullFinancialView)
                <div class="col-lg-4 col-12">
                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title">توزيع الحسابات على أنواعها</h4>
                        </div>
                        <div class="card-body p-0">
                            {{--
                                كان الرسم يقرأ العمود `type` — وقيمُه
                                `main`/`sub`، أي **مستوى** الحساب في الشجرة لا
                                نوعَه المحاسبي. فيعرض «رئيسي 1512، فرعي 249»
                                تحت عنوان «توزيع أنواع الحسابات»، وهو جوابٌ
                                عن سؤالٍ لم يُطرح. النوع المحاسبي في
                                `account_type`.
                            --}}
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <tbody>
                                        @php $totalTyped = $accountTypes->sum('count') ?: 1; @endphp

                                        @foreach ($accountTypes as $type)
                                            <tr>
                                                <td style="width:34%">
                                                    <span class="badge bg-{{ $typeTones[$type->key] ?? 'secondary' }}">
                                                        {{ $type->name }}
                                                    </span>
                                                </td>
                                                <td class="stk-num" style="width:18%">{{ number_format($type->count) }}</td>
                                                <td>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <div class="progress flex-grow-1" style="height:8px;">
                                                            <div class="progress-bar bg-{{ $typeTones[$type->key] ?? 'secondary' }}"
                                                                 role="progressbar"
                                                                 style="width:{{ round($type->count / $totalTyped * 100, 1) }}%"
                                                                 aria-valuenow="{{ $type->count }}"
                                                                 aria-valuemin="0" aria-valuemax="{{ $totalTyped }}"></div>
                                                        </div>
                                                        <small class="text-muted">{{ round($type->count / $totalTyped * 100, 1) }}%</small>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                @endif {{-- fullFinancialView: توزيع الحسابات --}}
            </div>

            {{-- ══════════════ الخزائن ══════════════ --}}
            <h4 class="section-title mt-3">الخزائن والبنوك</h4>
            <div class="row">
                <div class="col-lg-4 col-12">
                    <div class="card h-100">
                        <div class="card-header">
                            <h4 class="card-title"><i class="feather icon-briefcase"></i> الملخّص</h4>
                        </div>
                        <div class="card-body">
                            <div class="d-flex justify-content-between mb-2">
                                <span>نقدية الخزائن</span>
                                <span class="stk-num fw-bold text-success">{{ $money($treasuryStats->cash_balance) }}</span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>أرصدة البنوك</span>
                                <span class="stk-num fw-bold text-info">{{ $money($treasuryStats->bank_balance) }}</span>
                            </div>

                            <hr>

                            <div class="d-flex justify-content-between">
                                <span class="fw-bold">الإجمالي</span>
                                <span class="stk-num fw-bold text-{{ $treasuryTone }}">{{ $money($totalTreasuryBalance) }}</span>
                            </div>

                            <div class="progress mt-2" style="height:10px;">
                                @php
                                    $tt = max(abs($treasuryStats->cash_balance) + abs($treasuryStats->bank_balance), 0.01);
                                @endphp
                                <div class="progress-bar bg-success"
                                     style="width:{{ abs($treasuryStats->cash_balance) / $tt * 100 }}%"></div>
                                <div class="progress-bar bg-info"
                                     style="width:{{ abs($treasuryStats->bank_balance) / $tt * 100 }}%"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-8 col-12">
                    <div class="card stk-table-card h-100">
                        <div class="stk-table-head">
                            <h6 class="stk-table-head__title">
                                <i class="feather icon-list"></i> الأرصدة تفصيلاً
                                <span class="stk-count">{{ $treasuryAccounts->count() }}</span>
                            </h6>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th style="width:14%">الكود</th>
                                        <th>الحساب</th>
                                        <th class="text-center" style="width:14%">النوع</th>
                                        <th class="text-end" style="width:22%">الرصيد</th>
                                        <th class="text-center" style="width:12%">الحالة</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($treasuryAccounts->sortByDesc('balance') as $account)
                                        <tr>
                                            <td class="stk-num">{{ $account->code }}</td>
                                            <td>{{ $account->name }}</td>
                                            <td class="text-center">
                                                <span class="badge bg-{{ $account->is_cash ? 'success' : 'info' }}">
                                                    {{ $account->is_cash ? 'خزينة' : 'بنك' }}
                                                </span>
                                            </td>
                                            <td class="text-end stk-num fw-bold">{{ $money($account->balance) }}</td>
                                            <td class="text-center">
                                                <span class="badge bg-{{ $account->is_active ? 'success' : 'secondary' }}">
                                                    {{ $account->is_active ? 'نشط' : 'موقوف' }}
                                                </span>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="text-center text-muted py-4">
                                                لا خزائن ولا حسابات بنكية معرَّفة تحت الحسابات المضبوطة في الإعدادات.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ══════════════ المستندات ══════════════ --}}
            <h4 class="section-title mt-3">أحدث المستندات</h4>
            <div class="row">
                <div class="col-lg-6 col-12">
                    <div class="card stk-table-card h-100">
                        <div class="stk-table-head">
                            <h6 class="stk-table-head__title">
                                <i class="feather icon-log-in text-success"></i> سندات القبض
                            </h6>
                            <a href="{{ route('incomes.index') }}" class="btn btn-sm btn-outline-secondary">الكل</a>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th style="width:26%">الرقم</th>
                                        <th style="width:20%">التاريخ</th>
                                        <th class="text-end">المبلغ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {{--
                                        كانت هذه القائمة تُبنى من `JournalEntry` وتجمع
                                        `credit` على حسابات الإيرادات — فتخرج بمبلغ
                                        **صفر** كلّما رُحِّل السند على الجانب الآخر، وهو
                                        ما كان يقع فعلاً. صارت من جدول السندات نفسه.
                                    --}}
                                    @forelse ($recentReceipts as $voucher)
                                        <tr>
                                            <td class="stk-num">{{ $voucher->code }}</td>
                                            <td class="stk-num">{{ $day($voucher->date) }}</td>
                                            <td class="text-end stk-num fw-bold text-success">{{ $money($voucher->amount) }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="3" class="text-center text-muted py-4">لا سندات قبض بعد.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6 col-12">
                    <div class="card stk-table-card h-100">
                        <div class="stk-table-head">
                            <h6 class="stk-table-head__title">
                                <i class="feather icon-log-out text-danger"></i> سندات الصرف
                            </h6>
                            <a href="{{ route('expenses.index') }}" class="btn btn-sm btn-outline-secondary">الكل</a>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th style="width:26%">الرقم</th>
                                        <th style="width:20%">التاريخ</th>
                                        <th class="text-end">المبلغ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($recentExpenses as $voucher)
                                        <tr>
                                            <td class="stk-num">{{ $voucher->code }}</td>
                                            <td class="stk-num">{{ $day($voucher->date) }}</td>
                                            <td class="text-end stk-num fw-bold text-danger">{{ $money($voucher->amount) }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="3" class="text-center text-muted py-4">لا سندات صرف بعد.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ══════════════ أحدث القيود ══════════════ --}}
            <div class="card stk-table-card mt-2">
                <div class="stk-table-head">
                    <h6 class="stk-table-head__title">
                        <i class="feather icon-book-open"></i> أحدث القيود
                        <span class="stk-count">{{ number_format($totalEntries) }}</span>
                    </h6>
                    <a href="{{ route('journal.index') }}" class="btn btn-sm btn-outline-secondary">دفتر اليومية</a>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th style="width:16%">الرقم</th>
                                <th style="width:12%">التاريخ</th>
                                <th>البيان</th>
                                <th class="text-end" style="width:16%">المبلغ</th>
                                <th class="text-center" style="width:12%">الحالة</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($recentEntries as $entry)
                                @php [$stateLabel, $stateTone] = $entryStates[(int) $entry->status] ?? [$entry->status, 'secondary']; @endphp
                                <tr>
                                    <td>
                                        <a href="{{ route('journal.show', $entry->id) }}" class="stk-num">
                                            {{ $entry->reference_number ?: '#' . $entry->id }}
                                        </a>
                                    </td>
                                    <td class="stk-num">{{ $day($entry->date) }}</td>
                                    <td>{{ \Illuminate\Support\Str::limit($entry->description, 60) ?: '—' }}</td>
                                    <td class="text-end stk-num">{{ $money($entry->total_debit) }}</td>
                                    <td class="text-center">
                                        <span class="badge bg-{{ $stateTone }}">{{ $stateLabel }}</span>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-muted py-4">لا قيود بعد.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- ══════════════ أكبر الحسابات حركةً ══════════════ --}}
            <h4 class="section-title mt-3">أكبر الحسابات حركةً</h4>
            <div class="row">
                @foreach ([
                    ['الإيرادات', $topReceipts, 'success', 'trending-up', 'لا حركة على حسابات الإيرادات بعد.'],
                    ['المصروفات', $topExpenses, 'danger', 'trending-down', 'لا حركة على حسابات المصروفات بعد.'],
                ] as [$title, $rows, $tone, $icon, $emptyText])
                    <div class="col-lg-6 col-12">
                        <div class="card stk-table-card h-100">
                            <div class="stk-table-head">
                                <h6 class="stk-table-head__title">
                                    <i class="feather icon-{{ $icon }} text-{{ $tone }}"></i> {{ $title }}
                                </h6>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <tbody>
                                        @php $scale = $rows->max(fn ($r) => abs($r->total)) ?: 1; @endphp

                                        @forelse ($rows as $row)
                                            <tr>
                                                <td style="width:12%" class="stk-num">{{ $row->code }}</td>
                                                <td>{{ $row->name }}</td>
                                                <td style="width:34%">
                                                    <div class="progress" style="height:8px;">
                                                        <div class="progress-bar bg-{{ $row->total < 0 ? 'secondary' : $tone }}"
                                                             role="progressbar"
                                                             style="width:{{ abs($row->total) / $scale * 100 }}%"
                                                             aria-valuenow="{{ abs($row->total) }}"
                                                             aria-valuemin="0" aria-valuemax="{{ $scale }}"></div>
                                                    </div>
                                                </td>
                                                <td class="text-end stk-num fw-bold text-{{ $row->total < 0 ? 'secondary' : $tone }}"
                                                    style="width:22%">
                                                    {{ $money($row->total) }}
                                                </td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="4" class="text-center text-muted py-4">{{ $emptyText }}</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- ══════════════ الضريبة وحركة النقد ══════════════ --}}
            <h4 class="section-title mt-3">{{ $fullFinancialView ? 'الضريبة وحركة النقد' : 'حركة النقد' }}</h4>
            <div class="row">
                @if ($fullFinancialView)
                <div class="col-lg-4 col-md-6 col-12">
                    <div class="card h-100">
                        <div class="card-header">
                            <h4 class="card-title"><i class="feather icon-percent text-warning"></i> الوضع الضريبي</h4>
                        </div>
                        <div class="card-body">
                            {{--
                                «كم عليّ للهيئة؟» سؤالٌ تُسأله كل منشأةٍ آخر كل
                                فترة، ولم يكن في اللوحة جواب. يُشتقّ من حسابَي
                                الضريبة كما ضُبطا في «الحسابات المحاسبية».
                            --}}
                            <div class="d-flex justify-content-between mb-2">
                                <span>
                                    ضريبة محصَّلة
                                    <small class="d-block text-muted">{{ $vat['collected_account'] ?: 'غير مضبوط' }}</small>
                                </span>
                                <span class="stk-num fw-bold text-warning">
                                    {{ $vat['collected'] === null ? '—' : $money($vat['collected']) }}
                                </span>
                            </div>

                            <div class="d-flex justify-content-between">
                                <span>
                                    ضريبة مدفوعة
                                    <small class="d-block text-muted">{{ $vat['paid_account'] ?: 'غير مضبوط' }}</small>
                                </span>
                                <span class="stk-num fw-bold text-info">
                                    {{ $vat['paid'] === null ? '—' : $money($vat['paid']) }}
                                </span>
                            </div>

                            <hr>

                            <div class="d-flex justify-content-between">
                                <span class="fw-bold">
                                    {{ ($vat['net'] ?? 0) > 0 ? 'مستحقّ للهيئة' : 'مستحقّ لك' }}
                                </span>
                                <span class="stk-num fw-bold text-{{ ($vat['net'] ?? 0) > 0 ? 'warning' : 'success' }}">
                                    {{ $vat['net'] === null ? '—' : $money(abs($vat['net'])) }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                @endif {{-- fullFinancialView: الوضع الضريبي --}}

                <div class="col-lg-{{ $fullFinancialView ? 4 : 6 }} col-md-6 col-12">
                    <div class="card h-100">
                        <div class="card-header">
                            <h4 class="card-title"><i class="feather icon-repeat text-info"></i> حركة الخزائن</h4>
                        </div>
                        <div class="card-body">
                            {{--
                                الرصيد وحده لا يقول أدخل مليونٌ وخرج مليون أم
                                لم تتحرّك الخزينة أصلاً. الداخل والخارج يفصلان.
                            --}}
                            @foreach ([['الإجمالي', $flowAll], ['هذا الشهر', $flowMonth]] as [$scope, $flow])
                                <div class="mb-2">
                                    <div class="d-flex justify-content-between">
                                        <small class="text-muted">{{ $scope }}</small>
                                        <small class="stk-num text-{{ $flow['net'] >= 0 ? 'success' : 'danger' }}">
                                            صافي {{ $money($flow['net']) }}
                                        </small>
                                    </div>

                                    @php $span = max(abs($flow['in']), abs($flow['out']), 0.01); @endphp

                                    <div class="d-flex align-items-center gap-2 mt-1">
                                        <i class="feather icon-arrow-down text-success"></i>
                                        <div class="progress flex-grow-1" style="height:8px;">
                                            <div class="progress-bar bg-success"
                                                 style="width:{{ abs($flow['in']) / $span * 100 }}%"></div>
                                        </div>
                                        <span class="stk-num text-success" style="width:7.5rem;text-align:end">
                                            {{ $money($flow['in']) }}
                                        </span>
                                    </div>

                                    <div class="d-flex align-items-center gap-2 mt-1">
                                        <i class="feather icon-arrow-up text-danger"></i>
                                        <div class="progress flex-grow-1" style="height:8px;">
                                            <div class="progress-bar bg-danger"
                                                 style="width:{{ abs($flow['out']) / $span * 100 }}%"></div>
                                        </div>
                                        <span class="stk-num text-danger" style="width:7.5rem;text-align:end">
                                            {{ $money($flow['out']) }}
                                        </span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                @if ($fullFinancialView)
                <div class="col-lg-4 col-md-6 col-12">
                    <div class="card h-100">
                        <div class="card-header">
                            <h4 class="card-title"><i class="feather icon-package text-primary"></i> الأصول ومراكز التكلفة</h4>
                        </div>
                        <div class="card-body">
                            <div class="d-flex justify-content-between mb-2">
                                <span>أصول ثابتة</span>
                                <span class="stk-num fw-bold">{{ number_format($assets['count']) }}</span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">قيمة الشراء</span>
                                <span class="stk-num">{{ $money($assets['value']) }}</span>
                            </div>
                            @if ($assets['sold'] > 0)
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">منها مباع</span>
                                    <span class="stk-num">{{ number_format($assets['sold']) }}</span>
                                </div>
                            @endif

                            <hr>

                            @forelse ($costCenters as $center)
                                <div class="d-flex justify-content-between mb-1">
                                    <span>{{ $center->name }}</span>
                                    <span class="stk-num">{{ $money($center->total) }}</span>
                                </div>
                            @empty
                                <p class="text-muted mb-0">
                                    لا حركة محمَّلة على مراكز التكلفة —
                                    {{ number_format($totalCostCenters) }} مركزاً معرَّفاً.
                                </p>
                            @endforelse
                        </div>
                    </div>
                </div>
                @endif {{-- fullFinancialView: الأصول ومراكز التكلفة --}}
            </div>

            {{-- ══════════════ أكبر الحسابات رصيداً ══════════════ --}}
            @if ($fullFinancialView)
            <div class="card stk-table-card mt-2">
                <div class="stk-table-head">
                    <h6 class="stk-table-head__title">
                        <i class="feather icon-bar-chart"></i> أكبر الحسابات رصيداً
                    </h6>
                    <a href="{{ route('accounts_chart.view_root') }}" class="btn btn-sm btn-outline-secondary">شجرة الحسابات</a>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th style="width:12%">الكود</th>
                                <th>الحساب</th>
                                <th class="text-center" style="width:14%">النوع</th>
                                <th style="width:26%">الحجم النسبي</th>
                                <th class="text-end" style="width:18%">الرصيد</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php $topScale = $largestAccounts->max(fn ($a) => abs($a->balance)) ?: 1; @endphp

                            @forelse ($largestAccounts as $account)
                                @php $tone = $typeTones[$account->account_type] ?? 'secondary'; @endphp
                                <tr>
                                    <td class="stk-num">{{ $account->code }}</td>
                                    <td>{{ $account->name }}</td>
                                    <td class="text-center">
                                        <span class="badge bg-{{ $tone }}">{{ $typeNames[$account->account_type] ?? '—' }}</span>
                                    </td>
                                    <td>
                                        <div class="progress" style="height:8px;">
                                            <div class="progress-bar bg-{{ $tone }}"
                                                 style="width:{{ abs($account->balance) / $topScale * 100 }}%"></div>
                                        </div>
                                    </td>
                                    <td class="text-end stk-num fw-bold text-{{ $account->balance < 0 ? 'danger' : $tone }}">
                                        {{ $money($account->balance) }}
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-muted py-4">لا حساب برصيدٍ غير صفري.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @endif {{-- fullFinancialView: أكبر الحسابات رصيداً --}}

            {{-- ══════════════ الشيكات ودورة المستندات ══════════════ --}}
            <h4 class="section-title mt-3">{{ $fullFinancialView ? 'الشيكات والمراكز' : 'الشيكات' }}</h4>
            <div class="row">
                @php
                    $tiles = [
                        [
                            'title' => 'شيكات واردة',
                            'value' => $receivedChequeStats->total,
                            'money' => $receivedChequeStats->amount,
                            'overdue' => $receivedChequeStats->overdue,
                            'due' => $receivedChequeStats->due_soon,
                            'icon' => 'download',
                            'tone' => 'success',
                            'url' => \Illuminate\Support\Facades\Route::has('received_cheques.index')
                                ? route('received_cheques.index') : null,
                        ],
                        [
                            'title' => 'شيكات صادرة',
                            'value' => $payableChequeStats->total,
                            'money' => $payableChequeStats->amount,
                            'overdue' => $payableChequeStats->overdue,
                            'due' => $payableChequeStats->due_soon,
                            'icon' => 'upload',
                            'tone' => 'danger',
                            'url' => \Illuminate\Support\Facades\Route::has('payable_cheques.index')
                                ? route('payable_cheques.index') : null,
                        ],
                    ];
                @endphp

                @foreach ($tiles as $tile)
                    <div class="col-lg-4 col-md-6 col-12">
                        <div class="card h-100">
                            <div class="card-header">
                                <h4 class="card-title">
                                    <i class="feather icon-{{ $tile['icon'] }} text-{{ $tile['tone'] }}"></i> {{ $tile['title'] }}
                                </h4>
                                @if ($tile['url'])
                                    <a href="{{ $tile['url'] }}" class="btn btn-sm btn-outline-secondary">الكل</a>
                                @endif
                            </div>
                            <div class="card-body">
                                <h2 class="text-bold-700 mb-0">{{ number_format($tile['value']) }}</h2>
                                <p class="text-muted mb-2">بقيمة {{ $money($tile['money']) }}</p>

                                {{--
                                    كانت هذه الأرقام تأتي من `SUM(CASE …)` — وهي
                                    تُرجع `null` لا صفراً على جدولٍ فارغ، فتظهر
                                    الخانة **فارغةً** بدل «0».
                                --}}
                                <div class="stk-tags">
                                    <span class="badge bg-{{ $tile['overdue'] > 0 ? 'danger' : 'secondary' }}">
                                        متأخّر: {{ number_format($tile['overdue']) }}
                                    </span>
                                    <span class="badge bg-{{ $tile['due'] > 0 ? 'warning' : 'secondary' }}">
                                        يستحقّ خلال 30 يوماً: {{ number_format($tile['due']) }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach

                @if ($fullFinancialView)
                <div class="col-lg-4 col-md-6 col-12">
                    <div class="card h-100">
                        <div class="card-header">
                            <h4 class="card-title"><i class="feather icon-grid text-primary"></i> دفاتر ومراكز</h4>
                        </div>
                        <div class="card-body">
                            <div class="d-flex justify-content-between mb-2">
                                <span>دفاتر الشيكات</span>
                                <span class="stk-num fw-bold">{{ number_format($chequeBookStats->total) }}</span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">منها نشط</span>
                                <span class="stk-num">{{ number_format($chequeBookStats->active) }}</span>
                            </div>

                            <hr>

                            <div class="d-flex justify-content-between mb-2">
                                <span>مراكز التكلفة</span>
                                <span class="stk-num fw-bold">{{ number_format($totalCostCenters) }}</span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span class="text-muted">منها نشط</span>
                                <span class="stk-num">{{ number_format($activeCostCenters) }}</span>
                            </div>
                        </div>
                    </div>
                </div>
                @endif {{-- fullFinancialView: دفاتر ومراكز --}}
            </div>
        </section>
    </div>
@endsection

@section('scripts')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/apexcharts/3.45.0/apexcharts.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var el = document.querySelector('#financial-trend-chart');

            if (! el || typeof ApexCharts === 'undefined') {
                return;
            }

            /*
                ألوان الرسم هي ألوان البطاقات نفسها — أخضر للإيراد وأحمر
                للمصروف وأزرق للصافي. كان الرسم يستعمل لوحةً أخرى، فيقرأ
                الناظر لوناً في البطاقة وغيرَه في المنحنى لنفس الرقم.
            */
            var options = {
                chart: { type: 'area', height: 340, toolbar: { show: false }, fontFamily: 'inherit' },
                series: [
                    { name: 'الإيرادات', data: @json($monthlyReceipts) },
                    { name: 'المصروفات', data: @json($monthlyExpenses) },
                    { name: 'الصافي', data: @json($monthlyNet) }
                ],
                xaxis: { categories: @json($months) },
                colors: ['#28c76f', '#ea5455', '#00cfe8'],
                stroke: { curve: 'smooth', width: 2 },
                fill: { type: 'gradient', gradient: { opacityFrom: 0.3, opacityTo: 0.02 } },
                dataLabels: { enabled: false },
                legend: { position: 'top', horizontalAlign: 'left' },
                grid: { borderColor: 'rgba(127,127,127,.15)' },
                tooltip: {
                    y: {
                        formatter: function (v) {
                            return new Intl.NumberFormat('ar-EG', { minimumFractionDigits: 2 }).format(v);
                        }
                    }
                },
                noData: { text: 'لا حركة مالية في هذه المدّة' }
            };

            new ApexCharts(el, options).render();
        });
    </script>
@endsection
