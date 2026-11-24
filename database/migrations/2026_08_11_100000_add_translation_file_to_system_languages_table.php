<?php

use App\Support\SchemaGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * إضافة الحقول الناقصة لجدول اللغات دون المساس بالبيانات الحالية.
 *
 * - translation_file: مسار ملف الترجمة المرفوع (resources/lang/{code}.json)
 * - توسعة عمود flag ليستوعب اسم ملف علم أو رمز Emoji
 * - فهارس للبحث والفرز
 */
return new class extends Migration
{

    public function up(): void
    {
        if (! Schema::hasTable('system_languages')) {
            return;
        }

        Schema::table('system_languages', function (Blueprint $table) {
            if (! Schema::hasColumn('system_languages', 'translation_file')) {
                $table->string('translation_file')->nullable()->after('flag');
            }
        });

        // توسعة عمود العلم: كان varchar(10) وهو لا يكفي لاسم ملف مثل "sa.svg" مع الامتداد الطويل
        if (Schema::hasColumn('system_languages', 'flag')) {
            Schema::table('system_languages', function (Blueprint $table) {
                $table->string('flag', 50)->nullable()->change();
            });
        }

        // فهارس تخدم الفلترة والفرز في شاشة الإدارة
        Schema::table('system_languages', function (Blueprint $table) {
            $indexes = collect(Schema::getIndexes('system_languages'))->pluck('name')->all();

            if (! in_array('system_languages_is_active_index', $indexes, true)) {
                if (! SchemaGuard::hasIndex('system_languages', 'system_languages_is_active_index')) {
                    $table->index('is_active');
                }
            }
            if (! in_array('system_languages_is_default_index', $indexes, true)) {
                if (! SchemaGuard::hasIndex('system_languages', 'system_languages_is_default_index')) {
                    $table->index('is_default');
                }
            }
            if (! in_array('system_languages_sort_order_index', $indexes, true)) {
                if (! SchemaGuard::hasIndex('system_languages', 'system_languages_sort_order_index')) {
                    $table->index('sort_order');
                }
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('system_languages')) {
            return;
        }

        Schema::table('system_languages', function (Blueprint $table) {
            $indexes = collect(Schema::getIndexes('system_languages'))->pluck('name')->all();

            if (in_array('system_languages_is_active_index', $indexes, true)) {
                $table->dropIndex('system_languages_is_active_index');
            }
            if (in_array('system_languages_is_default_index', $indexes, true)) {
                $table->dropIndex('system_languages_is_default_index');
            }
            if (in_array('system_languages_sort_order_index', $indexes, true)) {
                $table->dropIndex('system_languages_sort_order_index');
            }

            if (Schema::hasColumn('system_languages', 'translation_file')) {
                $table->dropColumn('translation_file');
            }
        });
    }
};