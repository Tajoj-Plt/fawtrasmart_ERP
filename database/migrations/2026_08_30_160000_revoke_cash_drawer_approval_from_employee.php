<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

/**
 * سحبُ اعتماد حركات الدرج من دور «موظف».
 *
 * ═════════════════════ العَرَض ═════════════════════
 *
 * كاشيرٌ يفتح `/POS/cash-drawer` فيرى حركاتِ درج زملائه كلِّهم — لا حركاتِه
 * وحدها.
 *
 * ═════════════════════ والسبب ═════════════════════
 *
 * `CashDrawerController::scopeMovements()` تقيّد بالمُنشئ **إلّا لمن يملك
 * الاعتماد** — ومن يعتمد يلزمه أن يرى ما يعتمده. والقاعدةُ صحيحة. لكنّ
 * الدورَ الافتراضيَّ «employee» يحمل `pos.cash_drawer.approve`، فلا يُقيَّد
 * أحد.
 *
 * ═════════════════════ وأخطرُ من الرؤية ═════════════════════
 *
 * الدورُ نفسُه يحمل `pos.cash_drawer.pay_out` و`pos.cash_drawer.drop`.
 * فالكاشيرُ يُخرج نقداً من الدرج **ويعتمد إخراجَه بنفسه**. وهو نقضٌ للفصل
 * الذي بنته الوحدةُ صراحةً في كتالوج الصلاحيات:
 *
 *     «مفصولٌ عن الجلسة عمداً: فتحُ الجلسة عملٌ يوميّ للكاشير، وسحبُ النقد
 *      من الدرج عملٌ رقابيّ. من يبيع ليس بالضرورة من يُخرج المال.»
 *
 * حالةُ `pending` في `PosCashMovement` موجودةٌ لهذا الغرض وحده — ومنحُ
 * الاعتماد لمن يُنشئ يجعلها مرحلةً تمرّ بلا أن يراها أحد.
 *
 * ═════════════════════ ما يبقى ═════════════════════
 *
 * `view` و`pay_in` و`pay_out` و`drop` كما هي — الكاشيرُ يُودع الفكّةَ
 * ويُودع النقدَ ويطلب السحب. يتغيّر شيءٌ واحد: طلبُه ينتظر اعتماداً.
 *
 * ودورُ «employee» وحده — لا `owner` ولا `manager` ولا `admin`، ولا أيُّ
 * دورٍ أنشأه العميل. و`down()` تُعيده.
 *
 * ويكمّله [الترحيل 2026_08_30_140000] الذي سحب صلاحيات الجلسات الرقابية.
 */
return new class extends Migration
{
    private const PERMISSION = 'pos.cash_drawer.approve';

    private const ROLE = 'employee';

    public function up(): void
    {
        $ids = $this->ids();

        if (! $ids) {
            return;
        }

        DB::table('role_has_permissions')
            ->where('role_id', $ids['role'])
            ->where('permission_id', $ids['permission'])
            ->delete();

        $this->forgetPermissionCache();
    }

    public function down(): void
    {
        $ids = $this->ids();

        if (! $ids) {
            return;
        }

        $exists = DB::table('role_has_permissions')
            ->where('role_id', $ids['role'])
            ->where('permission_id', $ids['permission'])
            ->exists();

        if (! $exists) {
            DB::table('role_has_permissions')->insert([
                'role_id' => $ids['role'],
                'permission_id' => $ids['permission'],
            ]);
        }

        $this->forgetPermissionCache();
    }

    /** @return array{role: int, permission: int}|null */
    private function ids(): ?array
    {
        if (! Schema::hasTable('roles') || ! Schema::hasTable('permissions')
            || ! Schema::hasTable('role_has_permissions')) {
            return null;
        }

        $role = DB::table('roles')->where('name', self::ROLE)->value('id');
        $permission = DB::table('permissions')->where('name', self::PERMISSION)->value('id');

        return $role && $permission
            ? ['role' => (int) $role, 'permission' => (int) $permission]
            : null;
    }

    private function forgetPermissionCache(): void
    {
        if (class_exists(PermissionRegistrar::class)) {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }
};
