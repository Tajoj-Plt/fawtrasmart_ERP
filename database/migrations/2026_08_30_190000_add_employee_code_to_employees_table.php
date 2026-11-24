<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * رقمُ الموظف — معرّفٌ مقروءٌ ثابت بدل `id`.
 *
 * **ما كان**
 *
 * لا عمودَ للرقم أصلاً. فالشاشاتُ والتقاريرُ والمطبوعاتُ تعرض `id` الخام،
 * وهو مفتاحُ قاعدةٍ لا رقمُ موظف: يتغيّر بين بيئةٍ وأخرى، ولا بادئةَ له
 * ولا حشوَ ولا تصفيرَ دوري، ولا يُرجَع إليه في مراسلةٍ ولا كشفِ رواتب.
 *
 * وبنيةُ الترقيم قائمةٌ في النظام منذ زمن — `DocumentSections` و
 * `DocumentNumberService` و«شاشة الترقيم المتسلسل» — والعقودُ تستعملها
 * بالفعل (`next_document_number('contracts')`). الموظفون وحدهم كانوا
 * خارجها.
 *
 * **العمود**
 *
 *   • `nullable`: الموظفون القائمون بلا رقم، ولا يُخترع لهم واحد هنا —
 *     الترقيمُ يبدأ من أوّل موظفٍ يُضاف بعد هذه المهاجرة. وملؤُهم بأثرٍ
 *     رجعي قرارُ إدارةٍ لا مهاجرة: قد تكون لهم أرقامٌ في نظامٍ سابق.
 *   • `unique`: الرقم يميّز، وفهرسُه الفريد هو ما يجعل
 *     `DocumentNumberService` يتخطّى المحجوز فعلاً بدل أن يسلّم رقماً
 *     يرفضه الحفظ. و`null` لا يتصادم مع `null` في MySQL، فالقائمون
 *     يمرّون جميعاً.
 *   • بعد `email` مباشرةً: أوّلُ ما يُقرأ في البطاقة.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('employees', 'employee_code')) {
            return;
        }

        Schema::table('employees', function (Blueprint $table) {
            if (! Schema::hasColumn('employees', 'employee_code')) {
                $table->string('employee_code', 64)->nullable()->unique()->after('email');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('employees', 'employee_code')) {
            return;
        }

        Schema::table('employees', function (Blueprint $table) {
            $table->dropUnique(['employee_code']);
            $table->dropColumn('employee_code');
        });
    }
};
