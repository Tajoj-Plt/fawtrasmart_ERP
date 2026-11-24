{{-- المصروفات — البنية المشتركة في `cost-section` --}}

@php
    $rows = $order->manufacturOrdersItem->whereNotNull('expenses_account_id')->values();
@endphp

@include('manufacturing::orders.partials.cost-section', [
    'rows' => $rows,
    'title' => 'المصروفات',
    'icon' => 'dollar-sign',
    'kind' => 'expenses',
    'headers' => ['الحساب', 'نوع التكلفة', 'المبلغ', 'المرحلة', 'الوصف', 'المجموع'],
    'total' => $rows->sum('expenses_total'),
])
