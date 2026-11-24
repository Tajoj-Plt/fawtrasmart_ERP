<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * إضافة حقول لتتبع الزيارات المنقولة من يوم/أسبوع لآخر
     */
    public function up(): void
    {
        Schema::table('employee_client_visits', function (Blueprint $table) {
            // هل تم نقل هذه الزيارة من يوم/أسبوع آخر؟
            if (! Schema::hasColumn('employee_client_visits', 'is_moved')) {
                $table->boolean('is_moved')->default(false)->after('status');
            }

            // اليوم الأصلي الذي كانت فيه الزيارة
            if (! Schema::hasColumn('employee_client_visits', 'moved_from_day')) {
                $table->string('moved_from_day')->nullable()->after('is_moved');
            }

            // رقم الأسبوع الأصلي
            if (! Schema::hasColumn('employee_client_visits', 'moved_from_week')) {
                $table->integer('moved_from_week')->nullable()->after('moved_from_day');
            }

            // السنة الأصلية
            if (! Schema::hasColumn('employee_client_visits', 'moved_from_year')) {
                $table->integer('moved_from_year')->nullable()->after('moved_from_week');
            }

            // تاريخ نقل الزيارة
            if (! Schema::hasColumn('employee_client_visits', 'moved_at')) {
                $table->timestamp('moved_at')->nullable()->after('moved_from_year');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employee_client_visits', function (Blueprint $table) {
            $table->dropColumn([
                'is_moved',
                'moved_from_day',
                'moved_from_week',
                'moved_from_year',
                'moved_at'
            ]);
        });
    }
};
