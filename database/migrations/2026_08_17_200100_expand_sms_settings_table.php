<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * توسعة إعدادات الرسائل.
 *
 * كان الجدول أربعة أعمدة: اسم مستخدم ومفتاح واجهة واسم مرسِل ومفتاح
 * تفعيل. وكلّ ما عدا ذلك مكتوبٌ في الكود: المهلة عشرون ثانية، والبوابة
 * Msegat وحدها، ولا حدّ يومي ولا منع تكرار ولا أوقات هدوء — فالنظام
 * قادر على إرسال ألف رسالة بالخطأ في الثالثة فجراً ولا شيء يوقفه.
 *
 * كل عمود هنا يقرؤه `SmsGateway` قبل الإرسال، كي يكون الإعداد تغييراً
 * في السلوك لا حقلاً يُحفظ ولا أثر له.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sms_settings', function (Blueprint $table) {
            // ==================== البوابة ====================
            if (! Schema::hasColumn('sms_settings', 'provider')) {
                $table->string('provider', 30)->default('msegat')->after('id')
                    ->comment('مزوّد الخدمة — يحدّد نقطة الاتصال وصيغة الطلب');
            }
            if (! Schema::hasColumn('sms_settings', 'api_url')) {
                $table->string('api_url', 300)->nullable()->after('api_key')
                    ->comment('نقطة اتصال مخصّصة — تُترك فارغة لاستعمال عنوان المزوّد');
            }
            if (! Schema::hasColumn('sms_settings', 'fallback_sender_name')) {
                $table->string('fallback_sender_name')->nullable()->after('sender_name')
                    ->comment('اسم مرسِل بديل حين يُرفض الأساسي');
            }
            if (! Schema::hasColumn('sms_settings', 'timeout_seconds')) {
                $table->unsignedSmallInteger('timeout_seconds')->default(20)->after('fallback_sender_name');
            }

            // ==================== صياغة الرسالة ====================
            if (! Schema::hasColumn('sms_settings', 'default_country_code')) {
                $table->string('default_country_code', 6)->default('966')->after('is_active')
                    ->comment('مفتاح الدولة الذي يُكمَّل به الرقم المحلي');
            }
            if (! Schema::hasColumn('sms_settings', 'allow_international')) {
                $table->boolean('allow_international')->default(false)->after('default_country_code');
            }
            if (! Schema::hasColumn('sms_settings', 'allowed_country_codes')) {
                $table->json('allowed_country_codes')->nullable()->after('allow_international')
                    ->comment('مفاتيح الدول المسموح الإرسال إليها — فارغ = الكل');
            }
            if (! Schema::hasColumn('sms_settings', 'encoding_mode')) {
                $table->string('encoding_mode', 20)->default('auto')->after('allowed_country_codes')
                    ->comment('auto | unicode | gsm — يحدّد حدّ الحروف وكلفة الرسالة');
            }
            if (! Schema::hasColumn('sms_settings', 'max_segments')) {
                $table->unsignedTinyInteger('max_segments')->default(4)->after('encoding_mode')
                    ->comment('أقصى عدد أجزاء للرسالة الواحدة — ما فوقه يُرفض');
            }
            if (! Schema::hasColumn('sms_settings', 'strip_emoji')) {
                $table->boolean('strip_emoji')->default(false)->after('max_segments')
                    ->comment('الرموز التعبيرية تحوّل الرسالة إلى Unicode فتضاعف كلفتها');
            }
            if (! Schema::hasColumn('sms_settings', 'collapse_whitespace')) {
                $table->boolean('collapse_whitespace')->default(true)->after('strip_emoji');
            }
            if (! Schema::hasColumn('sms_settings', 'append_signature')) {
                $table->boolean('append_signature')->default(false)->after('collapse_whitespace');
            }
            if (! Schema::hasColumn('sms_settings', 'signature_text')) {
                $table->string('signature_text', 120)->nullable()->after('append_signature');
            }
            if (! Schema::hasColumn('sms_settings', 'append_opt_out')) {
                $table->boolean('append_opt_out')->default(false)->after('signature_text');
            }
            if (! Schema::hasColumn('sms_settings', 'opt_out_text')) {
                $table->string('opt_out_text', 120)->nullable()->after('append_opt_out');
            }

            // ==================== أوقات الهدوء وأيام العمل ====================
            if (! Schema::hasColumn('sms_settings', 'quiet_hours_enabled')) {
                $table->boolean('quiet_hours_enabled')->default(false)->after('opt_out_text');
            }
            if (! Schema::hasColumn('sms_settings', 'quiet_hours_start')) {
                $table->time('quiet_hours_start')->nullable()->after('quiet_hours_enabled');
            }
            if (! Schema::hasColumn('sms_settings', 'quiet_hours_end')) {
                $table->time('quiet_hours_end')->nullable()->after('quiet_hours_start');
            }
            if (! Schema::hasColumn('sms_settings', 'quiet_hours_action')) {
                $table->string('quiet_hours_action', 20)->default('defer')->after('quiet_hours_end')
                    ->comment('defer = يؤجَّل لأول وقت مسموح، drop = يُلغى');
            }
            if (! Schema::hasColumn('sms_settings', 'respect_working_days')) {
                $table->boolean('respect_working_days')->default(false)->after('quiet_hours_action');
            }
            if (! Schema::hasColumn('sms_settings', 'working_days')) {
                $table->json('working_days')->nullable()->after('respect_working_days')
                    ->comment('أيام الأسبوع المسموح الإرسال فيها — 0 الأحد');
            }
            if (! Schema::hasColumn('sms_settings', 'timezone')) {
                $table->string('timezone', 60)->nullable()->after('working_days')
                    ->comment('المنطقة الزمنية التي تُقاس بها أوقات الهدوء');
            }

            // ==================== الحدود ومنع التكرار ====================
            if (! Schema::hasColumn('sms_settings', 'rate_limit_per_minute')) {
                $table->unsignedSmallInteger('rate_limit_per_minute')->default(0)->after('timezone')
                    ->comment('0 = بلا حدّ');
            }
            if (! Schema::hasColumn('sms_settings', 'daily_cap')) {
                $table->unsignedInteger('daily_cap')->default(0)->after('rate_limit_per_minute');
            }
            if (! Schema::hasColumn('sms_settings', 'monthly_cap')) {
                $table->unsignedInteger('monthly_cap')->default(0)->after('daily_cap');
            }
            if (! Schema::hasColumn('sms_settings', 'per_recipient_daily_cap')) {
                $table->unsignedSmallInteger('per_recipient_daily_cap')->default(0)->after('monthly_cap')
                    ->comment('أقصى ما يستقبله رقم واحد يومياً');
            }
            if (! Schema::hasColumn('sms_settings', 'duplicate_window_minutes')) {
                $table->unsignedSmallInteger('duplicate_window_minutes')->default(0)->after('per_recipient_daily_cap')
                    ->comment('يمنع تكرار النصّ نفسه للرقم نفسه خلال هذه المدّة');
            }

            // ==================== إعادة المحاولة ====================
            if (! Schema::hasColumn('sms_settings', 'retry_enabled')) {
                $table->boolean('retry_enabled')->default(false)->after('duplicate_window_minutes');
            }
            if (! Schema::hasColumn('sms_settings', 'retry_attempts')) {
                $table->unsignedTinyInteger('retry_attempts')->default(2)->after('retry_enabled');
            }
            if (! Schema::hasColumn('sms_settings', 'retry_delay_seconds')) {
                $table->unsignedSmallInteger('retry_delay_seconds')->default(60)->after('retry_attempts');
            }
            if (! Schema::hasColumn('sms_settings', 'retry_backoff')) {
                $table->string('retry_backoff', 20)->default('exponential')->after('retry_delay_seconds')
                    ->comment('fixed | exponential');
            }

            // ==================== وضع الاختبار والحماية ====================
            if (! Schema::hasColumn('sms_settings', 'test_mode')) {
                $table->boolean('test_mode')->default(false)->after('retry_backoff')
                    ->comment('يسجّل الرسالة ولا يرسلها — للتجربة بلا كلفة');
            }
            if (! Schema::hasColumn('sms_settings', 'test_redirect_number')) {
                $table->string('test_redirect_number', 20)->nullable()->after('test_mode')
                    ->comment('يُحوَّل إليه كل إرسال أثناء التجربة');
            }
            if (! Schema::hasColumn('sms_settings', 'blocked_numbers')) {
                $table->text('blocked_numbers')->nullable()->after('test_redirect_number')
                    ->comment('أرقام لا تصلها رسالة — رقم في كل سطر');
            }
            if (! Schema::hasColumn('sms_settings', 'whitelist_enabled')) {
                $table->boolean('whitelist_enabled')->default(false)->after('blocked_numbers');
            }
            if (! Schema::hasColumn('sms_settings', 'allowed_numbers')) {
                $table->text('allowed_numbers')->nullable()->after('whitelist_enabled')
                    ->comment('حين يُفعَّل، لا يُرسل إلا إلى هذه الأرقام');
            }

            // ==================== الرصيد والكلفة ====================
            if (! Schema::hasColumn('sms_settings', 'low_balance_alert')) {
                $table->boolean('low_balance_alert')->default(false)->after('allowed_numbers');
            }
            if (! Schema::hasColumn('sms_settings', 'low_balance_threshold')) {
                $table->unsignedInteger('low_balance_threshold')->default(100)->after('low_balance_alert');
            }
            if (! Schema::hasColumn('sms_settings', 'cost_per_segment')) {
                $table->decimal('cost_per_segment', 8, 4)->default(0)->after('low_balance_threshold');
            }
            if (! Schema::hasColumn('sms_settings', 'cost_currency')) {
                $table->string('cost_currency', 10)->default('SAR')->after('cost_per_segment');
            }

            // ==================== السجل ====================
            if (! Schema::hasColumn('sms_settings', 'log_enabled')) {
                $table->boolean('log_enabled')->default(true)->after('cost_currency');
            }
            if (! Schema::hasColumn('sms_settings', 'log_body')) {
                $table->boolean('log_body')->default(true)->after('log_enabled')
                    ->comment('نصّ الرسالة قد يحمل بيانات العميل — يمكن ألّا يُخزَّن');
            }
            if (! Schema::hasColumn('sms_settings', 'mask_recipient')) {
                $table->boolean('mask_recipient')->default(false)->after('log_body')
                    ->comment('يُخفي أوسط أرقام الجوال في السجل');
            }
            if (! Schema::hasColumn('sms_settings', 'log_retention_days')) {
                $table->unsignedSmallInteger('log_retention_days')->default(0)->after('mask_recipient')
                    ->comment('0 = بلا حذف تلقائي');
            }

            // ==================== تنبيه المسؤولين ====================
            if (! Schema::hasColumn('sms_settings', 'notify_on_failure')) {
                $table->boolean('notify_on_failure')->default(false)->after('log_retention_days');
            }
            if (! Schema::hasColumn('sms_settings', 'failure_threshold')) {
                $table->unsignedSmallInteger('failure_threshold')->default(5)->after('notify_on_failure')
                    ->comment('عدد الإخفاقات المتتالية قبل التنبيه');
            }
            if (! Schema::hasColumn('sms_settings', 'failure_notify_emails')) {
                $table->string('failure_notify_emails', 500)->nullable()->after('failure_threshold');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sms_settings', function (Blueprint $table) {
            $table->dropColumn([
                'provider', 'api_url', 'fallback_sender_name', 'timeout_seconds',
                'default_country_code', 'allow_international', 'allowed_country_codes',
                'encoding_mode', 'max_segments', 'strip_emoji', 'collapse_whitespace',
                'append_signature', 'signature_text', 'append_opt_out', 'opt_out_text',
                'quiet_hours_enabled', 'quiet_hours_start', 'quiet_hours_end',
                'quiet_hours_action', 'respect_working_days', 'working_days', 'timezone',
                'rate_limit_per_minute', 'daily_cap', 'monthly_cap',
                'per_recipient_daily_cap', 'duplicate_window_minutes',
                'retry_enabled', 'retry_attempts', 'retry_delay_seconds', 'retry_backoff',
                'test_mode', 'test_redirect_number', 'blocked_numbers',
                'whitelist_enabled', 'allowed_numbers',
                'low_balance_alert', 'low_balance_threshold', 'cost_per_segment', 'cost_currency',
                'log_enabled', 'log_body', 'mask_recipient', 'log_retention_days',
                'notify_on_failure', 'failure_threshold', 'failure_notify_emails',
            ]);
        });
    }
};
