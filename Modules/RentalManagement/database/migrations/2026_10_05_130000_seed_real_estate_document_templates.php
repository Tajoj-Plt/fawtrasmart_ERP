<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\RentalManagement\Support\RealEstateTemplates;

/**
 * قالبان جاهزان في «قوالب المستندات»: عقد الإيجار العقاري وإيصال دفعته.
 *
 * النوعان `realestate_contract` و`realestate_receipt` جديدان على الشاشة، وبلا
 * قالبٍ واحد لكلٍّ منهما تبقى الطباعةُ على الصفحة المدمجة في الوحدة ولا يجد
 * المسؤول ما يصمّمه. التصميمُ من {@see RealEstateTemplates::presets()}.
 *
 * غير مُتلِفة وقابلةٌ للتكرار:
 *   • القالبُ يُعرَف باسمه ونوعه — إن وُجد لا يُكتب فوقه (قد عدّله المسؤول).
 *   • `is_default` يُمنَح فقط إن لم يكن للنوع قالبٌ افتراضيّ بعد.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('document_templates')) {
            return;
        }

        foreach (RealEstateTemplates::presets() as $preset) {
            $exists = DB::table('document_templates')
                ->where('document_type', $preset['document_type'])
                ->where('name', $preset['name'])
                ->whereNull('branch_id')
                ->exists();

            if ($exists) {
                continue;
            }

            $hasDefault = DB::table('document_templates')
                ->where('document_type', $preset['document_type'])
                ->where('is_default', true)
                ->exists();

            DB::table('document_templates')->insert([
                'name' => $preset['name'],
                'document_type' => $preset['document_type'],
                'template_type' => $preset['template_type'],
                'paper_size' => $preset['paper_size'],
                'width' => $preset['width'],
                'height' => $preset['height'],
                'orientation' => $preset['orientation'],
                'content' => $preset['content'],
                'styles' => $preset['styles'],
                'settings' => json_encode($preset['settings'], JSON_UNESCAPED_UNICODE),
                'is_default' => ! $hasDefault,
                'is_active' => true,
                'user_id' => null,
                'branch_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('document_templates')) {
            return;
        }

        // الجاهزُ غيرُ المعدَّل وحده: ما حرّره المسؤول صار قالبَه هو
        DB::table('document_templates')
            ->whereIn('document_type', RealEstateTemplates::TYPES)
            ->where('template_type', 'ready')
            ->where('name', 'like', '%'.RealEstateTemplates::DESIGN_SUFFIX.'%')
            ->whereColumn('created_at', 'updated_at')
            ->delete();
    }
};
