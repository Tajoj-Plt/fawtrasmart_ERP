{{--
    ورديات نقاط البيع.

    ═══════════════ ما كان ═══════════════

    • **المدى الزمني غائبٌ عن الجدول**: الأعمدةُ كانت الاسمَ والتصنيفَ
      الرئيسي والمرفقَ وتاريخَ الإنشاء. أمّا `start_time` و`end_time`
      و`days_of_week` و`grace_minutes` — وهي ما يجعل الوردية **وردية** لا
      تسميةً في شجرة — فلا يظهر منها حرف. يضبطها المستخدمُ في نموذج التعديل
      ثم لا يراها في القائمة، فلا يعرف أضُبطت أم لا.

    • **مسارُ الحذف مكتوبٌ يدوياً** `/POS/Shift/delete/${id}` بدل `route()`.

    • **مودل حذفٍ ومناداةُ `$('#deleteModal').modal()`** — واجهةُ بوتستراب ٤
      عبر jQuery، وبقيّةُ الوحدة على بوتستراب ٥ (`data-bs-*`).

    • **رابطُ Bootstrap Icons من CDN** يُحمَّل هنا وحده.

    • **مؤقّتٌ يُخفي كلَّ `.alert` بعد خمس ثوانٍ** — بما فيها أخطاءُ التحقّق:
      يقرأ المستخدمُ نصفَ الرسالة ثم تختفي، ولا سبيل لإعادتها.

    ═══════════════ والتصميم ═══════════════

    نظامُ `stk-` نفسُه المستعمل في سندات الصرف، والمدى الزمني في عمودٍ ظاهر.
--}}
@extends('pos::master')

@section('title', 'ورديات نقاط البيع')

@section('content')

@php
    use App\Constants\planFeatures\PosPlanFeatures;
    use App\Services\Fawtra\SubscriptionGate;
    use App\Support\PlanLock;

    /*
        الورديةُ تتبع مفتاحَ الأجهزة في الباقة — تُدار من شاشة الإعدادات
        نفسِها ولا تُباع وحدها.
    */
    $planOn = SubscriptionGate::permits(PosPlanFeatures::DEVICES);
    $limit = app(SubscriptionGate::class)->limit(PosPlanFeatures::LIMIT_SHIFTS);

    $limitBadge = (! $limit['known'] || $limit['unlimited'] || (int) $limit['percent'] < 80)
        ? null
        : [
            'full' => (bool) $limit['exhausted'],
            'text' => $limit['exhausted'] ? 'امتلأ' : $limit['percent'].'٪',
            'title' => sprintf('%s: %d من %d', $limit['label'], (int) $limit['used'], (int) $limit['allowance']),
        ];

    $canCreate = $canManage && $planOn && ! ($limit['exhausted'] ?? false);

    $dayNames = [
        'sat' => 'السبت', 'sun' => 'الأحد', 'mon' => 'الاثنين', 'tue' => 'الثلاثاء',
        'wed' => 'الأربعاء', 'thu' => 'الخميس', 'fri' => 'الجمعة',
        1 => 'الاثنين', 2 => 'الثلاثاء', 3 => 'الأربعاء', 4 => 'الخميس',
        5 => 'الجمعة', 6 => 'السبت', 7 => 'الأحد', 0 => 'الأحد',
    ];

    $hasFilters = collect(['search', 'category_id'])->contains(fn ($k) => filled(request($k)));
@endphp

<div class="content-body">

    @section('page-actions')
        @if ($canManage)
            <a href="{{ $canCreate ? route('pos.settings.shift.create') : PlanLock::url() }}"
               class="btn btn-primary"
               @unless ($canCreate)
                   title="{{ $planOn
                       ? 'بلغتَ حدَّ الورديات في باقتك: '.(int) $limit['used'].' من '.(int) $limit['allowance']
                       : PlanLock::reason(PosPlanFeatures::DEVICES) }}"
               @endunless>
                <i class="feather icon-{{ $canCreate ? 'plus' : 'lock' }}"></i> وردية جديدة
            </a>
        @endif
    @endsection

    @include('pos::components.page-head', [
        'title' => 'الورديات',
        'icon' => 'clock',
        'sub' => 'أوقاتُ الدوام وأيّامُه ومهلةُ التأخّر',
        'crumbs' => [
            'الرئيسية' => url('/'),
            'الإعدادات' => route('pos.settings.index'),
            'الورديات' => null,
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
                    <a href="{{ route('pos.settings.shift.index') }}" class="btn btn-sm btn-outline-secondary">
                        <i class="feather icon-x"></i> مسح الفلاتر
                    </a>
                @endif
            </div>

            <form method="GET" action="{{ route('pos.settings.shift.index') }}" class="form">
                <div class="row">
                    <div class="col-md-6 col-sm-6">
                        <div class="form-group">
                            <label for="search">بحث</label>
                            <input type="text" id="search" name="search" class="form-control"
                                   placeholder="اسم الوردية…" value="{{ request('search') }}">
                        </div>
                    </div>

                    <div class="col-md-6 col-sm-6">
                        <div class="form-group">
                            <label for="category_id">التصنيف</label>
                            <select id="category_id" name="category_id" class="form-control select2">
                                <option value="">جميع التصنيفات</option>
                                @foreach ($categories as $category)
                                    <option value="{{ $category->id }}" @selected(request('category_id') == $category->id)>
                                        {{ $category->name }}
                                    </option>
                                @endforeach
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
            @if ($shifts->count() > 0)
                <div class="stk-table-head">
                    <h6 class="stk-table-head__title">
                        <i class="feather icon-list"></i> الورديات
                        <span class="stk-count">{{ $shifts->total() }}</span>
                        @if ($limitBadge)
                            <span class="stk-plan-badge {{ $limitBadge['full'] ? 'stk-plan-badge--full' : 'stk-plan-badge--warn' }}"
                                  title="{{ $limitBadge['title'] }}">{{ $limitBadge['text'] }}</span>
                        @endif
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
                                <th>الوردية</th>
                                <th class="text-center">المدى الزمني</th>
                                <th>أيام الدوام</th>
                                <th class="text-center">مهلة التأخّر</th>
                                <th class="text-center">الحالة</th>
                                <th class="text-center" style="width:90px">إجراءات</th>
                            </tr>
                        </thead>

                        <tbody>
                            @foreach ($shifts as $shift)
                                <tr>
                                    <td>
                                        <div class="stk-media">
                                            <span class="stk-media__thumb"><i class="feather icon-clock"></i></span>
                                            <span class="stk-media__body">
                                                <span class="stk-media__title">
                                                    {{ $shift->name }}
                                                    @if ($shift->attachment)
                                                        <a href="{{ Storage::url($shift->attachment) }}" target="_blank"
                                                           rel="noopener" title="تحميل المرفق">
                                                            <i class="feather icon-paperclip"></i>
                                                        </a>
                                                    @endif
                                                </span>
                                                <span class="stk-media__sub">
                                                    {{ $shift->category->name ?? 'بلا تصنيف' }}
                                                    @if ($shift->description)
                                                        · {{ Str::limit($shift->description, 40) }}
                                                    @endif
                                                </span>
                                            </span>
                                        </div>
                                    </td>

                                    {{--
                                        المدى الزمني — كان غائباً عن الجدول كلّه.
                                        ووردياتٌ تعبر منتصف الليل تُعلَّم صراحةً، فـ«22:00
                                        إلى 06:00» تُقرأ خطأً على أنها ثمانيَ عشرةَ ساعة.
                                    --}}
                                    <td class="text-center">
                                        @if ($shift->start_time && $shift->end_time)
                                            <span class="stk-num" dir="ltr">
                                                {{ substr($shift->start_time, 0, 5) }} — {{ substr($shift->end_time, 0, 5) }}
                                            </span>
                                            @if ($shift->end_time < $shift->start_time)
                                                <span class="stk-media__sub d-block">تعبر منتصف الليل</span>
                                            @endif
                                        @else
                                            <span class="text-muted">غير مضبوط</span>
                                        @endif
                                    </td>

                                    <td>
                                        @php $days = (array) ($shift->days_of_week ?? []); @endphp

                                        @if ($days)
                                            <span class="stk-tags">
                                                @foreach ($days as $day)
                                                    <span class="stk-tag">{{ $dayNames[$day] ?? $day }}</span>
                                                @endforeach
                                            </span>
                                        @else
                                            <span class="text-muted">كل الأيام</span>
                                        @endif
                                    </td>

                                    <td class="text-center">
                                        @if ($shift->grace_minutes)
                                            <span class="stk-num">{{ (int) $shift->grace_minutes }}</span> دقيقة
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>

                                    <td class="text-center">
                                        <span class="stk-state {{ $shift->is_active ? 'stk-state--on' : 'stk-state--off' }}">
                                            {{ $shift->is_active ? 'مفعَّلة' : 'معطَّلة' }}
                                        </span>
                                    </td>

                                    <td class="text-center">
                                        <div class="dropdown stk-actions">
                                            <button class="btn btn-sm btn-outline-secondary" type="button"
                                                    data-bs-toggle="dropdown" aria-expanded="false"
                                                    aria-label="إجراءات">
                                                <i class="feather icon-more-vertical"></i>
                                            </button>

                                            <div class="dropdown-menu dropdown-menu-end">
                                                <a class="dropdown-item"
                                                   href="{{ route('pos.settings.shift.show', $shift->id) }}">
                                                    <i class="feather icon-eye"></i> عرض
                                                </a>

                                                @if ($canManage)
                                                    <a class="dropdown-item"
                                                       href="{{ route('pos.settings.shift.edit', $shift->id) }}">
                                                        <i class="feather icon-edit-2"></i> تعديل
                                                    </a>

                                                    <div class="dropdown-divider"></div>

                                                    <button type="submit" form="del-shift-{{ $shift->id }}"
                                                            class="dropdown-item text-danger"
                                                            onclick="return confirm('حذف الوردية «{{ $shift->name }}»؟ لا يمكن التراجع.');">
                                                        <i class="feather icon-trash-2"></i> حذف
                                                    </button>
                                                @endif
                                            </div>
                                        </div>

                                        @if ($canManage)
                                            {{-- خارج القائمة: `dropdown-menu` يُنقل في DOM عند الفتح --}}
                                            <form id="del-shift-{{ $shift->id }}" class="d-none" method="POST"
                                                  action="{{ route('pos.settings.shift.destroy', $shift->id) }}">
                                                @csrf
                                                @method('DELETE')
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="stk-table-foot">{{ $shifts->withQueryString()->links() }}</div>
            @else
                <div class="stk-empty">
                    <span class="stk-empty__icon"><i class="feather icon-clock"></i></span>
                    <h6 class="stk-empty__title">لا ورديات مطابقة</h6>
                    <p class="stk-empty__hint">
                        @if ($hasFilters)
                            جرّب توسيع الفلاتر أو أعِد تعيينها.
                        @else
                            لم تُسجَّل ورديةٌ بعد — الجلسةُ تُنسب إلى ورديةٍ ليُقاس أداؤها بها.
                        @endif
                    </p>

                    @if ($hasFilters)
                        <a href="{{ route('pos.settings.shift.index') }}" class="btn btn-outline-secondary mt-1">
                            <i class="feather icon-x"></i> مسح الفلاتر
                        </a>
                    @elseif ($canCreate)
                        <a href="{{ route('pos.settings.shift.create') }}" class="btn btn-primary mt-1">
                            <i class="feather icon-plus"></i> وردية جديدة
                        </a>
                    @endif
                </div>
            @endif
        </div>
    </div>
</div>

@endsection
