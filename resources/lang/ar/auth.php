<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines are used during authentication for various
    | messages that we need to display to the user. You are free to modify
    | these language lines according to your application's requirements.
    |
    */

    'failed' => 'These credentials do not match our records.',
    'throttle' => 'Too many login attempts. Please try again in :seconds seconds.',

    /*
    |--------------------------------------------------------------------------
    | شاشة تسجيل الدخول
    |--------------------------------------------------------------------------
    |
    | نصوص الصفحة الوحيدة التي تُرى قبل الدخول. اسم النظام لا يُكتب هنا: يأتي
    | من «مظهر النظام ← الهوية» ويُمرَّر كمتغيّر `:app`.
    |
    */

    'login' => [
        'page_title' => 'تسجيل الدخول',
        'tagline' => 'منظومة إدارة موارد متكاملة',
        'features' => [
            'تحليلات الأعمال الفورية',
            'إدارة المخزون المتكاملة',
            'التقارير المالية المتقدمة',
            'التعاون متعدد المستخدمين',
            'الأمان على مستوى المؤسسات',
        ],

        'welcome' => 'مرحباً بك',
        'subtitle' => 'سجّل الدخول للوصول إلى لوحة تحكّم :app',

        'email' => 'البريد الإلكتروني',
        'email_placeholder' => 'name@company.com',
        'password' => 'كلمة المرور',
        'password_placeholder' => 'أدخل كلمة المرور',
        'show_password' => 'إظهار كلمة المرور',
        'hide_password' => 'إخفاء كلمة المرور',

        'remember' => 'تذكّرني',
        'forgot' => 'نسيت كلمة المرور؟',
        'forgot_hint' => 'إعادة تعيين كلمة المرور تتم من مدير النظام — تواصل معه لاستعادة الوصول إلى حسابك.',

        'submit' => 'تسجيل الدخول',
        'submitting' => 'جارٍ تسجيل الدخول…',

        'email_invalid' => 'يرجى إدخال بريد إلكتروني صحيح',
        'password_required' => 'يرجى إدخال كلمة المرور',
        'caps_lock' => 'مفتاح Caps Lock مُفعّل',

        'secure' => 'اتصال مُؤمَّن — لا تُشارك بيانات دخولك مع أحد',
        'language' => 'اللغة',
        'rights' => 'جميع الحقوق محفوظة',
    ],

];
