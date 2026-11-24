<?php

namespace App\Console\Commands;

use App\Models\Backup;
use App\Models\BackupSetting;
use App\Services\Backup\BackupCleanupService;
use App\Services\Backup\BackupService;
use App\Services\Cron\CronJobRunner;
use Illuminate\Console\Command;

/**
 * النسخ الاحتياطي المجدول.
 *
 * هو نفسه مهمة داخل `cron_jobs`، فيظهر في شاشة المهام وشاشة النسخ
 * الاحتياطي معاً، ومصدر إعداداته واحد: `backup_settings`. تفعيل النسخ
 * الدوري من الواجهة يعني تفعيل هذه المهمة، لا شيء آخر.
 */
class RunScheduledBackup extends Command
{
    protected $signature = 'backup:scheduled-run
                            {--type= : تجاوز النوع المضبوط (database|files|both)}
                            {--all-tables : نسخة كاملة مهما كان نطاق الجداول في الإعدادات}
                            {--tables= : أسماء جداول مفصولة بفواصل — تُنسَخ وحدها}
                            {--exclude-tables= : أسماء جداول مفصولة بفواصل — تُستبعَد من النسخة}
                            {--skip-cleanup : عدم تطبيق سياسة الاحتفاظ بعد النسخ}';

    protected $description = 'تنفيذ النسخة الاحتياطية حسب الإعدادات ثم تطبيق سياسة الاحتفاظ';

    public function handle(BackupService $service, BackupCleanupService $cleanup): int
    {
        $settings = BackupSetting::current();

        // المهمة قد تكون نشطة في جدول المهام بينما أوقف المسؤول النسخ
        // الاحتياطي من شاشته — الإعداد هو الحاكم
        if (! $settings->is_enabled) {
            // مفتاح واحد يحكم كل الجداول المجدولة لا الجدول الأساسي وحده:
            // «أوقف الحفظ التلقائي» يجب أن يعني ما يقوله مهما بلغ عدد
            // المهام المضافة
            $this->warn(
                'الحفظ التلقائي معطَّل في إعدادات النسخ الاحتياطي — لم يُنفَّذ شيء. '
                .'فعّله من: الإعدادات ← النسخ الاحتياطي ← الإعدادات ← «تفعيل الحفظ التلقائي».'
            );

            return self::SUCCESS;
        }

        $type = $this->option('type') ?: $settings->backup_type;
        $selection = $this->tableSelection();

        $this->info('بدء النسخ الاحتياطي ('.$type.')…');

        // الجدول الذي شغّل الأمر — يُوسَم به الصفّ فتعرف شاشة الجداول أي
        // نسخة أنتجها أيّها. null حين يُشغَّل الأمر من الطرفية مباشرة
        $backup = $service->run($type, Backup::TRIGGER_SCHEDULED, null, CronJobRunner::$current, $selection);

        if ($backup->isPartial()) {
            $this->line('نطاق الجداول: '.$backup->tableScopeLabel());
        }

        if ($backup->status !== Backup::STATUS_COMPLETED) {
            $this->error('فشل النسخ الاحتياطي: '.$backup->error_message);

            // شفرة خروج غير صفرية تجعل المُشغّل يسجّلها فشلاً في سجلّ
            // المهام كما ينبغي
            return self::FAILURE;
        }

        $this->info('اكتملت النسخة: '.$backup->file_name.' ('.$backup->humanSize().')');

        if (! $this->option('skip-cleanup')) {
            $result = $cleanup->run($settings);

            $this->line(
                'التنظيف: حُذفت '.$result['deleted'].' نسخة، '
                .'أُفرغ '.Backup::formatBytes($result['freed_bytes']).'، '
                .'أُبقيت '.$result['kept'].'.'
            );

            foreach ($result['errors'] as $error) {
                $this->warn($error);
            }
        }

        return self::SUCCESS;
    }

    /**
     * اختيار الجداول من خيارات الأمر.
     *
     * وجوده يتيح جدولة أكثر من مهمة نسخ بنطاقات مختلفة — مثلاً نسخة
     * كاملة أسبوعياً ونسخة جداول الحركات كل ساعة — دون أن يتنازع
     * الجدولان على إعداد واحد. أسماء الجداول تُصفَّى في الخدمة نفسها،
     * فالخيار هنا لا يوسّع ما هو مسموح.
     *
     * @return array{mode:string,included?:array<int,string>,excluded?:array<int,string>}|null
     */
    protected function tableSelection(): ?array
    {
        $split = fn (?string $value) => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) $value)
        ), fn ($name) => $name !== ''));

        // «كاملة» تسبق الباقي: مهمة نسخة كاملة أسبوعية يجب أن تبقى كاملة
        // مهما ضُبط في الإعدادات من استبعاد جداول
        if ($this->option('all-tables')) {
            return ['mode' => 'all'];
        }

        if ($this->option('tables')) {
            return ['mode' => 'include', 'included' => $split($this->option('tables'))];
        }

        if ($this->option('exclude-tables')) {
            return ['mode' => 'exclude', 'excluded' => $split($this->option('exclude-tables'))];
        }

        return null; // بلا خيار: تُتَّبع الإعدادات المحفوظة
    }
}
