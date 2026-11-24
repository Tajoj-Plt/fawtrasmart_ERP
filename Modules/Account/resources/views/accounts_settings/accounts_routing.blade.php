@extends('account::master')

@section('title', 'توجيه الحسابات')

@section('content')
    @php
        $groups = $groups ?? collect();
        $all = $groups->flatten(1);
        $mapped = $all->filter(fn ($s) => $s->account_id)->count();
        $active = $all->filter(fn ($s) => $s->is_active)->count();
    @endphp

    <div class="content-body">

        {{--
            كانت الشاشة نموذجاً وهمياً: ستّ بطاقات فيها قوائم بلا `name`
            وخياراتٌ مكتوبة في القالب («411 - المبيعات»، «تعيين تلقائي»)
            و`<form action="#">` لا يحفظ. والتوجيه الحقيقي يعيش في جدول
            `accounting_settings` — واحدٌ وثمانون مفتاحاً مربوطاً بشجرة
            الحسابات — وله شاشته في الإعدادات.

            فبدل نموذجٍ ثانٍ يتنازع مع الأوّل: هذه تعرض ما رُبط فعلاً،
            والتعديل في مصدره.
        --}}
        @include('account::components.page-head', [
            'title' => 'توجيه الحسابات',
            'icon' => 'git-branch',
            'sub' => 'أي حسابٍ يستقبل قيد كل عملية — كما رُبط في «الحسابات المحاسبية»',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'إعدادات الحسابات' => route('accounts_settings.index'),
                'توجيه الحسابات' => null,
            ],
            {{-- الرابط يفتح شاشة `AccountingSettings.index` (وحدة الإعدادات) التي
                 تحرس نفسها بـ`settings.accounting_settings.view` — إظهار الزرّ
                 لمن لا يملكها يقوده إلى صفحة 403. --}}
            'actions' => (Route::has('AccountingSettings.index') && hasPermission('settings.accounting_settings.view'))
                ? '<a href="' . route('AccountingSettings.index') . '" class="btn btn-primary">'
                    . '<i class="feather icon-edit-2"></i> تعديل التوجيه</a>'
                : '',
        ])

        <div class="stk-metrics">
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-git-branch"></i></span>
                <span class="stk-metric__label">مفاتيح التوجيه</span>
                <span class="stk-metric__value stk-num">{{ $all->count() }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-link"></i></span>
                <span class="stk-metric__label">مربوطة بحساب</span>
                <span class="stk-metric__value stk-num">{{ $mapped }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-check-circle"></i></span>
                <span class="stk-metric__label">مفعَّلة</span>
                <span class="stk-metric__value stk-num">{{ $active }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-alert-triangle"></i></span>
                <span class="stk-metric__label">بلا حساب</span>
                <span class="stk-metric__value stk-num">{{ $all->count() - $mapped }}</span>
            </div>
        </div>

        @if ($all->count() > $mapped)
            <div class="alert alert-warning" role="alert">
                <i class="feather icon-alert-triangle"></i>
                <strong>{{ $all->count() - $mapped }} مفتاحاً بلا حساب.</strong>
                العملية التي يخصّها مفتاحٌ غير مربوط تقع على الحساب المكتوب في الشيفرة
                احتياطاً — لا على ما تختاره أنت.
            </div>
        @endif

        {{-- اسمُ متغيّر الحلقة يتسرّب إلى القالب الأب عبر `@extends` --}}
        @forelse ($groups as $groupName => $groupSettings)
            <div class="card stk-table-card">
                <div class="stk-table-head">
                    <h6 class="stk-table-head__title">
                        <i class="feather icon-folder"></i> {{ $groupName }}
                        <span class="stk-count">{{ $groupSettings->count() }}</span>
                    </h6>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover table-striped mb-0">
                        <thead>
                            <tr>
                                <th>العملية</th>
                                <th>الحساب المرتبط</th>
                                <th class="text-center" style="width:120px">الكود</th>
                                <th class="text-center" style="width:110px">الحالة</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($groupSettings as $setting)
                                <tr>
                                    <td>
                                        <span class="stk-media__title">{{ $setting->name }}</span>
                                        @if ($setting->description)
                                            <small class="stk-media__sub d-block">{{ $setting->description }}</small>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($setting->account)
                                            {{ $setting->account->name }}
                                        @else
                                            <span class="stk-state stk-state--off">غير مربوط</span>
                                        @endif
                                    </td>
                                    <td class="text-center stk-num">{{ $setting->account->code ?? '—' }}</td>
                                    <td class="text-center">
                                        @if ($setting->is_active)
                                            <span class="stk-state stk-state--on">مفعَّل</span>
                                        @else
                                            <span class="stk-state stk-state--neutral">موقوف</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @empty
            <div class="card stk-table-card">
                <div class="stk-empty">
                    <span class="stk-empty__icon"><i class="feather icon-git-branch"></i></span>
                    <h6 class="stk-empty__title">لا مفاتيح توجيه معرَّفة</h6>
                    <p class="stk-empty__hint">
                        شغّل <code>php artisan db:seed --class=AccountingSettingsSeeder</code>
                        لزرع مفاتيح التوجيه من شجرة الحسابات.
                    </p>
                </div>
            </div>
        @endforelse
    </div>
@endsection
