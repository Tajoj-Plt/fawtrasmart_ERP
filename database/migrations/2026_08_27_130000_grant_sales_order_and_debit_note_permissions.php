<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * صلاحيات «أوامر البيع» و«الإشعارات المدينة» — إلى الأدوار التي تملك نظيرَها.
 *
 * ── المشكلة ──
 *
 * `permissions:sync` يُنشئ الصلاحية في جدول `permissions` ولا يربطها بدور.
 * و`hasPermission()` تقرأ **دور المستخدم** (`role_id` ← `role_has_permissions`)
 * لا Spatie المباشرة — انظر `app/Helpers/PermissionHelper.php`. فالنتيجة أنّ
 * مستنداً جديداً يخرج بصلاحياتٍ موجودةٍ في القاعدة ومملوكةٍ لأحد: الرابط
 * مخفيّ في القائمة الجانبية، والشاشة مردودةٌ بـ403، حتى لو كان صاحب النظام
 * مديراً يملك كل شيء سواه. وهو ما وقع فعلاً: صفرُ ارتباطٍ لكلّ من
 * `sales.sales_orders.*` و`sales.debit_notes.*` بعد المزامنة.
 *
 * ── لماذا ترحيلٌ لا انتظارُ شاشة الأدوار ──
 *
 * نفس منطق `2026_08_26_120000_add_system_update_permissions`: ميزةٌ لا
 * يفتحها أحد ليست ميزة. والترحيل يُشغَّل على كل تثبيت بأيّ وسيلة، فيصل
 * الأثر إلى الخوادم التي لا سطر أوامر لها.
 *
 * ── المنح بالنظير لا بالتعميم ──
 *
 * لا تُمنح الصلاحية لكل دور، بل **لكلّ دورٍ يملك نظيرَها على المستند
 * الشقيق**:
 *
 *   `sales.sales_orders.X` ← كلّ دورٍ يملك `sales.quotes.X`
 *   `sales.debit_notes.X`  ← كلّ دورٍ يملك `sales.credit_notes.X`
 *
 * أمرُ البيع وعرضُ السعر مستندٌ واحدٌ في بنيته (صفّان في `quotes`)،
 * والإشعارُ المدين نظيرُ الدائن معكوساً. فمن يُصدر عرضاً ويحوّله إلى فاتورة
 * يفعل الشيء نفسه بأمر البيع، ومن يُصدر إشعاراً دائناً يعرف الإشعار المدين.
 * ودورٌ لا يملك إلّا `view` من عروض الأسعار لا يأخذ إلّا `view` من الأوامر.
 *
 * وما يُمنَح هنا يبقى قابلاً للسحب من شاشة الأدوار كأيّ صلاحية أخرى.
 *
 * `down()` تسحب الارتباطات وحدها ولا تحذف الصلاحيات: الصلاحية مصدرُها
 * كتالوج `SalesPermissions`، والتراجع عن ترحيلٍ لا يعني إلغاء الميزة.
 */
return new class extends Migration
{
    /**
     * الصلاحية الجديدة ← نظيرُها الذي يُشتقّ منه الدور.
     *
     * `send_link` لأوامر البيع نظيرُه `send_link` لعروض الأسعار، و`cancel`
     * للإشعار المدين نظيرُه `cancel` للدائن — والدائن وحده فيه `cancel`
     * أصلاً، فالمقابلة قائمة.
     */
    private const MAPPING = [
        'sales.sales_orders.view' => 'sales.quotes.view',
        'sales.sales_orders.create' => 'sales.quotes.create',
        'sales.sales_orders.edit' => 'sales.quotes.edit',
        'sales.sales_orders.delete' => 'sales.quotes.delete',
        'sales.sales_orders.print' => 'sales.quotes.print',
        'sales.sales_orders.send_link' => 'sales.quotes.send_link',
        'sales.sales_orders.convert' => 'sales.quotes.convert',

        'sales.debit_notes.view' => 'sales.credit_notes.view',
        'sales.debit_notes.create' => 'sales.credit_notes.create',
        'sales.debit_notes.print' => 'sales.credit_notes.print',
        'sales.debit_notes.cancel' => 'sales.credit_notes.cancel',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('role_has_permissions')) {
            return;
        }

        foreach (self::MAPPING as $name => $counterpart) {
            $permissionId = $this->permissionId($name, true);
            $counterpartId = $this->permissionId($counterpart, false);

            if (! $permissionId || ! $counterpartId) {
                continue;
            }

            $roleIds = DB::table('role_has_permissions')
                ->where('permission_id', $counterpartId)
                ->pluck('role_id');

            foreach ($roleIds as $roleId) {
                /* `insertOrIgnore` لا `insert`: المفتاح مركّب، وإعادةُ
                   تشغيل الترحيل على قاعدةٍ مُنحت فيها الصلاحية يدوياً من
                   شاشة الأدوار تسقط بلا هذا. */
                DB::table('role_has_permissions')->insertOrIgnore([
                    'permission_id' => $permissionId,
                    'role_id' => $roleId,
                ]);
            }
        }

        $this->forgetPermissionCache();
    }

    public function down(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('role_has_permissions')) {
            return;
        }

        $ids = DB::table('permissions')
            ->whereIn('name', array_keys(self::MAPPING))
            ->pluck('id');

        DB::table('role_has_permissions')->whereIn('permission_id', $ids)->delete();

        $this->forgetPermissionCache();
    }

    /** معرّف الصلاحية — وتُنشأ إن غابت وكان `$create` صحيحاً. */
    private function permissionId(string $name, bool $create): ?int
    {
        $id = DB::table('permissions')
            ->where('name', $name)
            ->where('guard_name', 'web')
            ->value('id');

        if ($id || ! $create) {
            return $id ? (int) $id : null;
        }

        // تثبيتٌ لم يُشغَّل فيه `permissions:sync` بعد: الترحيل يُنشئها
        // فلا تتوقّف الميزة على أمرٍ في سطر الأوامر
        return (int) DB::table('permissions')->insertGetId([
            'name' => $name,
            'guard_name' => 'web',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** كاش Spatie يحمل خريطة الأدوار والصلاحيات — بلا تفريغه لا يُرى المنح. */
    private function forgetPermissionCache(): void
    {
        try {
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        } catch (\Throwable $e) {
            // الحزمة غير مهيّأة في هذا السياق — الكاش سينتهي بنفسه
        }
    }
};
