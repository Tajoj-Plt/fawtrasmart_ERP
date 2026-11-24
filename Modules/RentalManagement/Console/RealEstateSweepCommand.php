<?php

namespace Modules\RentalManagement\Console;

use Illuminate\Console\Command;
use Modules\RentalManagement\Services\ContractService;

class RealEstateSweepCommand extends Command
{
    protected $signature = 'realestate:sweep';

    protected $description = 'إدارة العقارات: إنهاء العقود المنقضية وتحرير وحداتها، تحديث حالات الاستحقاقات، وإنهاء العروض المنتهية.';

    public function handle(ContractService $contracts): int
    {
        $result = $contracts->dailySweep();

        $this->info(sprintf('عقود انتهت: %d — استحقاقات حُدّثت: %d', $result['expired'], $result['schedules']));

        return self::SUCCESS;
    }
}
