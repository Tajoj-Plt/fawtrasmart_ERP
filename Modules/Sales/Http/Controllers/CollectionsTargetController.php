<?php

namespace Modules\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\CollectionsTarget;
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
 * أهداف التحصيلات.
 *
 * يحتسب الإنجاز عبر `TargetCalculationService::getEmployeeCollections`:
 *   الفواتير النقدية + سندات القبض − المرتجعات النقدية − الإشعارات الدائنة النقدية،
 * حسب `calculation_method` في `TargetSetting::TYPE_COLLECTIONS`.
 *
 * **ما بقي هنا وما انتقل**
 *
 * كان هذا الملفّ نسخةً من `SalesTargetController` بفارق اسم الصنف: نفس
 * السبعة توابع، ونفس التحقّق، ونفس الحلقات — ونفس العيوب، إلّا واحداً
 * أُصلح في أحدهما دون الآخر. فانتقلت دورةُ الحياة كلّها إلى
 * `ManagesTargetScreens`، ولم يبقَ هنا إلّا ما يخصّ التحصيل: التقرير
 * بطريقة الحساب المضبوطة، والتسوية.
 *
 * و`show()` هنا كان يستدعي `sales::targets.collections.show` — قالبٌ لا
 * وجود له، فكل عرضٍ لهدف تحصيلٍ كان صفحةَ ٥٠٠. أُنشئ وصار مشترَكاً.
 *
 * @see \Modules\Sales\Http\Controllers\Concerns\ManagesTargetScreens
 * @see \Modules\Sales\Http\Controllers\SalesTargetController النظير
 */
class CollectionsTargetController extends Controller
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
    public function __construct(
        private TargetCalculationService $calc
    ) {
        $this->middleware('permission.role:commissions.create', ['only' => ['settle']]);
        $this->middleware('permission.role:commissions.disburse', ['only' => ['settle']]);
    }

    protected function targetMode(): string
    {
        return TargetScreen::COLLECTIONS;
    }

    /** إنجاز الدورة الجارية في القائمة وصفحة الهدف */
    protected function achievedFor(int $userId, string $from, string $to): float
    {
        $method = TargetSetting::for(TargetSetting::TYPE_COLLECTIONS)->calculation_method ?: 'total_collected';

        return (float) $this->calc->getEmployeeCollections($userId, $from, $to, $method);
    }

    public function report($id)
    {
        $target = CollectionsTarget::with(['achievements.employee', 'settlements'])->findOrFail($id);

        return view('sales::targets.collections.report', compact('target'));
    }

    /**
     * توليد التقرير لفترة محددة.
     * يستخدم TargetCalculationService::getEmployeeCollections مع calculation_method من الإعدادات.
     */
    public function generateReport(Request $request, $id)
    {
        $target = CollectionsTarget::with(['achievements.employee', 'achievements.treasury'])->findOrFail($id);

        $fromDate = $request->input('from_date') ?: session('from_date');
        $toDate = $request->input('to_date') ?: session('to_date');

        /* لا حدَّ أدنى لتاريخ «من» من تاريخ إنشاء الهدف (أُزيل 2026-09-26):
           هدفٌ أُنشئ في السادس والعشرين لقياس شهرٍ كاملٍ كان يرفض «هذا
           الشهر» ولا يقبل إلا «اليوم»، فلا تظهر تحصيلاتُ الشهر ولا تُسوّى.
           المدّةُ قرارُ من يطلب التقرير — والتسويةُ المجدولة نفسُها لا
           تعرف تاريخ الإنشاء. */

        if (! $fromDate || ! $toDate) {
            return redirect()->route('collections-targets.report', $id)
                ->with('warning', __('sales_targets.report.choose_period_first'));
        }

        if ($request->isMethod('post')) {
            $rules = [
                'from_date' => 'required|date',
                'to_date' => 'required|date|after_or_equal:from_date',
            ];
            $request->validate($rules, [
                'to_date.after_or_equal' => __('sales_targets.report.to_before_from'),
            ]);
        }

        // الإعدادات
        $setting = TargetSetting::for(TargetSetting::TYPE_COLLECTIONS);
        $method = $setting->calculation_method ?: 'total_collected';

        // مدّةُ الهدف تحدّ التقرير: ما خرج عنها لا يُقاس عليه ولا يُصرف عنه
        if ($outside = $this->outsideTargetPeriod($target, $fromDate, $toDate)) {
            return redirect()->route('collections-targets.report', $id)->with('warning', $outside);
        }

        // من سُوّيت عمولتُه لهذه الفترة — لكلّ موظفٍ وحده لا للهدف كلّه
        $settledUsers = $this->settledUsersForPeriod($target, $fromDate, $toDate);

        $achievements = $target->achievements();
        if ($request->filled('employee_id')) {
            $achievements = $achievements->where('employee_id', $request->employee_id);
        }
        $achievements = $achievements->get();

        $results = [];
        foreach ($achievements as $achievement) {
            $breakdown = $this->calc->getEmployeeCollections(
                $achievement->employee_id,
                $fromDate,
                $toDate,
                $method,
                true // detailed
            );
            $achievedAmount = (float) $breakdown['total_collected'];

            $percentage = $achievement->target_amount > 0
                ? ($achievedAmount / $achievement->target_amount) * 100
                : 0;

            $commissionAmount = $target->calculateCommission($achievedAmount, $achievement->target_amount);

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
                'invoice_payments' => (float) $breakdown['invoice_payments'],
                'receipts' => (float) $breakdown['receipts'],
                'achieved_amount' => $achievedAmount,
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

        $summary = [
            'total_target' => collect($results)->sum('target_amount'),
            'total_invoice_payments' => collect($results)->sum('invoice_payments'),
            'total_receipts' => collect($results)->sum('receipts'),
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

        // التسمية من ملفّ اللغة — نفسها المعروضة في شاشة الإعدادات
        $methodLabel = __('sales_settings.targets.methods.'.$method);

        $employeeFilter = $request->filled('employee_id') ? (int) $request->employee_id : null;

        return view('sales::targets.collections.report-results', compact(
            'target', 'results', 'summary', 'fromDate', 'toDate', 'isSettled', 'partlySettled', 'methodLabel', 'method', 'employeeFilter'
        ));
    }

    /**
     * تسوية الفترة — نظير `SalesTargetController::settle` بمصدر إنجازٍ آخر.
     *
     * ثلاثة فحوصٍ تسبق التنفيذ: حسابُ العمولة مضبوط، والفترة المحاسبية
     * لتاريخ التسوية مفتوحة (**فحصٌ لم يكن موجوداً** — كان القيد يُرحَّل
     * إلى سنةٍ مقفلة)، ولا تسوية سابقة تتداخل مع المدّة.
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
        $target = CollectionsTarget::with(['achievements.employee', 'commissionTiers'])->findOrFail($id);

        $fromDate = $request->input('from_date');
        $toDate = $request->input('to_date');

        $setting = TargetSetting::for(TargetSetting::TYPE_COLLECTIONS);

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

        if (empty($setting->commission_account_id)) {
            return back()->with('error', __('sales_targets.messages.commission_account_missing'));
        }

        // `blockReason` تُرجع الرسالة مترجَمةً جاهزة، لا مفتاحاً
        if ($reason = $this->accountingPeriodBlock($toDate)) {
            return back()->with('error', __('sales_targets.messages.period_blocked', [
                'reason' => $reason,
            ]));
        }

        // من سُوّيت عمولتُه عن فترةٍ متداخلة يُستثنى وحده — ويُسوّى الباقون
        $settledUsers = $this->settledUsersForPeriod($target, $fromDate, $toDate);
        $onlyUser = (int) $request->input('employee_id') ?: null;
        $pending = $target->achievements
            ->when($onlyUser, fn ($c) => $c->where('employee_id', $onlyUser))
            ->reject(fn ($a) => isset($settledUsers[(int) $a->employee_id]));

        if ($pending->isEmpty()) {
            return back()->with('error', __('sales_targets.messages.settle_duplicate'));
        }

        // الافتراضي هو افتراضي الخدمة نفسها — لا قيمةٌ أخرى تُغيّر
        // الاحتساب صامتةً حين لا يكون الإعداد مضبوطاً
        $method = $setting->calculation_method ?: 'total_collected';

        $rows = [];
        foreach ($pending as $achievement) {
            $achieved = (float) $this->calc->getEmployeeCollections(
                $achievement->employee_id,
                $fromDate,
                $toDate,
                $method
            );

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
            Log::error('تعذّرت تسوية هدف التحصيلات', ['target_id' => $id, 'error' => $e->getMessage()]);

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

        return redirect()->route('collections-targets.generate-report', $id)->with([
            'success' => $message,
            'from_date' => $fromDate,
            'to_date' => $toDate,
        ]);
    }
}
