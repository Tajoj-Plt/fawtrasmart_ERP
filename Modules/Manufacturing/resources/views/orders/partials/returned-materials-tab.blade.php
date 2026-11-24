{{--
    مرتجعات المواد — إذون إرجاع المواد الخام (المصدر 16) وحدها.

    كان هذا التبويب يمرّر `type => 1` أي «إذن إضافة» — وهو نوعٌ يشمل
    ثلاثة مصادر: المنتج التام (3) والهالك (4) والمرتجع (16). فكان يعرض
    ناتج الإنتاج والهالك تحت عنوان «مرتجعات المواد»، ولم يكن للمنتج التام
    تبويبٌ باسمه أصلاً.
--}}

@include('manufacturing::orders.partials.permits-tab', [
    'order' => $order,
    'id' => 'returned-materials',
    'source' => [\Modules\Manufacturing\Constants\ManufacturingSources::PERMIT_RETURNED_MATERIALS],
    'title' => 'مرتجعات المواد',
    'icon' => 'rotate-ccw',
    'emptyText' => 'لم تُرجَع مواد من هذا الأمر',
    'action' => (\App\Enums\ManufacturOrderStatus::fromLegacy($order->status)?->canReturnMaterials() ?? false)
        ? '<a href="' . route('manufacturing.orders.show-return-materials', $order->id)
            . '" class="btn btn-sm btn-outline-warning"><i class="feather icon-rotate-ccw"></i> إرجاع مواد</a>'
        : '',
])
