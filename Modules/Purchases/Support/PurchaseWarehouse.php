<?php

namespace Modules\Purchases\Support;

use App\Models\DefaultWarehouses;
use App\Models\StoreHouse;
use App\Models\User;
use App\Support\StorehouseAccess;
use Illuminate\Support\Collection;

/**
 * إلى أيّ مستودعٍ تدخل بضاعةُ الشراء؟ — نظيرُ
 * {@see \Modules\Sales\Support\SalesWarehouse} في المشتريات.
 *
 * ══════════════════ ما كان ══════════════════
 *
 * **نموذجُ فاتورة الشراء لا يحمل حقلَ مستودعٍ إطلاقاً.** لا في الإنشاء
 * ولا في التعديل ولا في بنوده — فحصُ الجدول كلِّه لا يجد `store_house_id`
 * في مستندٍ من مستندات الدورة عدا شاشة «استلام المشتريات».
 *
 * والخادمُ يقرؤه: `InvoicesPurchaseController::store()` يحسم المستودعَ
 * بـ«المُرسَل، ثمّ مستودعُ الموظّف الافتراضي، ثمّ الرئيسيّ»، ويفحص عليه
 * صلاحيةَ «إنشاء فاتورة» المضبوطة على المستودع
 * ({@see StorehouseAccess::canCreateInvoices()}). فما دام النموذج لا
 * يرسل شيئاً:
 *
 *   • تدخل البضاعةُ **المستودعَ الرئيسيّ دائماً** — أو مستودعَ الموظّف
 *     المربوط إن وُجد — بلا أن يعرف المُدخِل أين ذهبت. ومن ينظر في
 *     مستودعٍ آخر يقول: «الفاتورة لم تُحدِّث المخزن».
 *   • وفحصُ الصلاحية على المستودع لا يعمل من هذه الشاشة قطّ: الحارسُ
 *     مكتوبٌ ولا يمرّ عليه شيء.
 *   • ومنشأةٌ بلا مستودعٍ موسومٍ `major = 1` تُرَدّ فاتورتُها برسالة «لا
 *     يوجد أي مستودع في النظام» وفيها مستودعاتٌ عدّة.
 *
 * ══════════════════ وما صار ══════════════════
 *
 * الحقلُ يُعرَض في تبويبة التسليم — حيث يُقرَّر الاستلام نفسُه — وقائمتُه
 * من `optionsFor()`: مستودعاتُ الموظّف المربوطة أوّلاً، ومن لا مربوطَ له
 * يرى ما تسمح به صلاحيتُه. والترتيبُ نفسُه الذي يحسم به الخادم، فما
 * يُعرَض هو ما يُقبَل.
 *
 * والمستخدمُ بلا سجلّ موظّف — المدير وصاحب النظام — يرى المستودعات كلَّها
 * كما في المبيعات: الحراسةُ تنظّم عملَ الموظّفين لا من يديرها.
 */
class PurchaseWarehouse
{
    /**
     * المستودعات التي تُعرَض في شاشة مستند الشراء.
     *
     * @return Collection<int, StoreHouse>
     */
    public static function optionsFor(?User $user = null): Collection
    {
        $user = $user ?: auth()->user();

        if (! $user || ! $user->employee_id) {
            return self::onHeaderBranch(StoreHouse::query())->orderByDesc('major')->orderBy('name')->get();
        }

        $linked = self::linkedIds($user);

        if ($linked !== []) {
            /* «استلام المشتريات في المستودع الرئيسي» مفعَّلاً: الرئيسيُّ يدخل
               قائمةَ الموظّف إلى جانب مستودعاته — وإلّا لم يجده ليختاره. */
            $own = StorehouseAccess::scopeCreateInvoices(
                StoreHouse::where(function ($w) use ($linked) {
                    $w->whereIn('id', $linked);

                    if (self::mainFirst()) {
                        $w->orWhere('major', 1);
                    }
                }),
                $user
            )->orderByDesc('major')->orderBy('name')->get();

            if ($own->isNotEmpty()) {
                return $own;
            }
        }

        /* النزولُ إلى ما تسمح به الصلاحية: من لا مستودعَ مربوطاً له — أو
           رُبط بمستودعٍ حُرِس عنه — لا تُترَك قائمتُه فارغةً بلا تفسير،
           وهو النزولُ نفسُه الذي يفعله الخادم إلى الرئيسيّ. */
        return StorehouseAccess::scopeCreateInvoices(self::onHeaderBranch(StoreHouse::query()), $user)
            ->orderByDesc('major')->orderBy('name')->get();
    }

    /**
     * القائمةُ العامّة تتبع فرعَ الشريط العلويّ وما يتبعه.
     *
     * لا تمسّ المستودعاتِ المربوطةَ بالموظّف — تلك إسنادٌ صريح. والمستودعُ
     * بلا فرعٍ يبقى ظاهراً، كقاعدة `forBranch()`. والرئيسيُّ يعني المنشأة كلَّها
     * ({@see \App\Support\HeaderBranchScope}).
     */
    private static function onHeaderBranch($query)
    {
        $node = \App\Support\HeaderBranchScope::nodeId();

        if (! $node) {
            return $query;
        }

        return $query->where(fn ($w) => $w
            ->whereIn('branch_id', \App\Support\AffiliationTree::descendantIds($node))
            ->orWhereNull('branch_id'));
    }

    /**
     * المستودعُ المختار مبدئياً في الشاشة — نفسُ ترتيب حسم الخادم:
     * المُرسَل، ثمّ مستودعُ الموظّف الافتراضي، ثمّ الرئيسيّ، ثمّ أوّلُ متاح.
     */
    public static function defaultId($submitted = null, ?User $user = null): ?int
    {
        $user = $user ?: auth()->user();
        $options = self::optionsFor($user);

        if ($submitted && $options->contains('id', (int) $submitted)) {
            return (int) $submitted;
        }

        /* مصدرُ المستودع من الإعدادات: الرئيسيّ أوّلاً، أو مستودعُ الموظّف
           أوّلاً. وكلٌّ منهما ينزل إلى الآخر إن لم يجد — لا قائمةَ بلا افتراض. */
        if (self::mainFirst() && ($main = $options->firstWhere('major', 1))) {
            return (int) $main->id;
        }

        foreach (self::linkedIds($user) as $id) {
            if ($options->contains('id', $id)) {
                return $id;
            }
        }

        return $options->firstWhere('major', 1)?->id ?? $options->first()?->id;
    }

    /**
     * أيُستلَم في المستودع الرئيسيّ افتراضاً؟ — إعدادُ المشتريات
     * `receive_in_main_warehouse`، نظيرُ «مصدر مستودع البيع» في المبيعات.
     */
    public static function mainFirst(): bool
    {
        return \Modules\Purchases\Services\PurchaseSettings::enabled('receive_in_main_warehouse');
    }

    /** @return array<int, int> مستودعاتُ الموظّف المربوطة */
    private static function linkedIds(?User $user): array
    {
        if (! $user || ! $user->employee_id) {
            return [];
        }

        return DefaultWarehouses::where('employee_id', $user->employee_id)
            ->whereNotNull('storehouse_id')
            ->pluck('storehouse_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
