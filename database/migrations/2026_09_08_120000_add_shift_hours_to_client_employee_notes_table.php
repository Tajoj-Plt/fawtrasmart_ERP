<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «دوام المسؤول» صار خاصّيةً لمسؤول العميل نفسه، لا حقلاً يُعاد كتابته في
 * كل ملاحظة.
 *
 * كان `shift_start`/`shift_end` عمودين في `client_relations` (الملاحظة)
 * يملؤهما المندوب من جديد في كل زيارة — فلا يمكن أن يُسأل قبل الزيارة
 * «هل لهذا العميل مسؤولٌ ودوامٌ مسجَّلان من قبل؟»، ولا أن يُحرَس تغييرُ
 * الدوام بصلاحية: لا شيء ثابتٌ ليُغيَّر أصلاً. مساحة عمل الزيارة تسأل
 * هذا السؤال الآن كخطوةٍ إلزامية (راجع `VisitWorkspaceController::show()`)،
 * فالدوام يُسجَّل مرّةً على المسؤول ويُقرأ في كل زيارةٍ تالية.
 *
 * أعمدة الملاحظة تبقى كما هي: `ClientNoteController::addnotes()` ما زال
 * يطلبهما ويكتبهما، لكنّه يستقبلهما الآن من دوام المسؤول المحفوظ هنا.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_employee_notes', function (Blueprint $table) {
            if (! Schema::hasColumn('client_employee_notes', 'shift_start')) {
                $table->string('shift_start', 5)->nullable()->after('employee_phone');
            }
            if (! Schema::hasColumn('client_employee_notes', 'shift_end')) {
                $table->string('shift_end', 5)->nullable()->after('shift_start');
            }
        });
    }

    public function down(): void
    {
        Schema::table('client_employee_notes', function (Blueprint $table) {
            foreach (['shift_start', 'shift_end'] as $column) {
                if (Schema::hasColumn('client_employee_notes', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
