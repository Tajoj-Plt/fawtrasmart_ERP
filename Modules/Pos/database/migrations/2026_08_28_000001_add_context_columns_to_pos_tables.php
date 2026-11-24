<?php

use App\Support\SchemaGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ربطُ مستندات نقاط البيع بسياقها: الفرعُ والفترةُ المحاسبية والعملة.
 *
 * ─────────────────────── لماذا الأعمدة الثلاثة معاً؟ ───────────────────────
 *
 * هي جوابُ ثلاثة أسئلةٍ يُسألها كلُّ مستندٍ مالي في هذا النظام، وكانت
 * جلسةُ نقطة البيع تعجز عن الثلاثة:
 *
 * • **أيُّ فرع؟** — الجلسةُ مربوطةٌ بجهازٍ والجهازُ بفرع، فالفرعُ يُستنتج
 *   بقفزتين. والاستنتاجُ يكسر التقارير: `leftJoin` مرتان لكل صفٍّ لمعرفة
 *   فرعه، ومقارنةُ الفروع مستحيلةٌ عملياً. والأسوأ أن نقل الجهاز بين
 *   الفروع لاحقاً **يُعيد كتابة تاريخ الجلسات القديمة** — فجلسةُ الشهر
 *   الماضي تظهر فجأةً في فرعٍ لم تُفتح فيه.
 *
 * • **أيُّ فترة محاسبية؟** — الفترةُ علاقةٌ لا مدًى، كما في المخزون
 *   والمالية. والختمُ لحظةَ الإنشاء لا الاستنتاجُ لاحقاً: حدودُ الفترات
 *   تُعدَّل، فمستندٌ يُسأل عن فترته بمقارنة تاريخه اليوم قد يُجيب بغير ما
 *   أُنشئ فيه.
 *
 * • **أيُّ عملة؟** — كانت `'SAR'` مكتوبةً في الشيفرة أربعةَ عشرَ موضعاً،
 *   والجلسةُ بلا عمودِ عملةٍ أصلاً. فأرصدةُ الافتتاح والإقفال أرقامٌ بلا
 *   وحدة.
 *
 * ─────────────────────── وأعمدةُ تأكيد الإقفال ───────────────────────
 *
 * `points_sale_confirm_close_sessions_own/all` صلاحيتان موجودتان في
 * الكتالوج منذ البداية — **بلا أيّ تنفيذ**: لا عمودَ يحمل من أكّد ولا
 * متى، ولا حالةَ «بانتظار التأكيد». فالكاشير يُقفل عهدته ويعتمدها بنفسه،
 * وهو ما وُضعت الصلاحيتان لمنعه بالضبط.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_sessions', function (Blueprint $table) {
            if (! Schema::hasColumn('pos_sessions', 'branch_id')) {
                $table->unsignedBigInteger('branch_id')->nullable()->after('device_id');
                if (! SchemaGuard::hasIndex('pos_sessions', 'pos_sessions_branch_idx')) {
                    $table->index('branch_id', 'pos_sessions_branch_idx');
                }
            }

            if (! Schema::hasColumn('pos_sessions', 'accounting_period_id')) {
                $table->unsignedBigInteger('accounting_period_id')->nullable()->after('branch_id');
                if (! SchemaGuard::hasIndex('pos_sessions', 'pos_sessions_period_idx')) {
                    $table->index('accounting_period_id', 'pos_sessions_period_idx');
                }
            }

            if (! Schema::hasColumn('pos_sessions', 'currency')) {
                $table->string('currency', 10)->nullable()->after('accounting_period_id');
            }

            /*
                دورةُ التأكيد.

                `closed_by` منفصلٌ عن `user_id`: المشرفُ قد يُقفل جلسةَ كاشير
                غاب، فصاحبُ الجلسة غيرُ من أقفلها. وكان العمودُ الوحيد
                `user_id` فلا يُعرف من أقفل.
            */
            if (! Schema::hasColumn('pos_sessions', 'closed_by')) {
                $table->unsignedBigInteger('closed_by')->nullable()->after('closing_notes');
            }

            if (! Schema::hasColumn('pos_sessions', 'confirmed_by')) {
                $table->unsignedBigInteger('confirmed_by')->nullable()->after('closed_by');
            }

            if (! Schema::hasColumn('pos_sessions', 'confirmed_at')) {
                $table->datetime('confirmed_at')->nullable()->after('confirmed_by');
            }

            if (! Schema::hasColumn('pos_sessions', 'confirmation_notes')) {
                $table->text('confirmation_notes')->nullable()->after('confirmed_at');
            }

            /*
                المتوقَّعُ محفوظاً لا محسوباً عند العرض.

                كان «الرصيد المتوقَّع» يُحسب في كل شاشةٍ بصيغته:
                `opening + total_cash - total_returns`. وأيُّ تغييرٍ لاحق في
                تفاصيل الجلسة يُغيّر ما «كان متوقَّعاً» أثناء الإقفال — فيصير
                الفرقُ المحفوظ لا يساوي الفرقَ المعروض.
            */
            if (! Schema::hasColumn('pos_sessions', 'expected_cash')) {
                $table->decimal('expected_cash', 15, 2)->nullable()->after('closing_balance');
            }

            if (! Schema::hasColumn('pos_sessions', 'variance_journal_entry_id')) {
                $table->unsignedBigInteger('variance_journal_entry_id')->nullable()->after('difference');
            }
        });

        /*
            الفاتورة: الفرعُ والجلسةُ معاً.

            `session_id` موجودٌ سلفاً، لكن الفرعَ كان يُستنتج منه بقفزتين —
            وتقاريرُ نقاط البيع كانت تصل إليه بـ`leftJoin` مزدوج على
            `pos_sessions` ثم `cashier_devices` في كل استعلام.
        */
        Schema::table('invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('invoices', 'pos_branch_id')) {
                $table->unsignedBigInteger('pos_branch_id')->nullable()->after('session_id');
                if (! SchemaGuard::hasIndex('invoices', 'invoices_type_pos_branch_idx')) {
                    $table->index(['type', 'pos_branch_id'], 'invoices_type_pos_branch_idx');
                }
            }

            if (! Schema::hasColumn('invoices', 'pos_device_id')) {
                $table->unsignedBigInteger('pos_device_id')->nullable()->after('pos_branch_id');
            }

        });

        /*
            تكلفةُ الوحدة **مجمَّدةً لحظةَ البيع**.

            كانت تقاريرُ الربحية تحسب التكلفة من `products.purchase_price`
            **الحالي**. فتغييرُ سعر الشراء اليوم يُعيد كتابة ربحية الشهر
            الماضي — تقريرٌ لا يُنتج الرقمَ نفسَه مرّتين، ولا يصلح أساساً
            لقرار.

            والعمودُ هنا بالوحدة الأساسية: يُضرب في
            `quantity × conversion_factor` فتخرج تكلفةُ السطر — بنفس المنطق
            الذي يستعمله قيدُ تكلفة المبيعات، فلا يختلف التقريرُ عن الدفتر.

            و`null` للفواتير السابقة: تُقرأ بالسعر الحالي وتُعلَّم «تقديرية»
            في التقرير بدل أن تُعرض كأنها مؤكّدة.
        */
        Schema::table('invoice_items', function (Blueprint $table) {
            if (! Schema::hasColumn('invoice_items', 'unit_cost_at_sale')) {
                $table->decimal('unit_cost_at_sale', 18, 4)->nullable();
            }
        });

        // أوقاتُ الوردية — بلا هذه الأعمدة كانت «الوردية» تسميةً في شجرة، لا مدًى زمنياً
        Schema::table('shifts_pos', function (Blueprint $table) {
            if (! Schema::hasColumn('shifts_pos', 'start_time')) {
                $table->time('start_time')->nullable()->after('name');
            }

            if (! Schema::hasColumn('shifts_pos', 'end_time')) {
                $table->time('end_time')->nullable()->after('start_time');
            }

            /*
                أيامُ الأسبوع كـJSON: `[0..6]` بالأحد أولاً.

                وردياتُ نهاية الأسبوع تختلف عن أيام العمل في أكثر المنشآت،
                وبلا هذا العمود تُنشأ وردياتٌ مكرّرةٌ بأسماءٍ متشابهة.
            */
            if (! Schema::hasColumn('shifts_pos', 'days_of_week')) {
                $table->json('days_of_week')->nullable()->after('end_time');
            }

            /** مهلةُ التأخّر المقبولة قبل أن يُعدّ فتحُ الجلسة خارج الوردية. */
            if (! Schema::hasColumn('shifts_pos', 'grace_minutes')) {
                $table->unsignedSmallInteger('grace_minutes')->default(15)->after('days_of_week');
            }

            if (! Schema::hasColumn('shifts_pos', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('grace_minutes');
            }
        });

        Schema::table('cashier_devices', function (Blueprint $table) {
            if (! Schema::hasColumn('cashier_devices', 'serial_number')) {
                $table->string('serial_number')->nullable()->after('device_name');
            }

            if (! Schema::hasColumn('cashier_devices', 'ip_address')) {
                $table->string('ip_address', 45)->nullable()->after('serial_number');
            }

            /** حدُّ النقد في الدرج — تجاوزُه يستدعي ترحيلاً إلى الخزينة. */
            if (! Schema::hasColumn('cashier_devices', 'max_cash_in_drawer')) {
                $table->decimal('max_cash_in_drawer', 15, 2)->nullable()->after('ip_address');
            }

            if (! Schema::hasColumn('cashier_devices', 'default_printer_id')) {
                $table->unsignedBigInteger('default_printer_id')->nullable()->after('max_cash_in_drawer');
            }
        });

        /*
            الإعدادات: عمودُ الفرع.

            `null` = الإعدادُ العام الذي يرثه الجميع. وصفٌّ بفرعٍ محدَّد
            يتقدّم عليه لذلك الفرع وحده — نفس تعاقد `BranchSettingsService`.
        */
        Schema::table('pos_general_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('pos_general_settings', 'branch_id')) {
                $table->unsignedBigInteger('branch_id')->nullable()->after('id');
                if (! SchemaGuard::hasIndex('pos_general_settings', 'pos_settings_branch_idx')) {
                    $table->index('branch_id', 'pos_settings_branch_idx');
                }
            }

            if (! Schema::hasColumn('pos_general_settings', 'default_bank_account_id')) {
                $table->unsignedBigInteger('default_bank_account_id')->nullable();
            }

            /** حدُّ الخصم الذي يجوز للكاشير منحُه بلا اعتماد — نسبةً مئوية. */
            if (! Schema::hasColumn('pos_general_settings', 'max_discount_percent')) {
                $table->decimal('max_discount_percent', 5, 2)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('pos_sessions', function (Blueprint $table) {
            foreach ([
                'pos_sessions_branch_idx' => 'branch_id',
                'pos_sessions_period_idx' => 'accounting_period_id',
            ] as $index => $column) {
                if (Schema::hasColumn('pos_sessions', $column)) {
                    $table->dropIndex($index);
                }
            }

            $table->dropColumn(array_values(array_filter([
                'branch_id', 'accounting_period_id', 'currency', 'closed_by',
                'confirmed_by', 'confirmed_at', 'confirmation_notes',
                'expected_cash', 'variance_journal_entry_id',
            ], fn ($c) => Schema::hasColumn('pos_sessions', $c))));
        });

        Schema::table('invoices', function (Blueprint $table) {
            if (Schema::hasColumn('invoices', 'pos_branch_id')) {
                $table->dropIndex('invoices_type_pos_branch_idx');
            }

            $table->dropColumn(array_values(array_filter(
                ['pos_branch_id', 'pos_device_id'],
                fn ($c) => Schema::hasColumn('invoices', $c)
            )));
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            if (Schema::hasColumn('invoice_items', 'unit_cost_at_sale')) {
                $table->dropColumn('unit_cost_at_sale');
            }
        });

        Schema::table('shifts_pos', function (Blueprint $table) {
            $table->dropColumn(array_values(array_filter(
                ['start_time', 'end_time', 'days_of_week', 'grace_minutes', 'is_active'],
                fn ($c) => Schema::hasColumn('shifts_pos', $c)
            )));
        });

        Schema::table('cashier_devices', function (Blueprint $table) {
            $table->dropColumn(array_values(array_filter(
                ['serial_number', 'ip_address', 'max_cash_in_drawer', 'default_printer_id'],
                fn ($c) => Schema::hasColumn('cashier_devices', $c)
            )));
        });

        Schema::table('pos_general_settings', function (Blueprint $table) {
            if (Schema::hasColumn('pos_general_settings', 'branch_id')) {
                $table->dropIndex('pos_settings_branch_idx');
            }

            $table->dropColumn(array_values(array_filter(
                ['branch_id', 'max_discount_percent'],
                fn ($c) => Schema::hasColumn('pos_general_settings', $c)
            )));
        });
    }
};
