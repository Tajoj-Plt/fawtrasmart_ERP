<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\CustomerTargetService;

class EvaluateClientTargets extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'clients:evaluate-targets';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Evaluate all clients against target settings and update their target dynamically based on lifetime history.';

    /**
     * Execute the console command.
     */
    public function handle(CustomerTargetService $service)
    {
        $this->info('Starting dynamic client target evaluations...');
        
        // Evaluate all clients over their lifetime (passing null for dates)
        $downgradedClients = $service->evaluateAllClients();

        // إنشاء إشعار للإدارة
        $admins = \App\Models\User::where('role', 'manager')->get();
        foreach ($admins as $admin) {
            $description = 'تم الانتهاء من عملية الفحص وإعادة التقييم الديناميكي لجميع العملاء بنجاح.';
            if (!empty($downgradedClients)) {
                $downgradedCount = count($downgradedClients);
                $description .= " (تم تسجيل تراجع في مستوى {$downgradedCount} عميل/عملاء).";
            }

            // 1. الإشعار العام بانتهاء العملية
            \App\Models\notifications::create([
                'title' => 'تم تقييم العملاء آلياً',
                'description' => $description,
                'type' => 'target_evaluation',
                'read' => 0,
                'is_sent' => 1,
                'popup_shown' => 1,
                'receiver_id' => $admin->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->info("Evaluation finished successfully.");
    }
}
