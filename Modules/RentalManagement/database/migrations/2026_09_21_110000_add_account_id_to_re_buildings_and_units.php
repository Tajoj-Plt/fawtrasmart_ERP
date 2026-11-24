<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\RentalManagement\Models\Building;
use Modules\RentalManagement\Models\Unit;
use Modules\RentalManagement\Services\PropertyAccountService;

/**
 * لكل مبنى حسابٌ رئيسي في دليل الحسابات، ولكل وحدةٍ حسابٌ فرعي تحته —
 * فإيراد الإيجار يُقيَّد على حساب الوحدة لا على حسابٍ عامٍّ واحد.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['re_buildings' => 'store_house_id', 're_units' => 'building_id'] as $table => $after) {
            if (! Schema::hasColumn($table, 'account_id')) {
                Schema::table($table, function (Blueprint $blueprint) use ($after) {
                    $blueprint->unsignedBigInteger('account_id')->nullable()->after($after)->index();
                });
            }
        }

        // ما سُجّل قبل هذا الترحيل يأخذ حسابه الآن. فشلُ التعبئة لا يُسقط الترحيل:
        // الحساب يُنشأ كسولاً عند أول تعديلٍ أو أول دفعة
        try {
            $service = app(PropertyAccountService::class);
            Building::whereNull('account_id')->get()->each(fn (Building $building) => $service->ensureBuildingAccount($building));
            Unit::whereNull('account_id')->get()->each(fn (Unit $unit) => $service->ensureUnitAccount($unit));
        } catch (Throwable $e) {
            report($e);
        }
    }

    public function down(): void
    {
        foreach (['re_buildings', 're_units'] as $table) {
            if (Schema::hasColumn($table, 'account_id')) {
                Schema::table($table, function (Blueprint $blueprint) use ($table) {
                    $blueprint->dropIndex($table.'_account_id_index');
                    $blueprint->dropColumn('account_id');
                });
            }
        }
    }
};
