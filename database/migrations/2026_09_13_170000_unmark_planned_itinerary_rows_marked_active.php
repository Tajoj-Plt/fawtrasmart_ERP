<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * صفوفُ خطّ سيرٍ لم تُزَر بعد لكنّ عمودها القديم `status = active`.
 *
 * كتبها «سأعود لهذا العميل اليوم» (`VisitWorkspaceController::createReturnItineraryRow`)
 * بـ`active` وهي `planned`، وشاشاتُ خطّ السير كانت تعدّ `active` مكتملةً وحده —
 * فظهر العميلُ «مكتملاً» في يومٍ لم يُزَر فيه، وإن أُجِّل الصفُّ حمل ذلك ليومٍ آخر.
 *
 * بياناتٌ لا بنية: لا حذف. يُصحَّح ما لم يبدأ ولم يُكمَل فقط.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('employee_client_visits') || ! Schema::hasColumn('employee_client_visits', 'visit_status')) {
            return;
        }

        DB::table('employee_client_visits')
            ->where('status', 'active')
            ->where('visit_status', 'planned')
            ->whereNull('started_at')
            ->whereNull('completed_at')
            ->update(['status' => 'unactive']);
    }

    public function down(): void
    {
        // لا رجوع: القيمةُ السابقة كانت خطأً، ولا يُعرف أيُّ الصفوف كانت كذلك
    }
};
