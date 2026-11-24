<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `users.phone` يصير قابلاً للفراغ — تتمّةٌ لما بدأه ترحيلُ البريد والدور.
 *
 * شاشةُ «إعدادات الموظفين» تُتيح إطفاءَ إلزاميّة رقمَي الجوّال والهاتف،
 * و`EmployeeController::store()` يُنشئ لكلّ موظفٍ حسابَ دخولٍ ويكتب فيه
 * `'phone' => $request->phone_number` **بلا شرط**. والعمودُ `NOT NULL` بلا
 * افتراض، فالنتيجةُ أنّ إطفاءَ القاعدة في الشاشة يُمرّر الطلبَ من التحقّق
 * ثمّ يرتدّ في وجه المستخدم نصّاً خاماً:
 *
 *     SQLSTATE[23000] … 1048 Column 'phone' cannot be null
 *
 * وهذا عينُ ما شُكي منه: «أعطّلتُه من الإعدادات وما زال يطلبُه».
 *
 * والهاتفُ ليس معرّفَ دخول: الدخولُ بالبريد، و`RegisteredUserController`
 * (تسجيلُ العموم) مسلكٌ آخرُ يفرض الهاتفَ بتحقّقه الخاصّ ولا يمسّه هذا.
 * ومسلكُ «تفعيل الدخول» في `EmployeeController` يحرس نفسَه سلفاً: يرفض
 * الإنشاءَ بلا رقمٍ لأنّ الرقمَ هو كلمةُ المرور الأولية هناك.
 *
 * `down()` تُعيد `NOT NULL`، وقد تسقط إن كان في الجدول صفٌّ بلا هاتف — وهو
 * متوقَّع: لا يصحّ أن يلفّق التراجعُ رقماً لمن لا رقمَ له.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'phone')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('phone')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'phone')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('phone')->nullable(false)->change();
        });
    }
};
