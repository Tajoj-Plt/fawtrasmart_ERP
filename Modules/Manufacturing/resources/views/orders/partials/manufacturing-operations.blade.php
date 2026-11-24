{{-- عمليات التصنيع — البنية المشتركة في `cost-section` --}}

@php
    $rows = $order->manufacturOrdersItem->whereNotNull('workstation_id')->values();
@endphp

@include('manufacturing::orders.partials.cost-section', [
    'rows' => $rows,
    'title' => 'عمليات التصنيع',
    'icon' => 'settings',
    'kind' => 'operations',
    'headers' => ['محطة العمل', 'نوع التكلفة', 'وقت التشغيل', 'المرحلة', 'الوصف', 'المجموع'],
    'total' => $rows->sum('manu_total_cost'),
])
