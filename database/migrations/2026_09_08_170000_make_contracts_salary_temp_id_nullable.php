<?php

use App\Support\SchemaGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
    قالبُ الراتب في العقد اختياريٌّ فعلاً لا دعوى.

    كلُّ ما حول العمود مكتوبٌ على أنّه اختياري:

      • شاشةُ الإنشاء تقول فوق القائمة حرفياً «قالب الرواتب (اختياري)»،
        وأولُ خيارٍ فيها «بدون قالب - إضافة البنود يدوياً» بقيمةٍ فارغة.
      • و`ContractRequest` لا يطلبه.
      • و`store()` تُسند `$request->salary_temp_id ?? null`.
      • والعقدُ يقبل بنودَ استحقاقٍ واستقطاعٍ تُكتب يدويّاً في الشاشة
        نفسِها، وهي البديلُ المقصود من «بدون قالب».

    والعمودُ وحده `NOT NULL` بلا قيمةٍ افتراضية، فيُردّ الحفظُ بخطأ SQL
    خام: «Column 'salary_temp_id' cannot be null» — أي أنّ الخيارَ الأول
    في القائمة كان يُسقِط الحفظَ كلَّه.

    ولا خطرَ في التوسيع: تحويلُ عمودٍ من `NOT NULL` إلى `NULL` يقبل ما كان
    مقبولاً وزيادة، فلا صفَّ قائمٌ يتأثّر.

    ── والمفتاحُ الأجنبي يتبع ───────────────────────────────────────────

    كان `ON DELETE CASCADE`: حذفُ قالبِ راتبٍ واحدٍ يمحو معه **عقودَ**
    الموظفين المرتبطة به، ولا حارسَ في `SalaryTemplatesController::destroy`
    يمنع ذلك. وهو معنًى لا يريده أحد: القالبُ وسيلةُ تعبئةٍ للبنود، لا
    مالكُ العقد. وما دام العمودُ صار يقبل الفراغَ فالتصرّفُ الصحيح
    `SET NULL` — يبقى العقدُ ويسقط القالبُ عنه، كحال العقد المُنشأ
    «بدون قالب» أصلاً.
*/
return new class extends Migration
{
    public function up(): void
    {
        $this->dropForeignKey();

        Schema::table('contracts', function (Blueprint $table) {
            $table->unsignedBigInteger('salary_temp_id')->nullable()->change();
        });

        $this->addForeignKey('set null');
    }

    public function down(): void
    {
        $this->dropForeignKey();

        /*
            التضييقُ لا يجري إلّا إذا خلا الجدولُ من الفراغ: العقودُ
            المُنشأةُ «بدون قالب» بعد الترقية لا قالبَ لها يُملأ به العمود،
            وأيُّ قيمةٍ مخترَعةٍ إمّا يردّها المفتاحُ الأجنبي أو تنسب العقدَ
            إلى قالبٍ لا يخصّه. فيبقى العمودُ واسعاً ويُستعاد المفتاحُ وحده.
        */
        $hasNulls = DB::table('contracts')->whereNull('salary_temp_id')->exists();

        if (! $hasNulls) {
            Schema::table('contracts', function (Blueprint $table) {
                $table->unsignedBigInteger('salary_temp_id')->nullable(false)->change();
            });
        }

        $this->addForeignKey($hasNulls ? 'set null' : 'cascade');
    }

    /**
     * حذفُ المفتاح الأجنبي إن وُجد.
     *
     * SQLite لا تسمّي قيودَها الأجنبية ولا تدعم حذفَها، و`SchemaGuard`
     * تُعيد `false` عليها — فلا شيءَ يجري هناك أصلاً.
     */
    private function dropForeignKey(): void
    {
        if (! SchemaGuard::hasForeignKey('contracts', 'contracts_salary_temp_id_foreign')) {
            return;
        }

        Schema::table('contracts', function (Blueprint $table) {
            $table->dropForeign('contracts_salary_temp_id_foreign');
        });
    }

    /** إعادةُ المفتاح الأجنبي بالتصرّف المطلوب عند الحذف. */
    private function addForeignKey(string $onDelete): void
    {
        if (! $this->onMysql() || SchemaGuard::hasForeignKey('contracts', 'contracts_salary_temp_id_foreign')) {
            return;
        }

        Schema::table('contracts', function (Blueprint $table) use ($onDelete) {
            $table->foreign(['salary_temp_id'])
                ->references(['id'])
                ->on('salary_template')
                ->onUpdate('restrict')
                ->onDelete($onDelete);
        });
    }

    private function onMysql(): bool
    {
        return in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true);
    }
};
