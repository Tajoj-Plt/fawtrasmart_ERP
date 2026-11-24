<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * نقلُ خصائص «كتالوج البدلات والاستقطاعات» إلى بنود الرواتب نفسها.
 *
 * ── لماذا ──
 *
 * كان في الوحدة شاشتان تعرّفان الشيءَ نفسَه: «بنود الرواتب» (`salary_items`
 * بلا قسيمةٍ ولا عقدٍ ولا قالب — وهي ما يُختار في العقد والقسيمة) و«كتالوج
 * البدلات» (`salary_component_types`) الذي يصنّف البند ليقول أمرَين فقط:
 *
 *   • `is_insurable` — هل يدخل البندُ الأجرَ الخاضعَ للتأمينات الاجتماعية.
 *   • `is_default`   — هل يدخل أجرَ احتساب مكافأة نهاية الخدمة.
 *
 * والتصنيفُ لم يكن يبلغ القسيمةَ أصلاً: العقدُ والقسيمة ينسخان البندَ
 * (الاسم والمبلغ والحساب) ولا ينسخان `component_type_id`، فكان الأجرُ
 * الخاضعُ للتأمينات في كلّ قسيمةٍ حقيقية = الأساسي وحده، أيّاً كان ما
 * ضُبط في الكتالوج.
 *
 * فالعلَمان صارا عمودين على البند نفسِه، يُضبطان من شاشة البند، ويُورَّثان
 * إلى نُسَخه في العقد والقسيمة (انظر `SalaryItem::booted()`).
 *
 * ── ما لا يُمسّ ──
 *
 * جدولُ `salary_component_types` وعمودُ `salary_items.component_type_id`
 * يبقيان كما هما: عليهما قيودٌ سابقة (مكافآتُ نهاية خدمة، بنودُ تأمينات
 * مولَّدة، تقريرُ البدلات يجمع بالاسم)، والأنواعُ المزروعة (أجر إضافي،
 * نهاية خدمة، تأمينات) ما زالت تسمّي البنودَ المولَّدة. الكتالوجُ تقاعدَ
 * شاشةً لا بيانات.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('salary_items')) {
            return;
        }

        Schema::table('salary_items', function (Blueprint $table) {
            if (! Schema::hasColumn('salary_items', 'is_insurable')) {
                $table->boolean('is_insurable')->default(false)->after('type');
            }
            if (! Schema::hasColumn('salary_items', 'in_eos_wage')) {
                $table->boolean('in_eos_wage')->default(false)->after('is_insurable');
            }
        });

        if (! Schema::hasTable('salary_component_types') || ! Schema::hasColumn('salary_items', 'component_type_id')) {
            return;
        }

        /*
            ما صُنِّف في الكتالوج يحمل علمَي نوعه — بنداً كان أو نسخةً في
            قسيمة. صفوفٌ بلا تصنيفٍ تبقى على الافتراضي (لا تأمينات ولا
            نهاية خدمة) كما كانت تُعامَل فعلاً.
        */
        $types = DB::table('salary_component_types')->get(['id', 'is_insurable', 'is_default']);

        foreach ($types as $type) {
            DB::table('salary_items')
                ->where('component_type_id', $type->id)
                ->update([
                    'is_insurable' => (bool) $type->is_insurable,
                    'in_eos_wage' => (bool) $type->is_default,
                ]);
        }
    }

    public function down(): void
    {
        /* الأعمدة لا تُحذف — الترحيلات هنا تُقاعد ولا تُتلف. */
    }
};
