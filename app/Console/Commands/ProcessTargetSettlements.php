<?php

namespace App\Console\Commands;

use App\Models\CollectionsTarget;
use App\Models\SalesTarget;
use App\Models\TargetSetting;
use App\Models\User;
use App\Services\Accounting\AccountingPeriodService;
use App\Services\TargetCalculationService;
use App\Services\Targets\TargetSettlementSchedule;
use App\Services\TargetSettlementService;
use App\Services\SystemAlertService;
use App\Support\CurrencySettings;
use App\Support\OperationLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * فحص الأهداف المُفعَّل عليها التسوية التلقائية، وإنشاء التسويات المستحقة.
 *
 * **الأمر يفحص استحقاقه بنفسه** — وكان لا يفعل. التعليق القديم هنا يقول
 * «لا حاجة للتحقق من اليوم/الوقت لأن الـ scheduler يبني cron expression»،
 * وهو صحيحٌ في مسارٍ واحد من مسارين: الأمر مسجَّلٌ أيضاً في جدول
 * `cron_jobs` بمفتاح `targets_settlements` **كل ساعة**، ومعروضٌ في شاشة
 * «المهام المجدولة» لمن يُفعّله. فمن فعّله من هناك كان يحصل على تسويةٍ
 * ماليّة — سند صرفٍ وقيد — في أول ساعةٍ تمرّ، متجاهلةً يومَ التسوية ووقتَها
 * المضبوطَين في «إعدادات الأهداف». وكانت `shouldRunNow()` مكتوبةً لهذا
 * الغرض ولا تُستدعى، وفيها اصطلاحٌ أسبوعيّ يخالف اصطلاح المجدول بيومٍ كامل.
 *
 * صار الموعدُ والفترةُ في `TargetSettlementSchedule` يقرؤهما الطرفان،
 * و`--force` وحدها تتخطّى الفحص.
 *
 * **التشغيلُ المستحقّ يترك أثراً دائماً** (2026-09-26). كان تشغيلٌ لا يجد
 * عمولةً مستحقّة ينتهي بسطرٍ في مخرجات الأمر لا يقرؤه أحد: من ضبط
 * «اليوم ٢٦» على هدفٍ أنشأه في الشهر نفسه، رأى الموعدَ يمرّ بلا سندٍ ولا
 * إشعار — لأنّ أوّلَ تشغيلٍ يُسوّي الشهرَ **المنقضي** الذي لم يكن الهدفُ
 * فيه بعد — فحسب النظامَ معطّلاً. صار كلُّ تشغيلٍ مستحقّ يُسجَّل في سجلّ
 * النشاط ويُرسَل خلاصتُه تنبيهاً لمسؤولي النظام: ما صُرف، أو أنّه لا
 * مستحقّ ولماذا، أو سببُ التخطّي (فترةٌ مقفلة، خزينةٌ ناقصة…).
 *
 * @see \App\Services\Targets\TargetSettlementSchedule
 */
class ProcessTargetSettlements extends Command
{
    protected $signature = 'targets:process-settlements
                            {--type= : نوع هدف محدد (sales|collections) - اختياري}
                            {--force : التنفيذ بغض النظر عن اليوم/الوقت المُجدوَل}
                            {--dry-run : عرض ما سيتم تنفيذه دون حفظ}';

    protected $description = 'تنفيذ التسويات المجدولة للأهداف وحساب العمولات تلقائياً';

    public function handle(TargetSettlementService $service)
    {
        $this->info('🚀 بدء فحص التسويات المجدولة - '.now()->format('Y-m-d H:i:s'));

        $typeFilter = $this->option('type');
        $force = (bool) $this->option('force');
        $dryRun = (bool) $this->option('dry-run');

        $types = $typeFilter ? [$typeFilter] : array_keys(TargetSetting::supportedTypes());

        $totalProcessed = 0;
        $totalCommissions = 0;

        foreach ($types as $type) {
            $setting = TargetSetting::for($type);

            if (! $setting->enabled) {
                $this->line("⏭  تخطّي [$type]: غير مُفعَّل");

                continue;
            }

            if (! $setting->auto_settlement_enabled && ! $force) {
                $this->line("⏭  تخطّي [$type]: التسوية التلقائية غير مُفعَّلة");

                continue;
            }

            // ⛔ التحقق من اختيار حساب العمولة
            if (empty($setting->commission_account_id)) {
                $this->error("⛔ تخطّي [$type]: حساب العمولة غير محدد في الإعدادات. لا يمكن التسوية.");
                Log::warning("ProcessTargetSettlements: skipped [$type] - no commission_account_id");

                continue;
            }

            /* أهدافٌ انتهت مدّتُها وتُصرف عمولتُها مرّةً عن المدّة كلِّها — قبل
               فحص يوم الدورة: موعدُها نهايةُ مدّتها هي لا يومُ التسوية العامّ،
               فتُفحص كلَّ يومٍ لا في يوم الدورة وحده. */
            $periodEnd = $this->processPeriodEndTargets($type, $setting, $force, $dryRun, $service);
            $totalProcessed += $periodEnd['count'];
            $totalCommissions += $periodEnd['total'];

            // الاستحقاق: يومُ التسوية ووقتُها من الإعدادات نفسها — لا من
            // تعبير cron الذي أطلق الأمر. `--force` وحدها تتخطّاه.
            if (! $force && ! TargetSettlementSchedule::isDueOn($setting)) {
                $this->line("⏭  تخطّي [$type]: اليوم ليس موعد التسوية المضبوط");

                continue;
            }

            if (! $force && ! TargetSettlementSchedule::isTimeReached($setting)) {
                $this->line('⏭  تخطّي ['.$type.']: لم يحن وقت التسوية ('.TargetSettlementSchedule::time($setting).')');

                continue;
            }

            // المجدول يسأل كلَّ دقيقة ما دام الوقتُ قد بلغ — فالتنفيذُ الأوّل
            // في اليوم يُسجَّل، وما بعده يُتخطّى. `--force` تتجاوز هذا أيضاً.
            if (! $force && TargetSettlementSchedule::ranOn($setting)) {
                $this->line("⏭  تخطّي [$type]: نُفِّذت تسوية اليوم من قبل");

                continue;
            }

            $this->info("⚙️  معالجة [$type]...");

            // تُسجَّل قبل النتيجة لا بعدها: تشغيلٌ تخطّته فترةٌ مقفلة أو أسقطه
            // خطأ لا يجوز أن يُعاد كلَّ دقيقة حتى منتصف الليل
            if (! $dryRun) {
                TargetSettlementSchedule::markRan($setting);
            }

            // حساب الفترة — من نفس المصدر الذي بُني عليه الموعد
            [$fromDate, $toDate] = TargetSettlementSchedule::periodFor($setting);
            $this->line("   📅 الفترة: $fromDate → $toDate");

            // الفترة المحاسبية — بتاريخ نهاية المدّة، فهو تاريخ القيد.
            // التسوية اليدوية تفحصها في المتحكّم؛ المجدولة كانت تمرّ فوقها
            // فتُرحّل قيداً إلى سنةٍ أُقفلت وأُصدرت قوائمها.
            if ($reason = $this->accountingPeriodBlock($toDate)) {
                $this->error("⛔ تخطّي [$type]: $reason");
                Log::warning("ProcessTargetSettlements: skipped [$type] - accounting period closed", [
                    'date' => $toDate,
                    'reason' => $reason,
                ]);
                if (! $dryRun) {
                    $this->reportRun($setting, $fromDate, $toDate, null, $reason);
                }

                continue;
            }

            // معالجة أهداف هذا النوع
            $result = $this->processTargetsOfType($type, $fromDate, $toDate, $setting, $dryRun, $service);

            $totalProcessed += $result['count'];
            $totalCommissions += $result['total'];

            $this->line("   ✓ تم معالجة {$result['count']} تسوية بإجمالي ".CurrencySettings::format((float) $result['total']));
            foreach ($result['errors'] as $error) {
                $this->warn('   ⚠ '.$error);
            }

            if (! $dryRun) {
                $this->reportRun($setting, $fromDate, $toDate, $result);
            }
        }

        $this->info('');
        $this->info("✅ انتهى - تم إنشاء $totalProcessed تسوية بإجمالي ".CurrencySettings::format((float) $totalCommissions));

        if ($dryRun) {
            $this->warn('⚠️  وضع dry-run: لم يتم حفظ أي تغييرات.');
        }

        return Command::SUCCESS;
    }

    /**
     * سبب منع القيد بهذا التاريخ — أو `null` إن كانت الفترة مفتوحة.
     *
     * بلا تجاوز: صلاحية التجاوز في شاشة الفترات تُمنح لمستخدم، ولا مستخدمَ
     * في تشغيلٍ مجدول. وخدمةٌ غير مهيّأة لا تُعطّل التسوية، لكنّ ابتلاع
     * الخطأ يُكتب في السجلّ.
     */
    private function accountingPeriodBlock(string $date): ?string
    {
        try {
            return app(AccountingPeriodService::class)->blockReason($date);
        } catch (\Throwable $e) {
            Log::warning('ProcessTargetSettlements: فحص الفترة المحاسبية أخفق — سُمح بالتسوية', [
                'date' => $date,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * خلاصةُ تشغيلٍ مستحقّ — في سجلّ النشاط وتنبيهاً لمسؤولي النظام.
     *
     * تُكتب مهما كانت النتيجة: صرفٌ، أو لا مستحقّ، أو تخطٍّ. فالصمتُ هو ما
     * جعل التسوية التلقائية تبدو معطّلة حين كانت تعمل وتجد الفترةَ فارغة.
     *
     * @param  array{count:int,total:float,errors:list<string>,targets:int}|null  $result  `null` حين يُتخطّى التشغيل
     */
    private function reportRun(TargetSetting $setting, string $fromDate, string $toDate, ?array $result, ?string $skipReason = null, ?string $targetName = null): void
    {
        $isCollections = $setting->target_type === TargetSetting::TYPE_COLLECTIONS;
        $kind = $isCollections ? 'أهداف التحصيلات' : 'أهداف المبيعات';
        $module = $isCollections ? 'CollectionsTargets' : 'SalesTargets';
        $period = "الفترة من $fromDate إلى $toDate";

        // هدفٌ انتهت مدّتُه: الخبرُ عنه باسمه وبمدّته هو، لا عن النوع كلِّه
        if ($targetName !== null) {
            $this->reportTargetRun($module, $targetName, $fromDate, $toDate, $result, $skipReason);

            return;
        }

        if ($result === null) {
            $title = "تخطّي التسوية المجدولة لـ$kind";
            $body = "لم تُنفَّذ التسوية التلقائية عن $period: ".($skipReason ?: 'سببٌ غير محدّد').'.';
        } elseif ($result['count'] > 0) {
            $title = "صُرفت عمولات التسوية المجدولة لـ$kind";
            $body = "صُرفت {$result['count']} عمولة بإجمالي ".CurrencySettings::format((float) $result['total'])." عن $period.";
        } elseif ($result['targets'] === 0) {
            $title = "التسوية المجدولة لـ$kind: لا أهداف نشطة";
            $body = "حلّ موعد التسوية التلقائية عن $period ولا هدفَ نشطاً من هذا النوع.";
        } else {
            $title = "التسوية المجدولة لـ$kind: لا عمولات مستحقّة";
            $body = "حلّ موعد التسوية التلقائية عن $period ولم يبلغ أيُّ موظفٍ في الأهداف النشطة ({$result['targets']}) مستوى عمولةٍ عنها، أو سُوّيت عمولتُه من قبل.";
            if ((int) ($setting->settlement_day ?? 1) !== 0 && $setting->settlementScope() === TargetSetting::SCOPE_PREVIOUS) {
                $body .= ' تنبيه: «ما يُسوَّى» مضبوطٌ على الدورة المنقضية — فهدفٌ أُنشئ في هذه الدورة تُصرف عمولتُه في الموعد التالي، أو اختر «الدورة الجارية حتى يوم التنفيذ» في إعدادات الأهداف.';
            }
        }

        if ($result !== null && $result['errors'] !== []) {
            $body .= ' لم يُصرف لـ: '.implode('؛ ', $result['errors']).'.';
        }

        OperationLog::record('auto_settled', $module, null, $title.' — '.$body);
        SystemAlertService::send(SystemAlertService::TARGET_SETTLEMENT_RUN, $title, $body);
    }

    /**
     * خلاصةُ تسوية هدفٍ انتهت مدّتُه — باسمه ومدّته.
     *
     * @param  array{count:int,total:float,errors:list<string>}|null  $result
     */
    private function reportTargetRun(string $module, string $name, string $fromDate, string $toDate, ?array $result, ?string $skipReason): void
    {
        $period = "مدّته من $fromDate إلى $toDate";

        if ($result === null) {
            $title = "تخطّي صرف عمولة الهدف «{$name}»";
            $body = "انتهت مدّةُ الهدف ($period) ولم تُصرف عمولتُه: ".($skipReason ?: 'سببٌ غير محدّد').'. يُعاد المحاولةُ في التشغيل التالي.';
        } elseif ($result['count'] > 0) {
            $title = "صُرفت عمولة الهدف «{$name}»";
            $body = "انتهت مدّةُ الهدف فصُرفت {$result['count']} عمولة بإجمالي ".CurrencySettings::format((float) $result['total'])." عن $period.";
        } else {
            $title = "انتهت مدّة الهدف «{$name}» بلا عمولةٍ مستحقّة";
            $body = "انتهت مدّةُ الهدف ($period) ولم يبلغ أيُّ موظفٍ فيه مستوى عمولة، أو سُوّيت عمولتُه من قبل.";
        }

        if ($result !== null && $result['errors'] !== []) {
            $body .= ' لم يُصرف لـ: '.implode('؛ ', $result['errors']).'. يُعاد المحاولةُ في التشغيل التالي.';
        }

        OperationLog::record('auto_settled', $module, null, $title.' — '.$body);
        SystemAlertService::send(SystemAlertService::TARGET_SETTLEMENT_RUN, $title, $body);
    }

    /** صنفُ الهدف لهذا النوع — أو `null` لنوعٍ لا أهدافَ له */
    private function targetClass(string $type): ?string
    {
        return match ($type) {
            'sales' => SalesTarget::class,
            'collections' => CollectionsTarget::class,
            default => null,
        };
    }

    /**
     * أهدافٌ انتهت مدّتُها وطريقةُ صرفها «مرّةً عند نهاية المدّة».
     *
     * **الموعد**: نهايةُ مدّة الهدف نفسِه — في يوم النهاية بعد بلوغ وقت
     * التسوية المضبوط، أو في أيّ يومٍ بعده إن فات (مجدولٌ متوقّف، خزينةٌ
     * ناقصة). و`period_settled_at` تُكتب حين تتمّ المعالجةُ بلا خطأ، فلا يُعاد
     * الهدفُ كلَّ يوم؛ ومن بقي له مستحقٌّ لم يُصرف يُعاد حتى يُصرف.
     *
     * **الفترة**: مدّةُ الهدف كلُّها، وقيمةُ الهدف تُقاس عليها.
     *
     * @return array{count:int,total:float}
     */
    private function processPeriodEndTargets(
        string $type,
        TargetSetting $setting,
        bool $force,
        bool $dryRun,
        TargetSettlementService $service
    ): array {
        $targetClass = $this->targetClass($type);

        if (! $targetClass || ! $targetClass::periodColumnsReady()) {
            return ['count' => 0, 'total' => 0.0];
        }

        $today = now()->toDateString();
        $ranKey = "targets:period-end-ran:$type:$today";

        // مرّةً في اليوم — المجدول يسأل كلَّ عشر دقائق
        if (! $force && cache()->get($ranKey)) {
            return ['count' => 0, 'total' => 0.0];
        }

        // وقتُ التسوية المضبوط يسري هنا أيضاً: هدفٌ ينتهي اليوم يُصرف بعد
        // بلوغه، فما يقع بعده في اليوم يخرج من المدّة
        if (! $force && ! TargetSettlementSchedule::isTimeReached($setting)) {
            return ['count' => 0, 'total' => 0.0];
        }

        $targets = $targetClass::with(['achievements.employee', 'commissionTiers'])
            ->where('status', 'active')
            ->awaitingPeriodSettlement()
            ->get();

        if ($targets->isEmpty()) {
            return ['count' => 0, 'total' => 0.0];
        }

        if (! $dryRun) {
            cache()->put($ranKey, true, now()->addHours(36));
        }

        $count = 0;
        $total = 0.0;

        foreach ($targets as $target) {
            $from = $target->start_date->toDateString();
            $to = $target->end_date->toDateString();

            $this->info("⚙️  [$type] هدف انتهت مدّتُه: {$target->name} ($from → $to)");

            if ($reason = $this->accountingPeriodBlock($to)) {
                $this->error("   ⛔ $reason");
                if (! $dryRun) {
                    $this->reportRun($setting, $from, $to, null, $reason, $target->name);
                }

                continue;
            }

            $result = $this->settleTarget($target, $type, $from, $to, $setting, $dryRun, $service, 'تسوية نهاية مدّة الهدف');

            $count += $result['count'];
            $total += $result['total'];

            $this->line("   ✓ {$result['count']} تسوية بإجمالي ".CurrencySettings::format((float) $result['total']));
            foreach ($result['errors'] as $error) {
                $this->warn('   ⚠ '.$error);
            }

            if ($dryRun) {
                continue;
            }

            // من بقي له مستحقٌّ لم يُصرف يُعاد غداً — فلا تُختم المعالجة
            if ($result['errors'] === []) {
                $target->forceFill(['period_settled_at' => now()])->save();
            }

            $this->reportRun($setting, $from, $to, $result, null, $target->name);
        }

        return ['count' => $count, 'total' => $total];
    }

    /**
     * تسويةُ هدفٍ واحد عن فترة — تحتسب لكلّ موظفٍ لم يُسوَّ ثم تستدعي الخدمة.
     *
     * @return array{count:int,total:float,errors:list<string>}
     */
    private function settleTarget(
        $target,
        string $type,
        string $fromDate,
        string $toDate,
        TargetSetting $setting,
        bool $dryRun,
        TargetSettlementService $service,
        string $note
    ): array {
        $calc = app(TargetCalculationService::class);
        $method = $setting->calculation_method ?: ($type === 'sales' ? 'net_sales' : 'total_collected');

        // من سُوّيت عمولتُه عن فترةٍ متداخلة يُتخطّى وحده — كان الهدف كلُّه
        // يُتخطّى، فمن فاتته تسويةٌ (بلا خزينة مثلاً) لا يُصرف له أبداً
        $settledEmployees = $target->settledEmployeeIdsForPeriod($fromDate, $toDate);
        $settledUsers = $settledEmployees === []
            ? []
            : User::whereIn('employee_id', $settledEmployees)->pluck('id')->map(fn ($id) => (int) $id)->all();

        $rows = [];
        foreach ($target->achievements as $ach) {
            if (in_array((int) $ach->employee_id, $settledUsers, true)) {
                continue;
            }

            // الطريقة تُمرَّر للنوعين — كانت تُمرَّر للتحصيلات وحدها،
            // فيُحتسب للمبيعات الصافي مهما اختير في الإعدادات
            $achieved = $type === 'collections'
                ? $calc->getEmployeeCollections($ach->employee_id, $fromDate, $toDate, $method)
                : $calc->getEmployeeNetSales($ach->employee_id, $fromDate, $toDate, $method);

            $commission = $target->calculateCommission($achieved, $ach->target_amount);

            if ($commission > 0) {
                $rows[] = [
                    'user_id' => $ach->employee_id,
                    'achieved_amount' => $achieved,
                    'commission_amount' => $commission,
                ];
            }
        }

        if ($rows === []) {
            return ['count' => 0, 'total' => 0.0, 'errors' => []];
        }

        if ($dryRun) {
            $batchTotal = (float) array_sum(array_column($rows, 'commission_amount'));
            $this->line("   🔍 [dry-run] target={$target->id} ({$target->name}) $fromDate → $toDate — ".count($rows).' موظف بإجمالي '.CurrencySettings::format($batchTotal));

            return ['count' => count($rows), 'total' => $batchTotal, 'errors' => []];
        }

        try {
            $result = $service->settle($target, $fromDate, $toDate, $rows, $note, null, true);

            Log::info("ProcessTargetSettlements: target={$target->id} settled", [
                'from' => $fromDate,
                'to' => $toDate,
                'created' => $result['settlements_created'],
                'total' => $result['total_commission'],
                'errors' => $result['errors'],
            ]);

            return [
                'count' => (int) $result['settlements_created'],
                'total' => (float) $result['total_commission'],
                'errors' => array_map(fn ($error) => "{$target->name}: $error", $result['errors']),
            ];
        } catch (\Throwable $e) {
            $this->error("   ❌ فشل تسوية target={$target->id}: ".$e->getMessage());
            Log::error("ProcessTargetSettlements: target={$target->id} failed", ['error' => $e->getMessage()]);

            return ['count' => 0, 'total' => 0.0, 'errors' => ["{$target->name}: ".$e->getMessage()]];
        }
    }

    /**
     * معالجة أهداف نوع معين خلال الفترة - تستدعي الـ Service الموحَّد.
     */
    private function processTargetsOfType(
        string $type,
        string $fromDate,
        string $toDate,
        TargetSetting $setting,
        bool $dryRun,
        TargetSettlementService $service
    ): array {
        $count = 0;
        $total = 0.0;
        $errors = [];

        $targetClass = $this->targetClass($type);
        if (! $targetClass) {
            $this->line("   ℹ نوع [$type] غير مدعوم.");

            return ['count' => 0, 'total' => 0.0, 'errors' => [], 'targets' => 0];
        }

        /* الدوريّ يخصّ هدفين: ما لا مدّةَ له (كما كان)، وما له مدّةٌ واختير له
           «دوريّاً داخل المدّة». وما يُصرف مرّةً عند نهاية مدّته يخرج من هنا —
           له {@see processPeriodEndTargets()}. */
        $targets = $targetClass::with(['achievements.employee', 'commissionTiers'])
            ->where('status', 'active')
            ->get()
            ->reject(fn ($target) => $target->settlesAtPeriodEnd())
            ->values();

        foreach ($targets as $target) {
            /* الدورةُ تُقصّ على مدّة الهدف: كانت عمولةُ «الشهر المنقضي» تُصرف
               لهدفٍ بدأ هذا الشهر، عن أيّامٍ لم يكن الهدفُ فيها قائماً. */
            $period = $target->clampToPeriod($fromDate, $toDate);

            if ($period === null) {
                $this->line("   ⏭  target={$target->id} ({$target->name}): الدورة خارج مدّة الهدف");

                continue;
            }

            $result = $this->settleTarget(
                $target, $type, $period[0], $period[1], $setting, $dryRun, $service,
                "تسوية مجدولة تلقائية ({$setting->settlement_frequency})"
            );

            $count += $result['count'];
            $total += $result['total'];
            $errors = array_merge($errors, $result['errors']);
        }

        return ['count' => $count, 'total' => $total, 'errors' => $errors, 'targets' => $targets->count()];
    }

    // ملاحظة: تم إزالة getEmployeeNetSales المُكرَّر — يُستخدم TargetCalculationService بدلاً منه.
}
