<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * تذكيرُ الموظّف بحجزه — طلب المستخدم (2026-09-20): الإشعاراتُ المجدولة
 * تصل الموظّفَ المعيَّن على الحجز أيضاً لا العميلَ وحدَه، بمفتاحٍ في
 * إعدادات الحجوزات. يقرؤه `SendReservationReminders`.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('reservation_settings', 'notify_reminder_employee')) {
            return;
        }

        Schema::table('reservation_settings', function (Blueprint $table) {
            // مفعَّلٌ ابتداءً: الموظّفُ صاحبُ الموعد أولى الناس بتذكيره،
            // والمفتاحُ في الشاشة لمن يريد قصرَ التذكير على العميل
            $table->boolean('notify_reminder_employee')->default(true)->after('notify_reminder');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('reservation_settings', 'notify_reminder_employee')) {
            return;
        }

        Schema::table('reservation_settings', function (Blueprint $table) {
            $table->dropColumn('notify_reminder_employee');
        });
    }
};
