<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * سجلُّ دخول الموظفين إلى ملف العميل — مَن فتح `clients.show` ومتى.
 *
 * `logs` يسرد ما تغيّر على العميل، وفتحُ الملف لا يغيّر شيئاً؛ فكتابتُه هناك
 * تُغرق الخطَّ الزمني بسطرٍ لكل تحديثٍ للصفحة. لذلك جدولٌ مستقل، صفٌّ لكل
 * دخول و`viewed_at` وقتُه.
 *
 * والصلاحية تُنشأ وتُربط هنا، لا عبر `permissions:sync` وحده الذي لا يربطها
 * بدور ([[new-permissions-need-role-links]]). تُمنح لأدوار النظام الثلاثة؛
 * المشرفُ وغيرُه تُؤشَّر لهم من شاشة الأدوار — المندوب يملك
 * `clients_view_all_activity_logs` في بعض التركيبات، فلا تصلح نظيراً.
 */
return new class extends Migration
{
    private const PERMISSION = 'clients_view_profile_access_log';

    private const ROLES = ['owner', 'manager', 'admin'];

    public function up(): void
    {
        if (! Schema::hasTable('client_profile_views')) {
            Schema::create('client_profile_views', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('client_id');
                $table->unsignedBigInteger('user_id');
                $table->timestamp('viewed_at')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->string('user_agent', 255)->nullable();
                $table->timestamps();

                $table->index(['client_id', 'viewed_at']);
                $table->index(['client_id', 'user_id', 'viewed_at']);
            });
        }

        if (! Schema::hasTable('permissions') || ! Schema::hasTable('role_has_permissions') || ! Schema::hasTable('roles')) {
            return;
        }

        $permissionId = DB::table('permissions')->where('name', self::PERMISSION)->where('guard_name', 'web')->value('id')
            ?: DB::table('permissions')->insertGetId([
                'name' => self::PERMISSION,
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        foreach (DB::table('roles')->whereIn('name', self::ROLES)->pluck('id') as $roleId) {
            DB::table('role_has_permissions')->insertOrIgnore([
                'permission_id' => $permissionId,
                'role_id' => $roleId,
            ]);
        }

        try {
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        } catch (\Throwable $e) {
            // الحزمة غير مهيّأة في هذا السياق — الكاش سينتهي بنفسه
        }
    }

    public function down(): void
    {
        // لا حذف: الترحيلات هنا تُقاعد ولا تُسقط ([[migration-retire-not-drop]])
    }
};
