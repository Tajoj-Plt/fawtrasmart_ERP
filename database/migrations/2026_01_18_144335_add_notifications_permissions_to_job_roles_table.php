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
        Schema::table('job_roles', function (Blueprint $table) {
            // ⭐ صلاحيات الإشعارات الجديدة
            if (! Schema::hasColumn('job_roles', 'notifications_view_all_notifications')) {
                $table->boolean('notifications_view_all_notifications')->default(0)->comment('عرض جميع الإشعارات');
            }
            if (! Schema::hasColumn('job_roles', 'notifications_view_system_notifications')) {
                $table->boolean('notifications_view_system_notifications')->default(0)->comment('عرض الإشعارات العامة');
            }
            if (! Schema::hasColumn('job_roles', 'notifications_view_client_notifications')) {
                $table->boolean('notifications_view_client_notifications')->default(0)->comment('عرض إشعارات العملاء');
            }
            if (! Schema::hasColumn('job_roles', 'notifications_send_notifications')) {
                $table->boolean('notifications_send_notifications')->default(0)->comment('إرسال إشعارات');
            }
            if (! Schema::hasColumn('job_roles', 'notifications_reply_to_notifications')) {
                $table->boolean('notifications_reply_to_notifications')->default(0)->comment('الرد على الإشعارات');
            }
            if (! Schema::hasColumn('job_roles', 'notifications_mark_as_read')) {
                $table->boolean('notifications_mark_as_read')->default(0)->comment('تسجيل الإشعارات كمقروءة');
            }
            if (! Schema::hasColumn('job_roles', 'notifications_delete_notifications')) {
                $table->boolean('notifications_delete_notifications')->default(0)->comment('حذف الإشعارات');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('job_roles', function (Blueprint $table) {
            // حذف الأعمدة عند الرجوع للإصدار السابق
            $table->dropColumn([
                'notifications_view_all_notifications',
                'notifications_view_system_notifications',
                'notifications_view_client_notifications',
                'notifications_send_notifications',
                'notifications_reply_to_notifications',
                'notifications_mark_as_read',
                'notifications_delete_notifications',
            ]);
        });
    }
};
