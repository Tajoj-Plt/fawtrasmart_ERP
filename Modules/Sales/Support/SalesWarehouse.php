<?php

namespace Modules\Sales\Support;

use App\Models\DefaultWarehouses;
use App\Models\Product;
use App\Models\SalesInvoiceSetting;
use App\Models\StoreHouse;
use App\Models\User;
use App\Support\DataScope;
use App\Support\StorehouseAccess;
use Illuminate\Support\Collection;

/**
 * مِن أيّ مستودعٍ يبيع الموظّف؟ — جوابٌ واحدٌ لشاشات البيع كلِّها.
 *
 * **ما كان**
 *
 * الجواب مكتوبٌ حرفياً في ثلاث شاشات — فاتورة البيع، ومرتجع البيع،
 * والإشعار الدائن — مرّتين في كلٍّ منها: مرّةً حين تُبنى قائمة المستودعات
 * في شاشة الإنشاء، ومرّةً حين يُحسم المستودع عند الحفظ. ستّ نسخٍ من
 * قاعدةٍ واحدة: «مستودعات الموظّف المربوطة، فإن لم تكن فالرئيسي». فمن
 * أراد أن يبيع موظّفوه من مستودعٍ مركزيّ واحد — ومستوداعتهم مربوطةٌ بهم
 * لأغراضٍ أخرى: الجرد والنقل وعهدة المندوب — لم يجد أين يقول ذلك.
 *
 * **ما صار**
 *
 * «مصدر مستودع البيع» في إعدادات الفواتير (`warehouse_source`) يقول
 * أيَّ المصدرين هو الافتراضي، وهذا الصنف يطبّقه في الموضعين:
 * `optionsFor()` للقائمة المعروضة، و`resolve()` للمستودع المحفوظ.
 *
 * **الصلاحية فوق الإعداد**
 *
 * الإعداد يختار المصدر، و`StorehouseAccess` يقول أيُّ مستودعٍ من ذلك
 * المصدر يُعرَض ويُقبَل — فلا يفتح إعدادٌ عامٌّ مستودعاً حُرِس بصلاحية
 * «إنشاء فاتورة» المضبوطة عليه.
 *
 * **ولا يُترَك الموظّف بلا مستودع**
 *
 * لكلّ مصدرٍ نزولٌ إلى الآخر عند الفراغ: من اختار «الرئيسي» ولم يكن
 * الرئيسيُّ مسموحاً لهذا الموظّف يرجع إلى مستودعاته المربوطة، والعكس
 * كذلك — وهو النزول الذي كان قائماً قبل الإعداد. فالبديل شاشةُ بيعٍ
 * بقائمة مستودعاتٍ فارغة لا تُفسَّر.
 *
 * @see \App\Models\SalesInvoiceSetting::WAREHOUSE_SOURCES
 */
class SalesWarehouse
{
    /** الإعداد السارية — يُقرأ مرّةً في الطلب الواحد. */
    private static ?SalesInvoiceSetting $settings = null;

    /** `employee` أو `main`. */
    public static function source(): string
    {
        return self::settings()->warehouseSource();
    }

    /**
     * قائمة المستودعات التي تُعرَض في شاشة إنشاء مستند البيع.
     *
     * المستخدم بلا سجلّ موظّف — المدير وصاحب النظام — يرى المستودعات
     * كلَّها كما كان: الحراسة تنظّم عمل الموظّفين لا من يديرها.
     */
    public static function optionsFor(?User $user = null): Collection
    {
        $user = $user ?: auth()->user();

        if (! $user || ! $user->employee_id) {
            return StoreHouse::all();
        }

        $primary = self::source() === 'main'
            ? self::mainQuery()
            : self::employeeQuery($user);

        $result = StorehouseAccess::scopeCreateInvoices($primary, $user)->get();

        if ($result->isNotEmpty()) {
            return self::ownFirst($result, $user);
        }

        /* النزول إلى المصدر الآخر — انظر تعليق الصنف: لا تُترَك الشاشة
           بقائمةٍ فارغة لأنّ المصدر المختار لا مستودعَ فيه لهذا الموظّف. */
        $fallback = self::source() === 'main'
            ? self::employeeQuery($user)
            : self::mainQuery();

        return self::ownFirst(StorehouseAccess::scopeCreateInvoices($fallback, $user)->get(), $user);
    }

    /**
     * أصنافُ بنود مستند البيع — ما في مستودع البيع الافتراضي وحدَه.
     *
     * القاعدةُ نفسُها التي تبني بها شاشاتُ الفاتورة والمرتجع والإشعار
     * الدائن قائمةَ أصنافها: للموظّف أصنافُ **أوّلِ** مستودعٍ في
     * {@see optionsFor()} — وهو ما يحفظه `resolve()` حين لا يُختار
     * مستودع — ولمن لا سجلَّ موظّفٍ له الأصنافُ كلُّها. كان عرضُ السعر
     * وأمرُ البيع يعرضان المنتجات كلَّها، فيُكتب المستندُ بصنفٍ لا يُصرف
     * من مستودع الموظّف حين يُحوَّل إلى فاتورة.
     *
     * `$keepIds` أصنافُ مستندٍ محفوظ تبقى في القائمة ولو خرجت من المستودع:
     * شاشةُ التعديل لا تُخفي بنودَ المستند نفسِه.
     *
     * @param  array  $with  علاقاتٌ تُحمَّل؛ `productDetails` تُستبدَل بنسخةٍ مقصورةٍ على المستودع
     */
    public static function productsQuery(array $with = [], ?User $user = null, array $keepIds = [])
    {
        $user = $user ?: auth()->user();
        $keepIds = array_values(array_filter(array_map('intval', $keepIds)));

        if (! $user || ! $user->employee_id) {
            return Product::with($with);
        }

        $storehouseId = self::optionsFor($user)->first()->id ?? null;

        $with = array_values(array_filter($with, fn ($relation) => $relation !== 'productDetails'));
        $with['productDetails'] = fn ($query) => $query->where('store_house_id', $storehouseId);

        return Product::with($with)->where(function ($query) use ($storehouseId, $keepIds) {
            $query->whereHas('productDetails', fn ($q) => $q->where('store_house_id', $storehouseId));

            if ($stocklessTypes = self::visibleStocklessTypes()) {
                $query->orWhereIn('type', $stocklessTypes);
            }

            if ($keepIds) {
                $query->orWhereIn('id', $keepIds);
            }
        });
    }

    /**
     * أنواعُ الأصناف بلا رصيد (الخدمي والرقمي) التي تُضاف إلى قائمة الموظّف.
     *
     * شرطُ المستودع (`whereHas('productDetails')`) لا يمرّ منه صنفٌ لا رصيد
     * له، فيُضاف النوعُ بـ`orWhereIn` في كلّ شاشةٍ تصفّي على المستودع —
     * بحسب «إعدادات الفواتير». انظر
     * {@see SalesInvoiceSetting::stocklessTypesForEmployees()}.
     *
     * @return array<int, string>
     */
    public static function visibleStocklessTypes(): array
    {
        return self::settings()->stocklessTypesForEmployees();
    }

    /**
     * مستودعاتُ الموظّف نفسِه أوّلاً ثمّ مستودعاتُ فريقه.
     *
     * القائمةُ صارت تضمّ فريقَه ({@see employeeQuery()})، و`resolve()`
     * تأخذ **أوّلَها** حين لا يختار المستخدم شيئاً — فلولا هذا الترتيب
     * لحُفظت فاتورةُ المشرف على عهدة مندوبٍ من مندوبيه بلا أن يطلب ذلك.
     * وهو أيضاً ما يريده في الشاشة: عهدتُه أوّلَ القائمة.
     *
     * @param  Collection<int, StoreHouse>  $warehouses
     * @return Collection<int, StoreHouse>
     */
    private static function ownFirst(Collection $warehouses, ?User $user): Collection
    {
        if (! $user || ! $user->employee_id || $warehouses->count() < 2) {
            return $warehouses;
        }

        $own = DefaultWarehouses::where('employee_id', $user->employee_id)
            ->pluck('storehouse_id')->map(fn ($id) => (int) $id)->all();

        if (! $own) {
            return $warehouses;
        }

        return $warehouses->sortBy(fn ($warehouse) => in_array((int) $warehouse->id, $own, true) ? 0 : 1)
            ->values();
    }

    /**
     * المستودع الذي يُحفَظ به المستند.
     *
     * `$selectedId` هو ما اختاره المستخدم في الشاشة — يُقدَّم على الإعداد
     * لأنّ الإعداد يضبط الافتراضي لا يُلغي الاختيار، وصلاحيةُ المستودع
     * مفحوصةٌ في المتحكّم قبل بلوغ هذه الدالّة.
     */
    public static function resolve($selectedId = null, ?User $user = null): ?StoreHouse
    {
        $user = $user ?: auth()->user();

        if ($selectedId && ($selected = StoreHouse::find($selectedId))) {
            return $selected;
        }

        /* المستخدم بلا سجلّ موظّف لا مستودعات مربوطةً له ولا تصفيةَ صلاحية
           عليه، فقائمتُه المستودعاتُ كلُّها — وأوّلُها ليس الرئيسيَّ
           بالضرورة. فيُقصَد الرئيسيُّ له صراحةً كما كان قبل هذا الصنف. */
        if ($user && $user->employee_id) {
            if ($first = self::optionsFor($user)->first()) {
                return $first;
            }
        }

        return StoreHouse::where('major', 1)->first();
    }

    /** معرّف المستودع المحسوم — `null` حين لا مستودع في النظام أصلاً. */
    public static function resolveId($selectedId = null, ?User $user = null): ?int
    {
        return self::resolve($selectedId, $user)?->id;
    }

    /** المستودعات المربوطة بالموظّف في «المستودعات الافتراضية». */
    /**
     * مستودعاتُ الموظّف المربوطة — **ومستودعاتُ من يراهم بنطاقه**.
     *
     * كان القيدُ `employee_id = $user->employee_id` وحدَه: المشرفُ الذي
     * يدير مندوبَين يفتح شاشة الفاتورة فلا يجد في قائمة المستودعات إلّا
     * مستودعَه هو — فلا يستطيع أن يبيع من عهدة أحدهما ولا أن يصحّح
     * فاتورةً عليه، وقد مُنح «بيانات فريقه» صراحةً.
     *
     * والنطاقُ من {@see BBApp\Support\DataScope::documentEmployeeIds()} — السلَّمُ
     * نفسُه الذي يُصفّي جداولَ المبيعات ومُنتقيَ «مسؤول المبيعات»، فلا
     * تَعِد الشاشةُ بموظّفٍ لا مستودعَ له في القائمة.
     *
     * و`null` تعني بلا قيد (مديرٌ له سجلّ موظف): المستودعاتُ كلُّها كما
     * يراها من لا سجلَّ موظفٍ له في {@see optionsFor()} — لا مستودعُه
     * وحدَه.
     */
    private static function employeeQuery(User $user)
    {
        $employeeIds = DataScope::documentEmployeeIds();

        if ($employeeIds === null) {
            return StoreHouse::query();
        }

        return StoreHouse::whereIn(
            'id',
            DefaultWarehouses::whereIn('employee_id', $employeeIds ?: [$user->employee_id])
                ->pluck('storehouse_id')
        );
    }

    /** المستودع الرئيسي — `store_houses.major = 1`. */
    private static function mainQuery()
    {
        return StoreHouse::where('major', 1);
    }

    private static function settings(): SalesInvoiceSetting
    {
        return self::$settings ??= SalesInvoiceSetting::current();
    }
}
