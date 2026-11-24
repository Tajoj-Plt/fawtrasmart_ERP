<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * البريدُ والدورُ الوظيفي يصيران قابلَين للفراغ — ليحكمهما الإعداد.
 *
 * شاشةُ «إعدادات الموظفين والهيكل التنظيمي» تُتيح إطفاءَ إلزاميّة البريد
 * والدور، والعمودان كانا `NOT NULL`: فإطفاءُ القاعدة في الشاشة وحدَها يعني
 * طلباً يعبر التحقّقَ ثمّ يرتدّ `SQLSTATE[23000] … cannot be null` خاماً في
 * وجه المستخدم.
 *
 * **و`Job_role_id` كان مكسوراً قبل الشاشة أصلاً**: `EmployeeController::store()`
 * يكتب فيه `null` صريحاً لكلّ من لا يملك صلاحية `employee_accounts.roles`
 * («الدورُ يمنح صلاحياتِ الحساب كلَّها — بصلاحيته وحدها»)، وهو عمودٌ
 * `NOT NULL` بلا افتراض. فالكتابةُ تنجح بالصدفة وحدها: على MySQL غير
 * الصارم تُحوَّل إلى صفرٍ — رقمُ دورٍ لا وجود له — وعلى الصارم تسقط. فهذا
 * الترحيل يُصلح ذلك بقدر ما يُمكّن الإعداد.
 *
 * الفرادةُ على البريد تبقى: MySQL تسمح بتكرار `NULL` في الفهرس الفريد، فلا
 * يُقبل بريدان متماثلان ويُقبل موظفان بلا بريد.
 *
 * و`users.email` معه: `store()` يُنشئ حسابَ دخولٍ لكلّ موظفٍ ويكتب فيه
 * `$request->email` بلا شرط. ومن لا بريدَ له لا يستطيع الدخول — وهذا هو
 * المعنى، فالبريدُ هو معرّفُ الدخول، و`allow_system_access` مفتاحٌ مستقلٌّ
 * عنه. ومسالكُ الإرسال محروسةٌ سلفاً بـ`empty($user->email)`.
 *
 * `down()` تُعيد `NOT NULL`. وقد تسقط إن كان في الجدول صفٌّ فارغُ البريد أو
 * الدور — وهو متوقَّع: لا يصحّ أن يُرجِع التراجعُ عمودَ المفتاح إلى
 * `NOT NULL` بأن يلفّق قيمةً لمن لا قيمةَ له.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('employees')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->string('email')->nullable()->change();
                $table->unsignedBigInteger('Job_role_id')->nullable()->change();
            });
        }

        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('email')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('employees')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->string('email')->nullable(false)->change();
                $table->unsignedBigInteger('Job_role_id')->nullable(false)->change();
            });
        }

        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('email')->nullable(false)->change();
            });
        }
    }
};
