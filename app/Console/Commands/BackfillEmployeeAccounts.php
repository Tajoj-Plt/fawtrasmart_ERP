<?php

namespace App\Console\Commands;

use App\Models\Employee;
use Illuminate\Console\Command;
use Modules\HR\Http\Controllers\EmployeeController;

/**
 * ربط الموظفين القائمين بحساب الرواتب المستحقة (وحساب السلف).
 *
 * **المشكلة**
 *
 * إنشاء `salary_payable_account_id`/`employee_advance_account_id` يقع في
 * `EmployeeController::store()`/`update()` وحدهما — فالموظف الذي دخل
 * النظام من غير تلك الشاشة (بذرة، استيراد، هجرة من نظام سابق) يبقى بلا
 * حساب. والصرف يفشل بدونه (`PayrollAccountant` يشترط الحساب موجوداً قبل
 * أن يُرحِّل قيد الصرف) — بلا أي تحذيرٍ مسبق، يُكتشف فقط لحظة محاولة صرف
 * راتب موظفٍ بذرةٍ لم يمرّ بشاشة الحفظ أبداً.
 *
 * **ما يفعله هذا الأمر**
 *
 * يستدعي لكل موظفٍ بلا حساب نفس الدالّة التي تستدعيها شاشة الحفظ
 * (`EmployeeController::syncEmployeeLiabilityAccounts()`) — لا يعيد كتابة
 * منطق إنشاء الحساب ولا حلّ الحساب الأب، فيبقى السلوك متطابقاً تماماً مع
 * ما يحدث فعلاً عند حفظ موظفٍ من الواجهة.
 *
 * الأمر **قابل للتكرار بلا ضرر**: يتخطّى كل موظفٍ له الحسابان أصلاً.
 *
 *   php artisan employees:backfill-accounts --dry-run   ← معاينة بلا كتابة
 *   php artisan employees:backfill-accounts             ← التنفيذ
 *   php artisan employees:backfill-accounts --limit=50  ← دفعة محدودة
 */
class BackfillEmployeeAccounts extends Command
{
    protected $signature = 'employees:backfill-accounts
                            {--dry-run : عرض ما سيحدث بلا كتابة}
                            {--limit= : الاكتفاء بهذا العدد من الموظفين}';

    protected $description = 'ربط الموظفين الذين لا حساب رواتب مستحقة لهم بحساب حقيقي في شجرة الحسابات';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;

        $query = Employee::query()
            ->whereNull('salary_payable_account_id')
            ->orderBy('id');

        $total = (clone $query)->count();

        if ($total === 0) {
            $this->info('كل الموظفين مرتبطون بحساب رواتبهم المستحقة — لا شيء ليُنشأ.');

            return self::SUCCESS;
        }

        if ($limit) {
            $query->limit($limit);
        }

        $this->line('موظفون بلا حساب رواتب مستحقة: <comment>'.number_format($total).'</comment>'
            .($limit ? '  (سيُعالَج '.number_format(min($limit, $total)).')' : ''));

        if ($dryRun) {
            $this->newLine();
            $this->warn('وضع المعاينة — لن يُكتب شيء.');
            $this->newLine();
        }

        $controller = app(EmployeeController::class);

        $processed = 0;
        $failed = 0;
        $bar = $this->output->createProgressBar($limit ? min($limit, $total) : $total);
        $bar->start();

        $query->chunkById(200, function ($employees) use ($controller, $dryRun, &$processed, &$failed, $bar) {
            foreach ($employees as $employee) {
                if ($dryRun) {
                    $processed++;
                    $bar->advance();

                    continue;
                }

                try {
                    $controller->syncEmployeeLiabilityAccounts($employee);
                    $processed++;
                } catch (\Throwable $e) {
                    $failed++;
                    $this->newLine();
                    $this->error("فشل الموظف #{$employee->id}: ".$e->getMessage());
                }

                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);

        $this->info(($dryRun ? 'سيُربَط: ' : 'رُبِط: ').number_format($processed).' موظف بحسابه');

        if ($failed) {
            $this->warn('فشل '.number_format($failed).' موظفاً — راجع الأخطاء أعلاه.');
        }

        if ($dryRun) {
            $this->line('لتنفيذ العملية فعلاً: <info>php artisan employees:backfill-accounts</info>');
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
