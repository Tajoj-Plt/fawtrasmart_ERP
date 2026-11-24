<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * إصلاح جدول سجل الإشعارات:
 * - إضافة عمود updated_at المفقود
 * - تغيير channel من enum إلى string لمرونة أكبر
 * - تغيير status من enum إلى string لمرونة أكبر
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('points_notifications_log', function (Blueprint $table) {
            // إضافة updated_at إذا لم يكن موجوداً
            if (!Schema::hasColumn('points_notifications_log', 'updated_at')) {
                $table->timestamp('updated_at')->nullable()->after('created_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('points_notifications_log', function (Blueprint $table) {
            if (Schema::hasColumn('points_notifications_log', 'updated_at')) {
                $table->dropColumn('updated_at');
            }
        });
    }
};
