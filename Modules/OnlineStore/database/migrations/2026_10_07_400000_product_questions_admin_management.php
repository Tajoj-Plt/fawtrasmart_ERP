<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

/**
 * إدارة «أسئلة المنتجات» من لوحة المتجر.
 *
 * 1) `product_questions.client_id` يصير اختيارياً + عمود `asker_name`:
 *    الموظف يسجّل سؤالاً وصله من زائرٍ بلا حساب (هاتف/واتساب/الفرع) —
 *    كان العمود إلزامياً فلا يُحفظ سؤالٌ إلا لعميل متجر مسجَّل.
 * 2) صلاحيتا `online_store.questions.create` و`online_store.questions.edit`
 *    تُضافان لجدول الصلاحيات (بنفس بيانات مجموعة «أسئلة المنتجات») وتُمنحان
 *    لكل دورٍ يملك «الإجابة على سؤال» — من يجيب اليوم يضيف ويعدّل غداً
 *    بلا خطوةٍ يدوية.
 *
 * آمنٌ للتكرار: كل خطوة تفحص قبل أن تكتب، ولا يُحذف شيء.
 */
return new class extends Migration
{
    private const NEW_PERMISSIONS = [
        'online_store.questions.create' => 'إضافة سؤال نيابةً عن عميل أو زائر',
        'online_store.questions.edit' => 'تعديل سؤال',
    ];

    public function up(): void
    {
        if (Schema::hasTable('product_questions')) {
            if (! Schema::hasColumn('product_questions', 'asker_name')) {
                Schema::table('product_questions', function (Blueprint $table) {
                    $table->string('asker_name', 150)->nullable()->after('client_id');
                });
            }

            // بلا doctrine/dbal — تعديل العمود بجملةٍ صريحة، وعلى MySQL فقط
            if (DB::getDriverName() === 'mysql') {
                $column = collect(DB::select('SHOW COLUMNS FROM product_questions'))
                    ->firstWhere('Field', 'client_id');

                if ($column && strtoupper((string) $column->Null) === 'NO') {
                    DB::statement('ALTER TABLE product_questions MODIFY client_id BIGINT UNSIGNED NULL');
                }
            }
        }

        $this->addPermissions();
    }

    private function addPermissions(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        // صفّ «الإجابة على سؤال» مرجعٌ لبيانات المجموعة والترتيب
        $reference = DB::table('permissions')->where('name', 'online_store.questions.answer')->first();
        if (! $reference) {
            return; // الكتالوج لم يُزامَن بعد — `permissions:sync` يتكفّل لاحقاً
        }

        $newIds = [];
        foreach (self::NEW_PERMISSIONS as $name => $label) {
            $existing = DB::table('permissions')->where('name', $name)->where('guard_name', $reference->guard_name)->first();
            if ($existing) {
                $newIds[] = $existing->id;

                continue;
            }

            $row = ['name' => $name, 'guard_name' => $reference->guard_name, 'created_at' => now(), 'updated_at' => now()];
            foreach (['module', 'module_label', 'group_key', 'group_label', 'sort_order'] as $col) {
                if (Schema::hasColumn('permissions', $col)) {
                    $row[$col] = $reference->{$col};
                }
            }
            if (Schema::hasColumn('permissions', 'label')) {
                $row['label'] = $label;
            }

            $newIds[] = DB::table('permissions')->insertGetId($row);
        }

        if (! Schema::hasTable('role_has_permissions')) {
            return;
        }

        $roleIds = DB::table('role_has_permissions')->where('permission_id', $reference->id)->pluck('role_id');
        foreach ($roleIds as $roleId) {
            foreach ($newIds as $permissionId) {
                $exists = DB::table('role_has_permissions')
                    ->where('role_id', $roleId)->where('permission_id', $permissionId)->exists();
                if (! $exists) {
                    DB::table('role_has_permissions')->insert(['role_id' => $roleId, 'permission_id' => $permissionId]);
                }
            }
        }

        try {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        } catch (Throwable $e) {
            // لا كاش صلاحيات — لا شيء يُمسح
        }
    }

    public function down(): void
    {
        // لا تراجع: إعادة `client_id` إلزامياً تكسر أسئلة الزوّار المحفوظة
    }
};
