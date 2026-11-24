<?php

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
        Schema::table('employee_client_visits', function (Blueprint $table) {
            // إضافة حقل تاريخ الزيارة (متى تم تسجيل الزيارة)
            // null للبيانات القديمة، ويتم ملؤه من الآن عند إنشاء زيارات جديدة
            if (! Schema::hasColumn('employee_client_visits', 'visit_date')) {
                $table->date('visit_date')->nullable()->after('client_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employee_client_visits', function (Blueprint $table) {
            $table->dropColumn('visit_date');
        });
    }
};
