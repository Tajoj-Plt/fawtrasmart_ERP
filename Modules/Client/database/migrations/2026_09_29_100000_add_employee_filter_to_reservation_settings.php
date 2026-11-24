<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «من يُحجز له؟» — قصرُ موظفي الحجوزات على قسمٍ أو مسمّى وظيفي.
 *
 *   employee_filter            all | department | job_title
 *   employee_department_ids    أقسامٌ من `departments`
 *   employee_job_title_ids     مسمّياتٌ من `jop_titles`
 *
 * يقرؤه `ReservationAvailabilityService::eligibleEmployees()` — البابُ الذي
 * تمرّ منه قائمةُ النموذج والتقويمُ وإعادةُ الجدولة والبوّابة — ويُفرض عند
 * الحفظ في `ReservationBookingService::assertEmployeeEligible()`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservation_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('reservation_settings', 'employee_filter')) {
                $table->string('employee_filter', 20)->default('all')->after('max_daily_reservations');
            }
            if (! Schema::hasColumn('reservation_settings', 'employee_department_ids')) {
                $table->json('employee_department_ids')->nullable()->after('employee_filter');
            }
            if (! Schema::hasColumn('reservation_settings', 'employee_job_title_ids')) {
                $table->json('employee_job_title_ids')->nullable()->after('employee_department_ids');
            }
        });
    }

    public function down(): void
    {
        Schema::table('reservation_settings', function (Blueprint $table) {
            foreach (['employee_job_title_ids', 'employee_department_ids', 'employee_filter'] as $column) {
                if (Schema::hasColumn('reservation_settings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
