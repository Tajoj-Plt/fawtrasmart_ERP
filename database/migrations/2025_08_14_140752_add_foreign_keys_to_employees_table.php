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
        Schema::table('employees', function (Blueprint $table) {
            if (! SchemaGuard::hasForeignKey('employees', 'employees_branch_id_foreign')) {
                $table->foreign(['branch_id'])->references(['id'])->on('branches')->onUpdate('restrict')->onDelete('cascade');
            }
            if (! SchemaGuard::hasForeignKey('employees', 'employees_department_id_foreign')) {
                $table->foreign(['department_id'])->references(['id'])->on('departments')->onUpdate('restrict')->onDelete('cascade');
            }
            if (! SchemaGuard::hasForeignKey('employees', 'employees_direct_manager_id_foreign')) {
                $table->foreign(['direct_manager_id'])->references(['id'])->on('employees')->onUpdate('restrict')->onDelete('cascade');
            }
            if (! SchemaGuard::hasForeignKey('employees', 'employees_job_level_id_foreign')) {
                $table->foreign(['job_level_id'])->references(['id'])->on('functional_levels')->onUpdate('restrict')->onDelete('cascade');
            }
            if (! SchemaGuard::hasForeignKey('employees', 'employees_Job_role_id_foreign')) {
                $table->foreign(['Job_role_id'])->references(['id'])->on('job_roles')->onUpdate('restrict')->onDelete('cascade');
            }
            if (! SchemaGuard::hasForeignKey('employees', 'employees_job_title_id_foreign')) {
                $table->foreign(['job_title_id'])->references(['id'])->on('jop_titles')->onUpdate('restrict')->onDelete('cascade');
            }
            if (! SchemaGuard::hasForeignKey('employees', 'employees_job_type_id_foreign')) {
                $table->foreign(['job_type_id'])->references(['id'])->on('types_jobs')->onUpdate('restrict')->onDelete('cascade');
            }
            if (! SchemaGuard::hasForeignKey('employees', 'employees_shift_id_foreign')) {
                $table->foreign(['shift_id'])->references(['id'])->on('shifts')->onUpdate('restrict')->onDelete('cascade');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropForeign('employees_branch_id_foreign');
            $table->dropForeign('employees_department_id_foreign');
            $table->dropForeign('employees_direct_manager_id_foreign');
            $table->dropForeign('employees_job_level_id_foreign');
            $table->dropForeign('employees_job_role_id_foreign');
            $table->dropForeign('employees_job_title_id_foreign');
            $table->dropForeign('employees_job_type_id_foreign');
            $table->dropForeign('employees_shift_id_foreign');
        });
    }
};
