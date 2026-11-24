<?php

namespace App\Console\Commands;

use App\Services\RegionGroubsToBranchesMigrationService;
use Illuminate\Console\Command;

class MigrateRegionGroubsToBranchesCommand extends Command
{
    protected $signature = 'region-groubs:migrate-to-branches
                            {--dry-run : عرض العدد دون تنفيذ}
                            {--keep-table : عدم حذف جدول region_groubs بعد الترحيل}';

    protected $description = 'ترحيل بيانات region_groubs إلى branches وتحديث المراجع (يمكن إزالة الاعتماد على جدول region_groubs)';

    public function handle(RegionGroubsToBranchesMigrationService $service): int
    {
        $result = $service->migrate(
            (bool) $this->option('dry-run'),
            !$this->option('keep-table'),
        );

        $this->info($result['message']);
        $this->line('عدد السجلات في region_groubs: ' . $result['region_groubs_count']);
        $this->line('صفوف جديدة في branches: ' . $result['inserted'] . ' | معاد استخدام موجود: ' . $result['reused_existing'] . ' | متخطى (لا فرع أب): ' . $result['skipped_no_parent']);

        if ($this->option('dry-run') && ($result['dry_run_clients_linked'] ?? 0) > 0) {
            $this->line('عملاء مربوطون بالمجموعات (حي أو group_id) سيُعاد ضبطهم بعد التنفيذ الفعلي: ' . $result['dry_run_clients_linked']);
        }

        if (!$this->option('dry-run') && $result['region_groubs_count'] > 0) {
            foreach ($result['fk_updates'] as $k => $v) {
                $this->line("تحديث {$k}: {$v} صفاً");
            }
            $this->line('حذف جدول region_groubs: ' . ($result['dropped_table'] ? 'نعم' : 'لا'));
            if (($result['clients_synced'] ?? 0) > 0) {
                $this->line('تم ضبط فرع المحاسبة والحي للعملاء المربوطين: ' . $result['clients_synced'] . ' عميلاً');
            }
        }

        return $result['success'] ? self::SUCCESS : self::FAILURE;
    }
}
