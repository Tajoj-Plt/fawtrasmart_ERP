@extends('branches::layouts.master')

@section('title', __('branches.merge.transfer_title'))

@section('css')
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
@stop

@section('content')
    @php
        use App\Constants\planFeatures\BranchesPlanFeatures;
        use App\Support\PlanLock;

        /*
            التبويبان فعلان مختلفان بصلاحيتين مختلفتين في المتحكّم:
            «نقل عملاء» تحرسها branches.groups.transfer_clients، و«دمج
            مجموعتين» تحرسها branches.groups.merge. عرضُهما معاً لكل من
            بلغ الشاشة كان يُظهر نموذجاً يُردُّ عند الإرسال.
        */
        $canTransfer = hasPermission('branches.groups.transfer_clients');
        $canMerge = hasPermission('branches.groups.merge');
        $lockMerge = PlanLock::locked(BranchesPlanFeatures::MERGE);
    @endphp

    <x-branches::page-head
        icon="users"
        :title="__('branches.merge.transfer_title')"
        :subtitle="__('branches.merge.transfer_subtitle')"
        :crumbs="[__('branches.tree.breadcrumb') => route('branches.index'), __('branches.tree.move_clients') => null]">
        <x-slot:actions>
            @can('branches.groups.view')
                <a href="{{ route('groups.merge_logs') }}" class="btn btn-outline-primary">
                    <i class="feather icon-rotate-ccw"></i> {{ __('branches.merge.logs_link') }}
                </a>
            @endcan
            @can('branches.view')
                <a href="{{ route('branches.index') }}" class="btn btn-outline-secondary">
                    <i class="feather icon-git-branch"></i> {{ __('branches.show.the_tree') }}
                </a>
            @endcan
        </x-slot:actions>
    </x-branches::page-head>

    <div class="content-body">
        @include('layouts.alerts.success')
        @include('layouts.alerts.error')

        {{--
            التبويبان على الصفحة مباشرةً، وكلُّ تبويبٍ نموذجٌ بهيئة نماذج النظام:
            بطاقةٌ للمصدر وبطاقةٌ للهدف، فذيلُ النموذج بأزراره.

            كانت بطاقةً كبيرةً تحمل التبويبين وفي جوفها صندوقان لهما حدودُهما
            وظلُّهما — بطاقةٌ داخل بطاقة — وبينهما عمودٌ لا يحمل إلّا سهماً.
        --}}
        @if ($canTransfer || $canMerge)
            <ul class="nav bx-tabs" id="mergeTabs" role="tablist">
                @if ($canTransfer)
                    <li class="nav-item">
                        <a class="nav-link active" id="transfer-tab" data-bs-toggle="tab" href="#transfer-panel" role="tab">
                            <i class="feather icon-users"></i> {{ __('branches.merge.tab_transfer') }}
                        </a>
                    </li>
                @endif
                @if ($canMerge)
                    <li class="nav-item {{ $lockMerge ? 'stk-plan-locked' : '' }}">
                        <a class="nav-link {{ $canTransfer ? '' : 'active' }}" id="merge-tab" data-bs-toggle="tab" href="#merge-panel" role="tab">
                            <i class="feather icon-git-merge"></i> {{ __('branches.merge.tab_merge') }}
                            @if ($lockMerge) {!! PlanLock::menuBadge() !!} @endif
                        </a>
                    </li>
                @endif
            </ul>
        @endif

        <div class="tab-content" id="mergeTabsContent">

            {{-- ═════ نقل عملاء محدّدين ═════ --}}
            @if ($canTransfer)
            <div class="tab-pane fade show active" id="transfer-panel" role="tabpanel">
                <x-branches::explain :title="__('branches.merge.transfer_explain_title')" :open="true">
                    {!! __('branches.merge.transfer_explain', [
                        'strong' => '<strong>' . e(__('branches.merge.transfer_explain_strong')) . '</strong>',
                    ]) !!}
                </x-branches::explain>

                <form id="transfer-form" action="{{ route('groups.transfer_clients') }}" method="POST">
                    @csrf
                    <div id="transfer-error-message" class="alert alert-danger error-message" role="alert"></div>

                    <div class="row bx-sides">
                        <div class="col-lg-6 col-12">
                            <div class="card bx-side bx-side--warning">
                                <div class="card-header">
                                    <h4 class="card-title">
                                        <i class="feather icon-log-out"></i> {{ __('branches.merge.from_source') }}
                                    </h4>
                                </div>
                                <div class="card-body">
                                    <input type="hidden" name="source_group_id" id="transfer_source_group_id" required>
                                    <div class="row" id="transferSourceContainer"></div>

                                    <div class="bx-picked" style="display:none">
                                        <i class="feather icon-map-pin"></i>
                                        <span id="transfer-source-path"></span>
                                    </div>

                                    <div id="clients-container" style="display:none">
                                        <div class="search-box">
                                            <input type="text" id="search-clients" class="form-control"
                                                   placeholder="{{ __('branches.merge.clients_search') }}">
                                        </div>
                                        <div class="clients-list-container">
                                            <div class="select-all-wrapper">
                                                <div class="custom-control custom-checkbox">
                                                    <input type="checkbox" class="custom-control-input" id="select-all">
                                                    <label class="custom-control-label" for="select-all">{{ __('branches.merge.select_all') }}</label>
                                                </div>
                                                <span class="sl-count">
                                                    {{ __('branches.merge.selected_count', ['count' => '']) }}<span id="selected-count">0</span>
                                                </span>
                                            </div>
                                            <div id="clients-list"></div>
                                        </div>
                                    </div>

                                    <div id="no-clients-message" class="empty-state" style="display:none">
                                        <i class="feather icon-users"></i>
                                        <p>{{ __('branches.merge.no_clients') }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-6 col-12">
                            <div class="card bx-side bx-side--success">
                                <div class="card-header">
                                    <h4 class="card-title">
                                        <i class="feather icon-log-in"></i> {{ __('branches.merge.to_target') }}
                                    </h4>
                                </div>
                                <div class="card-body">
                                    <input type="hidden" name="target_group_id" id="transfer_target_group_id" required>
                                    <div class="row" id="transferTargetContainer"></div>

                                    <div class="bx-picked" style="display:none">
                                        <i class="feather icon-map-pin"></i>
                                        <span id="transfer-target-path"></span>
                                    </div>

                                    <div id="transfer-target-info" class="bx-mini" style="display:none">
                                        <div class="bx-mini__item">
                                            <span class="bx-mini__label">{{ __('branches.merge.current_clients') }}</span>
                                            <span class="bx-mini__value" id="transfer-target-current">0</span>
                                        </div>
                                        <div class="bx-mini__item">
                                            <span class="bx-mini__label">{{ __('branches.merge.after_transfer') }}</span>
                                            <span class="bx-mini__value bx-tone--success" id="transfer-target-after">0</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="transfer-summary" style="display:none">
                        <div class="alert alert-success" role="alert">
                            <i class="feather icon-check-circle"></i>
                            {!! __('branches.merge.transfer_summary', ['count' => '<strong id="summary-count">0</strong>']) !!}
                        </div>
                    </div>

                    <div class="sl-form-foot">
                        <div class="d-flex flex-wrap" style="gap:.5rem">
                            @can('branches.view')
                                <a href="{{ route('branches.index') }}" class="btn btn-outline-secondary">
                                    <i class="feather icon-x"></i> {{ __('branches.common.cancel') }}
                                </a>
                            @endcan
                            <button type="submit" class="btn btn-primary" id="transfer-btn" disabled>
                                <i class="feather icon-send"></i> {{ __('branches.merge.transfer_action') }}
                            </button>
                        </div>
                    </div>
                </form>
            </div>
            @endif

            {{-- ═════ ضم مجموعة كاملة ═════ --}}
            @if ($canMerge)
            <div class="tab-pane fade {{ $canTransfer ? '' : 'show active' }}" id="merge-panel" role="tabpanel">
                <x-branches::explain tone="warning" :title="__('branches.merge.merge_explain_title')">
                    <ul class="mb-0">
                        <li>{!! __('branches.merge.merge_explain_1', ['strong' => '<strong>' . e(__('branches.merge.merge_explain_all')) . '</strong>']) !!}</li>
                        <li>{!! __('branches.merge.merge_explain_2', ['strong' => '<strong>' . e(__('branches.merge.merge_explain_deleted')) . '</strong>']) !!}</li>
                        <li>{{ __('branches.merge.merge_explain_3') }}</li>
                    </ul>
                </x-branches::explain>

                <form id="merge-form" action="{{ route('groups.merge_groups_bulk') }}" method="POST">
                    @csrf
                    <div id="merge-error-message" class="alert alert-danger error-message" role="alert"></div>

                    <div class="row bx-sides">
                        <div class="col-lg-6 col-12">
                            <div class="card bx-side bx-side--danger">
                                <div class="card-header">
                                    <h4 class="card-title">
                                        <i class="feather icon-folder-minus"></i> {{ __('branches.merge.merge_source') }}
                                    </h4>
                                </div>
                                <div class="card-body">
                                    <input type="hidden" name="source_group_id" id="source_group_id" required>
                                    <div class="row" id="mergeSourceContainer"></div>
                                    <dl id="source-info" class="sl-def bx-side__facts" style="display:none">
                                        <div class="sl-def__row"><dt>{{ __('branches.merge.node_path') }}</dt><dd id="source-branch"></dd></div>
                                        <div class="sl-def__row"><dt>{{ __('branches.merge.node_kind') }}</dt><dd id="source-direction"></dd></div>
                                        <div class="sl-def__row"><dt>{{ __('branches.merge.node_descendants') }}</dt><dd id="source-descendants">0</dd></div>
                                        <div class="sl-def__row">
                                            <dt>{{ __('branches.merge.node_clients') }}</dt>
                                            <dd><strong class="bx-tone--primary" id="source-count"></strong></dd>
                                        </div>
                                    </dl>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-6 col-12">
                            <div class="card bx-side bx-side--info">
                                <div class="card-header">
                                    <h4 class="card-title">
                                        <i class="feather icon-folder-plus"></i> {{ __('branches.merge.target_place') }}
                                    </h4>
                                </div>
                                <div class="card-body">
                                    <input type="hidden" name="target_group_id" id="target_group_id" required>
                                    <div class="row" id="mergeTargetContainer"></div>
                                    <dl id="target-info" class="sl-def bx-side__facts" style="display:none">
                                        <div class="sl-def__row"><dt>{{ __('branches.merge.node_path') }}</dt><dd id="target-branch"></dd></div>
                                        <div class="sl-def__row"><dt>{{ __('branches.merge.node_kind') }}</dt><dd id="target-direction"></dd></div>
                                        <div class="sl-def__row"><dt>{{ __('branches.merge.node_descendants') }}</dt><dd id="target-descendants">0</dd></div>
                                        <div class="sl-def__row">
                                            <dt>{{ __('branches.merge.current_clients') }}</dt>
                                            <dd><strong class="bx-tone--success" id="target-count"></strong></dd>
                                        </div>
                                    </dl>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-body">
                            {{-- حذفُ المصدر خيارٌ صريح لا أثرٌ لازم للنقل: كان يقع
                                 دائماً، فمن أراد نقل عملاء من فرعٍ إلى آخر يفقد
                                 الفرع نفسَه. --}}
                            <label class="stk-switch mb-0">
                                <input type="hidden" name="delete_source" value="0">
                                <input type="checkbox" name="delete_source" value="1">
                                <span class="stk-switch__track"><span class="stk-switch__thumb"></span></span>
                                <span class="stk-switch__label">
                                    <span class="stk-switch__title">{{ __('branches.merge.delete_source') }}</span>
                                    <span class="stk-switch__hint">{{ __('branches.merge.delete_source_hint') }}</span>
                                </span>
                            </label>
                        </div>
                    </div>

                    <div class="sl-form-foot">
                        <div class="d-flex flex-wrap" style="gap:.5rem">
                            @can('branches.view')
                                <a href="{{ route('branches.index') }}" class="btn btn-outline-secondary">
                                    <i class="feather icon-x"></i> {{ __('branches.common.cancel') }}
                                </a>
                            @endcan
                            <button type="submit" class="btn btn-danger" id="merge-btn" disabled
                                    @if ($lockMerge) title="{{ PlanLock::reason(BranchesPlanFeatures::MERGE) }}" @endif>
                                <i class="feather icon-{{ $lockMerge ? 'lock' : 'git-merge' }}"></i> {{ __('branches.merge.merge_action') }}
                            </button>
                        </div>
                    </div>
                </form>
            </div>
            @endif

            {{-- لا صلاحيةَ لأيّ تبويب: الشاشة تُبلغ ولا تُترك فارغة --}}
            @unless ($canTransfer || $canMerge)
                <div class="card">
                    <div class="card-body">
                        <x-branches::empty icon="lock" :title="__('branches.merge.no_permission')"
                                           :text="__('branches.merge.no_permission_hint')" />
                    </div>
                </div>
            @endunless

        </div>
    </div>
@endsection


@section('scripts')
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        $(document).ready(function() {
            // بيانات الفروع والمجموعات
            // الشجرةُ كاملةً: كلُّ عقدةٍ صالحةٌ مصدراً وهدفاً، بلا تصفيةٍ بالنوع.
            // (`CASCADE_GROUPS` و`groupsData` حُذفا — الأول تكرارٌ لصفوفٍ هي
            //  أصلاً في `branches`، والثاني متغيّرٌ ميت لم يُقرأ قط.)
            const CASCADE_BRANCHES = @json($cascadeBranches ?? []);
            // تسميةُ كل نوع من مصدرها الواحد في النظام — لا «مجموعة» تُلصق
            // على كل ما ليس فرعاً
            const NODE_TYPE_LABELS = @json($nodeTypeLabels ?? []);

            let selectedClients = [];

            // ===========================================================
            // شجرة موحدة: كل عنصر (فرع أو مجموعة) له key واحدة وparentKey واحدة
            // ===========================================================

            // بناء قائمة العقد الموحدة (فروع + مجموعات)
            var TREE_NODES = [];
            var _seenKeys      = {}; // تجنب تكرار ID
            var _seenNameLevel = {}; // تجنب تكرار نفس الاسم في نفس المستوى

            CASCADE_BRANCHES.forEach(function(b) {
                var key = 'b_' + b.id;
                if (_seenKeys[key]) return;
                _seenKeys[key] = true;
                var pid = parseInt(b.parent_id) || 0;
                var parentKey = pid > 0 ? ('b_' + pid) : null;
                // تجنب تكرار نفس الاسم في نفس المستوى
                var nameKey = (b.name || '').trim().toLowerCase() + '|' + (parentKey || '');
                if (_seenNameLevel[nameKey]) return;
                _seenNameLevel[nameKey] = true;
                TREE_NODES.push({
                    key: key,
                    name: b.name,
                    parentKey: parentKey,
                    // نوعُ العرض أوّلاً: المناطقُ والمنافذُ القديمة مخزَّنةٌ «مجموعة»
                    node_type: b.display_type || b.node_type || (b.is_territory ? 'group' : 'branch'),
                    is_territory: b.is_territory == 1 || b.is_territory == '1' || b.is_territory === true
                });
            });

            // جلب الجذور (لا أب لها)
            function getRootNodes() {
                return TREE_NODES.filter(function(n) { return n.parentKey === null; });
            }

            // جلب أبناء عقدة بـ key معين
            function getChildNodes(parentKey) {
                return TREE_NODES.filter(function(n) { return n.parentKey === parentKey; });
            }

            /* مفاتيحُ عقدةٍ وكلِّ ما تحتها — تُستعمل لاستبعاد المصدر وتوابعه من
               قائمة الهدف: لا يُنقل شيءٌ إلى نفسه ولا إلى ابنٍ من أبنائه، فذلك
               ليس نقلاً. وكان الخادم يردّه بعد ملء النموذج كلّه، والشاشةُ
               تعرضه كأنّه خيارٌ صالح. */
            function subtreeKeys(key) {
                if (!key) return [];
                var out = [key];
                var frontier = [key];
                var guard = 0;
                while (frontier.length && guard++ < 100) {
                    var next = [];
                    frontier.forEach(function (k) {
                        getChildNodes(k).forEach(function (c) {
                            out.push(c.key);
                            next.push(c.key);
                        });
                    });
                    frontier = next;
                }
                return out;
            }

            function nodeHasChildren(key) {
                return TREE_NODES.some(function(n) { return n.parentKey === key; });
            }

            // فهرس سريع بالمفتاح — يُستعمل في بناء المسار وقراءة النوع
            var NODE_BY_KEY = {};
            TREE_NODES.forEach(function(n) { NODE_BY_KEY[n.key] = n; });

            /*
             * كل العقد تحمل البادئة `b_` (وحّدها البناء أعلاه: المجموعات صارت
             * صفوفاً في `branches`). لذلك كل اختبار `startsWith('g_')` كان
             * يعطي `false` دائماً — فبقيت لوحة البيان تعرض «غير محدد» أبداً،
             * وأرجعت `getDescendantGroups` مصفوفة فارغة في كل الحالات.
             * التمييز الصحيح الآن بالحقل `is_territory`.
             */
            function nodeTypeLabelOf(key) {
                var n = NODE_BY_KEY[key];
                if (!n) return '';

                return NODE_TYPE_LABELS[n.node_type] || '';
            }

            /** مسار العقدة من الجذر: «فرع الرياض ← شرق الرياض ← اشبيليا» */
            function pathOf(key) {
                var names = [];
                var guard = 0;
                while (key && NODE_BY_KEY[key] && guard++ < 50) {
                    names.unshift(NODE_BY_KEY[key].name);
                    key = NODE_BY_KEY[key].parentKey;
                }
                return names.join(' ← ');
            }

            /** عدد كل العقد تحت عقدة، على أي عمق */
            function descendantCount(key) {
                var total = 0;
                (function recurse(k) {
                    getChildNodes(k).forEach(function(child) { total++; recurse(child.key); });
                })(key);
                return total;
            }

            // ===========================================================
            // دالة بناء شجرة الفلاتر التفاعلية (Cascade Selects)
            // ===========================================================
            /* لا قيدَ على نوع ما يُختار: أيُّ عقدةٍ مصدراً وأيُّ عقدةٍ هدفاً.
               (كان هنا وسيطُ `forceGroupSelectionOnly` يُمرَّر `false` في كل
               النداءات الأربعة — قيدٌ ميت أُسقط.) */
            function initCascade(containerId, hiddenInputId, onChangeCallback, isExcluded) {
                var $container = $(containerId);
                var $hiddenInput = $(hiddenInputId);

                function addLevel(level, parentKey, selectedKey) {
                    var nodes = (level === 0) ? getRootNodes() : getChildNodes(parentKey);
                    if (isExcluded) {
                        nodes = nodes.filter(function (n) { return !isExcluded(n.key); });
                    }
                    if (!nodes.length) return false;

                    // تسمية تقول ما هذا المستوى فعلاً: «أبناء فرع الرياض»
                    // أوضح بكثير من «المستوى الفرعي ٢».
                    var labelText = (level === 0)
                        ? @json(__('branches.merge.cascade_root_level'))
                        : @json(__('branches.merge.cascade_children_of', ['name' => ':n']))
                            .replace(':n', NODE_BY_KEY[parentKey] ? NODE_BY_KEY[parentKey].name : '');

                    var $col = $('<div class="col-md-12 mb-2 cascade-col"></div>');
                    $col.append($('<label></label>').text(labelText));

                    var $select = $('<select class="form-control select2 cascade-select" data-level="' + level + '"></select>');
                    $select.append($('<option></option>').attr('value', '').text(@json(__('branches.common.select'))));

                    nodes.forEach(function(node) {
                        // عدد الأبناء في التسمية يقول للمستخدم أيّ خيار يفتح
                        // مستوىً أعمق وأيّها طرفيّ — قبل أن يجرّبها واحداً واحداً.
                        var kids = getChildNodes(node.key).length;
                        // النوعُ الحقيقيّ: «منطقة» تُكتب منطقة، و«منفذ» منفذاً.
                        // كان كلُّ ما ليس فرعاً يُكتب «مجموعة» فيقرأ المستخدم
                        // «منطقة الرياض — مجموعة» ويظنّ الاختيار مقصوراً عليها.
                        var typeLabel = NODE_TYPE_LABELS[node.node_type] || '';
                        var nameText = node.name
                            + (typeLabel ? ' — ' + typeLabel : '')
                            + (kids ? ' ' + @json(__('branches.merge.cascade_under', ['count' => ':n'])).replace(':n', kids) : '');
                        var $opt = $('<option></option>').attr('value', node.key).text(nameText);
                        if (selectedKey === node.key) { $opt.attr('selected', 'selected'); }
                        $select.append($opt);
                    });

                    $col.append($select);
                    $container.append($col);

                    if ($.fn.select2) {
                        $select.select2({
                            dir: 'rtl',
                            language: { noResults: function () { return @json(__('branches.common.no_results')); } },
                            allowClear: true,
                            width: '100%'
                        });
                    }

                    $select.on('change', function() {
                        var val = $(this).val();

                        // إزالة المستويات الأعمق
                        $container.find('.cascade-col').each(function() {
                            var lvl = parseInt($(this).find('select').attr('data-level'), 10);
                            if (lvl > level) {
                                var $s = $(this).find('select');
                                if ($.fn.select2 && $s.length && $s.data('select2')) {
                                    $s.select2('destroy');
                                }
                                $(this).remove();
                            }
                        });

                        var finalVal = "";
                        if (val) {
                            finalVal = val;
                            $hiddenInput.val(finalVal);

                            if (nodeHasChildren(val)) {
                                addLevel(level + 1, val, null);
                            }
                        } else {
                            // التوقّفُ في مستوًى يعني الاكتفاءَ بالعقدة الأعلى
                            var $prev = $container.find('select[data-level="' + (level - 1) + '"]');
                            finalVal = $prev.length ? ($prev.val() || '') : '';
                            $hiddenInput.val(finalVal);
                        }

                        if (onChangeCallback) {
                            onChangeCallback($hiddenInput.val());
                        }
                    });

                    return true;
                }

                // تفريغ المكونات وإعادة البناء
                $container.find('.cascade-col select').each(function() {
                    var $s = $(this);
                    if ($.fn.select2 && $s.data('select2')) { $s.select2('destroy'); }
                });
                function rebuild() {
                    $container.find('.cascade-col select').each(function() {
                        var $s = $(this);
                        if ($.fn.select2 && $s.data('select2')) { $s.select2('destroy'); }
                    });
                    $container.empty();
                    $hiddenInput.val('');
                    addLevel(0, null, null);
                    if (onChangeCallback) { onChangeCallback(''); }
                }

                $container.empty();
                $hiddenInput.val('');

                addLevel(0, null, null);

                return { rebuild: rebuild };
            }

            // ===========================================================
            // تهيئة الفلاتر للتاب الأول والثاني
            // ===========================================================

            /* المصدرُ وتوابعُه لا يظهرون في قائمة الهدف: نقلُ شيءٍ إلى نفسه أو
               إلى أحد أبنائه ليس نقلاً. وتُعاد بناءُ قائمة الهدف عند كل تغييرٍ
               للمصدر كي تُحسب الاستبعادات من جديد. */
            var mergeTargetCascade = null;
            var transferTargetCascade = null;

            function excludedBy(hiddenInputId) {
                return function (key) {
                    var src = $(hiddenInputId).val();
                    if (!src) return false;

                    return subtreeKeys(src).indexOf(key) !== -1;
                };
            }

            // تهيئة تاب 1: نقل كل العملاء
            function initTab1() {
                initCascade('#mergeSourceContainer', '#source_group_id', function(val) {
                    if (val) {
                        $('#source-count').text('…');
                        $('#source-info').slideDown();

                        // المسار الكامل بدل «الفرع/الاتجاه» التي كانت تبقى
                        // «غير محدد» أبداً لأن شرطها لا يتحقّق في أي حال.
                        $('#source-branch').text(pathOf(val));
                        $('#source-direction').text(
                            nodeTypeLabelOf(val)
                        );
                        $('#source-descendants').text(descendantCount(val));

                        $.ajax({
                            url: '{{ route("groups.get_clients_by_node", "") }}/' + val,
                            type: 'GET',
                            success: function(allClients) {
                                $('#source-count').text(allClients ? allClients.length : 0);
                                validateMergeForm();
                            },
                            error: function() {
                                $('#source-count').text(@json(__('branches.merge.load_error')));
                            }
                        });
                    } else {
                        $('#source-info').slideUp();
                    }
                    // الهدفُ يُعاد بناؤه بعد تغيّر المصدر — ما كان صالحاً قد لا يبقى
                    if (mergeTargetCascade) { mergeTargetCascade.rebuild(); }
                    validateMergeForm();
                });

                mergeTargetCascade = initCascade('#mergeTargetContainer', '#target_group_id', function(val) {
                    if (val) {
                        $('#target-count').text('…');
                        $('#target-info').slideDown();

                        $('#target-branch').text(pathOf(val));
                        $('#target-direction').text(
                            nodeTypeLabelOf(val)
                        );
                        $('#target-descendants').text(descendantCount(val));

                        $.ajax({
                            url: '{{ route("groups.get_clients_by_node", "") }}/' + val,
                            type: 'GET',
                            success: function(allClients) {
                                $('#target-count').text(allClients ? allClients.length : 0);
                                validateMergeForm();
                            },
                            error: function() {
                                $('#target-count').text(@json(__('branches.merge.load_error')));
                            }
                        });
                    } else {
                        $('#target-info').slideUp();
                    }
                    validateMergeForm();
                }, excludedBy('#source_group_id'));
            }

            // تهيئة تاب 2: نقل
            function initTab2() {
                initCascade('#transferSourceContainer', '#transfer_source_group_id', function(val) {
                    selectedClients = [];
                    updateSelectedCount();

                    if (val) {
                        // المسار المختار يبقى ظاهراً: بعد أربعة مستويات لا يعود
                        // المستخدم يذكر من أين بدأ ولا ماذا اختار في كل مستوى.
                        $('#transfer-source-path').text(pathOf(val)).parent().slideDown();
                        $('#clients-list').html(
                            '<div class="empty-state"><i class="feather icon-loader"></i><p>' + @json(__('branches.common.loading')) + '</p></div>'
                        );
                        $('#clients-container').slideDown();
                        $('#no-clients-message').hide();

                        $.ajax({
                            url: '{{ route("groups.get_clients_by_node", "") }}/' + val,
                            type: 'GET',
                            success: function(allClients) {
                                if (allClients && allClients.length > 0) {
                                    renderClientsList(allClients);
                                    $('#no-clients-message').hide();
                                } else {
                                    $('#clients-container').slideUp();
                                    $('#no-clients-message').slideDown();
                                }
                                validateTransferForm();
                            },
                            error: function() {
                                $('#clients-container').slideUp();
                                Swal.fire(@json(__('branches.merge.error')), @json(__('branches.merge.clients_load_error')), 'error');
                            }
                        });
                    } else {
                        $('#transfer-source-path').parent().slideUp();
                        $('#clients-container').slideUp();
                        $('#no-clients-message').hide();
                    }
                    if (transferTargetCascade) { transferTargetCascade.rebuild(); }
                    validateTransferForm();
                });

                transferTargetCascade = initCascade('#transferTargetContainer', '#transfer_target_group_id', function(val) {
                    if (val) {
                        $('#transfer-target-path').text(pathOf(val)).parent().slideDown();
                        $('#transfer-target-current').text('…');
                        $('#transfer-target-info').slideDown();

                        $.ajax({
                            url: '{{ route("groups.get_clients_by_node", "") }}/' + val,
                            type: 'GET',
                            success: function(allClients) {
                                const currentCount = allClients ? allClients.length : 0;
                                $('#transfer-target-current').text(currentCount);
                                updateTransferTargetAfterCount(currentCount);
                            },
                            error: function() {
                                $('#transfer-target-current').text(@json(__('branches.merge.error')));
                            }
                        });
                    } else {
                        $('#transfer-target-path').parent().slideUp();
                        $('#transfer-target-info').slideUp();
                    }
                    validateTransferForm();
                }, excludedBy('#transfer_source_group_id'));
            }

            // التشغيل الأولي
            initTab1();
            initTab2();

            // ==================== Tab 1: ضم مجموعة كاملة ====================

            function validateMergeForm() {
                const source = $('#source_group_id').val();
                const target = $('#target_group_id').val();
                $('#merge-error-message').slideUp();

                if (!source || !target) {
                    $('#merge-btn').prop('disabled', true);
                    return;
                }
                if (source === target) {
                    $('#merge-error-message').text(@json(__('branches.merge.same_source_target'))).slideDown();
                    $('#merge-btn').prop('disabled', true);
                } else {
                    $('#merge-btn').prop('disabled', false);
                }
            }

            $('#merge-form').on('submit', function(e) {
                e.preventDefault();
                const sourceVal = $('#source_group_id').val();
                const targetVal = $('#target_group_id').val();
                if (!sourceVal || !targetVal) return;

                const sourceNode = TREE_NODES.find(n => n.key === sourceVal);
                const targetNode = TREE_NODES.find(n => n.key === targetVal);
                const sourceName = sourceNode ? sourceNode.name : '';
                const targetName = targetNode ? targetNode.name : '';
                const sourceCount = $('#source-count').text() || 0;
                const deleting = $('#merge-form input[type="checkbox"][name="delete_source"]').is(':checked');

                Swal.fire({
                    title: @json(__('branches.merge.merge_confirm_title')),
                    html: bxConfirm.body(
                        @json(__('branches.merge.merge_confirm_q2', ['source' => ':s', 'target' => ':t']))
                            .replace(':s', sourceName).replace(':t', targetName),
                        [
                            { label: @json(__('branches.merge.from_source')), value: pathOf(sourceVal) },
                            { label: @json(__('branches.merge.to_target')), value: pathOf(targetVal) },
                            { label: @json(__('branches.merge.node_clients')), value: String(sourceCount) }
                        ],
                        deleting ? @json(__('branches.merge.confirm_delete_note')) : @json(__('branches.merge.merge_confirm_note'))
                    ),
                    icon: 'warning',
                    showCancelButton: true,
                    reverseButtons: true,
                    confirmButtonColor: bxConfirm.danger(),
                    cancelButtonColor: bxConfirm.muted,
                    confirmButtonText: @json(__('branches.merge.merge_confirm_ok')),
                    cancelButtonText: @json(__('branches.common.cancel')),
                }).then((result) => {
                    if (result.isConfirmed) { this.submit(); }
                });
            });

            // ==================== Tab 2: نقل عملاء محددين ====================

            // الخادم صار يرسل العميل نفسه لا صفَّ حيّه: `id` معرّف العميل،
            // ومعه موضعُه الحاليّ كي يعرف الناقلُ من أين يتحرّك كلُّ اسم
            function renderClientsList(clients) {
                let html = '';
                clients.forEach(client => {
                    const clientCode = client.code || client.id || '-';
                    const tradeName = client.trade_name || @json(__('branches.merge.client_fallback', ['id' => ':i'])).replace(':i', client.id);
                    const at = client.node_name
                        ? `<div class="client-code">${client.node_name}${client.node_type_label ? ' — ' + client.node_type_label : ''}</div>`
                        : '';

                    html += `
                        <div class="client-item" data-id="${client.id}" data-name="${tradeName}" data-code="${clientCode}">
                            <input type="checkbox" name="client_ids[]" value="${client.id}" class="client-checkbox">
                            <div class="client-info-wrapper">
                                <div class="client-name">${tradeName}</div>
                                <div class="client-code">${@json(__('branches.merge.client_code'))}: ${clientCode}</div>
                                ${at}
                            </div>
                        </div>
                    `;
                });
                if (!html) {
                    html = '<div class="empty-state"><i class="feather icon-users"></i><p>' + @json(__('branches.merge.no_client_data')) + '</p></div>';
                }
                $('#clients-list').html(html);
                $('#select-all').prop('checked', false);
            }

            $(document).on('change', '.client-checkbox', function() {
                const clientItem = $(this).closest('.client-item');
                const clientId = clientItem.data('id');

                if ($(this).is(':checked')) {
                    clientItem.addClass('selected');
                    if (!selectedClients.includes(clientId)) {
                        selectedClients.push(clientId);
                    }
                } else {
                    clientItem.removeClass('selected');
                    selectedClients = selectedClients.filter(id => id != clientId);
                }
                updateSelectedCount();
                validateTransferForm();
            });

            $(document).on('click', '.client-item', function(e) {
                if (!$(e.target).is('input')) {
                    const checkbox = $(this).find('.client-checkbox');
                    checkbox.prop('checked', !checkbox.prop('checked')).trigger('change');
                }
            });

            $('#select-all').on('change', function() {
                const isChecked = $(this).is(':checked');
                $('.client-checkbox').prop('checked', isChecked).trigger('change');
            });

            $('#search-clients').on('input', function() {
                const searchTerm = $(this).val().toLowerCase();
                $('.client-item').each(function() {
                    const clientName = $(this).find('.client-name').text().toLowerCase();
                    const clientCode = $(this).data('code')?.toString().toLowerCase() || '';
                    $(this).toggle(clientName.includes(searchTerm) || clientCode.includes(searchTerm));
                });
            });

            function updateSelectedCount() {
                $('#selected-count').text(selectedClients.length);
                $('#summary-count').text(selectedClients.length);
                updateTransferTargetAfterCount();
            }

            function updateTransferTargetAfterCount(currentCount = null) {
                if (currentCount === null) {
                    currentCount = parseInt($('#transfer-target-current').text()) || 0;
                }
                const afterCount = currentCount + selectedClients.length;
                $('#transfer-target-after').text(afterCount);
            }

            function validateTransferForm() {
                const sourceId = $('#transfer_source_group_id').val();
                const targetId = $('#transfer_target_group_id').val();
                const hasSelectedClients = selectedClients.length > 0;

                if (sourceId && targetId && hasSelectedClients && sourceId !== targetId) {
                    $('#transfer-btn').prop('disabled', false);
                    $('#transfer-summary').slideDown();
                } else {
                    $('#transfer-btn').prop('disabled', true);
                    $('#transfer-summary').slideUp();
                }
            }

            $('#transfer-form').on('submit', function(e) {
                e.preventDefault();
                const targetVal = $('#transfer_target_group_id').val();
                if (!targetVal) return;

                const sourceVal = $('#transfer_source_group_id').val();
                const count = selectedClients.length;

                Swal.fire({
                    title: @json(__('branches.merge.transfer_confirm_title')),
                    html: bxConfirm.body(
                        @json(__('branches.merge.transfer_summary', ['count' => ':n'])).replace(':n', count),
                        [
                            { label: @json(__('branches.merge.from_source')), value: sourceVal ? pathOf(sourceVal) : '' },
                            { label: @json(__('branches.merge.to_target')), value: pathOf(targetVal) }
                        ],
                        @json(__('branches.merge.transfer_note'))
                    ),
                    icon: 'question',
                    showCancelButton: true,
                    reverseButtons: true,
                    confirmButtonColor: bxConfirm.primary(),
                    cancelButtonColor: bxConfirm.muted,
                    confirmButtonText: @json(__('branches.merge.transfer_ok')),
                    cancelButtonText: @json(__('branches.common.cancel')),
                }).then((result) => {
                    if (result.isConfirmed) {
                        Swal.fire({
                            title: @json(__('branches.merge.transfer_running')),
                            allowOutsideClick: false,
                            didOpen: () => { Swal.showLoading(); }
                        });
                        this.submit();
                    }
                });
            });
        });
    </script>
@endsection
