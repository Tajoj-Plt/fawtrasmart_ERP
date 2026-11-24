<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * الموظفون المؤهَّلون لأداء كل خدمة.
 *
 * بلا هذا الجدول تكون كلُّ خدمةٍ قابلةً للإسناد إلى كل موظف — فيُحجَز
 * محاسبٌ لخدمةِ صيانةٍ لا يؤدّيها، ولا يُكتشَف الخطأ إلا يومَ الموعد.
 *
 * **وجدولٌ فارغٌ لخدمةٍ يعني «كلُّ الموظفين»** لا «لا أحد»: المنشأةُ التي
 * لا تفرّق بين موظفيها يجب ألّا تُجبَر على ملء الجدول لكل خدمة قبل أن
 * تستطيع الحجز. القاعدةُ نفسُها في `ReservationAvailabilityService`.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('reservation_service_employee')) {
            return;
        }

        Schema::create('reservation_service_employee', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('reservation_service_id');
            $table->unsignedBigInteger('employee_id');
            $table->timestamps();

            $table->unique(['reservation_service_id', 'employee_id'], 'reservation_service_employee_unique');
            $table->index('employee_id', 'reservation_service_employee_employee_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservation_service_employee');
    }
};
