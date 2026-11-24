<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * إصلاحُ مرجعِ الضريبة ومبلغِها في السندات القائمة.
 *
 * **عطبان اجتمعا:**
 *
 * ١) قائمةُ الضريبة كانت تُرسل **النسبة** (`0.15`) بدل **المعرّف**، وعمودُ
 *    `tax1` نوعه `tinyint` — فتُخزَّن **صفراً**. فالسندُ يبدو بلا ضريبةٍ
 *    مختارة وإن كان مبلغُها مسجَّلاً.
 *
 * ٢) حسابُ سند القبض كان يقسم على 100 والنسبةُ مخزَّنةٌ كسراً — فخرجت
 *    ضريبةُ مئةٍ بمقدار **0.15** بدل 15، ودخل هذا الرقمُ الخاطئ إجماليَّ
 *    السند.
 *
 * **كيف يُستدَلّ على الضريبة؟** بنسبةٍ ضمنية: `tax_amount ÷ amount`. فإن
 * طابقت نسبةً معرَّفةً فالمبلغُ صحيحٌ والمرجعُ وحدَه ضاع. وإن طابقت
 * **نسبةً مقسومةً على مئة** فهو أثرُ العطب الثاني: يُصحَّح المبلغُ
 * والإجمالي معاً.
 *
 * وما لم تُطابِق نسبتُه شيئاً **يُترك كما هو**: التخمينُ في المبالغ أسوأ
 * من تركها — تُراجَع يدوياً.
 */
return new class extends Migration
{
    private const TOLERANCE = 0.0005;

    public function up(): void
    {
        $rates = $this->definedRates();

        if ($rates === []) {
            return;
        }

        foreach ([['expenses', 'App\Models\Expense'], ['receipts', 'App\Models\Receipt']] as [$table, $_]) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'tax1')) {
                continue;
            }

            $rows = DB::table($table)
                ->where(function ($q) {
                    $q->where('tax1_amount', '>', 0)->orWhere('tax2_amount', '>', 0);
                })
                ->where(function ($q) {
                    $q->where('tax1', 0)->orWhereNull('tax1')
                        ->orWhere('tax2', 0)->orWhereNull('tax2');
                })
                ->get();

            foreach ($rows as $row) {
                $this->repairRow($table, $row, $rates);
            }
        }
    }

    /** نسبةُ كل ضريبة معرَّفة، ككسر. */
    private function definedRates(): array
    {
        if (! Schema::hasTable('tax_sittings')) {
            return [];
        }

        $out = [];
        foreach (DB::table('tax_sittings')->get() as $tax) {
            $rate = (float) $tax->tax;
            if ($rate <= 0) {
                continue;
            }
            // تُقبل الصيغتان: كسرٌ (0.15) ونسبةٌ مئوية (15)
            $out[(int) $tax->id] = $rate < 1 ? $rate : $rate / 100;
        }

        return $out;
    }

    private function repairRow(string $table, object $row, array $rates): void
    {
        $amount = (float) $row->amount;

        if ($amount <= 0) {
            return;
        }

        $update = [];
        $taxTotal = 0.0;

        foreach (['tax1', 'tax2'] as $field) {
            $paid = (float) ($row->{$field.'_amount'} ?? 0);

            if ($paid <= 0) {
                continue;
            }

            $implied = $paid / $amount;
            $matched = null;
            $corrected = null;

            foreach ($rates as $id => $rate) {
                if (abs($implied - $rate) <= self::TOLERANCE) {
                    // المبلغُ صحيح، والمرجعُ وحدَه ضاع
                    $matched = $id;
                    break;
                }

                if (abs($implied - ($rate / 100)) <= self::TOLERANCE) {
                    // أثرُ القسمة على مئة: يُصحَّح المبلغ أيضاً
                    $matched = $id;
                    $corrected = round($amount * $rate, 2);
                    break;
                }
            }

            if ($matched === null) {
                // نسبةٌ لا تُطابق شيئاً — تُترك للمراجعة اليدوية
                $taxTotal += $paid;

                continue;
            }

            if (empty($row->{$field})) {
                $update[$field] = $matched;
            }

            if ($corrected !== null) {
                $update[$field.'_amount'] = $corrected;
                $taxTotal += $corrected;
            } else {
                $taxTotal += $paid;
            }
        }

        if ($update === []) {
            return;
        }

        // الإجمالي يُعاد بناؤه من المبلغ والضريبة المصحَّحة
        if (Schema::hasColumn($table, 'total_amount')) {
            $update['total_amount'] = round($amount + $taxTotal, 2);
        }

        if (Schema::hasColumn($table, 'is_taxable')) {
            $update['is_taxable'] = true;
        }

        DB::table($table)->where('id', $row->id)->update($update);
    }

    /**
     * لا تراجُع.
     *
     * الأصلُ بياناتٌ خاطئة — وإعادتُها ليست استرجاعاً بل إفساداً ثانياً.
     */
    public function down(): void
    {
        // بلا فعل: لا يُعاد المبلغُ الخاطئ.
    }
};
