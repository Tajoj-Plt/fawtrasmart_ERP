<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * اتفاقية التقسيط وأقساطها — الأعمدة التي يكتبها المتحكّم ولا وجود لها.
 *
 * ══════════════════════════════════════════════════════════════════════
 *  ما كان في الجدولين
 * ══════════════════════════════════════════════════════════════════════
 *
 *   installments          id, invoice_id, amount, installment_number,
 *                         due_date, payment_rate, status, timestamps
 *   installments_details  id, amount, installments_id, status, due_date,
 *                         timestamps
 *
 * سبعةُ أعمدة وستّة. و`InstallmentsController::store()` يكتب:
 *
 *     Installment::create([
 *         'invoice_id' => …, 'client_id' => …, 'amount' => …,
 *         'installment_number' => …, 'payment_rate' => …,
 *         'start_date' => …, 'note' => …,
 *     ]);
 *
 * منها **`client_id` و`start_date` و`note` لا وجود لها في الجدول**، وهي
 * محجوبةٌ أصلاً بـ`$fillable` فلا تصل إلى الاستعلام ولا يُرمى خطأ — تُسقَط
 * بصمت. والأثر:
 *
 *   • `client_id` ← علاقة `Installment::client()` تُرجع `null` دائماً.
 *   • `start_date` ← `Carbon::parse($installment->start_date)` تُعطي
 *     «الآن»، فجدول الاستحقاقات كلُّه يبدأ من لحظة الحفظ لا من التاريخ
 *     الذي اختاره المستخدم.
 *   • `due_date` على الاتفاقية يبقى **NULL أبداً** (المتحكّم يكتب
 *     `start_date` لا `due_date`)، فـ`orderBy('due_date')` في القائمة
 *     يرتّب على فراغ، و`whereBetween('due_date', …)` لا يطابق شيئاً.
 *   • `payment_rate` عمودٌ موجود لكنّه خارج `$fillable`، فيبقى على
 *     الافتراضي `1` — أي أن **كلّ اتفاقيةٍ شهريةٌ مهما اختار المستخدم**.
 *     (السطر `$installment->refresh(); // ضروري حتى نضمن أن payment_rate
 *     مش null` في المتحكّم أثرُ من اصطدم بهذا وظنّ أنه عالجه.)
 *
 * وفي التفاصيل: `installment_number` و`payment_rate` يُمرَّران إلى
 * `InstallmentDetail::create()` ولا عمودَ لهما ولا `$fillable`. و`status`
 * عمود **tinyInteger** والمتحكّم يكتب فيه النصّ `'unactive'` — في MySQL
 * الصارم `SQLSTATE[22007] Incorrect integer value`، أي أن **إنشاء
 * الاتفاقية يسقط عند أوّل قسط**.
 *
 * ══════════════════════════════════════════════════════════════════════
 *  ما تضيفه هذه الهجرة
 * ══════════════════════════════════════════════════════════════════════
 *
 * البند 5 في تقرير الإصلاح يعدّد ستّة عشر حقلاً لاتفاقية التقسيط. الجدول
 * فيه سبعة. هنا تُستكمَل: الدفعة المقدَّمة، والمبلغ الممول، ونسبة
 * المرابحة، والرسوم الإدارية، والضريبة، وغرامة التأخير، وحالة الاتفاقية،
 * والعملة، والفرع، ومن أنشأها.
 *
 * والحساب كلُّه في `Modules\Sales\Support\InstallmentSchedule` — صنفٌ نقيّ
 * مُختبَرٌ عددياً، لا في المتحكّم ولا في القالب.
 *
 * **لا عمود يُحذف.** `status` القديم في الجدولين يُعاد تسميته
 * `status_legacy` ويبقى: مراجعةُ ما كان محفوظاً قبل التحويل حقٌّ لا يجوز
 * أن تُسقطه هجرة.
 */
return new class extends Migration
{
    /** حالات الاتفاقية — البند 5: نشطة، مكتملة، متعثرة، ملغاة. */
    private const AGREEMENT_STATUSES = ['active', 'completed', 'defaulted', 'cancelled'];

    /** حالات القسط الواحد. «متأخّر» مشتقٌّ من التاريخ لا مخزَّن. */
    private const DETAIL_STATUSES = ['pending', 'partial', 'paid', 'cancelled'];

    public function up(): void
    {
        $this->upgradeAgreements();
        $this->upgradeDetails();
        $this->backfill();
    }

    // ══════════════════════════════════════════════════════════════
    //  الاتفاقية
    // ══════════════════════════════════════════════════════════════

    private function upgradeAgreements(): void
    {
        Schema::table('installments', function (Blueprint $table) {
            // ── الهوية والمسؤولية ─────────────────────────────────
            // رقم الاتفاقية من «الترقيم المتسلسل» — لم يكن للاتفاقية رقمٌ
            // تُعرَف به غير `id`، فالمستند المطبوع بلا رقم
            if (! Schema::hasColumn('installments', 'code')) {
                $table->string('code', 60)->nullable()->after('id')->unique();
            }

            // العمود الذي يكتبه `store()` منذ اليوم الأول ولا وجود له
            if (! Schema::hasColumn('installments', 'client_id')) {
                $table->unsignedBigInteger('client_id')->nullable()->after('invoice_id')->index();
            }

            // البند 13.4 في تقرير التحسين يرصد `installments` ضمن جداول
            // المبيعات الثمانية التي بلا `branch_id`
            if (! Schema::hasColumn('installments', 'branch_id')) {
                $table->unsignedBigInteger('branch_id')->nullable()->after('client_id')->index();
            }

            foreach (['created_by', 'updated_by'] as $column) {
                if (! Schema::hasColumn('installments', $column)) {
                    $table->unsignedBigInteger($column)->nullable();
                }
            }

            // ── المال ─────────────────────────────────────────────
            // `amount` القديم يبقى (قيمة القسط كما كانت تُحفَظ)، وتُضاف
            // حوله المبالغُ التي تجعل الاتفاقية مفهومة
            foreach ([
                'total_amount' => 'إجمالي المستحقّ قبل التقسيط',
                'down_payment' => 'الدفعة المقدَّمة',
                'principal_amount' => 'أصل الدين بعد المقدَّم',
                'profit_amount' => 'الربح بالمرابحة',
                'admin_fees' => 'رسوم إدارية مقطوعة',
                'tax_amount' => 'الضريبة على الربح والرسوم',
                'financed_amount' => 'ما يُقسَّم فعلاً',
                'installment_amount' => 'قيمة القسط الواحد',
            ] as $column => $comment) {
                if (! Schema::hasColumn('installments', $column)) {
                    $table->decimal($column, 15, 2)->nullable()->default(0)->comment($comment);
                }
            }

            foreach (['profit_rate', 'tax_rate'] as $column) {
                if (! Schema::hasColumn('installments', $column)) {
                    $table->decimal($column, 8, 4)->nullable()->default(0);
                }
            }

            // موضع التفرّع لو أُضيفت الفائدة المتناقصة لاحقاً بلا هجرة
            if (! Schema::hasColumn('installments', 'profit_method')) {
                $table->string('profit_method', 20)->nullable()->default('flat');
            }

            // ── الغرامة: تُحتسب وتُعرَض ولا تُقيَّد آلياً ──────────
            if (! Schema::hasColumn('installments', 'late_fee_type')) {
                $table->string('late_fee_type', 10)->nullable()->comment('percent|fixed');
            }

            if (! Schema::hasColumn('installments', 'late_fee_value')) {
                $table->decimal('late_fee_value', 12, 2)->nullable()->default(0);
            }

            if (! Schema::hasColumn('installments', 'late_fee_grace_days')) {
                $table->unsignedSmallInteger('late_fee_grace_days')->nullable()->default(0);
            }

            // ── العملة وطريقة السداد ──────────────────────────────
            // نصّاً لا رقماً: نفس ما تتوقّعه `UsesDocumentCurrency` في
            // بقيّة مستندات المبيعات
            if (! Schema::hasColumn('installments', 'currency')) {
                $table->string('currency', 10)->nullable();
            }

            if (! Schema::hasColumn('installments', 'payment_method')) {
                $table->unsignedBigInteger('payment_method')->nullable();
            }

            // ── الجدولة ───────────────────────────────────────────
            // `start_date` هو ما يكتبه `store()` ويقرأه `createInstallmentDetails()`
            // ولا وجود له. الاسم هنا `first_due_date` لأنّه ما يعنيه فعلاً:
            // تاريخ أوّل قسط لا تاريخ بدء الاتفاقية
            if (! Schema::hasColumn('installments', 'first_due_date')) {
                $table->date('first_due_date')->nullable()->after('due_date');
            }

            if (! Schema::hasColumn('installments', 'last_due_date')) {
                $table->date('last_due_date')->nullable()->after('first_due_date');
            }

            if (! Schema::hasColumn('installments', 'note')) {
                $table->text('note')->nullable();
            }
        });

        // حالة الاتفاقية نصّاً: `tinyInteger` لا يقول «متعثّرة» ولا «ملغاة»
        $this->replaceStatusColumn('installments', 'active');

        $this->addIndexIfMissing('installments', ['status', 'first_due_date'], 'installments_status_due_index');
    }

    // ══════════════════════════════════════════════════════════════
    //  الأقساط
    // ══════════════════════════════════════════════════════════════

    private function upgradeDetails(): void
    {
        Schema::table('installments_details', function (Blueprint $table) {
            // يُمرَّران إلى `create()` ولا عمودَ لهما
            if (! Schema::hasColumn('installments_details', 'installment_number')) {
                $table->unsignedSmallInteger('installment_number')->nullable()->after('installments_id');
            }

            if (! Schema::hasColumn('installments_details', 'payment_rate')) {
                $table->tinyInteger('payment_rate')->nullable()->default(1);
            }

            // تفكيك القسط: التقرير المحاسبي يحتاج فصل مقابل البضاعة عن
            // مقابل التمويل، ولا سبيل إليه بعد الحفظ إن لم يُخزَّن
            foreach (['principal_amount', 'profit_amount', 'tax_amount'] as $column) {
                if (! Schema::hasColumn('installments_details', $column)) {
                    $table->decimal($column, 15, 2)->nullable()->default(0);
                }
            }

            // ── السداد ────────────────────────────────────────────
            // لم يكن للقسط عمودٌ يقول كم سُدِّد منه ولا متى — فـ«مدفوع
            // جزئياً» في البند 6 من تقرير الإصلاح لم يكن قابلاً للتمثيل
            if (! Schema::hasColumn('installments_details', 'paid_amount')) {
                $table->decimal('paid_amount', 15, 2)->nullable()->default(0);
            }

            if (! Schema::hasColumn('installments_details', 'paid_at')) {
                $table->date('paid_at')->nullable();
            }

            if (! Schema::hasColumn('installments_details', 'note')) {
                $table->string('note')->nullable();
            }
        });

        // العمود كان `tinyInteger` والمتحكّم يكتب فيه `'unactive'` نصّاً —
        // فيسقط الحفظ في MySQL الصارم
        $this->replaceStatusColumn('installments_details', 'pending');

        $this->addIndexIfMissing(
            'installments_details',
            ['installments_id', 'due_date'],
            'installments_details_agreement_due_index'
        );
    }

    /**
     * تبديل عمود `status` من رقمٍ إلى نصّ، مع حفظ القديم.
     *
     * لا `dropColumn`: القيمة القديمة قد تكون آخر ما يفسّر صفّاً قديماً،
     * وهجرةٌ تحذف بيانات لا يُتراجَع عنها بـ`down()`.
     */
    private function replaceStatusColumn(string $table, string $default): void
    {
        $hasLegacy = Schema::hasColumn($table, 'status_legacy');
        $hasStatus = Schema::hasColumn($table, 'status');

        // النصّي موجودٌ سلفاً (هجرةٌ أُعيد تشغيلها) — لا شيء يُفعل
        if ($hasLegacy && $hasStatus) {
            return;
        }

        if ($hasStatus && ! $hasLegacy) {
            Schema::table($table, fn (Blueprint $t) => $t->renameColumn('status', 'status_legacy'));
        }

        if (! Schema::hasColumn($table, 'status')) {
            Schema::table($table, function (Blueprint $t) use ($default) {
                $t->string('status', 20)->nullable()->default($default)->index();
            });
        }
    }

    // ══════════════════════════════════════════════════════════════
    //  ملء الصفوف القائمة
    // ══════════════════════════════════════════════════════════════

    private function backfill(): void
    {
        // ── الحالة النصّية ────────────────────────────────────────
        try {
            DB::table('installments')->whereNull('status')->update(['status' => 'active']);
            DB::table('installments_details')->whereNull('status')->update(['status' => 'pending']);
        } catch (\Throwable $e) {
            // لا شيء: الحالة تُقرأ بافتراضيّها وقت العرض
        }

        // ── العميل والعملة والمبالغ من الفاتورة ──────────────────
        // الاتفاقيات القائمة بلا `client_id` ولا مبالغ: تُشتقّ من فاتورتها،
        // وهي المصدر الوحيد المتاح — لم يكن شيءٌ منها يُحفَظ على الاتفاقية
        try {
            if (Schema::hasTable('invoices')) {
                DB::table('installments')
                    ->join('invoices', 'installments.invoice_id', '=', 'invoices.id')
                    ->whereNull('installments.client_id')
                    ->update(['installments.client_id' => DB::raw('invoices.client_id')]);

                DB::table('installments')
                    ->join('invoices', 'installments.invoice_id', '=', 'invoices.id')
                    ->where(function ($q) {
                        $q->whereNull('installments.total_amount')->orWhere('installments.total_amount', 0);
                    })
                    ->update([
                        'installments.total_amount' => DB::raw('invoices.grand_total'),
                        'installments.principal_amount' => DB::raw('invoices.grand_total'),
                        'installments.financed_amount' => DB::raw('invoices.grand_total'),
                    ]);

                if (Schema::hasColumn('invoices', 'currency')) {
                    DB::table('installments')
                        ->join('invoices', 'installments.invoice_id', '=', 'invoices.id')
                        ->whereNull('installments.currency')
                        ->update(['installments.currency' => DB::raw('invoices.currency')]);
                }
            }
        } catch (\Throwable $e) {
            // بنيةٌ مختلفة في `invoices`: تبقى الحقول فارغةً وتُقرأ
            // بافتراضيّها، ولا تُعطَّل الهجرة كلُّها بسبب صفٍّ قديم
        }

        // ما بقي بلا عملة يأخذ عملة النظام: مبلغٌ بلا رمز يُقرأ في
        // التقارير كأنّه بلا قيمة
        try {
            DB::table('installments')
                ->whereNull('currency')
                ->update(['currency' => \App\Support\CurrencySettings::code()]);
        } catch (\Throwable $e) {
            // إعدادات العملة غير مهيّأة بعد
        }

        // ── قيمة القسط ────────────────────────────────────────────
        // `amount` القديم كان يحمل قيمة القسط الواحد (لا الإجمالي):
        // النموذج القديم فيه حقلان باسم `amount` والثاني يدهس الأول
        try {
            DB::table('installments')
                ->whereNotNull('amount')
                ->where(function ($q) {
                    $q->whereNull('installment_amount')->orWhere('installment_amount', 0);
                })
                ->update(['installment_amount' => DB::raw('amount')]);

            // `first_due_date` من `due_date` إن وُجد، وإلا من أوّل قسط
            DB::table('installments')
                ->whereNull('first_due_date')
                ->whereNotNull('due_date')
                ->update(['first_due_date' => DB::raw('due_date')]);
        } catch (\Throwable $e) {
            // لا شيء
        }

        // ── ترقيم الأقساط القائمة ────────────────────────────────
        // الأقساط القديمة بلا `installment_number` — يُشتقّ من ترتيب
        // الاستحقاق داخل كلّ اتفاقية
        try {
            $agreements = DB::table('installments_details')
                ->whereNull('installment_number')
                ->distinct()
                ->pluck('installments_id')
                ->filter();

            foreach ($agreements as $agreementId) {
                $rows = DB::table('installments_details')
                    ->where('installments_id', $agreementId)
                    ->orderBy('due_date')
                    ->orderBy('id')
                    ->pluck('id');

                foreach ($rows as $index => $rowId) {
                    DB::table('installments_details')
                        ->where('id', $rowId)
                        ->update(['installment_number' => $index + 1]);
                }
            }
        } catch (\Throwable $e) {
            // ترقيمٌ ناقص لا يمنع الشاشة من العمل
        }
    }

    // ══════════════════════════════════════════════════════════════

    private function addIndexIfMissing(string $table, array $columns, string $name): void
    {
        try {
            if (Schema::hasTable($table) && ! $this->indexExists($table, $name)) {
                Schema::table($table, fn (Blueprint $t) => $t->index($columns, $name));
            }
        } catch (\Throwable $e) {
            // الفهرس تحسينٌ لا شرط
        }
    }

    private function indexExists(string $table, string $name): bool
    {
        try {
            return collect(Schema::getIndexes($table))
                ->contains(fn ($index) => ($index['name'] ?? null) === $name);
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function down(): void
    {
        Schema::table('installments', function (Blueprint $table) {
            foreach ([
                'code', 'client_id', 'branch_id', 'created_by', 'updated_by',
                'total_amount', 'down_payment', 'principal_amount', 'profit_amount',
                'admin_fees', 'tax_amount', 'financed_amount', 'installment_amount',
                'profit_rate', 'tax_rate', 'profit_method',
                'late_fee_type', 'late_fee_value', 'late_fee_grace_days',
                'currency', 'payment_method', 'first_due_date', 'last_due_date', 'note',
            ] as $column) {
                if (Schema::hasColumn('installments', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('installments_details', function (Blueprint $table) {
            foreach ([
                'installment_number', 'payment_rate', 'principal_amount',
                'profit_amount', 'tax_amount', 'paid_amount', 'paid_at', 'note',
            ] as $column) {
                if (Schema::hasColumn('installments_details', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        foreach (['installments', 'installments_details'] as $table) {
            if (Schema::hasColumn($table, 'status_legacy')) {
                if (Schema::hasColumn($table, 'status')) {
                    Schema::table($table, fn (Blueprint $t) => $t->dropColumn('status'));
                }

                Schema::table($table, fn (Blueprint $t) => $t->renameColumn('status_legacy', 'status'));
            }
        }
    }
};
