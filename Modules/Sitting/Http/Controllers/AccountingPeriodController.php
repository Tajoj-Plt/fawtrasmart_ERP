<?php

namespace Modules\Sitting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\AccountingPeriod;
use App\Models\AccountingPeriodSetting;
use App\Models\FiscalYear;
use App\Services\Accounting\AccountingPeriodService;
use App\Services\Accounting\YearEndClosingService;
use App\Support\AccountingPeriodOptions;
use App\Support\OperationLog;
use App\Support\OrphanedPeriodDocuments;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Account\Http\Controllers\YearClosingReportController;

/**
 * الفترات المحاسبية — السنوات المالية وفتراتها وضوابط قفلها.
 *
 * الشاشة تعرض السنوات، وكل سنة تُفتَح على فتراتها. الإقفال إجراء منفصل عن
 * التعديل: من يعدّل اسم فترة ليس بالضرورة من يقفل الشهر، ولذلك فُصلت
 * صلاحية `close` عن `manage`، وفُصلت إعادة الفتح عنهما معاً — فهي تُعيد
 * فتح باب على شهر أُقفل عمداً.
 */
class AccountingPeriodController extends Controller
{
    public function __construct(
        protected AccountingPeriodService $periods,
        protected YearEndClosingService $yearEnd,
    ) {
        $this->middleware('permission:settings.accounting_periods.view')->only(['index', 'closingReport']);
        $this->middleware('permission:settings.accounting_periods.manage')
            ->only(['storeYear', 'updateYear', 'destroyYear', 'setCurrent', 'generate', 'storePeriod', 'updatePeriod', 'destroyPeriod', 'updateSettings', 'attachOrphans']);
        $this->middleware('permission:settings.accounting_periods.close')
            ->only(['closePeriod', 'closeYear']);
        $this->middleware('permission:settings.accounting_periods.reopen')
            ->only(['reopenPeriod', 'reopenYear']);
    }

    public function index()
    {
        $years = FiscalYear::with(['periods'])
            ->orderByDesc('start_date')
            ->get();

        return view('sitting::accounting_periods.index', [
            'years' => $years,
            'settings' => AccountingPeriodSetting::current(),
            'entryCounts' => $this->entryCounts($years),
            // أسبابُ منع الإقفال وتنبيهاته ومعاينةُ قيده — لكلّ سنةٍ مفتوحة، تُرى قبل الضغط
            'closingReadiness' => $years->filter->isOpen()->mapWithKeys(fn (FiscalYear $year) => [$year->id => $this->yearEnd->readiness($year)]),
            // قيودُ إقفال السنوات المقفلة — روابطُها في بطاقة السنة
            'closingEntries' => $years->reject->isOpen()->mapWithKeys(fn (FiscalYear $year) => [$year->id => $this->yearEnd->closingEntries($year)->get(['id', 'reference_number', 'branch_id'])]),
            'currentYear' => FiscalYear::current(),
            'currentPeriod' => AccountingPeriod::forDate(today()),
            'types' => array_keys(AccountingPeriod::TYPES),
        ]);
    }

    // ═══════════════════════ السنوات المالية ═══════════════════════

    public function storeYear(Request $request)
    {
        $data = $this->validateYear($request);

        $year = DB::transaction(function () use ($data, $request) {
            $year = FiscalYear::create([
                'name' => $data['name'],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'status' => FiscalYear::STATUS_OPEN,
                'notes' => $data['notes'] ?? null,
            ]);

            // أول سنة تُنشأ هي الجارية بلا سؤال: نظام بسنة واحدة غير
            // معلَّمة يجعل كل شاشة تسأل «أي سنة؟» بلا جواب
            if ($request->boolean('is_current') || FiscalYear::count() === 1) {
                $this->markCurrent($year);
            }

            if ($type = $request->input('period_type')) {
                $this->periods->generate($year, $type);
            }

            return $year;
        });

        Log::info('أُنشئت سنة مالية', ['id' => $year->id, 'name' => $year->name, 'user_id' => auth()->id()]);

        AccountingPeriodOptions::forget();

        /*
            **عملياتٌ سابقة بلا سنة: يُعرَض ربطُها لا يُفرَض.**

            المنشأةُ تُدخل بياناتِها قبل أن ترسم تقويمَها، فتبقى بلا نسبة —
            لا تظهر في قائمةٍ ولا تقرير. ورسمُ السنة هو اللحظةُ التي يصير فيها
            الربطُ ممكناً، فيُسأل عنه هنا لا في مكانٍ لا يمرّ به المستخدم.

            ولا يُربط تلقائياً: نسبةُ مستندٍ إلى فترةٍ قرارٌ محاسبيّ، وقد يكون
            التاريخُ نفسُه خطأً يُصحَّح قبل النسبة.
        */
        $orphans = OrphanedPeriodDocuments::summary();

        $redirect = redirect()->route('AccountingPeriods.index')
            ->with('success', __('settings.accounting_periods.year_created', ['name' => $year->name]));

        if ($orphans['total'] > 0) {
            $redirect->with('orphaned_documents', [
                'total' => $orphans['total'],
                'tables' => $orphans['tables'],
                'year' => $year->name,
            ]);
        }

        return $redirect;
    }

    /**
     * ربطُ العمليات غير المصنَّفة بفتراتها — بعد رسم التقويم.
     *
     * لا يمسّ مستنداً منسوباً: الشرطُ `accounting_period_id IS NULL` وحده،
     * فلا يُعيد تصنيف ما صُنِّف ولا ينقض إقفالاً حصل. وما لا يقع تاريخُه في
     * فترةٍ يبقى غيرَ مصنَّف ويُرى بفلتر «غير مصنَّفة».
     */
    public function attachOrphans()
    {
        $filed = OrphanedPeriodDocuments::refile();

        /*
            ومع الفارغ: ما خُتم بفترةٍ لا يقع تاريخُه فيها — في الفترات المفتوحة
            وحدها. سببُ منعٍ في الإقفال السنوي، وهذا الزرّ علاجُه المعروض هناك.
        */
        foreach (FiscalYear::where('status', FiscalYear::STATUS_OPEN)->get(['start_date', 'end_date']) as $openYear) {
            foreach (OrphanedPeriodDocuments::resyncRange($openYear->start_date->toDateString(), $openYear->end_date->toDateString()) as $table => $count) {
                $filed[$table] = ($filed[$table] ?? 0) + $count;
            }
        }

        $attached = array_sum($filed);
        $remaining = OrphanedPeriodDocuments::summary()['total'];

        AccountingPeriodOptions::forget();

        Log::info('رُبطت عمليات غير مصنَّفة بفتراتها', [
            'attached' => $attached, 'remaining' => $remaining, 'user_id' => auth()->id(),
        ]);

        if ($attached === 0) {
            return back()->with('warning',
                'لا عملية يقع تاريخها في فترة معرَّفة. تحقّق من اكتمال فترات السنة ومن ألّا فجوة في التقويم.');
        }

        $message = 'رُبطت '.number_format($attached).' عملية بفتراتها.';

        if ($remaining > 0) {
            $message .= ' وبقيت '.number_format($remaining)
                .' عملية خارج التقويم — تُرى باختيار «غير مصنَّفة» في فلتر الفترة.';
        }

        return back()->with('success', $message);
    }

    public function updateYear(Request $request, FiscalYear $fiscalYear)
    {
        $data = $this->validateYear($request, $fiscalYear);

        // تضييق حدود سنة بعد توليد فتراتها يترك فترات خارج سنتها
        $outside = $fiscalYear->periods()
            ->where(function ($query) use ($data) {
                $query->whereDate('start_date', '<', $data['start_date'])
                    ->orWhereDate('end_date', '>', $data['end_date']);
            })
            ->count();

        if ($outside > 0) {
            throw ValidationException::withMessages([
                'start_date' => __('settings.accounting_periods.errors.year_shrinks', ['count' => $outside]),
            ]);
        }

        $fiscalYear->update([
            'name' => $data['name'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'notes' => $data['notes'] ?? null,
        ]);

        if ($request->boolean('is_current')) {
            $this->markCurrent($fiscalYear);
        }

        return redirect()->route('AccountingPeriods.index')
            ->with('success', __('settings.accounting_periods.year_updated', ['name' => $fiscalYear->name]));
    }

    /**
     * حذف سنة — يمنعه وجود قيود داخل مداها.
     *
     * الفحص على القيود لا على الفترات: الفترات تُحذف تبعاً للسنة بلا ضرر،
     * أما سنة فيها قيود فحذفها يمحو الحدّ الذي يُحتَكم إليه في قفلها.
     */
    public function destroyYear(FiscalYear $fiscalYear)
    {
        $entries = $this->countEntriesBetween($fiscalYear->start_date, $fiscalYear->end_date);

        if ($entries > 0) {
            return back()->with('error', __('settings.accounting_periods.errors.year_has_entries', [
                'name' => $fiscalYear->name,
                'count' => number_format($entries),
            ]));
        }

        if (! $fiscalYear->isOpen()) {
            return back()->with('error', __('settings.accounting_periods.errors.year_closed_delete'));
        }

        $name = $fiscalYear->name;
        $fiscalYear->delete();

        Log::info('حُذفت سنة مالية', ['name' => $name, 'user_id' => auth()->id()]);

        return redirect()->route('AccountingPeriods.index')
            ->with('success', __('settings.accounting_periods.year_deleted', ['name' => $name]));
    }

    public function setCurrent(FiscalYear $fiscalYear)
    {
        $this->markCurrent($fiscalYear);

        return back()->with('success', __('settings.accounting_periods.year_marked_current', ['name' => $fiscalYear->name]));
    }

    public function closeYear(FiscalYear $fiscalYear)
    {
        if (! $fiscalYear->isOpen()) {
            return back()->with('error', __('settings.accounting_periods.errors.already_closed'));
        }

        /*
            الإقفالُ يمرّ بفحص الجاهزية ويُنشئ قيودَ الإقفال — أو يُردّ بأسبابه.
            كان يقلب الحالة فقط: تُقفل سنةٌ لم تنتهِ أو فيها قيدٌ غير متوازن، ولا
            يُرحَّل صافي ربحها إلى حقوق الملكية.
        */
        try {
            $entries = $this->yearEnd->close($fiscalYear);
        } catch (ValidationException $e) {
            return back()
                ->with('error', __('settings.accounting_periods.errors.year_not_ready', ['name' => $fiscalYear->name]))
                ->with('closing_blockers', $e->errors()['fiscal_year'] ?? []);
        }

        Log::info('أُقفلت سنة مالية', ['id' => $fiscalYear->id, 'user_id' => auth()->id(), 'closing_entries' => array_map(fn ($entry) => $entry->id, $entries)]);

        OperationLog::record(
            'fiscal_year_closed',
            'AccountingPeriods',
            $fiscalYear,
            "إقفال السنة المالية «{$fiscalYear->name}» — ".count($entries).' قيد إقفال'
        );

        return back()->with('success', __('settings.accounting_periods.year_closed_with_entries', [
            'name' => $fiscalYear->name,
            'count' => count($entries),
        ]));
    }

    public function reopenYear(FiscalYear $fiscalYear)
    {
        if ($fiscalYear->isOpen()) {
            return back()->with('error', __('settings.accounting_periods.errors.already_open'));
        }

        // يفتح فتراتها ثم يعكس قيودَ إقفالها (لا يحذفها) فتعود الإيراداتُ والمصروفات بأرصدتها
        try {
            $reversed = $this->yearEnd->reopen($fiscalYear);
        } catch (ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        }

        Log::warning('أُعيد فتح سنة مالية', ['id' => $fiscalYear->id, 'user_id' => auth()->id(), 'closing_entries_reversed' => $reversed]);

        OperationLog::record(
            'fiscal_year_reopened',
            'AccountingPeriods',
            $fiscalYear,
            "إعادة فتح السنة المالية «{$fiscalYear->name}» — عُكس {$reversed} قيد إقفال بقيودٍ عكسية"
        );

        return back()->with('success', __('settings.accounting_periods.year_reopened_reversed', ['name' => $fiscalYear->name, 'count' => $reversed]));
    }

    /**
     * تقرير الإقفال السنوي — للسنة المقفلة ما رُحِّل، وللمفتوحة معاينةُ ما سيُرحَّل.
     *
     * يُحال إلى نسخته في التقارير المالية (بتصميم تقارير السندات وميزان
     * المراجعة، وتصديرها وطباعتها المخصَّصين) لمن يبلغها؛ وهذه النسخةُ
     * احتياطٌ لمن يملك الفترات ولا تشمله التقارير المالية.
     */
    public function closingReport(FiscalYear $fiscalYear)
    {
        if ($url = YearClosingReportController::urlFor($fiscalYear)) {
            return redirect()->to($url);
        }

        return view('sitting::accounting_periods.closing_report', [
            'report' => $this->yearEnd->report($fiscalYear),
            'readiness' => $fiscalYear->isOpen() ? $this->yearEnd->readiness($fiscalYear) : null,
        ]);
    }

    /** توليد فترات السنة — يستبدل المفتوحة، ويرفض إن كان فيها مقفلة. */
    public function generate(Request $request, FiscalYear $fiscalYear)
    {
        $data = $request->validate([
            'period_type' => ['required', Rule::in(array_keys(AccountingPeriod::TYPES))],
        ]);

        try {
            $count = $this->periods->generate($fiscalYear, $data['period_type']);
        } catch (\RuntimeException $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Sitting.AccountingPeriod', 'generate');

            return back()->with('error', $e->getMessage());
        }

        // التوليدُ يحذف الفترات المفتوحة ويُعيد إنشاءها فيُفرغ ختمَ مستنداتها — تُختم بالجديدة
        $this->resyncStamps($fiscalYear->start_date, $fiscalYear->end_date);

        return back()->with('success', __('settings.accounting_periods.generated', [
            'count' => $count,
            'name' => $fiscalYear->name,
        ]));
    }

    // ═══════════════════════ الفترات ═══════════════════════

    public function storePeriod(Request $request, FiscalYear $fiscalYear)
    {
        $data = $this->validatePeriod($request, $fiscalYear);

        $period = $fiscalYear->periods()->create([
            'name' => $data['name'],
            'sequence' => (int) ($fiscalYear->periods()->max('sequence') + 1),
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'status' => AccountingPeriod::STATUS_OPEN,
            'notes' => $data['notes'] ?? null,
        ]);

        // فترةٌ في فجوة: ما وقع تاريخُه فيها يُختم بها
        $this->resyncStamps($period->start_date, $period->end_date);

        return back()->with('success', __('settings.accounting_periods.period_created', ['name' => $period->name]));
    }

    public function updatePeriod(Request $request, AccountingPeriod $accountingPeriod)
    {
        if ($accountingPeriod->isLocked()) {
            return back()->with('error', __('settings.accounting_periods.errors.locked_edit'));
        }

        $data = $this->validatePeriod($request, $accountingPeriod->fiscalYear, $accountingPeriod);

        /*
            حدودُ الفترة المقفلة لا تتحرّك — الاسمُ والملاحظة وحدهما.

            كان المنعُ للمقفلة نهائياً وحدها: فترةٌ مقفلةٌ إدارياً تُمدَّ أو
            تُقصَّر فيدخل دفترَها المقفلَ مستندٌ لم يكن فيه أو يخرج منه ما كان،
            والإقفالُ قائم. من أراد تغيير الحدود فتحها أوّلاً.
        */
        if (! $accountingPeriod->isOpen()
            && ($accountingPeriod->start_date->toDateString() !== Carbon::parse($data['start_date'])->toDateString()
                || $accountingPeriod->end_date->toDateString() !== Carbon::parse($data['end_date'])->toDateString())) {
            return back()->with('error', 'لا تُغيَّر حدودُ فترةٍ مقفلة — افتحها أوّلاً ثم عدّل تاريخيها.');
        }

        // المدى القديم والجديد معاً: ما خرج من الفترة وما دخلها
        $from = min($accountingPeriod->start_date->toDateString(), Carbon::parse($data['start_date'])->toDateString());
        $to = max($accountingPeriod->end_date->toDateString(), Carbon::parse($data['end_date'])->toDateString());

        $accountingPeriod->update([
            'name' => $data['name'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'notes' => $data['notes'] ?? null,
        ]);

        $this->resyncStamps($from, $to);

        return back()->with('success', __('settings.accounting_periods.period_updated', ['name' => $accountingPeriod->name]));
    }

    /**
     * ختمُ المستندات يطابق تواريخها بعد إعادة ترسيم فتراتٍ مفتوحة —
     * `OrphanedPeriodDocuments::resyncRange()`. المقفلةُ لا تُمسّ.
     */
    protected function resyncStamps($from, $to): void
    {
        $changed = OrphanedPeriodDocuments::resyncRange(Carbon::parse($from)->toDateString(), Carbon::parse($to)->toDateString());

        if ($changed) {
            Log::info('أُعيد ختمُ مستنداتٍ بفتراتها بعد إعادة ترسيم الفترات', ['from' => (string) $from, 'to' => (string) $to, 'tables' => $changed]);
        }

        AccountingPeriodOptions::forget();
        $this->periods->forgetReportLists();
    }

    public function destroyPeriod(AccountingPeriod $accountingPeriod)
    {
        if (! $accountingPeriod->isOpen()) {
            return back()->with('error', __('settings.accounting_periods.errors.closed_delete'));
        }

        $entries = $this->countEntriesBetween($accountingPeriod->start_date, $accountingPeriod->end_date);

        if ($entries > 0) {
            return back()->with('error', __('settings.accounting_periods.errors.period_has_entries', [
                'name' => $accountingPeriod->name,
                'count' => number_format($entries),
            ]));
        }

        $name = $accountingPeriod->name;
        $accountingPeriod->delete();

        return back()->with('success', __('settings.accounting_periods.period_deleted', ['name' => $name]));
    }

    public function closePeriod(Request $request, AccountingPeriod $accountingPeriod)
    {
        if (! $accountingPeriod->isOpen()) {
            return back()->with('error', __('settings.accounting_periods.errors.already_closed'));
        }

        $permanent = $request->boolean('permanent');

        // القفل النهائي بلا رجعة — يُطلب تأكيده باسم الفترة في الواجهة،
        // ويُشترط هنا صلاحية إعادة الفتح: من لا يملك التراجع لا يملك
        // الإجراء الذي لا تراجع عنه
        if ($permanent && ! hasPermission('settings.accounting_periods.reopen')) {
            return back()->with('error', __('settings.accounting_periods.errors.lock_forbidden'));
        }

        $this->periods->closePeriod($accountingPeriod, $permanent);

        Log::info('أُقفلت فترة محاسبية', [
            'id' => $accountingPeriod->id,
            'permanent' => $permanent,
            'user_id' => auth()->id(),
        ]);

        return back()->with('success', __(
            $permanent ? 'settings.accounting_periods.period_locked' : 'settings.accounting_periods.period_closed',
            ['name' => $accountingPeriod->name]
        ));
    }

    public function reopenPeriod(AccountingPeriod $accountingPeriod)
    {
        if (! $accountingPeriod->canReopen()) {
            return back()->with('error', __(
                $accountingPeriod->isLocked()
                    ? 'settings.accounting_periods.errors.locked_reopen'
                    : 'settings.accounting_periods.errors.already_open'
            ));
        }

        // فترة داخل سنة مقفلة تبقى مقفلة فعلياً — فتحها وحدها يوهم بسماح
        // لا يقع، لأن `check()` تردّ التاريخ بسبب السنة
        if (! $accountingPeriod->fiscalYear->isOpen()) {
            return back()->with('error', __('settings.accounting_periods.errors.year_closed_reopen'));
        }

        $this->periods->reopenPeriod($accountingPeriod);

        Log::warning('أُعيد فتح فترة محاسبية', ['id' => $accountingPeriod->id, 'user_id' => auth()->id()]);

        return back()->with('success', __('settings.accounting_periods.period_reopened', ['name' => $accountingPeriod->name]));
    }

    // ═══════════════════════ الضوابط ═══════════════════════

    public function updateSettings(Request $request)
    {
        $data = $request->validate([
            'lock_before_date' => ['nullable', 'date'],
            'default_period_type' => ['required', Rule::in(array_keys(AccountingPeriod::TYPES))],
        ]);

        $settings = AccountingPeriodSetting::first() ?: new AccountingPeriodSetting;

        $settings->fill([
            'enforce_lock' => $request->boolean('enforce_lock'),
            'lock_before_date' => $data['lock_before_date'] ?: null,
            'block_undefined_dates' => $request->boolean('block_undefined_dates'),
            'block_future_dates' => $request->boolean('block_future_dates'),
            'default_period_type' => $data['default_period_type'],
            'override_permission' => AccountingPeriodSetting::DEFAULTS['override_permission'],
        ])->save();

        $this->periods->forgetSettings();

        Log::info('حُدِّثت ضوابط الفترات المحاسبية', [
            'enforce_lock' => $settings->enforce_lock,
            'user_id' => auth()->id(),
        ]);

        return back()->with('success', __('settings.accounting_periods.settings_saved'));
    }

    // ═══════════════════════ مساعدات ═══════════════════════

    /** @return array<string, mixed> */
    protected function validateYear(Request $request, ?FiscalYear $except = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $start = Carbon::parse($data['start_date']);
        $end = Carbon::parse($data['end_date']);

        if ($this->periods->yearOverlaps($start, $end, $except?->id)) {
            throw ValidationException::withMessages([
                'start_date' => __('settings.accounting_periods.errors.year_overlap'),
            ]);
        }

        return $data;
    }

    /** @return array<string, mixed> */
    protected function validatePeriod(Request $request, FiscalYear $year, ?AccountingPeriod $except = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $start = Carbon::parse($data['start_date']);
        $end = Carbon::parse($data['end_date']);

        if ($start->lt($year->start_date) || $end->gt($year->end_date)) {
            throw ValidationException::withMessages([
                'start_date' => __('settings.accounting_periods.errors.period_outside_year', [
                    'from' => $year->start_date->format('Y-m-d'),
                    'to' => $year->end_date->format('Y-m-d'),
                ]),
            ]);
        }

        if ($this->periods->periodOverlaps($year->id, $start, $end, $except?->id)) {
            throw ValidationException::withMessages([
                'start_date' => __('settings.accounting_periods.errors.period_overlap'),
            ]);
        }

        return $data;
    }

    /** سنة جارية واحدة لا أكثر — التعليم ينزع ما قبله في معاملة واحدة. */
    protected function markCurrent(FiscalYear $year): void
    {
        DB::transaction(function () use ($year) {
            FiscalYear::where('is_current', true)->whereKeyNot($year->id)->update(['is_current' => false]);
            $year->update(['is_current' => true]);
        });
    }

    /**
     * عدد القيود داخل كل فترة — استعلام واحد لكل السنوات لا استعلام لكل فترة.
     *
     * الشاشة تعرض عشرات الفترات، واستدعاء `journalEntryCount()` لكل واحدة
     * يعني عشرات الاستعلامات في رندر واحد.
     *
     * @param  Collection<int, FiscalYear>  $years
     * @return array<int, int> معرّف الفترة ← عدد القيود
     */
    protected function entryCounts($years): array
    {
        $periods = $years->flatMap->periods;

        if ($periods->isEmpty()) {
            return [];
        }

        try {
            $entries = DB::table('journal_entries')
                ->whereDate('date', '>=', $periods->min('start_date'))
                ->whereDate('date', '<=', $periods->max('end_date'))
                ->selectRaw('date, COUNT(*) as total')
                ->groupBy('date')
                ->pluck('total', 'date');
        } catch (\Throwable) {
            // جدول القيود غائب في هذا التركيب — الشاشة تعمل بلا أعداد
            return [];
        }

        $counts = [];

        foreach ($periods as $period) {
            $total = 0;

            foreach ($entries as $date => $number) {
                $day = Carbon::parse($date);

                if ($day->betweenIncluded($period->start_date, $period->end_date)) {
                    $total += (int) $number;
                }
            }

            $counts[$period->id] = $total;
        }

        return $counts;
    }

    protected function countEntriesBetween(Carbon $from, Carbon $to): int
    {
        try {
            return DB::table('journal_entries')
                ->whereDate('date', '>=', $from)
                ->whereDate('date', '<=', $to)
                ->count();
        } catch (\Throwable) {
            return 0;
        }
    }
}
