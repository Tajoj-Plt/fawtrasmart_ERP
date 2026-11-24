{{--
    شارة حالة طلب الشراء — مصدر واحد للجدول وشاشة العرض.
    التسمية من ملفّ الترجمة، فتتبع لغة الواجهة، والألوان من متغيّرات المظهر.

    @param string $status
--}}
@php
    $variants = [
        'Under Review' => ['class' => 'po-status--review', 'icon' => 'fa-clock'],
        'approval' => ['class' => 'po-status--approved', 'icon' => 'fa-check-circle'],
        'disagree' => ['class' => 'po-status--rejected', 'icon' => 'fa-times-circle'],
        'Convert to Quotation' => ['class' => 'po-status--quotation', 'icon' => 'fa-file-signature'],
    ];

    // حالة غير معروفة تُعرض كما هي بدل أن تختفي الخانة فارغة
    $variant = $variants[$status] ?? ['class' => 'po-status--quotation', 'icon' => 'fa-circle'];
    $key = 'purchase_orders.statuses.'.$status;
    $label = __($key);
@endphp

<span class="po-status {{ $variant['class'] }}">
    <i class="fas {{ $variant['icon'] }}"></i>
    {{ $label === $key ? $status : $label }}
</span>
