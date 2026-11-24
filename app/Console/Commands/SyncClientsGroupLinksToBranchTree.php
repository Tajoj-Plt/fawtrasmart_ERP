<?php

namespace App\Console\Commands;

use App\Services\RegionGroubsToBranchesMigrationService;
use Illuminate\Console\Command;

/**
 * يحدّث branch_id و region_id في الحي اعتماداً على شجرة الفروع،
 * ويُفرّغ group_id عند وجوده بعد الربط الصحيح (نفس منطق ترحيل المجموعات مع العملاء).
 */
class SyncClientsGroupLinksToBranchTree extends Command
{
    protected $signature = 'clients:sync-group-links-to-branch-tree
                            {--dry-run : عرض عدد السجلات دون تعديل}
                            {--keep-group-id : عدم تصفير group_id بعد المزامنة}';

    protected $description = 'مزامنة عملاء مرتبطين بمجموعات/مناطق مع شجرة الفروع (branch_id + حي.region_id) بما فيهم group_id';

    public function handle(RegionGroubsToBranchesMigrationService $migrationService): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $keepGroupId = (bool) $this->option('keep-group-id');

        $result = $migrationService->syncAllClientsBranchTreeFromAnchors($dryRun, $keepGroupId);

        if ($dryRun) {
            $this->info("وضع تجريبي: يمكن مزامنة {$result['updated']} عميلاً، متخطى: {$result['skipped']}.");
        } else {
            $this->info("تمت مزامنة {$result['updated']} عميلاً، متخطى: {$result['skipped']}.");
        }

        return self::SUCCESS;
    }
}
