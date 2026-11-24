@extends('manufacturing::master')

@section('title', 'تقارير التصنيع')

@section('content')
    @php
        $money = fn ($v) => number_format((float) $v, 2);
        $num = fn ($v) => number_format((float) $v);

        $reports = [
            [
                'route' => 'manufacturing.reports.orders',
                'title' => 'أوامر التصنيع',
                'desc' => 'الأوامر ضمن مدًى زمني بحالاتها وكمّياتها وتكلفتها.',
                'icon' => 'clipboard',
                'tone' => 'primary',
            ],
            [
                'route' => 'manufacturing.reports.costs',
                'title' => 'تكاليف الإنتاج',
                'desc' => 'التكلفة مفصولةً: خامٌ ومصروفاتٌ وتشغيل، ونصيبُ الوحدة منها.',
                'icon' => 'dollar-sign',
                'tone' => 'danger',
            ],
            [
                'route' => 'manufacturing.reports.workstations',
                'title' => 'حِمل محطّات العمل',
                'desc' => 'كم أمراً مرّ بكل محطّة، وكم ساعةً شغّلت، وبكم.',
                'icon' => 'cpu',
                'tone' => 'info',
            ],
            [
                'route' => 'manufacturing.reports.waste',
                'title' => 'الفاقد ونواتج نهاية العمر',
                'desc' => 'المخرجات الجانبية بصنفها ونوعها وقيمتها.',
                'icon' => 'trash-2',
                'tone' => 'warning',
            ],
            [
                'route' => 'manufacturing.reports.boms',
                'title' => 'قوائم المواد',
                'desc' => 'قوائم مواد الإنتاج وعدد بنودها وتكلفة خامها.',
                'icon' => 'layers',
                'tone' => 'success',
            ],
        ];

        /*
            كان هنا «الكمّية المخطَّطة»: مجموعُ كميات الأصناف كلِّها في رقمٍ
            واحد (مليلتراتٌ مع زجاجات) ومعها الأوامر الملغاة. و«إجمالي
            التكلفة» يجمع الملغيَّ والتقديريَّ إلى الفعلي. فحُذفت الكمية —
            وهي بالصنف في تقرير الأوامر — وصارت التكلفةُ فعليةَ المكتملة
            وحدها، ولكل عملةٍ بطاقتُها.
        */
        $metrics = [
            ['label' => 'أوامر التصنيع (بلا الملغي)', 'value' => $num($summary['orders']), 'icon' => 'clipboard', 'tone' => 'primary'],
            ['label' => 'مكتملة', 'value' => $num($summary['done']), 'icon' => 'check-circle', 'tone' => 'info'],
        ];

        foreach ($summary['costs'] as $code => $cost) {
            $metrics[] = ['label' => 'التكلفة الفعلية للمكتملة — ' . $code, 'value' => $money($cost), 'icon' => 'dollar-sign', 'tone' => 'danger'];
        }

        $metrics[] = ['label' => 'قوائم المواد', 'value' => $num($summary['boms']), 'icon' => 'layers', 'tone' => 'success'];
        $metrics[] = ['label' => 'محطّات العمل', 'value' => $num($summary['workstations']), 'icon' => 'cpu', 'tone' => 'warning'];
    @endphp

    @include('stock::components.page-head', [
        'title' => 'تقارير التصنيع',
        'icon' => 'bar-chart-2',
        'sub' => 'خمسة تقارير على أوامر التصنيع وتكاليفها ومحطّاتها',
        'crumbs' => [
            'الرئيسية' => url('/'),
            'التصنيع' => route('manufacturing.dashboard'),
            'التقارير' => null,
        ],
    ])

    <div class="content-body">
        <section id="manufacturing-reports-hub">

            <div class="stk-metrics">
                @foreach ($metrics as $m)
                    <div class="stk-metric">
                        <span class="stk-metric__icon text-{{ $m['tone'] }}">
                            <i class="feather icon-{{ $m['icon'] }}"></i>
                        </span>
                        <span class="stk-metric__value stk-num text-{{ $m['tone'] }}">{{ $m['value'] }}</span>
                        <span class="stk-metric__label">{{ $m['label'] }}</span>
                    </div>
                @endforeach
            </div>

            <div class="row mt-2">
                @foreach ($reports as $r)
                    <div class="col-lg-4 col-md-6 mb-2">
                        <a href="{{ route($r['route']) }}" class="card h-100 mb-0"
                            style="text-decoration:none;transition:transform .15s ease,box-shadow .15s ease;"
                            onmouseover="this.style.transform='translateY(-3px)'"
                            onmouseout="this.style.transform=''">
                            <div class="card-body d-flex" style="gap:.9rem;">
                                <span class="text-{{ $r['tone'] }}"
                                    style="font-size:1.7rem;line-height:1;flex:0 0 auto;">
                                    <i class="feather icon-{{ $r['icon'] }}"></i>
                                </span>
                                <span>
                                    <h6 class="mb-1">{{ $r['title'] }}</h6>
                                    <span class="text-muted" style="font-size:.8rem;">{{ $r['desc'] }}</span>
                                </span>
                            </div>
                        </a>
                    </div>
                @endforeach
            </div>

            @if ($summary['orders'] === 0 && $summary['cancelled'] === 0)
                <div class="alert alert-info mt-1">
                    <i class="feather icon-info"></i>
                    لا أوامر تصنيع مسجَّلة بعد — تفتح التقارير وتعمل، لكنها تُظهر جداول فارغة
                    حتى يُسجَّل أوّل أمر.
                </div>
            @endif
        </section>
    </div>
@endsection
