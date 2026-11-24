<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * حذف بطلب المستخدم — «هدف التحصيل الشهري» ونسب تصنيف العملاء (A++/A/B)
 * ما كان لهما داعٍ كإعداد قابل للتعديل. الشاشة والكنترولر رجعا يستعملان
 * القيم الثابتة الأصلية (648, 100/60/30) مباشرة في الكود.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('general_client_settings', function (Blueprint $table) {
            if (Schema::hasColumn('general_client_settings', 'monthly_collection_target')) {
                $table->dropColumn('monthly_collection_target');
            }
            if (Schema::hasColumn('general_client_settings', 'classification_thresholds')) {
                $table->dropColumn('classification_thresholds');
            }
        });
    }

    public function down(): void
    {
        Schema::table('general_client_settings', function (Blueprint $table) {
            $table->decimal('monthly_collection_target', 10, 2)->nullable()->after('default_new_client_status_id');
            $table->json('classification_thresholds')->nullable()->after('monthly_collection_target');
        });
    }
};
