{{--
    شاشةُ إعدادات نقاط البيع — بلاطاتٌ تقود إلى شاشاتها.

    ═══════════════ ما كان ═══════════════

    • **بطاقاتٌ بلا حراسة**: الثلاثُ تُعرض للجميع، فيقود نصفُها إلى 403.
      وشاشةُ الإعدادات أوّلُ ما يفتحه المستخدم الجديد، فيبدو النظامُ
      مكسوراً من أوّل نقرة.

    • **ترميزٌ مكسور**: وسمُ `</a>` كان **داخل** البطاقة قبل إغلاق
      `div.card`، وعنصرُ `col-md-6` الأوّل يُغلق مرّتين.

    • **`padding: 100px`** على كل بطاقة: ارتفاعٌ ثابت يكسر الشاشة الصغيرة.

    • **رابطُ Bootstrap Icons من CDN** يُحمَّل في هذه الشاشة وحدها، ويُخالف
      أيقونات `feather` المستعملة في الوحدة كلّها.

    • **بطاقةُ «تطبيق سطح المكتب»** بلا رابطٍ إطلاقاً — تُنقَر فلا تفعل شيئاً.

    ═══════════════ والتصميم ═══════════════

    صارت `stk-tiles` نفسَها المستعملة في «إعدادات الحسابات» و«الإعدادات
    المالية» — فمن انتقل من هناك وجد الشكلَ نفسه لا شكلاً ثالثاً.

    والبلاطةُ خارج الباقة **تُقفَل ولا تُخفى**: إخفاؤها يجعل المنشأة لا تعرف
    أن الميزة موجودة، فلا تسألُ عنها. أمّا صلاحيةُ النظام فتُخفي — إذ لا
    معنى لأن يُعرض قفلٌ لمن لا شأنَ له بالشاشة أصلاً.
--}}
@extends('pos::master')

@section('title', 'إعدادات نقاط البيع')

@section('content')

@php
    use App\Constants\planFeatures\PosPlanFeatures;
    use App\Support\PlanLock;

    /*
        الترتيبُ مقصود: `perm` أوّلاً فتُخفي، ثم `feature` فتُقفل.

        وكلُّ بلاطةٍ تحمل صلاحياتِها بديلاً لا شرطاً واحداً: من مُنح «عرض
        الأجهزة» دون «إدارتها» يحتاج الشاشة أيضاً.
    */
    $tiles = [
        [
            'perm' => ['pos_manage_settings'],
            'feature' => PosPlanFeatures::SETTINGS,
            'route' => 'pos.settings.general',
            'icon' => 'settings',
            'title' => 'الإعدادات العامة',
            'hint' => 'العميل الافتراضي وطرق الدفع والبنك الافتراضي وحدّ الخصم',
        ],
        [
            'perm' => ['pos.devices.view', 'pos.devices.manage'],
            'feature' => PosPlanFeatures::DEVICES,
            'route' => 'pos.settings.devices.index',
            'icon' => 'monitor',
            'title' => 'أجهزة الكاشير',
            'hint' => 'الصناديق ومستودعاتها وفروعها وحدّ النقد في أدراجها',
        ],
        [
            'perm' => ['pos.printers.manage', 'pos.devices.view'],
            'feature' => PosPlanFeatures::PRINTERS,
            'route' => 'pos.settings.printers.index',
            'icon' => 'printer',
            'title' => 'الطابعات',
            'hint' => 'إيصالات الكاشير وطابعات المطبخ والملصقات',
        ],
        [
            'perm' => ['pos.shifts.view', 'pos.shifts.manage'],
            'feature' => PosPlanFeatures::DEVICES,
            'route' => 'pos.settings.shift.index',
            'icon' => 'clock',
            'title' => 'الورديات',
            'hint' => 'أوقات الدوام وأيامه ومهلة التأخّر',
        ],
        [
            'perm' => ['pos.shift_categories.view', 'pos.shift_categories.manage'],
            'feature' => PosPlanFeatures::DEVICES,
            'route' => 'pos.settings.shift_categories.index',
            'icon' => 'tag',
            'title' => 'تصنيفات الورديات',
            'hint' => 'تسميات تُجمع بها الورديات — نهارية، مسائية، نهاية الأسبوع',
        ],
    ];
@endphp

<div class="content-body">

    @include('pos::components.page-head', [
        'title' => 'إعدادات نقاط البيع',
        'icon' => 'sliders',
        'sub' => 'الأجهزة والطابعات والورديات والإعدادات العامة للكاشير',
        'crumbs' => [
            'الرئيسية' => url('/'),
            'نقاط البيع' => null,
            'الإعدادات' => null,
        ],
    ])

    <div class="card">
        <div class="card-body">
            <div class="stk-tiles">
                @foreach ($tiles as $tile)
                    @continue (! Route::has($tile['route']) || ! canAny($tile['perm']))

                    @php $locked = PlanLock::locked($tile['feature']); @endphp

                    <a href="{{ $locked ? PlanLock::url() : route($tile['route']) }}"
                       class="stk-tile {{ $locked ? 'stk-tile--locked' : '' }}"
                       @if ($locked) title="{{ PlanLock::reason($tile['feature']) }}" @endif>
                        <span class="stk-tile__icon">
                            <i class="feather icon-{{ $locked ? 'lock' : $tile['icon'] }}"></i>
                        </span>
                        <span class="stk-tile__body">
                            <span class="stk-tile__title d-block">
                                {{ $tile['title'] }}
                                @if ($locked)
                                    <span class="stk-plan-badge">
                                        <i class="feather icon-lock"></i> خارج الباقة
                                    </span>
                                @endif
                            </span>
                            <span class="stk-tile__hint d-block">
                                {{ $locked
                                    ? 'غير مشمولة بباقتك الحالية — اضغط لعرض الاشتراك والترقية.'
                                    : $tile['hint'] }}
                            </span>
                        </span>
                    </a>
                @endforeach
            </div>
        </div>
    </div>

    {{--
        الحساباتُ المحاسبية تُضبط في شاشتها لا هنا.

        كان المستخدم يبحث عنها في إعدادات نقطة البيع فلا يجدها، ولا شيء
        يدلّه على مكانها — فيُترك حسابا فرق الصندوق بلا ربط، ولا يُكتب قيدُ
        الفرق عند الإقفال، ولا يُعرف لماذا.
    --}}
    @permission('pos_manage_settings')
        <div class="alert alert-info">
            <i class="feather icon-info"></i>
            <strong>الحسابات المحاسبية:</strong>
            حساباتُ المبيعات وتكلفتها والضريبة و<strong>فائض درج الكاشير وعجزه</strong>
            تُربط من شاشة «الحسابات المحاسبية» في إعدادات النظام — لا من هنا.
            وما لم يُربط حسابا الفرق لا يُكتب قيدُ فرق الصندوق عند إقفال الجلسة.
            @if (Route::has('AccountingSettings.index'))
                <a href="{{ route('AccountingSettings.index') }}" class="alert-link">فتح الشاشة</a>
            @endif
        </div>
    @endpermission
</div>

@endsection
