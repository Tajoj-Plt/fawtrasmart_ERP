<?php

namespace App\Console\Commands;

use App\Models\WarehousePermitsProducts;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * يعيد `warehouse_permits_products.unit_price` إلى سعر **الوحدة الأساسية**.
 *
 * **لماذا؟** كمية الإذن المخزني بالوحدة الأساسية دائماً، لكنّ أذوناً قديمة
 * حفظت معها سعرَ **الوحدة المشتراة** — أربعةُ كراتين بـ١٩١٫٣٠ للكرتون
 * كُتبت `quantity = 4000` حبة و `unit_price = 191.30`. فسعرُ الحبّة يظهر
 * في شاشات المخزون ألفَ ضعفِ حقيقته، ومنه خرجت قيودُ تكلفة مبيعاتٍ
 * بعشرات الآلاف لفواتيرَ قيمتُها مئات.
 *
 * `total` في هذه الأسطر صحيح — فهو المرجع: `unit_price = total ÷ quantity`،
 * وهي نفسُها معادلةُ `WarehousePermitsProducts::inventoryLineFromInvoiceItem()`
 * التي تكتب بها الأذونُ الجديدة. لا يُمسّ سطرٌ اتّسق فيه الاثنان، ولا سطرٌ
 * بلا إجمالي — ولا يُحذف شيء.
 *
 * الفحص وحده هو التصرّف الافتراضي؛ الكتابة تُطلب صراحةً بـ `--fix`.
 *
 * ملاحظة: `App\Models\Product::averageCost()` صار يقرأ `total` مباشرةً، فلا
 * يتوقّف صحّةُ قيد التكلفة على تشغيل هذا الأمر — لكنّ الشاشات التي تعرض
 * `unit_price` الخام تبقى كاذبةً حتى يُشغَّل.
 */
class RepairPermitUnitPrices extends Command
{
    protected $signature = 'stock:repair-permit-unit-prices
                            {--fix : يكتب التصحيح؛ بدونه فحصٌ فقط}';

    protected $description = 'إصلاح أسعار الوحدة في الأذون المخزنية حيث حُفظ سعر العبوة مع الكمية بالوحدة الأساسية';

    public function handle(): int
    {
        $suspect = DB::table('warehouse_permits_products')
            ->whereRaw('quantity > 0 and total > 0 and abs(quantity * unit_price - total) > greatest(0.02, abs(total) * 0.01)')
            ->orderBy('id')
            ->get(['id', 'product_id', 'quantity', 'unit_price', 'total']);

        // سعرُ عبوةٍ مع كميةٍ أساسية: الإجمالي أصغر، وهو الصحيح
        [$repairable, $quantitySuspect] = $suspect->partition(
            fn ($row) => (float) $row->total < (float) $row->quantity * (float) $row->unit_price
        );

        if ($suspect->isEmpty()) {
            $this->info('لا توجد أسطرُ أذونٍ سعرُها يخالف إجماليها.');

            return self::SUCCESS;
        }

        $this->warn('أسطرٌ سعرُها متضخّم (سعرُ العبوة مع كميةٍ أساسية): '.$repairable->count());

        if ($repairable->isNotEmpty()) {
            $this->table(
                ['السطر', 'المنتج', 'الكمية (أساسية)', 'السعر المحفوظ', 'الإجمالي', 'السعر الصحيح'],
                $repairable->take(25)->map(fn ($row) => [
                    $row->id,
                    $row->product_id,
                    rtrim(rtrim(number_format((float) $row->quantity, 4, '.', ''), '0'), '.'),
                    number_format((float) $row->unit_price, 4, '.', ''),
                    number_format((float) $row->total, 2, '.', ''),
                    number_format((float) $row->total / (float) $row->quantity, 4, '.', ''),
                ])->all()
            );

            if ($repairable->count() > 25) {
                $this->line('… و'.($repairable->count() - 25).' سطراً آخر.');
            }
        }

        if ($quantitySuspect->isNotEmpty()) {
            $this->newLine();
            $this->warn('أسطرٌ إجماليُّها أكبر من الكمية × السعر — الكميةُ فيها هي المشكوك فيها، ولا تُمسّ: '.$quantitySuspect->count());
            $this->line('  '.$quantitySuspect->take(10)->map(
                fn ($row) => "#{$row->id} (منتج {$row->product_id}: كمية {$row->quantity} × {$row->unit_price} مقابل إجمالي {$row->total})"
            )->implode("\n  "));
        }

        if ($repairable->isEmpty()) {
            return self::SUCCESS;
        }

        if (! $this->option('fix')) {
            $this->newLine();
            $this->line('فحصٌ فقط — شغّل مع ‎--fix‎ للكتابة.');

            return self::SUCCESS;
        }

        $fixed = WarehousePermitsProducts::repairInconsistentUnitPrices();

        $this->info("صُحّح $fixed سطراً.");

        return self::SUCCESS;
    }
}
