<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * سجلُّ اليوم الموحَّد يحمل الآن **الوسيلة والمكان** لا الوقت وحده.
 *
 * الأعمدةُ `checkin_latitude/longitude` كانت مُضافةً منذ توسعة V2 ولم
 * يكتب فيها مسارُ تسجيلٍ واحد: `checkIn()` كان يمرّر الإحداثيات إلى
 * مُسجِّل الصور فقط، فيبقى صفُّ اليوم — وهو ما تقرؤه تقاريرُ الموارد
 * البشرية — بلا موقعٍ إطلاقاً.
 *
 * ويُوسَّع `check_in_method` ليقبل `geo`: التسجيلُ الميداني ليس باركوداً
 * ولا يدوياً، وحشرُه في إحداهما يُفقِد التقريرَ الفرقَ بين من مسح بطاقتَه
 * على جهاز الفرع ومن سجّل من جواله في الميدان.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_days', function (Blueprint $table) {
            $columns = [
                'check_in_channel' => fn () => $table->string('check_in_channel', 16)->nullable()
                    ->comment('kiosk | field | manual'),
                'check_out_channel' => fn () => $table->string('check_out_channel', 16)->nullable(),
                'check_in_kiosk_id' => fn () => $table->unsignedBigInteger('check_in_kiosk_id')->nullable(),
                'check_out_kiosk_id' => fn () => $table->unsignedBigInteger('check_out_kiosk_id')->nullable(),
                'check_in_geofence_id' => fn () => $table->unsignedBigInteger('check_in_geofence_id')->nullable(),
                'check_out_geofence_id' => fn () => $table->unsignedBigInteger('check_out_geofence_id')->nullable(),
                'check_in_distance_meters' => fn () => $table->unsignedInteger('check_in_distance_meters')->nullable(),
                'check_out_distance_meters' => fn () => $table->unsignedInteger('check_out_distance_meters')->nullable(),
                'check_in_punch_id' => fn () => $table->unsignedBigInteger('check_in_punch_id')->nullable(),
                'check_out_punch_id' => fn () => $table->unsignedBigInteger('check_out_punch_id')->nullable(),
            ];

            foreach ($columns as $column => $definition) {
                if (! Schema::hasColumn('attendance_days', $column)) {
                    $definition();
                }
            }
        });

        // توسعةُ الـenum شأنٌ خاصٌّ بـMySQL؛ sqlite يخزّنها نصاً أصلاً.
        if (DB::getDriverName() === 'mysql') {
            foreach (['check_in_method', 'check_out_method'] as $column) {
                DB::statement(
                    "ALTER TABLE `attendance_days` MODIFY `{$column}` ".
                    "ENUM('manual','barcode','qr','geo') NOT NULL DEFAULT 'manual'"
                );
            }
        }
    }

    public function down(): void
    {
        Schema::table('attendance_days', function (Blueprint $table) {
            foreach ([
                'check_in_channel', 'check_out_channel',
                'check_in_kiosk_id', 'check_out_kiosk_id',
                'check_in_geofence_id', 'check_out_geofence_id',
                'check_in_distance_meters', 'check_out_distance_meters',
                'check_in_punch_id', 'check_out_punch_id',
            ] as $column) {
                if (Schema::hasColumn('attendance_days', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        if (DB::getDriverName() === 'mysql') {
            foreach (['check_in_method', 'check_out_method'] as $column) {
                DB::statement(
                    "ALTER TABLE `attendance_days` MODIFY `{$column}` ".
                    "ENUM('manual','barcode','qr') NOT NULL DEFAULT 'manual'"
                );
            }
        }
    }
};
