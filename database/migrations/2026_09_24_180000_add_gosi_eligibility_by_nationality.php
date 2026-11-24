<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * خضوعُ الموظف للتأمينات — لا يُطبَّق على الجميع بنسبةٍ واحدة.
 *
 * كانت `SocialInsuranceService` تستقطع 9.75% وتُساهم بـ11.75% عن **كلّ**
 * موظف متى فُعِّلت التأمينات، مواطناً كان أو مقيماً أو زائراً، ولا مفتاحَ
 * على بطاقة الموظف يستثنيه. والمعمولُ به أنّ غيرَ المواطن لا يُستقطع منه
 * شيء وتُساهم المنشأةُ عنه بفرع الأخطار المهنية وحده (2%).
 *
 * • على الموظف: `gosi_subject` (فارغ = حسب حالة المواطنة، 1 = خاضع، 0 = معفى)
 *   ورقمُ الاشتراك وتاريخُ بدء الاشتراك.
 * • على الإعدادات: نسبتا غير المواطن — قيمُ بدءٍ قابلةٌ للتعديل من الشاشة.
 *
 * أعمدةٌ تُضاف ولا يُحذف شيء؛ `down()` يُزيلها وحدها.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('employees')) {
            Schema::table('employees', function (Blueprint $table) {
                if (! Schema::hasColumn('employees', 'gosi_subject')) {
                    $table->tinyInteger('gosi_subject')->nullable()->comment('null=حسب حالة المواطنة، 1=خاضع، 0=معفى');
                }
                if (! Schema::hasColumn('employees', 'gosi_number')) {
                    $table->string('gosi_number', 50)->nullable()->comment('رقم الاشتراك في التأمينات');
                }
                if (! Schema::hasColumn('employees', 'gosi_start_date')) {
                    $table->date('gosi_start_date')->nullable()->comment('تاريخ بدء الاشتراك');
                }
            });
        }

        if (Schema::hasTable('social_insurance_settings')) {
            Schema::table('social_insurance_settings', function (Blueprint $table) {
                if (! Schema::hasColumn('social_insurance_settings', 'resident_employee_rate')) {
                    $table->decimal('resident_employee_rate', 6, 3)->default(0);
                }
                if (! Schema::hasColumn('social_insurance_settings', 'resident_employer_rate')) {
                    $table->decimal('resident_employer_rate', 6, 3)->default(2);
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['employees' => ['gosi_subject', 'gosi_number', 'gosi_start_date'],
            'social_insurance_settings' => ['resident_employee_rate', 'resident_employer_rate']] as $tableName => $columns) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }
            $existing = array_values(array_filter($columns, fn ($c) => Schema::hasColumn($tableName, $c)));
            if ($existing !== []) {
                Schema::table($tableName, fn (Blueprint $table) => $table->dropColumn($existing));
            }
        }
    }
};
