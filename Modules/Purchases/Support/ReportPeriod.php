<?php

namespace Modules\Purchases\Support;

/**
 * فترة تقارير المشتريات — وارثةٌ من النواة المشتركة.
 *
 * المنطق كلّه في `App\Support\ReportPeriod`؛ وهذا الصنف باقٍ لأن شيفرة
 * المشتريات كلّها تُلمِّح إليه بالاسم (`periodTotals(ReportPeriod $period)`
 * وأخواتها). والمصنع في الأب يستعمل `new static()` فيعيد هذا الصنف حين
 * يُستدعى عليه — فتبقى تلميحات النوع صحيحة بلا تعديل.
 */
class ReportPeriod extends \App\Support\ReportPeriod {}
