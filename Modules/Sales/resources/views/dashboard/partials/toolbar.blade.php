{{--
    شريطُ الفلترة — الفترةُ والفرعُ والنطاق، مشتركٌ بين اللوحة وشاشة «أداء المندوبين».

    يقرأ: $period، $periodTabs، $isCustom، $selectedBranchId، $includeChildren،
    $descendantCount، $branchLineage، $isMainBranch، $hasChildBranches، $branchOptions.
    اختيارياً: $toolbarTitle و$toolbarIcon (عنوانُ الشاشة)، و$sortOptions مع $sort
    (قائمةُ ترتيبٍ تُرسَل باسم `sort` وتُعيد تحميل الشاشة عند تغييرها).
--}}
            {{-- ==================== الفلترة ==================== --}}
            <div class="sd-toolbar">
                <h4 class="sd-title"><i class="feather icon-{{ $toolbarIcon ?? 'bar-chart-2' }}"></i> {{ $toolbarTitle ?? __('sales_dashboard.title') }}</h4>
                <p class="sd-subtitle">
                    {{ $period->labelWithDates() }}
                    @if ($selectedBranchId)
                        —
                        {{ $includeChildren && $descendantCount > 0
                            ? __('sales_dashboard.filters.branch_with_children', ['count' => $descendantCount])
                            : __('sales_dashboard.filters.branch_only') }}
                    @endif
                </p>

                @if ($branchLineage->isNotEmpty())
                    {{-- المسار من الجذر إلى العقدة الحالية — كل حلقةٍ رابطٌ يصعد إليها --}}
                    <nav class="sd-crumbs">
                        {{-- «الكل» للرئيسي وحده: مستخدمُ فرعٍ لا يخرج من شجرته --}}
                        @if ($isMainBranch)
                            <a href="{{ request()->fullUrlWithQuery(['branch_id' => null]) }}">— {{ __('sales_dashboard.filters.all_branches') }} —</a>
                        @endif
                        @foreach ($branchLineage as $node)
                            @if (! $loop->first || $isMainBranch)
                                <span class="sd-crumb-sep">/</span>
                            @endif
                            @if ($loop->last)
                                <strong>{{ $node->name }}</strong>
                            @else
                                <a href="{{ request()->fullUrlWithQuery(['branch_id' => $node->id]) }}">{{ $node->name }}</a>
                            @endif
                        @endforeach
                    </nav>
                @endif

                <div class="sd-tabs">
                    @foreach ($periodTabs as $key => $label)
                        <button type="button" class="sd-tab {{ $period->key === $key ? 'active' : '' }}" data-period="{{ $key }}">{{ $label }}</button>
                    @endforeach
                </div>

                <form method="GET" action="{{ url()->current() }}" class="sd-filters" id="sdFilterForm">
                    <input type="hidden" name="period" id="sdPeriod" value="{{ $period->key }}">

                    <div class="sd-field" data-sd-custom style="{{ $isCustom ? '' : 'display:none;' }}">
                        <label for="sdFrom">{{ __('sales_dashboard.toolbar.from') }}</label>
                        <input type="date" class="form-control" name="date_from" id="sdFrom" value="{{ request('date_from') }}">
                    </div>
                    <div class="sd-field" data-sd-custom style="{{ $isCustom ? '' : 'display:none;' }}">
                        <label for="sdTo">{{ __('sales_dashboard.toolbar.to') }}</label>
                        <input type="date" class="form-control" name="date_to" id="sdTo" value="{{ request('date_to') }}">
                    </div>

                    {{-- التنقّل في الشجرة نزولاً: القائمة تعرض أبناء العقدة الحالية
                         لا كل العقد، والمسارُ أعلاها يعود صعوداً. الاسمُ وحده بلا
                         وسم النوع: عُقد المناطق مخزَّنةٌ «مجموعة» فيكذب الوسم. --}}
                    @if ($hasChildBranches)
                        <div class="sd-field">
                            <label for="sdBranch">{{ $selectedBranchId ? __('sales_dashboard.filters.drill_into') : __('sales_dashboard.filters.branch') }}</label>
                            <select name="branch_id" id="sdBranch" class="custom-select">
                                <option value="">
                                    {{ $selectedBranchId
                                        ? '— '.__('sales_dashboard.toolbar.back_to_this').' —'
                                        : '— '.__('sales_dashboard.filters.all_branches').' —' }}
                                </option>
                                @foreach ($branchOptions as $option)
                                    <option value="{{ $option->id }}">{{ $option->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    @if ($selectedBranchId)
                        <div class="sd-field">
                            <label>{{ __('sales_dashboard.toolbar.scope') }}</label>
                            <label class="sd-switch" title="{{ __('sales_dashboard.filters.include_children_hint') }}">
                                {{-- المربّع غير المؤشَّر لا يُرسَل، فالحقل المخفي يحمل «لا» صراحةً --}}
                                <input type="hidden" name="include_children" value="0">
                                <input type="checkbox" name="include_children" value="1" id="sdIncludeChildren" {{ $includeChildren ? 'checked' : '' }}>
                                <span>{{ __('sales_dashboard.filters.include_children') }}</span>
                                @if ($includeChildren && $descendantCount > 0)
                                    <em class="sd-scope-count">+{{ $descendantCount }}</em>
                                @endif
                            </label>
                        </div>
                    @endif

                    {{-- ترتيبُ الصفوف — شاشةُ «أداء المندوبين» وحدها تمرّره؛ تغييرُه يعيد التحميل --}}
                    @if (! empty($sortOptions))
                        <div class="sd-field">
                            <label for="sdSort">{{ __('sales_dashboard.reps.sort') }}</label>
                            <select name="sort" id="sdSort" class="custom-select">
                                @foreach ($sortOptions as $sortKey => $sortLabel)
                                    <option value="{{ $sortKey }}" {{ ($sort ?? '') === $sortKey ? 'selected' : '' }}>{{ $sortLabel }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    <button type="submit" class="sd-apply"><i class="feather icon-filter"></i> {{ __('sales_dashboard.toolbar.filter') }}</button>
                    <a href="{{ url()->current() }}" class="sd-reset"><i class="feather icon-refresh-cw"></i> {{ __('sales_dashboard.toolbar.refresh') }}</a>
                </form>
            </div>
