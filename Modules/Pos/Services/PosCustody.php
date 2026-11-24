<?php

namespace Modules\Pos\Services;

use App\Models\CashierDevice;
use App\Models\PosGeneralSetting;
use App\Models\PosSession;
use App\Models\TreasuryEmployee;
use App\Models\User;

/**
 * سياقُ البيع: مَن يبيع، ومن أيّ مخزن، وإلى أيّ خزينة.
 *
 * ═══════════════════ مصدران مختلفان لسؤالين مختلفين ═══════════════════
 *
 * الوحدةُ تسأل هنا سؤالين لا سؤالاً واحداً، ولكلٍّ منهما مصدرُه الصحيح:
 *
 *   • **المخزن** — مِلكُ المنشأة لا مِلكُ أحد. فمصدرُه **إعدادُ نقطة
 *     البيع** (`pos_general_settings.store_house_id`) بالتوريث: صفُّ الفرع
 *     ← الصفُّ العام. انظر [PosCustody::storeHouseId].
 *
 *   • **الخزينة** — عهدةُ الكاشير نفسِه: نقدٌ في يده يُسأل عنه عند
 *     الإقفال. فمصدرُها `treasury_employees` لموظّف الجهاز.
 *
 * وكان الاثنان يُشتقّان من الموظّف معاً — فانقسم مخزونُ الفرع على
 * الكاشيرين، وتغيّر حسابُ المخزون في القيد بتغيّر من وقف على الجهاز.
 *
 * ═══════════════════ سلسلةُ الموظّف — للخزينة ═══════════════════
 *
 *     جهازُ الجلسة النشطة  →  موظّفُ الجهاز  →  خزينتُه
 *                          ↓ (لا جلسة)
 *     المستخدمُ الحالي     →  موظّفُه        →  خزينتُه
 *                          ↓
 *                        لا شيء  ⇐ يمنعه `EnsurePosCustody`
 *
 * **وموظّفُ الجهاز يتقدّم على المستخدم الحالي**: مشرفٌ يفتح جلسةً على جهاز
 * كاشيرٍ يقبض في خزينة ذلك الكاشير، لا في خزينته هو. وهذا ما يجعل الدرجَ
 * يُقفل على صاحبه.
 *
 * **ولا سقوطَ إلى مخزنٍ أو خزينةٍ اعتباطية**: `null` تعني «غيرُ مضبوط»،
 * وهي حالةٌ تُمنع لا تُداوى بعهدة غيره.
 */
class PosCustody
{
    /** ذاكرةٌ لمدّة الطلب: السلسلةُ تُقرأ في كل استعلامِ منتجات. */
    private static array $memo = [];

    /**
     * الموظّفُ صاحبُ العهدة في هذه اللحظة.
     *
     * @return int|null معرّفُ الموظّف في `employees`
     */
    public static function employeeId(?int $userId = null): ?int
    {
        $userId = $userId ?: auth()->id();

        if (! $userId) {
            return null;
        }

        return static::remember("emp:{$userId}", function () use ($userId) {
            // ١) موظّفُ جهازِ الجلسة النشطة
            $deviceUserId = PosSession::query()
                ->where('pos_sessions.status', PosSession::STATUS_ACTIVE)
                ->where('pos_sessions.user_id', $userId)
                ->join('cashier_devices', 'cashier_devices.id', '=', 'pos_sessions.device_id')
                ->value('cashier_devices.user_id');

            if ($deviceUserId) {
                $employeeId = User::whereKey($deviceUserId)->value('employee_id');

                if ($employeeId) {
                    return (int) $employeeId;
                }
            }

            // ٢) موظّفُ المستخدم الحالي
            $own = User::whereKey($userId)->value('employee_id');

            return $own ? (int) $own : null;
        });
    }

    /**
     * مستودعُ نقطة البيع — يُقرأ منه المعروضُ ويُخصم منه المباع.
     *
     * ═══════════════ ما كان: مخزونُ المندوب ═══════════════
     *
     *     DefaultWarehouses::where('employee_id', $employeeId)
     *         ->value('storehouse_id');
     *
     * المستودعُ يُشتقّ من **عهدة الموظّف**. وهو منطقُ مندوبٍ يحمل بضاعتَه
     * في سيّارته، لا منطقُ كاشيرٍ يبيع من مخزن الفرع. وأثرُه ثلاثة:
     *
     *   ١. كاشيرٌ لم يُربط بمستودعٍ في شاشة الموظفين **لا يبيع إطلاقاً**
     *      — والربطُ إجراءٌ مخزنيّ لا علاقةَ له بنقطة البيع.
     *
     *   ٢. كاشيران في فرعٍ واحد بمستودعين ينقسم مخزونُ الفرع بينهما،
     *      فلا يُعرف رصيدُ الصنف من شاشةٍ واحدة، ويظهر «نفدت الكمّية» على
     *      جهازٍ والبضاعةُ في مخزن زميله.
     *
     *   ٣. والمستودعُ يحدّد **حساب المخزون** في قيد التكلفة
     *      (`accounts.storehouse_id`) — فقيدُ الفاتورة الواحدة يتغيّر
     *      حسابُه بتغيّر مَن وقف على الجهاز.
     *
     * ═══════════════ وما صار: إعدادٌ يُختار ═══════════════
     *
     *     إعدادُ الفرع  ←  الإعدادُ العام
     *
     * وهو `PosGeneralSetting::forBranch()` نفسُها التي تُحلّ بها بقيّةُ
     * الإعدادات — فلا تعاقدَ ثانٍ يُخترع هنا.
     *
     * **وفرعُ الجلسة يتقدّم على فرع المستخدم**: كاشيرٌ يفتح جلسةً على
     * جهاز فرعٍ آخر يبيع من مخزن ذلك الفرع، لا من مخزن فرعه المسجَّل في
     * `users.branch_id`.
     *
     * و`null` تعني «لا مستودعَ مضبوط» — حالةٌ تُمنع عند الباب
     * (`EnsurePosCustody`) ولا تُداوى بمخزنٍ مخمَّن.
     */
    public static function storeHouseId(?int $userId = null): ?int
    {
        $branchId = static::branchId($userId);

        return static::remember('wh:'.($branchId ?: 'global'), function () use ($branchId) {
            /*
                والسقوطُ إلى العام حقلاً بحقل.

                `forBranch()` تُرجع **صفَّ الفرع كاملاً** إن وُجد ولا تنزل
                إلى العام. فمنشأةٌ ضبطت مستودعَها في «الإعداد العام»، وفرعٌ
                له صفٌّ أُنشئ لسببٍ آخر (قالبُ طباعةٍ مثلاً) وعمودُ مستودعه
                فارغ ⇒ لا مستودعَ للفرع، وتُغلق شاشةُ البيع في وجهه
                والإعدادُ مضبوطٌ أمام عينَي المسؤول.

                فالمستودعُ وحدَه يرث: ما اختاره الفرعُ صراحةً يفوز، وما تركه
                يعود إلى مستودع المنشأة.
            */
            $id = PosGeneralSetting::forBranch($branchId)?->store_house_id;

            if (! $id) {
                $id = PosGeneralSetting::whereNull('branch_id')->value('store_house_id');
            }

            return $id ? (int) $id : null;
        });
    }

    /**
     * فرعُ التشغيل — فرعُ الجلسة النشطة، وإلا فرعُ المستخدم.
     *
     * الجلسةُ أخصّ: هي التي تقول على أيّ جهازٍ وفي أيّ فرعٍ يجري البيعُ
     * الآن، بينما `users.branch_id` إعدادٌ يُكتب مرّةً عند التبديل.
     */
    public static function branchId(?int $userId = null): ?int
    {
        $userId = $userId ?: auth()->id();

        if (! $userId) {
            return function_exists('current_branch_id') ? current_branch_id() : null;
        }

        return static::remember("br:{$userId}", function () use ($userId) {
            $sessionBranch = PosSession::query()
                ->where('status', PosSession::STATUS_ACTIVE)
                ->where('user_id', $userId)
                ->value('branch_id');

            if ($sessionBranch) {
                return (int) $sessionBranch;
            }

            $userBranch = User::whereKey($userId)->value('branch_id');

            return $userBranch ? (int) $userBranch : null;
        });
    }

    /** خزينةُ العهدة — يدخلها نقدُ البيع. */
    public static function treasuryId(?int $userId = null): ?int
    {
        $employeeId = static::employeeId($userId);

        if (! $employeeId) {
            return null;
        }

        return static::remember("tr:{$employeeId}", function () use ($employeeId) {
            $id = TreasuryEmployee::where('employee_id', $employeeId)->value('treasury_id');

            return $id ? (int) $id : null;
        });
    }

    /**
     * جهازُ الجلسة النشطة — لمن يحتاج الجهازَ نفسَه لا عهدتَه.
     */
    public static function activeDevice(?int $userId = null): ?CashierDevice
    {
        $userId = $userId ?: auth()->id();

        if (! $userId) {
            return null;
        }

        $deviceId = PosSession::query()
            ->where('status', PosSession::STATUS_ACTIVE)
            ->where('user_id', $userId)
            ->value('device_id');

        return $deviceId ? CashierDevice::find($deviceId) : null;
    }

    /**
     * ما ينقص من العهدة — قائمةٌ فارغةٌ تعني أنّ كلَّ شيءٍ مربوط.
     *
     * @return array<int, string>
     */
    public static function missing(?int $userId = null): array
    {
        $gaps = [];

        /*
            المستودعُ إعدادٌ لا عهدة — فالرسالةُ تدلّ على شاشة الإعدادات،
            والخزينةُ عهدةٌ فرسالتُها تدلّ على شاشة الموظفين. وخلطُ
            الدلالتين كان يُرسل المستخدمَ إلى الشاشة الخطأ.
        */
        if (! static::storeHouseId($userId)) {
            $gaps[] = 'مستودعُ نقطة البيع (يُضبط من إعدادات نقاط البيع ← تبويب البيع)';
        }

        if (! static::treasuryId($userId)) {
            $gaps[] = 'خزينةٌ للكاشير (تُربط من شاشة الموظفين)';
        }

        return $gaps;
    }

    /** تُنسى الذاكرةُ عند تبدّل الجلسة أو الربط — يناديها الاختبارُ كذلك. */
    public static function forget(): void
    {
        static::$memo = [];
    }

    private static function remember(string $key, callable $resolve)
    {
        if (! array_key_exists($key, static::$memo)) {
            static::$memo[$key] = $resolve();
        }

        return static::$memo[$key];
    }
}
