<?php

namespace Modules\Installer\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Modules\Installer\Services\InstallationRunner;
use Modules\Installer\Services\InstallationState;
use Throwable;

/**
 * إعادة فتح المعالج — المخرج الرسمي من «النظام مثبَّت بالفعل».
 *
 * **لماذا لا يكفي حذف `storage/app/installed`؟** لأن العلامة ليست الحارس
 * الوحيد: إن وجدت القاعدةُ مستخدمين وشجرةَ حسابات اعتُبر النظام مثبَّتاً
 * وكُتبت العلامة من جديد في أول طلب. فمن يحذف الملف ويعيد التحميل يرى
 * الرسالة نفسها ويظنّ أن شيئاً معطوب — وهو ليس كذلك، لكن الرسالة كانت تصف
 * نصف الحقيقة.
 *
 * فهذا الأمر يعالج الحالتين معاً: يمحو العلامة وملفات التشغيل العالقة، ومع
 * `--fresh` يُفرغ القاعدة أيضاً فيزول سبب الاكتشاف.
 */
class ResetInstallationCommand extends Command
{
    protected $signature = 'install:reset
        {--fresh : إسقاط كل جداول قاعدة البيانات أيضاً — لا رجعة فيه}
        {--force : بلا سؤال تأكيد}';

    protected $description = 'إعادة فتح معالج التثبيت (وإفراغ القاعدة مع --fresh)';

    public function handle(): int
    {
        $fresh = (bool) $this->option('fresh');

        if ($fresh && ! $this->option('force')) {
            $database = DB::connection()->getDatabaseName();

            // التأكيد يذكر اسم القاعدة: أشيع خطأ في هذا الأمر أن يُنفَّذ
            // و`.env` يشير إلى قاعدة الإنتاج لا إلى التجريبية
            if (! $this->confirm("سيُسقط كل جدول في «{$database}». متأكّد؟", false)) {
                $this->warn('أُلغي.');

                return self::FAILURE;
            }
        }

        $removed = [];

        foreach ([
            InstallationState::markerPath(),
            InstallationRunner::statePath(),
            InstallationRunner::lockPath(),
            storage_path('app/installer-journal.jsonl'),
        ] as $path) {
            if (file_exists($path) && @unlink($path)) {
                $removed[] = basename($path);
            }
        }

        $this->line($removed === []
            ? '  لا ملفات تثبيت لحذفها.'
            : '  حُذف: '.implode('، ', $removed));

        if ($fresh) {
            try {
                Artisan::call('db:wipe', ['--force' => true], $this->output);
                $this->line('  أُسقطت جداول القاعدة.');
            } catch (Throwable $e) {
                $this->error('  تعذّر إسقاط الجداول: '.$e->getMessage());

                return self::FAILURE;
            }
        } else {
            $this->warnIfDatabaseStillLooksInstalled();
        }

        // الذاكرة الساكنة تحمل جواب هذا الطلب — وتصفيرها يجعل الأمر صالحاً
        // للاستدعاء برمجياً في الاختبارات لا من الطرفية وحدها
        InstallationState::forget();

        $this->info('المعالج مفتوح الآن على /install');

        return self::SUCCESS;
    }

    /**
     * تحذير مبكر خيرٌ من رسالةٍ في المتصفّح: القاعدة المملوءة ستُعيد كتابة
     * العلامة في أول طلب، فيبدو الأمر كأنه لم يفعل شيئاً.
     */
    protected function warnIfDatabaseStillLooksInstalled(): void
    {
        try {
            if (! DB::getSchemaBuilder()->hasTable('users')) {
                return;
            }

            $users = DB::table('users')->count();

            if ($users > 0) {
                $this->warn("  تنبيه: القاعدة ما زالت تحمل {$users} مستخدماً — سيُعاد قفل المعالج تلقائياً.");
                $this->warn('  للتثبيت من الصفر: php artisan install:reset --fresh');
            }
        } catch (Throwable) {
            // لا اتصال بالقاعدة — وهذا في ذاته يعني أن المعالج سيُفتح
        }
    }
}
