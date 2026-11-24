<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * صور الحضور/الانصراف المُلتقطة فعلياً.
 * كل صورة ترتبط بـ attendance_day + photo_type + phase (checkin/checkout).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('attendance_photos')) {
            Schema::create('attendance_photos', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('attendance_day_id');
                $table->unsignedBigInteger('photo_type_id');
                $table->unsignedBigInteger('employee_id');
                $table->enum('phase', ['checkin', 'checkout'])->default('checkin')
                    ->comment('هل التقطت عند الحضور أم الانصراف');
                $table->string('file_path')->comment('المسار النسبي للصورة في storage');
                $table->string('original_name')->nullable();
                $table->unsignedInteger('file_size')->nullable()->comment('بالبايت');
                $table->string('mime_type', 100)->nullable();

                // معلومات إضافية
                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 10, 7)->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->text('notes')->nullable();

                $table->timestamps();

                $table->index('attendance_day_id', 'idx_photos_day');
                $table->index(['employee_id', 'phase'], 'idx_photos_emp_phase');
                $table->foreign('attendance_day_id', 'fk_photos_day')
                    ->references('id')->on('attendance_days')->cascadeOnDelete();
                $table->foreign('photo_type_id', 'fk_photos_type')
                    ->references('id')->on('attendance_photo_types');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_photos');
    }
};
