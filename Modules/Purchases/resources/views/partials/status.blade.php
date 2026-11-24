{{--
    شارة حالة مستند — مشتركة بين أقسام دورة المشتريات.

    الحالات نفسها في الأقسام الأربعة (`Under Review` / `approval` /
    `disagree`)، فالتسمية من `purchase_cycle.statuses` والألوان من متغيّرات
    المظهر المضبوطة في «مظهر النظام».

    @param string|int|null $status
--}}
@php
    $variants = [
        'Under Review' => ['class' => 'po-status--review', 'icon' => 'fa-clock'],
        'approval' => ['class' => 'po-status--approved', 'icon' => 'fa-check-circle'],
        'disagree' => ['class' => 'po-status--rejected', 'icon' => 'fa-times-circle'],
        'Convert to Quotation' => ['class' => 'po-status--quotation', 'icon' => 'fa-file-signature'],
        'Convert to Order' => ['class' => 'po-status--quotation', 'icon' => 'fa-dolly'],
        'convert invoice' => ['class' => 'po-status--quotation', 'icon' => 'fa-file-invoice-dollar'],
        'closed' => ['class' => 'po-status--rejected', 'icon' => 'fa-lock'],
        'cancelled' => ['class' => 'po-status--rejected', 'icon' => 'fa-ban'],
        // استلام المشتريات
        'pending' => ['class' => 'po-status--review', 'icon' => 'fa-clock'],
        'approved' => ['class' => 'po-status--approved', 'icon' => 'fa-check-circle'],
        'not_received' => ['class' => 'po-status--review', 'icon' => 'fa-truck'],
        'partially_received' => ['class' => 'po-status--quotation', 'icon' => 'fa-truck-loading'],
        'received' => ['class' => 'po-status--approved', 'icon' => 'fa-check-double'],
    ];

    $key = (string) $status;
    $variant = $variants[$key] ?? ['class' => 'po-status--quotation', 'icon' => 'fa-circle'];

    // حالة غير معروفة تُعرض كما هي بدل أن تختفي الخانة فارغة
    $translationKey = 'purchase_cycle.statuses.'.$key;
    $label = __($translationKey);
    $label = $label === $translationKey ? $key : $label;
@endphp

<span class="po-status {{ $variant['class'] }}">
    <i class="fas {{ $variant['icon'] }}"></i>
    {{ $label }}
</span>
