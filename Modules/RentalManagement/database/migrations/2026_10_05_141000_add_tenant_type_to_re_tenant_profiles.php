<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * نوع المستأجر (فرد / شركة) وبيانات ممثّل الشركة. الرقم الضريبي والسجلّ
 * التجاري عمودان قائمان في `clients` — لا يتكرّران هنا.
 */
return new class extends Migration
{
    private const COLUMNS = [
        'tenant_type' => 20,
        'representative_name' => 150,
        'representative_title' => 100,
        'representative_id_number' => 50,
        'representative_phone' => 30,
    ];

    public function up(): void
    {
        if (! Schema::hasTable('re_tenant_profiles')) {
            return;
        }

        Schema::table('re_tenant_profiles', function (Blueprint $table) {
            foreach (self::COLUMNS as $column => $length) {
                if (Schema::hasColumn('re_tenant_profiles', $column)) {
                    continue;
                }

                $definition = $table->string($column, $length);
                $column === 'tenant_type' ? $definition->default('individual') : $definition->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('re_tenant_profiles')) {
            return;
        }

        Schema::table('re_tenant_profiles', function (Blueprint $table) {
            foreach (array_keys(self::COLUMNS) as $column) {
                if (Schema::hasColumn('re_tenant_profiles', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
