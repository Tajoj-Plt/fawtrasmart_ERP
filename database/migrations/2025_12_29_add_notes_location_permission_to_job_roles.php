<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * إضافة صلاحية إضافة ملاحظة من أي موقع (بدون التحقق من موقع العميل)
     */
    public function up(): void
    {
        Schema::table('job_roles', function (Blueprint $table) {
            // صلاحية إضافة ملاحظة من أي موقع
            if (!Schema::hasColumn('job_roles', 'client_follow_up_add_notes_from_any_location')) {
                $table->boolean('client_follow_up_add_notes_from_any_location')->default(false)->after('client_follow_up_add_notes_attachments_appointments_own');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('job_roles', function (Blueprint $table) {
            if (Schema::hasColumn('job_roles', 'client_follow_up_add_notes_from_any_location')) {
                $table->dropColumn('client_follow_up_add_notes_from_any_location');
            }
        });
    }
};
