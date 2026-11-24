@php
    use App\Constants\planFeatures\BranchesPlanFeatures;
    use App\Support\PlanLock;

    /** @var \App\Models\Branch $node */
    /** @var \Illuminate\Support\Collection $childrenByParent */
    $children = $childrenByParent->get($node->id, collect());
    $hasChildren = $children->isNotEmpty();
    $depth = $depth ?? 0;
    $isTerritory = (bool) $node->is_territory;

    /* نوعُ **العرض** لا المخزَّن: مناطقُ المنشأة ومنافذُها القديمة مخزَّنةٌ
       «مجموعة»، فكان الصفُّ يقول «منطقة الرياض — مجموعة». الخريطةُ تأتي
       محسوبةً من المتحكّم مرّةً للشجرة كلِّها، والاحتياطُ هنا لشاشةٍ تضمّ
       القالبَ بلا خريطة. وهو ما يُفلتَر به كذلك، فمن اختار «منطقة» وجدها. */
    $nodeType = ($displayTypes ?? [])[$node->id] ?? \App\Models\Branch::displayNodeType($node, $children);
    $typeMeta = [
        'branch' => [__('branches.node_types.branch_short'), 'icon-home', 'primary'],
        'region' => [__('branches.node_types.region'), 'icon-map', 'success'],
        'group'  => [__('branches.node_types.group_short'), 'icon-users', 'info'],
        'outlet' => [__('branches.node_types.outlet_short'), 'icon-shopping-bag', 'warning'],
    ][$nodeType] ?? [__('branches.node_types.branch_short'), 'icon-home', 'primary'];

    // status: 0/NULL = نشط، 1 = معطّل (كما في CheckBranchStatus)
    $isActive = (int) $node->status !== 1;

    $counts = $counts ?? ['clients' => [], 'neighborhoods' => [], 'clients_total' => [], 'neighborhoods_total' => []];

    // القفلان يأتيان محسوبَين من الشاشة الأمّ (index) فلا يُعاد حسابهما لكل صفّ،
    // والافتراضُ هنا يقي القالبَ الانكسارَ لو ضُمّن من شاشةٍ لا تحسبهما.
    $lockCreate = $lockCreate ?? \App\Support\PlanLock::locked(\App\Constants\planFeatures\BranchesPlanFeatures::BRANCHES);
    $lockMerge = $lockMerge ?? \App\Support\PlanLock::locked(\App\Constants\planFeatures\BranchesPlanFeatures::MERGE);
    $clientCount = $counts['clients'][$node->id] ?? 0;
    $hoodCount = $counts['neighborhoods'][$node->id] ?? 0;

    // الفارق بين ما ارتبط بالعقدة نفسها وما ارتبط بها وبكل ما تحتها: فرعٌ كلُّ
    // عملائه على منافذه يظهر مباشرةً بصفر، والإجمالي وحده يقول إنه ليس فارغاً
    $clientTotal = $counts['clients_total'][$node->id] ?? $clientCount;
    $hoodTotal = $counts['neighborhoods_total'][$node->id] ?? $hoodCount;
    $clientBelow = max(0, $clientTotal - $clientCount);
    $hoodBelow = max(0, $hoodTotal - $hoodCount);

    // النشِطُ والموقوفُ شاملَين ما تحت العقدة — كالرقم الكبير الذي يفصّلانه
    $clientActive = $counts['clients_active'][$node->id] ?? 0;
    $clientStopped = $counts['clients_stopped'][$node->id] ?? 0;
@endphp

<tr class="branch-row"
    data-id="{{ $node->id }}"
    data-parent-id="{{ $node->parent_id ?? '' }}"
    data-depth="{{ $depth }}"
    data-search="{{ $node->name }} {{ $node->code }}"
    {{-- يستعملهما فلترا «النوع» و«الحالة» في بطاقة التصفية أعلى الشاشة --}}
    data-type="{{ $nodeType }}"
    data-status="{{ $isActive ? 'active' : 'inactive' }}"
    @if ($depth > 0) style="display: none;" @endif>

    {{-- عمود التحديد مثبَّت (sticky): الجدول أعرض من الحاوية حين تعمق الشجرة،
         وفي RTL يبدأ شريط التمرير عند الطرف المقابل فيُقصّ هذا العمود تماماً —
         وهو ما جعل التحديد يبدو معطّلاً. التثبيت يُبقيه ظاهراً مهما تمّ التمرير. --}}
    <td class="text-center bx-pin-col">
        <div class="custom-control custom-checkbox bx-check-lg">
            <input type="checkbox" class="custom-control-input branch-bulk-check"
                   id="branch-check-{{ $node->id }}" name="branch_ids[]" value="{{ $node->id }}">
            <label class="custom-control-label" for="branch-check-{{ $node->id }}"
                   title="{{ __('branches.tree.include_in_bulk', ['name' => $node->name]) }}"></label>
        </div>
    </td>

    <td class="bx-node-cell">
        <div class="bx-node">
            @if ($depth > 0)
                <span class="bx-indent" style="width: {{ $depth * 20 }}px;"></span>
            @endif

            @if ($hasChildren)
                <button type="button" class="bx-toggle" data-node-id="{{ $node->id }}"
                        aria-expanded="false" title="{{ __('branches.tree.toggle_hint') }}">
                    <i class="feather icon-chevron-left ic"></i>
                </button>
            @else
                <span class="bx-toggle-spacer"></span>
            @endif

            <span class="bx-node-icon {{ $typeMeta[2] }}">
                <i class="feather {{ $typeMeta[1] }}"></i>
            </span>

            {{-- الاسم يُقصّ بنقاط بدل أن يوسّع الجدول: عرضٌ ثابت يمنع
                 شريط التمرير الأفقي الذي كان يخفي عمود التحديد. --}}
            @can('branches.show')
                <a href="{{ route('branches.show', $node->id) }}" class="bx-node-name"
                   title="{{ __('branches.show.view_title', ['name' => $node->name]) }}">{{ $node->name }}</a>
            @else
                <span class="bx-node-name">{{ $node->name }}</span>
            @endcan

            @if ($node->is_main)
                <span class="sl-tag">{{ __('branches.common.main') }}</span>
            @endif

            @if ($hasChildren)
                <span class="bx-node-count" title="{{ __('branches.tree.children_count_hint', ['count' => $children->count()]) }}">{{ $children->count() }}</span>
            @endif
        </div>
    </td>

    <td><span class="stk-num">{{ $node->code ?: '—' }}</span></td>

    <td><span class="bxd-type bxd-type--{{ $typeMeta[2] }}">{{ $typeMeta[0] }}</span></td>

    {{--
        ══ العملاء ══
        كان العمودُ «الارتباطات» صفّاً من شاراتٍ رماديّة بأيقوناتٍ بلا كلمة:
        «5» و«407» و«97» متجاورةً، لا يُعرف أيُّها عملاءُ وأيُّها أحياء ولا
        أيُّها مباشرٌ وأيُّها ممّا تحت — إلّا بالمرور بالفأرة على كلٍّ منها.

        صار عمودَ «العملاء» وحدَه، وكلُّ رقمٍ مكتوبٌ اسمُه بجانبه: الإجماليُّ
        كبيراً، ثمّ «مباشر / تحته»، ثمّ النشِطُ والموقوفُ بلونَيهما. وعمودُ
        الأحياء حُذف بطلب المستخدم — عددُها باقٍ في بطاقة الإحصاء وصفحة العنصر.
    --}}
    <td class="bx-figure">
        @if ($clientTotal)
            <span class="bx-figure__total">{{ number_format($clientTotal) }}</span>
            <span class="bx-figure__split">
                <span title="{{ __('branches.tree.clients_direct') }}">{{ __('branches.tree.lbl_direct') }} <b>{{ number_format($clientCount) }}</b></span>
                @if ($clientBelow)
                    <span title="{{ __('branches.tree.clients_below') }}">{{ __('branches.tree.lbl_below') }} <b>{{ number_format($clientBelow) }}</b></span>
                @endif
            </span>
            <span class="bxd-pair">
                <span class="bxd-dot bxd-dot--on" title="{{ __('branches.dashboard.col_active_hint') }}">
                    {{ __('branches.tree.lbl_active') }} {{ number_format($clientActive) }}
                </span>
                <span class="bxd-dot bxd-dot--off" title="{{ __('branches.dashboard.col_stopped_hint') }}">
                    {{ __('branches.tree.lbl_stopped') }} {{ number_format($clientStopped) }}
                </span>
            </span>
        @else
            <span class="bxd-none">{{ __('branches.tree.lbl_none') }}</span>
        @endif
    </td>

    <td>
        <span class="sl-state {{ $isActive ? 'sl-state--on' : 'sl-state--off' }}">
            {{ $isActive ? __('branches.common.active') : __('branches.common.inactive') }}
        </span>
    </td>

    {{--
        قائمة الإجراءات بنفس بنية جدول المصروفات: `dropdown stk-actions`
        وزرٌّ محايد تحمل الأيقونةُ نفسَها لا صنفُ الزرّ.

        كانت تُفتح بـ `data-toggle` — صياغة بوتستراب ٤ — والقالبُ يحمّل
        بوتستراب ٥٫٣، فلم تكن القائمة تُفتح أصلاً. و`data-bs-toggle` هي
        الصياغة التي يفهمها المحمَّل فعلاً.

        وكل بندٍ صار خلف صلاحيته: القائمة كانت تعرض «حذف» و«تعطيل» لكل
        من بلغ الشجرة، ثم يردّه المتحكّم — إظهارُ ما لا يُنفَّذ.
    --}}
    <td class="text-center">
        <div class="dropdown stk-actions">
            <button class="btn btn-sm btn-outline-secondary" type="button"
                    id="branchMenu{{ $node->id }}" data-bs-toggle="dropdown"
                    aria-expanded="false" aria-label="{{ __('branches.common.actions') }}">
                <i class="feather icon-more-vertical"></i>
            </button>
            <div class="dropdown-menu dropdown-menu-end" aria-labelledby="branchMenu{{ $node->id }}">
                @can('branches.show')
                    <a class="dropdown-item" href="{{ route('branches.show', $node->id) }}">
                        <i class="feather icon-eye"></i> {{ __('branches.common.view_details') }}
                    </a>
                @endcan

                @can('branches.edit')
                    <a class="dropdown-item" href="{{ route('branches.edit', $node->id) }}">
                        <i class="feather icon-edit-2"></i> {{ __('branches.common.edit') }}
                    </a>
                @endcan

                @can('branches.create')
                    <a class="dropdown-item {{ $lockCreate ? 'stk-plan-locked' : '' }}"
                       href="{{ $lockCreate ? PlanLock::url() : route('branches.create') . '?parent_id=' . $node->id }}"
                       @if ($lockCreate) title="{{ PlanLock::reason(BranchesPlanFeatures::BRANCHES) }}" @endif>
                        <i class="feather icon-{{ $lockCreate ? 'lock' : 'plus' }}"></i> {{ __('branches.tree.add_child_here') }}
                    </a>
                @endcan

                @canany(['branches.toggle_status', 'branches.groups.merge', 'branches.groups.delete', 'branches.delete'])
                    <div class="dropdown-divider"></div>
                @endcanany

                {{-- عمليات مدمّرة: تُرسَل عبر نموذج مخفي مع CSRF بدل روابط GET --}}
                @can('branches.toggle_status')
                    <a class="dropdown-item branch-action-btn" href="#"
                       data-url="{{ route('branches.updateStatus', $node->id) }}"
                       data-method="PATCH"
                       data-confirm="{{ $isActive ? __('branches.tree.confirm_deactivate', ['name' => $node->name]) : __('branches.tree.confirm_activate', ['name' => $node->name]) }}">
                        <i class="feather icon-{{ $isActive ? 'pause-circle' : 'play-circle' }}"></i>
                        {{ $isActive ? __('branches.tree.deactivate') : __('branches.tree.activate') }}
                    </a>
                @endcan

                @if ($isTerritory)
                    @can('branches.groups.merge')
                        <a class="dropdown-item {{ $lockMerge ? 'stk-plan-locked' : '' }}"
                           href="{{ $lockMerge ? PlanLock::url() : route('groups.merge_groups_page', $node->id) }}"
                           @if ($lockMerge) title="{{ PlanLock::reason(BranchesPlanFeatures::MERGE) }}" @endif>
                            <i class="feather icon-{{ $lockMerge ? 'lock' : 'git-merge' }}"></i> {{ __('branches.tree.merge_into_other') }}
                        </a>
                    @endcan
                    @can('branches.groups.delete')
                        <a class="dropdown-item text-danger branch-action-btn" href="#"
                           data-url="{{ route('groups.group_client_destroy', $node->id) }}"
                           data-method="DELETE" data-danger="1"
                           data-confirm="{{ __('branches.tree.confirm_delete_group', ['name' => $node->name]) }}">
                            <i class="feather icon-trash-2"></i> {{ __('branches.tree.delete_group') }}
                        </a>
                    @endcan
                @else
                    @can('branches.delete')
                        <a class="dropdown-item text-danger branch-action-btn" href="#"
                           data-url="{{ route('branches.destroy', $node->id) }}"
                           data-method="DELETE" data-danger="1"
                           data-confirm="{{ __('branches.tree.confirm_delete_branch', ['name' => $node->name]) }}">
                            <i class="feather icon-trash-2"></i> {{ __('branches.tree.delete_branch') }}
                        </a>
                    @endcan
                @endif
            </div>
        </div>
    </td>
</tr>

@foreach ($children as $child)
    @include('branches::partials._branch_tree_node', [
        'node' => $child,
        'childrenByParent' => $childrenByParent,
        'counts' => $counts,
        'depth' => $depth + 1,
    ])
@endforeach
