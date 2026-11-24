<?php

namespace App\Support;

use App\Services\ActivityLogService;
use App\Services\ErrorLogService;
use App\Services\EventLogService;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * نقطة تسجيل واحدة لكل عملية — تُوزّع على السجلّات الثلاثة معاً.
 *
 * كانت كل شاشة تكتب سطر `Log::create([...])` بيدها: لا عنوان IP، ولا متصفّح،
 * ولا مسار، ولا فرق بين القيم القديمة والجديدة، ولا سطر في «سجلّ الأحداث»،
 * ولا شيء في «سجلّ الأخطاء» حين تفشل العملية. النتيجة أن ثلاث شاشات في
 * الإعدادات تعرض ما لم يُكتب فيها قطّ.
 *
 * الاستعمال:
 *
 *     OperationLog::record('created', 'OrdersPurchases', $order,
 *         __('purchase_orders.log.created', [...]));
 *
 *     OperationLog::failure($e, 'OrdersPurchases', 'store', $order?->id);
 *
 * ماذا يُكتب في كل نداء؟
 *   • `logs`       ← سجلّ النشاط: الوصف والأيقونة والمتصفّح والجهاز وفرق القيم
 *   • `event_logs` ← سجلّ الأحداث: مفتاح العملية وعنوان IP وبصمة المتصفّح
 *   • `error_logs` ← سجلّ الأخطاء: عند `failure()` وحدها
 *
 * لا يرمي هذا الصنف شيئاً أبداً: فشلُ التسجيل لا يجوز أن يُسقط العمليةَ
 * التي نجحت — يُبتلع ويُكتب في سجلّ لارافيل.
 */
class OperationLog
{
    /**
     * تسجيل عملية ناجحة.
     *
     * @param  string  $action  مفتاح العملية: created|updated|deleted|approved…
     * @param  string  $module  قيمة العمود `type` — بها يربط المستندُ خطَّه الزمني
     * @param  Model|int|null  $subject  السجلّ المعني (أو معرّفه)
     * @param  array{old?: array, new?: array}  $changes  فرق القيم عند التعديل
     */
    public static function record(
        string $action,
        string $module,
        Model|int|null $subject,
        string $description,
        array $changes = []
    ): void {
        $subjectId = $subject instanceof Model ? (int) $subject->getKey() : $subject;

        try {
            app(ActivityLogService::class)->log(
                action: $action,
                description: $description,
                oldValues: $changes['old'] ?? null,
                newValues: $changes['new'] ?? null,
                type: $module,
                typeId: $subjectId,
            );
        } catch (Throwable $e) {
            self::swallow('activity', $e);
        }

        try {
            app(EventLogService::class)->log(
                action: self::eventKey($module, $action),
                description: strip_tags($description),
            );
        } catch (Throwable $e) {
            self::swallow('event', $e);
        }
    }

    /**
     * تسجيل عملية فاشلة — يُكتب في سجلّ الأخطاء وسجلّ الأحداث معاً.
     *
     * سجلّ النشاط لا يُكتب هنا عمداً: النشاط سردٌ لما جرى فعلاً على المستند،
     * والعملية الفاشلة لم تُغيّر شيئاً.
     */
    public static function failure(
        Throwable $exception,
        string $module,
        string $action,
        Model|int|null $subject = null
    ): void {
        try {
            app(ErrorLogService::class)->log($exception);
        } catch (Throwable $e) {
            self::swallow('error', $e);
        }

        try {
            $subjectId = $subject instanceof Model ? $subject->getKey() : $subject;

            app(EventLogService::class)->log(
                action: self::eventKey($module, $action.'_failed'),
                description: trim($exception->getMessage().($subjectId ? ' [#'.$subjectId.']' : '')),
            );
        } catch (Throwable $e) {
            self::swallow('event', $e);
        }
    }

    /**
     * فرق القيم بين ما كان وما صار — للتمرير إلى `record()`.
     *
     * تُقارَن الحقول المطلوبة وحدها، فلا تمتلئ الشاشة بطوابع الوقت وبكل
     * عمود لم يمسّه المستخدم.
     *
     * @param  array<int, string>  $fields
     * @return array{old?: array, new?: array}
     */
    public static function diff(Model $model, array $fields): array
    {
        $old = [];
        $new = [];

        foreach ($fields as $field) {
            $before = $model->getOriginal($field);
            $after = $model->getAttribute($field);

            // مقارنة نصّية: `'5'` و`5` القادمان من النموذج والقاعدة ليسا اختلافاً
            if ((string) $before !== (string) $after) {
                $old[$field] = $before;
                $new[$field] = $after;
            }
        }

        return $new === [] ? [] : ['old' => $old, 'new' => $new];
    }

    /** `OrdersPurchases` + `created` ← `orders_purchases.created` */
    protected static function eventKey(string $module, string $action): string
    {
        return \Illuminate\Support\Str::snake($module).'.'.$action;
    }

    protected static function swallow(string $channel, Throwable $e): void
    {
        \Illuminate\Support\Facades\Log::warning(
            "OperationLog: failed writing the {$channel} log — {$e->getMessage()}"
        );
    }
}
