<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * وثائقُ الموظف الرسمية — بأرقامها وتواريخ انتهائها.
 *
 * **ما كان**
 *
 * `employee_attachments` دلوُ ملفّاتٍ عام: `label` و`file_path` و
 * `original_name` و`mime_type` — **بلا نوعٍ ولا رقمٍ ولا تاريخِ انتهاء**.
 * فتُرفع صورةُ الإقامة بين الملفّات، ولا يعرف النظام أنها إقامة، ولا متى
 * تنتهي، ولا يُنبّه أحداً.
 *
 * ونتيجةُ ذلك أنّ `hr:document-expiry-alerts` — المسجَّلة في
 * `CronRegistry` والمبذورة في `cron_jobs` — لا بيانات لها أصلاً، فبقيت
 * معطَّلةً موسومةً «(غير مطبَّقة بعد)».
 *
 * **ولماذا جدولٌ مستقلٌّ لا توسيعُ المرفقات؟**
 *
 * لأنهما شيئان: المرفقُ ملفٌّ يُرفع مرّةً مع بطاقة الموظف ولا عمرَ له،
 * والوثيقةُ سجلٌّ يُنشأ ويُجدَّد ويُنبَّه عليه. وتوسيعُ المرفقات يعني ستّةَ
 * أعمدةٍ فارغةٍ في تسعين بالمئة من صفوفه، وفهرسَ انتهاءٍ يمرّ على ما لا
 * ينتهي.
 *
 * **الأعمدة**
 *
 *   • `type` من [\App\Enums\EmployeeDocumentType].
 *   • `title` للنوع `other` ولمن أراد تسميةً أدقّ.
 *   • `number` رقمُ الوثيقة — يُبحث به.
 *   • `issue_date` / `expiry_date` — والثاني `nullable` لما لا ينتهي.
 *   • `issuing_authority` الجهةُ المُصدِرة.
 *   • ملفٌّ اختياري بنمط `employee_attachments` نفسِه.
 *
 * **الفهارس**: `(employee_id, type)` لبطاقة الموظف، و`expiry_date` وحده
 * لأن مهمّة التنبيه تمسح الجدول كلَّه بتاريخٍ لا بموظف.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('employee_documents')) {
            return;
        }

        Schema::create('employee_documents', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('employee_id');

            $table->string('type', 40)->default('other');
            $table->string('title')->nullable();
            $table->string('number', 100)->nullable();

            $table->date('issue_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->string('issuing_authority')->nullable();

            $table->string('file_path')->nullable();
            $table->string('original_name')->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();

            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'type']);
            $table->index('expiry_date');

            /*
                `cascadeOnDelete` كبقيّة علاقات الموظف — ولا يقع فعلاً بعد
                أن صارت الشاشةُ تؤرشف لا تحذف (`SoftDeletes`). يبقى للحذف
                من القاعدة مباشرةً: وثيقةٌ بلا صاحبها لا معنى لها.
            */
            $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_documents');
    }
};
