<?php

namespace Modules\Client\Reservations\Console;

use App\Models\notifications;
use App\Support\OperationLog;
use Illuminate\Console\Command;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Modules\Client\Reservations\Models\Reservation;
use Modules\Client\Reservations\Models\ReservationSetting;
use Throwable;

/**
 * تذكيرُ العملاء بحجوزاتهم القادمة.
 *
 * ═══════════ الأمرُ كان موعوداً به قبل هذه الوحدة ═══════════
 *
 * `reservations:reminders` مسجَّلٌ في `CronRegistry` وله صفٌّ في
 * `CronJobsSeeder` منذ التهيئة — ولم يكن له صنفٌ ينفّذه. فتشغيلُه من شاشة
 * «المهام المجدولة» كان يفشل صامتاً. هذا هو الصنف.
 *
 * ═══════════ ما يقرؤه من الإعدادات ═══════════
 *
 *   • `notify_reminder`    ← هل التذكير مفعَّل أصلاً؟
 *   • `reminder_offsets`   ← المهل بالدقائق («1440,120,30»)
 *   • موعدُ التشغيل        ← «المهام المجدولة» (جدول `cron_jobs`)
 *   • قنواتُ الإرسال       ← «إعدادات الإشعارات» (النوع `reservation_reminder`)
 *
 * ═══════════ ولماذا هو غيرُ قابلٍ للتكرار (idempotent)؟ ═══════════
 *
 * كلُّ تذكيرٍ يُسجَّل صفّاً في `reservation_reminders` بمفتاحٍ فريد
 * `(reservation_id, offset_minutes)`. والصفُّ **يُكتب قبل الإرسال** لا
 * بعده: فلو تسابق تشغيلان، الثاني يصطدم بالفهرس فيتخطّى بلا إرسال.
 *
 * والكتابةُ قبل الإرسال تعني أن فشلَ الإرسال لا يُعيد المحاولة تلقائياً —
 * وهو المقصود: رسالةٌ متأخّرةٌ ساعتين عن موعدها أسوأ من ألّا تصل، ويبقى
 * الفشلُ مكتوباً في العمود `error` ليُراجَع.
 *
 * ═══════════ نافذةُ الالتقاط ═══════════
 *
 * المهلةُ لحظةٌ بعينها والأمرُ يعمل على فترات، فالالتقاطُ بنافذةٍ
 * (`--window`) لا بمطابقةٍ دقيقة: كلُّ حجزٍ تقع لحظةُ تذكيره بين
 * التشغيلة السابقة وهذه يُذكَّر.
 *
 * **النافذةُ يجب أن تساوي فترةَ التشغيل**: المهمّةُ المبذورة ساعيّةٌ
 * بنافذة 60. تشغيلةٌ يوميّةٌ بمهلة «قبل ساعتين» تلتقط حجوزاتِ الساعتين
 * التاليتين لوقتها وحدَها وتُفوّت البقيّة؛ ونافذةٌ أعرضُ من الفترة تُرسل
 * التذكيرَ أبكرَ من مهلته.
 *
 * ═══════════ من يُذكَّر؟ ═══════════
 *
 * الحجوزاتُ **المؤكَّدة** وحدَها: المسودّةُ والمعلّقة لم تُقبل بعدُ
 * فالتذكيرُ بها وعدٌ لم يُقطع، والمكتملةُ والجارية فات وقتُ تذكيرها.
 */
class SendReservationReminders extends Command
{
    protected $signature = 'reservations:reminders
                            {--window=720 : عرض نافذة الالتقاط بالدقائق حول كل مهلة}
                            {--dry-run : عرض ما سيُرسَل بلا إنشاء إشعارات}';

    protected $description = 'يذكّر العملاء بحجوزاتهم قبل موعدها حسب المهل المضبوطة في إعدادات الحجوزات';

    public function handle(): int
    {
        $settings = ReservationSetting::current();

        if (! $settings->notify_reminder) {
            $this->warn('تذكير الحجوزات مُطفأ في إعدادات الوحدة — لا شيء ليُرسَل.');

            return self::SUCCESS;
        }

        $offsets = $settings->reminderOffsets();

        if ($offsets === []) {
            $this->warn('لا مهل تذكير مضبوطة (reminder_offsets فارغة).');

            return self::SUCCESS;
        }

        $window = max(5, (int) $this->option('window'));
        $dryRun = (bool) $this->option('dry-run');

        $this->info('المهل: '.implode('، ', $offsets).' دقيقة — نافذة الالتقاط '.$window.' دقيقة');

        $sent = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($offsets as $offset) {
            /*
                الحدّان: الحجوزاتُ التي تبعد عن الآن ما بين (المهلة − النافذة)
                و(المهلة). أي التي «حان وقتُ تذكيرها ولم يفت».
            */
            $from = now()->addMinutes(max(0, $offset - $window));
            $to = now()->addMinutes($offset);

            $due = Reservation::query()
                ->where('status', \Modules\Client\Reservations\Enums\ReservationStatus::CONFIRMED->value)
                ->whereNotNull('client_id')
                ->whereDate('reservation_date', '>=', $from->toDateString())
                ->whereDate('reservation_date', '<=', $to->toDateString())
                ->with(['client:id,trade_name,first_name,last_name,phone,mobile', 'service:id,name,product_id', 'service.product:id,name'])
                ->get()
                // الفلترةُ الدقيقة باللحظة: الاستعلامُ يقصّ باليوم، وحساب
                // اللحظة (تاريخ + وقت) يختلف بين MySQL و SQLite
                ->filter(function (Reservation $reservation) use ($from, $to) {
                    $startsAt = $reservation->starts_at;

                    return $startsAt && $startsAt->betweenIncluded($from, $to);
                });

            foreach ($due as $reservation) {
                if ($dryRun) {
                    $this->line(sprintf('• [%d د] %s — %s', $offset, $reservation->code, $reservation->client_name));
                    $sent++;

                    continue;
                }

                /*
                    الحجزُ يُختَم أوّلاً ثمّ يُرسَل — انظر ترويسة الصنف.
                    والاصطدامُ بالفهرس الفريد يعني «أُرسل من قبل».
                */
                try {
                    $marker = DB::table('reservation_reminders')->insertGetId([
                        'reservation_id' => $reservation->id,
                        'offset_minutes' => $offset,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } catch (UniqueConstraintViolationException $e) {
                    $skipped++;

                    continue;
                } catch (Throwable $e) {
                    // بعضُ التعريفات القديمة ترمي QueryException عامّاً
                    if (str_contains($e->getMessage(), 'Duplicate') || str_contains($e->getMessage(), 'UNIQUE')) {
                        $skipped++;

                        continue;
                    }

                    throw $e;
                }

                try {
                    $this->notify($reservation, $offset);

                    DB::table('reservation_reminders')->where('id', $marker)->update([
                        'sent_at' => now(),
                        'channel' => 'database',
                        'updated_at' => now(),
                    ]);

                    $sent++;
                } catch (Throwable $e) {
                    // الفشلُ يُكتب ولا يُخفى، ولا يُسقط بقيّةَ التذكيرات
                    DB::table('reservation_reminders')->where('id', $marker)->update([
                        'error' => substr($e->getMessage(), 0, 1000),
                        'updated_at' => now(),
                    ]);

                    OperationLog::failure($e, 'Reservations', 'reminder', $reservation);
                    $failed++;
                }
            }
        }

        $this->info($dryRun
            ? sprintf('عرض فقط — %d تذكيراً سيُرسَل.', $sent)
            : sprintf('أُرسل %d تذكيراً، وتُخطّي %d مُرسَلاً سابقاً، وفشل %d.', $sent, $skipped, $failed));

        return self::SUCCESS;
    }

    /**
     * كتابةُ الإشعار — ومنه تنطلق القنواتُ الإضافية.
     *
     * لا يُرسَل بريدٌ ولا رسالةٌ نصّية من هنا مباشرةً: `notifications::created`
     * يوقظ `NotificationDispatcher` الذي يقرأ قنوات النوع من «إعدادات
     * الإشعارات». فالقناةُ قرارُ المسؤول لا قرارُ هذا الأمر.
     */
    protected function notify(Reservation $reservation, int $offset): void
    {
        $when = $this->humanize($offset);

        /*
            صناديقُ الإشعارات كلُّها تُصفّي على `receiver_id` — الصفُّ
            بـ`null` يبلغ العميلَ بالبريد والرسائل عبر `NotificationDispatcher`
            لكنه لا يظهر في بوّابته. فيُعنوَن بمستخدم بوّابة العميل إن وُجد
            (صفٌّ واحد لا صفٌّ لكلّ مستخدم — تكرارُ الصفوف يكرّر البريد).
        */
        $portalUserId = \App\Models\User::query()
            ->where('client_id', $reservation->client_id)
            ->value('id');

        notifications::create([
            'user_id' => null,
            'receiver_id' => $portalUserId,
            'client_id' => $reservation->client_id,
            'title' => 'تذكير بموعد حجزك',
            'description' => sprintf(
                'تذكير: لديك حجز %s%s بتاريخ %s الساعة %s (بعد %s).',
                $reservation->code,
                $reservation->service ? ' — '.$reservation->service->displayName() : '',
                $reservation->reservation_date->format('Y-m-d'),
                substr((string) $reservation->start_time, 0, 5),
                $when
            ),
            'type' => 'reservation_reminder',
            'read' => 0,
        ]);

        $this->notifyAssignedEmployee($reservation, $when);
    }

    /**
     * تذكيرُ الموظّف المعيَّن — خلف مفتاح «تذكير الموظّف المعيَّن بحجزه»
     * في إعدادات الحجوزات (طلب المستخدم 2026-09-20).
     *
     * صفٌّ منفصل بلا `client_id` عمداً: قنواتُ نوع `reservation_reminder`
     * في «إعدادات الإشعارات» تستهدف العميل، وصفٌّ ثانٍ يحمل `client_id`
     * كان سيُرسل بريدَ العميل مرّتين. الموظّفُ يصله في صندوقه داخل النظام.
     */
    protected function notifyAssignedEmployee(Reservation $reservation, string $when): void
    {
        if (! $reservation->employee_id || ! (ReservationSetting::current()->notify_reminder_employee ?? true)) {
            return;
        }

        $employeeUserId = \App\Models\User::query()
            ->where('employee_id', $reservation->employee_id)
            ->value('id');

        if (! $employeeUserId) {
            return;
        }

        notifications::create([
            'user_id' => null,
            'receiver_id' => $employeeUserId,
            'client_id' => null,
            'title' => 'تذكير بحجزٍ عليك',
            'description' => sprintf(
                'لديك حجز %s للعميل %s%s بتاريخ %s الساعة %s (بعد %s).',
                $reservation->code,
                $reservation->client_name ?? '#'.$reservation->client_id,
                $reservation->service ? ' — '.$reservation->service->displayName() : '',
                $reservation->reservation_date->format('Y-m-d'),
                substr((string) $reservation->start_time, 0, 5),
                $when
            ),
            'type' => 'reservation_reminder_employee',
            'read' => 0,
        ]);
    }

    /** «1440 دقيقة» ← «يوم» — الرسالةُ تُقرأ لا تُحسَب. */
    protected function humanize(int $minutes): string
    {
        if ($minutes % 1440 === 0) {
            $days = intdiv($minutes, 1440);

            return $days === 1 ? 'يوم' : $days.' أيام';
        }

        if ($minutes % 60 === 0) {
            $hours = intdiv($minutes, 60);

            return $hours === 1 ? 'ساعة' : $hours.' ساعات';
        }

        return $minutes.' دقيقة';
    }
}
