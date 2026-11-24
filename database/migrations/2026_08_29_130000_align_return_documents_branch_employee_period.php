<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * الفرعُ والموظّفُ والفترة على المستندات العكسية — كما هي على الفاتورة.
 *
 * ═════════════════════ ما كان ═════════════════════
 *
 * فاتورةُ المبيعات تختم سياقَها الثلاثة لحظةَ إنشائها: `branch_id` يُجمَّد
 * فلا يتبع تبديلَ منشئه فرعَه، و`employee_id` يقول لمن تُنسب، و
 * `accounting_period_id` يقول في أيّ فترةٍ وقعت (انظر
 * `BelongsToAccountingPeriod` و`StampsOwnerContext`).
 *
 * والمستنداتُ التي **تعكس** تلك الفاتورة لم تكن تختم مثلها:
 *
 *   • `credit_notifications` (الإشعار الدائن) — فرعٌ وموظّف، بلا فترة.
 *   • `purchase_invoices` (فاتورة الشراء ومرتجعُها وإشعارُها المدين) —
 *     فرعٌ وحده: لا عمودَ موظّفٍ أصلاً، ولا فترة.
 *
 * وأثرُ ذلك ليس نظرياً: تقريرُ فترةٍ يجمع مبيعاتِها من `accounting_period_id`
 * ويجمع مرتجعاتِها بمقارنةِ تواريخ — فإن حُرّكت حدودُ الفترة اختلف الطرفان،
 * وخرج صافي المبيعات لا يوافق ميزانَ المراجعة للفترة نفسِها. ومرتجعُ شراءٍ
 * لا يُعرف موظّفُه إلّا بقفزةٍ إلى `users` ثمّ إلى `employees` — وهي قفزةٌ
 * تُعيد كتابةَ التاريخ كلّما نُقل الموظّف.
 *
 * ═════════════════════ والأعمدة تقبل الفراغ ═════════════════════
 *
 * مستندٌ تاريخُه خارج كلّ فترةٍ معرَّفة يُحفَظ بلا فترة ويُرى في «بلا فترة»
 * ليُصحَّح — ومنعُ الحفظ شأنُ ضابط الإقفال لا شأنُ بنية الجدول. وحسابُ نظامٍ
 * لا سجلَّ موظفٍ له يُنشئ مستنداً بلا موظّف، وذاك واقعٌ لا خطأ.
 *
 * @see \App\Models\Concerns\BelongsToAccountingPeriod
 * @see \App\Models\Concerns\StampsOwnerContext
 */
return new class extends Migration
{
    /** الجدول ← العمود الذي يحكم فترتَه (تاريخُ الحدث المحاسبي لا تاريخُ الإدخال) */
    private const PERIOD_TABLES = [
        'credit_notifications' => 'credit_date',
        'purchase_invoices' => 'date',
    ];

    public function up(): void
    {
        /* ── الموظّف على مستندات المشتريات ──
           `credit_notifications` تحمله أصلاً، و`purchase_invoices` لا. */
        if (Schema::hasTable('purchase_invoices') && ! Schema::hasColumn('purchase_invoices', 'employee_id')) {
            Schema::table('purchase_invoices', function (Blueprint $table) {
                if (! Schema::hasColumn('purchase_invoices', 'employee_id')) {
                    $table->unsignedBigInteger('employee_id')->nullable()->after('supplier_id')->index();
                }
            });

            $this->backfillEmployee();
        }

        foreach (self::PERIOD_TABLES as $table => $dateColumn) {
            if (! Schema::hasTable($table) || Schema::hasColumn($table, 'accounting_period_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                $blueprint->unsignedBigInteger('accounting_period_id')->nullable();

                /*
                    الاستعلامُ الغالب في `purchase_invoices` هو نوعُ المستند
                    داخل فترة — فالجدولُ واحدٌ لأربعة أنواع. أمّا
                    `credit_notifications` فنوعٌ واحد، فالفهرسُ على الفترة وحدها.
                */
                if ($table === 'purchase_invoices') {
                    $blueprint->index(['type', 'accounting_period_id'], 'purchase_invoices_type_period_idx');
                } else {
                    $blueprint->index('accounting_period_id');
                }
            });

            $this->backfillPeriod($table, $dateColumn);
        }
    }

    /**
     * موظّفُ المستند القائم = موظّفُ منشئه.
     *
     * تحديثٌ واحدٌ بانضمامٍ لا استعلامٌ لكلّ صفّ. ويبقى فارغاً لمن أنشأه
     * حسابٌ لا سجلَّ موظفٍ له — وهو ما تفعله `StampsOwnerContext` نفسُها.
     */
    private function backfillEmployee(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'employee_id')) {
            return;
        }

        // UPDATE مع JOIN: يُصرّفه Laravel لـMySQL بجملةٍ واحدة، أما SQLite فيبني
        // صيغةً لا تفهم users.employee_id داخل SET. الجملةُ الفرعيّة تعمل هناك.
        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::statement('
                UPDATE purchase_invoices
                SET employee_id = (
                    SELECT u.employee_id FROM users u WHERE u.id = purchase_invoices.created_by
                )
                WHERE employee_id IS NULL
                  AND EXISTS (
                    SELECT 1 FROM users u
                    WHERE u.id = purchase_invoices.created_by AND u.employee_id IS NOT NULL
                  )
            ');

            return;
        }

        DB::table('purchase_invoices')
            ->join('users', 'users.id', '=', 'purchase_invoices.created_by')
            ->whereNull('purchase_invoices.employee_id')
            ->whereNotNull('users.employee_id')
            ->update(['purchase_invoices.employee_id' => DB::raw('users.employee_id')]);
    }

    /**
     * إلحاقُ القائم: كلُّ مستندٍ يقع تاريخُه في فترةٍ يُنسَب إليها.
     *
     * تحديثٌ واحدٌ لكلّ فترة — والفترات عشراتٌ بينما المستندات قد تكون ألوفاً.
     */
    private function backfillPeriod(string $table, string $dateColumn): void
    {
        if (! Schema::hasTable('accounting_periods') || ! Schema::hasColumn($table, $dateColumn)) {
            return;
        }

        foreach (DB::table('accounting_periods')->select('id', 'start_date', 'end_date')->get() as $period) {
            DB::table($table)
                ->whereNull('accounting_period_id')
                ->whereNotNull($dateColumn)
                ->whereDate($dateColumn, '>=', $period->start_date)
                ->whereDate($dateColumn, '<=', $period->end_date)
                ->update(['accounting_period_id' => $period->id]);
        }
    }

    public function down(): void
    {
        foreach (self::PERIOD_TABLES as $table => $dateColumn) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'accounting_period_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                if ($table === 'purchase_invoices') {
                    $blueprint->dropIndex('purchase_invoices_type_period_idx');
                } else {
                    // بالأعمدة لا بالاسم: `dropIndex` تشتقّ الاسمَ منها،
                    // وتمريرُ الاسم داخل مصفوفةٍ يُولّد اسماً آخر لا وجود له
                    $blueprint->dropIndex(['accounting_period_id']);
                }

                $blueprint->dropColumn('accounting_period_id');
            });
        }

        if (Schema::hasTable('purchase_invoices') && Schema::hasColumn('purchase_invoices', 'employee_id')) {
            Schema::table('purchase_invoices', function (Blueprint $table) {
                $table->dropIndex(['employee_id']);
                $table->dropColumn('employee_id');
            });
        }
    }
};
