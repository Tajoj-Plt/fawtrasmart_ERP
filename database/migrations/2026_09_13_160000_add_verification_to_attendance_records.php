<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * جلساتُ الحضور: بيّنةٌ لا وصف.
 *
 * `attendance_records.source` كان قائمةً منسدلة فيها أربعةُ خيارات —
 * يدوي، جهاز، باركود، موبايل — يختار منها المُدخِلُ ما شاء. لا باركودَ
 * يُمسَح، ولا موقعَ يُقاس، ولا جهازَ يُسأل. فـ«باركود» كانت **كلمةً
 * تُكتب في عمود**، والجلسةُ المُدخَلة يدوياً تُصبح «مسحاً موثَّقاً» بتبديل
 * خيارٍ في قائمة.
 *
 * وخياران منها — الجهاز والموبايل — لا يقابلهما في هذا المسار شيءٌ
 * أصلاً: لا تكامل جهازٍ يمرّ من هنا، ولا تطبيقَ جوّالٍ يكتب في هذه
 * الشاشة. وجودُهما يُغري بوصفٍ لا سند له.
 *
 * فالأعمدةُ هنا هي البيّنة: إحداثيات المسح ودقّتها، والباركود الذي
 * قُرِئ، وسببُ الاستثناء في المسار اليدوي.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            if (! Schema::hasColumn('attendance_records', 'latitude')) {
                $table->decimal('latitude', 10, 7)->nullable()->after('source');
            }
            if (! Schema::hasColumn('attendance_records', 'longitude')) {
                $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            }
            if (! Schema::hasColumn('attendance_records', 'accuracy_meters')) {
                $table->unsignedInteger('accuracy_meters')->nullable()->after('longitude');
            }
            if (! Schema::hasColumn('attendance_records', 'scanned_barcode')) {
                $table->string('scanned_barcode', 64)->nullable()->after('accuracy_meters')
                    ->comment('الباركود الذي قُرِئ — بيّنةُ أن المسح وقع');
            }
            if (! Schema::hasColumn('attendance_records', 'entry_note')) {
                $table->string('entry_note', 255)->nullable()->after('scanned_barcode')
                    ->comment('سببُ الإدخال اليدوي — إلزاميٌّ في المسار الاستثنائي');
            }
        });

        /*
            تطبيعُ ما سبق: صفوفُ «device» و«mobile» — إن وُجدت — أُدخلت
            يدوياً من هذه الشاشة نفسِها، فوصفُها الصادق «يدوي». وصفوفُ
            «barcode» القديمة بلا `scanned_barcode` كذلك: لا بيّنةَ لها.
        */
        if (Schema::hasColumn('attendance_records', 'source')) {
            DB::table('attendance_records')
                ->whereIn('source', ['device', 'mobile'])
                ->update(['source' => 'manual', 'entry_note' => 'وصفٌ سابقٌ بلا بيّنة — طُبِّع إلى يدوي']);

            DB::table('attendance_records')
                ->where('source', 'barcode')
                ->whereNull('scanned_barcode')
                ->update(['source' => 'manual', 'entry_note' => 'وُصف باركوداً بلا باركود — طُبِّع إلى يدوي']);
        }
    }

    public function down(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            foreach (['latitude', 'longitude', 'accuracy_meters', 'scanned_barcode', 'entry_note'] as $column) {
                if (Schema::hasColumn('attendance_records', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
