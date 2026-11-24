<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Supplier;
use Illuminate\Support\Facades\DB;

class VerifySupplierBalanceFix extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'verify:supplier-balance';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'التحقق من أن حسابات أرصدة الموردين صحيحة';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info("=== التحقق من إصلاح حساب الأرصدة ===\n");

        // جلب أول 5 موردين
        $suppliers = Supplier::with('purchaseInvoices', 'payments')
            ->take(5)
            ->get();

        if ($suppliers->isEmpty()) {
            $this->warn('لا توجد موردين في قاعدة البيانات');
            return;
        }

        foreach ($suppliers as $supplier) {
            $this->line("=== المورد: {$supplier->trade_name} (الكود: {$supplier->number_suply}) ===");
            
            // حساب المشتريات (الفواتير فقط)
            $totalPurchases = $supplier->purchaseInvoices()
                ->where('type', 'invoice')
                ->sum('grand_total');
            
            // حساب المرتجعات
            $totalReturns = $supplier->purchaseInvoices()
                ->where('type', 'return')
                ->sum('grand_total');
            
            // حساب المدفوعات
            $totalPayments = $supplier->payments()->sum('amount');
            
            // حساب الرصيد
            $openingBalance = $supplier->opening_balance ?? 0;
            $calculatedBalance = $openingBalance + $totalPurchases - $totalReturns - $totalPayments;
            
            // الحصول على الرصيد من Accessor
            $modelBalance = $supplier->current_balance;
            
            $this->line("الرصيد الافتتاحي: " . number_format($openingBalance, 2));
            $this->line("إجمالي المشتريات: " . number_format($totalPurchases, 2));
            $this->line("إجمالي المرتجعات: " . number_format($totalReturns, 2));
            $this->line("إجمالي المدفوعات: " . number_format($totalPayments, 2));
            $this->line("الصيغة: $openingBalance + $totalPurchases - $totalReturns - $totalPayments = $calculatedBalance");
            $this->line("الرصيد المحسوب: " . number_format($calculatedBalance, 2));
            $this->line("الرصيد من Model (current_balance): " . number_format($modelBalance, 2));
            
            // التحقق من التطابق
            if (abs($calculatedBalance - $modelBalance) < 0.01) {
                $this->info("✅ الحساب صحيح!\n");
            } else {
                $this->error("❌ هناك فرق: الفرق = " . ($modelBalance - $calculatedBalance) . "\n");
            }
        }

        $this->line("\n=== التحقق من عدد الفواتير من كل نوع ===");
        $invoiceTypes = DB::table('purchase_invoices')
            ->select('type', DB::raw('COUNT(*) as count'))
            ->groupBy('type')
            ->get();

        $this->line("أنواع الفواتير:");
        foreach ($invoiceTypes as $type) {
            $this->line("  - {$type->type}: {$type->count} فاتورة");
        }
    }
}
