<?php

use App\Support\SchemaGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * نقل مركز التكلفة من جدول القيود إلى جدول تفاصيل القيود
     */
    public function up(): void
    {
        // 1. إضافة عمود cost_center_id إلى جدول تفاصيل القيود
        Schema::table('journal_entry_details', function (Blueprint $table) {
            if (! Schema::hasColumn('journal_entry_details', 'cost_center_id')) {
                $table->unsignedBigInteger('cost_center_id')->nullable()->after('credit');
            }
            if (! SchemaGuard::hasForeignKey('journal_entry_details', 'journal_entry_details_cost_center_id_foreign')) {
                $table->foreign('cost_center_id')->references('id')->on('cost_centers')->onDelete('set null');
            }
        });

        // 2. نقل البيانات الموجودة من journal_entries إلى journal_entry_details
        // كل سطر تفاصيل سيأخذ مركز التكلفة من القيد الرئيسي
        // صيغة محمولة: `UPDATE ... JOIN` خاصّة بـMySQL وحدها، وكانت تُسقط
        // كل ترحيلات الاختبارات على SQLite بخطأ نحوي عند «jed»
        DB::statement('
            UPDATE journal_entry_details
            SET cost_center_id = (
                SELECT je.cost_center_id
                FROM journal_entries je
                WHERE je.id = journal_entry_details.journal_entry_id
            )
            WHERE journal_entry_id IN (
                SELECT id FROM journal_entries WHERE cost_center_id IS NOT NULL
            )
        ');

        // 3. حذف عمود cost_center_id من جدول القيود الرئيسية
        Schema::table('journal_entries', function (Blueprint $table) {
            // القيدُ أولاً ثم فهرسُه ثم العمود — بهذا الترتيب لا غيره.
            //
            // الفهرسُ `journal_entries_cost_center_id_foreign` يخدم مفتاحاً
            // أجنبياً بالاسم نفسه في القواعد التي وُلدت من هجرة الإنشاء،
            // وMySQL ترفض إسقاطَه ما دام القيدُ قائماً (الخطأ 1553) وترفض
            // إسقاطَ العمود كذلك (1828). التركيباتُ الجديدة لا تملك القيد
            // فيمرُّ الحارسُ دون عمل.
            if (SchemaGuard::hasForeignKey('journal_entries', 'journal_entries_cost_center_id_foreign')) {
                $table->dropForeign('journal_entries_cost_center_id_foreign');
            }

            // حذف الـ index إن وجد
            if (SchemaGuard::hasIndex('journal_entries', 'journal_entries_cost_center_id_foreign')) {
                $table->dropIndex('journal_entries_cost_center_id_foreign');
            }
            if (Schema::hasColumn('journal_entries', 'cost_center_id')) {
                $table->dropColumn('cost_center_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 1. إعادة إضافة عمود cost_center_id إلى جدول القيود الرئيسية
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->unsignedBigInteger('cost_center_id')->nullable()->after('invoice_id');
            $table->foreign('cost_center_id')->references('id')->on('cost_centers')->onDelete('set null');
        });

        // 2. نقل البيانات من أول سطر تفاصيل لكل قيد (أو أي سطر يحتوي على cost_center_id)
        // بلا كنية للجدول في SET — SQLite لا يقبلها
        DB::statement('
            UPDATE journal_entries
            SET cost_center_id = (
                SELECT jed.cost_center_id
                FROM journal_entry_details jed
                WHERE jed.journal_entry_id = journal_entries.id
                AND jed.cost_center_id IS NOT NULL
                LIMIT 1
            )
        ');

        // 3. حذف عمود cost_center_id من جدول تفاصيل القيود
        Schema::table('journal_entry_details', function (Blueprint $table) {
            $table->dropForeign(['cost_center_id']);
            $table->dropColumn('cost_center_id');
        });
    }
};
