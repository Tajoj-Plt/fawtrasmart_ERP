{{-- المواد الهالكة — البنية المشتركة في `cost-section` --}}

@php
    $rows = $order->manufacturOrdersItem->whereNotNull('end_life_product_id')->values();
@endphp

@include('manufacturing::orders.partials.cost-section', [
    'rows' => $rows,
    'title' => 'المواد الهالكة',
    'icon' => 'trash-2',
    'kind' => 'waste',
    'headers' => ['المنتج', 'السعر', 'الكمية', 'المرحلة', 'المجموع'],
    'total' => $rows->sum('end_life_total'),
])
