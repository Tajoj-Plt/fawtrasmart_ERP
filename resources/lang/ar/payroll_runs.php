<?php

/*
 * نصوص شاشة «سجلات صرف الرواتب» (hr::salaries.payroll_runs.index).
 * المراحل مفاتيحها من PayrollRun::stages().
 */
return [
    'page_title' => 'سجلات الصرف',
    'title' => 'سجلات صرف الرواتب',
    'subtitle' => 'صرفةٌ لكلّ موظفٍ في كلّ دورة — تُعتمد في «مسير الصرفات» ثمّ تُصرف فتصير «مدفوعة»',
    'batches' => 'مسير الصرفات',
    'payrolls' => 'مسيرات الرواتب',

    'stages' => [
        'awaiting_approval' => 'بانتظار اعتماد مسير الرواتب',
        'scheduled' => 'معتمدة — قبل يوم الصرف',
        'ready' => 'مستحقة الصرف',
        'held' => 'موقوفة',
        'paid' => 'مدفوعة',
        'cancelled' => 'ملغاة',
    ],

    'due_today' => ':count حلّ يومُ صرفها',
    'this_month' => 'هذا الشهر: :amount',

    'filters' => 'بحث وتصفية',
    'filters_active' => 'فلاتر مفعّلة',
    'employee' => 'الموظف',
    'stage' => 'المرحلة',
    'all' => 'الكل',
    'period_from' => 'الفترة من',
    'period_to' => 'إلى',
    'reset' => 'إعادة تعيين',
    'search' => 'بحث',

    'list' => 'سجلات الصرف',
    'record' => 'السجل',
    'period' => 'الفترة',
    'disbursed_date' => 'تاريخ الصرف',
    'earnings' => 'الاستحقاقات',
    'deductions' => 'الخصميات',
    'advances' => 'السلف',
    'net' => 'الصافي',
    'actions' => 'إجراءات',
    'options' => 'خيارات',
    'view' => 'عرض',
    'view_slip' => 'عرض القسيمة',
    'slip_no' => 'القسيمة #:id',
    'auto' => 'تلقائي',
    'manual' => 'يدوي',
    'showing' => 'عرض :from–:to من :total',

    'empty_title' => 'لا توجد سجلات صرف',
    'empty_filtered' => 'لم يطابق البحث أيّ سجل — جرّب معايير أوسع',
    'empty_hint' => 'تُولَّد الصرفاتُ شهرياً من قسائم الرواتب المعتمدة',
    'clear_filters' => 'إزالة الفلاتر',
];
