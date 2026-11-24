@extends('installer::layout')

@section('title', 'المتطلبات')

@section('content')
    <h2>فحص المتطلبات</h2>
    <p class="lead">لا يمكن المتابعة قبل اجتياز كل البنود.</p>

    @include('installer::partials.guide', [
        'title' => 'كيف تعالج بنداً راسباً',
        'items' => [
            '<b>امتداد غير مُفعَّل</b>: افتح <code>php.ini</code> وأزل الفاصلة المنقوطة من أول سطر الامتداد (مثل <code>;extension=gd</code>) ثم أعد تشغيل الخادم.',
            '<b>مجلد غير قابل للكتابة</b>: على لينكس <code>chmod -R 775</code> للمجلد وملكيته لمستخدم خادم الويب. على ويندوز امنح صلاحية الكتابة من خصائص المجلد.',
            '<b>إصدار PHP</b>: النظام يتطلّب 8.2 فأعلى — لا يعمل على ما دونه.',
            'بعد المعالجة اضغط «أعِد الفحص» — لا حاجة لبدء المعالج من جديد.',
        ],
    ])

    @php
        $titles = [
            'php' => 'إصدار PHP',
            'extensions' => 'امتدادات PHP',
            'permissions' => 'صلاحيات الكتابة',
        ];
    @endphp

    {{-- شبكة مدمجة لا ثلاثة جداول: البنود عشرون، وجدولٌ لكل مجموعة يدفع
         زرّ «التالي» خارج الشاشة. والمعلومة المطلوبة هنا ثنائية — مجتاز أو
         راسب — والقيمة التفصيلية تكفي في التلميح --}}
    @foreach ($groups as $groupKey => $checks)
        <h3>{{ $titles[$groupKey] ?? $groupKey }}</h3>
        <div class="req-grid">
            @foreach ($checks as $check)
                <div class="req {{ $check['passed'] ? 'ok' : 'bad' }}"
                     title="المطلوب: {{ $check['required'] }} — الحالي: {{ $check['current'] }}">
                    <span class="req-mark">{{ $check['passed'] ? '✔' : '✘' }}</span>
                    <span class="req-label">{{ $check['label'] }}</span>
                    <span class="req-value">{{ $check['current'] }}</span>
                </div>
            @endforeach
        </div>
    @endforeach

    @unless ($passes)
        <div class="alert bad">
            بعض المتطلبات غير مستوفاة. فعّل الامتدادات الناقصة من إعدادات PHP، وامنح صلاحية الكتابة
            على المجلدات الراسبة (عادةً <code>chmod -R 775</code> للمجلد وملكيته لمستخدم خادم الويب)،
            ثم أعِد تحميل هذه الصفحة.
        </div>
    @endunless

    <div class="actions">
        <a class="btn ghost" href="{{ route('installer.welcome') }}">→ رجوع</a>
        @if ($passes)
            <a class="btn" href="{{ route('installer.activation') }}">التالي: تفعيل الاشتراك ←</a>
        @else
            <a class="btn" href="{{ route('installer.requirements') }}">أعِد الفحص</a>
        @endif
    </div>
@endsection
