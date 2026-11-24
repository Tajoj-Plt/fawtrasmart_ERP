<?php

namespace Modules\RentalManagement\Support;

use App\Models\notifications;
use App\Models\User;
use App\Support\DataScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * أنواع إشعارات العقارات ومن يستلمها.
 *
 * كانت التنبيهات تُكتب بأنواع النظام العامّة (`error`، `warning`، `info`)،
 * فلا يجدها المسؤول في «إعدادات الإشعارات» ولا يملك إيقافها أو توجيهها إلى
 * قناةٍ دون إيقاف كلّ «خطأ» في النظام. صار لكلّ حدثٍ نوعٌ مسمّى هنا، وصفُّه
 * في `notification_settings` تحت فئة «إدارة العقارات».
 *
 * والمستلمون يُحسبون هنا كذلك ({@see recipients()}): حاملُ الصلاحية لا يكفي —
 * يجب أن يرى السجلَّ نفسَه في قوائمه، وإلّا وصله إشعارٌ عن عقدٍ في فرعٍ لا
 * يفتحه.
 */
final class RealEstateNotifications
{
    public const CATEGORY = 'realestate';

    public const CONTRACT_EXPIRING = 'realestate_contract_expiring';

    public const PAYMENT_DUE = 'realestate_payment_due';

    public const PAYMENT_OVERDUE = 'realestate_payment_overdue';

    public const MAINTENANCE_ASSIGNED = 'realestate_maintenance_assigned';

    public const MAINTENANCE_CLOSED = 'realestate_maintenance_closed';

    public const TASK_ASSIGNED = 'realestate_task_assigned';

    /** مهمةٌ مفتوحة فات موعدُها — من المهمّة المجدولة، مرّةً لكلّ مهمة. */
    public const TASK_DUE = 'realestate_task_due';

    /**
     * الكتالوج: المفتاح ← التسمية والوصف — مصدرُ صفوف `notification_settings`.
     *
     * @return array<string, array{label: string, description: string}>
     */
    public static function catalog(): array
    {
        return [
            self::CONTRACT_EXPIRING => [
                'label' => 'قرب انتهاء عقد إيجار',
                'description' => 'من المهمّة المجدولة «تنبيهات العقود والدفعات» قبل انتهاء العقد بالمدّة المضبوطة في إعدادات العقارات',
            ],
            self::PAYMENT_DUE => [
                'label' => 'استحقاق أقساط إيجار',
                'description' => 'إشعارٌ واحد لكلّ عقد بأقساطه التي حلّت أو قاربت — بالمدّة المضبوطة في إعدادات العقارات',
            ],
            self::PAYMENT_OVERDUE => [
                'label' => 'تأخّر أقساط إيجار',
                'description' => 'إشعارٌ واحد لكلّ عقد بعدد أقساطه المتأخّرة وإجماليّها بعد مهلة السماح',
            ],
            self::MAINTENANCE_ASSIGNED => [
                'label' => 'إسناد طلب صيانة عقارية',
                'description' => 'عند إسناد طلب صيانة إلى مسؤول',
            ],
            self::MAINTENANCE_CLOSED => [
                'label' => 'إغلاق طلب صيانة عقارية',
                'description' => 'عند اكتمال طلب الصيانة أو إلغائه — يصل إلى من فتحه',
            ],
            self::TASK_ASSIGNED => [
                'label' => 'مهمة عقارية جديدة',
                'description' => 'عند إسناد مهمة أو متابعة عقارية إلى مستخدم',
            ],
            self::TASK_DUE => [
                'label' => 'مهمة عقارية فات موعدها',
                'description' => 'مرّةً واحدة عن كلّ مهمة مفتوحة تجاوزت موعدها — للمكلَّف بها',
            ],
        ];
    }

    /** @return array<int, string> */
    public static function keys(): array
    {
        return array_keys(self::catalog());
    }

    /**
     * إشعارٌ واحد لمستخدمٍ واحد. يعيد `false` إن أوقف المسؤولُ النوعَ من
     * «إعدادات الإشعارات» (بوابةُ `notifications::creating`) أو تعذّر الحفظ —
     * ولا يرمي: فشلُ الإشعار لا يُسقط العمليةَ التي استدعته.
     */
    public static function send(int $userId, string $type, string $title, string $body = ''): bool
    {
        try {
            $row = notifications::create([
                'user_id' => $userId,
                'receiver_id' => $userId,
                'title' => $title,
                'description' => $body,
                'type' => $type,
            ]);

            return (bool) ($row->exists ?? false);
        } catch (\Throwable $e) {
            report($e);

            return false;
        }
    }

    /**
     * من يُبلَّغ عن سجلّاتٍ عقارية: منشئُ كلٍّ منها، وحَمَلةُ الصلاحية **ممّن
     * يرونه في قوائمهم**.
     *
     * كان التنبيهُ يذهب إلى كلّ من يحمل الصلاحية في كلّ الفروع: موظفُ تحصيلٍ
     * في فرعٍ يُبلَّغ بأقساط عقدٍ في فرعٍ آخر لا يفتحه. «يرى» هنا بقاعدة
     * القوائم نفسِها — {@see RealEstateScope::apply()} — محسوبةً لكلّ مرشَّحٍ
     * على حدة:
     *
     *   • بلا قيد (`data_scope_all`، ومن ليس موظفاً، ومديرُ الفرع الرئيسيّ ذو
     *     نطاق الفرع/المنطقة) — يرى الكلّ.
     *   • محورُ مكان (`data_scope_branch`/`region`) — ما في فروعه، وما يخصّه
     *     هو ومن يديرهم.
     *   • محورُ أشخاص (`own`/`team`/`department`، والموظفُ بلا نطاق) — ما
     *     أنشأه أو أُسند إليه أو إلى من يديرهم.
     *
     * ولا يُقرأ اسمُ الدور في أيّ موضع: الصلاحيةُ من `role_has_permissions`،
     * والموظفُ بـ`employee_id` (داخل `DataScope`).
     *
     * `DataScope` يقرأ المستخدمَ من `Auth`، فيُلبَس كلُّ مرشَّحٍ لحظةَ الحساب ثم
     * يُعاد من كان (في المهمّة المجدولة: لا أحد) — وهو ما تفعله
     * `Modules\Integration\Services\AppPermissions` للغرض نفسِه.
     *
     * @param  class-string<Model>  $model  نموذجٌ يحمل `branch_id` و`created_by`
     * @param  array<int, int|null>  $creators  معرّف السجلّ ← منشئه
     * @return array<int, array<int, int>> معرّف السجلّ ← معرّفات المستخدمين
     */
    public static function recipientsFor(string $permission, string $model, array $creators): array
    {
        $ids = array_map('intval', array_keys($creators));
        $map = array_fill_keys($ids, []);

        if ($ids === []) {
            return $map;
        }

        $guard = Auth::guard();
        $previous = $guard->user();

        try {
            foreach (self::holders($permission) as $user) {
                $guard->setUser($user);
                self::flushScopes();

                $query = $model::query()->whereIn((new $model)->getQualifiedKeyName(), $ids);

                foreach (self::scoped($query)->pluck((new $model)->getQualifiedKeyName()) as $id) {
                    $map[(int) $id][] = (int) $user->id;
                }
            }
        } finally {
            if ($previous) {
                $guard->setUser($previous);
            } elseif (method_exists($guard, 'forgetUser')) {
                $guard->forgetUser();
            }

            self::flushScopes();
        }

        foreach ($creators as $id => $creatorId) {
            if ($creatorId && self::userExists((int) $creatorId)) {
                $map[(int) $id][] = (int) $creatorId;
            }
        }

        return array_map(fn (array $users) => array_values(array_unique($users)), $map);
    }

    /** قيدُ القوائم على استعلامٍ — `RealEstateScope` إن وُجد، وإلّا سلَّمُ `DataScope` مباشرةً. */
    private static function scoped($query)
    {
        if (class_exists(RealEstateScope::class)) {
            return RealEstateScope::apply($query);
        }

        return DataScope::isPeopleAxis()
            ? DataScope::constrainCreator($query, $query->getModel()->getTable().'.created_by')
            : DataScope::constrainBranch($query, $query->getModel()->getTable().'.branch_id');
    }

    private static function flushScopes(): void
    {
        DataScope::flush();

        if (class_exists(RealEstateScope::class)) {
            RealEstateScope::flush();
        }
    }

    /**
     * حَمَلةُ صلاحيةٍ — يُقرؤون مرّةً لكلّ صلاحية في التشغيل الواحد.
     *
     * @return Collection<int, User>
     */
    private static function holders(string $permission)
    {
        static $memo = [];

        return $memo[$permission] ??= User::whereHas('primaryRole.permissions', fn ($q) => $q->where('name', $permission))
            ->with('employee')->get();
    }

    private static function userExists(int $userId): bool
    {
        static $memo = [];

        return $memo[$userId] ??= User::whereKey($userId)->exists();
    }
}
