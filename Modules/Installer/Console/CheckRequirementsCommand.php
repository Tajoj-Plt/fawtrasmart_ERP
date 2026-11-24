<?php

namespace Modules\Installer\Console;

use Illuminate\Console\Command;
use Modules\Installer\Services\InstallationState;
use Modules\Installer\Services\RequirementsChecker;

/**
 * فحص المتطلبات وحده — يُشغَّل قبل النشر للتأكد من صلاحية الخادم،
 * وفي CI للتحقّق من أن بيئة الاختبار تطابق بيئة الإنتاج.
 */
class CheckRequirementsCommand extends Command
{
    protected $signature = 'installer:check';

    protected $description = 'فحص متطلبات تشغيل النظام (PHP، الامتدادات، صلاحيات الكتابة)';

    public function handle(RequirementsChecker $checker): int
    {
        $titles = [
            'php' => 'إصدار PHP',
            'extensions' => 'امتدادات PHP',
            'permissions' => 'صلاحيات الكتابة',
        ];

        foreach ($checker->all() as $group => $checks) {
            $this->newLine();
            $this->components->info($titles[$group] ?? $group);

            foreach ($checks as $check) {
                $this->components->twoColumnDetail(
                    $check['label'],
                    $check['passed'] ? '<fg=green>'.$check['current'].'</>' : '<fg=red>'.$check['current'].'</>'
                );
            }
        }

        $this->newLine();

        $this->components->twoColumnDetail(
            'حالة التثبيت',
            InstallationState::isInstalled() ? '<fg=green>مثبَّت</>' : '<fg=yellow>غير مثبَّت</>'
        );

        if (! $checker->passes()) {
            $this->components->error('بعض المتطلبات غير مستوفاة.');

            return self::FAILURE;
        }

        $this->components->info('كل المتطلبات مستوفاة.');

        return self::SUCCESS;
    }
}
