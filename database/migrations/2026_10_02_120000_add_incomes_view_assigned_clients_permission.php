<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

/**
 * «عرض سندات قبض العملاء المسندين إليه» (`account.incomes.view_assigned_clients`).
 *
 * قائمةُ سندات القبض وتفاصيلُ السند وطباعتُه تُقيَّد بمنشئ السند (سنداته وفريقه)، فالمندوب لا يرى
 * سنداً قبضه زميلٌ من عميلٍ مسنَدٍ إليه. هذه الصلاحية تضيف إلى نطاق **العرض** سنداتِ عملائه
 * المسنَدين إليه في `client_employee` — والتعديلُ والإلغاءُ يبقيان على نطاق المنشئ.
 *
 * `AccountPermissions::groups()` تعرّفها وشاشة الأدوار تسردها؛ وهذا الترحيل يخلق صفَّها فقط
 * ولا يمنحها لأيّ دور: هي توسيعٌ لما يراه الدور، فتُمنح من شاشة الأدوار عمداً.
 *
 * `down()` تسحب الروابط وتترك صفّ الصلاحية (نفس ملاحظة 2026_09_01_140000).
 */
return new class extends Migration
{
    private const PERMISSION = 'account.incomes.view_assigned_clients';

    private const GUARD = 'web';

    public function up(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        $exists = DB::table('permissions')
            ->where('name', self::PERMISSION)
            ->where('guard_name', self::GUARD)
            ->exists();

        if (! $exists) {
            DB::table('permissions')->insert([
                'name' => self::PERMISSION,
                'guard_name' => self::GUARD,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->forgetPermissionCache();
    }

    public function down(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('role_has_permissions')) {
            return;
        }

        $id = DB::table('permissions')
            ->where('name', self::PERMISSION)
            ->where('guard_name', self::GUARD)
            ->value('id');

        if ($id) {
            DB::table('role_has_permissions')->where('permission_id', $id)->delete();
        }

        $this->forgetPermissionCache();
    }

    /** بلا هذا تبقى الصلاحيةُ غيرَ مرئيّةٍ حتى انتهاء ذاكرة Spatie المؤقّتة. */
    private function forgetPermissionCache(): void
    {
        if (app()->bound(PermissionRegistrar::class)) {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }
};
