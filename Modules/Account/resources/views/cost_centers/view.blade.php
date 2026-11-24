@extends('account::master')

@section('title', $center ? 'مركز التكلفة: ' . $center->name : 'مراكز التكلفة')

@section('content')
    @php
        /*
            شاشة مركز التكلفة — على نمط شجرة الحسابات.

            لمراكز التكلفة `parent_id` و`code` و`is_main` منذ إنشائها: أي
            أنها شجرةٌ في القاعدة بلا واجهةِ شجرة. وكانت الشاشة الوحيدة
            تعرضها كلّها دفعةً واحدة، فلا يُعرف لمركزٍ بعينه ما حُمّل عليه
            ولا من أبناؤه.
        */
        $money = fn ($v) => number_format((float) $v, 2);

        $netTone = $movement['net'] > 0 ? 'danger' : ($movement['net'] < 0 ? 'success' : 'info');

        $filtered = $branchId || request()->filled('period_id');
    @endphp

    @include('stock::components.page-head', [
        'title' => $center ? $center->name : 'مراكز التكلفة',
        'icon' => 'target',
        'sub' => $center
            ? 'كود ' . $center->code . ' — ' . $children->total() . ' مركزاً فرعياً و' . $movement['lines'] . ' حركة'
            : $children->total() . ' مركزاً رئيسياً',
        'crumbs' => [
            'الرئيسية' => url('/'),
            'مراكز التكلفة' => route('cost_centers.view_root'),
            ($center->name ?? 'الجذر') => null,
        ],
    ])

    <div class="content-body">
        <section id="cost-center-view">

            {{-- ══════════ مسار الفئات ══════════ --}}
            <div class="card mb-2">
                <div class="card-body py-2">
                    <nav aria-label="مسار مركز التكلفة">
                        <ol class="breadcrumb mb-0 p-0" style="background:transparent;">
                            <li class="breadcrumb-item">
                                <a href="{{ route('cost_centers.view_root') }}">
                                    <i class="feather icon-home"></i> الجذر
                                </a>
                            </li>

                            @foreach ($breadcrumb as $i => $crumb)
                                <li class="breadcrumb-item {{ $i === count($breadcrumb) - 1 ? 'active' : '' }}">
                                    @if ($i === count($breadcrumb) - 1)
                                        <span class="stk-num">{{ $crumb['code'] }}</span> {{ $crumb['name'] }}
                                    @else
                                        <a href="{{ route('cost_centers.view', $crumb['id']) }}">
                                            <span class="stk-num">{{ $crumb['code'] }}</span> {{ $crumb['name'] }}
                                        </a>
                                    @endif
                                </li>
                            @endforeach
                        </ol>
                    </nav>
                </div>
            </div>

            {{-- ══════════ الأرقام ══════════ --}}
            @php
                $metrics = [
                    [
                        'label' => 'مراكز فرعية',
                        'value' => number_format($children->total()),
                        'hint' => $center ? 'تحت هذا المركز' : 'مراكز رئيسية',
                        'icon' => 'git-branch',
                        'tone' => 'info',
                    ],
                    [
                        'label' => 'حركات محمَّلة',
                        'value' => number_format($movement['lines']),
                        'hint' => 'بنود قيود' . ($directMovement ? ' · ' . number_format($directMovement['lines']) . ' مباشرة' : ''),
                        'icon' => 'list',
                        'tone' => $movement['lines'] > 0 ? 'primary' : 'muted',
                    ],
                    [
                        'label' => 'إجمالي المدين',
                        'value' => $money($movement['debit']),
                        'hint' => 'ما حُمّل على المركز',
                        'icon' => 'arrow-down',
                        'tone' => $movement['debit'] > 0 ? 'danger' : 'muted',
                    ],
                    [
                        'label' => 'إجمالي الدائن',
                        'value' => $money($movement['credit']),
                        'hint' => 'ما رُدّ أو عُكس',
                        'icon' => 'arrow-up',
                        'tone' => $movement['credit'] > 0 ? 'success' : 'muted',
                    ],
                    [
                        'label' => 'صافي التحميل',
                        'value' => $money(abs($movement['net'])),
                        'hint' => $movement['net'] >= 0 ? 'تكلفة صافية' : 'رصيد دائن',
                        'icon' => 'activity',
                        'tone' => $netTone,
                    ],
                ];
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

            {{--
                ══════════ المباشر مقابل الذرّيّة ══════════

                شجرةُ الحسابات تقول «القيود المباشرة — لا تشمل قيود الحسابات
                الفرعية» صراحةً. وبغير هذا الفصل لا يُعرف أيُّ الرقمَين رقمُ
                المركز نفسِه: المركزُ الأبُ لا تُحمَّل عليه الحركةُ مباشرةً
                غالباً، بل على أبنائه — فيُقرأ رقمُ الذرّيّة على أنّه رقمُه.
            --}}
            @if ($center && $directMovement)
                <div class="card mb-2">
                    <div class="card-body py-2">
                        <div class="d-flex flex-wrap align-items-center" style="gap:1.5rem">
                            <span class="text-muted">
                                <i class="feather icon-crosshair"></i> محمَّلٌ على هذا المركز مباشرةً:
                            </span>
                            <span>
                                <strong class="stk-num text-danger">{{ $money($directMovement['debit']) }}</strong>
                                <small class="text-muted">مدين</small>
                            </span>
                            <span>
                                <strong class="stk-num text-success">{{ $money($directMovement['credit']) }}</strong>
                                <small class="text-muted">دائن</small>
                            </span>
                            <span>
                                <strong class="stk-num">{{ number_format($directMovement['lines']) }}</strong>
                                <small class="text-muted">حركة</small>
                            </span>

                            @if ($movement['lines'] > $directMovement['lines'])
                                <span class="text-muted">
                                    · والباقي
                                    <strong class="stk-num">{{ number_format($movement['lines'] - $directMovement['lines']) }}</strong>
                                    على المراكز الفرعية.
                                </span>
                            @endif
                        </div>
                    </div>
                </div>
            @endif

            {{--
                ══════════ فلاتر الحركة ══════════

                كانت الشاشة تعرض حركةَ المركز منذ نشأته بلا مدىً ولا فرع:
                مركزٌ عمرُه سنتان يُقرأ رقماً واحداً لا يُنسب إلى فترة.
            --}}
            <div class="card mb-2">
                <div class="card-body">
                    <form method="GET" class="row g-2 align-items-end">
                        {{-- الفترة المحاسبية: اختيارها يملأ حقلَي المدى --}}
                        @include('partials.accounting-period-filter', [
                            'colClass' => 'col-md-3',
                            'period' => $period ?? [],
                        ])

                        <div class="col-md-2">
                            <label class="form-label">الفرع</label>
                            <select name="branch_id" class="form-control">
                                <option value="">كل الفروع</option>
                                @foreach ($branches as $branch)
                                    <option value="{{ $branch->id }}" @selected($branchId == $branch->id)>
                                        {{ $branch->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-1">
                            <label class="form-label">لكل صفحة</label>
                            <select name="per_page" class="form-control">
                                @foreach ([25, 50, 100, 200] as $option)
                                    <option value="{{ $option }}" @selected($perPage == $option)>{{ $option }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-2 d-flex" style="gap:.4rem">
                            <button type="submit" class="btn btn-primary flex-grow-1">
                                <i class="feather icon-filter"></i> تطبيق
                            </button>

                            @if ($filtered)
                                <a href="{{ $center ? route('cost_centers.view', $center->id) : route('cost_centers.view_root') }}"
                                    class="btn btn-outline-secondary" title="مسح الفلاتر">
                                    <i class="feather icon-x"></i>
                                </a>
                            @endif
                        </div>
                    </form>

                    @if ($filtered)
                        <div class="mt-2 text-muted">
                            <i class="feather icon-info"></i>
                            الأرقام أعلاه محسوبةٌ على الفترة المطلوبة وحدَها، لا على عمر المركز.
                        </div>
                    @endif
                </div>
            </div>

            {{-- ══════════ المراكز الفرعية ══════════ --}}
            <div class="card stk-table-card mt-2">
                <div class="stk-table-head">
                    <h6 class="stk-table-head__title">
                        <i class="feather icon-git-branch"></i>
                        {{ $center ? 'المراكز الفرعية' : 'المراكز الرئيسية' }}
                        <span class="stk-count">{{ $children->total() }}</span>
                    </h6>
                    <a href="{{ route('cost_centers.index') }}" class="btn btn-sm btn-outline-secondary">
                        <i class="feather icon-list"></i> الشجرة كاملة
                    </a>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th style="width:12%">الكود</th>
                                <th>المركز</th>
                                <th class="text-center" style="width:9%">أبناؤه</th>
                                <th class="text-center" style="width:9%">النوع</th>
                                {{--
                                    المحمولُ على كلّ فرعٍ إلى جانبه، كما تعرض شجرةُ
                                    الحسابات رصيدَ كلّ حسابٍ فرعيّ: يُقرأ التوزيعُ من
                                    نظرةٍ واحدة بدل فتحِ كلِّ فرعٍ على حدة.
                                --}}
                                <th class="text-end" style="width:13%">مدين</th>
                                <th class="text-end" style="width:13%">دائن</th>
                                <th class="text-end" style="width:14%">الصافي</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($children as $child)
                                @php
                                    $totals = $childTotals[$child->id] ?? ['debit' => 0, 'credit' => 0, 'lines' => 0];
                                    $net = $totals['debit'] - $totals['credit'];
                                @endphp
                                <tr>
                                    <td class="stk-num">{{ $child->code }}</td>
                                    <td>
                                        <a href="{{ route('cost_centers.view', $child->id) }}">
                                            {{ $child->name }}
                                        </a>
                                        @if ($totals['lines'])
                                            <small class="d-block text-muted">
                                                {{ number_format($totals['lines']) }} حركة — شاملةً فروعه
                                            </small>
                                        @endif
                                    </td>
                                    <td class="text-center stk-num">
                                        {{ $child->children_count > 0 ? number_format($child->children_count) : '—' }}
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-{{ $child->parent_id ? 'secondary' : 'primary' }}">
                                            {{ $child->parent_id ? 'فرعي' : 'رئيسي' }}
                                        </span>
                                    </td>
                                    <td class="text-end stk-num">
                                        {{ $totals['debit'] ? $money($totals['debit']) : '—' }}
                                    </td>
                                    <td class="text-end stk-num">
                                        {{ $totals['credit'] ? $money($totals['credit']) : '—' }}
                                    </td>
                                    <td class="text-end stk-num fw-bold {{ $net > 0 ? 'text-danger' : ($net < 0 ? 'text-success' : 'text-muted') }}">
                                        {{ $totals['lines'] ? $money($net) : '—' }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">
                                        {{ $center ? 'لا مراكز فرعية تحت هذا المركز.' : 'لا مراكز تكلفة معرَّفة بعد.' }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($children->hasPages())
                    <div class="stk-table-foot">
                        {{ $children->appends(request()->except('children_page'))->links() }}
                    </div>
                @endif
            </div>

            {{-- ══════════ الحركة المحمَّلة ══════════ --}}
            <div class="card stk-table-card mt-2">
                <div class="stk-table-head">
                    <h6 class="stk-table-head__title">
                        <i class="feather icon-activity"></i> الحركة المحمَّلة
                        <span class="stk-count">{{ $entries->total() }}</span>
                    </h6>
                    <span class="stk-help">المركز وذرّيّته — والعمودُ الأخير رصيدٌ جارٍ على ترتيب الصفحة</span>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th style="width:13%">القيد</th>
                                <th style="width:10%">التاريخ</th>
                                <th style="width:19%">الحساب المرتبط</th>
                                <th>البيان</th>
                                <th class="text-end" style="width:11%">مدين</th>
                                <th class="text-end" style="width:11%">دائن</th>
                                <th class="text-end" style="width:12%">الرصيد الجاري</th>
                                <th class="text-center" style="width:8%">الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                                /*
                                    الرصيدُ الجاري ينزل من صافي المدّة كلِّها، لا من صفر:
                                    الترتيبُ من الأحدث إلى الأقدم، فالسطرُ الأوّل يحمل
                                    الصافيَ كلَّه وما دونه يُطرَح منه. وبدايةُ الصفحة
                                    محسوبةٌ في الخادم (`runningStart`) بعد طرح ما سبقها،
                                    وإلّا كرّرت كلُّ صفحةٍ أرقامَ الأولى.
                                */
                                $running = $runningStart;
                            @endphp

                            @forelse ($entries as $row)
                                @php
                                    $rowNet = (float) $row->debit - (float) $row->credit;
                                @endphp
                                <tr>
                                    <td>
                                        <a href="{{ route('journal.show', $row->entry_id) }}" class="stk-num">
                                            {{ $row->reference_number ?: '#' . $row->entry_id }}
                                        </a>
                                        @if ($row->reference_type)
                                            <small class="d-block text-muted">
                                                {{ \App\Models\JournalEntry::getReferenceTypeName($row->reference_type) }}
                                            </small>
                                        @endif
                                    </td>
                                    <td class="stk-num">
                                        {{ $row->date ? \Carbon\Carbon::parse($row->date)->format('Y-m-d') : '—' }}
                                        @if ($row->branch_name)
                                            <small class="d-block text-muted">{{ $row->branch_name }}</small>
                                        @endif
                                    </td>
                                    <td>
                                        {{--
                                            الحسابُ المرتبط برصيده: السؤالُ عن مركز التكلفة
                                            دائماً «على أيّ حساب حُمّل؟» — والاسمُ وحده لا
                                            يقول أين موقعُه من الدفتر.
                                        --}}
                                        @if ($row->account_id)
                                            <a href="{{ route('accounts_chart.show_account', $row->account_id) }}">
                                                <span class="stk-num">{{ $row->account_code }}</span>
                                                {{ $row->account_name }}
                                            </a>
                                            <small class="d-block text-muted">
                                                رصيده {{ $money($row->account_balance) }}
                                                ({{ $row->account_balance_type === 'credit' ? 'دائن' : 'مدين' }})
                                            </small>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        {{ \Illuminate\Support\Str::limit($row->description, 50) ?: '—' }}

                                        {{-- على أيّ مركزٍ وقعت الحركة: الجدولُ يجمع المركزَ وذرّيّته --}}
                                        @if ($center && $row->cost_center_id != $center->id && $row->center_name)
                                            <small class="d-block text-muted">
                                                <i class="feather icon-corner-down-left"></i>
                                                <span class="stk-num">{{ $row->center_code }}</span> {{ $row->center_name }}
                                            </small>
                                        @endif
                                    </td>
                                    <td class="text-end stk-num">{{ $row->debit > 0 ? $money($row->debit) : '—' }}</td>
                                    <td class="text-end stk-num">{{ $row->credit > 0 ? $money($row->credit) : '—' }}</td>
                                    <td class="text-end stk-num fw-bold">
                                        {{ $money($running) }}
                                        @php $running -= $rowNet; @endphp
                                    </td>
                                    <td class="text-center">
                                        <div class="stk-actions">
                                            <a href="{{ route('journal.show', $row->entry_id) }}"
                                                class="btn btn-outline-secondary" title="عرض القيد" aria-label="عرض القيد">
                                                <i class="feather icon-eye"></i>
                                            </a>
                                            @permission('account.journal.edit')
                                                <a href="{{ route('journal.edit', $row->entry_id) }}"
                                                    class="btn btn-outline-primary" title="تعديل القيد" aria-label="تعديل القيد">
                                                    <i class="feather icon-edit-2"></i>
                                                </a>
                                            @endpermission
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">
                                        @if ($filtered)
                                            لا حركة على هذا المركز ضمن المدى المطلوب — جرّب توسيعه.
                                        @else
                                            لا حركة محمَّلة على هذا المركز — تُحمَّل الحركة باختيار المركز في بنود القيد.
                                        @endif
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>

                        @if ($entries->total() > 0)
                            <tfoot>
                                <tr>
                                    <td colspan="4" class="text-end fw-bold">الإجمالي (المركز وذرّيّته)</td>
                                    <td class="text-end stk-num fw-bold">{{ $money($movement['debit']) }}</td>
                                    <td class="text-end stk-num fw-bold">{{ $money($movement['credit']) }}</td>
                                    <td class="text-end stk-num fw-bold text-{{ $netTone }}">{{ $money($movement['net']) }}</td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>

                @if ($entries->hasPages())
                    <div class="stk-table-foot">
                        {{ $entries->appends(request()->except('entries_page'))->links() }}
                    </div>
                @endif
            </div>
        </section>
    </div>
@endsection
