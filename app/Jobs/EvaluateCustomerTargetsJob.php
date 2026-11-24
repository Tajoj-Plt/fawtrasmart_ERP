<?php

namespace App\Jobs;

use App\Services\CustomerTargetService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class EvaluateCustomerTargetsJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(CustomerTargetService $service): void
    {
        // افترض أننا نقيم لجميع الأوقات أو لفترة محددة في الإعدادات
        $service->evaluateAllClients();
    }
}
