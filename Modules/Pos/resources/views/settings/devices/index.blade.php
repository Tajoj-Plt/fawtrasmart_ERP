{{--
    أجهزة الكاشير.

    ═══════════════ ما كان ═══════════════

    • **مودل الحذف معرَّفٌ مرّتين** بنفس `id="deleteModal"`، ودالّة
      `confirmDelete` مكتوبةٌ مرّتين بمسارين مختلفين — والثانيةُ تغلب.
      فالحذفُ يذهب إلى عنوانٍ والأولى تشير إلى غيره، ولا سبيل لمعرفة أيّهما
      نفَذ إلا بالتجربة.

    • **زرُّ التفعيل/التعطيل مكسور**: المسارُ `PATCH` والنموذجُ يرسل `POST`
      بلا `@method('PATCH')` — فكلُّ ضغطةٍ تُردّ بـ405 بصمت.

    • **مساراتٌ مكتوبةٌ يدوياً** (`/POS/Devices/delete/${id}`) بدل `route()`:
      تغييرُ بادئةٍ في التوجيه يكسرها ولا يُنبّه أحدٌ إليه.

    • **رابطُ Bootstrap Icons من CDN** يُحمَّل هنا وحده، وأصنافُ `data-toggle`
      (بوتستراب ٤) مختلطةٌ بـ`data-bs-*` (٥) في الشاشة نفسها.

    • **حالةُ الفراغ** تعرض «إضافة جهاز جديد» لمن لا يملك `manage`.

    • **تنبيهاتُ الجلسة** مكتوبةٌ في الشاشة وقد صارت في `master`.

    ═══════════════ والتصميم ═══════════════

    صارت على نظام `stk-` نفسِه المستعمل في سندات الصرف: ترويسةٌ مكوّنة،
    وأرقامٌ `stk-metric`، وجدولٌ برأسٍ وعدّادٍ ومُنتقي صفحات، وقائمةُ إجراءات
    `stk-actions`، وفراغٌ `stk-empty` يقول ما العمل.
--}}
@extends('pos::master')

@section('title', 'أجهزة الكاشير')

@section('content')

@php
    use App\Constants\planFeatures\PosPlanFeatures;
    use App\Models\CashierDevice;
    use App\Services\Fawtra\SubscriptionGate;
    use App\Support\PlanLock;

    /*
        ═══════════ الطبقتان ═══════════

        `$canManage` صلاحيةُ نظام — تُخفي أزرارَ الكتابة.
        و`$planOn` ميزةُ باقة — تُقفلها وتبقيها ظاهرةً بسببها مكتوباً.
        و`$limit` حدُّ استهلاك — شارةٌ فقط، ويمنع الإضافة وحدها.
    */
    $planOn = SubscriptionGate::permits(PosPlanFeatures::DEVICES);
    $limit = app(SubscriptionGate::class)->limit(PosPlanFeatures::LIMIT_DEVICES);

    $limitBadge = (! $limit['known'] || $limit['unlimited'] || (int) $limit['percent'] < 80)
        ? null
        : [
            'full' => (bool) $limit['exhausted'],
            'text' => $limit['exhausted'] ? 'امتلأ' : $limit['percent'].'٪',
            'title' => sprintf('%s: %d من %d', $limit['label'], (int) $limit['used'], (int) $limit['allowance']),
        ];

    $canCreate = $canManage && $planOn && ! ($limit['exhausted'] ?? false);

    $states = [
        'active' => ['stk-state--on', 'نشط'],
        'inactive' => ['stk-state--off', 'معطَّل'],
        'maintenance' => ['stk-state--warn', 'صيانة'],
        'damaged' => ['stk-state--off', 'تالف'],
    ];

    $hasFilters = collect(['search', 'store_id', 'status', 'branch_id'])
        ->contains(fn ($k) => filled(request($k)));

    $activeCount = $devices->getCollection()->where('device_status', 'active')->count();
    $openSessions = $devices->getCollection()->sum('open_sessions_count');
@endphp

<div class="content-body">

    @section('page-actions')
        @if ($canManage)
            <a href="{{ $canCreate ? route('pos.settings.devices.create') : PlanLock::url() }}"
               class="btn btn-primary"
               @unless ($canCreate)
                   title="{{ $planOn
                       ? 'بلغتَ حدَّ الأجهزة في باقتك: '.(int) $limit['used'].' من '.(int) $limit['allowance']
                       : PlanLock::reason(PosPlanFeatures::DEVICES) }}"
               @endunless>
                <i class="feather icon-{{ $canCreate ? 'plus' : 'lock' }}"></i> جهاز جديد
            </a>
        @endif
    @endsection

    @include('pos::components.page-head', [
        'title' => 'أجهزة الكاشير',
        'icon' => 'monitor',
        'sub' => 'الصناديق ومستودعاتها وفروعها وحالتها التشغيلية',
        'crumbs' => [
            'الرئيسية' => url('/'),
            'الإعدادات' => route('pos.settings.index'),
            'الأجهزة' => null,
        ],
    ])

    @unless ($planOn)
        <div class="alert alert-warning">
            <i class="feather icon-lock"></i>
            <strong>إدارةُ الأجهزة خارج باقتك الحالية.</strong>
            الأجهزةُ المسجَّلة تبقى معروضةً، ولا يُقبل إنشاءُ جديد.
            <a href="{{ PlanLock::url() }}" class="alert-link">عرض الاشتراك والترقية</a>
        </div>
    @endunless

    <div class="stk-metrics">
        <div class="stk-metric">
            <span class="stk-metric__icon"><i class="feather icon-monitor"></i></span>
            <span class="stk-metric__label">إجمالي الأجهزة</span>
            <span class="stk-metric__value">{{ $devices->total() }}</span>
        </div>

        <div class="stk-metric stk-metric--success">
            <span class="stk-metric__icon"><i class="feather icon-check-circle"></i></span>
            <span class="stk-metric__label">نشطة في هذه الصفحة</span>
            <span class="stk-metric__value">{{ $activeCount }}</span>
        </div>

        <div class="stk-metric stk-metric--info">
            <span class="stk-metric__icon"><i class="feather icon-play-circle"></i></span>
            <span class="stk-metric__label">جلسات مفتوحة</span>
            <span class="stk-metric__value">{{ $openSessions }}</span>
        </div>

        @if ($limit['known'] && ! $limit['unlimited'])
            <div class="stk-metric {{ $limit['exhausted'] ? 'stk-metric--danger' : '' }}">
                <span class="stk-metric__icon"><i class="feather icon-layers"></i></span>
                <span class="stk-metric__label">حدّ الباقة</span>
                <span class="stk-metric__value">
                    {{ (int) $limit['used'] }} / {{ (int) $limit['allowance'] }}
                </span>
            </div>
        @endif
    </div>

    {{-- ═══════════ الفلاتر ═══════════ --}}
    <div class="card stk-filters">
        <div class="card-body">
            <div class="stk-filters__head">
                <h6 class="stk-filters__title"><i class="feather icon-filter"></i> بحث وتصفية</h6>

                @if ($hasFilters)
                    <a href="{{ route('pos.settings.devices.index') }}" class="btn btn-sm btn-outline-secondary">
                        <i class="feather icon-x"></i> مسح الفلاتر
                    </a>
                @endif
            </div>

            <form method="GET" action="{{ route('pos.settings.devices.index') }}" class="form">
                <div class="row">
                    <div class="col-md-4 col-sm-6">
                        <div class="form-group">
                            <label for="search">بحث</label>
                            <input type="text" id="search" name="search" class="form-control"
                                   placeholder="اسم الجهاز…" value="{{ request('search') }}">
                        </div>
                    </div>

                    <div class="col-md-3 col-sm-6">
                        <div class="form-group">
                            <label for="store_id">المستودع</label>
                            <select id="store_id" name="store_id" class="form-control select2">
                                <option value="">جميع المستودعات</option>
                                @foreach ($stores as $store)
                                    <option value="{{ $store->id }}" @selected(request('store_id') == $store->id)>
                                        {{ $store->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="col-md-3 col-sm-6">
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

                    <div class="col-md-2 col-sm-6">
                        <div class="form-group">
                            <label for="status">الحالة</label>
                            <select id="status" name="status" class="form-control">
                                <option value="">الكل</option>
                                @foreach ($statusOptions as $value => $label)
                                    <option value="{{ $value }}" @selected(request('status') == $value)>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="feather icon-search"></i> بحث
                </button>
            </form>
        </div>
    </div>

    {{-- ═══════════ الجدول ═══════════ --}}
    <div class="card">
        <div class="card-body">
            @if ($devices->count() > 0)
                <div class="stk-table-head">
                    <h6 class="stk-table-head__title">
                        <i class="feather icon-list"></i> الأجهزة
                        <span class="stk-count">{{ $devices->total() }}</span>
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
                                <th>الجهاز</th>
                                <th>المستودع والفرع</th>
                                <th class="text-center">الجلسات المفتوحة</th>
                                <th class="text-center">الحالة</th>
                                <th>أُنشئ</th>
                                <th class="text-center" style="width:90px">إجراءات</th>
                            </tr>
                        </thead>

                        <tbody>
                            @foreach ($devices as $device)
                                @php
                                    [$stateClass, $stateLabel] = $states[$device->device_status]
                                        ?? ['stk-state--neutral', $device->status_text];
                                @endphp

                                <tr>
                                    <td>
                                        <div class="stk-media">
                                            @if ($device->device_image)
                                                <img src="{{ $device->image_url }}" class="stk-media__thumb"
                                                     alt="{{ $device->device_name }}">
                                            @else
                                                <span class="stk-media__thumb"><i class="feather icon-monitor"></i></span>
                                            @endif

                                            <span class="stk-media__body">
                                                <span class="stk-media__title">{{ $device->device_name }}</span>
                                                @if ($device->description)
                                                    <span class="stk-media__sub">
                                                        {{ Str::limit($device->description, 60) }}
                                                    </span>
                                                @endif
                                            </span>
                                        </div>
                                    </td>

                                    <td>
                                        {{ $device->store->name ?? '—' }}
                                        <span class="stk-media__sub d-block">
                                            {{ $device->branch->name ?? 'بلا فرع' }}
                                        </span>
                                    </td>

                                    <td class="text-center">
                                        @if ($device->open_sessions_count > 0)
                                            <span class="stk-state stk-state--warn">
                                                {{ $device->open_sessions_count }} مفتوحة
                                            </span>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>

                                    <td class="text-center">
                                        <span class="stk-state {{ $stateClass }}">{{ $stateLabel }}</span>
                                    </td>

                                    <td>
                                        <span class="stk-num">{{ $device->created_at?->format('Y-m-d') ?? '—' }}</span>
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
                                                   href="{{ route('pos.settings.devices.show', $device->id) }}">
                                                    <i class="feather icon-eye"></i> عرض
                                                </a>

                                                @if ($canManage)
                                                    <a class="dropdown-item"
                                                       href="{{ route('pos.settings.devices.edit', $device->id) }}">
                                                        <i class="feather icon-edit-2"></i> تعديل
                                                    </a>

                                                    <div class="dropdown-divider"></div>

                                                    @if ($device->device_status === 'active')
                                                        <button type="submit" form="toggle-{{ $device->id }}"
                                                                class="dropdown-item">
                                                            <i class="feather icon-pause-circle"></i> تعطيل
                                                        </button>
                                                    @else
                                                        <button type="submit" form="toggle-{{ $device->id }}"
                                                                class="dropdown-item">
                                                            <i class="feather icon-play-circle"></i> تفعيل
                                                        </button>
                                                    @endif

                                                    <div class="dropdown-divider"></div>

                                                    {{--
                                                        الحذفُ يُمنع ما دامت للجهاز جلسةٌ مفتوحة:
                                                        حذفُه يترك جلسةً بلا صندوقٍ تُنسب إليه،
                                                        ولا سبيل لإقفالها بعدها.
                                                    --}}
                                                    @if ($device->open_sessions_count > 0)
                                                        <span class="dropdown-item text-muted"
                                                              title="للجهاز جلسةٌ مفتوحة — أقفلها أوّلاً">
                                                            <i class="feather icon-slash"></i> لا يُحذف الآن
                                                        </span>
                                                    @else
                                                        <button type="submit" form="delete-{{ $device->id }}"
                                                                class="dropdown-item text-danger"
                                                                onclick="return confirm('حذف الجهاز «{{ $device->device_name }}»؟ لا يمكن التراجع.');">
                                                            <i class="feather icon-trash-2"></i> حذف
                                                        </button>
                                                    @endif
                                                @endif
                                            </div>
                                        </div>

                                        @if ($canManage)
                                            {{--
                                                النماذجُ خارج القائمة المنسدلة: `dropdown-menu` يُنقل
                                                في DOM عند الفتح، فنموذجٌ داخله قد يخرج من سياقه.
                                                والزرُّ يشير إليه بـ`form=` فيعمل أينما كان.

                                                و`@method('PATCH')` هو ما كان ناقصاً: المسارُ
                                                `PATCH` والنموذجُ يرسل `POST` فيُردّ بـ405.
                                            --}}
                                            <form id="toggle-{{ $device->id }}" class="d-none" method="POST"
                                                  action="{{ route('pos.settings.devices.toggle-status', $device->id) }}">
                                                @csrf
                                                @method('PATCH')
                                                <input type="hidden" name="device_status"
                                                       value="{{ $device->device_status === 'active' ? 'inactive' : 'active' }}">
                                            </form>

                                            <form id="delete-{{ $device->id }}" class="d-none" method="POST"
                                                  action="{{ route('pos.settings.devices.destroy', $device->id) }}">
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

                <div class="stk-table-foot">
                    {{ $devices->withQueryString()->links() }}
                </div>
            @else
                <div class="stk-empty">
                    <span class="stk-empty__icon"><i class="feather icon-monitor"></i></span>
                    <h6 class="stk-empty__title">لا أجهزة مطابقة</h6>
                    <p class="stk-empty__hint">
                        @if ($hasFilters)
                            جرّب توسيع الفلاتر أو أعِد تعيينها.
                        @else
                            لم يُسجَّل جهازُ كاشيرٍ بعد — والجلسةُ لا تُفتح بلا جهاز.
                        @endif
                    </p>

                    @if ($hasFilters)
                        <a href="{{ route('pos.settings.devices.index') }}" class="btn btn-outline-secondary mt-1">
                            <i class="feather icon-x"></i> مسح الفلاتر
                        </a>
                    @elseif ($canCreate)
                        <a href="{{ route('pos.settings.devices.create') }}" class="btn btn-primary mt-1">
                            <i class="feather icon-plus"></i> جهاز جديد
                        </a>
                    @endif
                </div>
            @endif
        </div>
    </div>
</div>

@endsection
