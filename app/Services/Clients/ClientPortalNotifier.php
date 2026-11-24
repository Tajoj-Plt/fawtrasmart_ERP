<?php

namespace App\Services\Clients;

use App\Models\Client;
use App\Models\notifications;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * إشعارُ الموظّفين بما يفعله العميل في بوابته — إلى **موظّفه المسند** أوّلاً.
 *
 * ══════════ لماذا موظّفُه لا الجميع؟ ══════════
 *
 * (طلب المستخدم 2026-09-19: «الإشعار يوصل إلى الموظف المسند إلى العميل وليس
 * يظهر لكل الموظفين».) العميلُ له مندوبٌ يعرفه ويعرف حسابه؛ وإشعارٌ يصل
 * الجميع لا يصل أحداً — كلٌّ يظنّ أن غيره سيتولّاه.
 *
 * فيُكتب صفٌّ واحد بـ`receiver_id` = حسابُ الموظّف المسند. وإن لم يكن له
 * حسابُ دخول (أكثرُ المندوبين كذلك) أو لم يُسند العميلُ إلى أحد، يبقى
 * `receiver_id` فارغاً — فيراه أصحابُ صلاحيات قائمة الإشعارات كما كان.
 * لا يضيع الإشعار في الحالتين.
 *
 * ══════════ أيُّ «موظّف»؟ ══════════
 *
 * `clients.employee_id` يشير إلى صفٍّ في **`employees`**، وحسابُ الدخول
 * يُوصَل به عبر `users.employee_id`. وهذا فضاءُ معرّفاتٍ غيرُ فضاء
 * `employee_client_visits.employee_id` الذي يحمل معرّفَ **مستخدم** —
 * الخلطُ بينهما يُرسل الإشعار إلى شخصٍ آخر تماماً.
 *
 * @see \App\Models\Client::employee()
 */
class ClientPortalNotifier
{
    /**
     * حسابُ الدخول الذي يُرسَل إليه إشعارُ هذا العميل — أو `null` لتبقى
     * الرؤيةُ بالصلاحيات.
     */
    public static function staffRecipientId(int|Client|null $client): ?int
    {
        $client = $client instanceof Client ? $client : Client::find($client);

        if (! $client) {
            return null;
        }

        $employeeIds = [];

        if ($client->employee_id) {
            $employeeIds[] = (int) $client->employee_id;
        }

        /* وجدولُ الإسناد المتعدّد إن وُجد: عميلٌ قد يُسنَد إلى أكثر من
           موظّف، وأوّلُهم صاحبُ الملفّ. الفحصُ على وجود الجدول لأن
           قواعدَ قائمةً لا تحمله. */
        if ($employeeIds === [] && Schema::hasTable('client_employee')) {
            $pivot = DB::table('client_employee')
                ->where('client_id', $client->id)
                ->orderBy('id')
                ->pluck('employee_id')
                ->all();

            $employeeIds = array_map('intval', $pivot);
        }

        if ($employeeIds === []) {
            return null;
        }

        return User::whereIn('employee_id', $employeeIds)->value('id');
    }

    /**
     * صفُّ إشعارٍ واحد عن فعلٍ في البوابة.
     *
     * `user_id` هو حسابُ العميل المُرسِل (فيظهر الفعلُ منسوباً إليه)، و
     * `receiver_id` موظّفُه المسند إن كان له حساب.
     */
    public static function notify(
        int|Client|null $client,
        string $type,
        string $title,
        string $body,
        ?int $senderId = null,
    ): void {
        try {
            $clientId = $client instanceof Client ? $client->id : $client;

            notifications::create([
                'user_id' => $senderId ?? auth()->id(),
                'receiver_id' => static::staffRecipientId($client),
                'client_id' => $clientId,
                'title' => $title,
                'description' => $body,
                'type' => $type,
                'read' => 0,
            ]);
        } catch (Throwable $e) {
            /* إشعارٌ فاشلٌ لا يُسقط عمليةً نجحت — الحجزُ سُجّل فعلاً،
               وتعطّلُ الإشعار لا يُلغيه. */
            Log::warning('تعذّر إشعار الموظّف بفعلٍ في بوابة العميل', [
                'type' => $type,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
