<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * أجهزةُ الفرع المسموحة لكل موظف.
 *
 * كان أيُّ جهازٍ مربوطٍ يقبل بطاقةَ أيِّ موظف — موظفُ فرع الرياض يمسح
 * بطاقته على جهاز فرع جدة فيُقبل. الآن: من رُبط بأجهزةٍ لا يُقبل إلا عليها،
 * ومن لا صفوفَ له يُقبل على أيِّ جهاز كما كان (لا يتغيّر شيءٌ لمن لم يُضبط).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('attendance_kiosk_employee')) {
            return;
        }

        Schema::create('attendance_kiosk_employee', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('kiosk_id')->index();
            $table->unsignedBigInteger('employee_id')->index();
            $table->timestamps();

            $table->unique(['kiosk_id', 'employee_id']);
            $table->foreign('kiosk_id')->references('id')->on('attendance_kiosks')->cascadeOnDelete();
            $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_kiosk_employee');
    }
};
