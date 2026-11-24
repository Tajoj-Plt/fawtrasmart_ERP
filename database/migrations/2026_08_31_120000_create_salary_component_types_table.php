<?php

use App\Models\SalaryComponentType;
use App\Support\SchemaGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * كتالوج البدلات والاستقطاعات (§15 TODO 4.1).
 *
 * الأنواع المعيارية تُزرع هنا حتى تظهر بلا seeder منفصل.
 * الربط على `salary_items.component_type_id` اختياري — البنود القديمة بلا تصنيف.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('salary_component_types')) {
            Schema::create('salary_component_types', function (Blueprint $table) {
                $table->id();
                $table->string('code', 64)->unique();
                $table->string('name');
                $table->unsignedTinyInteger('kind')->comment('1=addition 2=deduction — SalaryItem::TYPE_*');
                $table->boolean('is_insurable')->default(false);
                $table->boolean('is_default')->default(false);
                $table->boolean('is_system')->default(false);
                $table->unsignedInteger('sort_order')->default(0);
                $table->unsignedTinyInteger('status')->default(SalaryComponentType::STATUS_ACTIVE);
                $table->timestamps();
            });
        }

        $now = now();
        foreach (SalaryComponentType::seededRows() as $row) {
            $exists = DB::table('salary_component_types')->where('code', $row['code'])->exists();
            if ($exists) {
                continue;
            }

            DB::table('salary_component_types')->insertOrIgnore($row + [
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if (Schema::hasTable('salary_items') && ! Schema::hasColumn('salary_items', 'component_type_id')) {
            Schema::table('salary_items', function (Blueprint $table) {
                if (! Schema::hasColumn('salary_items', 'component_type_id')) {
                    $table->unsignedBigInteger('component_type_id')->nullable()->after('type');
                }
                if (! SchemaGuard::hasForeignKey('salary_items', 'salary_items_component_type_id_foreign')) {
                    $table->foreign('component_type_id')
                        ->references('id')
                        ->on('salary_component_types')
                        ->nullOnDelete();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('salary_items') && Schema::hasColumn('salary_items', 'component_type_id')) {
            Schema::table('salary_items', function (Blueprint $table) {
                $table->dropForeign(['component_type_id']);
                $table->dropColumn('component_type_id');
            });
        }

        Schema::dropIfExists('salary_component_types');
    }
};
