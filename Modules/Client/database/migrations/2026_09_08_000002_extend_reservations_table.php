<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ترقية جدول الحجز من موعدٍ مجرَّد إلى مستندٍ كامل.
 *
 * الجدول الأصلي ثمانيةُ أعمدة: منتج، موظف، تاريخ، وقتان، عميل، حالة.
 * لا مبلغ ولا عملة ولا ضريبة ولا فرع ولا رقم ولا مُنشئ — أي أنّه ليس
 * مستنداً في نظامٍ محاسبي، بل صفٌّ في تقويم.
 *
 * ثلاث إضافات بنيوية تجعله مستنداً كبقية مستندات النظام:
 *
 *   • **الرقم** `code` — من `next_document_number('reservation')`، والقسم
 *     `reservation` معرَّفٌ في `DocumentSections` منذ قبل هذه الوحدة.
 *   • **السياق** `branch_id` + `employee_id` + `created_by` — تختمها
 *     `StampsOwnerContext` لحظة الإنشاء، فيصير الحجز مرئياً لفرعه وحده.
 *   • **المال** المبالغُ والعملةُ و`accounting_period_id` — فيُنسب الحجز
 *     إلى فترته كما تُنسب الفاتورة والسند.
 *
 * **`status` تصير `string` لا `ENUM`.** إضافةُ حالةٍ إلى عمود ENUM تعني
 * `ALTER TABLE` على جدولٍ حيّ؛ والحالاتُ العشر معرَّفةٌ في
 * `Modules\Client\Reservations\Enums\ReservationStatus` حيث يعيش معها جدولُ
 * الانتقالات المسموحة.
 *
 * **`employee_id` يشير إلى `employees` لا `users`.** النموذج القديم كان
 * `belongsTo(User::class)` — وهو خطأٌ يظهر أوّلَ ما يُسأل عن أوقات دوام
 * الموظف أو إجازته: تلك في سجلّ الموظف لا في حساب المستخدم. والجدول فارغ
 * فلا صفَّ يُصحَّح.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('reservations')) {
            return;
        }

        // ─────────── التسمية والأنواع ───────────
        if (Schema::hasColumn('reservations', 'appointment_date') && ! Schema::hasColumn('reservations', 'reservation_date')) {
            Schema::table('reservations', function (Blueprint $table) {
                $table->renameColumn('appointment_date', 'reservation_date');
            });
        }

        Schema::table('reservations', function (Blueprint $table) {
            // ENUM ← string: انظر ترويسة الملف
            $table->string('status', 40)->default('pending')->change();

            // الخدمة تحلّ محلّ المنتج المباشر، فيصير المنتج اختيارياً
            $table->unsignedBigInteger('product_id')->nullable()->change();
            $table->unsignedBigInteger('employee_id')->nullable()->change();
        });

        // ─────────── الأعمدة الجديدة ───────────
        Schema::table('reservations', function (Blueprint $table) {
            $add = fn (string $column) => ! Schema::hasColumn('reservations', $column);

            // الهوية والسياق
            if ($add('code')) {
                $table->string('code', 50)->nullable()->after('id');
            }
            if ($add('branch_id')) {
                $table->unsignedBigInteger('branch_id')->nullable()->after('code');
            }
            if ($add('service_id')) {
                $table->unsignedBigInteger('service_id')->nullable()->after('product_id');
            }

            // التوقيت
            if ($add('duration')) {
                $table->unsignedInteger('duration')->default(0)->after('end_time')
                    ->comment('مدّة الخدمة بالدقائق — بلا وقتَي التحضير والتنظيف');
            }

            // المال
            if ($add('currency')) {
                $table->string('currency', 10)->nullable()->after('status');
            }
            if ($add('exchange_rate')) {
                $table->decimal('exchange_rate', 18, 6)->default(1)->after('currency');
            }
            if ($add('tax_id')) {
                $table->unsignedBigInteger('tax_id')->nullable()->after('exchange_rate');
            }
            if ($add('unit_price')) {
                $table->decimal('unit_price', 15, 2)->default(0)->after('tax_id');
            }
            if ($add('subtotal')) {
                $table->decimal('subtotal', 15, 2)->default(0)->after('unit_price');
            }
            if ($add('discount_value')) {
                $table->decimal('discount_value', 15, 2)->default(0)->after('subtotal')
                    ->comment('القيمة كما أدخلها المستخدم — نسبةً أو مبلغاً حسب discount_type');
            }
            if ($add('discount_type')) {
                $table->string('discount_type', 10)->default('amount')->after('discount_value');
            }
            if ($add('discount_amount')) {
                $table->decimal('discount_amount', 15, 2)->default(0)->after('discount_type')
                    ->comment('الخصم محسوباً بالمبلغ دائماً — لا يُعاد اشتقاقه في أي شاشة');
            }
            if ($add('tax_amount')) {
                $table->decimal('tax_amount', 15, 2)->default(0)->after('discount_amount');
            }
            if ($add('total')) {
                $table->decimal('total', 15, 2)->default(0)->after('tax_amount');
            }
            if ($add('paid_amount')) {
                $table->decimal('paid_amount', 15, 2)->default(0)->after('total');
            }
            if ($add('remaining_amount')) {
                $table->decimal('remaining_amount', 15, 2)->default(0)->after('paid_amount');
            }
            if ($add('cancellation_fee')) {
                $table->decimal('cancellation_fee', 15, 2)->default(0)->after('remaining_amount');
            }

            // الروابط
            if ($add('payment_method_id')) {
                $table->unsignedBigInteger('payment_method_id')->nullable()->after('cancellation_fee');
            }
            if ($add('invoice_id')) {
                $table->unsignedBigInteger('invoice_id')->nullable()->after('payment_method_id');
            }
            if ($add('accounting_period_id')) {
                $table->unsignedBigInteger('accounting_period_id')->nullable()->after('invoice_id');
            }

            // الوصف
            if ($add('notes')) {
                $table->text('notes')->nullable()->after('accounting_period_id');
            }
            if ($add('source')) {
                $table->string('source', 30)->default('back_office')->after('notes')
                    ->comment('back_office | online | pos | api');
            }

            // ختم مراحل سير العمل — تاريخُ كل انتقالٍ في عمودِه
            foreach (['confirmed_at', 'checked_in_at', 'started_at', 'completed_at', 'cancelled_at', 'no_show_at'] as $stamp) {
                if ($add($stamp)) {
                    $table->timestamp($stamp)->nullable();
                }
            }
            if ($add('cancelled_by')) {
                $table->unsignedBigInteger('cancelled_by')->nullable();
            }
            if ($add('cancellation_reason')) {
                $table->text('cancellation_reason')->nullable();
            }

            // إعادة الجدولة
            if ($add('reschedule_count')) {
                $table->unsignedSmallInteger('reschedule_count')->default(0);
            }
            if ($add('rescheduled_from_date')) {
                $table->date('rescheduled_from_date')->nullable();
            }
            if ($add('rescheduled_from_start')) {
                $table->time('rescheduled_from_start')->nullable();
            }

            // التدقيق
            if ($add('created_by')) {
                $table->unsignedBigInteger('created_by')->nullable();
            }
            if ($add('updated_by')) {
                $table->unsignedBigInteger('updated_by')->nullable();
            }
            if ($add('deleted_at')) {
                $table->softDeletes();
            }
        });

        // ─────────── الفهارس ───────────
        //
        // فهرسان مركّبان لأثقل استعلامين في الوحدة: كشفُ التعارض يسأل
        // «حجوزاتُ هذا الموظف في هذا اليوم»، والتقويمُ يسأل «حجوزاتُ هذا
        // الفرع في هذا المدى». وبلا فهرسٍ يمسحان الجدول كلّه في كل نقرة.
        Schema::table('reservations', function (Blueprint $table) {
            $table->index(['employee_id', 'reservation_date'], 'reservations_employee_date_index');
            $table->index(['branch_id', 'reservation_date'], 'reservations_branch_date_index');
            $table->index(['status', 'reservation_date'], 'reservations_status_date_index');
            $table->index('client_id', 'reservations_client_index');
            $table->index('service_id', 'reservations_service_index');
            $table->index('invoice_id', 'reservations_invoice_index');
            $table->unique('code', 'reservations_code_unique');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('reservations')) {
            return;
        }

        Schema::table('reservations', function (Blueprint $table) {
            foreach ([
                'reservations_employee_date_index',
                'reservations_branch_date_index',
                'reservations_status_date_index',
                'reservations_client_index',
                'reservations_service_index',
                'reservations_invoice_index',
            ] as $index) {
                $table->dropIndex($index);
            }
            $table->dropUnique('reservations_code_unique');
        });

        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn([
                'code', 'branch_id', 'service_id', 'duration', 'currency', 'exchange_rate',
                'tax_id', 'unit_price', 'subtotal', 'discount_value', 'discount_type',
                'discount_amount', 'tax_amount', 'total', 'paid_amount', 'remaining_amount',
                'cancellation_fee', 'payment_method_id', 'invoice_id', 'accounting_period_id',
                'notes', 'source', 'confirmed_at', 'checked_in_at', 'started_at', 'completed_at',
                'cancelled_at', 'no_show_at', 'cancelled_by', 'cancellation_reason',
                'reschedule_count', 'rescheduled_from_date', 'rescheduled_from_start',
                'created_by', 'updated_by', 'deleted_at',
            ]);
        });

        Schema::table('reservations', function (Blueprint $table) {
            $table->renameColumn('reservation_date', 'appointment_date');
        });
    }
};
