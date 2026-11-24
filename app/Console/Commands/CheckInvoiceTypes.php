<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Supplier;
use Illuminate\Support\Facades\DB;

class CheckInvoiceTypes extends Command
{
    protected $signature = 'check:invoice-types';
    protected $description = 'معرفة أنواع الفواتير والمبالغ';

    public function handle()
    {
        $invoiceTypes = DB::table('purchase_invoices')
            ->select('type', DB::raw('COUNT(*) as count'), DB::raw('SUM(grand_total) as total'))
            ->groupBy('type')
            ->get();

        $this->line("\n=== تفاصيل أنواع الفواتير ===\n");
        foreach ($invoiceTypes as $type) {
            $total = number_format($type->total ?? 0, 2);
            $this->line("النوع: {$type->type}");
            $this->line("  عدد الفواتير: {$type->count}");
            $this->line("  المجموع: $total\n");
        }
    }
}
