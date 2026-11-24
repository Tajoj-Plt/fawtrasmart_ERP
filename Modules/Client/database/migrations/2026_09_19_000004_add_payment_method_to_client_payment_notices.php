<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * طريقةُ السداد في إشعار البوابة تصير مرجعاً إلى «طرق الدفع».
 *
 * كانت أربع قيمٍ مكتوبةً في الشيفرة (`bank_transfer`…): لا تعرف حسابَ
 * المنشأة البنكي ولا تعليماتِها ولا حدودَ المبلغ، ولا تُترجَم إلى طريقةٍ
 * في سند القبض — فالموظّف يختارها بيده مرّةً أخرى.
 *
 * صارت `payment_method_id` من جدول `payment_methods` نفسِه الذي يقرؤه سندُ
 * القبض، فما يختاره العميل يُعبَّأ في السند عند الاعتماد. و`method` القديمة
 * تبقى للصفوف السابقة — تُقرأ ولا تُكتب.
 */
return new class extends Migration
{
    public function up(): void
    {
        /*
            حارسُ الوجود ليس ترفاً: `MODIFY` أدناه صيغةُ MySQL، فعلى SQLite
            (بيئة التطوير) كان يرمي **بعد** نجاح إضافة العمود — فلا يُسجَّل
            الترحيل ويُعاد تشغيله فيفشل على «عمود مكرّر» إلى الأبد.
        */
        if (! Schema::hasColumn('client_payment_notices', 'payment_method_id')) {
            Schema::table('client_payment_notices', function (Blueprint $table) {
                $table->unsignedBigInteger('payment_method_id')->nullable()->after('amount')->index();
            });
        }

        // الصفوفُ الجديدة تحمل المرجع، فلم يعد النصُّ إلزامياً — بصيغةٍ
        // يفهمها المشغّلان (change() بدل MODIFY الخاصّة بـMySQL)
        Schema::table('client_payment_notices', function (Blueprint $table) {
            $table->string('method', 30)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('client_payment_notices', function (Blueprint $table) {
            $table->dropColumn('payment_method_id');
        });
    }
};
