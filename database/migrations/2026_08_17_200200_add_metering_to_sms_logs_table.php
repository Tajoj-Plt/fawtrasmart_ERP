<?php

use App\Support\SchemaGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * قياس الرسائل في السجل، وتأجيلها، وإعادة محاولتها.
 *
 * الحدود اليومية والشهرية ومنع التكرار كلّها تُحسب من هذا الجدول، وكان
 * فيه النصّ والحالة فقط — فلا يُعرف كم جزءاً حُوسب عليه ولا بكم، ولا أيّ
 * قالب أنتج الرسالة حين تصل صياغة خاطئة إلى العملاء.
 *
 * `scheduled_at` و`attempts` هما ما يجعل إعدادَي «تأجيل ساعات الهدوء»
 * و«إعادة المحاولة» حقيقيَّين: الرسالة المؤجَّلة تُكتب هنا بموعدها،
 * ويرسلها الأمر `sms:flush` حين يحين. بدون هذين العمودين يكون الإعداد
 * وعداً لا يُنفَّذ — تُمنع الرسالة وقت الهدوء ثم لا تصل أبداً.
 *
 * الفهارس ليست تحسيناً مؤجَّلاً: كل إرسال يستعلم عن سقف اليوم وعن تكرار
 * الرقم، فبدونها يمسح كل إرسالٍ الجدولَ كاملاً.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sms_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('sms_logs', 'template_code')) {
                $table->string('template_code', 60)->nullable()->after('message')
                    ->comment('القالب الذي أنتج النصّ — فارغ للرسائل الحرّة');
            }
            if (! Schema::hasColumn('sms_logs', 'segments')) {
                $table->unsignedSmallInteger('segments')->default(1)->after('template_code')
                    ->comment('عدد الأجزاء التي حُوسب عليها');
            }
            if (! Schema::hasColumn('sms_logs', 'cost')) {
                $table->decimal('cost', 10, 4)->default(0)->after('segments');
            }

            if (! Schema::hasColumn('sms_logs', 'scheduled_at')) {
                $table->timestamp('scheduled_at')->nullable()->after('status')
                    ->comment('موعد الإرسال المؤجَّل — يقرؤه الأمر sms:flush');
            }
            if (! Schema::hasColumn('sms_logs', 'attempts')) {
                $table->unsignedTinyInteger('attempts')->default(0)->after('scheduled_at')
                    ->comment('عدد محاولات الإرسال التي جرت');
            }

            if (! SchemaGuard::hasIndex('sms_logs', 'sms_logs_recipient_created_at_index')) {
                $table->index(['recipient', 'created_at']);
            }
            if (! SchemaGuard::hasIndex('sms_logs', 'sms_logs_status_created_at_index')) {
                $table->index(['status', 'created_at']);
            }
            if (! SchemaGuard::hasIndex('sms_logs', 'sms_logs_status_scheduled_at_index')) {
                $table->index(['status', 'scheduled_at']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('sms_logs', function (Blueprint $table) {
            $table->dropIndex(['recipient', 'created_at']);
            $table->dropIndex(['status', 'created_at']);
            $table->dropIndex(['status', 'scheduled_at']);
            $table->dropColumn(['template_code', 'segments', 'cost', 'scheduled_at', 'attempts']);
        });
    }
};
