<?php

namespace Modules\HR\Http\Controllers\Salaries;

use App\Http\Controllers\Controller;

/**
 * «النماذج ذات الصلة» — أُحيلت إلى «بنود الرواتب» (2026-09-24).
 *
 * كانت الشاشتان قالبين ثابتين بلا جدولٍ ولا حفظ: صفٌّ مكتوبٌ باليد («اب —
 * نشط»)، ونموذجُ إضافةٍ بلا `action`، وزرُّ «حفظ» خارج النموذج. لا بلاطةَ
 * ولا قائمةَ تقود إليهما، ولا إعدادَ يقرؤه شيء. فالمساران يبقيان (روابطُ
 * محفوظة لا تنكسر) ويُحيلان إلى المكان الحقيقي لبنود الراتب.
 */
class RelatedModelsController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission.role:salary_items.index', ['only' => ['index']]);
        $this->middleware('permission.role:salary_items.create', ['only' => ['create']]);
    }

    public function index()
    {
        return redirect()->route('SalaryItems.index')
            ->with('error', '«النماذج ذات الصلة» لم تكن مطبّقة — بنودُ الراتب تُدار من هنا.');
    }

    public function create()
    {
        return $this->index();
    }
}
