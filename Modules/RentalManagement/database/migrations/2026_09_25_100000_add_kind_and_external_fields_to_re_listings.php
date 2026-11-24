<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * العروض والطلبات العقارية: نوع السجلّ (عرض/طلب) وتفاصيل خارجية لا تستلزم وحدةً مسجّلة.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('re_listings')) {
            return;
        }

        Schema::table('re_listings', function (Blueprint $table) {
            if (! Schema::hasColumn('re_listings', 'listing_kind')) {
                $table->string('listing_kind', 20)->default('offer')->index();
            }
            foreach (['contact_name' => 191, 'contact_phone' => 40, 'city' => 100, 'district' => 100, 'address' => 255] as $column => $length) {
                if (! Schema::hasColumn('re_listings', $column)) {
                    $table->string($column, $length)->nullable();
                }
            }
            if (! Schema::hasColumn('re_listings', 'client_id')) {
                $table->unsignedBigInteger('client_id')->nullable()->index();
            }
            if (! Schema::hasColumn('re_listings', 'property_type')) {
                $table->string('property_type', 30)->nullable();
            }
            if (! Schema::hasColumn('re_listings', 'area_sqm')) {
                $table->decimal('area_sqm', 10, 2)->nullable();
            }
            foreach (['bedrooms', 'bathrooms'] as $column) {
                if (! Schema::hasColumn('re_listings', $column)) {
                    $table->unsignedSmallInteger($column)->nullable();
                }
            }
            foreach (['budget_min', 'budget_max'] as $column) {
                if (! Schema::hasColumn('re_listings', $column)) {
                    $table->decimal($column, 15, 2)->nullable();
                }
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('re_listings')) {
            return;
        }

        $columns = array_values(array_filter(
            ['listing_kind', 'contact_name', 'contact_phone', 'client_id', 'property_type', 'city', 'district', 'address', 'area_sqm', 'bedrooms', 'bathrooms', 'budget_min', 'budget_max'],
            fn ($c) => Schema::hasColumn('re_listings', $c)
        ));

        if ($columns) {
            Schema::table('re_listings', function (Blueprint $table) use ($columns) {
                if (in_array('listing_kind', $columns, true)) {
                    $table->dropIndex(['listing_kind']);
                }
                if (in_array('client_id', $columns, true)) {
                    $table->dropIndex(['client_id']);
                }
                $table->dropColumn($columns);
            });
        }
    }
};
