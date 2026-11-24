{{--
    شاشةُ الطابعات — لم تكن موجودة.

    `pos_general_settings.invoice_template` كان الضبطَ الوحيد: خيارٌ بين
    `thermal` و`electronic` لكل المنشأة. لا عرضَ ورق ولا نسخ ولا طابعةَ
    مطبخ ولا ربطَ طابعةٍ بجهاز.

    ═══════════════ والتصميم ═══════════════

    كانت ترويستُها مكتوبةً بيدها، وزرُّ «إضافة» محشوراً في شريط الفلاتر لأن
    لا مكان له، والجدولُ تسعةَ أعمدةٍ ضيّقة بلا رأسٍ ولا عدّاد، والفراغُ
    سطراً رمادياً يقول «لا طابعات» ولا يقول ما العمل.

    صارت على نظام `stk-` نفسِه المستعمل في سندات الصرف: الإجراءُ في مكانه من
    الترويسة، والأعمدةُ مضمومةٌ في `stk-media` بسطرٍ ثانٍ للتفاصيل.
--}}
@extends('pos::master')

@section('title', 'طابعات نقاط البيع')

@section('content')

@php
    use App\Constants\planFeatures\PosPlanFeatures;
    use App\Services\Fawtra\SubscriptionGate;
    use App\Support\PlanLock;
    use Modules\Pos\Models\PosPrinter;

    $planOn = SubscriptionGate::permits(PosPlanFeatures::PRINTERS);
    $limit = app(SubscriptionGate::class)->limit(PosPlanFeatures::LIMIT_PRINTERS);

    $limitBadge = (! $limit['known'] || $limit['unlimited'] || (int) $limit['percent'] < 80)
        ? null
        : [
            'full' => (bool) $limit['exhausted'],
            'text' => $limit['exhausted'] ? 'امتلأ' : $limit['percent'].'٪',
            'title' => sprintf('%s: %d من %d', $limit['label'], (int) $limit['used'], (int) $limit['allowance']),
        ];

    $canCreate = $canManage && $planOn && ! ($limit['exhausted'] ?? false);

    $icons = ['receipt' => 'file-text', 'kitchen' => 'coffee', 'label' => 'tag', 'report' => 'printer'];

    $hasFilters = collect(['search', 'type', 'device_id', 'branch_id'])
        ->contains(fn ($k) => filled(request($k)));
@endphp

<div class="content-body">

    @section('page-actions')
        @if ($canManage)
            <a href="{{ $canCreate ? route('pos.settings.printers.create') : PlanLock::url() }}"
               class="btn btn-primary"
               @unless ($canCreate)
                   title="{{ $planOn
                       ? 'بلغتَ حدَّ الطابعات في باقتك: '.(int) $limit['used'].' من '.(int) $limit['allowance']
                       : PlanLock::reason(PosPlanFeatures::PRINTERS) }}"
               @endunless>
                <i class="feather icon-{{ $canCreate ? 'plus' : 'lock' }}"></i> طابعة جديدة
            </a>
        @endif
    @endsection

    @include('pos::components.page-head', [
        'title' => 'الطابعات',
        'icon' => 'printer',
        'sub' => 'إيصالاتُ الكاشير وطابعاتُ المطبخ والملصقات، وربطُها بالأجهزة',
        'crumbs' => [
            'الرئيسية' => url('/'),
            'الإعدادات' => route('pos.settings.index'),
            'الطابعات' => null,
        ],
    ])

    @unless ($planOn)
        <div class="alert alert-warning">
            <i class="feather icon-lock"></i>
            <strong>إدارةُ الطابعات خارج باقتك الحالية.</strong>
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
                    <a href="{{ route('pos.settings.printers.index') }}" class="btn btn-sm btn-outline-secondary">
                        <i class="feather icon-x"></i> مسح الفلاتر
                    </a>
                @endif
            </div>

            <form method="GET" class="form">
                <div class="row">
                    <div class="col-md-4 col-sm-6">
                        <div class="form-group">
                            <label for="search">بحث</label>
                            <input type="text" id="search" name="search" value="{{ request('search') }}"
                                   class="form-control" placeholder="اسم الطابعة…">
                        </div>
                    </div>

                    <div class="col-md-3 col-sm-6">
                        <div class="form-group">
                            <label for="type">النوع</label>
                            <select id="type" name="type" class="form-control">
                                <option value="">كل الأنواع</option>
                                @foreach ($types as $key => $label)
                                    <option value="{{ $key }}" @selected(request('type') === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="col-md-3 col-sm-6">
                        <div class="form-group">
                            <label for="device_id">الجهاز</label>
                            <select id="device_id" name="device_id" class="form-control select2">
                                <option value="">كل الأجهزة</option>
                                @foreach ($devices as $device)
                                    <option value="{{ $device->id }}" @selected(request('device_id') == $device->id)>
                                        {{ $device->device_name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="col-md-2 col-sm-6">
                        <div class="form-group">
                            <label for="branch_id">الفرع</label>
                            <select id="branch_id" name="branch_id" class="form-control select2">
                                <option value="">الكل</option>
                                @foreach ($branches as $branch)
                                    <option value="{{ $branch->id }}" @selected(request('branch_id') == $branch->id)>
                                        {{ $branch->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                <button class="btn btn-primary"><i class="feather icon-search"></i> بحث</button>
            </form>
        </div>
    </div>

    {{-- ═══════════ الجدول ═══════════ --}}
    <div class="card">
        <div class="card-body">
            @if ($printers->count() > 0)
                <div class="stk-table-head">
                    <h6 class="stk-table-head__title">
                        <i class="feather icon-list"></i> الطابعات
                        <span class="stk-count">
                            {{ method_exists($printers, 'total') ? $printers->total() : $printers->count() }}
                        </span>
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
                                <th>الطابعة</th>
                                <th>الاتصال</th>
                                <th>الجهاز والفرع</th>
                                <th class="text-center">الورق والنسخ</th>
                                <th class="text-center">الحالة</th>
                                @if ($canManage)
                                    <th class="text-center" style="width:90px">إجراءات</th>
                                @endif
                            </tr>
                        </thead>

                        <tbody>
                            @foreach ($printers as $printer)
                                <tr>
                                    <td>
                                        <div class="stk-media">
                                            <span class="stk-media__thumb">
                                                <i class="feather icon-{{ $icons[$printer->type] ?? 'printer' }}"></i>
                                            </span>
                                            <span class="stk-media__body">
                                                <span class="stk-media__title">
                                                    {{ $printer->name }}
                                                    @if ($printer->is_default)
                                                        <span class="stk-state stk-state--on">افتراضية</span>
                                                    @endif
                                                </span>
                                                <span class="stk-media__sub">
                                                    {{ PosPrinter::typeLabel($printer->type) }}
                                                </span>
                                            </span>
                                        </div>
                                    </td>

                                    <td>
                                        {{ PosPrinter::connectionLabel($printer->connection) }}
                                        @if ($printer->address)
                                            <span class="stk-media__sub d-block">
                                                <code>{{ $printer->address }}</code>
                                            </span>
                                        @endif
                                    </td>

                                    <td>
                                        {{ $printer->device->device_name ?? 'كل الأجهزة' }}
                                        <span class="stk-media__sub d-block">{{ $printer->branch->name ?? 'بلا فرع' }}</span>
                                    </td>

                                    <td class="text-center">
                                        <span class="stk-num">{{ $printer->paper_width }}</span> مم
                                        <span class="stk-media__sub d-block">{{ $printer->copies }} نسخة</span>
                                    </td>

                                    <td class="text-center">
                                        <span class="stk-state {{ $printer->is_active ? 'stk-state--on' : 'stk-state--off' }}">
                                            {{ $printer->is_active ? 'مفعَّلة' : 'معطَّلة' }}
                                        </span>
                                    </td>

                                    @if ($canManage)
                                        <td class="text-center">
                                            <div class="dropdown stk-actions">
                                                <button class="btn btn-sm btn-outline-secondary" type="button"
                                                        data-bs-toggle="dropdown" aria-expanded="false"
                                                        aria-label="إجراءات">
                                                    <i class="feather icon-more-vertical"></i>
                                                </button>

                                                <div class="dropdown-menu dropdown-menu-end">
                                                    <a class="dropdown-item"
                                                       href="{{ route('pos.settings.printers.edit', $printer->id) }}">
                                                        <i class="feather icon-edit-2"></i> تعديل
                                                    </a>

                                                    <div class="dropdown-divider"></div>

                                                    <button type="submit" form="del-printer-{{ $printer->id }}"
                                                            class="dropdown-item text-danger"
                                                            onclick="return confirm('حذف الطابعة «{{ $printer->name }}»؟');">
                                                        <i class="feather icon-trash-2"></i> حذف
                                                    </button>
                                                </div>
                                            </div>

                                            {{-- خارج القائمة: `dropdown-menu` يُنقل في DOM عند الفتح --}}
                                            <form id="del-printer-{{ $printer->id }}" class="d-none" method="POST"
                                                  action="{{ route('pos.settings.printers.destroy', $printer->id) }}">
                                                @csrf
                                                @method('DELETE')
                                            </form>
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if (method_exists($printers, 'links'))
                    <div class="stk-table-foot">{{ $printers->withQueryString()->links() }}</div>
                @endif
            @else
                <div class="stk-empty">
                    <span class="stk-empty__icon"><i class="feather icon-printer"></i></span>
                    <h6 class="stk-empty__title">لا طابعات مطابقة</h6>
                    <p class="stk-empty__hint">
                        @if ($hasFilters)
                            جرّب توسيع الفلاتر أو أعِد تعيينها.
                        @else
                            لم تُسجَّل طابعةٌ بعد — الإيصالُ يُطبع على الطابعة الافتراضية للجهاز،
                            وبلا طابعةٍ مسجَّلة يُطبع من حوار المتصفّح.
                        @endif
                    </p>

                    @if ($hasFilters)
                        <a href="{{ route('pos.settings.printers.index') }}" class="btn btn-outline-secondary mt-1">
                            <i class="feather icon-x"></i> مسح الفلاتر
                        </a>
                    @elseif ($canCreate)
                        <a href="{{ route('pos.settings.printers.create') }}" class="btn btn-primary mt-1">
                            <i class="feather icon-plus"></i> طابعة جديدة
                        </a>
                    @endif
                </div>
            @endif
        </div>
    </div>
</div>

@endsection
