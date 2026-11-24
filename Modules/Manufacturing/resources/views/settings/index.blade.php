@extends('manufacturing::master')

@section('title', 'إعدادات التصنيع')

@section('content')
    <div class="content-body">

        @include('stock::components.page-head', [
            'title' => 'إعدادات التصنيع',
            'icon' => 'settings',
            'sub' => 'الإعدادات العامة ومسارات الإنتاج والحالات اليدوية',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'التصنيع' => null,
                'الإعدادات' => null,
            ],
        ])

        @include('layouts.alerts.error')
        @include('stock::components.form-errors')
        @include('layouts.alerts.success')

        {{--
            كانت البلاطات أيقونات Bootstrap Icons محمَّلة من CDN بحجم 5rem
            بلا سطرٍ يشرح وجهة كلٍّ منها، وكان وسم `</a>` مغلقاً خارج بطاقته
            في البلاطة الثالثة فيبتلع الرابطُ ما بعده.
        --}}
        @php
            $tiles = [
                [
                    'route' => 'manufacturing.settings.general',
                    'icon' => 'sliders',
                    'title' => 'الإعدادات العامة',
                    'hint' => 'قواعد أوامر التصنيع وتجاوز الكميات',
                ],
                [
                    'route' => 'manufacturing.paths.index',
                    'icon' => 'git-merge',
                    'title' => 'مسارات الإنتاج',
                    'hint' => 'مراحل التصنيع وترتيبها',
                ],
                [
                    'route' => 'Manufacturing.settings.Manual',
                    'icon' => 'tool',
                    'title' => 'الحالات اليدوية',
                    'hint' => 'حالات طلبات التصنيع المُدارة يدوياً',
                ],
            ];
        @endphp

        <div class="stk-tiles">
            @foreach ($tiles as $tile)
                @continue (! Route::has($tile['route']))

                <a href="{{ route($tile['route']) }}" class="stk-tile">
                    <span class="stk-tile__icon">
                        <i class="feather icon-{{ $tile['icon'] }}"></i>
                    </span>
                    <span class="stk-tile__body">
                        <span class="stk-tile__title d-block">{{ $tile['title'] }}</span>
                        <span class="stk-tile__hint d-block">{{ $tile['hint'] }}</span>
                    </span>
                </a>
            @endforeach
        </div>
    </div>
@endsection
