<?php

namespace Modules\Sales\Http\Controllers\Concerns;

use App\Models\Account;
use App\Models\TargetSetting;
use App\Models\TreasuryEmployee;
use App\Models\User;
use App\Support\AffiliationTree;
use App\Support\CurrencySettings;
use App\Support\HeaderBranchScope;
use App\Support\OperationLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Modules\Sales\Support\TargetScreen;
use Throwable;

/**
 * دورةُ حياة الهدف المشتركة — إنشاءً وعرضاً وتعديلاً وحذفاً.
 *
 * **لماذا هنا لا في كلّ متحكّم؟**
 *
 * `SalesTargetController` و`CollectionsTargetController` كانا يكتبان نفس
 * السبعة توابع حرفياً، بفارق اسم الصنف والمسار: نفس التحقّق، ونفس بناء
 * المستويات، ونفس حلقة الإسناد، ونفس رسائل النجاح. وقد تباعدا فعلاً —
 * `SalesTargetController::store()` يفتح معاملةً بلا `try`، فأيّ استثناء
 * يتركها مفتوحةً إلى نهاية الطلب؛ ونظيره في التحصيلات يلتقطها. عيبٌ
 * أُصلح في أحدهما ولم يُنقل إلى الآخر — وهذا ما يفعله التكرار دائماً.
 *
 * ما يفترق فيه النوعان يصل من `TargetScreen::config()`، ولا يبقى على
 * المتحكّم إلّا `targetMode()` وما يخصّه فعلاً: احتسابُ الإنجاز والتسوية.
 *
 * **ما أُصلح مع النقل**
 *
 * 1. *مستويات العمولة لم تكن تُعدَّل.* `update()` في الاثنين كان يمسّ
 *    الهدف والإسناد ولا يمسّ `commission_tiers` إطلاقاً — فمن أخطأ في
 *    نسبةٍ عند الإنشاء لا سبيل له إلّا حذف الهدف. صارت تُزامَن.
 *
 * 2. *`auto_calculate_weekly` كان يُقرأ بـ`$request->has()`.* والمفتاح
 *    في الشاشة الجديدة يسبقه حقلٌ مخفيّ بقيمة `0` (وهو ما يجعل الإطفاء
 *    يصل أصلاً) — فـ`has()` صحيحةٌ دائماً، والإعداد لا يُطفأ أبداً.
 *    صارت `boolean()`.
 *
 * 3. *الحذف كان يمرّ فوق التسويات.* هدفٌ نُفِّذت عليه تسوية له سندُ صرفٍ
 *    وقيدٌ محاسبي؛ حذفُه يترك القيد معلّقاً على مرجعٍ لا وجود له. صار
 *    الحذف يُردّ برسالةٍ تقترح الإيقاف بدلاً منه.
 *
 * 4. *لا أثر في السجلّات.* لم يكن للأهداف سطرٌ واحد في «سجلّ النشاط» ولا
 *    «سجلّ الأحداث» ولا «سجلّ الأخطاء» — تُنشأ وتُحذف بلا خبر. صارت كلّ
 *    عملية تمرّ بـ`OperationLog`.
 *
 * 5. *الشاشة كانت تُظهر ١٥ سجلاً ثابتة* بلا مُنتقي عدد، ولا بحثٍ بالاسم.
 *
 * @see \Modules\Sales\Support\TargetScreen
 * @see \App\Support\OperationLog
 */
trait ManagesTargetScreens
{
    /** خيارات عدد السجلّات — نفس ما تعرضه بقيّة شاشات المبيعات */
    protected array $targetPageSizes = [10, 25, 50, 100];

    /**
     * نوع الشاشة: `TargetScreen::SALES` أو `TargetScreen::COLLECTIONS`.
     * يُعرَّف في كل متحكّم.
     */
    abstract protected function targetMode(): string;

    /** وصف النوع كاملاً */
    protected function screen(): array
    {
        return TargetScreen::config($this->targetMode());
    }

    /**
     * هل نوع الأهداف هذا موقوف من «إعدادات الأهداف والعمولات»؟
     *
     * نصّ الخيار يَعِد بأنّ الإيقاف «يُجمّد كل الأهداف من هذا النوع ولا
     * يُحتسب لها إنجاز»، وكان `enabled` لا يُقرأ إلّا في التسوية المجدولة:
     * فتُنشأ أهدافٌ جديدة وتُعدَّل وتُسوّى يدوياً على نوعٍ موقوف.
     *
     * القراءة والعرضُ يبقيان مفتوحين عمداً — الإيقاف تجميدٌ لما هو قائم لا
     * إخفاءٌ لتاريخه.
     */
    protected function targetTypeDisabled(): bool
    {
        return ! TargetSetting::for($this->screen()['settings_type'])->enabled;
    }

    // ══════════════════════════════════════════════════════════════
    //  القائمة
    // ══════════════════════════════════════════════════════════════

    public function index(Request $request)
    {
        $screen = $this->screen();
        $model = $screen['model'];

        $query = $model::query()
            ->with(['creator', 'achievements', 'commissionTiers'])
            ->latest();

        // بحثٌ بالاسم والوصف — لم يكن في الشاشة القديمة فلترٌ إلّا الحالة
        if ($request->filled('search')) {
            $term = '%'.$request->input('search').'%';
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', $term)->orWhere('description', 'like', $term);
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $perPage = (int) $request->input('per_page', 25);
        if (! in_array($perPage, $this->targetPageSizes, true)) {
            $perPage = 25;
        }

        // `appends` يحمل الفلاتر مع الترقيم: كان التنقّل إلى الصفحة
        // الثانية يُسقطها فتعود القائمة كاملة
        $targets = $query->paginate($perPage)->appends($request->query());

        // «المحقَّق» يُحتسب للدورة الجارية — العمود المخزَّن لا يكتبه شيء
        $targets->getCollection()->each(fn ($target) => $this->applyLiveAchievements($target));

        return view($this->targetView('index'), [
            'targets' => $targets,
            'stats' => $this->targetStats($model),
            'pageSizes' => $this->targetPageSizes,
        ]);
    }

    /**
     * إحصاء الشاشة — استعلامات مجمَّعة لا مرورٌ على السجلّات.
     *
     * @return array{total:int, active:int, inactive:int, employees:int}
     */
    protected function targetStats(string $model): array
    {
        $byStatus = $model::query()
            ->selectRaw('status, COUNT(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status');

        $active = (int) ($byStatus['active'] ?? 0);
        $inactive = (int) ($byStatus['inactive'] ?? 0);

        $screen = $this->screen();
        $achievement = $screen['achievement'];

        return [
            // الجمع من نفس الخريطة لا استعلامٌ ثانٍ — ويشمل أي حالةٍ
            // ثالثة قد تُضاف لاحقاً بلا تعديلٍ هنا
            'total' => (int) $byStatus->sum(),
            'active' => $active,
            'inactive' => $inactive,
            // موظفٌ مُسنَدٌ إلى هدفين يُعدّ مرّة — العدد «كم موظفاً يعمل
            // على هدف»، لا «كم إسناداً في الجدول»
            'employees' => (int) $achievement::query()->distinct()->count('employee_id'),
        ];
    }

    // ══════════════════════════════════════════════════════════════
    //  الإنشاء
    // ══════════════════════════════════════════════════════════════

    public function create()
    {
        return view($this->targetView('create'), [
            'target' => null,
            'employees' => $this->eligibleEmployees(),
            'selectedEmployees' => [],
        ]);
    }

    public function store(Request $request)
    {
        if ($this->targetTypeDisabled()) {
            return back()->withInput()->with('error', __('sales_targets.messages.type_disabled'));
        }

        $data = $this->validateTarget($request);
        $screen = $this->screen();
        $model = $screen['model'];

        try {
            $target = DB::transaction(function () use ($data, $model) {
                $target = $model::create($this->targetAttributes($data) + [
                    'created_by' => auth()->id(),
                ]);

                $this->syncTiers($target, $data['commission_tiers']);
                $this->syncAchievements($target, $data['employees'], (float) $data['target_value']);

                return $target;
            });
        } catch (Throwable $e) {
            OperationLog::failure($e, $screen['log_module'], 'store');

            return back()->withInput()->with('error', __('sales_targets.messages.save_failed'));
        }

        OperationLog::record('created', $screen['log_module'], $target, __('sales_targets.log.created', [
            'type' => $screen['one'],
            'name' => $target->name,
            'value' => CurrencySettings::format((float) $target->target_value),
            'employees' => count($data['employees']),
        ]));

        return redirect()->route($screen['routes']['show'], $target->id)
            ->with('success', __('sales_targets.messages.created', ['name' => $target->name]));
    }

    // ══════════════════════════════════════════════════════════════
    //  العرض
    // ══════════════════════════════════════════════════════════════

    public function show($id)
    {
        $screen = $this->screen();
        $model = $screen['model'];

        $target = $model::with([
            'creator',
            'achievements.employee',
            'achievements.treasury',
            'commissionTiers',
            'settlements.employee',
            'settlements.settler',
        ])->findOrFail($id);

        $cycle = $this->applyLiveAchievements($target);

        $totalTarget = (float) $target->achievements->sum('target_amount');
        $totalAchieved = (float) $target->achievements->sum('achieved_amount');

        return view($this->targetView('show'), [
            'target' => $target,
            'totalTarget' => $totalTarget,
            'totalAchieved' => $totalAchieved,
            'overallPercentage' => $totalTarget > 0 ? ($totalAchieved / $totalTarget) * 100 : 0,
            'cycle' => $cycle,
        ]);
    }

    // ══════════════════════════════════════════════════════════════
    //  الإنجاز الحيّ
    // ══════════════════════════════════════════════════════════════

    /**
     * إنجاز المستخدم خلال مدّة، بمصدر النوع وطريقة احتسابه المضبوطة.
     * يُعرَّف في كل متحكّم.
     */
    abstract protected function achievedFor(int $userId, string $from, string $to): float;

    /** @var array<string, float> ذاكرةُ الطلب — موظفٌ في عدّة أهدافٍ يُحتسب مرّة */
    private array $liveAchievedMemo = [];

    /**
     * الدورة الجارية للهدف: من أوّل الشهر (أو آخر سبعة أيّام للأسبوعي)
     * إلى اليوم، ولا تبدأ قبل إنشاء الهدف — نفس حدّ التقرير.
     *
     * @return array{0:string, 1:string}
     */
    protected function currentCycle($target): array
    {
        $today = now();

        /* هدفٌ له مدّة يُقاس عليها: من بدايتها إلى اليوم، ولا يتجاوز نهايتَها.
           كان «المحقَّق» يُحتسب للشهر الجاري مهما كان الهدف — فهدفُ ستّة
           أشهرٍ يعرض إنجازَ شهرٍ واحد مقابلَ قيمةِ الستّة. */
        if ($target->hasPeriod()) {
            $from = $target->start_date->toDateString();
            $to = min($today->toDateString(), $target->end_date->toDateString());

            return [$from, max($from, $to)];
        }

        $from = $target->commission_period === 'weekly'
            ? $today->copy()->subDays(6)->startOfDay()
            : $today->copy()->startOfMonth();

        if ($target->created_at && $target->created_at->gt($from)) {
            $from = $target->created_at->copy()->startOfDay();
        }

        return [$from->toDateString(), $today->toDateString()];
    }

    /**
     * يملأ `achieved_amount` لكل إسنادٍ بإنجاز الدورة الجارية — في الذاكرة
     * لا في القاعدة.
     *
     * **كان «المحقَّق» صفراً دائماً** في القائمة وصفحة الهدف: العمود
     * المخزَّن يُكتب `0` عند الإسناد ولا يحدّثه شيءٌ في النظام، والتقرير
     * والتسوية يحتسبان من القيود مباشرة. فصار العرضُ يحتسب مثلهما.
     *
     * @return array{0:string, 1:string} الدورة التي احتُسبت
     */
    protected function applyLiveAchievements($target): array
    {
        [$from, $to] = $cycle = $this->currentCycle($target);

        foreach ($target->achievements as $achievement) {
            $userId = (int) $achievement->employee_id;
            $key = $userId.'|'.$from.'|'.$to;

            $achievement->achieved_amount = $this->liveAchievedMemo[$key]
                ??= $this->achievedFor($userId, $from, $to);
        }

        return $cycle;
    }

    // ══════════════════════════════════════════════════════════════
    //  التعديل
    // ══════════════════════════════════════════════════════════════

    public function edit($id)
    {
        $screen = $this->screen();
        $model = $screen['model'];

        $target = $model::with(['achievements', 'commissionTiers'])->findOrFail($id);
        $assigned = $this->assignedUserIds($target);

        return view($this->targetView('edit'), [
            'target' => $target,
            'employees' => $this->eligibleEmployees($assigned),
            'selectedEmployees' => $assigned,
        ]);
    }

    /**
     * رسالةُ الرفض حين تخرج الفترةُ عن مدّة الهدف — أو `null` حين تقع داخلها.
     *
     * هدفٌ بلا مدّة لا قيدَ عليه. وللهدف ذي المدّة قاعدةٌ واحدة في التقرير
     * والتسوية اليدوية والمجدولة: لا يُقاس ولا يُصرف عن يومٍ خارج مدّته.
     */
    protected function outsideTargetPeriod($target, string $from, string $to): ?string
    {
        if ($target->periodCovers($from, $to)) {
            return null;
        }

        return __('sales_targets.duration.outside', [
            'from' => $target->start_date->format('Y-m-d'),
            'to' => $target->end_date->format('Y-m-d'),
        ]);
    }

    /** @return array<int, int> معرّفات المستخدمين المُسنَد إليهم الهدف */
    protected function assignedUserIds($target): array
    {
        return $target->achievements->pluck('employee_id')->map(fn ($v) => (int) $v)->all();
    }

    /**
     * من سُوّيت عمولتُه من المُسنَدين عن فترةٍ تتداخل مع هذه — بمعرّف المستخدم.
     *
     * **كان الفحص للهدف كلّه**: تسويةٌ واحدة لأيّ موظف تُعلن الفترة «مسوّاة»
     * وتُخفي الزرّ وتردّ الطلب. فمن تخطّته التسوية (بلا خزينة مثلاً) لا
     * سبيل إلى صرف عمولته بعد إصلاح سببه. صار كلُّ موظفٍ يُفحص وحده.
     *
     * @return array<int, true> معرّف المستخدم ⇐ true
     */
    protected function settledUsersForPeriod($target, string $from, string $to): array
    {
        $employeeIds = $target->settledEmployeeIdsForPeriod($from, $to);

        if ($employeeIds === []) {
            return [];
        }

        // التسوية تُكتب بمعرّف سجلّ الموظف، والإسناد بمعرّف المستخدم
        return User::query()
            ->whereIn('employee_id', $employeeIds)
            ->pluck('id')
            ->mapWithKeys(fn ($id) => [(int) $id => true])
            ->all();
    }

    public function update(Request $request, $id)
    {
        if ($this->targetTypeDisabled()) {
            return back()->withInput()->with('error', __('sales_targets.messages.type_disabled'));
        }

        $screen = $this->screen();
        $model = $screen['model'];

        $target = $model::with('achievements')->findOrFail($id);
        $data = $this->validateTarget($request, $this->assignedUserIds($target));

        // فرقُ القيم يُلتقَط قبل الحفظ — بعده يصير الأصلُ هو الجديد
        try {
            $changes = DB::transaction(function () use ($target, $data) {
                $target->fill($this->targetAttributes($data));
                $changes = OperationLog::diff($target, [
                    'name', 'target_value', 'status', 'commission_period',
                    'auto_calculate_weekly', 'description',
                    'start_date', 'end_date', 'settlement_mode',
                ]);

                // مدّةٌ تغيّرت أو طريقةُ صرفٍ تبدّلت: تُعاد معالجةُ نهاية المدّة.
                // حارسُ التداخل في الخدمة يمنع الصرفَ مرّتين لمن صُرف له.
                if ($target->isDirty(['start_date', 'end_date', 'settlement_mode'])) {
                    $target->period_settled_at = null;
                }

                $target->save();

                $this->syncTiers($target, $data['commission_tiers']);
                $this->syncAchievements($target, $data['employees'], (float) $data['target_value']);

                return $changes;
            });
        } catch (Throwable $e) {
            OperationLog::failure($e, $screen['log_module'], 'update', $target);

            return back()->withInput()->with('error', __('sales_targets.messages.save_failed'));
        }

        OperationLog::record('updated', $screen['log_module'], $target, __('sales_targets.log.updated', [
            'type' => $screen['one'],
            'name' => $target->name,
        ]), $changes);

        return redirect()->route($screen['routes']['show'], $target->id)
            ->with('success', __('sales_targets.messages.updated', ['name' => $target->name]));
    }

    // ══════════════════════════════════════════════════════════════
    //  الحذف
    // ══════════════════════════════════════════════════════════════

    public function destroy($id)
    {
        $screen = $this->screen();
        $model = $screen['model'];

        $target = $model::findOrFail($id);
        $name = $target->name;

        // هدفٌ سُوّي له سندُ صرفٍ وقيدٌ محاسبي؛ حذفُه يُيتِّم القيد على
        // مرجعٍ لا وجود له. الإيقاف هو البديل الصحيح
        if ($target->settlements()->exists()) {
            return back()->with('error', __('sales_targets.messages.delete_blocked'));
        }

        try {
            DB::transaction(function () use ($target) {
                $target->achievements()->delete();
                $target->commissionTiers()->delete();
                $target->delete();
            });
        } catch (Throwable $e) {
            OperationLog::failure($e, $screen['log_module'], 'destroy', $id);

            return back()->with('error', __('sales_targets.messages.delete_failed'));
        }

        OperationLog::record('deleted', $screen['log_module'], $id, __('sales_targets.log.deleted', [
            'type' => $screen['one'],
            'name' => $name,
        ]));

        return redirect()->route($screen['routes']['index'])
            ->with('success', __('sales_targets.messages.deleted', ['name' => $name]));
    }

    // ══════════════════════════════════════════════════════════════
    //  المشترَك
    // ══════════════════════════════════════════════════════════════

    /** اسمُ القالب في مجلّد النوع */
    protected function targetView(string $name): string
    {
        return $this->targetMode() === TargetScreen::COLLECTIONS
            ? "sales::targets.collections.{$name}"
            : "sales::targets.{$name}";
    }

    /**
     * التحقّق — واحدٌ للإنشاء والتعديل.
     *
     * كان التعديل يتحقّق من الحقول الستّة ويُسقط `commission_tiers` من
     * قواعده، فتمرّ الشاشةُ بلا مستوًى واحد ولا يُحتسب شيء.
     */
    protected function validateTarget(Request $request, array $keepUserIds = []): array
    {
        // الحصر في المعروض فعلاً: مُدخَلٌ مُصاغ بيد لا يُسند الهدف إلى من
        // لا تعرضه القائمة. و`$keepUserIds` هم المُسنَدون حالياً عند التعديل
        $eligible = $this->eligibleEmployees($keepUserIds)->pluck('id')->all();

        // الحدود العليا هي سَعةُ الأعمدة: `target_value` ‏decimal(15,2) ونسبةُ
        // الإنجاز ‏decimal(5,2). بلا حدٍّ كان مستوى «1000%» أو مبلغٌ من أربعة
        // عشر رقماً يمرّ من التحقّق ويُرفض في القاعدة (Out of range)، فلا يرى
        // المستخدم إلّا «تعذّر حفظ الهدف» بلا إشارةٍ إلى الحقل
        return $request->validate([
            'name' => 'required|string|max:255',
            'target_value' => 'required|numeric|min:0|max:9999999999999.99',
            'status' => 'required|in:active,inactive',
            'commission_period' => 'nullable|in:monthly,weekly',
            'description' => 'nullable|string|max:2000',
            // المدّة: الطرفان معاً أو لا شيء — طرفٌ واحد لا يحدّ فترة
            'start_date' => 'nullable|date|required_with:end_date',
            'end_date' => 'nullable|date|required_with:start_date|after_or_equal:start_date',
            'settlement_mode' => 'nullable|in:period_end,cycle',
            'employees' => 'required|array|min:1',
            'employees.*' => ['required', 'integer', Rule::in($eligible)],
            'commission_tiers' => 'required|array|min:1',
            'commission_tiers.*.achievement_percentage' => 'required|numeric|min:0|max:999.99',
            'commission_tiers.*.commission_percentage' => 'required|numeric|min:0|max:100',
        ]);
    }

    /** أعمدةُ الهدف المشتقّة من المُدخَل */
    protected function targetAttributes(array $data): array
    {
        $model = $this->screen()['model'];

        /* أعمدةُ المدّة تُكتب حين تكون في الجدول وحده: الشيفرةُ تسبق
           الترحيلَ إلى الخادم، وحفظُ عمودٍ لا وجود له يُسقط الحفظَ كلَّه. */
        $period = [];
        if ($model::periodColumnsReady()) {
            $hasPeriod = ! empty($data['start_date']) && ! empty($data['end_date']);

            $period = [
                'start_date' => $hasPeriod ? $data['start_date'] : null,
                'end_date' => $hasPeriod ? $data['end_date'] : null,
                'settlement_mode' => $hasPeriod
                    ? ($data['settlement_mode'] ?? $model::SETTLE_PERIOD_END)
                    : null,
            ];
        }

        return $period + [
            'name' => $data['name'],
            'target_type' => 'fixed',              // الهدف مبلغٌ ثابت دائماً
            'target_value' => $data['target_value'],
            'base_amount' => null,
            'commission_percentage' => 0,          // المستويات المتدرّجة وحدها
            'status' => $data['status'],
            'commission_period' => $data['commission_period'] ?? 'monthly',
            // `boolean()` لا `has()`: المفتاح يسبقه حقلٌ مخفيّ بقيمة `0`
            'auto_calculate_weekly' => request()->boolean('auto_calculate_weekly'),
            'description' => $data['description'] ?? null,
        ];
    }

    /**
     * مزامنة مستويات العمولة — تُستبدَل بالكامل.
     *
     * الاستبدالُ أسلمُ من المطابقة سطراً سطراً: المستوى لا معرّف مستقرّ
     * له في الشاشة، ولا شيء في النظام يشير إليه بمعرّفه — التسويةُ
     * تقرأ النسب لا الصفوف.
     */
    protected function syncTiers($target, array $tiers): void
    {
        $target->commissionTiers()->delete();

        // الترتيب تصاعديّ بنسبة الإنجاز مهما كان ترتيب الإدخال، فيقرأ
        // من يفتح الهدف سلّماً صاعداً لا صفوفاً مبعثرة
        $sorted = collect($tiers)
            ->sortBy(fn ($t) => (float) $t['achievement_percentage'])
            ->values();

        $screen = $this->screen();
        $tierModel = $screen['tier'];
        $fk = $screen['foreign_key'];

        foreach ($sorted as $order => $tier) {
            $tierModel::create([
                $fk => $target->id,
                'achievement_percentage' => $tier['achievement_percentage'],
                'commission_percentage' => $tier['commission_percentage'],
                'tier_order' => $order,
            ]);
        }
    }

    /**
     * مزامنة إسناد الموظفين.
     *
     * ما بقي من الموظفين يحتفظ بإنجازه المحتسَب — كان التعديلُ يحذف
     * الإسنادات كلَّها ويعيد بناءها بـ`achieved_amount = 0`، فتضيع نتيجةُ
     * كل موظفٍ لم يتغيّر لمجرّد أن اسم الهدف عُدِّل.
     */
    protected function syncAchievements($target, array $employeeIds, float $targetValue): void
    {
        $screen = $this->screen();
        $achievementModel = $screen['achievement'];
        $fk = $screen['foreign_key'];

        $ids = array_values(array_unique(array_map('intval', $employeeIds)));

        // من رُفع عنه الإسناد
        $target->achievements()->whereNotIn('employee_id', $ids)->delete();

        $existing = $target->achievements()->pluck('employee_id')->all();
        $treasuries = $this->treasuriesFor($ids);

        foreach ($ids as $employeeId) {
            if (in_array($employeeId, $existing)) {
                // قيمةُ الهدف قد تكون تغيّرت؛ الإنجاز المحتسَب يبقى
                $target->achievements()
                    ->where('employee_id', $employeeId)
                    ->update([
                        'target_amount' => $targetValue,
                        'treasury_id' => $treasuries[$employeeId] ?? null,
                    ]);

                continue;
            }

            $achievementModel::create([
                $fk => $target->id,
                'employee_id' => $employeeId,
                'treasury_id' => $treasuries[$employeeId] ?? null,
                'target_amount' => $targetValue,
                'achieved_amount' => 0,
                'achievement_percentage' => 0,
            ]);
        }
    }

    /**
     * خزينةُ كل مستخدم من المُمرَّرين — استعلامان اثنان لا استعلامٌ لكلّ
     * موظف كما كانت الحلقة القديمة تفعل.
     *
     * @param  array<int, int>  $userIds
     * @return array<int, int|null> معرّف المستخدم ⇐ معرّف الخزينة
     */
    protected function treasuriesFor(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        $employeeByUser = User::query()
            ->whereIn('id', $userIds)
            ->whereNotNull('employee_id')
            ->pluck('employee_id', 'id');

        if ($employeeByUser->isEmpty()) {
            return [];
        }

        $treasuryByEmployee = TreasuryEmployee::query()
            ->whereIn('employee_id', $employeeByUser->values())
            ->pluck('treasury_id', 'employee_id');

        $out = [];
        foreach ($employeeByUser as $userId => $employeeId) {
            $out[(int) $userId] = $treasuryByEmployee[$employeeId] ?? null;
        }

        return $out;
    }

    /**
     * الموظفون المؤهَّلون للإسناد: حسابُ دخولٍ مرتبطٌ بسجلّ موظفٍ على رأس
     * العمل (`Employee::active()` — النشط وتحت التجربة).
     *
     * **كانت القائمة فارغةً دائماً.** الشرط `users.role = 'employee'` لا
     * يطابق حساباً واحداً: العمود النصّيّ يحمل اسمَ الدور كما سُمّي
     * («manager»، «مندوب مبيعات»…) لا كلمة `employee`. فلا موظف في شاشة
     * الإنشاء ولا التعديل، والتحقّقُ يردّ كلّ إسناد. الارتباطُ الفعليّ هو
     * `employee_id` — وهو ما يقرؤه `User::isEmployee()` نفسه.
     *
     * **حساباتُ الإدارة لا تُعرض** — مستخدمٌ دورُه (`role_id`) يملك
     * `data_scope_all` (مدير، مالك، مسؤول). حساباتُهم مرتبطةٌ بسجلّات
     * موظفين أيضاً، فكانت تظهر بين المناديب. هو نفسُ حدّ «الإدارة» الذي
     * يقرؤه `TreasuryAccessGuard`، ويتبع شاشة الأدوار لا أسماءً مكتوبة هنا.
     *
     * `$keepUserIds` المُسنَدون حالياً إلى الهدف عند التعديل: يبقون في
     * القائمة وإن انتهت خدمتُهم أو كانوا من الإدارة، وإلّا اختفوا من
     * المنتقي فحذفهم الحفظُ التالي صامتاً مع إنجازهم.
     *
     * @param  array<int, int>  $keepUserIds
     */
    protected function eligibleEmployees(array $keepUserIds = [])
    {
        $managementRoleIds = \App\Models\Role::query()
            ->whereHas('permissions', fn ($p) => $p->where('name', 'data_scope_all'))
            ->pluck('id')
            ->all();

        /* فرعُ الترويسة يُرشّح القائمة كسائر القوائم في النظام (راجع
           `HeaderBranchScope`): من وقف على فرعٍ رأى موظفيه وموظفي ما تحته —
           ارتباطُهم بأيّ صفٍّ في `employee_branch` أو بفرعهم الافتراضيّ —
           لا المنشأةَ كلَّها. الرئيسيّ يرفع القيد. المُسنَدون حالياً
           (`$keepUserIds`) يبقون وإن كانوا خارج الفرع. */
        $headerNode = HeaderBranchScope::nodeId();
        $branchIds = $headerNode ? AffiliationTree::descendantIds($headerNode) : null;

        $users = User::query()
            ->whereNotNull('employee_id')
            ->where(function ($q) use ($keepUserIds, $managementRoleIds, $branchIds) {
                $q->where(function ($q) use ($managementRoleIds, $branchIds) {
                    $q->whereHas('employee', function ($e) use ($branchIds) {
                        $e->active();
                        if ($branchIds !== null) {
                            $e->where(function ($b) use ($branchIds) {
                                $b->whereIn('branch_id', $branchIds)
                                    ->orWhereExists(function ($x) use ($branchIds) {
                                        $x->selectRaw('1')
                                            ->from('employee_branch')
                                            ->whereColumn('employee_branch.employee_id', 'employees.id')
                                            ->whereIn('employee_branch.branch_id', $branchIds);
                                    });
                            });
                        }
                    });
                    if ($managementRoleIds !== []) {
                        $q->where(fn ($r) => $r->whereNull('role_id')->orWhereNotIn('role_id', $managementRoleIds));
                    }
                });
                if ($keepUserIds !== []) {
                    $q->orWhereIn('id', $keepUserIds);
                }
            })
            ->with('employee.job_title')
            ->orderBy('name')
            ->get();

        $treasuries = $this->treasuriesFor($users->pluck('id')->all());

        $accounts = Account::query()
            ->whereIn('id', array_filter(array_values($treasuries)))
            ->pluck('name', 'id');

        return $users->map(function (User $user) use ($treasuries, $accounts) {
            $employee = $user->employee;

            // لا `last_name` في جدول الموظفين — الاسم الكامل من `full_name`
            $displayName = ($employee?->full_name) ?: $user->name;

            // `job_title` قد يكون نصّاً أو علاقةً أو مصفوفة — يُعاد نصّاً
            // دائماً، وإلّا رمى القالبُ «Object could not be converted to string»
            $raw = $employee->job_title ?? null;
            $jobTitle = match (true) {
                is_object($raw) => $raw->name ?? null,
                is_array($raw) => $raw['name'] ?? null,
                default => $raw,
            };

            $treasuryId = $treasuries[$user->id] ?? null;

            return [
                'id' => $user->id,
                'name' => $displayName,
                'employee_id' => $user->employee_id,
                'phone' => $employee->phone ?? null,
                'job_title' => $jobTitle,
                'inactive' => ! $employee || ! $employee->isActive(),
                'treasury_id' => $treasuryId,
                'treasury_name' => $treasuryId
                    ? ($accounts[$treasuryId] ?? __('sales_targets.fields.no_treasury'))
                    : __('sales_targets.fields.no_treasury'),
            ];
        });
    }
}
