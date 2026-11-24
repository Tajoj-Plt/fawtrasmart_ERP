<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * توسعة قوالب الرسائل: تصنيف ووصف وترتيب وحماية للقوالب النظامية.
 *
 * كان الجدول يعرض قائمة مسطّحة بلا تصنيف. سبعة قوالب تُحتمل هكذا، أمّا
 * كتالوج يغطّي المبيعات والمشتريات والمخزون والموارد البشرية والولاء
 * فيصير بلا تصنيف قائمةً لا تُقرأ.
 *
 * `is_system` يمنع حذف القالب الذي يستدعيه الكود برمزه: حذفه يُسكِت
 * إشعاراً دون أن يُنبَّه أحد.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sms_templates', function (Blueprint $table) {
            if (! Schema::hasColumn('sms_templates', 'category')) {
                $table->string('category', 40)->default('general')->after('name')->index()
                    ->comment('تصنيف القالب — يُجمَّع به العرض');
            }
            if (! Schema::hasColumn('sms_templates', 'description')) {
                $table->string('description', 500)->nullable()->after('category')
                    ->comment('متى يُرسل هذا القالب ولماذا');
            }
            if (! Schema::hasColumn('sms_templates', 'is_system')) {
                $table->boolean('is_system')->default(false)->after('is_active')
                    ->comment('قالب يستدعيه الكود برمزه — لا يُحذف');
            }
            if (! Schema::hasColumn('sms_templates', 'sort_order')) {
                $table->unsignedSmallInteger('sort_order')->default(0)->after('is_system');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sms_templates', function (Blueprint $table) {
            $table->dropIndex(['category']);
            $table->dropColumn(['category', 'description', 'is_system', 'sort_order']);
        });
    }
};
