<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * وقت العودة المتوقَّع لصفّ خطّ السير.
 *
 * «سأعود لهذا العميل اليوم» (`VisitWorkspaceController::scheduleReturnVisit()`)
 * ينشئ صفَّ خطّ سيرٍ ثانياً لليوم نفسه، ويحتاج أن يحمل ساعةَ العودة ليذكّر
 * المندوب. `visit_date` لا يصلح لها: النموذج يصبّه `date` لا `datetime`،
 * فالجزء الزمني يُقصّ بصمت — تُكتب 18:30 وتُقرأ 00:00.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_client_visits', function (Blueprint $table) {
            if (! Schema::hasColumn('employee_client_visits', 'planned_time')) {
                $table->time('planned_time')->nullable()->after('visit_date');
            }
        });
    }

    public function down(): void
    {
        Schema::table('employee_client_visits', function (Blueprint $table) {
            if (Schema::hasColumn('employee_client_visits', 'planned_time')) {
                $table->dropColumn('planned_time');
            }
        });
    }
};
