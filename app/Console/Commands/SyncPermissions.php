<?php

namespace App\Console\Commands;

use App\Models\JobRole;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;

class SyncPermissions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'permissions:sync';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'مزامنة صلاحيات النظام القديم من JobRole إلى جدول permissions (للتصنيف: permissions:sync-catalog)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔄 جاري مزامنة الصلاحيات...');
        
        $permissions = JobRole::$job_roles;
        $createdCount = 0;
        $existingCount = 0;

        $progressBar = $this->output->createProgressBar(count($permissions));
        $progressBar->start();

        foreach ($permissions as $permission) {
            $exists = Permission::where('name', $permission)->exists();
            
            if (!$exists) {
                Permission::create([
                    'name' => $permission,
                    'guard_name' => 'web'
                ]);
                $createdCount++;
            } else {
                $existingCount++;
            }
            
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        $this->info("✅ تم إنشاء {$createdCount} صلاحية جديدة");
        $this->info("ℹ️  {$existingCount} صلاحية موجودة مسبقاً");
        $this->info("📊 إجمالي الصلاحيات: " . count($permissions));
        
        // ما يُنشأ هنا يخرج بلا تصنيف — صفٌّ بلا `module` تضعه شاشة الأدوار
        // في «غير مصنَّفة». المزامنة تكتب له قسمه إن كان في كتالوجٍ ما،
        // وتُبلّغ عنه إن لم يكن.
        $this->call('permissions:sync-catalog');

        // Reset cache
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        $this->info("🔄 تم تحديث cache الصلاحيات");

        return Command::SUCCESS;
    }
}
