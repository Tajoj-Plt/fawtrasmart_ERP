<?php

use App\Support\SchemaGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('points_transactions', function (Blueprint $table) {
            // إضافة حقول الشهر والسنة لتتبع النقاط الشهرية
            if (! Schema::hasColumn('points_transactions', 'points_month')) {
                $table->unsignedTinyInteger('points_month')->after('expires_at')->comment('شهر المعاملة');
            }
            if (! Schema::hasColumn('points_transactions', 'points_year')) {
                $table->unsignedSmallInteger('points_year')->after('points_month')->comment('سنة المعاملة');
            }
            
            // فهرس مركب للبحث السريع عن نقاط شهر معين
            if (! SchemaGuard::hasIndex('points_transactions', 'idx_employee_monthly_points')) {
                $table->index(['user_type', 'employee_id', 'points_month', 'points_year'], 'idx_employee_monthly_points');
            }
            if (! SchemaGuard::hasIndex('points_transactions', 'idx_client_monthly_points')) {
                $table->index(['user_type', 'client_id', 'points_month', 'points_year'], 'idx_client_monthly_points');
            }
            if (! SchemaGuard::hasIndex('points_transactions', 'idx_monthly_points')) {
                $table->index(['points_month', 'points_year'], 'idx_monthly_points');
            }
        });

        // تحديث السجلات الموجودة بقيم الشهر والسنة من created_at.
        // MONTH()/YEAR() دالّتان في MySQL؛ مقابلُهما في SQLite strftime().
        $isSqlite = \DB::connection()->getDriverName() === 'sqlite';

        $month = $isSqlite ? "CAST(strftime('%m', created_at) AS INTEGER)" : 'MONTH(created_at)';
        $year = $isSqlite ? "CAST(strftime('%Y', created_at) AS INTEGER)" : 'YEAR(created_at)';

        \DB::statement("
            UPDATE points_transactions
            SET points_month = {$month},
                points_year = {$year}
            WHERE points_month IS NULL OR points_year IS NULL
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('points_transactions', function (Blueprint $table) {
            $table->dropIndex('idx_employee_monthly_points');
            $table->dropIndex('idx_client_monthly_points');
            $table->dropIndex('idx_monthly_points');
            $table->dropColumn(['points_month', 'points_year']);
        });
    }
};
