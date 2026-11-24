<?php

namespace Modules\Installer\Console;

use Illuminate\Console\Command;
use Modules\Installer\Services\DatabaseInstaller;
use Modules\Installer\Services\InstallationState;
use Modules\Installer\Services\RequirementsChecker;
use Modules\Installer\Services\SettingsInstaller;

/**
 * التثبيت من الطرفية.
 *
 * المعالج الرسومي يسقط على الاستضافات المشتركة حين تتجاوز الهجرات مهلة
 * PHP — و467 هجرة جذرية تتجاوزها كثيراً. هذا الأمر يفعل الشيء نفسه بلا
 * مهلة، ويصلح كذلك للنشر الآلي حيث لا متصفّح أصلاً.
 *
 * يفترض أن `.env` مضبوط على قاعدة صحيحة — لا يكتبه ولا يعدّله.
 */
class InstallCommand extends Command
{
    protected $signature = 'installer:install
                            {--admin-name= : اسم المدير}
                            {--admin-email= : بريد المدير}
                            {--admin-password= : كلمة مرور المدير}
                            {--company= : اسم المنشأة}
                            {--language=ar : اللغة الافتراضية}
                            {--currency=SAR : العملة الافتراضية}
                            {--timezone=Asia/Riyadh : المنطقة الزمنية}
                            {--tax=15 : نسبة ضريبة القيمة المضافة}
                            {--fresh : إسقاط كل الجداول وإعادة بنائها}
                            {--demo : زرع البيانات التجريبية}
                            {--force : المتابعة رغم وجود علامة تثبيت سابقة}';

    protected $description = 'تثبيت النظام: الهجرات والبذور الأساسية وتجهيز الإعدادات';

    public function handle(
        RequirementsChecker $requirements,
        DatabaseInstaller $database,
        SettingsInstaller $settings
    ): int {
        $this->components->info('تثبيت النظام');

        if (InstallationState::isInstalled() && ! $this->option('force')) {
            $this->components->error('النظام مثبَّت بالفعل. استعمل --force لإعادة التثبيت.');

            return self::FAILURE;
        }

        // ---- المتطلبات ----
        if (! $requirements->passes()) {
            $this->components->error('متطلبات غير مستوفاة:');

            foreach ($requirements->failures() as $failure) {
                $this->components->twoColumnDetail($failure['label'], $failure['current']);
            }

            return self::FAILURE;
        }

        $this->components->info('المتطلبات مستوفاة.');

        // ---- جمع المدخلات ----
        $adminName = $this->option('admin-name') ?: $this->ask('اسم المدير', 'مدير النظام');
        $adminEmail = $this->option('admin-email') ?: $this->ask('بريد المدير');
        $adminPassword = $this->option('admin-password') ?: $this->secret('كلمة مرور المدير');
        $company = $this->option('company') ?: $this->ask('اسم المنشأة', config('app.name'));

        if (! $adminEmail || ! $adminPassword) {
            $this->components->error('بريد المدير وكلمة المرور إلزاميان.');

            return self::FAILURE;
        }

        if (strlen($adminPassword) < 8) {
            $this->components->error('كلمة المرور يجب ألّا تقلّ عن ثمانية أحرف.');

            return self::FAILURE;
        }

        $fresh = (bool) $this->option('fresh');

        if ($fresh && $database->hasData() && ! $this->confirm('القاعدة تحتوي بيانات — سيُحذف كل شيء. أتريد المتابعة؟', false)) {
            return self::FAILURE;
        }

        // ---- الهجرات ----
        $this->components->task('تشغيل الهجرات', function () use ($database, $fresh, &$migration) {
            $migration = $database->migrate($fresh);

            return $migration['ok'];
        });

        if (! ($migration['ok'] ?? false)) {
            $this->components->error($migration['message'] ?? 'فشلت الهجرات.');

            return self::FAILURE;
        }

        // ---- البذور ----
        $core = $database->seed(DatabaseInstaller::CORE_SEEDERS);

        foreach ($core['results'] as $result) {
            $this->components->twoColumnDetail(
                $result['seeder'],
                $result['ok'] ? '<fg=green>تم</>' : ($result['skipped'] ? '<fg=yellow>تُخطِّي</>' : '<fg=red>فشل</>')
            );
        }

        if (! $core['ok']) {
            $this->components->warn('تعثّرت بذور: '.implode('، ', $core['failed']));
        }

        if ($this->option('demo')) {
            $demo = $database->seed(DatabaseInstaller::DEMO_SEEDERS);

            $this->components->info($demo['ok'] ? 'زُرعت البيانات التجريبية.' : 'تعثّرت بيانات تجريبية: '.implode('، ', $demo['failed']));
        }

        // ---- الإعدادات ----
        $applied = $settings->run([
            'admin' => [
                'name' => $adminName,
                'email' => $adminEmail,
                'password' => $adminPassword,
            ],
            'company' => ['name' => $company],
            'settings' => [
                'language' => $this->option('language'),
                'currency' => $this->option('currency'),
                'timezone' => $this->option('timezone'),
                'tax_percent' => (float) $this->option('tax'),
                'tax_type' => 'excluded',
                'business_type' => 'products',
                'date_format' => 'Y-m-d',
                'time_format' => 'H:i',
            ],
        ]);

        foreach ($applied as $result) {
            $this->components->twoColumnDetail($result['message'], $result['ok'] ? '<fg=green>تم</>' : '<fg=yellow>ملاحظة</>');
        }

        InstallationState::markInstalled([
            'company' => $company,
            'admin_email' => $adminEmail,
            'demo_data' => (bool) $this->option('demo'),
            'via' => 'cli',
        ]);

        $this->newLine();
        $this->components->info('تمّ التثبيت. سجّل الدخول بـ '.$adminEmail);
        $this->components->warn('لا تنسَ إضافة schedule:run إلى cron الخادم.');

        return self::SUCCESS;
    }
}
