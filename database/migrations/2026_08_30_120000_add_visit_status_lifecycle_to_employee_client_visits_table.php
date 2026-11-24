<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * دورة حياة الزيارة الكاملة: planned/started/completed/missed/cancelled —
     * إضافيّة بجانب عمود `status` القديم (active/unactive) لا بديلة عنه، لأن
     * `status` تعتمد عليه أنظمةٌ خارج الموديول (نقاط الأداء، وخمسة مواضع في
     * Sales/Account عبر `EmployeeClientVisit::markCompletedFor()`).
     */
    public function up(): void
    {
        Schema::table('employee_client_visits', function (Blueprint $table) {
            if (! Schema::hasColumn('employee_client_visits', 'visit_status')) {
                $table->enum('visit_status', ['planned', 'started', 'completed', 'missed', 'cancelled'])
                    ->default('planned')
                    ->after('status');
            }
            if (! Schema::hasColumn('employee_client_visits', 'started_at')) {
                $table->timestamp('started_at')->nullable()->after('visit_status');
            }
            if (! Schema::hasColumn('employee_client_visits', 'completed_at')) {
                $table->timestamp('completed_at')->nullable()->after('started_at');
            }
            if (! Schema::hasColumn('employee_client_visits', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable()->after('completed_at');
            }
            if (! Schema::hasColumn('employee_client_visits', 'cancel_reason')) {
                $table->string('cancel_reason')->nullable()->after('cancelled_at');
            }
            if (! Schema::hasColumn('employee_client_visits', 'missed_reason')) {
                $table->string('missed_reason')->nullable()->after('cancel_reason');
            }
            if (! Schema::hasColumn('employee_client_visits', 'actual_duration_minutes')) {
                $table->unsignedInteger('actual_duration_minutes')->nullable()->after('missed_reason');
            }
        });

        // تهيئة الصفوف القديمة: status='active' (مكتملة فعلياً) → completed.
        // status='unactive' تبقى القيمة الافتراضية 'planned' — بلا استنتاج
        // رجعي لحالة "فائتة" حتى لا تُنتج بياناتٍ وهمية.
        DB::table('employee_client_visits')
            ->where('status', 'active')
            ->update([
                'visit_status' => 'completed',
                'completed_at' => DB::raw('updated_at'),
            ]);
    }

    public function down(): void
    {
        Schema::table('employee_client_visits', function (Blueprint $table) {
            $table->dropColumn([
                'visit_status',
                'started_at',
                'completed_at',
                'cancelled_at',
                'cancel_reason',
                'missed_reason',
                'actual_duration_minutes',
            ]);
        });
    }
};
