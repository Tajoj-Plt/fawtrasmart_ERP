{{--
    المنتج التام — إذون إضافة ناتج الإنتاج (المصدر 3).

    تبويبٌ جديد: لم يكن للمنتج التام موضعٌ يُعرض فيه، فكان يظهر تحت
    «مرتجعات المواد» لأن التجميع كان بنوع الإذن لا بمصدره.
--}}

@include('manufacturing::orders.partials.permits-tab', [
    'order' => $order,
    'id' => 'finished-goods',
    'source' => [\Modules\Manufacturing\Constants\ManufacturingSources::PERMIT_FINISHED_PRODUCT],
    'title' => 'المنتج التام',
    'icon' => 'package',
    'emptyText' => 'لم يُنتَج شيء من هذا الأمر بعد',
    'action' => '',
])
