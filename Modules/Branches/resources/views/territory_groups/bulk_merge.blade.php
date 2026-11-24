@extends('branches::layouts.master')
@section('title', __('branches.merge.bulk_title'))


@section('content')
    @php
        use App\Constants\planFeatures\BranchesPlanFeatures;
        use App\Support\PlanLock;

        $lockMerge = PlanLock::locked(BranchesPlanFeatures::MERGE);
    @endphp

    {{--
        الشاشةُ على هيئة نماذج النظام: بطاقةٌ للمصدر وبطاقةٌ للهدف متجاورتان،
        فبطاقةُ الخيار، فذيلُ النموذج بأزراره.

        كانت بطاقةً كبيرةً في جوفها صندوقان لهما حدودُهما وظلُّهما — بطاقةٌ
        داخل بطاقة — وبينهما عمودٌ بسدس العرض لا يحمل إلّا سهماً، وذيلٌ
        واحدٌ يتزاحم فيه الشرحُ ومفتاحُ الحذف والزرّان.
    --}}
    <x-branches::page-head
        icon="minimize-2"
        :title="__('branches.merge.bulk_title')"
        :subtitle="__('branches.merge.bulk_subtitle')"
        :crumbs="[__('branches.tree.breadcrumb') => route('branches.index'), __('branches.nav.bulk_merge') => null]">
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

    <div class="content-body bulk-merge-container">
        @include('layouts.alerts.success')
        @include('layouts.alerts.error')

        <x-branches::explain tone="warning" :title="__('branches.merge.bulk_read_first')">
            <ul class="mb-0">
                <li>{!! __('branches.merge.bulk_warn_1', ['strong' => '<strong>' . e(__('branches.merge.merge_explain_all')) . '</strong>']) !!}</li>
                <li>{!! __('branches.merge.bulk_warn_2', ['strong' => '<strong>' . e(__('branches.merge.merge_explain_deleted')) . '</strong>']) !!}</li>
                <li>{{ __('branches.merge.bulk_warn_3') }}</li>
            </ul>
        </x-branches::explain>

        <form id="bulk-merge-form" action="{{ route('groups.bulk_merge') }}" method="POST">
            @csrf

            <div class="row bx-sides">
                {{-- ═══ المصدر ═══ --}}
                <div class="col-lg-6 col-12">
                    <div class="card bx-side bx-side--danger">
                        <div class="card-header">
                            <h4 class="card-title">
                                <i class="feather icon-folder-minus"></i> {{ __('branches.merge.bulk_sources') }}
                            </h4>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label for="source_groups">{{ __('branches.merge.bulk_sources_label') }}</label>
                                {{-- الخيارات تُبنى بجافاسكربت من شجرة الفروع نفسها التي
                                     تستعملها شاشة «ضم ونقل عملاء»، بمسار كامل لكل عنصر. --}}
                                <select name="source_groups[]" id="source_groups"
                                        class="form-control select2" multiple="multiple" required></select>
                                <small class="sl-help">{{ __('branches.merge.bulk_sources_hint') }}</small>
                            </div>

                            <div class="selected-groups-wrapper" id="selected-groups-wrapper">
                                <div class="empty-state">
                                    <i class="feather icon-mouse-pointer"></i>
                                    <p>{{ __('branches.merge.bulk_nothing_picked') }}</p>
                                </div>
                            </div>

                            <div class="bx-mini">
                                <div class="bx-mini__item">
                                    <span class="bx-mini__label">{{ __('branches.merge.bulk_groups_count') }}</span>
                                    <span class="bx-mini__value" id="selected-count">0</span>
                                </div>
                                <div class="bx-mini__item">
                                    <span class="bx-mini__label">{{ __('branches.merge.bulk_clients_total') }}</span>
                                    <span class="bx-mini__value" id="total-clients">0</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ═══ الهدف ═══ --}}
                <div class="col-lg-6 col-12">
                    <div class="card bx-side bx-side--success">
                        <div class="card-header">
                            <h4 class="card-title">
                                <i class="feather icon-folder-plus"></i> {{ __('branches.merge.merge_target') }}
                            </h4>
                        </div>
                        <div class="card-body">
                            {{-- اختيار متسلسل بنفس شاشة «ضم ونقل عملاء»: تختار الأب
                                 فتظهر قائمة أبنائه، وهكذا حتى آخر مستوى. --}}
                            <input type="hidden" name="target_group_id" id="target_group_id" required>
                            <div class="row" id="bulkTargetContainer"></div>

                            <div class="bx-picked" style="display:none">
                                <i class="feather icon-map-pin"></i>
                                <span id="bulk-target-path"></span>
                            </div>

                            <dl id="target-info" class="sl-def bx-side__facts" style="display:none">
                                <div class="sl-def__row"><dt>{{ __('branches.common.name') }}</dt><dd id="target-name"></dd></div>
                                <div class="sl-def__row"><dt>{{ __('branches.merge.current_clients') }}</dt><dd id="target-clients"></dd></div>
                                <div class="sl-def__row">
                                    <dt>{{ __('branches.merge.bulk_after_merge') }}</dt>
                                    <dd><strong class="bx-tone--primary" id="target-final"></strong></dd>
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
                <small class="sl-help mb-0">
                    <i class="feather icon-info"></i> {{ __('branches.merge.bulk_footer_hint') }}
                </small>

                <div class="d-flex flex-wrap" style="gap:.5rem">
                    @can('branches.view')
                        <a href="{{ route('branches.index') }}" class="btn btn-outline-secondary">
                            <i class="feather icon-x"></i> {{ __('branches.common.cancel') }}
                        </a>
                    @endcan
                    <button type="submit" class="btn btn-primary" id="merge-btn" disabled
                            @if ($lockMerge) title="{{ PlanLock::reason(BranchesPlanFeatures::MERGE) }}" @endif>
                        <i class="feather icon-{{ $lockMerge ? 'lock' : 'git-merge' }}"></i> {{ __('branches.merge.bulk_action') }}
                    </button>
                </div>
            </div>
        </form>
    </div>
@endsection

@section('scripts')
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        $(document).ready(function() {
            /* ══════════════════════════════════════════════════════════════
               نفس شجرة «ضم ونقل عملاء» ونفس منطق الاختيار المتسلسل.

               هذه الشاشة كانت تقرأ من `region_groubs` القديم بينما المتحكّم
               يتحقّق من `branches` — جدولان مختلفان لم ينجُ الربط بينهما إلا
               لأن المعرّفات تصادف تطابقها. الآن المصدر واحد.
               ══════════════════════════════════════════════════════════════ */
            const CASCADE_BRANCHES = @json($cascadeBranches ?? []);
            const CLIENT_COUNTS    = @json($clientCounts ?? []);

            var TREE_NODES = [];
            var NODE_BY_KEY = {};
            // تسميةُ كل نوع من مصدرها الواحد — لا «مجموعة» تُلصق على كل ما ليس فرعاً
            const NODE_TYPE_LABELS = @json($nodeTypeLabels ?? []);

            CASCADE_BRANCHES.forEach(function (b) {
                var key = 'b_' + b.id;
                if (NODE_BY_KEY[key]) return;
                var pid = parseInt(b.parent_id) || 0;
                var node = {
                    key: key,
                    id: b.id,
                    name: b.name,
                    parentKey: pid > 0 ? ('b_' + pid) : null,
                    // نوعُ العرض أوّلاً: المناطقُ والمنافذُ القديمة مخزَّنةٌ «مجموعة»
                    node_type: b.display_type || b.node_type || (b.is_territory ? 'group' : 'branch'),
                    is_territory: b.is_territory == 1 || b.is_territory === true
                };
                TREE_NODES.push(node);
                NODE_BY_KEY[key] = node;
            });

            function getRootNodes()            { return TREE_NODES.filter(function (n) { return n.parentKey === null; }); }
            function getChildNodes(parentKey)  { return TREE_NODES.filter(function (n) { return n.parentKey === parentKey; }); }
            function nodeHasChildren(key)      { return TREE_NODES.some(function (n) { return n.parentKey === key; }); }
            function clientsOf(id)             { return parseInt(CLIENT_COUNTS[id]) || 0; }

            /* مفاتيحُ عقدةٍ وكلِّ ما تحتها. الهدفُ لا يجوز أن يكون واحداً من
               المصادر ولا ابناً لأحدها — نقلُ شيءٍ إلى نفسه ليس نقلاً، وكان
               الخادمُ يردّه بعد ملء النموذج بينما الشاشةُ تعرضه خياراً صالحاً. */
            function subtreeKeys(key) {
                if (!key) return [];
                var out = [key], frontier = [key], guard = 0;
                while (frontier.length && guard++ < 100) {
                    var next = [];
                    frontier.forEach(function (k) {
                        getChildNodes(k).forEach(function (c) { out.push(c.key); next.push(c.key); });
                    });
                    frontier = next;
                }
                return out;
            }

            function isExcludedTarget(key) {
                var picked = $('#source_groups').val() || [];
                for (var i = 0; i < picked.length; i++) {
                    if (subtreeKeys('b_' + picked[i]).indexOf(key) !== -1) return true;
                }
                return false;
            }

            /** مسار العقدة من الجذر: «فرع الرياض ← شرق الرياض ← اشبيليا» */
            function pathOf(key) {
                var names = [], guard = 0;
                while (key && NODE_BY_KEY[key] && guard++ < 50) {
                    names.unshift(NODE_BY_KEY[key].name);
                    key = NODE_BY_KEY[key].parentKey;
                }
                return names.join(' ← ');
            }

            /** مجموع عملاء عقدة وكل ما تحتها */
            function clientsWithDescendants(key) {
                var node = NODE_BY_KEY[key];
                if (!node) return 0;
                var total = clientsOf(node.id);
                getChildNodes(key).forEach(function (c) { total += clientsWithDescendants(c.key); });
                return total;
            }

            /* ── قائمة المصادر: كلُّ العناصر بمسارها الكامل ──
               كانت مقصورةً على `is_territory` — فالفروعُ لا تظهر أصلاً، ومن
               أراد نقلَ عملاءِ فرعٍ إلى آخر لا يجده في القائمة. النقلُ يقع
               بين أيّ عنصرين. */
            var $sources = $('#source_groups');
            TREE_NODES
                .map(function (n) { return { node: n, path: pathOf(n.key) }; })
                .sort(function (a, b) { return a.path.localeCompare(b.path, 'ar'); })
                .forEach(function (row) {
                    var count = clientsWithDescendants(row.node.key);
                    // النصّ عبر .text() لا بلصق HTML: اسم فيه < أو " كان يكسر القائمة
                    $sources.append(
                        $('<option></option>')
                            .attr('value', row.node.id)
                            .attr('data-count', count)
                            .text(
                                row.path
                                + (NODE_TYPE_LABELS[row.node.node_type] ? ' — ' + NODE_TYPE_LABELS[row.node.node_type] : '')
                                + ' — ' + @json(__('branches.merge.node_clients')) + ': ' + count
                            )
                    );
                });

            $sources.select2({
                dir: 'rtl',
                width: '100%',
                // في القائمة المسارُ كاملاً ليُعرف العنصر، وفي الشارة اسمُه وحدَه:
                // المسارُ والنوعُ والعددُ معاً كانت تفيض عن الحقل وتُقصّ
                templateSelection: function (item) {
                    var node = NODE_BY_KEY['b_' + item.id];

                    return node ? node.name : item.text;
                },
                placeholder: @json(__('branches.merge.bulk_sources_placeholder')),
                language: { noResults: function () { return @json(__('branches.common.no_results')); } }
            });

            // ── الهدف: اختيار متسلسل مطابق لشاشة «ضم ونقل عملاء» ──
            function initCascade(containerId, hiddenInputId, onChangeCallback) {
                var $container = $(containerId);
                var $hidden = $(hiddenInputId);

                function addLevel(level, parentKey) {
                    var nodes = (level === 0) ? getRootNodes() : getChildNodes(parentKey);
                    nodes = nodes.filter(function (n) { return !isExcludedTarget(n.key); });
                    if (!nodes.length) return false;

                    var labelText = (level === 0)
                        ? @json(__('branches.merge.cascade_root_level'))
                        : @json(__('branches.merge.cascade_children_of', ['name' => ':n']))
                            .replace(':n', NODE_BY_KEY[parentKey] ? NODE_BY_KEY[parentKey].name : '');

                    var $col = $('<div class="col-md-12 mb-2 cascade-col"></div>');
                    $col.append($('<label></label>').text(labelText));

                    var $select = $('<select class="form-control select2 cascade-select" data-level="' + level + '"></select>');
                    $select.append($('<option></option>').attr('value', '').text(@json(__('branches.common.select'))));

                    nodes.forEach(function (node) {
                        var kids = getChildNodes(node.key).length;
                        $select.append(
                            $('<option></option>').attr('value', node.key).text(
                                node.name
                                + (NODE_TYPE_LABELS[node.node_type] ? ' — ' + NODE_TYPE_LABELS[node.node_type] : '')
                                + (kids ? ' ' + @json(__('branches.merge.cascade_under', ['count' => ':n'])).replace(':n', kids) : '')
                            )
                        );
                    });

                    $col.append($select);
                    $container.append($col);

                    if ($.fn.select2) {
                        $select.select2({
                            dir: 'rtl', width: '100%', allowClear: true,
                            language: { noResults: function () { return @json(__('branches.common.no_results')); } }
                        });
                    }

                    $select.on('change', function () {
                        var val = $(this).val();

                        // إزالة المستويات الأعمق قبل إضافة الجديد
                        $container.find('.cascade-col').each(function () {
                            var lvl = parseInt($(this).find('select').attr('data-level'), 10);
                            if (lvl > level) {
                                var $s = $(this).find('select');
                                if ($.fn.select2 && $s.data('select2')) { $s.select2('destroy'); }
                                $(this).remove();
                            }
                        });

                        if (val) {
                            $hidden.val(NODE_BY_KEY[val] ? NODE_BY_KEY[val].id : '');
                            if (nodeHasChildren(val)) { addLevel(level + 1, val); }
                        } else {
                            var $prev = $container.find('select[data-level="' + (level - 1) + '"]');
                            var prevVal = $prev.length ? ($prev.val() || '') : '';
                            $hidden.val(prevVal && NODE_BY_KEY[prevVal] ? NODE_BY_KEY[prevVal].id : '');
                        }

                        if (onChangeCallback) { onChangeCallback(val); }
                    });

                    return true;
                }

                function rebuild() {
                    $container.empty();
                    $hidden.val('');
                    addLevel(0, null);
                    if (onChangeCallback) { onChangeCallback(''); }
                }

                $container.empty();
                $hidden.val('');
                addLevel(0, null);

                return { rebuild: rebuild };
            }

            var bulkTargetCascade = initCascade('#bulkTargetContainer', '#target_group_id', function (key) {
                if (key) {
                    $('#bulk-target-path').text(pathOf(key)).parent().slideDown();
                } else {
                    $('#bulk-target-path').parent().slideUp();
                }
                updateTargetInfo();
                validateForm();
            });

            $sources.on('change', function () {
                updateSelectedGroups();
                // المصادرُ تغيّرت فتغيّرت الاستبعادات — يُعاد بناء قائمة الهدف
                if (bulkTargetCascade) { bulkTargetCascade.rebuild(); }
                validateForm();
            });

            function updateSelectedGroups() {
                const selected = $('#source_groups').val() || [];
                const wrapper = $('#selected-groups-wrapper');
                let totalClients = 0;

                if (selected.length === 0) {
                    wrapper.html(
                        '<div class="empty-state">'
                        + '<i class="feather icon-mouse-pointer"></i>'
                        + '<p>{{ __('branches.merge.bulk_nothing_picked') }}</p></div>'
                    );
                } else {
                    const $list = $('<div class="d-flex flex-wrap" style="gap:.35rem"></div>');
                    selected.forEach(function (id) {
                        const node = NODE_BY_KEY['b_' + id];
                        if (!node) return;
                        const count = clientsWithDescendants(node.key);
                        totalClients += count;
                        $list.append(
                            $('<span class="group-tag"></span>')
                                .attr('title', pathOf(node.key))
                                .text(node.name + ' (' + count + ')')
                                .append(
                                    $('<i class="feather icon-x" role="button"></i>')
                                        .on('click', function () { removeGroup(id); })
                                )
                        );
                    });
                    wrapper.empty().append($list);
                }

                $('#selected-count').text(selected.length);
                $('#total-clients').text(totalClients);

                updateTargetInfo();
            }

            function updateTargetInfo() {
                const targetId = $('#target_group_id').val();
                const selected = $('#source_groups').val() || [];

                if (targetId && selected.length > 0) {
                    const target = NODE_BY_KEY['b_' + targetId];
                    if (target) {
                        const currentClients = clientsWithDescendants(target.key);
                        let totalToMove = 0;

                        selected.forEach(function (id) {
                            if (id == targetId) return;
                            const node = NODE_BY_KEY['b_' + id];
                            if (node) { totalToMove += clientsWithDescendants(node.key); }
                        });

                        $('#target-name').text(pathOf(target.key));
                        $('#target-clients').text(currentClients);
                        $('#target-final').text(currentClients + totalToMove);
                        $('#target-info').slideDown();
                    }
                } else {
                    $('#target-info').slideUp();
                }
            }

            function validateForm() {
                const selected = $('#source_groups').val() || [];
                const targetId = $('#target_group_id').val();

                if (selected.length === 0 || !targetId) {
                    $('#merge-btn').prop('disabled', true);
                    return;
                }

                // Check if target is in selected
                if (selected.includes(targetId)) {
                    $('#merge-btn').prop('disabled', true);
                    return;
                }

                $('#merge-btn').prop('disabled', false);
            }

            window.removeGroup = function(groupId) {
                const selected = $('#source_groups').val() || [];
                const filtered = selected.filter(id => id != groupId);
                $('#source_groups').val(filtered).change();
            };

            // Handle form submit
            $('#bulk-merge-form').on('submit', function(e) {
                e.preventDefault();

                const selected = $('#source_groups').val() || [];
                const targetId = $('#target_group_id').val();
                const targetGroup = NODE_BY_KEY['b_' + targetId];
                if (!targetGroup) { return; }

                const sourcePaths = [];
                let totalClients = 0;
                selected.forEach(function (id) {
                    const node = NODE_BY_KEY['b_' + id];
                    if (!node) return;
                    sourcePaths.push(pathOf(node.key));
                    totalClients += clientsWithDescendants(node.key);
                });

                Swal.fire({
                    title: @json(__('branches.merge.bulk_confirm_title')),
                    html: bxConfirm.body(
                        @json(__('branches.merge.transfer_summary', ['count' => ':n'])).replace(':n', totalClients),
                        [
                            { label: @json(__('branches.merge.bulk_sources')), items: sourcePaths },
                            { label: @json(__('branches.merge.merge_target')), value: pathOf(targetGroup.key) }
                        ],
                        $('#bulk-merge-form input[type="checkbox"][name="delete_source"]').is(':checked')
                            ? @json(__('branches.merge.confirm_delete_note'))
                            : @json(__('branches.merge.merge_confirm_note'))
                    ),
                    icon: 'warning',
                    showCancelButton: true,
                    reverseButtons: true,
                    confirmButtonColor: bxConfirm.primary(),
                    cancelButtonColor: bxConfirm.muted,
                    confirmButtonText: @json(__('branches.merge.bulk_confirm_ok')),
                    cancelButtonText: @json(__('branches.common.cancel')),
                }).then((result) => {
                    if (result.isConfirmed) {
                        this.submit();
                    }
                });
            });
        });
    </script>
@endsection
