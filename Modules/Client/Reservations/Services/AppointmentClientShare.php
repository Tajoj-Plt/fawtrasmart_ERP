<?php

namespace Modules\Client\Reservations\Services;

use App\Models\Appointment;
use App\Models\notifications;
use App\Models\User;
use App\Services\Sales\SalesDocumentMailer;
use App\Support\LanguageSettings;
use App\Support\OperationLog;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * إبلاغُ أطراف الموعد به.
 *
 * **العميل** — حين يُعلَّم «مشاركة مع العميل» (`share_with_client`)، وكان
 * المربّعُ قبل ذلك يُرسَل ولا يُحفظ ولا يُرسَل منه شيء:
 *   ١. بريدٌ إلى `clients.email` بقالب `reservations.appointment` عبر
 *      `SalesDocumentMailer` — فيُكتب في `email_logs` نجح أم فشل، والنصُّ
 *      يحرّره صاحبُ المنشأة من «قوالب البريد». بلا مرفق: لا مستندَ للموعد.
 *   ٢. إشعارٌ `client_appointment_shared` لكلّ حساب بوابةٍ مربوطٍ بالعميل
 *      (`users.client_id`) — تعرضه البوابة شريطاً حتى يفتح «مواعيدي».
 *   ٣. البوابة نفسُها لا تعرض إلّا الموعدَ المشارَك — ذاك في شاشتها.
 *
 * **الموظفون** — إشعارٌ `client_appointment_assigned` لكلّ موظفٍ أُسند إليه
 * الموعد ولم يكن المُنشئَ نفسَه: الموعدُ الذي لا يعلم به صاحبه لا يُحضَر.
 *
 * لا شيء هنا يُسقط الحفظ: الموعدُ محفوظٌ قبل النداء، والفشلُ يُكتب في
 * سجلّ الأخطاء ويُعاد في النتيجة ليقرأه المستخدم.
 */
class AppointmentClientShare
{
    public const LOG_MODULE = 'ClientAppointments';

    /**
     * @return array{email: bool|null, email_error: string|null, portal_users: int}
     */
    public function shareWithClient(Appointment $appointment, string $event = 'created'): array
    {
        $result = ['email' => null, 'email_error' => null, 'portal_users' => 0];

        if (! $appointment->share_with_client) {
            return $result;
        }

        $appointment->loadMissing(['client', 'employees']);
        $client = $appointment->client;

        if (! $client) {
            return $result;
        }

        $when = $this->moment($appointment);
        $employees = $appointment->employeeNames() ?? '—';
        $title = $event === 'updated' ? 'تعديل موعدك' : 'موعد جديد';

        // ١. البريد
        if (filled($client->email)) {
            try {
                $sent = app(SalesDocumentMailer::class)->send(
                    'appointment',
                    $appointment->id,
                    $client->email,
                    [
                        'client' => (string) $client->display_name,
                        'number' => (string) $appointment->id,
                        'date' => $when ? LanguageSettings::formatDate($when) : '',
                        'time' => $when ? $when->format('H:i') : '',
                        'duration' => $appointment->duration ? $appointment->duration.' دقيقة' : '',
                        'action' => (string) $appointment->action_type,
                        'employee' => $employees,
                        'title' => $title,
                        'note' => trim(strip_tags((string) $appointment->notes)),
                    ],
                    null,
                    null,
                    (int) $client->id
                );

                $result['email'] = (bool) $sent['ok'];
                $result['email_error'] = $sent['error'];
            } catch (Throwable $e) {
                OperationLog::failure($e, self::LOG_MODULE, 'share_email', $appointment->id);
                $result['email'] = false;
                $result['email_error'] = $e->getMessage();
            }
        }

        // ٢. إشعارُ حسابات البوابة
        $message = $title.': '.($when ? $when->format('Y-m-d H:i') : '')
            .($appointment->action_type ? ' — '.$appointment->action_type : '')
            .' — مع '.$employees;

        foreach (User::where('client_id', $client->id)->pluck('id') as $userId) {
            try {
                notifications::create([
                    'user_id' => auth()->id() ?? $appointment->created_by,
                    'receiver_id' => $userId,
                    'client_id' => $client->id,
                    'title' => $title,
                    'description' => $message,
                    'type' => 'client_appointment_shared',
                    'read' => false,
                ]);
                $result['portal_users']++;
            } catch (Throwable $e) {
                OperationLog::failure($e, self::LOG_MODULE, 'share_notification', $appointment->id);
            }
        }

        return $result;
    }

    /**
     * إشعارُ الموظفين المُسنَدين.
     *
     * @param  array<int, int>|null  $onlyEmployeeIds  المضافون حديثاً وحدَهم عند التعديل
     */
    public function notifyEmployees(Appointment $appointment, ?array $onlyEmployeeIds = null, string $event = 'created'): int
    {
        $employeeIds = $onlyEmployeeIds ?? $appointment->assignedEmployeeIds();

        if ($employeeIds === []) {
            return 0;
        }

        $appointment->loadMissing('client');
        $when = $this->moment($appointment);
        $clientName = $appointment->client?->display_name ?? '#'.$appointment->client_id;
        $sent = 0;

        $receivers = User::whereIn('employee_id', $employeeIds)
            ->when(auth()->id(), fn ($q, $me) => $q->where('id', '!=', $me))
            ->pluck('id');

        foreach ($receivers as $userId) {
            try {
                notifications::create([
                    'user_id' => auth()->id() ?? $appointment->created_by,
                    'receiver_id' => $userId,
                    'client_id' => $appointment->client_id,
                    'title' => $event === 'recurring' ? 'موعد متكرر جديد' : 'موعد أُسند إليك',
                    'description' => 'موعد مع العميل '.$clientName
                        .($when ? ' في '.$when->format('Y-m-d H:i') : '')
                        .($appointment->action_type ? ' — '.$appointment->action_type : ''),
                    'type' => 'client_appointment_assigned',
                    'read' => false,
                ]);
                $sent++;
            } catch (Throwable $e) {
                OperationLog::failure($e, self::LOG_MODULE, 'assign_notification', $appointment->id);
            }
        }

        return $sent;
    }

    /** نصٌّ قصير يُلحق برسالة الحفظ — ما وصل العميلَ وما لم يصل */
    public static function summary(array $result, bool $hasEmail): string
    {
        $parts = [];

        if ($result['email'] === true) {
            $parts[] = 'أُرسل بريد للعميل';
        } elseif ($result['email'] === false) {
            $parts[] = 'تعذّر إرسال البريد للعميل: '.$result['email_error'];
        } elseif (! $hasEmail) {
            $parts[] = 'لا بريد مسجَّل للعميل';
        }

        if ($result['portal_users'] > 0) {
            $parts[] = 'وأُشعر العميل في بوابته';
        }

        return implode('، ', $parts);
    }

    private function moment(Appointment $appointment): ?Carbon
    {
        if (! $appointment->appointment_date) {
            return null;
        }

        try {
            return Carbon::parse(
                Carbon::parse($appointment->appointment_date)->toDateString().' '
                .($appointment->time ? Carbon::parse($appointment->time)->format('H:i:s') : '00:00:00')
            );
        } catch (Throwable $e) {
            return null;
        }
    }
}
