<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('attendance_settings', 'early_leave_deduction_amount')) {
                $table->decimal('early_leave_deduction_amount', 10, 2)->default(0)
                    ->after('absence_deduction_amount')
                    ->comment('قيمة خصم الانصراف المبكر');
            }
        });
    }

    public function down(): void
    {
        Schema::table('attendance_settings', function (Blueprint $table) {
            if (Schema::hasColumn('attendance_settings', 'early_leave_deduction_amount')) {
                $table->dropColumn('early_leave_deduction_amount');
            }
        });
    }
};
