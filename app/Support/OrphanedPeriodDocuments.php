<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * المستنداتُ التي وقعت خارج التقويم المحاسبي.
 *
 * **متى يقع هذا؟**
 *
 *   • الفتراتُ لم تُولَّد بعد — منشأةٌ جديدة، أو سنةٌ لم يُرسَم تقويمُها.
 *   • فجوةٌ بين سنتين: تنتهي واحدةٌ في ٢٥ وتبدأ التالية في ٢٧، ومستندُ
 *     السادس والعشرين لا يقع في أيّهما.
 *   • تاريخٌ بعيد أُدخل سهواً — سنةٌ في المستقبل أو في ماضٍ سحيق.
 *
 * **وماذا يجري له؟**
 *
 * يُحفَظ بفترةٍ فارغة. ومنعُ الحفظ شأنُ ضابط الإقفال في «إعدادات الفترات»
 * (`enforce_lock` مع `block_undefined_dates`)، لا شأنُ بنية البيانات — فلا
 * يصحّ أن يُرفض مستندٌ لأنّ التقويم لم يُضبط بعد.
 *
 * **لكنّه لا يجوز أن يبقى مخفيّاً.** الشاشاتُ تُقرأ على فترة، فمستندٌ بلا
 * فترة لا يظهر في أيٍّ منها: لا في القوائم، ولا في التقارير، ولا في الإقرار
 * الضريبي — وهو قائمٌ في الدفتر، ورصيدُ الحساب يحمله. فيُعَدّ هنا ويُعرَض،
 * ويُلحَق بفتراته حين تُرسَم (`accounting:refile-periods`).
 */
class OrphanedPeriodDocuments
{
    /**
     * الجدول ← [الاسم المعروض، عمود التاريخ الحاكم].
     *
     * هو الترتيبُ نفسُه الذي تختم به `BelongsToAccountingPeriod`، فلا يفترق
     * ما يُعَدّ عمّا يُختَم.
     */
    public const TABLES = [
        'journal_entries' => ['قيود اليومية', 'date'],
        'expenses' => ['سندات الصرف', 'date'],
        'receipts' => ['سندات القبض', 'date'],
        'payable_cheques' => ['شيكات الدفع', 'issue_date'],
        'received_cheques' => ['الشيكات المستلمة', 'issue_date'],
        'cheque_books' => ['دفاتر الشيكات', 'created_at'],
        'asset_dep' => ['الأصول', 'last_dep_date'],
        'payments_process' => ['عمليات السداد', 'payment_date'],

        /*
            المبيعات والمشتريات: كانت ناقصةً من هذه القائمة وهي تُختم بالفترة
            كغيرها (`BelongsToAccountingPeriod` على Invoice وPurchaseInvoice
            وCreditNotification وPosSession) — فبقيت فواتيرُ سنةٍ رُسمت بعدها
            بلا فترة لا تظهر في قوائم سنتها ولا تُعَدّ فيها، ولا يربطها الزرّ.
        */
        'invoices' => ['فواتير المبيعات ومرتجعاتها', 'invoice_date'],
        'purchase_invoices' => ['فواتير الشراء ومرتجعاتها', 'date'],
        'credit_notifications' => ['الإشعارات الدائنة', 'credit_date'],
        'pos_sessions' => ['جلسات نقاط البيع', 'started_at'],
        'pos_cash_movements' => ['حركات درج نقاط البيع', 'movement_time'],
        'pos_order_payments' => ['مدفوعات طلبات نقاط البيع', 'created_at'],
        'pos_audit_logs' => ['سجل تدقيق نقاط البيع', 'created_at'],

        // الحجوزاتُ ودفعاتُ العقود — كلٌّ بتاريخه الحاكم في مودله
        'reservations' => ['الحجوزات', 'reservation_date'],
        're_payments' => ['دفعات العقود العقارية', 'payment_date'],

        /*
            المخزون: إذنُ المخزن وتسويةُ الجرد عمليتان محاسبيتان تُولّدان
            قيداً يمسّ حساب المخزون — فتُعَدّان هنا كما تُعَدّ السندات.
        */
        'warehouse_permits' => ['أذون المخزن', 'permission_date'],
        'inventory_adjustments' => ['تسويات الجرد', 'inventory_time'],
    ];

    /**
     * عددُ ما خرج عن التقويم، مفصَّلاً بجداوله.
     *
     * @return array{total: int, tables: array<string, array{label: string, count: int}>}
     */
    public static function summary(): array
    {
        $tables = [];
        $total = 0;

        foreach (self::TABLES as $table => [$label, $dateColumn]) {
            $count = self::countIn($table);

            if ($count > 0) {
                $tables[$table] = ['label' => $label, 'count' => $count];
                $total += $count;
            }
        }

        return ['total' => $total, 'tables' => $tables];
    }

    /** عددُ ما خرج عن التقويم في جدولٍ بعينه. */
    public static function countIn(string $table): int
    {
        try {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'accounting_period_id')) {
                return 0;
            }

            return DB::table($table)->whereNull('accounting_period_id')->count();
        } catch (Throwable $e) {
            // جدولٌ غير مهيّأ في تركيبٍ ما: لا يُسقط الشاشة
            return 0;
        }
    }

    /**
     * إلحاقُ ما خرج عن التقويم بفتراته — بعد رسمها.
     *
     * تحديثٌ واحدٌ لكلّ فترة لا استعلامٌ لكلّ مستند: الفتراتُ عشراتٌ بينما
     * المستندات قد تكون مئات الآلاف.
     *
     * @param  bool  $dryRun  يَعُدّ ولا يكتب
     * @return array<string, int> الجدول ← عددُ ما أُلحق
     */
    /**
     * مطابقةُ الختم للتاريخ في مدىً أُعيد ترسيمُ فتراته — الفتراتُ المفتوحة وحدها.
     *
     * توليدُ فترات السنة يحذف المفتوحةَ ويُعيد إنشاءها (فيُفرغ المفتاحُ الأجنبيّ
     * ختمَ كلّ مستندٍ فيها)، وتعديلُ حدود فترةٍ يُبقي مستنداتٍ مختومةً بها وقد
     * خرج تاريخُها عنها — فتختفي من قوائم سنتها أو تُعدّ في غير فترتها.
     *
     * المقفلةُ لا تُمسّ، ختماً ومصدراً: الدفترُ المقفل لا يُعاد تصنيفه، وتلك
     * هي علّةُ الختم أصلاً. والفارغُ داخل المدى يُختم: المدى رُسم الآن بيد
     * المستخدم، فالنسبةُ إليه هي نتيجةُ قراره لا تخمين.
     *
     * @return array<string, int> الجدول ← عددُ ما أُعيد ختمُه
     */
    public static function resyncRange(string $from, string $to): array
    {
        $changed = [];

        foreach (self::TABLES as $table => [$label, $dateColumn]) {
            if (! Schema::hasTable($table)
                || ! Schema::hasColumn($table, 'accounting_period_id')
                || ! Schema::hasColumn($table, $dateColumn)) {
                continue;
            }

            $count = DB::update(
                "UPDATE `{$table}` AS doc
                    LEFT JOIN accounting_periods AS current_period ON current_period.id = doc.accounting_period_id
                    LEFT JOIN accounting_periods AS dated_period
                        ON DATE(doc.`{$dateColumn}`) BETWEEN dated_period.start_date AND dated_period.end_date
                SET doc.accounting_period_id = dated_period.id
                WHERE DATE(doc.`{$dateColumn}`) BETWEEN ? AND ?
                    AND NOT (doc.accounting_period_id <=> dated_period.id)
                    AND (current_period.id IS NULL OR current_period.status = 'open')
                    AND (dated_period.id IS NULL OR dated_period.status = 'open')",
                [$from, $to]
            );

            if ($count > 0) {
                $changed[$table] = $count;
            }
        }

        return $changed;
    }

    public static function refile(bool $dryRun = false): array
    {
        $periods = DB::table('accounting_periods')
            ->select('id', 'start_date', 'end_date')
            ->orderBy('start_date')
            ->get();

        $filed = [];

        foreach (self::TABLES as $table => [$label, $dateColumn]) {
            if (! Schema::hasTable($table)
                || ! Schema::hasColumn($table, 'accounting_period_id')
                || ! Schema::hasColumn($table, $dateColumn)) {
                continue;
            }

            $count = 0;

            foreach ($periods as $period) {
                $matching = DB::table($table)
                    ->whereNull('accounting_period_id')
                    ->whereDate($dateColumn, '>=', $period->start_date)
                    ->whereDate($dateColumn, '<=', $period->end_date);

                if ($dryRun) {
                    $count += $matching->count();

                    continue;
                }

                $count += $matching->update(['accounting_period_id' => $period->id]);
            }

            if ($count > 0) {
                $filed[$table] = $count;
            }
        }

        return $filed;
    }
}
