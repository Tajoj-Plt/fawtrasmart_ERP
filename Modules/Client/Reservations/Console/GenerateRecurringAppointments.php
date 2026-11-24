<?php

namespace Modules\Client\Reservations\Console;

use App\Models\Appointment;
use App\Models\ClientModuleSetting;
use App\Support\OperationLog;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Client\Reservations\Services\AppointmentClientShare;
use Throwable;

/**
 * توليدُ المواعيد المتكرّرة.
 *
 * كان «موعد متكرر» يُحفظ `is_recurring=1` مع نوعه وتاريخ نهايته ولا يولّد
 * شيئاً — الموعدُ الأوّل وحده في التقويم.
 *
 * **السلسلة** = الموعدُ الأصلي (`is_recurring=1`، بلا `recurrence_parent_id`)
 * وما وُلّد منه (`recurrence_parent_id` = الأصل). لكلّ سلسلةٍ لم تنتهِ، يُنشأ
 * كلُّ موعدٍ تالٍ يقع خلال `--days` يوماً من اليوم ولا يتجاوز تاريخ النهاية:
 * نفسُ العميل والوقت والمدّة والإجراء والموظفين والمشاركة، بحالة «قيد الانتظار».
 * فيظهر الموعدُ القادم قبل حلوله بأسبوع — يكفي للتذكير وللتحضير — ولا تُملأ
 * التقاويمُ بمواعيد سنةٍ دفعةً واحدة.
 *
 * **لا تكرار**: التاريخُ التالي يُحسب من آخر موعدٍ في السلسلة، والفريدُ على
 * (الأصل، التاريخ) يردّ تشغيلين متزامنين. وسلسلةٌ أُلغي أصلُها تتوقّف.
 *
 * كلُّ موعدٍ مولَّد يُشعَر به موظفوه، ويُبلَّغ به العميلُ إن كان مُشارَكاً.
 *
 * يُشغَّل يومياً من «المهام المجدولة» — `CronRegistry::commands()`.
 */
class GenerateRecurringAppointments extends Command
{
    protected $signature = 'appointments:generate-recurring
                            {--days=7 : يُولَّد ما يقع خلال هذه الأيام من اليوم}
                            {--dry-run : يعرض ما سيُولَّد دون كتابة}';

    protected $description = 'ينشئ المواعيد التالية للمواعيد المتكررة قبل حلولها، حتى تاريخ نهاية كل سلسلة';

    private const LOG_MODULE = 'ClientAppointments';

    /** حدٌّ لكل سلسلةٍ في التشغيل الواحد — حارسٌ من حلقةٍ لا تنتهي على بياناتٍ تالفة */
    private const MAX_PER_SERIES = 60;

    public function handle(AppointmentClientShare $share): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $days = max(0, (int) $this->option('days'));
        $today = Carbon::today();
        $horizon = $today->copy()->addDays($days);

        // قاطعُ القسم من «إعدادات الحجوزات ← المواعيد» — كما في أمر التذكير
        if (! (bool) (ClientModuleSetting::current()->appointments_enabled ?? true)) {
            $this->line('مواعيد العملاء معطَّلة من إعدادات الحجوزات — لا توليد.');

            return self::SUCCESS;
        }

        $series = Appointment::query()
            ->with('employees')
            ->where('is_recurring', true)
            ->whereNull('recurrence_parent_id')
            ->where('status', '!=', Appointment::STATUS_IGNORED)
            ->whereNotNull('recurrence_date')
            ->whereDate('recurrence_date', '>=', $today->toDateString())
            ->get();

        $created = 0;

        foreach ($series as $root) {
            $end = Carbon::parse($root->recurrence_date)->startOfDay();
            $limit = $end->lt($horizon) ? $end : $horizon;

            $last = Appointment::where('recurrence_parent_id', $root->id)->max('appointment_date');
            $cursor = Carbon::parse($last ?: $root->appointment_date)->startOfDay();
            $employeeIds = $root->assignedEmployeeIds();

            for ($i = 0; $i < self::MAX_PER_SERIES; $i++) {
                $next = Appointment::nextRecurrenceDate($cursor, (int) $root->recurrence_type);

                if (! $next || $next->gt($limit)) {
                    break;
                }

                $cursor = $next;

                // ما مضى من السلسلة (سلسلةٌ أُنشئت بتاريخٍ قديم) لا يُولَّد متأخّراً
                if ($next->lt($today)) {
                    continue;
                }

                $this->line(($dryRun ? '[تجريبي] ' : '')."#{$root->id} ← {$next->toDateString()} {$root->time}");

                if ($dryRun) {
                    $created++;

                    continue;
                }

                try {
                    $occurrence = DB::transaction(function () use ($root, $next, $employeeIds) {
                        $occurrence = new Appointment;
                        $occurrence->client_id = $root->client_id;
                        $occurrence->employee_id = $employeeIds[0] ?? $root->employee_id;
                        $occurrence->appointment_date = $next->toDateString();
                        $occurrence->time = $root->time;
                        $occurrence->duration = $root->duration;
                        $occurrence->notes = $root->notes;
                        $occurrence->action_type = $root->action_type;
                        $occurrence->share_with_client = (bool) $root->share_with_client;
                        $occurrence->is_recurring = false;
                        $occurrence->recurrence_type = $root->recurrence_type;
                        $occurrence->recurrence_parent_id = $root->id;
                        $occurrence->status = Appointment::STATUS_PENDING;
                        $occurrence->created_by = $root->created_by;
                        $occurrence->save();

                        if ($employeeIds !== []) {
                            $occurrence->employees()->sync($employeeIds);
                        }

                        return $occurrence;
                    });
                } catch (QueryException $e) {
                    // الفريدُ ردّ تشغيلاً متزامناً ولّد اليومَ نفسَه — ليس خطأ
                    if (str_contains($e->getMessage(), 'appointments_recurrence_occurrence_unique')) {
                        continue;
                    }

                    OperationLog::failure($e, self::LOG_MODULE, 'generate_recurring', $root->id);

                    break;
                } catch (Throwable $e) {
                    OperationLog::failure($e, self::LOG_MODULE, 'generate_recurring', $root->id);

                    break;
                }

                $created++;

                OperationLog::record(
                    'recurring_generated',
                    self::LOG_MODULE,
                    $occurrence,
                    'توليد موعد متكرر للعميل #'.$root->client_id.' بتاريخ '.$next->toDateString().' من الموعد #'.$root->id,
                    ['new' => ['recurrence_parent_id' => $root->id, 'appointment_date' => $next->toDateString()]]
                );

                $share->notifyEmployees($occurrence, null, 'recurring');
                $share->shareWithClient($occurrence);
            }
        }

        $this->info(($dryRun ? '[تجريبي] ' : '')."سلاسل متكررة: {$series->count()} — مواعيد مولَّدة: {$created}.");

        return self::SUCCESS;
    }
}
