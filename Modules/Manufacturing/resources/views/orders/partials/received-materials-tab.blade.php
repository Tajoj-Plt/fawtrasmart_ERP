{{-- المواد المستلمة — إذون صرف المواد الخام (المصدر 17) --}}

@include('manufacturing::orders.partials.permits-tab', [
    'order' => $order,
    'id' => 'received-materials',
    'source' => [\Modules\Manufacturing\Constants\ManufacturingSources::PERMIT_ISSUE_MATERIALS, 15],
    'title' => 'المواد المستلمة',
    'icon' => 'download',
    'emptyText' => 'لم تُستلَم مواد لهذا الأمر بعد',
    'action' => (\App\Enums\ManufacturOrderStatus::fromLegacy($order->status)?->canReceiveMaterials() ?? false)
        ? '<a href="' . route('manufacturing.orders.show-receive-materials', $order->id)
            . '" class="btn btn-sm btn-outline-primary"><i class="feather icon-plus"></i> استلام مواد</a>'
        : '',
])
