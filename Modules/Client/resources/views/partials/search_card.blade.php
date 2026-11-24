{{-- resources/views/client/partials/search_card.blade.php --}}

{{--
    بطاقة بحث العملاء على نظام تصميم الوحدة (`cl-`).

    كان فوقها `<style>` من مئةٍ وثلاثين سطراً يعيد بناء البطاقة بالكامل:
    تدرّجٌ بنفسجيّ مثبَّت (`#4f46e5 → #8b5cf6`)، وخلفيةٌ `#fafbff`،
    وحدودٌ `#eef0f6`، وزرّان بألوانٍ مكتوبة — ستّةَ عشرَ لوناً سُداسياً
    لا يعرفها «مظهر النظام». فمن بدّل اللون الأساسي أو فتح الوضع الداكن
    رأى بقيّةَ الشاشة تتبعه وهذه البطاقةَ وحدها بنفسجيةً بيضاء.

    صارت `cl-filters` من `client::partials.settings-theme`، وألوانُها
    `--app-*`. وما بقي هنا شيئان لا يغطّيهما النظام: تدرّجُ عمق شجرة
    الفروع في القائمة المنسدلة، وفاصلُ «البحث المتقدّم».

    **ما لم يُمسّ**: `#clientFilterForm` و`#advancedSearchForm`
    و`.hide-button-text` و`.button-text` و`.select2` — تقرؤها الشيفرة في
    `client_management_scripts`، وتغييرُها يُسكت الفلترة.
--}}

<style>
    /* عمقُ الفرع في القائمة: تدرّجٌ في الشدّة لا في اللون، فيتبع المظهر */
    .cl-scope .branch-option-depth-0 { font-weight: 700; color: var(--app-text, #1e293b); }
    .cl-scope .branch-option-depth-1 { color: var(--app-text, #334155); }
    .cl-scope .branch-option-depth-2 { color: var(--app-muted, #475569); }
    .cl-scope .branch-option-depth-3 { color: var(--app-muted, #64748b); }

    .cl-scope .advanced-divider {
        display: flex;
        align-items: center;
        gap: .6rem;
        margin: 1rem 0 .25rem;
        color: var(--app-muted, #94a3b8);
        font-size: .78rem;
        font-weight: 600;
    }
    .cl-scope .advanced-divider::before,
    .cl-scope .advanced-divider::after {
        content: '';
        flex: 1;
        height: 1px;
        background: var(--cl-line, #e2e8f0);
    }

    .cl-scope .form-label-pro {
        font-weight: 600;
        font-size: .82rem;
        color: var(--app-text, #475569);
        margin-bottom: .35rem;
        display: flex;
        align-items: center;
        gap: .35rem;
    }
    .cl-scope .form-label-pro i { color: var(--app-primary, #6366f1); font-size: .85rem; }

    .cl-scope .active-filters-badge {
        background: var(--app-primary-soft, rgba(115, 103, 240, .12));
        color: var(--app-primary, #7367f0);
        font-size: .72rem;
        padding: .1rem .5rem;
        border-radius: 999px;
        margin-inline-start: .4rem;
        font-weight: 700;
    }
</style>

<div class="cl-scope">
<div class="card cl-filters">
    <div class="card-body">
        <div class="cl-filters__head">
            <div class="cl-filters__title">
                <i class="feather icon-sliders"></i>
                <span>بحث وتصفية العملاء</span>
                @php
                    $activeFilters = collect(['client','region','status','last_activity_period','categories','date_from','date_to','employee','customer_target_id','registration_stage'])
                        ->filter(fn($k) => request()->filled($k))->count();
                @endphp
                {{-- يبقى في الصفحة دائماً — الفلترةُ بلا إعادة تحميل تحدّث عدّه وتُظهره أو تُخفيه --}}
                <span class="active-filters-badge {{ $activeFilters > 0 ? '' : 'd-none' }}" id="activeFiltersBadge">{{ $activeFilters }} فلتر نشط</span>
            </div>
            <div class="d-flex align-items-center gap-2">
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="toggleSearchFields(this)">
                    <i class="fa fa-eye-slash me-1"></i>
                    <span class="hide-button-text">إخفاء</span>
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse"
                    data-bs-target="#advancedSearchForm" onclick="toggleSearchText(this)">
                    <i class="fa fa-filter me-1"></i>
                    <span class="button-text">متقدم</span>
                </button>
            </div>
        </div>
        <form class="form" id="clientFilterForm" method="GET" action="{{ route('clients.index') }}">
            <div class="row g-3">
                <!-- الحقول الأساسية -->
                <div class="col-md-3">
                    <label class="form-label-pro">
                        <i class="fa fa-user-tie"></i> اختر العميل
                    </label>
                    <select name="client" class="form-control select2">
                        <option value="">— جميع العملاء —</option>
                        @foreach ($allClients as $client)
                            <option value="{{ $client->id }}"
                                {{ request('client') == $client->id ? 'selected' : '' }}>
                                {{ $client->trade_name }} ({{ $client->code }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label-pro">
                        <i class="fa fa-sitemap"></i> تابع لـ
                    </label>
                    <select name="region" class="form-control select2">
                        <option value="">— الكل —</option>
                        @foreach ($branchTreeOptions as $branchOption)
                            <option value="{{ $branchOption['id'] }}"
                                class="branch-option-depth-{{ min($branchOption['depth'], 3) }}"
                                {{ request('region') == $branchOption['id'] ? 'selected' : '' }}>
                                {{ $branchOption['label'] }}
                            </option>
                        @endforeach
                    </select>
                    {{-- العملاءُ يقعون على مستوياتٍ مختلفة من الشجرة: منهم من
                         نُسب إلى منطقة ومنهم إلى فرعٍ أو منفذٍ تحتها. فالمطابقةُ
                         التامّة تُخفي عميلَ المنفذ عمّن يفلتر بالفرع فوقه —
                         والشمولُ هو الافتراض، والمفتاحُ لمن أراد العقدةَ وحدها. --}}
                    <div class="form-check mt-1">
                        <input type="hidden" name="include_descendants" value="0">
                        <input class="form-check-input" type="checkbox" role="switch"
                            id="includeDescendants" name="include_descendants" value="1"
                            {{ request('include_descendants', '1') != '0' ? 'checked' : '' }}>
                        <label class="form-check-label small text-muted" for="includeDescendants">
                            يشمل الفروع والمناطق التي تحته
                        </label>
                    </div>
                </div>

                <div class="col-md-3">
                    <label class="form-label-pro">
                        <i class="fa fa-flag"></i> الحالة
                    </label>
                    <select name="status" class="form-control select2">
                        <option value="">— كل الحالات —</option>
                        <option value="no_status" {{ request('status') == 'no_status' ? 'selected' : '' }}>
                            بدون حالة محددة
                        </option>
                        {{-- لا `$statuses` (القابلة للاختيار: نشط/موقوف) — الفلترُ يصل أيضاً إلى
                             عملاء الحالات القديمة (عميل محتمل، ضريبي…) الذين لا تعرضهم تلك القائمة --}}
                        @include('reports.partials.status-filter-options')
                    </select>
                </div>

                {{-- «وضع العميل» بجانب «الحالة» لا في «متقدم»: فلترٌ أساسيّ، ومخفيّاً لا يُعرف وجودُه --}}
                <div class="col-md-3">
                    <label class="form-label-pro">
                        <i class="fa fa-hourglass-half"></i> وضع العميل
                    </label>
                    <select name="registration_stage" class="form-control">
                        <option value="">— الكل —</option>
                        @foreach (\App\Models\Statuses::section('registration_stage')->ordered()->get() as $stage)
                            <option value="{{ $stage->id }}" {{ (string) request('registration_stage') === (string) $stage->id ? 'selected' : '' }}>
                                {{ $stage->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <!-- البحث المتقدم -->
            <div class="collapse {{ request()->hasAny(['last_activity_period','categories','date_from','date_to','employee','customer_target_id']) ? 'show' : '' }}" id="advancedSearchForm">
                <div class="advanced-divider">
                    <i class="fa fa-filter"></i> خيارات بحث متقدمة
                </div>
                <div class="row g-3 pb-2">
                    <div class="col-md-4">
                        <label class="form-label-pro">
                            <i class="fa fa-clock"></i> اختر الفترة
                        </label>
                        <select name="last_activity_period" class="form-control">
                            <option value="">— كل الفترات —</option>
                            <option value="today" {{ request('last_activity_period') == 'today' ? 'selected' : '' }}>
                                اليوم (0-1 يوم)</option>
                            <option value="week" {{ request('last_activity_period') == 'week' ? 'selected' : '' }}>
                                منذ أسبوع (1-7 أيام)</option>
                            <option value="two_weeks"
                                {{ request('last_activity_period') == 'two_weeks' ? 'selected' : '' }}>منذ أسبوعين
                                (7-14 يوم)</option>
                            <option value="month" {{ request('last_activity_period') == 'month' ? 'selected' : '' }}>
                                منذ شهر (14-30 يوم)</option>
                            <option value="three_months"
                                {{ request('last_activity_period') == 'three_months' ? 'selected' : '' }}>منذ 3 أشهر
                                (30-90 يوم)</option>
                            <option value="six_months"
                                {{ request('last_activity_period') == 'six_months' ? 'selected' : '' }}>منذ 6 أشهر
                                (90-180 يوم)</option>
                            <option value="year" {{ request('last_activity_period') == 'year' ? 'selected' : '' }}>
                                منذ سنة (180-365 يوم)</option>
                            <option value="more_than_year"
                                {{ request('last_activity_period') == 'more_than_year' ? 'selected' : '' }}>أكثر من سنة
                                (أكثر من 365 يوم)</option>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label-pro">
                            <i class="fa fa-tags"></i> التصنيف
                        </label>
                        <select name="categories" class="form-control">
                            <option value="">— كل التصنيفات —</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}"
                                    {{ request('categories') == $category->id ? 'selected' : '' }}>
                                    {{ $category->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label-pro">
                            <i class="fa fa-user"></i> الموظف
                        </label>
                        <select name="employee" class="form-control">
                            <option value="">— كل الموظفين —</option>
                            @foreach ($employees as $employee)
                                <option value="{{ $employee->id }}"
                                    {{ request('employee') == $employee->id ? 'selected' : '' }}>
                                    {{ $employee->full_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label-pro">
                            <i class="fa fa-bullseye"></i> هدف العميل
                        </label>
                        <select name="customer_target_id" class="form-control select2">
                            <option value="">— كل الأهداف —</option>
                            <option value="no_target" {{ request('customer_target_id') == 'no_target' ? 'selected' : '' }}>
                                بدون هدف محدد
                            </option>
                            @isset($customerTargets)
                                @foreach ($customerTargets as $target)
                                    <option value="{{ $target->id }}"
                                        {{ request('customer_target_id') == $target->id ? 'selected' : '' }}>
                                        {{ $target->name }}
                                    </option>
                                @endforeach
                            @endisset
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label-pro">
                            <i class="fa fa-calendar-day"></i> التاريخ من
                        </label>
                        <input type="date" name="date_from" class="form-control"
                            value="{{ request('date_from') }}">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label-pro">
                            <i class="fa fa-calendar-check"></i> التاريخ إلى
                        </label>
                        <input type="date" name="date_to" class="form-control"
                            value="{{ request('date_to') }}">
                    </div>

                    {{-- حُذف فلترا «دوام المسؤول من / إلى»: كانا يفلتران على
                         دوام أحدث ملاحظةٍ للعميل (`client_relations.shift_start/end`)
                         — بيانٌ يخصّ الملاحظة لا العميل، ويتغيّر بكل ملاحظةٍ
                         جديدة، فنتيجةُ الفلترة لا تثبت. --}}
                </div>
            </div>

            <div class="cl-filters__foot">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search me-1"></i>
                    تطبيق البحث
                </button>
                {{-- الرابطُ باقٍ لمن فتحه بلا جافاسكربت؛ `SearchManager` يعترضه ويعيد التعيين في مكانه --}}
                <a href="{{ route('clients.index') }}" class="btn btn-outline-secondary" id="resetClientFilters">
                    <i class="fas fa-undo me-1"></i>
                    إعادة تعيين
                </a>
                <small class="text-muted ms-auto {{ $activeFilters > 0 ? '' : 'd-none' }}" id="activeFiltersNote">
                    <i class="fa fa-info-circle me-1"></i>
                    لديك <span data-active-filters-count>{{ $activeFilters }}</span> فلتر مفعّل حالياً
                </small>
            </div>
        </form>
    </div>
</div>
</div>
