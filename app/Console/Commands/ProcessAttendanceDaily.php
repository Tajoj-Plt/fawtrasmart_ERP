<?php

namespace App\Console\Commands;

use App\Services\AttendanceProcessorService;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * أمر معالجة الحضور اليومي.
 *
 * يُشغَّل من «المهام المجدولة» (cron_jobs ← attendance:process) كل ليلة.
 *
 * بلا تاريخ يعالج **أمس واليوم**: اليوم ليُسجَّل غيابُ من لم يحضر، وأمسِ
 * ليُقفَل نهائياً — كان يعالج اليوم وحده الساعة 23:55، فيومُ الدوام المرن
 * (لا يُحكم بغيابه قبل انقضاء اليوم) ويومُ الوردية الليلية (يبدأ قبل منتصف
 * الليل) يبقيان «لم يُحسما» إلى الأبد. وإعادةُ معالجة يومٍ آمنة: الخصمياتُ
 * تُزامَن ولا تتكرّر (`syncDayDeductions`).
 *
 * تستطيع تشغيله يدوياً:
 *   php artisan attendance:process              → أمس واليوم
 *   php artisan attendance:process 2026-05-13   → تاريخ محدد
 *   php artisan attendance:process --backfill=7 → آخر 7 أيام
 */
class ProcessAttendanceDaily extends Command
{
    protected $signature = 'attendance:process
                            {date? : التاريخ المراد معالجته (Y-m-d). افتراضياً اليوم}
                            {--backfill= : عدد الأيام السابقة للمعالجة بأثر رجعي}';

    protected $description = 'معالجة الحضور اليومي: حساب التأخير/الغياب، إنشاء خصميات تلقائية، احترام العطلات والإجازات';

    public function handle(AttendanceProcessorService $service): int
    {
        $backfill = (int) $this->option('backfill');

        $dates = [];
        if ($backfill > 0) {
            for ($i = $backfill - 1; $i >= 0; $i--) {
                $dates[] = now()->subDays($i);
            }
        } else {
            $dateArg = $this->argument('date');
            $dates = $dateArg ? [Carbon::parse($dateArg)] : [now()->subDay(), now()];
        }

        $totalStats = [
            'processed' => 0, 'present' => 0, 'absent' => 0, 'late' => 0,
            'leave' => 0, 'holiday' => 0, 'deductions' => 0, 'errors' => [],
        ];

        foreach ($dates as $date) {
            $this->info("🔄 معالجة تاريخ: {$date->toDateString()}");
            $stats = $service->processDay($date);

            $this->table(
                ['الموظفون المعالجون', 'حاضر', 'غائب', 'متأخر', 'إجازة', 'عطلة', 'خصميات منشأة'],
                [[
                    $stats['processed'],
                    $stats['present'],
                    $stats['absent'],
                    $stats['late'],
                    $stats['leave'],
                    $stats['holiday'],
                    $stats['deductions'],
                ]]
            );

            // تجميع الإحصائيات
            foreach (['processed', 'present', 'absent', 'late', 'leave', 'holiday', 'deductions'] as $key) {
                $totalStats[$key] += $stats[$key];
            }
            $totalStats['errors'] = array_merge($totalStats['errors'], $stats['errors']);

            if (!empty($stats['errors'])) {
                $this->warn('⚠️ أخطاء:');
                foreach ($stats['errors'] as $err) {
                    $this->line('   - ' . $err);
                }
            }
        }

        $this->newLine();
        $this->info('✅ اكتملت المعالجة');
        $this->info("الإجمالي: {$totalStats['processed']} موظف × " . count($dates) . " أيام");
        $this->info("الخصميات المُنشأة: {$totalStats['deductions']}");

        return self::SUCCESS;
    }
}
