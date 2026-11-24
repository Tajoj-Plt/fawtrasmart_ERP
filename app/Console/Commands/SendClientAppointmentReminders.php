<?php

namespace App\Console\Commands;

use App\Helpers\ClientSettingsHelper;
use App\Models\Appointment;
use App\Models\ClientModuleSetting;
use App\Models\notifications;
use App\Models\User;
use App\Support\OperationLog;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Throwable;

/**
 * تذكير الموظفين بمواعيد العملاء القادمة، وإقفال الفائتة منها.
 *
 * **عملان في أمرٍ واحد** لأنّ كليهما يقرأ نفس الجدول بنفس الدوريّة، وفصلُهما
 * يعني مهمّتين مجدولتين تمرّان على `appointments` كل ساعة بدل واحدة:
 *
 * 1. *التذكير*: كل موعدٍ يحلّ خلال `reminder_hours_before` ولم يُذكَّر به
 *    بعدُ — إشعارٌ لكلّ موظفي الموعد. كان حقلُ «التذكير قبل الموعد» في الإعدادات
 *    بلا قارئ: يُضبَط ولا يُرسَل شيء.
 *
 * 2. *الإقفال التلقائي*: المواعيد التي مضى وقتُها وبقيت «قيد الانتظار»
 *    تُنقَل إلى «مكتمل» — إن كان `auto_complete_past` مفعَّلاً. مطفأً لا
 *    يمسّها الأمر، فالإقفال التلقائي قرارُ منشأةٍ لا سلوكُ نظام.
 *
 * **لا تكرار في التذكير**: لا عمودَ `reminder_sent_at` في الجدول —
 * الحارسان هما نافذةُ الالتقاط الضيّقة (ساعةٌ نصف مفتوحة، فالتشغيل
 * الساعيُّ يلتقط الموعدَ مرّةً واحدة) وسؤالُ جدول الإشعارات عن تذكيرٍ
 * سابقٍ لنفس المستقبِل واللحظة (انظر `sendReminders`).
 *
 * يُشغَّل من «المهام المجدولة» كل ساعة — انظر `CronRegistry::commands()`.
 *
 * @see \App\Helpers\ClientSettingsHelper::appointmentReminderHoursBefore()
 */
class SendClientAppointmentReminders extends Command
{
    protected $signature = 'clients:appointment-reminders
                            {--dry-run : يعرض ما سيُرسَل ويُقفَل دون كتابة}';

    protected $description = 'يذكّر الموظفين بمواعيد العملاء القادمة ويُقفل الفائتة منها وفق إعدادات المواعيد';

    /** مفتاح الوحدة في السجلّات */
    private const LOG_MODULE = 'ClientAppointmentReminders';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        /* قاطعُ القسم من «إعدادات الحجوزات ← المواعيد»: مطفأً تُمنع الشاشةُ
           والروابط، فلا معنى لتذكيرٍ بموعدٍ لا يستطيع أحدٌ فتحه ولا لإقفاله. */
        if (! (bool) (ClientModuleSetting::current()->appointments_enabled ?? true)) {
            $this->line('مواعيد العملاء معطَّلة من إعدادات الحجوزات — لا تذكير ولا إقفال.');

            return self::SUCCESS;
        }

        $reminded = $this->sendReminders($dryRun);
        $completed = $this->autoCompletePast($dryRun);

        $this->info(($dryRun ? '[تجريبي] ' : '')."ذُكِّر بـ{$reminded} موعداً، وأُقفل {$completed} موعداً فائتاً.");

        if (($reminded > 0 || $completed > 0) && ! $dryRun) {
            OperationLog::record(
                'reminders_sent',
                self::LOG_MODULE,
                null,
                "تذكير بـ{$reminded} موعد وإقفال {$completed} موعد فائت",
                ['new' => ['reminded' => $reminded, 'completed' => $completed]]
            );
        }

        return self::SUCCESS;
    }

    /**
     * إرسال تذكيرات المواعيد التي تحلّ داخل نافذة التذكير.
     */
    private function sendReminders(bool $dryRun): int
    {
        $hours = ClientSettingsHelper::appointmentReminderHoursBefore();

        if ($hours === null) {
            $this->line('التذكير مُعطَّل (المهلة فارغة في الإعدادات).');

            return 0;
        }

        // النافذة: من الآن+المهلة إلى الآن+المهلة+ساعة. الأمر يعمل كل ساعة،
        // فالنافذة بعرض ساعةٍ تلتقط كل موعدٍ مرّةً واحدة تماماً
        $from = now()->addHours($hours);
        $to = $from->copy()->addHour();

        $this->line("نافذة التذكير: {$hours} ساعة قبل الموعد — من {$from->format('Y-m-d H:i')} إلى {$to->format('Y-m-d H:i')}.");

        $sent = 0;

        $candidates = Appointment::with(['client', 'employees'])
            ->whereDate('appointment_date', '>=', $from->toDateString())
            ->whereDate('appointment_date', '<=', $to->toDateString())
            ->where('status', Appointment::STATUS_PENDING)
            ->get();

        foreach ($candidates as $appointment) {
            $at = $this->appointmentMoment($appointment);

            if (! $at || $at->lt($from) || $at->gte($to)) {
                continue;
            }

            $clientName = $appointment->client?->display_name ?? '#'.$appointment->client_id;
            $description = 'لديك موعد مع العميل '.$clientName
                .' في '.$at->format('Y-m-d H:i')
                .($appointment->action_type ? ' — '.$appointment->action_type : '');

            /* المستقبِلون: مستخدمو موظفي الموعد كلّهم — كان المُنشئُ وحده،
               فموعدٌ أنشأه المشرف لمندوبٍ يُذكَّر به المشرف. ومن لا موظّفَ له
               بحسابٍ يُذكَّر مُنشئُه. */
            $receivers = User::whereIn('employee_id', $appointment->assignedEmployeeIds())->pluck('id');

            if ($receivers->isEmpty() && $appointment->created_by) {
                $receivers = collect([$appointment->created_by]);
            }

            /* العميلُ لا يُذكَّر إلّا بموعدٍ شُورك معه (`share_with_client`) —
               قاعدةُ المشاركة نفسُها التي تحكم بريدَه وإشعارَ بوابته عند
               الإنشاء (`AppointmentClientShare`). حساباتُ بوابته: `users.client_id`. */
            $portalReceivers = $appointment->share_with_client
                ? User::where('client_id', $appointment->client_id)->pluck('id')
                : collect();

            /* لا تكرار: لا عمودَ `reminder_sent_at` في الجدول، فيُسأل جدولُ
               الإشعارات — تذكيرٌ لنفس المستقبِل عن نفس العميل في نفس اللحظة
               أُرسل من قبل يعني أنّ تشغيلاً سابقاً (يدوياً أو مجدولاً) سبقنا. */
            $alreadyReminded = notifications::query()
                ->where('type', 'client_appointment_reminder')
                ->where('client_id', $appointment->client_id)
                // التذكيرُ يُكتب قبل موعده بساعات معدودة — القصُّ بالتاريخ
                // يمنع مسحاً كاملاً لجدول الإشعارات بـ`LIKE` في كل تشغيلة
                ->where('created_at', '>=', now()->subDays(3))
                ->where('description', 'like', '%في '.$at->format('Y-m-d H:i').'%')
                ->pluck('receiver_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $receivers = $receivers->map(fn ($id) => (int) $id)->reject(fn ($id) => in_array($id, $alreadyReminded, true))->values();
            $portalReceivers = $portalReceivers->map(fn ($id) => (int) $id)->reject(fn ($id) => in_array($id, $alreadyReminded, true))->values();

            if ($receivers->isEmpty() && $portalReceivers->isEmpty()) {
                $this->line("  #{$appointment->id} {$clientName} — {$at->format('Y-m-d H:i')}: ذُكِّر به من قبل، يُتخطّى.");

                continue;
            }

            $this->line(($dryRun ? '[تجريبي] ' : '')."  #{$appointment->id} {$clientName} — {$at->format('Y-m-d H:i')}"
                .' → موظفون: '.($receivers->isEmpty() ? '—' : $receivers->implode('، '))
                .($appointment->share_with_client ? ' | بوابة العميل: '.($portalReceivers->isEmpty() ? 'لا حساب' : $portalReceivers->count()) : ''));

            if (! $dryRun) {
                foreach ($receivers as $receiverId) {
                    try {
                        /* النصُّ في `description`: كان «تذكير موعد» هناك و`message`
                           بعده، والمحوِّل لا يكتب `message` فوق وصفٍ قائم — فيصل
                           التذكيرُ بلا اسم العميل ولا الوقت. */
                        notifications::create([
                            'user_id' => $appointment->created_by ?? $receiverId,
                            'receiver_id' => $receiverId,
                            'client_id' => $appointment->client_id,
                            'title' => 'تذكير بموعد عميل',
                            'description' => $description,
                            'type' => 'client_appointment_reminder',
                            'read' => false,
                        ]);
                    } catch (Throwable $e) {
                        OperationLog::failure($e, self::LOG_MODULE, 'send_reminder', $appointment->id);
                    }
                }

                foreach ($portalReceivers as $receiverId) {
                    try {
                        notifications::create([
                            'user_id' => $appointment->created_by ?? $receiverId,
                            'receiver_id' => $receiverId,
                            'client_id' => $appointment->client_id,
                            'title' => 'تذكير بموعدك',
                            'description' => 'موعدك في '.$at->format('Y-m-d H:i')
                                .($appointment->action_type ? ' — '.$appointment->action_type : '')
                                .' — مع '.($appointment->employeeNames() ?? '—'),
                            'type' => 'client_appointment_reminder',
                            'read' => false,
                        ]);
                    } catch (Throwable $e) {
                        OperationLog::failure($e, self::LOG_MODULE, 'send_client_reminder', $appointment->id);
                    }
                }
            }

            $sent++;
        }

        return $sent;
    }

    /**
     * إقفال المواعيد الفائتة — إن كان الإعداد مفعَّلاً.
     */
    private function autoCompletePast(bool $dryRun): int
    {
        if (! ClientSettingsHelper::appointmentsAutoCompletePast()) {
            return 0;
        }

        /* موعدُ الحجز (`reservation_id`) يُقفله حجزُه عند اكتماله — لا هذا
           الأمر: إقفالُه هنا يجعل الموعد «مكتملاً» وحجزُه ما زال مؤكَّداً. */
        $query = Appointment::where('status', Appointment::STATUS_PENDING)
            ->whereNull('reservation_id')
            ->whereDate('appointment_date', '<', now()->toDateString());

        $count = (clone $query)->count();

        if ($dryRun) {
            $this->line("[تجريبي] إقفال تلقائي: {$count} موعداً فائتاً «قيد الانتظار» قبل ".now()->toDateString().'.');
        }

        if ($count === 0 || $dryRun) {
            return $count;
        }

        try {
            $query->update(['status' => Appointment::STATUS_COMPLETED]);
        } catch (Throwable $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'auto_complete');

            return 0;
        }

        return $count;
    }

    /**
     * لحظةُ الموعد من تاريخه ووقته — أو `null` إن لم يكن له وقت.
     *
     * `time` عمودٌ منفصل عن `appointment_date`، فدمجُهما لازمٌ قبل أي
     * مقارنةٍ زمنية: المقارنة بالتاريخ وحده تذكّر بموعد الثامنة صباحاً
     * وموعد الثامنة مساءً في اللحظة نفسها.
     */
    private function appointmentMoment(Appointment $appointment): ?Carbon
    {
        if (! $appointment->appointment_date || ! $appointment->time) {
            return null;
        }

        try {
            return Carbon::parse(
                Carbon::parse($appointment->appointment_date)->toDateString().' '.
                Carbon::parse($appointment->time)->format('H:i:s')
            );
        } catch (Throwable $e) {
            return null;
        }
    }
}
