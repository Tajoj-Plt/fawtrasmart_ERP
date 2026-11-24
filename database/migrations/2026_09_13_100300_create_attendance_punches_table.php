<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * الختمُ الخام: كلُّ محاولةِ تسجيلٍ — قُبِلت أو رُدَّت — تُكتب هنا مرّةً
 * ولا تُعدَّل بعدها أبداً.
 *
 * `attendance_days` صفٌّ واحدٌ لليوم يُعاد حسابُه مع كل معالجة، فهو لا
 * يصلح سجلاً تدقيقياً: إذا اعتُمِد تصحيحٌ فقد ضاع ما كان قبله. وما كان
 * يقوم مقامَ السجل هو `Log::info` في ملف `laravel.log` — نصٌّ يُدوَّر
 * ويُحذف، لا يُستعلَم عنه ولا يُعرَض في تقرير.
 *
 * ولا تُكتب المحاولاتُ المقبولة وحدها: الردُّ نفسُه دليل. «حاول التسجيل
 * على بُعد ٨٠٠ متر من المرفق الساعة ٧:٥٠» واقعةٌ تحتاج الموارد البشرية
 * أن تراها حين يشكو الموظفُ أن النظام لم يسجّل له.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('attendance_punches')) {
            return;
        }

        Schema::create('attendance_punches', function (Blueprint $table) {
            $table->id();

            // قد يكون فارغاً: باركودٌ مجهولٌ مُسِح على الجهاز واقعةٌ تُسجَّل أيضاً
            $table->unsignedBigInteger('employee_id')->nullable()->index();
            $table->unsignedBigInteger('attendance_day_id')->nullable()->index();

            $table->string('punch_type', 8)->comment('in | out');
            $table->dateTime('punched_at');
            $table->date('attendance_date');

            // الوسيلةُ والمكان — يُسجّلهما النظام لا الموظف
            $table->string('channel', 16)->comment('kiosk | field | manual');
            $table->string('method', 16)->comment('barcode | qr | geo | manual');
            $table->unsignedBigInteger('kiosk_id')->nullable()->index();
            $table->unsignedBigInteger('geofence_id')->nullable()->index();

            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->unsignedInteger('accuracy_meters')->nullable();
            $table->unsignedInteger('distance_meters')->nullable()
                ->comment('بُعد نقطة التسجيل عن مركز النطاق');

            $table->string('ip_address', 45)->nullable();
            $table->string('wifi_ssid', 64)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('raw_barcode', 64)->nullable()
                ->comment('ما قرأته الكاميرا حرفياً — حتى لو لم يطابق موظفاً');

            // لقطةُ الوردية وقت الختم: الوردياتُ تُعدَّل لاحقاً، والحسابُ
            // القديم يجب أن يبقى مفهوماً بمعطياته هو.
            $table->time('expected_check_in')->nullable();
            $table->time('expected_check_out')->nullable();
            $table->integer('late_minutes')->default(0);
            $table->integer('early_leave_minutes')->default(0);

            $table->string('result', 16)->index()->comment('accepted | rejected');
            $table->string('stage', 32)->nullable()
                ->comment('حلقةُ التسلسل التي ردّت المحاولة: employee|shift|location|day|leave');
            $table->string('rejection_code', 64)->nullable();
            $table->string('rejection_reason', 255)->nullable();

            $table->unsignedBigInteger('created_by')->nullable()
                ->comment('المستخدم المُشغِّل للجهاز، أو الموظف نفسه ميدانياً');
            $table->timestamps();

            $table->index(['employee_id', 'attendance_date'], 'idx_punch_emp_date');
            $table->index(['attendance_date', 'result'], 'idx_punch_date_result');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_punches');
    }
};
