<?php

namespace Modules\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\CronJob;
use App\Models\TargetSetting;
use App\Services\Cron\CronMonitoringService;
use App\Services\Targets\TargetSettlementSchedule;
use App\Support\AccountingSettings;
use App\Support\OperationLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * إعدادات نظام الأهداف (مبيعات / تحصيلات).
 *
 * ما أُصلح هنا:
 *
 *   • **تسميات مكتوبة عربيةً في المتحكّم** — `$calcMethods` و`$frequencies`
 *     كانتا مصفوفتين بنصوص عربية داخل الدالّة، فالشاشة الإنجليزية تعرضها
 *     عربيةً ولا سبيل إلى ترجمتها. انتقلتا إلى `sales_settings.targets`.
 *
 *   • **السجلّات الثلاثة** — حفظُ الإعدادات يمسّ حساب العمولة وخزينة الصرف
 *     وجدولة التسوية التلقائية، أي أنّه يُغيّر إلى أين تذهب أموال، ولم يكن
 *     يترك أثراً في «سجلّ النشاطات» ولا «سجلّ الأحداث».
 *
 *   • **الفشل مردود لا مرميّ** — `save()` كان بلا حارس.
 *
 *   • **رسالة النجاح مكتوبة في الشيفرة** — `'تم حفظ إعدادات '.$label.' بنجاح'`.
 *
 *   • **`abort(404, 'نوع الهدف غير مدعوم')`** — نصّ عربيّ في صفحة خطأ.
 *
 * ملاحظة على القالب: كانت أنماط الشاشة كلّها في `@section('css')`، و
 * `sales::master` لا يعرض هذا القسم أصلاً (فيه `@stack('styles')` وحدها).
 * فالبطاقات ومفاتيح التبديل والتبويبات كانت تخرج بلا نمط — وهذا ما يبدو
 * «شريطاً جانبياً معطوباً» في البند 4 من تقرير الإصلاح. القالب الآن على
 * `sales::partials.settings-theme` وهو يحقن نمطه داخل المحتوى.
 */
class TargetSettingsController extends Controller
{
    /** مفتاح الوحدة في السجلّات — به يربط السجلّ سطرَه بهذه الشاشة. */
    private const LOG_MODULE = 'SalesTargetSettings';

    /**
     * طرق الاحتساب المتاحة لكل نوع.
     *
     * المفتاح يُخزَّن في `calculation_method` والتسمية تُترجَم — فلا يتغيّر
     * ما في القاعدة بتغيير اللغة.
     *
     * @var array<string, list<string>>
     */
    private const CALCULATION_METHODS = [
        TargetSetting::TYPE_SALES => ['net_sales', 'gross_sales', 'paid_only'],
        TargetSetting::TYPE_COLLECTIONS => ['total_collected', 'cash_only', 'bank_only'],
    ];

    /** دورات التسوية المتاحة. */
    private const FREQUENCIES = ['weekly', 'monthly', 'quarterly', 'yearly'];

    /** الحقول التي يُلتقط فرقُها للسجلّ. */
    private const TRACKED = [
        'enabled', 'calculation_method', 'commission_account_id',
        'pay_from_employee_treasury', 'default_commission_treasury_id',
        'auto_settlement_enabled', 'settlement_frequency', 'settlement_day',
        'settlement_time', 'notify_on_settlement', 'require_approval',
    ];

    public function __construct()
    {
        // مُنقولة إلى كتالوج sales.target_settings.* — نفس نمط InvoicesPurchaseController
        $this->middleware('permission:sales.target_settings.view', ['only' => ['index']]);
        $this->middleware('permission:sales.target_settings.update', ['only' => ['update']]);
    }

    /**
     * صفحة الإعدادات — تعرض نوعاً واحداً وتبويباً لكل نوع مدعوم.
     */
    public function index(?string $type = null)
    {
        $settings = [];

        foreach (array_keys(TargetSetting::supportedTypes()) as $t) {
            $settings[$t] = TargetSetting::for($t)
                ->load(['commissionAccount', 'defaultTreasury', 'updatedBy']);
        }

        $activeType = $type && array_key_exists($type, TargetSetting::supportedTypes())
            ? $type
            : TargetSetting::TYPE_SALES;

        return view('sales::targets.settings.index', [
            // لا `settings`: الاسم يطمس مصفوفة حالة التطبيقات
            // (`ApplicationSetting::pluck('status','key')`) التي يقرأها الشريط
            // الجانبي بنفس الاسم عبر `get_defined_vars()` — راجع تعليق $settings
            // في sales::layouts.sidebar. وقوعُ هذا هنا أخفى كل بنود القائمة
            // المشروطة بـ$appOn() ولم يبقَ إلا ما لا يفحصها (تقرير المبيعات
            // والإقفال اليومي)، تماماً كما وقع سابقاً في شاشة إنشاء الفاتورة.
            'targetSettings' => $settings,
            'activeType' => $activeType,
            'accounts' => $this->leafAccounts(),
            'treasuries' => $this->treasuries(),
            'calcMethods' => self::CALCULATION_METHODS[$activeType] ?? [],
            'frequencies' => self::FREQUENCIES,
            // ما سيقع فعلاً: الموعدُ القادم والفترةُ التي ستُسوَّى فيه، وحالُ
            // المجدول على الخادم — كان الإعدادُ يُحفظ ثم لا يُرى شيءٌ حتى
            // يحلّ الموعدُ ويمرّ صامتاً
            'nextRuns' => $this->nextRuns($settings),
            'schedulerHealth' => app(CronMonitoringService::class)->schedulerHealth(),
        ]);
    }

    /**
     * الموعدُ القادم لكلّ نوع والفترةُ التي ستُسوَّى فيه — أو سببُ غيابه.
     *
     * الأسبابُ هي شروطُ الأمر `targets:process-settlements` نفسها: نوعٌ غير
     * مفعّل، أو تسويةٌ تلقائية موقوفة، أو تكرارٌ لم يُختر، أو حسابُ عمولة
     * لم يُربط — ثم المفتاحُ الذي يُشغّل الأمرَ أصلاً: مهمة `targets_settlements`
     * في «المهام المجدولة». ما لن يقع يُقال هنا بدل أن يُكتشف بعد شهر.
     *
     * @param  array<string, TargetSetting>  $settings
     * @return array<string, array{at: ?\Illuminate\Support\Carbon, period: ?array{0:string,1:string}, reason: ?string}>
     */
    private function nextRuns(array $settings): array
    {
        $out = [];
        $job = CronJob::query()->where('key', 'targets_settlements')->first();
        $jobActive = $job !== null && $job->status === CronJob::STATUS_ACTIVE;

        foreach ($settings as $type => $setting) {
            $reason = match (true) {
                ! $setting->enabled => 'type_disabled',
                ! $setting->auto_settlement_enabled => 'auto_off',
                ! $setting->settlement_frequency => 'no_frequency',
                ! $setting->commission_account_id => 'no_account',
                $job === null => 'cron_job_missing',
                ! $jobActive => 'cron_job_disabled',
                default => null,
            };

            $at = $reason ? null : TargetSettlementSchedule::nextRun($setting);

            $out[$type] = [
                'at' => $at,
                'period' => $at ? TargetSettlementSchedule::periodFor($setting, $at) : null,
                'reason' => $reason,
            ];
        }

        return $out;
    }

    /**
     * حفظ إعدادات نوع معيّن.
     */
    public function update(Request $request, string $type)
    {
        if (! array_key_exists($type, TargetSetting::supportedTypes())) {
            abort(404);
        }

        $data = $request->validate([
            'enabled' => 'nullable|boolean',
            // القيمة مقصورة على طرق النوع نفسه: طريقةُ تحصيلٍ في هدف مبيعات
            // كانت تُقبَل وتُحفَظ ثم لا يعرفها الاحتساب
            'calculation_method' => ['nullable', 'string', 'in:'.implode(',', self::CALCULATION_METHODS[$type] ?? [])],
            'commission_account_id' => 'nullable|exists:accounts,id',
            'pay_from_employee_treasury' => 'nullable|boolean',
            'default_commission_treasury_id' => 'nullable|exists:accounts,id',
            'auto_settlement_enabled' => 'nullable|boolean',
            'settlement_frequency' => ['nullable', 'in:'.implode(',', self::FREQUENCIES)],
            'settlement_day' => 'nullable|integer|min:0|max:31', // 0 = آخر يوم
            'settlement_time' => 'nullable|date_format:H:i',
            'notify_on_settlement' => 'nullable|boolean',
            'require_approval' => 'nullable|boolean',
            'settlement_scope' => 'nullable|in:'.TargetSetting::SCOPE_PREVIOUS.','.TargetSetting::SCOPE_CURRENT,
        ]);

        // نطاقُ التسوية في العمود JSON — يُدمَج مع ما فيه ولا يمحوه
        $setting = TargetSetting::for($type);
        $data['extra_settings'] = array_merge($setting->extra_settings ?? [], [
            'settlement_scope' => $data['settlement_scope'] ?? TargetSetting::SCOPE_PREVIOUS,
        ]);
        unset($data['settlement_scope']);

        // مربّع الاختيار غير المؤشَّر لا يُرسَل أصلاً، فغيابه إطفاء لا «لم يُذكَر»
        foreach ([
            'enabled', 'pay_from_employee_treasury', 'auto_settlement_enabled',
            'notify_on_settlement', 'require_approval',
        ] as $bool) {
            $data[$bool] = (bool) ($data[$bool] ?? false);
        }

        try {
            DB::transaction(function () use ($setting, $data, &$changes) {
                $setting->fill($data);
                $setting->updated_by = Auth::id();

                // الفرق يُلتقط قبل الحفظ: بعده تصير القيمة القديمة هي الجديدة
                $changes = OperationLog::diff($setting, self::TRACKED);

                $setting->save();
            });
        } catch (Throwable $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'update', $setting);

            return back()->withInput()->with('error', __('sales_settings.targets.save_failed'));
        }

        OperationLog::record(
            'updated',
            self::LOG_MODULE,
            $setting,
            __('sales_settings.targets.log_updated', ['type' => $setting->type_label]),
            $changes ?? []
        );

        // موعدٌ جديد في اليوم نفسه يستحقّ تنفيذاً — لا تحجبه علامةُ تشغيلٍ سابق
        TargetSettlementSchedule::forgetRan($setting);

        return redirect()->route('sales-targets.settings', ['type' => $type])
            ->with('success', __('sales_settings.targets.saved', ['type' => $setting->type_label]));
    }

    // ─────────────────────────────────────────────────────────────────
    // الداخل
    // ─────────────────────────────────────────────────────────────────

    /**
     * الحسابات التفصيلية وحدها — حسابُ أبٍ لا تُقيَّد عليه حركة، فعرضُه في
     * القائمة دعوةٌ إلى ربطٍ لا يصحّ.
     */
    private function leafAccounts()
    {
        return Account::query()
            ->whereNotExists(function ($sub) {
                $sub->selectRaw('1')
                    ->from('accounts as child')
                    ->whereColumn('child.parent_id', 'accounts.id');
            })
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'parent_id']);
    }

    /**
     * الخزائن والبنوك.
     *
     * كان هنا إلحاقٌ مشروط بـ`defined()` لثابتَي `TREASURIES_PARENT=121`
     * و`BANKS_PARENT=122` «الجديدين». وهما كودا الحسابين 13 و15 لا
     * معرّفاهما، فكان المعرّفان 121 و122 يُدخلان «احتياطي طوارئ» و«احتياطي
     * توسعات» في قائمة الخزائن — وهما حسابان تفصيليان بلا أبناء فيجتازان
     * شرط الأوراق.
     */
    private function treasuries()
    {
        // من «إعدادات الحسابات المحاسبية» لا من ثوابت مكتوبة: أرقام الأبوين
        // تخصّ قاعدةً بعينها، وربطُها في الشاشة هو مصدرها الوحيد
        $parents = array_values(array_filter([
            AccountingSettings::id('cash_treasuries_parent'),
            AccountingSettings::id('bank_accounts_parent'),
        ]));

        if ($parents === []) {
            return collect();
        }

        return Account::query()
            ->where(function ($q) use ($parents) {
                $q->whereIn('parent_id', $parents)->orWhereIn('id', $parents);
            })
            ->whereNotExists(function ($sub) {
                $sub->selectRaw('1')
                    ->from('accounts as child')
                    ->whereColumn('child.parent_id', 'accounts.id');
            })
            ->orderBy('code')
            ->get(['id', 'code', 'name']);
    }
}
