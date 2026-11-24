<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * هل استُعملت العملة في حركة فعلية؟
 *
 * **لماذا؟** تغيير العملة الافتراضية بعد تسجيل الحركات لا يُعيد تسعير ما
 * سبق: الفواتير والقيود تبقى بأرقامها القديمة بينما يقرأها النظام على أنها
 * بالعملة الجديدة — فتنقلب الميزانية بصمت. لذا يُقفَل التبديل متى وُجدت
 * حركة، ويبقى مفتوحاً على نظام لم يبدأ العمل بعد.
 *
 * الحركات نوعان:
 *
 * - **موسومة** بعمود عملة: تُحسب لرمز العملة المطلوب وحده.
 * - **ضمنية** بلا عمود عملة (سندات القبض والصرف والدفعات ومشتريات):
 *   محرَّرة بالعملة الافتراضية بحكم الأمر الواقع، فتُحسب للافتراضية فقط.
 *
 * الجداول تُفحص قبل الاستعلام: الوحدات تُعطَّل من `modules_statuses.json`
 * وقواعد بيانات العملاء ليست متطابقة، فجدولٌ غائب يعني «لا حركة» لا خطأً.
 */
class CurrencyUsage
{
    /**
     * جداول تحمل رمز العملة صراحةً: [الجدول, العمود, مفتاح الترجمة]
     *
     * @var array<int, array{0:string,1:string,2:string}>
     */
    private const TAGGED = [
        ['invoices', 'currency', 'invoices'],
        ['journal_entries', 'currency', 'journal_entries'],
        ['journal_entry_details', 'currency', 'journal_entries'],
        ['clients', 'currency', 'clients'],
        ['suppliers', 'currency', 'suppliers'],
        ['contracts', 'currency', 'contracts'],
        ['salary_slips', 'currency', 'salary_slips'],
        ['bank_accounts', 'currency', 'bank_accounts'],
        ['cheque_books', 'currency', 'cheque_books'],
        ['pricing_rules', 'currency', 'pricing_rules'],
    ];

    /**
     * مستندات مالية بلا عمود عملة — تخصّ الافتراضية وحدها.
     *
     * @var array<int, array{0:string,1:string}>
     */
    private const IMPLICIT = [
        ['receipts', 'receipts'],
        ['receipt_vouchers', 'receipts'],
        ['payments_process', 'payments'],
        ['payment_vouchers', 'payments'],
        ['purchase_invoices', 'purchase_invoices'],
    ];

    /**
     * عدد الحركات لكل نوع، مجموعةً بمفتاح الترجمة، بلا الأنواع الصفرية.
     *
     * @return array<string, int>
     */
    public function breakdown(string $code, bool $isDefault): array
    {
        $counts = [];

        foreach (self::TAGGED as [$table, $column, $key]) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                continue;
            }

            $count = DB::table($table)->where($column, $code)->count();

            if ($count > 0) {
                $counts[$key] = ($counts[$key] ?? 0) + $count;
            }
        }

        if (! $isDefault) {
            return $counts;
        }

        foreach (self::IMPLICIT as [$table, $key]) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $count = DB::table($table)->count();

            if ($count > 0) {
                $counts[$key] = ($counts[$key] ?? 0) + $count;
            }
        }

        return $counts;
    }

    /**
     * أوّل حركة تكفي للقفل — فلا داعي لعدّ الخمسين ألف قيد كلّها.
     */
    public function isUsed(string $code, bool $isDefault): bool
    {
        foreach (self::TAGGED as [$table, $column]) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                continue;
            }

            if (DB::table($table)->where($column, $code)->exists()) {
                return true;
            }
        }

        if (! $isDefault) {
            return false;
        }

        foreach (self::IMPLICIT as [$table]) {
            if (Schema::hasTable($table) && DB::table($table)->exists()) {
                return true;
            }
        }

        return false;
    }

    /**
     * وصف مقروء للحركات: «فواتير (٤٠٦٤)، قيود يومية (٧٦٠٠١)».
     */
    public function describe(string $code, bool $isDefault): string
    {
        $parts = [];

        foreach ($this->breakdown($code, $isDefault) as $key => $count) {
            $parts[] = __('settings.currency.usage.'.$key).' ('.number_format($count).')';
        }

        return implode(__('settings.currency.usage_separator'), $parts);
    }
}
