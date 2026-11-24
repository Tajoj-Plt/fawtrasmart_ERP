{{--
    الهالك والنواتج الثانوية — إذون إضافة الهالك (المصدر 4).

    تبويبٌ جديد للسبب نفسه في «المنتج التام»: كان يظهر تحت «مرتجعات المواد».
--}}

@include('manufacturing::orders.partials.permits-tab', [
    'order' => $order,
    'id' => 'waste',
    'source' => [\Modules\Manufacturing\Constants\ManufacturingSources::PERMIT_WASTE],
    'title' => 'الهالك والنواتج الثانوية',
    'icon' => 'trash-2',
    'emptyText' => 'لا هالك مسجَّل لهذا الأمر',
    'action' => '',
])
