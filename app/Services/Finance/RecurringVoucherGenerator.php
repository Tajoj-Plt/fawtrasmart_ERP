<?php

namespace App\Services\Finance;

use App\Models\Account;
use App\Models\Expense;
use App\Models\Receipt;
use App\Models\User;
use App\Support\AccountingPeriodGuard;
use App\Support\OperationLog;
use App\Support\TaxCalculator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use Illuminate\Validation\ValidationException;
use Modules\Account\Http\Controllers\Finance\ExpensesController;
use Modules\Account\Http\Controllers\Finance\IncomesController;
use Throwable;

/**
 * مولّد سندات القبض والصرف المكرَّرة.
 *
 * ═════════════════════ لماذا عبر `store()` نفسها؟ ═════════════════════
 *
 * حفظُ السند في `IncomesController::store()` و`ExpensesController::store()`
 * ليس إدراجَ صفّ: ترقيمٌ متسلسل، وضريبةٌ تُحسب من إعداداتها، وقيدٌ بسطوره
 * وحسابات ضرائبه، ورصيدُ خزينة، وحارسُ صلاحية الخزينة، وملاحظةُ العميل،
 * وإشعار. وكلُّه مكتوبٌ داخل الدالّة ومبنيٌّ على `Request`.
 *
 * فنسخُه هنا يعني مسارَ ترحيلٍ ثانياً يتباعد عن الأوّل بأوّل تعديل — والسندُ
 * المولَّد يخرج بقيدٍ يختلف عن سندٍ أدخله المحاسب بيده. لذا يُبنى **طلبٌ
 * صناعيّ** من حقول السند الأصل، ويُنفَّذ باسم منشئه، ويُمرَّر إلى `store()`
 * كما تمرّره الشاشة. والطلبُ يطلب JSON فيعود بـ`success` و`message` بدل
 * إعادة توجيهٍ تُقرأ رسالتها من الجلسة (ويبقى ذلك مقروءاً احتياطاً).
 *
 * ═════════════════════ ما يختلف عن الإدخال اليدوي ═════════════════════
 *
 *   • النسخةُ **غير مكرَّرة**: لا `is_recurring` في الطلب، فلا تلد سلسلة.
 *   • لا تُسدِّد فواتير العميل: `store()` في سند القبض يوزّع المبلغ على
 *     فواتير العميل المفتوحة تلقائياً. والتوزيعُ قرارُ من يقبض فعلاً —
 *     إيجارٌ شهريّ يُقبض لا يعني أنّ فاتورةً بعينها سُدِّدت. فيُعلَّم الطلبُ
 *     بسِمةٍ (`attributes`، لا تصل من HTTP) يقرؤها المتحكّم فيتخطّى التوزيع
 *     وإكمالَ زيارة اليوم ونقاطَ الموظف — لم يقم أحدٌ بعملٍ يُكافأ عليه.
 *   • المرفقات لا تُنسخ: ملفُّ الأصل إيصالُ الأصل لا إيصالُ النسخة.
 *   • `recurring_parent_id` و`recurring_sequence` يُختمان في حدث `creating`
 *     فيدخلان مع الإدراج نفسه، والفريدُ عليهما يردّ الإصدار المكرَّر.
 *
 * ═════════════════════ ما يُتخطّى ═════════════════════
 *
 *   • تاريخٌ في فترةٍ محاسبية مقفلة — يُتخطّى ويُبلَّغ، ويُعاد فحصه كلَّ
 *     تشغيل: فتحُ الفترة يُصدره.
 *   • منشئٌ حُذف أو سُحبت منه صلاحيةُ الإنشاء — السندُ يُصدر باسمه، فلا
 *     يُصدر بصلاحيةٍ لم يعد يملكها.
 *   • أصلٌ مُلغى (سند قبض `status = 0`) أو معكوس (سند صرف `reversed_at`).
 *
 * وإخفاقُ نسخةٍ يوقف سلسلتها في هذا التشغيل: رصيدُ خزينةٍ لا يكفي لنسخة
 * مارس لن يكفي لنسخة أبريل، ولا معنى لإصدار أبريل قبل مارس.
 */
class RecurringVoucherGenerator
{
    /** سِمةُ الطلب التي تعلّم الحفظَ بأنّه توليدٌ آلي — انظر `isGenerating()` */
    public const REQUEST_ATTRIBUTE = 'recurring_voucher_generation';

    /** قيمة العمود `type` في سجلّ الأحداث والأخطاء */
    public const LOG_MODULE = 'FinanceRecurringVouchers';

    /** حدُّ النسخ لكل سلسلةٍ في التشغيل الواحد — حارسٌ على سلسلةٍ يوميةٍ قديمة */
    public const MAX_PER_SOURCE = 400;

    /**
     * النسخةُ قيد الإصدار — يقرؤها مستمعُ `creating`.
     *
     * @var array{class: class-string<Model>, parent: int, sequence: int, model: Model|null}|null
     */
    private static ?array $pending = null;

    /** @var array<class-string<Model>, bool> */
    private static array $listening = [];

    /**
     * هل هذا الطلبُ توليدٌ آليّ لنسخة سندٍ مكرَّر؟
     *
     * `attributes` لا تُملأ من مدخلات HTTP، فلا يستطيع مستخدمٌ أن يتخطّى
     * بها توزيعَ سند القبض على الفواتير من الشاشة.
     */
    public static function isGenerating(Request $request): bool
    {
        return $request->attributes->has(self::REQUEST_ATTRIBUTE);
    }

    /**
     * @return array<string, array{model: class-string<Model>, controller: class-string, permission: string, label: string, log: string}>
     */
    protected function types(): array
    {
        return [
            'receipt' => [
                'model' => Receipt::class,
                'controller' => IncomesController::class,
                'permission' => 'account.incomes.create',
                'label' => 'سند قبض',
                // نوعُ سجلّ النشاط الذي تقرؤه شاشة سند القبض
                'log' => 'receipt',
            ],
            'expense' => [
                'model' => Expense::class,
                'controller' => ExpensesController::class,
                'permission' => 'account.expenses.create',
                'label' => 'سند صرف',
                'log' => 'expense',
            ],
        ];
    }

    /**
     * يُصدر ما استحقّ من كل السندات المكرَّرة.
     *
     * @param  Carbon|null  $on  اليوم المرجعي
     * @param  bool  $dryRun  يحسب ولا يكتب
     * @return array{scanned:int, created:int, skipped:int, failed:int, vouchers:array<int,string>, notes:array<int,string>}
     */
    public function run(?Carbon $on = null, bool $dryRun = false): array
    {
        $today = ($on ?: Carbon::today())->copy()->startOfDay();

        $result = ['scanned' => 0, 'created' => 0, 'skipped' => 0, 'failed' => 0, 'vouchers' => [], 'notes' => []];

        foreach ($this->types() as $type) {
            /** @var class-string<Model> $class */
            $class = $type['model'];

            $query = $class::query()
                ->where('is_recurring', 1)
                ->whereNull('recurring_parent_id')
                ->whereIn('recurring_frequency', array_keys($class::recurringFrequencies()))
                ->whereDate('date', '<', $today->toDateString())
                // سلسلةٌ انتهت قبل أوّل تكرارٍ لها لا تُقرأ أصلاً
                ->where(fn ($q) => $q->whereNull('end_date')->orWhereColumn('end_date', '>', 'date'))
                ->orderBy('id');

            if ($class === Receipt::class) {
                $query->where('status', '!=', 0);
            } else {
                $query->whereNull('reversed_at');
            }

            foreach ($query->get() as $source) {
                $result['scanned']++;

                try {
                    $this->generateFor($source, $type, $today, $dryRun, $result);
                } catch (Throwable $e) {
                    $result['failed']++;
                    $result['notes'][] = "{$type['label']} {$source->code}: ".$e->getMessage();

                    OperationLog::failure($e, self::LOG_MODULE, 'generate', $source->id);
                }
            }
        }

        return $result;
    }

    protected function generateFor(Model $source, array $type, Carbon $today, bool $dryRun, array &$result): void
    {
        $issued = $source->recurringCopies()->pluck('recurring_sequence')->filter()->flip();

        for ($sequence = 1; $sequence <= self::MAX_PER_SOURCE; $sequence++) {
            if (isset($issued[$sequence])) {
                continue; // صدرت من قبل
            }

            $date = $source->recurringOccurrenceDate($sequence);

            // بعد تاريخ النهاية، أو لم يحن بعد — وما بعدها أبعد
            if ($date === null || $date->gt($today)) {
                return;
            }

            $tag = "{$type['label']} {$source->code} #{$sequence} ({$date->toDateString()})";

            if (! AccountingPeriodGuard::allows($date)) {
                $result['skipped']++;
                $result['notes'][] = $tag.': '.AccountingPeriodGuard::blockReason($date);

                continue;
            }

            if ($dryRun) {
                $result['created']++;
                $result['vouchers'][] = $tag;

                continue;
            }

            [$copy, $error] = $this->issue($source, $type, $sequence, $date);

            if ($copy === null) {
                $result['failed']++;
                $result['notes'][] = $tag.': '.$error;

                OperationLog::failure(new \RuntimeException($tag.': '.$error), self::LOG_MODULE, 'generate', $source->id);

                // الإخفاقُ يوقف السلسلة: لا تُصدر نسخةٌ لاحقة قبل سابقتها
                return;
            }

            $result['created']++;
            $result['vouchers'][] = $tag.' → '.$copy->code;

            OperationLog::record(
                'recurring_generated',
                $type['log'],
                $source,
                sprintf('صدرت النسخة رقم **%d** من %s المكرَّر **%s** بتاريخ %s: **%s**',
                    $sequence, $type['label'], $source->code, $date->toDateString(), $copy->code)
            );
        }
    }

    /**
     * يُصدر نسخةً واحدة بمسار الحفظ في المتحكّم.
     *
     * @return array{0: Model|null, 1: string|null} النسخة، أو سبب الإخفاق
     */
    protected function issue(Model $source, array $type, int $sequence, Carbon $date): array
    {
        $creator = $source->created_by ? User::find($source->created_by) : null;

        if (! $creator) {
            return [null, 'منشئ السند الأصل غير موجود — السند يُصدر باسم منشئه.'];
        }

        if (! $creator->hasRolePermission($type['permission'])) {
            return [null, "منشئ السند ({$creator->name}) لم يعد يملك صلاحية إنشاء {$type['label']}."];
        }

        $input = $source instanceof Receipt
            ? $this->receiptInput($source, $date)
            : $this->expenseInput($source, $date);

        $request = Request::create('/finance/recurring-vouchers', 'POST', $input, [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);
        $request->setLaravelSession(app('session.store'));
        $request->setUserResolver(fn () => $creator);
        $request->attributes->set(self::REQUEST_ATTRIBUTE, ['parent_id' => $source->id, 'sequence' => $sequence]);

        $previousRequest = app()->bound('request') ? app('request') : null;
        $previousUser = auth()->user();
        // مستوى المعاملة قبل النداء: `store()` يُغلق معاملته في كلّ مسار، وما
        // بقي مفتوحاً فوق هذا المستوى (استثناءٌ قبل `rollBack`) يُغلق هنا
        $level = DB::transactionLevel();

        $this->listen($type['model']);
        self::$pending = ['class' => $type['model'], 'parent' => (int) $source->id, 'sequence' => $sequence, 'model' => null];

        $response = null;
        $error = null;

        try {
            app()->instance('request', $request);
            Facade::clearResolvedInstance('request');
            auth()->setUser($creator);
            // رسائل الجلسة من نسخةٍ سابقة لا تُقرأ خطأً لهذه
            app('session.store')->forget(['error', 'errors', 'success']);

            $response = app($type['controller'])->store($request);
        } catch (ValidationException $e) {
            // حرّاسٌ قبل `try` في `store()`: الفترة وصلاحية الخزينة والتكرار
            $error = collect($e->errors())->flatten()->implode(' ');
        } catch (Throwable $e) {
            $error = $e->getMessage();
        } finally {
            $created = self::$pending['model'] ?? null;
            self::$pending = null;

            while (DB::transactionLevel() > $level) {
                DB::rollBack();
            }

            if ($previousRequest) {
                app()->instance('request', $previousRequest);
                Facade::clearResolvedInstance('request');
            }

            if ($previousUser) {
                auth()->setUser($previousUser);
            } else {
                auth()->forgetUser();
            }
        }

        if ($error === null) {
            [$ok, $error] = $this->readResponse($response);

            if ($ok && $created && $created->exists) {
                return [$created->fresh() ?? $created, null];
            }

            $error ??= 'أعاد الحفظُ نجاحاً ولم يُعثر على السند المُنشأ.';
        }

        return [null, $error];
    }

    /**
     * نجاحُ الحفظ ورسالتُه — من JSON، أو من الجلسة إن أعاد توجيهاً.
     *
     * @return array{0: bool, 1: string|null}
     */
    protected function readResponse($response): array
    {
        if ($response instanceof JsonResponse) {
            $data = $response->getData(true);

            if (! empty($data['success'])) {
                return [true, null];
            }

            $errors = collect($data['errors'] ?? [])->flatten()->implode(' ');

            return [false, trim(($data['message'] ?? 'تعذّر الحفظ').' '.$errors)];
        }

        if ($response instanceof RedirectResponse) {
            $session = app('session.store');

            if ($session->has('error')) {
                return [false, (string) $session->get('error')];
            }

            $errors = $session->get('errors');

            if ($errors && method_exists($errors, 'all') && count($errors->all())) {
                return [false, implode(' ', $errors->all())];
            }

            return [true, null];
        }

        return [false, 'استجابةٌ غير متوقّعة من مسار الحفظ.'];
    }

    /**
     * يختم النسخةَ في إدراجها نفسه.
     *
     * يُسجَّل مرّةً لكل صنف، ولا يفعل شيئاً خارج `issue()`: `$pending`
     * فارغٌ هناك.
     */
    protected function listen(string $class): void
    {
        if (isset(self::$listening[$class])) {
            return;
        }

        self::$listening[$class] = true;

        $class::creating(static function (Model $model) use ($class) {
            if (self::$pending === null || self::$pending['class'] !== $class || self::$pending['model'] !== null) {
                return;
            }

            $model->setAttribute('recurring_parent_id', self::$pending['parent']);
            $model->setAttribute('recurring_sequence', self::$pending['sequence']);
            // النسخة لا تلد سلسلة — ولو أُرسل غيرُ ذلك
            $model->setAttribute('is_recurring', 0);
            $model->setAttribute('recurring_frequency', null);
            $model->setAttribute('end_date', null);

            self::$pending['model'] = $model;
        });
    }

    /** حقولُ شاشة سند القبض من الأصل. */
    protected function receiptInput(Receipt $source, Carbon $date): array
    {
        return array_filter([
            'amount' => $this->enteredAmount($source),
            'date' => $date->toDateString(),
            'account_id' => $source->account_id,
            'treasury_id' => $source->treasury_id,
            'incomes_category_id' => $source->incomes_category_id,
            'cost_center_id' => $this->costCenterOf($source),
            'payment_method_id' => $source->payment_method_id,
            'branch_id' => $source->branch_id,
            'description' => $source->description,
            'seller' => $source->seller,
            'tax1' => $source->tax1,
            'tax2' => $source->tax2,
            'tax1_account_id' => $source->tax1_account_id,
            'tax2_account_id' => $source->tax2_account_id,
        ], fn ($value) => $value !== null && $value !== '');
    }

    /** حقولُ شاشة سند الصرف من الأصل. */
    protected function expenseInput(Expense $source, Carbon $date): array
    {
        /*
            نوعُ السند لا يُخزَّن — الشاشة ترسله والمتحكّم يبني عليه اختيار
            الحساب والتحقّق. فيُستنتج ممّا خُزّن: المورد يعني «لمورد»،
            وحسابٌ مرتبطٌ بعميل يعني «لعميل»، وما عداهما «عام».
        */
        $expenseType = 'general';

        if ($source->supplier_id) {
            $expenseType = 'supplier';
        } elseif ($source->account_id && Account::whereKey($source->account_id)->whereNotNull('client_id')->exists()) {
            $expenseType = 'client';
        }

        return array_filter([
            'amount' => $this->enteredAmount($source),
            'date' => $date->toDateString(),
            'expense_type' => $expenseType,
            'expenses_category_id' => $source->expenses_category_id,
            'account_id' => $source->account_id,
            'supplier_id' => $source->supplier_id,
            'treasury_id' => $source->treasury_id,
            'cost_center_id' => $this->costCenterOf($source),
            'payment_method_id' => $source->payment_method_id,
            'branch_id' => $source->branch_id,
            'unit_id' => $source->unit_id,
            'description' => $source->description,
            'seller' => $source->seller,
            'tax1' => $source->tax1,
            'tax2' => $source->tax2,
            'tax1_account_id' => $source->tax1_account_id,
            'tax2_account_id' => $source->tax2_account_id,
        ], fn ($value) => $value !== null && $value !== '');
    }

    /**
     * المبلغ كما أدخله المستخدم — لا الوعاء المخزَّن.
     *
     * `amount` يُخزَّن **قبل الضريبة**، و`store()` يستخرج الضريبة الشاملة
     * ممّا يُدخَل. فتمريرُ الوعاء لسندٍ ضريبتُه شاملة يستخرجها مرّةً ثانية
     * فتنكمش كلُّ نسخةٍ عن أصلها.
     */
    protected function enteredAmount(Model $source): float
    {
        $carried = 0.0;

        foreach ([$source->tax1, $source->tax2] as $taxId) {
            $tax = TaxCalculator::resolve($taxId);

            if ($tax && $tax->type === TaxCalculator::INCLUDED) {
                $carried += TaxCalculator::fraction($tax->tax);
            }
        }

        return round((float) $source->amount * (1 + $carried), 2);
    }

    /**
     * مركزُ التكلفة يسكن سطور قيد الأصل لا السند — فيُقرأ من هناك.
     */
    protected function costCenterOf(Model $source): ?int
    {
        if (! $source->cost_centers_enabled || ! $source->code) {
            return null;
        }

        $id = DB::table('journal_entry_details as d')
            ->join('journal_entries as e', 'e.id', '=', 'd.journal_entry_id')
            ->where('e.reference_number', $source->code)
            ->whereNotNull('d.cost_center_id')
            ->orderBy('d.id')
            ->value('d.cost_center_id');

        return $id ? (int) $id : null;
    }
}
