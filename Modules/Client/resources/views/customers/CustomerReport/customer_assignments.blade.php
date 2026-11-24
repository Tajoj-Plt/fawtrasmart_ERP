{{--
    تقرير العملاء المسنَدين للموظفين — اختر موظفاً فتظهر عملاؤه، ولأيّ جهةٍ يتبعون
    في شجرة الفروع. المتحكّم: `CustomerAssignmentReportController`.
--}}
@extends('client::master')

@section('title')
    العملاء المسندون للموظفين
@stop

@section('css')
    @include('client::customers.CustomerReport.partials.report-theme')
    <style>
        .rpt .as-path { font-size: 11px; color: var(--rpt-muted); }
        .rpt .as-emp { display: inline-block; margin: 1px 0 1px 4px; padding: 1px 8px; border-radius: 999px; font-size: 11px;
            background: color-mix(in srgb, var(--rpt-brand, #7367f0) 10%, transparent); color: var(--rpt-brand, #7367f0); }
        .rpt .as-emp.is-selected { background: var(--rpt-brand, #7367f0); color: #fff; }
        .rpt .as-nodes { max-height: 360px; overflow-y: auto; }
        .rpt .as-node-link { color: inherit; text-decoration: none; border-bottom: 1px dashed currentColor; }
        .rpt-client-link { color: inherit; text-decoration: none; border-bottom: 1px dashed currentColor; }
        .rpt-client-link:hover { color: var(--app-primary, #7367f0); border-bottom-style: solid; }

        /* ── شريطُ الإجراءات ── */
        .rpt .as-actions { padding: 12px 16px; border-bottom: 1px solid var(--rpt-line); background: color-mix(in srgb, var(--rpt-brand, #7367f0) 4%, transparent); }
        .rpt .as-actions__scope { display: flex; flex-wrap: wrap; align-items: center; gap: 16px; margin-bottom: 10px; font-size: .85rem; }
        .rpt .as-actions__all { display: inline-flex; align-items: center; gap: 6px; margin: 0; font-weight: 600; cursor: pointer; }
        .rpt .as-actions__fields { display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)) ; gap: 10px; align-items: end; }
        .rpt .as-field label { display: block; font-size: .76rem; font-weight: 700; color: var(--rpt-muted); margin-bottom: 4px; }
        .rpt .as-actions__go { height: 38px; white-space: nowrap; }
        .rpt .as-actions__hint { margin: 8px 0 0; font-size: .76rem; color: var(--rpt-muted); }
        .rpt .as-emp { display: inline-flex; align-items: center; gap: 4px; }
        .rpt .as-emp__x { border: 0; background: transparent; color: inherit; font-size: 15px; line-height: 1; padding: 0 2px; cursor: pointer; opacity: .6; }
        .rpt .as-emp__x:hover { opacity: 1; color: #ef4444; }
        .rpt .as-emp.is-selected .as-emp__x:hover { color: #fff; }
    </style>
@endsection

@section('content')
@php
    $pill = fn ($model, string $empty) => $model
        ? '<span class="rpt-pill" style="background:'.e($model->color ?: '#6c757d').'; color:#fff">'.e($model->name).'</span>'
        : '<span class="text-muted small">'.e($empty).'</span>';
    $selectedEmployeeId = $selectedEmployee?->id;
    $subject = $assignmentMode === 'unassigned'
        ? 'العملاء غير المسنَدين لأيّ موظف'
        : ($selectedEmployee ? 'عملاء '.$selectedEmployee->full_name : 'كل العملاء المسنَدين');
@endphp

<div class="rpt">
    @include('client::customers.CustomerReport.partials.report-head', [
        'headIcon' => 'user-friends',
        'headTitle' => 'العملاء المسندون للموظفين',
        'headSubtitle' => 'اختر موظفاً فتظهر عملاؤه المسنَدون إليه، ولأيّ جهةٍ يتبعون في شجرة الفروع',
        'headExtra' => $subject,
        'headGuideKey' => 'client-reports.assignments',
        'headGuide' => [
            'title' => 'ما «المسنَد»؟',
            'lead' => 'العميلُ المسنَد لموظف هو من يظهر الموظف في «الموظفون المسؤولون» في ملف العميل — جدول الإسناد نفسه الذي تعدّ منه لوحة العملاء.',
            'blocks' => [[
                '«التوزيع على شجرة الفروع» جهةٌ لكل سطر بمسارها من الجذر، وعدد عملاء الموظف فيها — اضغط الجهة لتصفية الجدول عليها.',
                'العميل قد يُسنَد لأكثر من موظف؛ عمود «المسؤولون» يعرضهم جميعاً ويميّز الموظف المختار.',
                'فرع الشريط العلوي و«تابع لـ» يحصران العملاء كبقية تقارير العملاء.',
            ]],
            'facts' => [],
        ],
    ])

    @include('layouts.alerts.success')
    @include('layouts.alerts.error')

    {{-- ===================== الفلترة ===================== --}}
    <div class="rpt-card rpt-filters no-print">
        <div class="rpt-card__body">
            <form method="GET" action="{{ url()->current() }}" id="filterForm">
                <div class="ag-filter-grid">
                    <div class="rpt-field">
                        @include('client::customers.CustomerReport.partials.sales-employee-select', ['salesEmployees' => $salesEmployees ?? collect(), 'label' => 'الموظف'])
                    </div>

                    <div class="rpt-field">
                        <label for="assignment">الإسناد</label>
                        <select name="assignment" id="assignment" class="form-control rpt-select2">
                            <option value="">المسنَدون</option>
                            <option value="unassigned" @selected($assignmentMode === 'unassigned')>غير مسنَدين لأيّ موظف</option>
                        </select>
                    </div>

                    <div class="rpt-field">
                        @include('reports.partials.client-select', ['name' => 'customer', 'selectedClient' => $selectedClient ?? null, 'label' => 'العميل'])
                    </div>

                    <div class="rpt-field">
                        <label for="status">حالة العميل</label>
                        <select name="status" id="status" class="form-control rpt-select2">
                            <option value="">كل الحالات</option>
                            @include('reports.partials.status-filter-options')
                        </select>
                    </div>

                    <div class="rpt-field">
                        @include('client::customers.CustomerReport.partials.stage-select', ['registrationStages' => $registrationStages ?? collect(), 'label' => 'وضع العميل'])
                    </div>

                    <div class="rpt-field">
                        @include('client::customers.CustomerReport.partials.target-select', ['customerTargets' => $customerTargets ?? collect(), 'label' => 'هدف العميل'])
                    </div>

                    <div class="rpt-field">
                        <label for="category_client">تصنيف العميل</label>
                        <select name="category_client" id="category_client" class="form-control rpt-select2">
                            <option value="">جميع التصنيفات</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}" @selected((string) request('category_client') === (string) $category->id)>{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="ag-span-all">
                        <div class="row">
                            @include('reports.partials.affiliation-tree', ['treeNodes' => $treeNodes ?? []])
                        </div>
                    </div>
                </div>

                <div class="ag-filter-foot">
                    <div class="ag-quick__actions">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> عرض التقرير</button>
                        <a href="{{ url()->current() }}" class="btn ag-btn-soft"><i class="fas fa-redo"></i> إعادة تعيين</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- ===================== المؤشّرات ===================== --}}
    <div class="ag-kpis">
        <div class="ag-kpi" style="--c:#3b82f6">
            <div><div class="ag-kpi__label">العملاء</div><div class="ag-kpi__value"><bdi>{{ number_format($totals['clients']) }}</bdi></div><div class="ag-kpi__sub">{{ $subject }}</div></div>
            <span class="ag-kpi__icon"><i class="fas fa-users"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#10b981">
            <div><div class="ag-kpi__label">نشط</div><div class="ag-kpi__value"><bdi>{{ number_format($totals['active']) }}</bdi></div><div class="ag-kpi__sub">عميل</div></div>
            <span class="ag-kpi__icon"><i class="fas fa-check-circle"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#ef4444">
            <div><div class="ag-kpi__label">موقوف</div><div class="ag-kpi__value"><bdi>{{ number_format($totals['stopped']) }}</bdi></div><div class="ag-kpi__sub">عميل</div></div>
            <span class="ag-kpi__icon"><i class="fas fa-ban"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#8b5cf6">
            <div><div class="ag-kpi__label">الجهات</div><div class="ag-kpi__value"><bdi>{{ number_format($totals['nodes']) }}</bdi></div><div class="ag-kpi__sub">جهةٌ في الشجرة يتبعها هؤلاء العملاء</div></div>
            <span class="ag-kpi__icon"><i class="fas fa-sitemap"></i></span>
        </div>
    </div>

    {{-- ===================== التوزيع على الشجرة ===================== --}}
    <div class="rpt-card">
        <div class="rpt-card__head">
            <h4><i class="fas fa-sitemap"></i> التوزيع على شجرة الفروع <span class="rpt-pill rpt-pill-secondary">{{ number_format($totals['nodes']) }} جهة</span></h4>
        </div>
        <div class="as-nodes rpt-scroll">
            <table class="rpt-table ag-table">
                <thead>
                    <tr><th>الجهة (تابع لـ)</th><th>النوع</th><th>العملاء</th><th>نشط</th><th>موقوف</th><th>غير ذلك</th></tr>
                </thead>
                <tbody>
                    @forelse ($byNode as $node)
                        <tr>
                            <td>
                                @if ($node['id'])
                                    {{-- الضغطُ يصفّي الجدول على هذه الجهة وحدها، بالفلاتر نفسها --}}
                                    <a class="as-node-link" href="{{ request()->fullUrlWithQuery(['tree_node' => $node['id'], 'exact_node' => 1, 'page' => null]) }}"><strong>{{ $node['name'] }}</strong></a>
                                @else
                                    <strong>{{ $node['name'] }}</strong>
                                @endif
                                <div class="as-path">{{ $node['path'] }}</div>
                            </td>
                            <td>{{ $node['type'] ?: '—' }}</td>
                            <td class="rpt-num"><strong>{{ number_format($node['total']) }}</strong></td>
                            <td class="rpt-num rpt-pos">{{ number_format($node['active']) }}</td>
                            <td class="rpt-num rpt-neg">{{ number_format($node['stopped']) }}</td>
                            <td class="rpt-num">{{ number_format($node['total'] - $node['active'] - $node['stopped']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="rpt-empty"><i class="fas fa-inbox"></i> لا عملاء مطابقون</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- ===================== العملاء ===================== --}}
    @php
        /* قائمةُ «الموظف» في الإزالة والنقل-عن: المسموحُ تعيينُهم + من هم على عملاء
           هذه الصفحة فعلاً — موظفٌ خرج قسمُه من الإعدادات يبقى قابلاً للإزالة. */
        $asOnPage = $clients->getCollection()->flatMap->employees->unique('id');
        $asAllEmployees = $assignableEmployees->concat($asOnPage)->unique('id')->sortBy(fn ($e) => $e->full_name)->values();
        $asFilters = collect(request()->query())->except(['page', 'export', 'per_page'])->all();
    @endphp
    <div class="rpt-card" id="reportCard">
        <div class="rpt-card__head ag-table-head">
            <h4><i class="fas fa-table"></i> {{ $subject }} <span class="rpt-pill rpt-pill-secondary">{{ number_format($clients->total()) }} عميل</span></h4>
            <div class="ag-tools no-print">
                <label class="ag-page-size" for="asPerPage">
                    <span>عدد الصفوف</span>
                    <select id="asPerPage" name="per_page" form="filterForm" class="form-control" onchange="this.form.submit()">
                        @foreach ($pageSizes as $size)
                            <option value="{{ $size }}" @selected($perPage === $size)>{{ $size }}</option>
                        @endforeach
                    </select>
                </label>
                <button type="button" class="btn btn-sm ag-btn-soft" id="exportExcel"><i class="fas fa-file-excel"></i> تصدير</button>
                <button type="button" class="btn btn-sm ag-btn-soft" id="printBtn"><i class="fas fa-print"></i> طباعة</button>
            </div>
        </div>

        @if ($canAssign)
            {{-- ── شريطُ الإجراءات: حدِّد عملاءَ من الجدول (أو كلَّ نتائج الفلتر) ثمّ اختر الإجراء ── --}}
            <form method="POST" action="{{ route('clients.assignments.apply') }}" id="asApplyForm" class="as-actions no-print">
                @csrf
                @foreach ($asFilters as $filterKey => $filterValue)
                    @if (is_scalar($filterValue))
                        <input type="hidden" name="filters[{{ $filterKey }}]" value="{{ $filterValue }}">
                    @endif
                @endforeach

                <div class="as-actions__scope">
                    <strong><span id="asSelectedCount">0</span> عميل محدَّد</strong>
                    <label class="as-actions__all">
                        <input type="checkbox" name="apply_to" value="all" id="asApplyAll">
                        كل نتائج الفلتر ({{ number_format($clients->total()) }} عميل)
                    </label>
                </div>

                <div class="as-actions__fields">
                    <div class="as-field">
                        <label for="asAction">الإجراء</label>
                        <select name="action" id="asAction" class="form-control">
                            <option value="add">إضافة موظف — بجانب من على العميل</option>
                            <option value="replace">نقل إلى موظف — يصير وحده على العميل</option>
                            <option value="transfer" @selected((bool) $selectedEmployee)>نقل من موظف إلى آخر</option>
                            <option value="remove">إزالة موظف عن العملاء</option>
                        </select>
                    </div>

                    <div class="as-field" id="asFromWrap" hidden>
                        <label for="asFrom">من الموظف</label>
                        <select name="from_employee_id" id="asFrom" class="form-control rpt-select2">
                            <option value="">— اختر —</option>
                            @foreach ($asAllEmployees as $employee)
                                <option value="{{ $employee->id }}" @selected((int) $selectedEmployeeId === (int) $employee->id)>{{ $employee->full_name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="as-field">
                        <label for="asEmployee" id="asEmployeeLabel">الموظف</label>
                        <select name="employee_id" id="asEmployee" class="form-control rpt-select2" required>
                            <option value="">— اختر —</option>
                            @foreach ($asAllEmployees as $employee)
                                <option value="{{ $employee->id }}" data-assignable="{{ $assignableEmployees->contains('id', $employee->id) ? 1 : 0 }}">{{ $employee->full_name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <button type="submit" class="btn btn-primary as-actions__go"><i class="fas fa-check"></i> تنفيذ</button>
                </div>

                <p class="as-actions__hint" id="asHint"></p>
            </form>
        @endif

        <div class="rpt-scroll table-responsive">
            <table class="rpt-table ag-table" id="reportTable">
                <thead>
                    <tr>
                        @if ($canAssign)
                            <th class="no-print" style="width:34px"><input type="checkbox" id="asCheckAll" title="تحديد كل عملاء الصفحة" aria-label="تحديد كل عملاء الصفحة"></th>
                        @endif
                        <th>#</th><th>الكود</th><th>العميل</th><th>الحالة</th><th>الوضع</th><th>الهدف</th><th>تابع لـ</th><th>المسؤولون</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($clients as $client)
                        @php
                            $node = $client->resolveTreeNode();
                            $name = trim((string) $client->trade_name) ?: trim($client->first_name.' '.$client->last_name);
                            $name = $name !== '' ? $name : '#'.$client->id;
                        @endphp
                        <tr data-client="{{ $client->id }}" data-name="{{ $name }}">
                            @if ($canAssign)
                                <td class="no-print"><input type="checkbox" class="as-check" name="client_ids[]" value="{{ $client->id }}" form="asApplyForm" aria-label="تحديد {{ $name }}"></td>
                            @endif
                            <td>{{ $clients->firstItem() + $loop->index }}</td>
                            <td class="rpt-num">{{ $client->code }}</td>
                            <td class="rpt-name"><a href="{{ route('clients.show', $client->id) }}" target="_blank" rel="noopener" class="rpt-client-link">{{ $name }}</a></td>
                            <td>{!! $pill($client->status_client, 'بدون حالة') !!}</td>
                            <td>{!! $pill($client->registrationStage, 'بدون وضع') !!}</td>
                            <td>{!! $pill($client->customerTarget, 'بلا هدف') !!}</td>
                            <td>{{ $node?->name ?? '—' }}<div class="as-path">{{ $node?->lineagePathLabel() }}</div></td>
                            <td>
                                @forelse ($client->employees as $employee)
                                    <span class="as-emp {{ (int) $employee->id === (int) $selectedEmployeeId ? 'is-selected' : '' }}">
                                        {{ $employee->full_name }}
                                        @if ($canAssign)
                                            <button type="button" class="as-emp__x no-print" data-remove-employee="{{ $employee->id }}" data-employee-name="{{ $employee->full_name }}" title="حذف هذا الموظف من العميل" aria-label="حذف {{ $employee->full_name }} من {{ $name }}">&times;</button>
                                        @endif
                                    </span>
                                @empty
                                    <span class="text-muted">—</span>
                                @endforelse
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ $canAssign ? 9 : 8 }}" class="rpt-empty"><i class="fas fa-inbox"></i> لا عملاء مطابقون</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($clients->hasPages())
            <div class="rpt-card__body no-print d-flex justify-content-between align-items-center flex-wrap gap-2">
                <small class="text-muted">عرض {{ $clients->firstItem() }} إلى {{ $clients->lastItem() }} من {{ number_format($clients->total()) }} عميل</small>
                {{ $clients->links('pagination::bootstrap-5') }}
            </div>
        @endif
    </div>

    @if ($canAssign)
        {{-- نموذجُ حذفٍ واحد لكل الشارات — يُملأ عند الضغط --}}
        <form action="{{ route('clients.remove-single-assignment') }}" method="POST" id="asRemoveForm" hidden>
            @csrf
            <input type="hidden" name="client_id">
            <input type="hidden" name="employee_id">
        </form>
    @endif

    @include('sales::sals_report.report_partials.print-footer')
</div>
@endsection

@section('scripts')
    @include('reports.partials.fetch-rows-tools', [
        'frTitle' => 'العملاء المسندون للموظفين',
        'frInfo' => $subject,
        'frHeaders' => ['#', 'الكود', 'العميل', 'الحالة', 'الوضع', 'الهدف', 'تابع لـ', 'المسؤولون'],
    ])
    <script>
        $(function () {
            if ($.fn.select2) {
                $('.rpt-select2').each(function () { if ($(this).data('select2')) { $(this).select2('destroy'); } });
                $('.rpt-select2').select2({ dir: 'rtl', allowClear: true, width: '100%', placeholder: 'اختر...' });
            }

            const form = document.getElementById('asApplyForm');
            if (!form) { return; }

            const TOTAL = {{ (int) $clients->total() }};
            const RULE = @json(! $allowMultiple
                ? 'الإعدادات تسمح بموظفٍ واحد للعميل — «إضافة» تتخطّى من له موظف.'
                : ($maxEmployees ? 'الحدّ الأقصى من الإعدادات '.$maxEmployees.' موظف للعميل — «إضافة» تتخطّى من بلغه.' : ''));
            const HINTS = {
                add: 'يُضاف الموظف بجانب من هم على العميل، ولا يفقد أحدٌ عملاءه. ' + RULE,
                replace: 'يصير الموظف المختار وحده على هؤلاء العملاء، ويُنزع عنهم كل موظفٍ آخر.',
                transfer: 'يُنقل العملاء من الموظف الأول إلى الثاني، ويبقى بقية المسؤولين عن العميل كما هم.',
                remove: 'يُزال الموظف المختار عن العملاء المحدَّدين، ويبقى بقية المسؤولين.'
            };
            const LABELS = { add: 'الموظف المضاف', replace: 'الموظف الجديد', transfer: 'إلى الموظف', remove: 'الموظف المُزال' };

            const $action = $('#asAction');
            const checks = function () { return Array.from(document.querySelectorAll('.as-check')); };
            const all = document.getElementById('asApplyAll');
            const head = document.getElementById('asCheckAll');

            function count() {
                return all.checked ? TOTAL : checks().filter(function (c) { return c.checked; }).length;
            }

            function refresh() {
                const action = $action.val();
                document.getElementById('asSelectedCount').textContent = count().toLocaleString('en-US');
                document.getElementById('asFromWrap').hidden = action !== 'transfer';
                document.getElementById('asEmployeeLabel').textContent = LABELS[action];
                document.getElementById('asHint').textContent = HINTS[action];
                checks().forEach(function (c) { c.disabled = all.checked; });
                head.disabled = all.checked;
            }

            $action.on('change', refresh);
            all.addEventListener('change', refresh);
            head.addEventListener('change', function () {
                checks().forEach(function (c) { c.checked = head.checked; });
                refresh();
            });
            document.getElementById('reportTable').addEventListener('change', function (e) {
                if (e.target.classList.contains('as-check')) { refresh(); }
            });

            form.addEventListener('submit', function (e) {
                const n = count();
                const action = $action.val();
                const employee = $('#asEmployee option:selected').text().trim();
                if (!n) { e.preventDefault(); alert('حدّد عميلاً واحداً على الأقل من الجدول، أو اختر «كل نتائج الفلتر».'); return; }
                if (action === 'transfer' && !$('#asFrom').val()) { e.preventDefault(); alert('اختر الموظف الذي يُنقل عنه.'); return; }

                const text = {
                    add: 'إضافة «' + employee + '» إلى ' + n + ' عميل؟',
                    replace: 'نقل ' + n + ' عميل إلى «' + employee + '»؟ سيُنزع عنهم كل موظفٍ آخر.',
                    transfer: 'نقل ' + n + ' عميل من «' + $('#asFrom option:selected').text().trim() + '» إلى «' + employee + '»؟',
                    remove: 'إزالة «' + employee + '» عن ' + n + ' عميل؟'
                }[action];
                if (!confirm(text)) { e.preventDefault(); }
            });

            // حذفُ موظفٍ من عميلٍ بعينه — من شارته
            document.getElementById('reportTable').addEventListener('click', function (e) {
                const btn = e.target.closest('[data-remove-employee]');
                if (!btn) { return; }
                const row = btn.closest('tr');
                if (!confirm('حذف «' + btn.dataset.employeeName + '» من العميل «' + row.dataset.name + '»؟')) { return; }
                const remove = document.getElementById('asRemoveForm');
                remove.elements.client_id.value = row.dataset.client;
                remove.elements.employee_id.value = btn.dataset.removeEmployee;
                remove.submit();
            });

            refresh();
        });
    </script>
@endsection
