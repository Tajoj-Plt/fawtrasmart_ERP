<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * إعدادات وحدة الحجوزات — صفٌّ واحد بأعمدةٍ صريحة.
 *
 * **لماذا أعمدةٌ لا حقيبةُ JSON؟** لأنّه اصطلاح النظام في إعدادات الوحدات
 * كلِّها: `client_module_settings` و`sales_invoice_settings` و
 * `purchase_invoice_settings` و`pos_general_settings` — كلُّها صفٌّ واحد
 * بأعمدة. والعمودُ الصريح يُستعلَم ويُفهرَس ويظهر في فرق القيم في سجلّ
 * النشاط؛ المفتاحُ داخل JSON لا يفعل شيئاً من ذلك.
 *
 * الجدول قائمٌ أصلاً بعمودَين (`duration` و`payment_online`) — هو نفسُه
 * الجدول الذي كان اسمه `reservations` قبل تصحيح التسمية. فلا يُنشأ جديد،
 * بل يُوسَّع.
 *
 * **`duration` تصير `default_duration`.** الاسمُ القديم لا يقول لمن يقرأه
 * إن كانت مدّةَ حجزٍ بعينه أم الافتراضَ العام — وهي الافتراض. والجدول
 * فارغ فالتسمية تُصحَّح بلا أثر.
 *
 * كلُّ مفتاحٍ هنا **يجب أن يحرس شيئاً**. مفتاحٌ يُعرَض في الشاشة ولا يقرؤه
 * أحدٌ أسوأ من غيابه: يَعِد المستخدمَ بضبطٍ لا يقع.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('reservation_settings')) {
            return;
        }

        if (Schema::hasColumn('reservation_settings', 'duration') && ! Schema::hasColumn('reservation_settings', 'default_duration')) {
            Schema::table('reservation_settings', function (Blueprint $table) {
                $table->renameColumn('duration', 'default_duration');
            });
        }

        Schema::table('reservation_settings', function (Blueprint $table) {
            $add = fn (string $column) => ! Schema::hasColumn('reservation_settings', $column);

            if (! $add('default_duration')) {
                $table->unsignedInteger('default_duration')->default(30)->change();
            }

            // ═══════════ عام ═══════════
            if ($add('is_enabled')) {
                $table->boolean('is_enabled')->default(true);
            }
            if ($add('default_branch_id')) {
                $table->unsignedBigInteger('default_branch_id')->nullable();
            }
            if ($add('default_status')) {
                $table->string('default_status', 40)->default('pending');
            }
            if ($add('allow_guest_booking')) {
                $table->boolean('allow_guest_booking')->default(false);
            }
            if ($add('allow_repeated_booking')) {
                $table->boolean('allow_repeated_booking')->default(true)
                    ->comment('هل يُسمح للعميل بأكثر من حجزٍ في اليوم نفسه؟');
            }
            if ($add('allow_overbooking')) {
                $table->boolean('allow_overbooking')->default(false)
                    ->comment('تعطيل كشف التعارض — لا يُفعَّل إلا لمن يقبل الحجز الزائد عمداً');
            }
            if ($add('max_daily_reservations')) {
                $table->unsignedInteger('max_daily_reservations')->nullable();
            }

            // ═══════════ المواعيد ═══════════
            if ($add('min_duration')) {
                $table->unsignedInteger('min_duration')->default(5);
            }
            if ($add('max_duration')) {
                $table->unsignedInteger('max_duration')->default(480);
            }
            if ($add('buffer_time')) {
                $table->unsignedInteger('buffer_time')->default(0)
                    ->comment('الفاصل الافتراضي — تتجاوزه أوقاتُ الخدمة إن ضُبطت');
            }
            if ($add('slot_interval')) {
                $table->unsignedInteger('slot_interval')->default(15)
                    ->comment('خطوة توليد الفترات المتاحة بالدقائق');
            }
            if ($add('min_advance_minutes')) {
                $table->unsignedInteger('min_advance_minutes')->default(0)
                    ->comment('أقلّ مهلة قبل الموعد — يمنع الحجز «بعد خمس دقائق»');
            }
            if ($add('max_advance_days')) {
                $table->unsignedInteger('max_advance_days')->default(365);
            }
            if ($add('treat_unset_days_as_closed')) {
                $table->boolean('treat_unset_days_as_closed')->default(true)
                    ->comment('يومٌ بلا أوقات عملٍ مضبوطة: مغلق (افتراضاً) أم مفتوح بلا قيد؟');
            }

            // ═══════════ الإلغاء ═══════════
            if ($add('allow_cancellation')) {
                $table->boolean('allow_cancellation')->default(true);
            }
            if ($add('cancellation_deadline_hours')) {
                $table->unsignedInteger('cancellation_deadline_hours')->default(24);
            }
            if ($add('allow_cancel_after_confirm')) {
                $table->boolean('allow_cancel_after_confirm')->default(true);
            }
            if ($add('cancellation_fee_type')) {
                $table->string('cancellation_fee_type', 10)->default('none')->comment('none | fixed | percent');
            }
            if ($add('cancellation_fee_value')) {
                $table->decimal('cancellation_fee_value', 15, 2)->default(0);
            }

            // ═══════════ إعادة الجدولة ═══════════
            if ($add('allow_rescheduling')) {
                $table->boolean('allow_rescheduling')->default(true);
            }
            if ($add('reschedule_deadline_hours')) {
                $table->unsignedInteger('reschedule_deadline_hours')->default(24);
            }
            if ($add('max_reschedule_attempts')) {
                $table->unsignedInteger('max_reschedule_attempts')->default(2);
            }

            // ═══════════ العميل ═══════════
            if ($add('require_client')) {
                $table->boolean('require_client')->default(true);
            }
            if ($add('allow_new_client')) {
                $table->boolean('allow_new_client')->default(true);
            }
            if ($add('require_mobile')) {
                $table->boolean('require_mobile')->default(false);
            }
            if ($add('require_email')) {
                $table->boolean('require_email')->default(false);
            }

            // ═══════════ الإشعارات والتذكير ═══════════
            foreach ([
                'notify_created', 'notify_confirmed', 'notify_cancelled',
                'notify_rescheduled', 'notify_reminder', 'notify_completed', 'notify_no_show',
            ] as $flag) {
                if ($add($flag)) {
                    $table->boolean($flag)->default(true);
                }
            }
            if ($add('reminder_offsets')) {
                $table->string('reminder_offsets', 120)->default('1440,120')
                    ->comment('دقائقُ قبل الموعد، مفصولة بفواصل — مثال: 1440,120,30');
            }

            // ═══════════ المال والفوترة ═══════════
            if ($add('require_deposit')) {
                $table->boolean('require_deposit')->default(false);
            }
            if ($add('deposit_type')) {
                $table->string('deposit_type', 10)->default('percent')->comment('percent | fixed');
            }
            if ($add('deposit_value')) {
                $table->decimal('deposit_value', 15, 2)->default(0);
            }
            if ($add('allow_overpayment')) {
                $table->boolean('allow_overpayment')->default(false);
            }
            if ($add('auto_invoice_on')) {
                $table->string('auto_invoice_on', 20)->default('never')
                    ->comment('never | confirmed | completed — متى تُنشأ الفاتورة تلقائياً');
            }
            if ($add('default_payment_method_id')) {
                $table->unsignedBigInteger('default_payment_method_id')->nullable();
            }
            if ($add('default_treasury_id')) {
                $table->unsignedBigInteger('default_treasury_id')->nullable();
            }
            if ($add('default_tax_id')) {
                $table->unsignedBigInteger('default_tax_id')->nullable();
            }

            // ═══════════ متقدّم ═══════════
            if ($add('calendar_default_view')) {
                $table->string('calendar_default_view', 30)->default('timeGridWeek');
            }
            if ($add('updated_by')) {
                $table->unsignedBigInteger('updated_by')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('reservation_settings')) {
            return;
        }

        Schema::table('reservation_settings', function (Blueprint $table) {
            $table->dropColumn([
                'is_enabled', 'default_branch_id', 'default_status', 'allow_guest_booking',
                'allow_repeated_booking', 'allow_overbooking', 'max_daily_reservations',
                'min_duration', 'max_duration', 'buffer_time', 'slot_interval',
                'min_advance_minutes', 'max_advance_days', 'treat_unset_days_as_closed',
                'allow_cancellation', 'cancellation_deadline_hours', 'allow_cancel_after_confirm',
                'cancellation_fee_type', 'cancellation_fee_value',
                'allow_rescheduling', 'reschedule_deadline_hours', 'max_reschedule_attempts',
                'require_client', 'allow_new_client', 'require_mobile', 'require_email',
                'notify_created', 'notify_confirmed', 'notify_cancelled', 'notify_rescheduled',
                'notify_reminder', 'notify_completed', 'notify_no_show', 'reminder_offsets',
                'require_deposit', 'deposit_type', 'deposit_value', 'allow_overpayment',
                'auto_invoice_on', 'default_payment_method_id', 'default_treasury_id',
                'default_tax_id', 'calendar_default_view', 'updated_by',
            ]);
        });

        Schema::table('reservation_settings', function (Blueprint $table) {
            $table->renameColumn('default_duration', 'duration');
        });
    }
};
