{{--
    تصنيفاتُ ورديات نقاط البيع.

    ═══════════════ ما كان ═══════════════

    لا شيء. حقلُ «التصنيف الرئيسي» في شاشة الوردية كان يعرض قائمةً مصدرُها
    `shifts_pos.parent_id` — أي ورديات أخرى — ولم تكن هناك شاشةٌ تُنشأ منها
    تلك «التصنيفات» ولا تُعدَّل ولا تُحذف.

    فكان على المستخدم أن ينشئ ورديةً وهميّةً ليستعملها تصنيفاً، ثم تظهر
    الوهميّةُ في قائمة الورديات وفي تقرير مبيعاتها وفي قائمة ما يُفتَح
    عليه جلسة. ولذلك لم يستعمله أحد.

    ═══════════════ وهذه الشاشة ═══════════════

    التصنيفُ كِيانٌ قائمٌ بذاته. والعمودُ الذي يجعل القائمةَ مقروءةً هو
    «الورديات»: تصنيفٌ فارغٌ يُحذف بلا تردّد، وتصنيفٌ عليه اثنتا عشرة
    ورديةً لا يُمسّ — ولا يُفتَح تصنيفٌ لمعرفة ذلك.
--}}
@extends('pos::master')

@section('title', 'تصنيفات الورديات')

@section('content')

@php
    use App\Constants\planFeatures\PosPlanFeatures;
    use App\Services\Fawtra\SubscriptionGate;
    use App\Support\PlanLock;
    use Modules\Pos\Models\PosShiftCategory;

    // التصنيفُ يتبع مفتاحَ الأجهزة في الباقة — كالورديات نفسِها
    $planOn = SubscriptionGate::permits(PosPlanFeatures::DEVICES);
    $canCreate = $canManage && $planOn;

    $hasFilters = collect(['search', 'branch_id', 'status'])->contains(fn ($k) => filled(request($k)));
@endphp

<div class="content-body">

    @section('page-actions')
        <a href="{{ route('pos.settings.shift.index') }}" class="btn btn-outline-secondary">
            <i class="feather icon-clock"></i> الورديات
        </a>

        @if ($canManage)
            <a href="{{ $canCreate ? route('pos.settings.shift_categories.create') : PlanLock::url() }}"
               class="btn btn-primary"
               @unless ($canCreate) title="{{ PlanLock::reason(PosPlanFeatures::DEVICES) }}" @endunless>
                <i class="feather icon-{{ $canCreate ? 'plus' : 'lock' }}"></i> تصنيف جديد
            </a>
        @endif
    @endsection

    @include('pos::components.page-head', [
        'title' => 'تصنيفات الورديات',
        'icon' => 'tag',
        'sub' => 'تسمياتٌ تُجمع بها الورديات — بلا أوقاتِ دوامٍ ولا جلسات',
        'crumbs' => [
            'الرئيسية' => url('/'),
            'الإعدادات' => route('pos.settings.index'),
            'تصنيفات الورديات' => null,
        ],
    ])

    @unless ($planOn)
        <div class="alert alert-warning">
            <i class="feather icon-lock"></i>
            <strong>إدارةُ الورديات خارج باقتك الحالية.</strong>
            المسجَّلُ منها يبقى معروضاً، ولا يُقبل إنشاءُ جديد.
            <a href="{{ PlanLock::url() }}" class="alert-link">عرض الاشتراك والترقية</a>
        </div>
    @endunless

    {{-- ═══════════ الفلاتر ═══════════ --}}
    <div class="card stk-filters">
        <div class="card-body">
            <div class="stk-filters__head">
                <h6 class="stk-filters__title"><i class="feather icon-filter"></i> بحث وتصفية</h6>

                @if ($hasFilters)
                    <a href="{{ route('pos.settings.shift_categories.index') }}"
                       class="btn btn-sm btn-outline-secondary">
                        <i class="feather icon-x"></i> مسح الفلاتر
                    </a>
                @endif
            </div>

            <form method="GET" action="{{ route('pos.settings.shift_categories.index') }}" class="form">
                <div class="row">
                    <div class="col-md-5 col-sm-6">
                        <div class="form-group">
                            <label for="search">بحث</label>
                            <input type="text" id="search" name="search" class="form-control"
                                   placeholder="اسم التصنيف…" value="{{ request('search') }}">
                        </div>
                    </div>

                    <div class="col-md-4 col-sm-6">
                        <div class="form-group">
                            <label for="branch_id">الفرع</label>
                            <select id="branch_id" name="branch_id" class="form-control select2">
                                <option value="">جميع الفروع</option>
                                @foreach ($branches as $branch)
                                    <option value="{{ $branch->id }}" @selected(request('branch_id') == $branch->id)>
                                        {{ $branch->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="col-md-3 col-sm-6">
                        <div class="form-group">
                            <label for="status">الحالة</label>
                            <select id="status" name="status" class="form-control">
                                <option value="">الكل</option>
                                <option value="active" @selected(request('status') === 'active')>مفعَّل</option>
                                <option value="inactive" @selected(request('status') === 'inactive')>معطَّل</option>
                            </select>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary"><i class="feather icon-search"></i> بحث</button>
            </form>
        </div>
    </div>

    {{-- ═══════════ الجدول ═══════════ --}}
    <div class="card">
        <div class="card-body">
            @if ($categories->count() > 0)
                <div class="stk-table-head">
                    <h6 class="stk-table-head__title">
                        <i class="feather icon-list"></i> التصنيفات
                        <span class="stk-count">{{ $categories->total() }}</span>
                    </h6>

                    @include('stock::components.per-page', [
                        'id' => 'perPage',
                        'current' => request('per_page', 15),
                        'onchange' => 'const u = new URL(location.href); u.searchParams.set("per_page", this.value); u.searchParams.delete("page"); location.href = u;',
                    ])
                </div>

                <div class="table-responsive">
                    <table class="table table-hover table-striped mb-0">
                        <thead>
                            <tr>
                                <th>التصنيف</th>
                                <th>الفرع</th>
                                <th class="text-center">الورديات</th>
                                <th class="text-center">الترتيب</th>
                                <th class="text-center">الحالة</th>
                                <th class="text-center" style="width:90px">إجراءات</th>
                            </tr>
                        </thead>

                        <tbody>
                            @foreach ($categories as $category)
                                <tr>
                                    <td>
                                        <div class="stk-media">
                                            {{-- لونُ التصنيف على المُصغَّر نفسِه — هو الغرضُ منه --}}
                                            <span class="stk-media__thumb"
                                                  style="background: {{ $category->color ?: PosShiftCategory::FALLBACK_COLOR }}; color:#fff">
                                                <i class="feather icon-tag"></i>
                                            </span>
                                            <span class="stk-media__body">
                                                <span class="stk-media__title">{{ $category->name }}</span>
                                                @if ($category->description)
                                                    <span class="stk-media__sub">
                                                        {{ Str::limit($category->description, 60) }}
                                                    </span>
                                                @endif
                                            </span>
                                        </div>
                                    </td>

                                    <td>{{ $category->branch->name ?? '—' }}</td>

                                    {{--
                                        العددُ رابطٌ إلى الورديات مصفّاةً بهذا التصنيف —
                                        فمن يرى «٤» يصل إليها بنقرة لا ببحث.
                                    --}}
                                    <td class="text-center">
                                        @if ($category->shifts_count)
                                            <a href="{{ route('pos.settings.shift.index', ['category_id' => $category->id]) }}"
                                               class="stk-num" title="عرض ورديات هذا التصنيف">
                                                {{ $category->shifts_count }}
                                            </a>
                                            @if ($category->active_shifts_count < $category->shifts_count)
                                                <span class="stk-media__sub d-block">
                                                    {{ $category->active_shifts_count }} مفعَّلة
                                                </span>
                                            @endif
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>

                                    <td class="text-center"><span class="stk-num">{{ $category->sort_order }}</span></td>

                                    <td class="text-center">
                                        <span class="stk-state {{ $category->is_active ? 'stk-state--on' : 'stk-state--off' }}">
                                            {{ $category->is_active ? 'مفعَّل' : 'معطَّل' }}
                                        </span>
                                    </td>

                                    <td class="text-center">
                                        @if ($canManage)
                                            <div class="dropdown stk-actions">
                                                <button class="btn btn-sm btn-outline-secondary" type="button"
                                                        data-bs-toggle="dropdown" aria-expanded="false"
                                                        aria-label="إجراءات">
                                                    <i class="feather icon-more-vertical"></i>
                                                </button>

                                                <div class="dropdown-menu dropdown-menu-end">
                                                    <a class="dropdown-item"
                                                       href="{{ route('pos.settings.shift_categories.edit', $category->id) }}">
                                                        <i class="feather icon-edit-2"></i> تعديل
                                                    </a>

                                                    <a class="dropdown-item"
                                                       href="{{ route('pos.settings.shift.index', ['category_id' => $category->id]) }}">
                                                        <i class="feather icon-clock"></i> ورديات التصنيف
                                                    </a>

                                                    <div class="dropdown-divider"></div>

                                                    {{--
                                                        تصنيفٌ عليه ورديات لا يُعرض له زرُّ حذف:
                                                        الخادمُ يرفضه على كل حال، وعرضُ زرٍّ
                                                        يُرفض دائماً إغراءٌ بلا فائدة.
                                                    --}}
                                                    @if ($category->shifts_count)
                                                        <span class="dropdown-item text-muted"
                                                              title="انقل وردياته أولاً أو عطِّله">
                                                            <i class="feather icon-lock"></i>
                                                            لا يُحذف — عليه {{ $category->shifts_count }} وردية
                                                        </span>
                                                    @else
                                                        <button type="submit" form="del-cat-{{ $category->id }}"
                                                                class="dropdown-item text-danger"
                                                                onclick="return confirm('حذف التصنيف «{{ $category->name }}»؟ لا يمكن التراجع.');">
                                                            <i class="feather icon-trash-2"></i> حذف
                                                        </button>
                                                    @endif
                                                </div>
                                            </div>

                                            @unless ($category->shifts_count)
                                                {{-- خارج القائمة: `dropdown-menu` يُنقل في DOM عند الفتح --}}
                                                <form id="del-cat-{{ $category->id }}" class="d-none" method="POST"
                                                      action="{{ route('pos.settings.shift_categories.destroy', $category->id) }}">
                                                    @csrf
                                                    @method('DELETE')
                                                </form>
                                            @endunless
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="stk-table-foot">{{ $categories->withQueryString()->links() }}</div>
            @else
                <div class="stk-empty">
                    <span class="stk-empty__icon"><i class="feather icon-tag"></i></span>
                    <h6 class="stk-empty__title">لا تصنيفات مطابقة</h6>
                    <p class="stk-empty__hint">
                        @if ($hasFilters)
                            جرّب توسيع الفلاتر أو أعِد تعيينها.
                        @else
                            التصنيفُ اختياري — الوردياتُ تعمل بلا تصنيف. وهو يفيد حين تكثر:
                            «نهارية» و«مسائية» و«نهاية الأسبوع».
                        @endif
                    </p>

                    @if ($hasFilters)
                        <a href="{{ route('pos.settings.shift_categories.index') }}"
                           class="btn btn-outline-secondary mt-1">
                            <i class="feather icon-x"></i> مسح الفلاتر
                        </a>
                    @elseif ($canCreate)
                        <a href="{{ route('pos.settings.shift_categories.create') }}" class="btn btn-primary mt-1">
                            <i class="feather icon-plus"></i> تصنيف جديد
                        </a>
                    @endif
                </div>
            @endif
        </div>
    </div>
</div>

@endsection
