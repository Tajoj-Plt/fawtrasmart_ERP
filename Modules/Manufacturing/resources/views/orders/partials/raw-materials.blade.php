{{-- المواد الخام — البنية المشتركة في `cost-section` --}}

@php
    $rows = $order->manufacturOrdersItem->whereNotNull('raw_product_id')->values();
@endphp

@include('manufacturing::orders.partials.cost-section', [
    'rows' => $rows,
    'title' => 'المواد الخام',
    'icon' => 'package',
    'kind' => 'raw',
    'headers' => ['المنتج', 'السعر', 'الكمية', 'المرحلة', 'المجموع'],
    'total' => $rows->sum('raw_total'),
])
