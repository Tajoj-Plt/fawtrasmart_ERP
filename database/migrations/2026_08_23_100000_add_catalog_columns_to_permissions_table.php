<?php

use App\Support\SchemaGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * أعمدة التقسيم في جدول `permissions`.
 *
 * الجدول كان `name` و`guard_name` لا غير، فكانت كل شاشةٍ تريد تصنيف
 * الصلاحيات تشتقّه من بادئة الاسم — وهو اشتقاقٌ يكذب: `Delete_Client`
 * بادئتها «Delete»، و`inv_manage_suppliers_add` بادئتها «inv» وهي من
 * المشتريات، و`employee_orders_management` بادئتها «employee» وهي من
 * الطلبات. الاسم أثرُ تاريخٍ لا تصنيف.
 *
 * التصنيف الآن بياناتٌ في الجدول تكتبها المزامنة من
 * [App\Constants\permissions\PermissionCatalog]:
 *
 *   module / module_label   القسم وتسميته العربية
 *   group_key / group_label المجموعة داخل القسم وتسميتها
 *   label                   تسمية الصلاحية العربية
 *   sort_order              ترتيب العرض (رتبة القسم × 10000 + ترتيبها فيه)
 *
 * التسميات مكرَّرةٌ هنا عمداً مع الكتالوج: الشاشات والتقارير تقرأ التقسيم
 * باستعلامٍ واحد بلا تحميل أحد عشر كتالوجاً، والمزامنة تُبقي النسختين
 * متطابقتين. المصدر يبقى الكتالوج — هذه الأعمدة نسخته المقروءة.
 *
 * `group` كلمةٌ محجوزة في MySQL، فالعمود `group_key`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('permissions', function (Blueprint $table) {
            if (! Schema::hasColumn('permissions', 'module')) {
                $table->string('module', 64)->nullable()->after('guard_name');
            }
            if (! Schema::hasColumn('permissions', 'module_label')) {
                $table->string('module_label', 191)->nullable()->after('module');
            }
            if (! Schema::hasColumn('permissions', 'group_key')) {
                $table->string('group_key', 64)->nullable()->after('module_label');
            }
            if (! Schema::hasColumn('permissions', 'group_label')) {
                $table->string('group_label', 191)->nullable()->after('group_key');
            }
            if (! Schema::hasColumn('permissions', 'label')) {
                $table->string('label', 191)->nullable()->after('group_label');
            }
            if (! Schema::hasColumn('permissions', 'sort_order')) {
                $table->unsignedInteger('sort_order')->default(0)->after('label');
            }

            if (! SchemaGuard::hasIndex('permissions', 'permissions_module_index')) {
                $table->index('module', 'permissions_module_index');
            }
            if (! SchemaGuard::hasIndex('permissions', 'permissions_module_group_index')) {
                $table->index(['module', 'group_key'], 'permissions_module_group_index');
            }
            if (! SchemaGuard::hasIndex('permissions', 'permissions_sort_order_index')) {
                $table->index('sort_order', 'permissions_sort_order_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('permissions', function (Blueprint $table) {
            $table->dropIndex('permissions_module_index');
            $table->dropIndex('permissions_module_group_index');
            $table->dropIndex('permissions_sort_order_index');

            $table->dropColumn([
                'module',
                'module_label',
                'group_key',
                'group_label',
                'label',
                'sort_order',
            ]);
        });
    }
};
