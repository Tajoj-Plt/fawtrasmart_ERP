<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ملءُ `accounting_period_id` رجعياً للمستندات السابقة على الختم.
 *
 * ═════════════════════ المسألة ═════════════════════
 *
 * الفترةُ تُختَم في المستند لحظةَ إنشائه (`BelongsToAccountingPeriod`)،
 * وشاشاتُ العرض تُفلتر **بالعلاقة لا بالتاريخ** — والنطاق الافتراضي
 * السنةُ المالية الجارية (`AccountingPeriodFilter`).
 *
 * لكنّ كلَّ ما أُنشئ **قبل** إضافة العمود وتفعيل الختم بقي `NULL` —
 * فيسقط من فلتر السنة كلِّه. والأثرُ الظاهر للمستخدم: شاشةُ الأذونات
 * المخزنية «تعرض أذونَ الشهر الحالي فقط» — ليس لأن الفلتر شهريّ، بل لأن
 * المختوم فعلاً هو ما أُنشئ بعد نشر الختم، وما قبله غير مرئيّ إلا عبر
 * خيار «بلا فترة».
 *
 * ═════════════════════ الحلّ ═════════════════════
 *
 * لكل جدولٍ يستعمل السِّمة: يُنسَب كلُّ صفٍّ `NULL` إلى الفترة التي يقع
 * فيها **تاريخُه الحاكم** — العمودُ نفسُه الذي تقرؤه
 * `accountingDateColumn()` في مودله، فلا يختلف الملءُ الرجعي عن الختم
 * الحيّ. ومن كان تاريخُه الحاكم فارغاً يُنسَب بـ`created_at` — كما تفعل
 * السِّمةُ نفسُها حين لا تاريخ (`Carbon::now()` عند الإنشاء).
 *
 * ما بقي بعد ذلك `NULL` فهو خارجُ التقويم فعلاً (تاريخُه لا تحويه أيُّ
 * فترةٍ معرَّفة) — وذاك بالضبط ما وُضع خيارُ «بلا فترة» ليكشفه، فلا يُلفَّق
 * له انتماء.
 *
 * الترحيلُ مُتكرِّرُ التشغيل بلا أثر (يمسّ `NULL` وحدها)، ويتخطّى بهدوء
 * كلَّ جدولٍ لم يصله عمودُ العلاقة بعد.
 */
return new class extends Migration
{
    /** الجدول ⇐ عموده الحاكم — مطابقاً لـaccountingDateColumn() في مودله */
    private const TABLES = [
        'asset_dep' => 'last_dep_date',
        'cheque_books' => 'created_at',
        'credit_notifications' => 'credit_date',
        'expenses' => 'date',
        'inventory_adjustments' => 'inventory_time',
        'invoices' => 'invoice_date',
        'journal_entries' => 'date',
        'payable_cheques' => 'issue_date',
        'payments_process' => 'payment_date',
        'pos_sessions' => 'started_at',
        'purchase_invoices' => 'date',
        'receipts' => 'date',
        'received_cheques' => 'issue_date',
        'warehouse_permits' => 'permission_date',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('accounting_periods')) {
            return;
        }

        $periods = DB::table('accounting_periods')
            ->whereNotNull('start_date')
            ->whereNotNull('end_date')
            ->get(['id', 'start_date', 'end_date']);

        if ($periods->isEmpty()) {
            return;
        }

        foreach (self::TABLES as $table => $dateColumn) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'accounting_period_id')) {
                continue;
            }

            $hasDateColumn = Schema::hasColumn($table, $dateColumn);
            $hasCreatedAt = Schema::hasColumn($table, 'created_at');

            foreach ($periods as $period) {
                // النسبة بالتاريخ الحاكم أولاً
                if ($hasDateColumn) {
                    DB::table($table)
                        ->whereNull('accounting_period_id')
                        ->whereNotNull($dateColumn)
                        ->whereBetween(DB::raw("DATE(`$dateColumn`)"), [$period->start_date, $period->end_date])
                        ->update(['accounting_period_id' => $period->id]);
                }

                // ومن لا تاريخَ حاكماً له: بتاريخ إنشائه — كما تختم السِّمةُ الحيّة
                if ($hasCreatedAt && $dateColumn !== 'created_at') {
                    DB::table($table)
                        ->whereNull('accounting_period_id')
                        ->when($hasDateColumn, fn ($q) => $q->whereNull($dateColumn))
                        ->whereNotNull('created_at')
                        ->whereBetween(DB::raw('DATE(`created_at`)'), [$period->start_date, $period->end_date])
                        ->update(['accounting_period_id' => $period->id]);
                }
            }
        }
    }

    /**
     * لا تراجعَ فعليّاً: بعد الملء لا يُميَّز المختومُ رجعياً عن المختوم
     * لحظةَ الإنشاء، وتصفيرُهما معاً يُفقد الشاشات فلترتَها. والإبقاءُ
     * على الختم لا يضرّ — هو وصفٌ صحيح لموقع المستند في التقويم.
     */
    public function down(): void
    {
        // عمداً بلا أثر
    }
};
