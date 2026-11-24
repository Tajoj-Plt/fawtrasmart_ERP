<?php

use App\Support\SchemaGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * شاشة إضافة/تعديل الموظف كانت تختار "الدور الوظيفي" من جدول job_roles، الذي
 * يتزامن بالاسم (role_name) مع جدول roles (Spatie) بشكل هش عبر أكواد منفصلة.
 * هذه الهجرة تعيد توجيه employees.Job_role_id ليشير مباشرة إلى roles(id)
 * بدلاً من job_roles(id)، فيصبح جدول roles مصدر الحقيقة الوحيد لدور الموظف.
 */
return new class extends Migration
{
    public function up(): void
    {
        // SQLite لا يدعم إسقاط قيدٍ مرجعيٍّ بالاسم؛ وقاعدةُ الاختبارات تُبنى من
        // هجرات الإنشاء مباشرةً فلا قيدَ قديماً يُعاد توجيهه.
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        // خريطة job_roles.id => roles.id المطابق بالاسم (تُنشئ الدور في roles لو ناقصاً)
        $jobRoles = DB::table('job_roles')->select('id', 'role_name')->get();
        $map = [];
        foreach ($jobRoles as $jobRole) {
            $roleId = DB::table('roles')->where('name', $jobRole->role_name)->value('id');
            if (! $roleId) {
                $roleId = DB::table('roles')->insertGetId([
                    'name' => $jobRole->role_name,
                    'guard_name' => 'web',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            $map[$jobRole->id] = $roleId;
        }

        /*
            القيد القديم يمنع أي قيمة لا تطابق job_roles(id) — بما فيها
            القيم الجديدة القادمة من roles(id) التي على وشك أن تُكتب أدناه.
            يجب إسقاطه أولاً، لا بعد إعادة التوجيه.
        */
        Schema::table('employees', function (Blueprint $table) {
            $table->dropForeign('employees_job_role_id_foreign');
        });

        foreach ($map as $oldJobRoleId => $newRoleId) {
            DB::table('employees')->where('Job_role_id', $oldJobRoleId)->update(['Job_role_id' => $newRoleId]);
        }

        Schema::table('employees', function (Blueprint $table) {
            if (! SchemaGuard::hasForeignKey('employees', 'employees_Job_role_id_foreign')) {
                $table->foreign('Job_role_id')->references('id')->on('roles')->onUpdate('restrict')->onDelete('cascade');
            }
        });
    }

    public function down(): void
    {
        // SQLite لا يدعم إسقاط قيدٍ مرجعيٍّ بالاسم؛ وقاعدةُ الاختبارات تُبنى من
        // هجرات الإنشاء مباشرةً فلا قيدَ قديماً يُعاد توجيهه.
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('employees', function (Blueprint $table) {
            $table->dropForeign('employees_job_role_id_foreign');
        });

        // إرجاع القيم إلى job_roles.id المطابق بالاسم حيثما أمكن
        $roles = DB::table('roles')->select('id', 'name')->get();
        foreach ($roles as $role) {
            $jobRoleId = DB::table('job_roles')->where('role_name', $role->name)->value('id');
            if ($jobRoleId) {
                DB::table('employees')->where('Job_role_id', $role->id)->update(['Job_role_id' => $jobRoleId]);
            }
        }

        // أي موظف صار دوره Role جديداً بلا مقابل في job_roles (أُنشئ بعد هذه الهجرة) يُعاد توجيهه لأول دور وظيفي متاح كي لا يفشل الـ FK القديم
        $validJobRoleIds = DB::table('job_roles')->pluck('id')->all();
        $fallbackJobRoleId = DB::table('job_roles')->orderBy('id')->value('id');
        if ($fallbackJobRoleId) {
            DB::table('employees')->whereNotIn('Job_role_id', $validJobRoleIds)->update(['Job_role_id' => $fallbackJobRoleId]);
        }

        Schema::table('employees', function (Blueprint $table) {
            $table->foreign('Job_role_id')->references('id')->on('job_roles')->onUpdate('restrict')->onDelete('cascade');
        });
    }
};
