<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ترتيبُ صلاحيات قسم الحضور والانصراف (`HrPermissions`).
 *
 * ١) «أماكن التسجيل وأجهزة الفروع» (`attendance_places.*`) — كانت الأماكنُ
 *    والأجهزةُ ورمزُ الربط والموظفون المربوطون ورمزُ المكتب تحت «قواعد الحضور
 *    ومحدّداته» (`attendance_rules.*`)، فمن يُمنح تعديلَ قواعد الخصم يملك ربطَ
 *    الأجهزة وفكَّها. تُمنح لكل دورٍ يحمل نظيرتَها في القواعد (فعلاً بفعل).
 *
 * ٢) «بطاقات الحضور» (`attendance_cards.*`) — إصدارُ البطاقات وحذفُها وطباعتُها
 *    كانت بصلاحية تشغيل شاشة الجهاز نفسِها (`attendance_registration.others`)،
 *    فحسابُ تابلت الاستقبال يحذف بطاقات الموظفين. تُمنح لكل دورٍ يحمل تلك.
 *
 * ٣) ثلاثُ صلاحياتٍ لا تحرس شيئاً — لا شاشةَ ولا زرَّ خلفها — تُحذف:
 *    `attendance_records.destroy`، `attendance_records.import`، `attendance_books.status`.
 */
return new class extends Migration
{
    /** الجديدة ← نظيرتُها التي يُورَث منها المنح */
    private const MAPPING = [
        'attendance_places.index' => 'attendance_rules.index',
        'attendance_places.show' => 'attendance_rules.index',
        'attendance_places.create' => 'attendance_rules.create',
        'attendance_places.edit' => 'attendance_rules.edit',
        'attendance_places.destroy' => 'attendance_rules.destroy',
        'attendance_cards.index' => 'attendance_registration.others',
        'attendance_cards.manage' => 'attendance_registration.others',
        'attendance_cards.print' => 'attendance_registration.others',
    ];

    private const RETIRED = [
        'attendance_records.destroy',
        'attendance_records.import',
        'attendance_books.status',
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

            foreach (DB::table('role_has_permissions')->where('permission_id', $counterpartId)->pluck('role_id') as $roleId) {
                DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $permissionId, 'role_id' => $roleId]);
            }
        }

        // لا شاشةَ خلفها: تُحذف صفوفُها وروابطُها
        $retired = DB::table('permissions')->whereIn('name', self::RETIRED)->where('guard_name', 'web')->pluck('id');
        DB::table('role_has_permissions')->whereIn('permission_id', $retired)->delete();
        if (Schema::hasTable('model_has_permissions')) {
            DB::table('model_has_permissions')->whereIn('permission_id', $retired)->delete();
        }
        DB::table('permissions')->whereIn('id', $retired)->delete();

        $this->forgetPermissionCache();
        $this->classifyFromCatalog();
    }

    /**
     * الصفوفُ الجديدة تُنشأ هنا بالاسم وحده، وشاشةُ الأدوار تجمعها بأعمدة
     * `module`/`group_key`/`label` التي يكتبها `permissions:sync-catalog` — وهو زرٌّ
     * اختياريّ في «تحديث النظام». بدونه تظهر «الأماكن والأجهزة» و«البطاقات»
     * في «غير مصنَّفة» لا في قسم الحضور، كأنها بلا صلاحيات.
     */
    private function classifyFromCatalog(): void
    {
        try {
            \Illuminate\Support\Facades\Artisan::call('permissions:sync-catalog');
        } catch (\Throwable $e) {
            // يكمله زرُّ «مزامنة كتالوج الصلاحيات» في شاشة تحديث النظام
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('role_has_permissions')) {
            return;
        }

        // الروابطُ وحدها — والمحذوفةُ لا تعود: لم تكن تحرس شيئاً
        $ids = DB::table('permissions')->whereIn('name', array_keys(self::MAPPING))->pluck('id');
        DB::table('role_has_permissions')->whereIn('permission_id', $ids)->delete();

        $this->forgetPermissionCache();
    }

    private function permissionId(string $name, bool $create): ?int
    {
        $id = DB::table('permissions')->where('name', $name)->where('guard_name', 'web')->value('id');

        if ($id || ! $create) {
            return $id ? (int) $id : null;
        }

        return (int) DB::table('permissions')->insertGetId([
            'name' => $name,
            'guard_name' => 'web',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function forgetPermissionCache(): void
    {
        try {
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        } catch (\Throwable $e) {
            // الكاشُ يُبنى من جديد عند أوّل طلب
        }
    }
};
