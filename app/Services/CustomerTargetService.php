<?php

namespace App\Services;

use App\Constants\SystemAccountIds;
use App\Enums\ReferenceType;
use App\Models\Account;
use App\Models\Client;
use App\Models\CustomerTarget;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class CustomerTargetService
{
    /**
     * تقييم جميع العملاء بناءً على التواريخ أو عدد الأيام
     * @return array مصفوفة تحتوي على بيانات العملاء الذين تراجع تقييمهم
     */
    public function evaluateAllClients($fromDate = null, $toDate = null, $days = null): array
    {
        $downgradedClients = [];
        $clients = Client::pluck('id');
        
        // جلب جميع الأهداف كقاموس سريع لمعرفة الأولوية
        $targetsCache = CustomerTarget::pluck('priority', 'id')->toArray();

        foreach ($clients as $clientId) {
            $result = $this->evaluateClient($clientId, $fromDate, $toDate, $days, $targetsCache);
            if ($result && $result['is_downgraded']) {
                $downgradedClients[] = $result;
            }
        }
        
        return $downgradedClients;
    }

    /**
     * تقييم عميل محدد وحفظ الهدف المطابق
     */
    public function evaluateClient($clientId, $fromDate = null, $toDate = null, $days = null, $targetsCache = null): array|false
    {
        $client = Client::find($clientId);
        if (!$client) return false;

        $metrics  = $this->calculateClientMetrics($clientId, $fromDate, $toDate, $days);
        $targetId = $this->findMatchingTarget($metrics);

        $oldTargetId = $client->customer_target_id;
        
        // التحقق من التراجع
        $isDowngraded = false;
        $oldTargetName = 'غير مقيّم';
        $newTargetName = 'غير مقيّم';

        if ($oldTargetId && $oldTargetId != $targetId) {
            if (!$targetsCache) {
                $targetsCache = CustomerTarget::pluck('priority', 'id')->toArray();
            }
            
            $oldPriority = $targetsCache[$oldTargetId] ?? 0;
            $newPriority = $targetId ? ($targetsCache[$targetId] ?? 0) : 0;
            
            if ($oldPriority > $newPriority) {
                $isDowngraded = true;
                $oldTargetName = CustomerTarget::find($oldTargetId)?->name ?? 'غير مقيّم';
                $newTargetName = $targetId ? (CustomerTarget::find($targetId)?->name ?? 'غير مقيّم') : 'غير مقيّم';
            }
        }

        $updateData = [
            'target_evaluated_at' => now(),
        ];
        if ($oldTargetId != $targetId) {
            $updateData['customer_target_id'] = $targetId;
            $updateData['previous_target_id'] = $oldTargetId;
        }

        $client->update($updateData);

        return [
            'client_id'     => $client->id,
            'client_name'   => $client->trade_name ?? $client->name,
            'is_downgraded' => $isDowngraded,
            'old_target'    => $oldTargetName,
            'new_target'    => $newTargetName,
            'metrics'       => $metrics,
            'target_id'     => $targetId,
        ];
    }

    // =====================================================================
    // حساب مؤشرات العميل — بنفس منطق التقرير الموحد
    // =====================================================================
    public function calculateClientMetrics($clientId, $fromDate = null, $toDate = null, $days = null): array
    {
        $client = Client::find($clientId);
        // إذا تم تحديد عدد الأيام، نحسبها من تاريخ أول قيد (فاتورة أو تحصيل) للعميل
        if ($days && (int)$days > 0) {
            $firstTransactionDate = DB::table('journal_entries')
                ->where('client_id', $clientId)
                ->where('status', 1)
                ->min('date');

            if ($firstTransactionDate) {
                $fromDate = Carbon::parse($firstTransactionDate)->startOfDay();
                $toDate   = $fromDate->copy()->addDays((int)$days)->endOfDay();
            } else {
                $fromDate = Carbon::now()->startOfDay();
                $toDate   = Carbon::now()->endOfDay();
            }
        } else {
            $fromDate = $fromDate ? Carbon::parse($fromDate)->startOfDay() : Carbon::now()->startOfYear();
            $toDate   = $toDate ? Carbon::parse($toDate)->endOfDay() : Carbon::now()->endOfDay();
        }

        // ── 1. المبيعات ──────────────────────────────────────────────────
        $salesRows = DB::table('journal_entries as je')
            ->join('journal_entry_details as jed', 'je.id', '=', 'jed.journal_entry_id')
            ->where('jed.account_id', SystemAccountIds::SALES)
            ->where('jed.credit', '>', 0)
            ->whereBetween('je.date', [$fromDate, $toDate])
            ->where('je.status', 1)
            ->where('je.client_id', $clientId)
            ->whereNotIn('je.reference_type', [
                ReferenceType::SALES_RETURN->value,
                ReferenceType::CREDIT_NOTE->value,
            ])
            ->select(
                'je.id as je_id',
                DB::raw('(jed.credit - jed.debit) as net_amount')
            )
            ->get();

        $totalSales   = 0;
        $invoiceCount = 0;
        $seenIds      = [];

        foreach ($salesRows as $row) {
            $totalSales += (float) $row->net_amount;
            if (!in_array($row->je_id, $seenIds)) {
                $seenIds[]     = $row->je_id;
                $invoiceCount++;
            }
        }

        // المرتجعات
        $returnsAmount = (float) DB::table('journal_entries as je')
            ->join('journal_entry_details as jed', 'je.id', '=', 'jed.journal_entry_id')
            ->whereIn('jed.account_id', [SystemAccountIds::SALES, SystemAccountIds::SALES_RETURNS])
            ->where('jed.debit', '>', 0)
            ->whereBetween('je.date', [$fromDate, $toDate])
            ->where('je.status', 1)
            ->where('je.client_id', $clientId)
            ->whereIn('je.reference_type', [
                ReferenceType::SALES_RETURN->value,
                ReferenceType::CREDIT_NOTE->value,
            ])
            ->sum(DB::raw('jed.debit - jed.credit'));

        $netSales = max(0, $totalSales - $returnsAmount);

        // ── 2. التحصيلات ─────────────────────────────────────────────────
        $treasuryAccountIds = Account::whereIn('parent_id', [
            SystemAccountIds::CASH_TREASURIES_PARENT,
            SystemAccountIds::BANK_ACCOUNTS_PARENT,
        ])->pluck('id')->toArray();

        $totalCollections = 0;

        if (!empty($treasuryAccountIds)) {
            $totalCollections = (float) DB::table('journal_entry_details as jed')
                ->join('journal_entries as je', 'jed.journal_entry_id', '=', 'je.id')
                ->whereIn('jed.account_id', $treasuryAccountIds)
                ->where('jed.debit', '>', 0)
                ->whereBetween('je.date', [$fromDate, $toDate])
                ->where('je.status', 1)
                ->where('je.client_id', $clientId)
                ->whereNotExists(function ($sub) use ($treasuryAccountIds) {
                    $sub->select(DB::raw(1))
                        ->from('journal_entry_details as jed2')
                        ->whereColumn('jed2.journal_entry_id', 'jed.journal_entry_id')
                        ->whereIn('jed2.account_id', $treasuryAccountIds)
                        ->where('jed2.credit', '>', 0);
                })
                ->sum('jed.debit');
        }

        // ── 3. متوسط فترة التحصيل بالأيام ──────────────────────────────
        // حساب جميع الفواتير (المسددة والغير مسددة لضمان تقييم الديون المتأخرة)
        $invoices = DB::table('invoices as inv')
            ->where('inv.client_id', $clientId)
            ->whereBetween('inv.invoice_date', [$fromDate, $toDate])
            ->select('inv.id', 'inv.invoice_date', 'inv.is_paid')
            ->get();

        $totalDays = 0;
        $count = 0;

        foreach ($invoices as $inv) {
            if ($inv->is_paid == 1) {
                // فاتورة مسددة بالكامل: نحسب الأيام حتى تاريخ آخر دفعة صَفَّرت الفاتورة
                $lastPaymentDate = DB::table('payments_process')
                    ->where('invoice_id', $inv->id)
                    ->where('type', 'client payments')
                    ->max('payment_date');

                if ($lastPaymentDate) {
                    $diff = Carbon::parse($inv->invoice_date)->diffInDays(Carbon::parse($lastPaymentDate));
                    $totalDays += $diff;
                    $count++;
                }
            } else {
                // فاتورة غير مسددة أو مسددة جزئياً: نحسب أيام التأخير من تاريخ الفاتورة حتى اليوم الحالي
                $diff = Carbon::parse($inv->invoice_date)->diffInDays(Carbon::now());
                $totalDays += $diff;
                $count++;
            }
        }

        $avgCollectionDays = $count > 0 ? round($totalDays / $count) : 0;

        // نحسب عدد الأشهر الإجمالي للفترة المحددة (أو منذ تاريخ تسجيل العميل إذا كان أحدث من تاريخ البداية)
        $start = Carbon::parse($fromDate);
        // تم تعطيل أخذ تاريخ تسجيل العميل بناءً على طلبك، بحيث تقسم دائماً على الأشهر كاملة
        if ($client && $client->created_at && $client->created_at->gt($start)) {
            $start = $client->created_at->copy();
        }
        $end = Carbon::parse($toDate);
        
        $finalMonths = (($end->year - $start->year) * 12) + ($end->month - $start->month) + 1;
        $finalMonths = max(1, $finalMonths);

        return [
            'total_sales'         => $netSales,
            'total_collections'   => $totalCollections,
            'avg_collection_days' => $avgCollectionDays,
            'months'              => $finalMonths,
        ];
    }

    // =====================================================================
    // إيجاد الهدف الأول المطابق (مرتّب من الأعلى أولوية)
    // =====================================================================
    public function findMatchingTarget(array $metrics): ?int
    {
        $targets = CustomerTarget::where('is_active', true)
            ->orderBy('priority', 'desc')
            ->get();

        // حساب سرعة السداد الشهري باستخدام الأشهر المحسوبة بدقة
        $months = $metrics['months'] ?? 1;
        $monthlyVelocity = $metrics['total_collections'] / $months;

        foreach ($targets as $target) {
            $ok = true;

            // شرط الحد الأدنى لسرعة السداد الشهري (نستخدم حقل min_sales لتمثيل الدفع الشهري)
            if ($target->min_sales > 0 && $monthlyVelocity < $target->min_sales)
                $ok = false;

            // شرط الحد الأدنى للتحصيلات
            if ($ok && $target->min_collections > 0 && $metrics['total_collections'] < $target->min_collections)
                $ok = false;

            // شرط الحد الأقصى لمتوسط أيام التحصيل (لضمان عدم تجاوز العميل مدة قصوى حتى لو كانت مسحوباته عالية)
            if ($ok && $target->max_avg_collection_days > 0 && $metrics['avg_collection_days'] > $target->max_avg_collection_days)
                $ok = false;

            if ($ok) return $target->id;
        }

        return null;
    }
}
