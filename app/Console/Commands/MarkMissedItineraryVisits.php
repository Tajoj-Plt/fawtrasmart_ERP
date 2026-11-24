<?php

namespace App\Console\Commands;

use App\Enums\VisitStatus;
use App\Models\EmployeeClientVisit;
use App\Support\OperationLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * تصنيف زيارات خط السير التي انقضى يومها ولم تكتمل كـ«فائتة».
 *
 * زيارةٌ لم تكتمل حتى انقضاء `visit_date` لا تتحوّل تلقائياً — تبقى
 * «مخطَّطة» أو «جارية» للأبد ما لم يمرّ عليها أحد. هذا الأمر ينقلها إلى
 * `VisitStatus::MISSED` فتظهر بشكل صحيح في لوحة تحكّم خط السير («فائتة» لا
 * «متأخرة») ويستطيع المدير محاسبة المندوب عليها.
 *
 * يشمل حالتين، ويكتب لكلٍّ سببها في `missed_reason` كي تُميَّزا:
 * - `planned` — لم تبدأ أصلاً.
 * - `started` — بدأها المندوب ولم يُنهِها قبل انقضاء يومها.
 *
 * لا يمسّ `completed`/`cancelled`/`missed` — ولا صفّاً عموده القديم
 * `status: active` (مكتملٌ فعلاً وإن تخلّف `visit_status`)، ولا يكتب في
 * `status`، فلا يتأثر به `PointsService::evaluateItineraryCompletion`
 * ولا `EmployeeClientVisit::dayIsFull()`.
 *
 * يُشغَّل من «المهام المجدولة» يومياً بعد منتصف الليل — انظر
 * `CronRegistry::commands()` وصفَّ `itinerary_mark_missed_visits` في `CronJobsSeeder`.
 */
class MarkMissedItineraryVisits extends Command
{
    private const REASONS = [
        'planned' => 'تلقائي: انقضى يومها ولم تبدأ',
        'started' => 'تلقائي: بدأت ولم تُنهَ قبل انقضاء يومها',
    ];

    protected $signature = 'itinerary:mark-missed-visits
                            {--dry-run : يعرض ما سيُصنَّف فائتاً دون كتابة}';

    protected $description = 'يصنّف زيارات خط السير التي انقضى يومها ولم تكتمل كـ«فائتة»';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $query = EmployeeClientVisit::query()
            ->whereIn('visit_status', [VisitStatus::PLANNED, VisitStatus::STARTED])
            ->where(fn ($q) => $q->where('status', '!=', 'active')->orWhereNull('status'))
            ->whereDate('visit_date', '<', today()->toDateString());

        $total = (clone $query)->count();

        if ($total === 0) {
            $this->info('لا زيارات فائتة لتصنيفها.');

            return self::SUCCESS;
        }

        if ($dryRun) {
            $started = (clone $query)->where('visit_status', VisitStatus::STARTED)->count();

            $this->warn('عرضٌ فقط — لا يُكتب شيء ('.$total.' زيارة، منها '.$started.' بدأت ولم تُنهَ):');

            $query->orderBy('visit_date')->limit(50)->get()->each(function (EmployeeClientVisit $visit) {
                $this->line(sprintf(
                    '  #%d — الموظف #%d — العميل #%d — تاريخ %s — %s',
                    $visit->id,
                    $visit->employee_id,
                    $visit->client_id,
                    $visit->visit_date?->format('Y-m-d') ?? '—',
                    $visit->visit_status->label()
                ));
            });

            return self::SUCCESS;
        }

        $marked = 0;

        $query->orderBy('id')->chunkById(200, function ($visits) use (&$marked) {
            foreach ($visits as $visit) {
                $reason = self::REASONS[$visit->visit_status->value];

                $visit->markMissed($reason);
                $marked++;

                OperationLog::record(
                    'visit_missed',
                    'ClientItineraries',
                    $visit,
                    "تصنيف الزيارة #{$visit->id} فائتة تلقائياً ({$reason} — {$visit->visit_date?->format('Y-m-d')})"
                );
            }
        });

        Log::info('itinerary:mark-missed-visits — تم تصنيف '.$marked.' زيارة فائتة.');

        $this->info('تم تصنيف '.$marked.' زيارة فائتة.');

        return self::SUCCESS;
    }
}
