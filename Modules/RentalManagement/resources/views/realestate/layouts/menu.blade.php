{{--
    بنود قائمة إدارة العقارات — مصدرٌ واحد تستدعيه القائمة الموحّدة
    (`layouts.sidebar`) وقائمة القسم (`realestate.layouts.sidebar`) معاً، فلا
    يختلف بندٌ أو حارسٌ بين القائمتين.

    الحارسان كما في المخزون: **صلاحية النظام تُخفي** (`hasPermission`)،
    و**الميزة خارج الباقة تُقفَل وتبقى ظاهرة** (`PlanLock`). والأسماء هنا
    مطابقةٌ لِما تفحصه مُنشئات المتحكّمات فعلاً.

    الوسيط: `$nested` — true داخل القائمة الموحّدة (مجموعة قابلة للطيّ). وبعض البنود
    مجموعاتٌ متداخلة (`children`) تُرسم بـ `menu-item`.
--}}
@php
    use App\Constants\planFeatures\RealEstatePlanFeatures as RePlan;
    use App\Services\Fawtra\SubscriptionGate;
    use App\Support\PlanLock;

    $reLocale = App::getLocale();
    $nested = $nested ?? false;

    /*
        بنودٌ مفردة، أو مجموعاتٌ بأبناء (`children`): «المستأجرون» تضمّ العقود والدفعات
        والاستحقاقات، و«المباني والمجمعات» تضمّ الوحدات والعروض العقارية.
        المجموعة تظهر إن ظهر أيٌّ من أبنائها.
    */
    $reItems = [
        ['label' => 'لوحة التحكم', 'icon' => 'bar-chart-2', 'route' => 'realestate.dashboard', 'path' => 'dashboard', 'can' => null, 'plan' => null],
        ['label' => 'الملّاك', 'icon' => 'user', 'route' => 'realestate.owners.index', 'path' => 'owners', 'can' => 'realestate.owners.view', 'plan' => RePlan::PROPERTIES],
        ['label' => 'المباني والمجمعات', 'icon' => 'home', 'children' => [
            ['label' => 'المباني والمجمعات', 'route' => 'realestate.buildings.index', 'path' => 'buildings', 'can' => 'realestate.buildings.view', 'plan' => RePlan::PROPERTIES],
            ['label' => 'الوحدات', 'route' => 'realestate.units.index', 'path' => 'units', 'can' => 'realestate.units.view', 'plan' => RePlan::PROPERTIES],
            ['label' => 'العروض العقارية', 'route' => 'realestate.listings.index', 'path' => 'listings', 'can' => 'realestate.listings.view', 'plan' => RePlan::LISTINGS, 'except' => 'listings/requests'],
        ]],
        ['label' => 'طلبات العملاء', 'icon' => 'search', 'route' => 'realestate.listings.requests', 'path' => 'listings/requests', 'can' => 'realestate.listings.view', 'plan' => RePlan::LISTINGS],
        ['label' => 'اهتمامات العملاء', 'icon' => 'users', 'route' => 'realestate.relationships.index', 'path' => 'relationships', 'can' => 'realestate.relationships.view', 'plan' => RePlan::LISTINGS],
        ['label' => 'المستأجرون', 'icon' => 'user-check', 'children' => [
            ['label' => 'قائمة المستأجرين', 'route' => 'realestate.tenants.index', 'path' => 'tenants', 'can' => 'realestate.tenants.view', 'plan' => RePlan::LISTINGS],
            ['label' => 'العقود', 'route' => 'realestate.contracts.index', 'path' => 'contracts', 'can' => 'realestate.contracts.view', 'plan' => RePlan::CONTRACTS],
            ['label' => 'الدفعات', 'route' => 'realestate.payments.index', 'path' => 'payments', 'can' => 'realestate.payments.view', 'plan' => RePlan::PAYMENTS, 'except' => 'payments/schedules'],
            ['label' => 'الاستحقاقات والمتأخرات', 'route' => 'realestate.payments.schedules', 'path' => 'payments/schedules', 'can' => 'realestate.payments.schedules', 'plan' => RePlan::PAYMENTS],
        ]],
        ['label' => 'الصيانة', 'icon' => 'tool', 'route' => 'realestate.maintenance.index', 'path' => 'maintenance', 'can' => 'realestate.maintenance.view', 'plan' => RePlan::MAINTENANCE],
        ['label' => 'المهام والمتابعات', 'icon' => 'check-square', 'route' => 'realestate.tasks.index', 'path' => 'tasks', 'can' => 'realestate.tasks.view', 'plan' => RePlan::TASKS],
        ['label' => 'التقارير', 'icon' => 'pie-chart', 'route' => 'realestate.reports.index', 'path' => 'reports', 'can' => 'realestate.reports.view|realestate.reports.financial', 'plan' => RePlan::REPORTS],
        ['label' => 'الإعدادات', 'icon' => 'settings', 'route' => 'realestate.settings.index', 'path' => 'settings', 'can' => 'realestate.settings.view', 'plan' => null, 'except' => 'settings/logs'],
        ['label' => 'سجلّ النشاط', 'icon' => 'activity', 'route' => 'realestate.settings.logs', 'path' => 'settings/logs', 'can' => 'realestate.logs.view', 'plan' => null],
    ];

    $visible = fn (array $item) => $item['can'] === null || hasPermission($item['can']);

    $reItems = collect($reItems)->map(function ($item) use ($visible) {
        if (isset($item['children'])) {
            $item['children'] = array_values(array_filter($item['children'], $visible));

            return $item['children'] ? $item : null;
        }

        return $visible($item) ? $item : null;
    })->filter()->values()->all();

    $rePlanAny = SubscriptionGate::permits(RePlan::all());
@endphp

@foreach ($reItems as $item)
    @include('rentalmanagement::realestate.layouts.menu-item', ['item' => $item, 'nested' => $nested, 'level' => 0, 'reLocale' => $reLocale, 'rePlanAny' => $rePlanAny])
@endforeach
