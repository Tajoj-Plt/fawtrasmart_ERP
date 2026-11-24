<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * منشئُ العقد — `contracts.created_by`.
 *
 * صلاحيةُ «تعديل وحذف عقوده هو» (`salaries_contracts_edit_delete_own`)
 * وفلترُ «أضيف بواسطة» في قائمة العقود وعلاقةُ `Contract::creator()` كلُّها
 * تقرأ هذا العمود — ولم يكن موجوداً: فالفلترُ يرمي خطأ SQL، والصلاحيةُ
 * «الخاصّة» كانت تفتح تعديلَ كلّ العقود.
 *
 * يُملأ للعقود القائمة من سجلّ النشاط («تم انشاء عقد») حيث وُجد.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('contracts') || Schema::hasColumn('contracts', 'created_by')) {
            return;
        }

        Schema::table('contracts', function (Blueprint $table) {
            $table->unsignedBigInteger('created_by')->nullable()->after('attachments');
            $table->index('created_by');
        });

        if (Schema::hasTable('logs') && Schema::hasColumn('logs', 'type_id') && Schema::hasColumn('logs', 'created_by')) {
            $rows = DB::table('logs')
                ->where('type', 'salary_contracts_log')
                ->where('description', 'like', 'تم انشاء عقد%')
                ->whereNotNull('type_id')
                ->whereNotNull('created_by')
                ->orderBy('id')
                ->get(['type_id', 'created_by']);

            foreach ($rows as $row) {
                DB::table('contracts')
                    ->where('id', $row->type_id)
                    ->whereNull('created_by')
                    ->update(['created_by' => $row->created_by]);
            }
        }
    }

    public function down(): void
    {
        // تقاعدٌ لا إسقاط: العمودُ يبقى — انظر اصطلاح «Retire, do not drop».
    }
};
