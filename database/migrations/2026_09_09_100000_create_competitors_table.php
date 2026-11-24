<?php

use App\Support\SchemaGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * قائمة المنافسين — كتالوجٌ يُدار من شاشة، لا اسمٌ حرٌّ يُكتب في كل رصد.
 *
 * كان `visit_competitor_observations.competitor_name` نصّاً حرّاً: «بيبسي»
 * و«Pepsi» و«بيبسى» ثلاثةُ منافسين مختلفين في أي تقرير، ولا سبيل لجمع
 * حضور منافسٍ واحدٍ عبر العملاء. المندوب يختار الآن من هذه القائمة،
 * والاسمُ الحرّ يبقى في عمود الرصد لصفوفه القديمة وحدها.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('competitors')) {
            Schema::create('competitors', function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique();
                $table->string('notes')->nullable();
                // منافسٌ خرج من السوق لا يُحذف — تبقى أرصاده التاريخية
                // مقروءةً، ويُخفى من قائمة الاختيار وحدها
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index('is_active');
            });
        }

        Schema::table('visit_competitor_observations', function (Blueprint $table) {
            if (! Schema::hasColumn('visit_competitor_observations', 'competitor_id')) {
                $table->unsignedBigInteger('competitor_id')->nullable()->after('employee_id');
                $table->index('competitor_id');
            }

            // «عدد قطع العهدة» — كم قطعةً من عهدة هذا المنافس عند العميل
            if (! Schema::hasColumn('visit_competitor_observations', 'asset_count')) {
                $table->unsignedInteger('asset_count')->nullable()->after('competitor_name');
            }

            /* الرصد صار يُسجَّل **داخل الملاحظة** لا في مودالٍ منفصل، فلا بدّ
               من رابطٍ إليها: تبويب «رصد المنافسين» يعرض ما رُصد مع كل
               ملاحظة، والملاحظة هي وحدة عمل الزيارة. */
            if (! Schema::hasColumn('visit_competitor_observations', 'client_relation_id')) {
                $table->unsignedBigInteger('client_relation_id')->nullable()->after('visit_id');
                $table->index('client_relation_id');
            }
        });

        /* المفتاح الأجنبي بعد العمود وبحارسٍ صريح: قواعد الإنتاج تسبق
           مجموعة الترحيلات هذه، وإضافةُ مفتاحٍ موجودٍ تُسقط الترحيل كلَّه —
           راجع `SchemaGuard` وتعليق «انحراف المخطّط القديم». */
        if (! SchemaGuard::hasForeignKey('visit_competitor_observations', 'vco_competitor_id_foreign')) {
            try {
                Schema::table('visit_competitor_observations', function (Blueprint $table) {
                    $table->foreign('competitor_id', 'vco_competitor_id_foreign')
                        ->references('id')->on('competitors')
                        ->nullOnDelete();
                });
            } catch (\Throwable $e) {
                // محرّكٌ أو نوعُ عمودٍ لا يقبل الربط في قاعدةٍ قديمة —
                // العمود يبقى صالحاً والتطبيق لا يعتمد على القيد نفسه
            }
        }
    }

    public function down(): void
    {
        Schema::table('visit_competitor_observations', function (Blueprint $table) {
            foreach (['competitor_id', 'asset_count', 'client_relation_id'] as $column) {
                if (Schema::hasColumn('visit_competitor_observations', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::dropIfExists('competitors');
    }
};
