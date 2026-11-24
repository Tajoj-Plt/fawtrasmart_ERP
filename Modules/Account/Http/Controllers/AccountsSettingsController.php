<?php

namespace Modules\Account\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\AccountingGeneralSetting;
use App\Models\AccountingPeriod;
use App\Models\AccountingSetting;
use App\Models\FiscalYear;
use App\Support\OperationLog;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AccountsSettingsController extends Controller
{
    public function __construct()
    {
        // صلاحيات إعدادات الحسابات — الكتالوج الجديد account.settings.* /
        // settings.accounting_periods.*
        $this->middleware('permission.role:account.settings.view', ['only' => ['index']]);
        $this->middleware('permission.role:account.settings.fiscal_years', ['only' => ['financial_years', 'store_financial_year']]);
        $this->middleware('permission.role:account.settings.routing', ['only' => ['accounts_routing']]);
        $this->middleware('permission.role:account.settings.general', ['only' => ['accounting_general', 'store_accounting_general']]);
        $this->middleware('permission.role:settings.accounting_periods.view', ['only' => ['closed_periods']]);
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return view('account::accounts_settings.index');
    }

    /**
     * Show the form for creating a new resource.
     */
    // الفترات المالية
    public function financial_years()
    {
        return view('account::accounts_settings.financial_years', [
            'years' => FiscalYear::withCount('periods')->orderByDesc('start_date')->get(),
            'currentYear' => FiscalYear::current(),
        ]);
    }

    /**
     * الفترات المقفلة.
     *
     * كانت الشاشة صفّين من البيانات الوهمية مكتوبَين في القالب — «الربع
     * الأول» و«الربع الثاني» بتواريخ 2024 ثابتة — وأزرارُ التعديل والحذف
     * `href="#"`. تعرض الآن فترات النظام الحقيقية من `accounting_periods`.
     */
    public function closed_periods(Request $request)
    {
        $periods = AccountingPeriod::query()
            ->with('fiscalYear')
            ->when($request->filled('from_date'), fn ($q) => $q->whereDate('end_date', '>=', $request->from_date))
            ->when($request->filled('to_date'), fn ($q) => $q->whereDate('start_date', '<=', $request->to_date))
            ->when($request->filled('status'), function ($q) use ($request) {
                // «نشط» هنا تعني مفتوحة: الفترة المقفلة لا تُقبل فيها حركة
                return $request->status === '1'
                    ? $q->where('status', 'open')
                    : $q->where('status', '!=', 'open');
            })
            ->orderByDesc('start_date')
            ->get();

        return view('account::accounts_settings.closed_periods', [
            'periods' => $periods,
        ]);
    }

    /**
     * توجيه الحسابات.
     *
     * كانت الشاشة نموذجاً وهمياً: قوائم بلا `name` وخياراتٌ مكتوبة في
     * القالب («411 - المبيعات») و`<form action="#">`. والنظام الحقيقي
     * للتوجيه موجود — جدول `accounting_settings` وشاشته في الإعدادات —
     * فتعرض هذه ما رُبط فعلاً وتُحيل التعديل إلى مصدره.
     */
    public function accounts_routing()
    {
        $settings = AccountingSetting::query()
            ->with('account')
            ->orderBy('group')
            ->orderBy('name')
            ->get()
            ->groupBy(fn ($setting) => $setting->group ?: 'أخرى');

        return view('account::accounts_settings.accounts_routing', [
            'groups' => $settings,
        ]);
    }

    public function accounting_general()
    {
        /*
            كان المفتاح `settings` — وهو الاسم نفسه الذي يقرأ به الشريط
            الجانبي خريطةَ تفعيل التطبيقات. فيصل النموذج إلى الشريط بدلاً
            من الخريطة، وتختفي ثلاثُ مجموعاتٍ من القائمة في هذه الشاشة وحدها.
        */
        return view('account::accounts_settings.accounting_general', [
            'generalSettings' => AccountingGeneralSetting::current(),
        ]);
    }

    /**
     * حفظ ضوابط القيد العامة.
     *
     * كان النموذج `<form action="#">` وزرُّ الحفظ خارجه أصلاً: الشاشة لا
     * تحفظ ولا تحاول، وتُفتح دائماً على القيم الافتراضية.
     */
    public function store_accounting_general(Request $request)
    {
        $settings = AccountingGeneralSetting::current();

        $settings->update([
            'show_cost_center' => $request->boolean('show_cost_center'),
            'show_tax' => $request->boolean('show_tax'),
            // `update_exchange_rate` أُزيل من الشاشة: كل العملات بسعر 1.000000
            // فالضابط يَعِد بقراءةِ لا شيء. لا يُكتب هنا كي لا يُصفَّر بطلبٍ
            // لا يحمل الحقل أصلاً.
            'assign_tags' => $request->boolean('assign_tags'),
            'updated_by' => auth()->id(),
        ]);

        OperationLog::record(
            'updated',
            'finance_log',
            $settings->id,
            'تم تحديث ضوابط القيد العامة',
            OperationLog::diff($settings, ['show_cost_center', 'show_tax', 'assign_tags'])
        );

        return redirect()->route('accounts_settings.accounting_general')
            ->with('success', 'تم حفظ الإعدادات بنجاح');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Store a newly created financial year.
     */
    /**
     * حفظ سنة مالية.
     *
     * **ما كان:** الدالّة تتحقّق من المدخلات ثم تُعيد التوجيه برسالة «تم
     * حفظ الفترة المالية بنجاح» — والحفظ نفسه سطرٌ معطَّل بالتعليق. أي أن
     * الشاشة **تكذب**: تقول للمستخدم إنّ سنته حُفظت ولا شيء يُكتب.
     *
     * الجدول موجود (`fiscal_years`) والنموذج موجود؛ لم يكن ينقص إلا الكتابة.
     */
    public function store_financial_year(Request $request)
    {
        $data = $request->validate([
            'name' => 'nullable|string|max:120',
            'start_date' => 'required|date',
            'period' => 'required|integer|in:3,6,12',
            'end_date' => 'required|date|after:start_date',
            'description' => 'nullable|string|max:500',
        ], [
            'start_date.required' => 'تاريخ البدء مطلوب',
            'period.required' => 'مدّة السنة مطلوبة',
            'period.in' => 'المدّة يجب أن تكون 3 أو 6 أو 12 شهراً',
            'end_date.required' => 'تاريخ الانتهاء مطلوب',
            'end_date.after' => 'تاريخ الانتهاء يجب أن يلي تاريخ البدء',
        ]);

        $start = Carbon::parse($data['start_date'])->startOfDay();
        $end = Carbon::parse($data['end_date'])->endOfDay();

        // سنتان متداخلتان تعنيان تاريخاً ينتمي إلى اثنتين — وكل ما يُبنى
        // على «السنة المالية للتاريخ» يصير غير محدَّد
        $overlap = FiscalYear::whereDate('start_date', '<=', $end)
            ->whereDate('end_date', '>=', $start)
            ->first();

        if ($overlap) {
            return back()->withInput()->withErrors([
                'start_date' => 'المدى يتداخل مع السنة المالية «'.$overlap->name.'» ('
                    .$overlap->start_date->toDateString().' — '.$overlap->end_date->toDateString().').',
            ]);
        }

        $year = FiscalYear::create([
            'name' => $data['name'] ?: 'سنة مالية '.$start->year,
            'start_date' => $start,
            'end_date' => $end,
            'status' => 'open',
            // أول سنة تُنشأ هي الجارية، وإلا بقي النظام بلا سنةٍ حالية
            'is_current' => ! FiscalYear::where('is_current', true)->exists(),
            'notes' => $data['description'] ?? null,
        ]);

        OperationLog::record(
            'created',
            'finance_log',
            $year->id,
            'تم إنشاء السنة المالية <strong>'.$year->name.'</strong> ('
                .$start->toDateString().' — '.$end->toDateString().')'
        );

        return redirect()->route('accounts_settings.financial_years')
            ->with('success', 'تم حفظ السنة المالية بنجاح');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
