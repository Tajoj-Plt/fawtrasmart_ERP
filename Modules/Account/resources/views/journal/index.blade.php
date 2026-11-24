@extends('account::master')

@section('title', 'قيود اليومية')

@section('content')
    <div class="content-body">

        @section('page-actions')
            @can('account.journal.create')
                <a href="{{ route('journal.create') }}" class="btn btn-primary">
                    <i class="feather icon-plus"></i> قيد جديد
                </a>
            @endcan
        @endsection

        @include('account::components.page-head', [
            'title' => 'قيود اليومية',
            'icon' => 'edit-3',
            'sub' => 'القيود المحرَّرة يدوياً والمولَّدة من المستندات',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'الحسابات' => null,
                'قيود اليومية' => null,
            ],
        ])

        {{--
            **ما كان**

            الشاشة كانت تفتح `content-body` ثم تفتح `content-body` أخرى
            داخلها، وشريطَ أدواتٍ في بطاقةٍ فارغة نصفها، فيه زرّ «سجل
            التعديلات» يشير إلى الشاشة نفسها وزرّ «استيراد» بـ `href=""` —
            رابطان لا يفعلان شيئاً. وبطاقات الإجماليات كانت
            `card-congratulation-medal` بعناوين `h3` داخل `<a href="javascript:void(0)">`
            — روابطُ لا تقود إلى شيء وصنفٌ من قالب Vuexy لا يتبع سمة النظام.

            **ما صار**

            نفس هيكل شاشة المصروفات: ترويسة موحّدة، وبطاقات `stk-metric`،
            وبطاقةُ فلترة `stk-filters`، وجدولٌ في `stk-table-card`.
        --}}
        <div class="stk-metrics">
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-arrow-down-left"></i></span>
                <span class="stk-metric__label">إجمالي المدين</span>
                <span class="stk-metric__value">@money($totalDebit ?? 0)</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-arrow-up-right"></i></span>
                <span class="stk-metric__label">إجمالي الدائن</span>
                <span class="stk-metric__value">@money($totalCredit ?? 0)</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-percent"></i></span>
                <span class="stk-metric__label">الرصيد {{ $balanceType ? '(' . $balanceType . ')' : '' }}</span>
                <span class="stk-metric__value">@money($balance ?? 0)</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-list"></i></span>
                <span class="stk-metric__label">عدد القيود</span>
                <span class="stk-metric__value stk-num">
                    {{ method_exists($entries, 'total') ? $entries->total() : count($entries) }}
                </span>
            </div>
        </div>

        <div class="card stk-filters">
            <div class="card-body">
                <div class="stk-filters__head">
                    <h6 class="stk-filters__title"><i class="feather icon-filter"></i> بحث وتصفية</h6>

                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse"
                        data-bs-target="#advancedSearchForm" onclick="toggleSearchText(this)">
                        <i class="feather icon-sliders"></i> <span class="button-text">بحث متقدم</span>
                    </button>
                </div>

                <form id="searchForm" action="{{ route('journal.index') }}" method="GET" class="form">
                    <div class="row">
                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label for="entry_number">رقم القيد</label>
                                <input type="text" id="entry_number" name="entry_number" class="form-control"
                                    placeholder="مثال: SI-00084 أو 00084…" value="{{ request('entry_number') }}">
                            </div>
                        </div>

                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label for="accountSelect">الحساب</label>
                                <select name="account_id" class="form-control select2" id="accountSelect">
                                    <option value="">جميع الحسابات</option>
                                    @foreach ($accounts as $account)
                                        <option value="{{ $account->id }}" data-code="{{ $account->code }}"
                                            @selected(request('account_id') == $account->id)>
                                            {{ $account->code ? $account->code . ' - ' : '' }}{{ $account->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label for="description">الوصف</label>
                                <input type="text" id="description" name="description" class="form-control"
                                    placeholder="جزء من نصّ البيان…" value="{{ request('description') }}">
                            </div>
                        </div>

                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label for="date_type">التجميع الزمني</label>
                                <select name="date_type" class="form-control" id="date_type">
                                    <option value="">بلا تجميع</option>
                                    <option value="daily" @selected(request('date_type') == 'daily')>يومياً</option>
                                    <option value="weekly" @selected(request('date_type') == 'weekly')>أسبوعياً</option>
                                    <option value="monthly" @selected(request('date_type') == 'monthly')>شهرياً</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="collapse {{ request()->hasAny(['total_from', 'total_to', 'period_id', 'created_by', 'cost_center', 'source', 'status', 'balance_status', 'taxable']) ? 'show' : '' }}"
                        id="advancedSearchForm">
                        <div class="row">
                            {{-- الفترة المحاسبية: اختيارها يملأ حقلَي المدى --}}
                            @include('partials.accounting-period-filter', [
                                'colClass' => 'col-md-3 col-sm-6',
                                'costName' => 'cost_center',
                                'period' => $period ?? [],
                            ])

                            {{--
                                حقلُ «الإجمالي من» وإغلاقُ الصفّ سقطا حين رُفع حقلا
                                التاريخ من البحث المتقدّم: أُغلق `.row` قبل أوانه فخرجت
                                بقيّةُ الحقول منه، فبانت غيرَ مصطفّة ولا يجد المستخدمُ
                                حدَّ المبلغ الأدنى.
                            --}}
                            <div class="col-md-3 col-sm-6">
                                <div class="form-group">
                                    <label for="total_from">الإجمالي من</label>
                                    <input type="number" step="0.01" id="total_from" name="total_from"
                                        class="form-control" placeholder="0.00" value="{{ request('total_from') }}">
                                </div>
                            </div>

                            <div class="col-md-3 col-sm-6">
                                <div class="form-group">
                                    <label for="total_to">الإجمالي إلى</label>
                                    <input type="number" step="0.01" id="total_to" name="total_to"
                                        class="form-control" placeholder="0.00" value="{{ request('total_to') }}">
                                </div>
                            </div>

                            <div class="col-md-3 col-sm-6">
                                <div class="form-group">
                                    <label for="created_by">أضيفت بواسطة</label>
                                    <select name="created_by" id="created_by" class="form-control select2">
                                        <option value="">جميع المستخدمين</option>
                                        @foreach ($users as $user)
                                            <option value="{{ $user->id }}" @selected(request('created_by') == $user->id)>
                                                {{ $user->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-3 col-sm-6">
                                <div class="form-group">
                                    <label for="status">حالة القيد</label>
                                    <select name="status" id="status" class="form-control">
                                        <option value="">جميع الحالات</option>
                                        <option value="1" @selected(request('status') === '1')>معتمد</option>
                                        <option value="0" @selected(request('status') === '0')>غير معتمد</option>
                                    </select>
                                </div>
                            </div>

                            {{-- ثلاثُ حالاتٍ لا اثنتان: خانةُ التأشير لا تُتيح طلبَ
                                 «غير الضريبية» وحدَها، وهي ما يُراجَع عند التسوية. --}}
                            <div class="col-md-3 col-sm-6">
                                <div class="form-group">
                                    <label for="taxable">الضريبة</label>
                                    <select name="taxable" id="taxable" class="form-control">
                                        <option value="">الكل</option>
                                        <option value="1" @selected(request('taxable') === '1')>ضريبية فقط</option>
                                        <option value="0" @selected(request('taxable') === '0')>غير ضريبية</option>
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-3 col-sm-6">
                                <div class="form-group">
                                    <label for="balance_status">حالة الاتزان</label>
                                    <select name="balance_status" id="balance_status" class="form-control">
                                        <option value="">الكل</option>
                                        <option value="balanced" @selected(request('balance_status') == 'balanced')>
                                            المتوازنة
                                        </option>
                                        <option value="unbalanced" @selected(request('balance_status') == 'unbalanced')>
                                            غير المتوازنة
                                        </option>
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-3 col-sm-6">
                                <div class="form-group">
                                    <label for="source">المصدر</label>
                                    <select name="source" id="source" class="form-control select2">
                                        <option value="">جميع المصادر</option>
                                        {{-- أنواعُ القيود الفعلية (`reference_type`) — كانت أسماءً من نظامٍ آخر على عمودٍ غير موجود --}}
                                        @foreach (\App\Enums\ReferenceType::cases() as $referenceType)
                                            <option value="{{ $referenceType->value }}" @selected(request('source') === $referenceType->value)>
                                                {{ $referenceType->getName() }}
                                            </option>
                                        @endforeach
                                        <option value="none" @selected(request('source') === 'none')>بلا نوع</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="stk-filters__foot">
                        <a href="{{ route('journal.index') }}" class="btn btn-outline-secondary">
                            <i class="feather icon-rotate-ccw"></i> إعادة تعيين
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="feather icon-search"></i> بحث
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card stk-table-card">
            @if (count($entries) > 0)
                <div class="stk-table-head">
                    <h6 class="stk-table-head__title">
                        <i class="feather icon-list"></i> القيود
                        <span class="stk-count">
                            {{ method_exists($entries, 'total') ? $entries->total() : count($entries) }}
                        </span>
                    </h6>

                    @include('stock::components.per-page', [
                        'onchange' => "window.location.href='"
                            . route('journal.index') . '?' . http_build_query(request()->except('per_page', 'page'))
                            . "&per_page=' + this.value",
                    ])
                </div>

                <div class="table-responsive">
                    <table class="table table-hover table-striped mb-0" id="journalTable">
                        <thead>
                            <tr>
                                <th style="width:90px">رقم القيد</th>
                                <th>مسار الحسابات</th>
                                <th>التاريخ</th>
                                <th>بواسطة</th>
                                <th class="text-center">الحالة</th>
                                <th class="text-center">المبلغ</th>
                                <th class="text-center" style="width:90px">إجراءات</th>
                            </tr>
                        </thead>

                        <tbody>
                            @php $prevGroupKey = null; @endphp
                            @foreach ($entries as $entry)
                                {{-- التجميع الزمني: ترويسةٌ قبل أوّل قيدٍ من كل يوم/أسبوع/شهر —
                                     العددُ والمجموعُ على الفلتر كاملاً لا على الصفحة وحدها --}}
                                @if (! empty($groups) && $entry->group_key !== $prevGroupKey)
                                    @php
                                        $prevGroupKey = $entry->group_key;
                                        $group = $groups[$entry->group_key] ?? null;
                                    @endphp
                                    @if ($group)
                                        <tr class="table-active">
                                            <td colspan="5">
                                                <i class="feather icon-calendar"></i>
                                                <strong>{{ $group['label'] }}</strong>
                                                <span class="text-muted">— {{ $group['count'] }} قيداً</span>
                                            </td>
                                            <td class="text-center"><strong>@money($group['total'])</strong></td>
                                            <td></td>
                                        </tr>
                                    @endif
                                @endif
                                @php
                                    // ترتيبٌ بالتناوب: مدين ثمّ دائن، ليُقرأ القيد كمسار
                                    $debitDetails = $entry->details->where('debit', '>', 0)->values();
                                    $creditDetails = $entry->details->where('credit', '>', 0)->values();

                                    $orderedDetails = collect();
                                    $maxCount = max($debitDetails->count(), $creditDetails->count());

                                    for ($i = 0; $i < $maxCount; $i++) {
                                        if (isset($debitDetails[$i])) {
                                            $orderedDetails->push($debitDetails[$i]);
                                        }
                                        if (isset($creditDetails[$i])) {
                                            $orderedDetails->push($creditDetails[$i]);
                                        }
                                    }

                                    $isApproved = $entry->status == 1;

                                    // $entry->can_edit يُحسب في JournalEntryController::index() —
                                    // صلاحية account.journal.edit ضمن نطاق الرؤية، لا صلاحيات النظام القديم
                                    $canEdit = $entry->can_edit;
                                @endphp

                                <tr>
                                    <td>
                                        <span class="stk-num">{{ $entry->reference_number ?: $entry->id }}</span>
                                        <x-taxable-badge :on="$entry->is_taxable" />
                                    </td>

                                    <td>
                                        @if ($orderedDetails->count())
                                            <div class="stk-tags">
                                                @foreach ($orderedDetails as $detail)
                                                    @continue(! $detail->account || ! $detail->account->name)
                                                    <a class="stk-state stk-state--neutral"
                                                        href="{{ route('accounts_chart.show_account', $detail->account->id) }}">
                                                        {{ $detail->account->name }}
                                                    </a>
                                                @endforeach
                                            </div>
                                        @else
                                            <span class="text-muted">لا تفاصيل</span>
                                        @endif
                                    </td>

                                    <td class="stk-num">{{ $entry->date->format('Y-m-d') }}</td>

                                    <td>{{ $entry->createdByEmployee->name ?? 'غير محدد' }}</td>

                                    <td class="text-center">
                                        <span class="stk-state {{ $isApproved ? 'stk-state--on' : 'stk-state--neutral' }}">
                                            {{ $isApproved ? 'معتمد' : 'غير معتمد' }}
                                        </span>
                                    </td>

                                    <td class="text-center">
                                        <strong>@money($entry->details->sum('debit'))</strong>
                                    </td>

                                    <td class="text-center">
                                        <div class="dropdown stk-actions">
                                            <button class="btn btn-sm btn-outline-secondary" type="button"
                                                data-bs-toggle="dropdown" aria-expanded="false" aria-label="إجراءات">
                                                <i class="feather icon-more-vertical"></i>
                                            </button>

                                            <div class="dropdown-menu dropdown-menu-end">
                                                <a class="dropdown-item" href="{{ route('journal.show', $entry->id) }}">
                                                    <i class="feather icon-eye"></i> عرض
                                                </a>

                                                @if ($canEdit)
                                                    <a class="dropdown-item"
                                                        href="{{ route('journal.edit', $entry->id) }}">
                                                        <i class="feather icon-edit-2"></i> تعديل
                                                    </a>

                                                    <div class="dropdown-divider"></div>

                                                    {{--
                                                        كان هنا بندٌ ثالث «عرض المصدر» بـ `href=""` —
                                                        يعيد تحميل الشاشة نفسها. حُذف حتى يُوصَل بمصدره.
                                                    --}}
                                                    <form action="{{ route('journal.destroy', $entry->id) }}"
                                                        method="POST" class="delete-form d-inline">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="dropdown-item text-danger delete-btn">
                                                            <i class="feather icon-trash-2"></i> حذف
                                                        </button>
                                                    </form>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @include('stock::components.table-foot', [
                    'paginator' => $entries,
                    'unit' => 'قيداً',
                ])
            @else
                <div class="stk-empty">
                    <span class="stk-empty__icon"><i class="feather icon-edit-3"></i></span>
                    <h6 class="stk-empty__title">لا قيود مطابقة</h6>
                    <p class="stk-empty__hint">
                        @if (request()->hasAny(['entry_number', 'account_id', 'description', 'period_id', 'created_by', 'source']))
                            لا نتائج لهذه الفلاتر — جرّب توسيعها أو أعِد تعيينها.
                        @else
                            لم يُسجَّل قيدٌ بعد. القيود المولَّدة من الفواتير والسندات تظهر هنا تلقائياً.
                        @endif
                    </p>

                    @can('account.journal.create')
                        <a href="{{ route('journal.create') }}" class="btn btn-primary mt-1">
                            <i class="feather icon-plus"></i> قيد جديد
                        </a>
                    @endcan
                </div>
            @endif
        </div>
    </div>
@endsection

@section('scripts')
    <script>
        function toggleSearchText(button) {
            var label = button.querySelector('.button-text');
            label.textContent = label.textContent === 'بحث متقدم' ? 'بحث بسيط' : 'بحث متقدم';
        }

        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.delete-btn').forEach(function(button) {
                button.addEventListener('click', function(e) {
                    e.preventDefault();

                    var form = this.closest('.delete-form');

                    Swal.fire({
                        title: 'حذف القيد؟',
                        text: 'سيُحذف القيد وسطوره. لا رجوع عن هذا.',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#d33',
                        cancelButtonColor: '#3085d6',
                        confirmButtonText: 'نعم، احذف',
                        cancelButtonText: 'إلغاء'
                    }).then(function(result) {
                        if (result.isConfirmed) {
                            form.submit();
                        }
                    });
                });
            });

            // بحثٌ بالاسم أو بالكود في قائمة الحسابات
            if ($('#accountSelect').length && $.fn.select2) {
                $('#accountSelect').select2({
                    dir: 'rtl',
                    placeholder: 'اختر الحساب أو ابحث بالكود',
                    allowClear: true,
                    matcher: function(params, data) {
                        if ($.trim(params.term) === '') {
                            return data;
                        }

                        var term = params.term.toLowerCase();
                        var text = (data.text || '').toLowerCase();
                        var code = $(data.element).data('code');
                        code = code ? String(code).toLowerCase() : '';

                        return (text.indexOf(term) > -1 || code.indexOf(term) > -1) ? data : null;
                    }
                });
            }
        });
    </script>
@endsection
