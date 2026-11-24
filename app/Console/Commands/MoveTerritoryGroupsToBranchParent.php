<?php

namespace App\Console\Commands;

use App\Services\TerritoryGroupsMoveService;
use Illuminate\Console\Command;

/**
 * @see TerritoryGroupsMoveService للتنفيذ من الواجهة: مسار branches/move-territory-groups
 */
class MoveTerritoryGroupsToBranchParent extends Command
{
    protected $signature = 'branches:move-territory-groups
                            {--parent-id= : معرّف الفرع الأب في جدول branches}
                            {--ids= : معرّفات المجموعات/المناطق مفصولة بفاصلة (مثال: 12,15,20)}
                            {--dry-run : عرض العملية دون حفظ}
                            {--skip-client-sync : عدم تحديث فرع المحاسبة للعملاء بعد النقل}';

    protected $description = 'نقل مجموعات المناطق تحت أب في شجرة الفروع وتحديث عملائهم';

    public function handle(TerritoryGroupsMoveService $service): int
    {
        $parentId = $this->option('parent-id');
        if ($parentId === null || $parentId === '') {
            $this->error('يجب تمرير --parent-id= معرّف الفرع الأب.');

            return self::FAILURE;
        }

        $idsRaw = (string) ($this->option('ids') ?? '');
        $groupIds = collect(preg_split('/[\s,]+/', $idsRaw, -1, PREG_SPLIT_NO_EMPTY))
            ->map(fn ($v) => (int) $v)
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($groupIds === []) {
            $this->error('يجب تمرير --ids= بقائمة معرفات (مثال: --ids=10,11,22).');

            return self::FAILURE;
        }

        $result = $service->run(
            (int) $parentId,
            $groupIds,
            (bool) $this->option('dry-run'),
            (bool) $this->option('skip-client-sync'),
        );

        foreach ($result['messages'] as $line) {
            $this->line($line);
        }

        foreach ($result['errors'] as $err) {
            $this->error($err);
        }

        return $result['success'] ? self::SUCCESS : self::FAILURE;
    }
}
