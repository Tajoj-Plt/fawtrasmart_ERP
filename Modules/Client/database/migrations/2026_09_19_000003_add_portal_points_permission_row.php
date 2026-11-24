<?php

use App\Models\ClientPermission;
use App\Support\ClientPortalPermissions;
use Illuminate\Database\Migrations\Migration;

/**
 * صفُّ مفتاح «نقاطي ومكافآتي» في «صلاحيات بوابة العميل».
 *
 * مفتاحٌ بلا صفٍّ لا يمكن تفعيله من الشاشة. ويُفعَّل هنا صراحةً لأن القسمَ
 * بديلٌ عن بوابةٍ كانت مفتوحةً للعملاء فعلاً (`/client-portal`): لو أُنشئ
 * مطفأً لفقد العملاءُ نقاطَهم من الواجهة يوم التوحيد.
 *
 * و`firstOrCreate` وحدها لا تكفي على قاعدةٍ سبق أن أنشأت الصفّ في ترحيلٍ
 * آخر — فالتفعيلُ `update` مستقلّ.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (ClientPortalPermissions::all() as $key => $definition) {
            ClientPermission::firstOrCreate(
                ['key' => $key],
                ['name' => $definition['label'], 'is_active' => false],
            );
        }

        ClientPermission::where('key', 'view_points_rewards')->update(['is_active' => true]);

        ClientPortalPermissions::forget();
    }

    public function down(): void
    {
        ClientPermission::where('key', 'view_points_rewards')->update(['is_active' => false]);

        ClientPortalPermissions::forget();
    }
};
