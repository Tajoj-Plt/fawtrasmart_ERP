<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «الضريبة الشاملة الافتراضية للمستندات» — عمودٌ على `tax_sittings`.
 *
 * ضريبةٌ واحدةٌ تُعلَّم بأنّها الافتراضية للمستندات، فتظهر في مستندات البيع
 * والشراء خانةٌ واحدةٌ أعلى المستند تُطبَّق على كلّ الأصناف بدل اختيار ضريبةٍ
 * لكلّ سطر. تُضبَط من شاشة «إعدادات الضريبة» وتسري على الوحدتين معاً.
 *
 * الافتراض `false` لكلّ الصفوف: النظام اليوم يختار الضريبة لكلّ بند، فتشغيلُ
 * الترحيل وحده لا يغيّر سلوك شاشةٍ قائمة حتى يُعلِّم المستخدمُ ضريبةً بنفسه.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tax_sittings') || Schema::hasColumn('tax_sittings', 'is_comprehensive')) {
            return;
        }

        Schema::table('tax_sittings', function (Blueprint $table) {
            $table->boolean('is_comprehensive')->default(false)->after('type');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('tax_sittings') || ! Schema::hasColumn('tax_sittings', 'is_comprehensive')) {
            return;
        }

        Schema::table('tax_sittings', function (Blueprint $table) {
            $table->dropColumn('is_comprehensive');
        });
    }
};
