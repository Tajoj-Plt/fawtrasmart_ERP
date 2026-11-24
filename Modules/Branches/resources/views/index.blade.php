@extends('branches::layouts.master')

@section('title', __('branches.tree.breadcrumb'))

@section('content')
    @php
        use App\Constants\planFeatures\BranchesPlanFeatures;
        use App\Support\PlanLock;

        // القفلُ يُحسب مرّةً في رأس القالب لا عند كل زرّ: `locked()` يقرأ
        // لقطةَ الاشتراك، وتكرارُ النداء داخل حلقة الشجرة يعني قراءةً لكل صفّ.
        $lockCreate = PlanLock::locked(BranchesPlanFeatures::BRANCHES);
        $lockMerge = PlanLock::locked(BranchesPlanFeatures::MERGE);
        $lockExport = PlanLock::locked(BranchesPlanFeatures::EXPORT);
        $lockHierarchy = PlanLock::locked(BranchesPlanFeatures::HIERARCHY);
    @endphp

    <x-branches::page-head
        :title="__('branches.tree.title')"
        :subtitle="__('branches.tree.subtitle')"
        :crumbs="[__('branches.tree.breadcrumb') => null]">
        <x-slot:actions>
            @can('branches.create')
                <a href="{{ $lockCreate ? PlanLock::url() : route('branches.create') }}"
                   class="btn btn-primary {{ $lockCreate ? 'stk-plan-locked' : '' }}"
                   @if ($lockCreate) title="{{ PlanLock::reason(BranchesPlanFeatures::BRANCHES) }}" @endif>
                    <i class="feather icon-{{ $lockCreate ? 'lock' : 'plus' }}"></i> {{ __('branches.dashboard.new_branch') }}
                </a>
            @endcan
            @can('branches.groups.merge')
                <a href="{{ $lockMerge ? PlanLock::url() : route('groups.merge_select_page') }}"
                   class="btn btn-outline-primary {{ $lockMerge ? 'stk-plan-locked' : '' }}"
                   @if ($lockMerge) title="{{ PlanLock::reason(BranchesPlanFeatures::MERGE) }}" @endif>
                    <i class="feather icon-{{ $lockMerge ? 'lock' : 'users' }}"></i> {{ __('branches.tree.move_clients') }}
                </a>
            @endcan
            @can('branches.export')
                <a href="{{ $lockExport ? PlanLock::url() : route('branches.export') }}"
                   class="btn btn-outline-secondary {{ $lockExport ? 'stk-plan-locked' : '' }}"
                   title="{{ $lockExport ? PlanLock::reason(BranchesPlanFeatures::EXPORT) : __('branches.tree.export_hint') }}">
                    <i class="feather icon-{{ $lockExport ? 'lock' : 'download' }}"></i> {{ __('branches.common.export') }}
                </a>
            @endcan
        </x-slot:actions>
    </x-branches::page-head>

    <div class="content-body branches-index-page">
        @include('layouts.alerts.success')
        @include('layouts.alerts.error')

        {{-- الشاشةُ محصورةٌ على فرع الشريط العلويّ — يُقال ذلك صراحةً، وإلّا
             ظنّ المستخدمُ أن بقيّةَ الهيكل حُذف --}}
        @if ($headerScope)
            <div class="alert alert-info sl-note" role="status">
                <i class="feather icon-map-pin"></i>
                <div style="flex: 1; min-width: 0;">
                    <strong class="d-block">{{ __('branches.common.header_scope_title', ['name' => $headerScope['name']]) }}</strong>
                    {{ __('branches.common.header_scope_text', ['name' => $headerScope['name']]) }}
                </div>
            </div>
        @endif

        {{-- ===== بطاقات الإحصاء ===== --}}
        <div class="sl-stats">
            <x-branches::stat :label="__('branches.tree.stat_total')" :value="$stats['total']" icon="git-branch" tone="primary"
                              :note="__('branches.tree.stat_total_note', ['count' => number_format($stats['roots'])])" note-icon="layers" />

            <x-branches::stat :label="__('branches.tree.stat_branches')" :value="$stats['branches']" icon="home" tone="success"
                              :note="$stats['inactive'] ? __('branches.tree.stat_inactive_note', ['count' => number_format($stats['inactive'])]) : __('branches.tree.stat_all_active')"
                              :note-icon="$stats['inactive'] ? 'pause-circle' : 'check-circle'"
                              :note-tone="$stats['inactive'] ? 'down' : 'up'" />

            <x-branches::stat :label="__('branches.tree.stat_territories')" :value="$stats['territories']" icon="map" tone="info"
                              :note="__('branches.tree.stat_neighborhoods_note', ['count' => number_format($stats['neighborhoods'])])" note-icon="map-pin" />

            <x-branches::stat :label="__('branches.tree.stat_clients')" :value="$stats['clients']" icon="users" tone="warning"
                              :note="__('branches.tree.stat_clients_note')" note-icon="link" />
        </div>

        {{-- ===== بطاقة التصفية ===== --}}
        {{--
            كانت البطاقة بترميز القالب القديم: `heading-elements-toggle`
            و`la la-ellipsis-v` وزرُّ طيٍّ يعتمد على سكربت `data-action`
            لم يعد مُحمَّلاً — فالأيقونتان تظهران بلا فعل. وصارت
            `sl-filters` نفسها المستعملة في فهارس المبيعات.
        --}}
        <div class="card sl-filters no-print">
            <div class="card-body">
                <div class="sl-filters__head">
                    <h6 class="sl-filters__title">
                        <i class="feather icon-filter"></i> {{ __('branches.tree.filter_title') }}
                    </h6>
                </div>
                <div>
                    {{-- التصفية تعمل في المتصفّح على الشجرة المعروضة: أي مطابقة
                         تُظهر معها كل أسلافها فيبقى المسار مفهوماً ولا تنكسر الشجرة. --}}
                    <div class="row">
                        <div class="col-md-6 col-12">
                            <div class="form-group">
                                <label for="tree-search">{{ __('branches.common.search') }}</label>
                                <input type="text" id="tree-search" class="form-control"
                                       placeholder="{{ __('branches.tree.filter_search_placeholder') }}" autocomplete="off">
                            </div>
                        </div>

                        <div class="col-md-3 col-sm-6 col-12">
                            <div class="form-group">
                                <label for="filter-type">{{ __('branches.common.type') }}</label>
                                <select id="filter-type" class="form-control">
                                    <option value="">{{ __('branches.tree.filter_all_types') }}</option>
                                    <option value="branch">{{ __('branches.node_types.branch_short') }}</option>
                                    <option value="region">{{ __('branches.node_types.region') }}</option>
                                    <option value="group">{{ __('branches.node_types.group_short') }}</option>
                                    <option value="outlet">{{ __('branches.node_types.outlet_short') }}</option>
                                </select>
                            </div>
                        </div>

                        <div class="col-md-3 col-sm-6 col-12">
                            <div class="form-group">
                                <label for="filter-status">{{ __('branches.common.status') }}</label>
                                <select id="filter-status" class="form-control">
                                    <option value="">{{ __('branches.tree.filter_all_statuses') }}</option>
                                    <option value="active">{{ __('branches.common.active') }}</option>
                                    <option value="inactive">{{ __('branches.common.inactive') }}</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    {{-- ذيلُ البطاقة كما في فلاتر المبيعات: مفتاحُ الأيقونات وعددُ
                         النتائج في جهة، والأزرارُ في الأخرى. كانت الأزرارُ الثلاثة
                         محشورةً في عمودٍ بسدس العرض فتنكسر على سطرين.

                         وهي بنصّها لا بأيقونتها: «توسيع» و«طيّ» أيقونتاهما متشابهتان
                         (مربّعٌ بسهمَين داخلٍ وخارج)، فلا يعرف المستخدم أيّهما أيّ
                         إلّا بالتجربة. --}}
                    <div class="sl-filters__foot bx-filters-foot">
                        <div class="bx-type-key">
                            <span><i class="feather icon-home bx-tone--primary"></i> {{ __('branches.node_types.branch_short') }}</span>
                            <span><i class="feather icon-map bx-tone--success"></i> {{ __('branches.node_types.region') }}</span>
                            <span><i class="feather icon-users bx-tone--info"></i> {{ __('branches.node_types.group_short') }}</span>
                            <span><i class="feather icon-shopping-bag bx-tone--warning"></i> {{ __('branches.node_types.outlet_short') }}</span>
                            <span class="sl-count" id="filter-result"></span>
                        </div>

                        <div class="d-flex flex-wrap" style="gap:.5rem">
                            <button type="button" id="expand-all" class="btn btn-outline-primary btn-sm"
                                    title="{{ __('branches.tree.expand') }}">
                                <i class="feather icon-maximize-2"></i> {{ __('branches.tree.expand_short') }}
                            </button>
                            <button type="button" id="collapse-all" class="btn btn-outline-secondary btn-sm"
                                    title="{{ __('branches.tree.collapse') }}">
                                <i class="feather icon-minimize-2"></i> {{ __('branches.tree.collapse_short') }}
                            </button>
                            <button type="button" id="filter-reset" class="btn btn-outline-secondary btn-sm"
                                    title="{{ __('branches.common.reset') }}">
                                <i class="feather icon-rotate-ccw"></i> {{ __('branches.common.reset') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ===== بطاقة الشجرة ===== --}}
        <div class="card sl-table-card" id="treeCard">
            <div class="sl-table-head">
                <h6 class="sl-table-head__title">
                    <i class="feather icon-git-branch"></i>
                    {{ __('branches.tree.structure') }}
                    <span class="sl-count">{{ __('branches.tree.roots_badge', ['count' => $rootBranches->count()]) }}</span>
                </h6>
            </div>

            <div class="stk-scroll">
                <div class="p-1">
                    {{-- نموذج واحد مخفي لكل الإجراءات المدمّرة — خارج نموذج النقل الجماعي
                         لأن HTML لا يسمح بتداخل النماذج، ومع CSRF بدل روابط GET. --}}
                    <form method="POST" id="branch-action-form" class="d-none">
                        @csrf
                        <input type="hidden" name="_method" value="POST">
                    </form>

                    @if ($rootBranches->isNotEmpty())
                        @php($bulkParentOptions = $bulkParentOptions ?? collect())
                        <form method="POST" action="{{ route('branches.bulk_assign_parent') }}" id="bulk-parent-form">
                            @csrf
                            {{-- «تحديد الكل» انتقل إلى ترويسة الجدول كما في سجل النشاطات --}}
                            <div class="bx-bulk" id="bulk-bar">
                                <div class="flex-grow-1" style="min-width:230px">
                                    <label for="bulk-parent-id" class="d-block mb-25">{{ __('branches.tree.bulk_move_label') }}</label>
                                    <select name="parent_id" id="bulk-parent-id" class="form-control form-control-sm">
                                        <option value="">{{ __('branches.tree.bulk_root_option') }}</option>
                                        @foreach ($bulkParentOptions as $opt)
                                            <option value="{{ $opt['id'] }}">{{ $opt['label'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-primary btn-sm" @disabled($lockHierarchy)
                                        @if ($lockHierarchy) title="{{ PlanLock::reason(BranchesPlanFeatures::HIERARCHY) }}" @endif>
                                    <i class="feather icon-{{ $lockHierarchy ? 'lock' : 'corner-down-left' }}"></i> {{ __('branches.tree.bulk_assign') }}
                                </button>
                                <span class="stk-num" id="bulk-count"></span>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-hover mb-0" id="bx-tree">
                                    <thead>
                                        <tr>
                                            <th class="text-center" style="width:40px">
                                                <div class="custom-control custom-checkbox">
                                                    <input type="checkbox" class="custom-control-input" id="bulk-select-all">
                                                    <label class="custom-control-label" for="bulk-select-all"
                                                           title="{{ __('branches.tree.bulk_select_all') }}"></label>
                                                </div>
                                            </th>
                                            <th>{{ __('branches.tree.col_node') }}</th>
                                            <th style="width:10%">{{ __('branches.common.code') }}</th>
                                            <th style="width:9%">{{ __('branches.common.type') }}</th>
                                            <th style="width:17%" title="{{ __('branches.tree.col_clients_hint') }}">{{ __('branches.tree.col_clients') }}</th>
                                            <th style="width:9%">{{ __('branches.common.status') }}</th>
                                            <th class="text-center" style="width:80px">{{ __('branches.common.actions') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($rootBranches as $root)
                                            @include('branches::partials._branch_tree_node', [
                                                'node' => $root,
                                                'childrenByParent' => $childrenByParent,
                                                'counts' => $counts,
                                                'displayTypes' => $displayTypes,
                                                'depth' => 0,
                                            ])
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                            <div class="d-none" id="tree-no-results">
                                <x-branches::empty icon="search" :title="__('branches.tree.no_filter_results')"
                                                   :text="__('branches.tree.no_filter_results_hint')" />
                            </div>
                        </form>
                    @else
                        <x-branches::empty icon="git-branch" :title="__('branches.tree.empty_title')"
                                           :text="__('branches.tree.empty_text')">
                            @can('branches.create')
                                <a href="{{ $lockCreate ? PlanLock::url() : route('branches.create') }}"
                                   class="btn btn-primary {{ $lockCreate ? 'stk-plan-locked' : '' }}"
                                   @if ($lockCreate) title="{{ PlanLock::reason(BranchesPlanFeatures::BRANCHES) }}" @endif>
                                    <i class="feather icon-{{ $lockCreate ? 'lock' : 'plus' }}"></i> {{ __('branches.tree.empty_action') }}
                                </a>
                            @endcan
                        </x-branches::empty>
                    @endif
                </div>

                @if ($rootBranches->isNotEmpty())
                    <div class="sl-table-foot">
                        <span class="sl-table-foot__count">
                            <i class="feather icon-info"></i> {{ __('branches.tree.footer_hint') }}
                            {{ __('branches.tree.col_clients_hint') }}
                        </span>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection

@section('scripts')
<script>
(function () {
    // ====== أدوات الشجرة ======
    function descendantsOf(nodeId) {
        var direct = document.querySelectorAll('.branch-row[data-parent-id="' + nodeId + '"]');
        var all = Array.prototype.slice.call(direct);
        direct.forEach(function (row) { all = all.concat(descendantsOf(row.getAttribute('data-id'))); });
        return all;
    }

    function collapseSubtree(nodeId) {
        descendantsOf(nodeId).forEach(function (row) {
            row.style.display = 'none';
            var t = document.querySelector('.bx-toggle[data-node-id="' + row.getAttribute('data-id') + '"]');
            if (t) t.setAttribute('aria-expanded', 'false');
        });
    }

    function expandChildren(nodeId) {
        document.querySelectorAll('.branch-row[data-parent-id="' + nodeId + '"]')
            .forEach(function (row) { row.style.display = ''; });
    }

    document.querySelectorAll('.bx-toggle').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            var id = this.getAttribute('data-node-id');
            if (this.getAttribute('aria-expanded') === 'true') {
                collapseSubtree(id);
                this.setAttribute('aria-expanded', 'false');
            } else {
                expandChildren(id);
                this.setAttribute('aria-expanded', 'true');
            }
        });
    });

    // التوسيع والطي عمليتان على الشجرة كاملة، فتُلغيان التصفية القائمة —
    // وإلا ظهر بعد الضغط عناصرُ استبعدتها التصفية والحقول ما زالت ممتلئة.
    function clearFilterInputs() {
        ['tree-search', 'filter-type', 'filter-status'].forEach(function (id) {
            var el = document.getElementById(id);
            if (el) el.value = '';
        });
        var label = document.getElementById('filter-result');
        if (label) label.textContent = '';
        var none = document.getElementById('tree-no-results');
        if (none) none.classList.add('d-none');
        var table = document.getElementById('bx-tree');
        if (table) table.parentElement.classList.remove('d-none');
        document.querySelectorAll('.branch-row').forEach(function (r) { r.classList.remove('is-hit'); });
    }

    var expandAll = document.getElementById('expand-all');
    var collapseAll = document.getElementById('collapse-all');
    if (expandAll) expandAll.addEventListener('click', function () {
        clearFilterInputs();
        document.querySelectorAll('.branch-row').forEach(function (r) { r.style.display = ''; });
        document.querySelectorAll('.bx-toggle').forEach(function (b) { b.setAttribute('aria-expanded', 'true'); });
    });
    if (collapseAll) collapseAll.addEventListener('click', function () {
        clearFilterInputs();
        document.querySelectorAll('.branch-row').forEach(function (r) {
            if (parseInt(r.getAttribute('data-depth'), 10) > 0) r.style.display = 'none';
        });
        document.querySelectorAll('.bx-toggle').forEach(function (b) { b.setAttribute('aria-expanded', 'false'); });
    });

    // ====== التصفية: بحث + نوع + حالة ======
    // المطابق يظهر مع كل أسلافه حتى يبقى مساره في الشجرة مفهوماً — الأسلاف
    // تظهر كمسار لا كنتيجة، ولذلك لا تُحسب ضمن عدد النتائج.
    var search = document.getElementById('tree-search');
    var typeFilter = document.getElementById('filter-type');
    var statusFilter = document.getElementById('filter-status');
    var resultLabel = document.getElementById('filter-result');
    var noResults = document.getElementById('tree-no-results');
    var treeTable = document.getElementById('bx-tree');

    function applyFilters() {
        var term = search ? search.value.trim().toLowerCase() : '';
        var type = typeFilter ? typeFilter.value : '';
        var status = statusFilter ? statusFilter.value : '';
        var rows = document.querySelectorAll('.branch-row');
        var active = term !== '' || type !== '' || status !== '';

        rows.forEach(function (r) { r.classList.remove('is-hit'); });

        if (!active) {
            rows.forEach(function (r) {
                r.style.display = parseInt(r.getAttribute('data-depth'), 10) === 0 ? '' : 'none';
            });
            document.querySelectorAll('.bx-toggle').forEach(function (b) { b.setAttribute('aria-expanded', 'false'); });
            if (resultLabel) resultLabel.textContent = '';
            if (noResults) noResults.classList.add('d-none');
            if (treeTable) treeTable.parentElement.classList.remove('d-none');
            return;
        }

        rows.forEach(function (r) { r.style.display = 'none'; });

        var hits = 0;
        rows.forEach(function (row) {
            var hay = (row.getAttribute('data-search') || row.innerText).toLowerCase();
            if (term !== '' && hay.indexOf(term) === -1) return;
            if (type !== '' && row.getAttribute('data-type') !== type) return;
            if (status !== '' && row.getAttribute('data-status') !== status) return;

            row.style.display = '';
            row.classList.add('is-hit');
            hits++;

            var parentId = row.getAttribute('data-parent-id');
            while (parentId) {
                var parentRow = document.querySelector('.branch-row[data-id="' + parentId + '"]');
                if (!parentRow) break;
                parentRow.style.display = '';
                var toggle = document.querySelector('.bx-toggle[data-node-id="' + parentId + '"]');
                if (toggle) toggle.setAttribute('aria-expanded', 'true');
                parentId = parentRow.getAttribute('data-parent-id');
            }
        });

        if (resultLabel) resultLabel.textContent = hits ? @json(__('branches.tree.filter_results', ['count' => ':n'])).replace(':n', hits) : '';
        if (noResults) noResults.classList.toggle('d-none', hits > 0);
        if (treeTable) treeTable.parentElement.classList.toggle('d-none', hits === 0);
    }

    if (search) search.addEventListener('input', applyFilters);
    if (typeFilter) typeFilter.addEventListener('change', applyFilters);
    if (statusFilter) statusFilter.addEventListener('change', applyFilters);

    var resetBtn = document.getElementById('filter-reset');
    if (resetBtn) resetBtn.addEventListener('click', function () {
        if (search) search.value = '';
        if (typeFilter) typeFilter.value = '';
        if (statusFilter) statusFilter.value = '';
        applyFilters();
    });

    // ====== الإجراءات المدمّرة عبر نموذج POST مع CSRF ======
    var actionForm = document.getElementById('branch-action-form');
    document.querySelectorAll('.branch-action-btn').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            var url = this.getAttribute('data-url');
            var method = (this.getAttribute('data-method') || 'POST').toUpperCase();
            var text = this.getAttribute('data-confirm') || '';
            var danger = this.getAttribute('data-danger') === '1';

            Swal.fire({
                title: @json(__('branches.common.confirm_action')),
                text: text,
                icon: danger ? 'warning' : 'question',
                showCancelButton: true,
                confirmButtonColor: danger ? '#ea5455' : '#7367f0',
                cancelButtonColor: '#82868b',
                confirmButtonText: danger ? @json(__('branches.common.yes_execute')) : @json(__('branches.common.yes_proceed')),
                cancelButtonText: @json(__('branches.common.cancel')),
                reverseButtons: true
            }).then(function (r) {
                if (r.isConfirmed && actionForm) {
                    actionForm.setAttribute('action', url);
                    actionForm.querySelector('input[name="_method"]').value = method;
                    actionForm.submit();
                }
            });
        });
    });

    // ====== النقل الجماعي ======
    var bulkForm = document.getElementById('bulk-parent-form');
    if (bulkForm) {
        var bar = document.getElementById('bulk-bar');
        var counter = document.getElementById('bulk-count');
        var selectAll = document.getElementById('bulk-select-all');
        var confirmedBulk = false;

        function refreshCount() {
            var n = bulkForm.querySelectorAll('.branch-bulk-check:checked').length;
            counter.textContent = n ? @json(__('branches.tree.bulk_selected', ['count' => ':n'])).replace(':n', n) : '';
            bar.classList.toggle('is-armed', n > 0);

            bulkForm.querySelectorAll('.branch-bulk-check').forEach(function (cb) {
                var row = cb.closest('.branch-row');
                if (row) row.classList.toggle('is-picked', cb.checked);
            });
        }

        bulkForm.addEventListener('change', function (e) {
            if (e.target.classList.contains('branch-bulk-check')) refreshCount();
        });

        // النقر على أي موضع في الصفّ يحدّده — مربّع 1.15rem وحده هدف صغير
        // في جدول من ٨٦ صفاً، ويستحيل إصابته إن قصّه التمرير الأفقي.
        // تُستثنى الروابط والأزرار والقوائم حتى لا يسرق التحديد نقرةً مقصودة.
        bulkForm.addEventListener('click', function (e) {
            if (e.target.closest('a, button, .dropdown, label, input')) return;

            var row = e.target.closest('.branch-row');
            if (!row) return;

            var cb = row.querySelector('.branch-bulk-check');
            if (!cb) return;

            cb.checked = !cb.checked;
            refreshCount();
        });

        if (selectAll) selectAll.addEventListener('change', function () {
            var on = this.checked;
            // نقصر التحديد على الصفوف المرئية: تحديد صفوف مطويّة ينقل عناصر لا يراها المستخدم
            bulkForm.querySelectorAll('.branch-bulk-check').forEach(function (cb) {
                var row = cb.closest('.branch-row');
                if (row && row.style.display === 'none') return;
                cb.checked = on;
            });
            refreshCount();
        });

        bulkForm.addEventListener('submit', function (e) {
            var checked = bulkForm.querySelectorAll('.branch-bulk-check:checked');
            if (!checked.length) {
                e.preventDefault();
                Swal.fire({ icon: 'info', title: @json(__('branches.tree.bulk_none_title')),
                            text: @json(__('branches.tree.bulk_none_text')),
                            confirmButtonText: @json(__('branches.common.ok')), confirmButtonColor: '#7367f0' });
                return;
            }
            if (confirmedBulk) return;

            e.preventDefault();
            var sel = document.getElementById('bulk-parent-id');
            var label = sel.options[sel.selectedIndex].text.replace(/—/g, '').trim();
            Swal.fire({
                title: @json(__('branches.tree.bulk_confirm_title')),
                html: @json(__('branches.tree.bulk_confirm_html', ['count' => ':n', 'parent' => ':p']))
                          .replace(':n', checked.length).replace(':p', label)
                      + '<br><span class="text-muted" style="font-size:.85rem">' + @json(__('branches.tree.bulk_confirm_note')) + '</span>',
                icon: 'question', showCancelButton: true,
                confirmButtonText: @json(__('branches.tree.bulk_confirm_ok')), cancelButtonText: @json(__('branches.common.cancel')),
                confirmButtonColor: '#7367f0', cancelButtonColor: '#82868b', reverseButtons: true
            }).then(function (r) {
                if (r.isConfirmed) { confirmedBulk = true; bulkForm.submit(); }
            });
        });

        refreshCount();
    }
})();
</script>
@endsection
