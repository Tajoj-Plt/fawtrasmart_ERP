<?php

namespace App\Constants\permissions;

/**
 * السجلّ الجامع لكتالوجات الصلاحيات — المصدر الوحيد لتقسيم صلاحيات النظام.
 *
 * كل كتالوج يصف قسماً (`{Module}Permissions extends BasePermissionCatalog`)،
 * وهذا السجلّ يرتّبها ويجمعها في شجرةٍ واحدة: **قسم ← مجموعة ← صلاحية**،
 * ولكلٍّ من الثلاثة تسميةٌ عربية وسطرٌ توضيحي.
 *
 * **من يقرأ من هنا؟**
 *
 * - [App\Console\Commands\SyncPermissionCatalog] — ينسخ الشجرة إلى أعمدة
 *   `module` و`group_key` و`label` في جدول `permissions`، ويُنشئ ما ينقص
 *   منها ويبلّغ عمّا في الجدول ولا كتالوج له.
 * - [App\Support\PermissionLabels] — الترجمة والتصنيف للعرض.
 * - شاشة بناء الأدوار — تقرأ التقسيم من أعمدة الجدول بعد المزامنة، لا من
 *   بادئة الاسم كما كانت.
 *
 * **إضافة قسم**: كتالوجٌ جديد يرث [BasePermissionCatalog]، ثم سطرٌ في
 * `CATALOGS` أدناه، ثم `php artisan permissions:sync-catalog`. لا موضع
 * ثالث.
 */
class PermissionCatalog
{
    /**
     * كتالوجات الأقسام — الترتيب هنا لا يهمّ، `order` في `module()` هو الحَكَم.
     *
     * @var array<int, class-string<BasePermissionCatalog>>
     */
    public const CATALOGS = [
        DataScopePermissions::class,
        SalesPermissions::class,
        PosPermissions::class,
        ClientsPermissions::class,
        BranchesPermissions::class,
        ReservationsPermissions::class,
        EcosystemPermissions::class,
        StockPermissions::class,
        OnlineStorePermissions::class,
        PurchasesPermissions::class,
        AccountPermissions::class,
        HrPermissions::class,
        TaskManagerPermissions::class,
        OperationsPermissions::class,
        RealEstatePermissions::class,
        SystemPermissions::class,
        SettingsUserManagementPermissions::class,
    ];

    /*
     * كانت هنا `SUPERSEDED` — استثناءُ أسماءٍ في `JobRole::$job_roles` لها
     * مرادفٌ حديث، كُتب هنا لأن تلك القائمة كانت «تُقرأ ولا تُعدَّل».
     * سقط ذلك الشرط مع تقاعد أسماء النظام القديم كلِّها
     * ([الترحيل 2026_09_12_210000])، فالاسمُ المهجور يُحذف من مصدره الآن
     * ولا يُستثنى في موضعٍ ثانٍ.
     */

    /** @var array<string, array{module:string, module_label:string, group_key:string, group_label:string, label:string, sort_order:int}>|null */
    protected static ?array $index = null;

    /**
     * شجرة الأقسام كاملةً، مرتَّبةً بـ`order`.
     *
     * @return array<string, array{key:string, label:string, description:string, icon:string, order:int, groups:array}>
     */
    public static function modules(): array
    {
        $modules = [];

        foreach (self::CATALOGS as $catalog) {
            $module = $catalog::module();
            $module['groups'] = $catalog::groups();
            // أقسامٌ فرعية تُرتَّب بها المجموعات في شاشة الأدوار (اختيارية لكل كتالوج)
            $module['sections'] = method_exists($catalog, 'sections') ? $catalog::sections() : [];
            $module['catalog'] = $catalog;
            $modules[$module['key']] = $module;
        }

        uasort($modules, fn ($a, $b) => $a['order'] <=> $b['order']);

        return $modules;
    }

    /**
     * فهرسٌ مسطَّح: اسم الصلاحية ← موضعها كاملاً.
     *
     * يُبنى مرةً واحدة لكل طلب — `locate()` في الكتالوج تمشي على مجموعاته
     * كلها في كل نداء، ومناداتها ثماني مئة مرة في شاشةٍ واحدة تُبطئها.
     *
     * @return array<string, array{module:string, module_label:string, group_key:string, group_label:string, label:string, sort_order:int}>
     */
    public static function index(): array
    {
        if (self::$index !== null) {
            return self::$index;
        }

        $index = [];

        foreach (self::modules() as $module) {
            $sort = 0;

            foreach ($module['groups'] as $groupKey => $group) {
                foreach ($group['permissions'] as $name => $label) {
                    $sort++;

                    $index[$name] = [
                        'module' => $module['key'],
                        'module_label' => $module['label'],
                        'group_key' => $groupKey,
                        'group_label' => $group['label'],
                        'label' => $label,
                        'sort_order' => $module['order'] * 10000 + $sort,
                    ];
                }
            }
        }

        return self::$index = $index;
    }

    /** كل أسماء الصلاحيات المعرَّفة في الكتالوجات. */
    public static function all(): array
    {
        return array_keys(self::index());
    }

    /** موضع صلاحيةٍ واحدة — أو null إن لم يعرفها أي كتالوج. */
    public static function locate(string $permission): ?array
    {
        return self::index()[$permission] ?? null;
    }

    /** التسمية العربية لصلاحية. */
    public static function describe(string $permission): ?string
    {
        return self::index()[$permission]['label'] ?? null;
    }

    /** تعريف قسمٍ بمفتاحه. */
    public static function module(string $key): ?array
    {
        return self::modules()[$key] ?? null;
    }

    /**
     * أسماءٌ مكرّرة بين كتالوجين — خطأُ تعريفٍ لا حالةَ تشغيل.
     *
     * `index()` يكتب الأخير فوق الأول بصمت، فيظهر التقسيم صحيحاً والصلاحية
     * في القسم الخطأ. يستدعيها أمر المزامنة ليقولها بدل أن تُبتلَع.
     *
     * @return array<string, array<int, string>> اسم الصلاحية => الأقسام التي تدّعيها
     */
    public static function duplicates(): array
    {
        $seen = [];

        foreach (self::modules() as $module) {
            foreach ($module['groups'] as $group) {
                foreach (array_keys($group['permissions']) as $name) {
                    $seen[$name][] = $module['key'];
                }
            }
        }

        return array_filter($seen, fn ($modules) => count($modules) > 1);
    }
}
