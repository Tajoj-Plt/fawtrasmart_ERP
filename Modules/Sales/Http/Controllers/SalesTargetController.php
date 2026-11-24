<?php

namespace Modules\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\SalesTarget;
use App\Models\TargetSetting;
use App\Services\TargetCalculationService;
use App\Services\TargetSettlementService;
use App\Support\CurrencySettings;
use App\Support\OperationLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\Sales\Http\Controllers\Concerns\ManagesTargetScreens;
use Modules\Sales\Http\Controllers\Concerns\RespectsAccountingPeriods;
use Modules\Sales\Support\TargetScreen;
use Throwable;

/**
 * أهداف المبيعات.
 *
 * **ما بقي هنا وما انتقل**
 *
 * دورةُ حياة الهدف — القائمة والإنشاء والعرض والتعديل والحذف — كانت
 * مكتوبةً هنا وفي `CollectionsTargetController` مرّتين متطابقتين، فانتقلت
 * إلى `ManagesTargetScreens`. ولم يبقَ في هذا الملفّ إلّا ما يخصّ
 * المبيعات فعلاً: احتسابُ صافي المبيعات، وتقريرُ الفترة، والتسوية.
 *
 * **العطب الذي كان يُسقط الشاشة**
 *
 * `show()` كان يستدعي `view('sales::targets.show')` — وذلك القالب غير
 * موجود في المشروع أصلاً. فالمسار مسجَّل، والمتحكّم يعمل، والضغطُ على
 * اسم أيّ هدفٍ يرتدّ صفحةَ ٥٠٠. أُنشئ القالب، وصار العرضُ يمرّ بالمشترَك.
 *
 * **إعدادات النظام التي صارت تسري على هذه الشاشة**
 *
 *   • *العملات* — كل مبلغ يمرّ بـ`CurrencySettings::format`، فيتبع رمزَ
 *     العملة وفواصلَها كما ضُبطت في «إعدادات النظام ← العملات». كانت
 *     `number_format(..., 2)` ثمّ «ر.س» مثبَّتةً في النصّ.
 *   • *الفترة المحاسبية* — التسوية تُنشئ سند صرفٍ وقيداً، فتُفحص بتاريخ
 *     نهاية الفترة قبل التنفيذ. كانت تُرحَّل إلى سنةٍ أُقفلت بلا مانع.
 *   • *الحسابات المحاسبية* — حسابُ العمولة من «إعدادات الأهداف».
 *   • *السجلّات* — كل إنشاءٍ وتعديلٍ وحذفٍ وتسوية يمرّ بـ`OperationLog`،
 *     فيُكتب في «سجلّ النشاط» و«سجلّ الأحداث»، والفشلُ في «سجلّ الأخطاء».
 *   • *اللغات* — لا نصّ عربيّ في الكود؛ الكلّ من `sales_targets`.
 *
 * @see \Modules\Sales\Http\Controllers\Concerns\ManagesTargetScreens
 * @see \Modules\Sales\Http\Controllers\CollectionsTargetController النظير
 */
class SalesTargetController extends Controller
{
    use ManagesTargetScreens;
    use RespectsAccountingPeriods;

    /*
        تسويةُ الهدف **تحتسب** العمولةَ و**تصرفها** (سندٌ وقيد) في خطوةٍ واحدة
        — فيلزمها فوق `sales.targets.settle` (على المسار) صلاحيتا مجموعة
        العمولات في الموارد البشرية: `commissions.create` و`commissions.disburse`.
        وحين لا «يتطلب اعتماداً» في إعدادات الأهداف يُعتمد القيدُ في الخطوة
        نفسها — فيلزم `commissions.approve` أيضاً (يُفحص في `settle`).

        «و» لا «أو»: كلُّ دورٍ يملك `sales.targets.settle` مُنح المجموعةَ كاملة
        في ترحيل الصلاحيات (2026_09_24_200000) — فلا يُحجب أحدٌ ممن كان يسوّي.
    */
    public function __construct()
    {
        $this->middleware('permission.role:commissions.create', ['only' => ['settle']]);
        $this->middleware('permission.role:commissions.disburse', ['only' => ['settle']]);
    }

    protected function targetMode(): string
    {
        return TargetScreen::SALES;
    }

    /**
     * طريقة احتساب الإنجاز من «إعدادات الأهداف».
     *
     * كانت الشاشة تعرض ثلاث طرق (صافي المبيعات / إجمالي الفواتير /
     * الفواتير المدفوعة) ويُحفظ اختيارها، ثمّ يُحتسب الصافي دائماً — هذا
     * المتحكّم لم يكن يقرأ `calculation_method` إطلاقاً.
     */
    private function salesCalculationMethod(): string
    {
        return TargetSetting::for(TargetSetting::TYPE_SALES)->calculation_method ?: 'net_sales';
    }

    /**
     * إنجاز الموظف خلال فترة، بطريقة الاحتساب المضبوطة في الإعدادات.
     *
     * كانت هنا نسخةٌ ثانيةٌ كاملة من `TargetCalculationService::getEmployeeNetSales()`
     * — نفس الاستعلامات الثلاثة حرفياً. فالتقرير والتسوية اليدوية يقرآن من
     * نسخةٍ، والتسوية المجدولة من أخرى، وأيّ تعديلٍ على إحداهما لا يبلغ
     * الثانية. صارت واحدةً، وهي التي تعرف طرق الاحتساب.
     *
     * @return array|float إذا تم تمرير $detailed=true يُرجع breakdown.
     */
    private function getEmployeeNetSales($userId, $startDate, $endDate, $detailed = false)
    {
        return app(TargetCalculationService::class)->getEmployeeNetSales(
            $userId,
            $startDate,
            $endDate,
            $this->salesCalculationMethod(),
            (bool) $detailed,
        );
    }

    /** إنجاز الدورة الجارية في القائمة وصفحة الهدف */
    protected function achievedFor(int $userId, string $from, string $to): float
    {
        return (float) $this->getEmployeeNetSales($userId, $from, $to);
    }

    /**
     * عرض صفحة التقرير (اختيار الفترة)
     */
    public function report($id)
    {
        $target = SalesTarget::with(['achievements.employee', 'settlements'])->findOrFail($id);

        return view('sales::targets.report', compact('target'));
    }

    /**
     * عرض نتائج التقرير
     */
    public function generateReport(Request $request, $id)
    {
        $target = SalesTarget::with(['achievements.employee', 'achievements.treasury'])->findOrFail($id);

        // قراءة التواريخ من request (POST/GET params) ثم session كـ fallback
        $fromDate = $request->input('from_date') ?: session('from_date');
        $toDate = $request->input('to_date') ?: session('to_date');

        /* لا حدَّ أدنى لتاريخ «من» من تاريخ إنشاء الهدف (أُزيل 2026-09-26):
           هدفٌ أُنشئ في السادس والعشرين لقياس شهرٍ كاملٍ كان يرفض «هذا
           الشهر» ولا يقبل إلا «اليوم»، فلا تظهر تحصيلاتُ الشهر ولا تُسوّى.
           المدّةُ قرارُ من يطلب التقرير — والتسويةُ المجدولة نفسُها لا
           تعرف تاريخ الإنشاء. */

        // إن لم تتوفر التواريخ ⇒ ارجع لصفحة اختيار الفترة (لا تسبب redirect loop)
        if (! $fromDate || ! $toDate) {
            return redirect()
                ->route('sales-targets.report', $id)
                ->with('warning', __('sales_targets.report.choose_period_first'));
        }

        // التحقق من صحة التواريخ (إن كانت في POST request — أي من form الاختيار)
        if ($request->isMethod('post')) {
            $rules = [
                'from_date' => 'required|date',
                'to_date' => 'required|date|after_or_equal:from_date',
            ];
            $request->validate($rules, [
                'to_date.after_or_equal' => __('sales_targets.report.to_before_from'),
            ]);
        }

        // مدّةُ الهدف تحدّ التقرير: ما خرج عنها لا يُقاس عليه ولا يُصرف عنه
        if ($outside = $this->outsideTargetPeriod($target, $fromDate, $toDate)) {
            return redirect()->route('sales-targets.report', $id)->with('warning', $outside);
        }

        // من سُوّيت عمولتُه لهذه الفترة — لكلّ موظفٍ وحده لا للهدف كلّه
        $settledUsers = $this->settledUsersForPeriod($target, $fromDate, $toDate);

        // فلتر الموظفين
        $achievements = $target->achievements();
        if ($request->filled('employee_id')) {
            $achievements = $achievements->where('employee_id', $request->employee_id);
        }
        $achievements = $achievements->get();

        // حساب الإنجازات لكل موظف على أساس صافي المبيعات
        $results = [];
        foreach ($achievements as $achievement) {
            $breakdown = $this->getEmployeeNetSales(
                $achievement->employee_id,
                $fromDate,
                $toDate,
                true // detailed
            );
            // `achieved` هو ما تُحتسب به الطريقة المختارة — و`net_sales`
            // يبقى في الجدول للعرض مهما كانت الطريقة
            $achievedAmount = (float) $breakdown['achieved'];

            $percentage = $achievement->target_amount > 0
                ? ($achievedAmount / $achievement->target_amount) * 100
                : 0;

            // حساب العمولة باستخدام النظام المتدرج
            $commissionAmount = $target->calculateCommission($achievedAmount, $achievement->target_amount);

            // تحديد المستوى المطبق
            $appliedTier = null;
            if ($target->commissionTiers && $target->commissionTiers->count() > 0) {
                foreach ($target->commissionTiers->sortByDesc('achievement_percentage') as $tier) {
                    if ($percentage >= $tier->achievement_percentage) {
                        $appliedTier = $tier;
                        break;
                    }
                }
            }

            $results[] = [
                'employee_name' => $achievement->employee->name,
                'settled' => isset($settledUsers[(int) $achievement->employee_id]),
                'treasury_name' => $achievement->treasury?->name ?: __('sales_targets.fields.no_treasury'),
                'target_amount' => (float) $achievement->target_amount,
                'gross_sales' => (float) $breakdown['gross_sales'],
                'returns' => (float) $breakdown['returns'],
                'credit_notes' => (float) $breakdown['credit_notes'],
                'achieved_amount' => $achievedAmount, // = net_sales
                'remaining' => max(0, (float) $achievement->target_amount - $achievedAmount),
                'percentage' => $percentage,
                'commission_percentage' => $appliedTier ? $appliedTier->commission_percentage : $target->commission_percentage,
                'commission_amount' => $commissionAmount,
                'applied_tier' => $appliedTier ? __('sales_targets.report.applied_tier', [
                    'achievement' => $appliedTier->achievement_percentage,
                    'commission' => $appliedTier->commission_percentage,
                ]) : null,
            ];
        }

        // حساب الملخص الإجمالي
        $summary = [
            'total_target' => collect($results)->sum('target_amount'),
            'total_gross_sales' => collect($results)->sum('gross_sales'),
            'total_returns' => collect($results)->sum('returns'),
            'total_credit_notes' => collect($results)->sum('credit_notes'),
            'total_achieved' => collect($results)->sum('achieved_amount'),
            'total_remaining' => collect($results)->sum('remaining'),
            'average_percentage' => collect($results)->avg('percentage') ?? 0,
            'total_commission' => collect($results)->sum('commission_amount'),
        ];

        // الحالة من الموظفين المعروضين: من لم يبلغ مستوًى لا ينتظر تسوية،
        // فالفترة «مسوّاة» حين لا يبقى مستحقٌّ لم يُصرف له
        $settledCount = collect($results)->where('settled', true)->count();
        $pendingDue = collect($results)->where('settled', false)->where('commission_amount', '>', 0)->count();
        $isSettled = $settledCount > 0 && $pendingDue === 0;
        $partlySettled = $settledCount > 0 && $pendingDue > 0;

        $method = $this->salesCalculationMethod();

        return view('sales::targets.report-results', [
            'target' => $target,
            'results' => $results,
            'summary' => $summary,
            'fromDate' => $fromDate,
            'toDate' => $toDate,
            'isSettled' => $isSettled,
            'partlySettled' => $partlySettled,
            'method' => $method,
            // نفس تسمية الشاشة التي يُختار منها — لا نصٌّ ثانٍ يوازيها
            'methodLabel' => __('sales_settings.targets.methods.'.$method),
            'employeeFilter' => $request->filled('employee_id') ? (int) $request->employee_id : null,
        ]);
    }

    /**
     * تسوية الفترة: عمولةُ كل موظفٍ بلغ مستوًى، سندُ صرفٍ وقيدٌ لكلٍّ منها.
     *
     * **ثلاثة فحوصٍ تسبق التنفيذ**، وأيٌّ منها يردّ العملية بسببٍ مسمّى:
     *
     *   1. حساب العمولة مضبوط في «إعدادات الأهداف» — بلا حسابٍ لا قيد.
     *   2. الفترة المحاسبية لتاريخ التسوية مفتوحة. **هذا الفحص لم يكن
     *      موجوداً**: كانت التسوية تُرحَّل قيداً إلى سنةٍ أُقفلت وأُصدرت
     *      قوائمها المالية، وهو ما يمنعه الإقفال أصلاً. ومن ملك صلاحية
     *      التجاوز المضبوطة في شاشة الفترات يمرّ كما كان.
     *   3. لا تسوية سابقة تتداخل مع هذه الفترة.
     */
    public function settle(Request $request, $id, TargetSettlementService $service)
    {
        $request->validate([
            'from_date' => 'required|date',
            'to_date' => 'required|date|after_or_equal:from_date',
            'notes' => 'nullable|string|max:2000',
            // موظفٌ بعينه: ما صُفّي عليه التقريرُ هو ما يُسوّى — كان الزرُّ
            // يصرف لكلّ الموظفين وإن عُرض واحد
            'employee_id' => 'nullable|integer',
        ]);

        $screen = $this->screen();
        $target = SalesTarget::with(['achievements.employee', 'commissionTiers'])->findOrFail($id);

        $fromDate = $request->input('from_date');
        $toDate = $request->input('to_date');

        $setting = TargetSetting::for(TargetSetting::TYPE_SALES);

        // بلا «يتطلب اعتماداً» يُعتمد قيدُ العمولة هنا — وذاك «اعتماد العمولة»
        if (! $setting->require_approval && ! hasPermission('commissions.approve')) {
            return back()->with('error', 'التسويةُ تعتمد قيدَ العمولة مباشرةً («يتطلب اعتماداً» غير مفعّل في إعدادات الأهداف) — ويلزمها صلاحية «اعتماد العمولة».');
        }

        /* «تفعيل :type» في إعدادات الأهداف — نصّه يَعِد بأنّ الإيقاف
         * «يُجمّد كل الأهداف من هذا النوع ولا يُحتسب لها إنجاز»، وكان
         * `enabled` يُقرأ في `ProcessTargetSettlements` وحده: التسوية
         * المجدولة تقف، واليدوية تمضي وتصرف عمولةً وتُرحّل قيداً. */
        if (! $setting->enabled) {
            return back()->with('error', __('sales_targets.messages.type_disabled'));
        }

        // الهدف الموقوف لا يُسوّى — كما تتخطّاه التسوية المجدولة
        if ($target->status !== 'active') {
            return back()->with('error', __('sales_targets.messages.settle_inactive'));
        }

        // مدّةُ الهدف قيدٌ على الصرف اليدويّ كما هي على المجدول
        if ($outside = $this->outsideTargetPeriod($target, $fromDate, $toDate)) {
            return back()->with('error', $outside);
        }

        // 1) حساب العمولة
        if (empty($setting->commission_account_id)) {
            return back()->with('error', __('sales_targets.messages.commission_account_missing'));
        }

        // 2) الفترة المحاسبية — بتاريخ نهاية المدّة، فهو تاريخ القيد
        if ($reason = $this->accountingPeriodBlock($toDate)) {
            // `blockReason` تُرجع الرسالة مترجَمةً جاهزة، لا مفتاحاً
            return back()->with('error', __('sales_targets.messages.period_blocked', [
                'reason' => $reason,
            ]));
        }

        // 3) من سُوّيت عمولتُه عن فترةٍ متداخلة يُستثنى وحده — ويُسوّى الباقون
        $settledUsers = $this->settledUsersForPeriod($target, $fromDate, $toDate);
        $onlyUser = (int) $request->input('employee_id') ?: null;
        $pending = $target->achievements
            ->when($onlyUser, fn ($c) => $c->where('employee_id', $onlyUser))
            ->reject(fn ($a) => isset($settledUsers[(int) $a->employee_id]));

        if ($pending->isEmpty()) {
            return back()->with('error', __('sales_targets.messages.settle_duplicate'));
        }

        $rows = [];
        foreach ($pending as $achievement) {
            // بالطريقة المضبوطة في الإعدادات — كانت الصافي دائماً
            $achieved = $this->getEmployeeNetSales($achievement->employee_id, $fromDate, $toDate);
            $commission = $target->calculateCommission($achieved, $achievement->target_amount);

            if ($commission > 0) {
                $rows[] = [
                    'user_id' => $achievement->employee_id,
                    'achieved_amount' => $achieved,
                    'commission_amount' => $commission,
                ];
            }
        }

        if ($rows === []) {
            // من استحقّ سُوّي والباقون لم يبلغوا مستوًى — الفترة مسوّاة لا «بلا مستحقّ»
            return back()->with('error', __($settledUsers !== []
                ? 'sales_targets.messages.settle_duplicate'
                : 'sales_targets.messages.settle_nothing_due'));
        }

        try {
            $result = $service->settle(
                $target,
                $fromDate,
                $toDate,
                $rows,
                $request->input('notes'),
                auth()->id(),
                false
            );
        } catch (Throwable $e) {
            OperationLog::failure($e, $screen['log_module'], 'settle', $target);
            Log::error('تعذّرت تسوية هدف المبيعات', ['target_id' => $id, 'error' => $e->getMessage()]);

            return back()->with('error', __('sales_targets.messages.settle_failed'));
        }

        OperationLog::record('settled', $screen['log_module'], $target, __('sales_targets.log.settled', [
            'type' => $screen['one'],
            'name' => $target->name,
            'from' => $fromDate,
            'to' => $toDate,
            'total' => CurrencySettings::format((float) $result['total_commission']),
        ]));

        $message = __('sales_targets.messages.settled', [
            'count' => $result['settlements_created'],
            'total' => CurrencySettings::format((float) $result['total_commission']),
        ]);

        if (! empty($result['errors'])) {
            $message .= ' — '.implode(__('sales_settings.ui.list_separator'), $result['errors']);
        }

        return redirect()->route('sales-targets.generate-report', $id)->with([
            'success' => $message,
            'from_date' => $fromDate,
            'to_date' => $toDate,
        ]);
    }
}
