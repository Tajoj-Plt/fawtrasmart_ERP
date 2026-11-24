<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CleanupExpiredProjectInvites extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'project-invites:cleanup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'تنظيف الدعوات المنتهية الصلاحية للمشاريع';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('جاري تنظيف الدعوات المنتهية الصلاحية...');

        try {
            $expiredCount = DB::table('project_user')
                ->where('status', 'pending')
                ->where('expires_at', '<', now())
                ->update(['status' => 'expired']);

            Log::info("Cleaned up {$expiredCount} expired project invites");

            $this->info("تم تنظيف {$expiredCount} دعوة منتهية الصلاحية");
            return 0;
        } catch (\Exception $e) {
            Log::error('Cleanup expired invites error: ' . $e->getMessage());
            $this->error('حدث خطأ أثناء تنظيف الدعوات المنتهية: ' . $e->getMessage());
            return 1;
        }
    }
}