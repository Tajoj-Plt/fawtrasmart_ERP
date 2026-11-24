<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * وردية القاعدة تصير اختيارية — «كل الورديات».
 *
 * `AttendanceRuleOverrides::forEmployee()` يتخطّى القاعدةَ حين
 * `$rule->shift_id` لا يطابق ورديةَ الموظف، ويُطبّقها حين تكون فارغة. لكنّ
 * العمود كان `NOT NULL` والحقلُ `required` في النموذج — فتعذّرت قاعدةٌ تسري
 * على المنشأة كلِّها، **وموظفٌ بلا ورديةٍ مُسنَدة لم تكن تصله قاعدةٌ البتّة**
 * (ثبت بتجربة: `forEmployee($employee, null)` تُعيد فراغاً دائماً).
 *
 * `down()` تُعيد `NOT NULL`، وقد تسقط إن وُجدت قاعدةٌ بلا وردية — وهو متوقَّع.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('attendance_rules') || ! Schema::hasColumn('attendance_rules', 'shift_id')) {
            return;
        }

        Schema::table('attendance_rules', function (Blueprint $table) {
            $table->unsignedBigInteger('shift_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('attendance_rules') || ! Schema::hasColumn('attendance_rules', 'shift_id')) {
            return;
        }

        Schema::table('attendance_rules', function (Blueprint $table) {
            $table->unsignedBigInteger('shift_id')->nullable(false)->change();
        });
    }
};
