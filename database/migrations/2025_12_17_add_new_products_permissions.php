<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * صلاحيات عرض المنتجات.
 *
 * **عطلان كانا هنا يُسقطان `migrate` على أي قاعدة جديدة:**
 *
 * ١. `use App\Models\Permission` — صنفٌ لا وجود له في المشروع. صلاحيات
 *    النظام كلّها من `spatie/laravel-permission`. فكان `migrate` يرمي
 *    «Class App\Models\Permission not found» ويتوقّف عند هذا الملف تاركاً
 *    القاعدة نصف مبنية.
 *
 * ٢. تمرير `description` — وجدول `permissions` لا يملك هذا العمود
 *    (`create_permissions_table`: id, name, guard_name, timestamps فقط).
 *    فحتى لو صحّ الاستيراد لسقط الإدراج على عمودٍ مجهول. الوصف محفوظ في
 *    التعليق أدناه، وهو موضعه الصحيح.
 *
 * والمطابقة على `name` و`guard_name` معاً لأنهما المفتاح الفريد للجدول:
 * المطابقة على الاسم وحده تُنشئ صفّاً مكرّراً حين يختلف الحارس.
 */
return new class extends Migration
{
    /**
     * الصلاحيات ووصفها:
     * - products_view_other_products     عرض المنتجات التي لم يضفها المستخدم
     * - products_view_warehouse_products عرض المنتجات المخزنة في المستودع
     * - products_view_system_products    عرض جميع المنتجات في النظام
     */
    private const PERMISSIONS = [
        'products_view_other_products',
        'products_view_warehouse_products',
        'products_view_system_products',
    ];

    public function up(): void
    {
        foreach (self::PERMISSIONS as $name) {
            Permission::firstOrCreate([
                'name' => $name,
                'guard_name' => 'web',
            ]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::whereIn('name', self::PERMISSIONS)
            ->where('guard_name', 'web')
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
