<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * تسليم الفواتير واستلامها من مساحة الزيارة.
 *
 * ══════════ اتجاهُ التحويل ══════════
 *
 * كان التحويلُ اتجاهاً واحداً: صاحبُ العهدة يدفع فاتورتَه إلى موظفٍ آخر
 * (`send`). المندوبُ الذي يزور عميلاً فاتورتُه المستحقّة في عهدة غيره لا
 * يملك إلّا أن يتّصل به ليرسلها. صار له أن **يطلب استلامها** (`request`):
 * الطلبُ يصل إلى صاحب العهدة، فإن قَبِل صارت الفاتورة باسم الطالب.
 *
 * في الاتجاهين `from_employee_id` هو من تخرج الفاتورة من عهدته و
 * `to_employee_id` من تدخل عهدتَه — فالقبولُ ينقل الفاتورة إلى `to` كما
 * كان. الذي يتغيّر هو من يردّ ومن يُلغي:
 *
 *   send    ← يردّ `to`، ويُلغي `from`
 *   request ← يردّ `from` (صاحب العهدة)، ويُلغي `to` (الطالب)
 *
 * و`visit_id` يختم التحويلَ الذي جرى من مساحة زيارة، للأثر لا للقيد.
 *
 * ══════════ الصلاحيات ══════════
 *
 * `clients_invoice_transfer_request` جديدة، وتُمنح لكلّ دورٍ يملك
 * `clients_invoice_transfer_send` اليوم. ودورُ «مندوب مبيعات» لم يكن يملك
 * أيّاً من صلاحيات التحويل، فلا يرى الشاشة ولا رابطها في قائمة العملاء ولا
 * يصله طلبٌ يُرسَل إليه — تُمنح له الخمس بطلب المستخدم (2026-09-25). وما
 * يُمنح يبقى قابلاً للسحب من شاشة الأدوار.
 */
return new class extends Migration
{
    private const REQUEST = 'clients_invoice_transfer_request';

    private const REP_ROLE = 'مندوب مبيعات';

    private const REP_GRANTS = [
        'clients_invoice_transfer_view',
        'clients_invoice_transfer_send',
        'clients_invoice_transfer_request',
        'clients_invoice_transfer_respond',
        'clients_invoice_transfer_cancel',
    ];

    public function up(): void
    {
        if (Schema::hasTable('invoice_transfers')) {
            Schema::table('invoice_transfers', function (Blueprint $table) {
                if (! Schema::hasColumn('invoice_transfers', 'direction')) {
                    $table->string('direction', 10)->default('send')->after('client_id');
                }

                if (! Schema::hasColumn('invoice_transfers', 'visit_id')) {
                    $table->unsignedBigInteger('visit_id')->nullable()->after('direction');
                    $table->index('visit_id');
                }
            });
        }

        if (! Schema::hasTable('permissions') || ! Schema::hasTable('role_has_permissions')) {
            return;
        }

        $requestId = $this->permissionId(self::REQUEST, true);
        $sendId = $this->permissionId('clients_invoice_transfer_send', false);

        if ($requestId && $sendId) {
            foreach (DB::table('role_has_permissions')->where('permission_id', $sendId)->pluck('role_id') as $roleId) {
                DB::table('role_has_permissions')->insertOrIgnore([
                    'permission_id' => $requestId,
                    'role_id' => $roleId,
                ]);
            }
        }

        if (Schema::hasTable('roles')) {
            $repRoles = DB::table('roles')->where('name', self::REP_ROLE)->pluck('id');

            foreach (self::REP_GRANTS as $name) {
                if (! $permissionId = $this->permissionId($name, true)) {
                    continue;
                }

                foreach ($repRoles as $roleId) {
                    DB::table('role_has_permissions')->insertOrIgnore([
                        'permission_id' => $permissionId,
                        'role_id' => $roleId,
                    ]);
                }
            }
        }

        $this->forgetPermissionCache();
    }

    public function down(): void
    {
        // المنحُ لدور المندوب يبقى — قد يكون المسؤول أبقاه عمداً من شاشة الأدوار
        if (Schema::hasTable('permissions') && Schema::hasTable('role_has_permissions')) {
            $ids = DB::table('permissions')->where('name', self::REQUEST)->pluck('id');
            DB::table('role_has_permissions')->whereIn('permission_id', $ids)->delete();
            $this->forgetPermissionCache();
        }

        if (Schema::hasTable('invoice_transfers')) {
            Schema::table('invoice_transfers', function (Blueprint $table) {
                if (Schema::hasColumn('invoice_transfers', 'visit_id')) {
                    $table->dropIndex(['visit_id']);
                    $table->dropColumn('visit_id');
                }

                if (Schema::hasColumn('invoice_transfers', 'direction')) {
                    $table->dropColumn('direction');
                }
            });
        }
    }

    private function permissionId(string $name, bool $create): ?int
    {
        $id = DB::table('permissions')
            ->where('name', $name)
            ->where('guard_name', 'web')
            ->value('id');

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
            // الحزمة غير مهيّأة في هذا السياق — الكاش سينتهي بنفسه
        }
    }
};
