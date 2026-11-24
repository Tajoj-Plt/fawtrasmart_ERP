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
        Schema::table('attendance_days', function (Blueprint $table) {
            if (! SchemaGuard::hasForeignKey('attendance_days', 'attendance_days_employee_id_foreign')) {
                $table->foreign(['employee_id'])->references(['id'])->on('employees')->onUpdate('restrict')->onDelete('cascade');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendance_days', function (Blueprint $table) {
            $table->dropForeign('attendance_days_employee_id_foreign');
        });
    }
};
