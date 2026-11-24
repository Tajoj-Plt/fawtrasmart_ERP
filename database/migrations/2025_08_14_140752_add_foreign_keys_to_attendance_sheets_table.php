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
        Schema::table('attendance_sheets', function (Blueprint $table) {
            if (! SchemaGuard::hasForeignKey('attendance_sheets', 'attendance_sheets_branch_id_foreign')) {
                $table->foreign(['branch_id'])->references(['id'])->on('branches')->onUpdate('restrict')->onDelete('cascade');
            }
            if (! SchemaGuard::hasForeignKey('attendance_sheets', 'attendance_sheets_department_id_foreign')) {
                $table->foreign(['department_id'])->references(['id'])->on('departments')->onUpdate('restrict')->onDelete('cascade');
            }
            if (! SchemaGuard::hasForeignKey('attendance_sheets', 'attendance_sheets_job_title_id_foreign')) {
                $table->foreign(['job_title_id'])->references(['id'])->on('jop_titles')->onUpdate('restrict')->onDelete('cascade');
            }
            if (! SchemaGuard::hasForeignKey('attendance_sheets', 'attendance_sheets_shift_id_foreign')) {
                $table->foreign(['shift_id'])->references(['id'])->on('shifts')->onUpdate('restrict')->onDelete('cascade');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendance_sheets', function (Blueprint $table) {
            $table->dropForeign('attendance_sheets_branch_id_foreign');
            $table->dropForeign('attendance_sheets_department_id_foreign');
            $table->dropForeign('attendance_sheets_job_title_id_foreign');
            $table->dropForeign('attendance_sheets_shift_id_foreign');
        });
    }
};
