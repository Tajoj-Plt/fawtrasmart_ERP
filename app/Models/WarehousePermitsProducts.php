<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WarehousePermitsProducts extends Model
{
    use HasFactory;

    protected $table = 'warehouse_permits_products';

    protected $fillable = [
        'warehouse_permits_id',
        'product_id',
        'quantity',
        'unit_price',
        'total',
        'stock_before',
        'stock_after',
        // يكتبه BOMController:529 ويُهمَل صامتاً بحماية الإسناد الجماعي،
        // فيضيع المستودع الحقيقي للبند في الأذونات متعددة المستودعات
        'store_house_id',
    ];

    public function warehousePermits()
    {
        return $this->belongsTo(WarehousePermits::class, 'warehouse_permits_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /**
     * سعر الوحدة للكمية المخزّنة (الوحدة الأساسية) من إجمالي السطر ÷ الكمية.
     */
    public static function unitPricePerInventoryQuantity(
        float $inventoryQuantity,
        float $lineTotal,
        float $fallbackUnitPrice = 0,
        ?float $conversionFactor = null
    ): float {
        if ($inventoryQuantity > 0 && $lineTotal > 0) {
            return round($lineTotal / $inventoryQuantity, 4);
        }

        $factor = ($conversionFactor !== null && $conversionFactor > 0) ? $conversionFactor : 1.0;
        if ($fallbackUnitPrice > 0 && $factor > 1) {
            return round($fallbackUnitPrice / $factor, 4);
        }

        return round($fallbackUnitPrice, 4);
    }

    /**
     * كمية + سعر + إجمالي متسقة للإذن المخزني (الكمية دائماً بالوحدة الأساسية).
     */
    public static function inventoryLineFromInvoiceItem(array $item): array
    {
        $quantity = (float) ($item['quantity_in_base_unit'] ?? $item['quantity'] ?? 0);
        $lineTotal = (float) ($item['total'] ?? 0);
        $invoiceUnitPrice = (float) ($item['unit_price'] ?? 0);
        $conversionFactor = isset($item['conversion_factor']) ? (float) $item['conversion_factor'] : null;

        $unitPrice = self::unitPricePerInventoryQuantity(
            $quantity,
            $lineTotal,
            $invoiceUnitPrice,
            $conversionFactor
        );

        return [
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'total' => $lineTotal > 0 ? round($lineTotal, 2) : round($quantity * $unitPrice, 2),
        ];
    }

    /**
     * تصحيح أذونات قديمة: unit_price × quantity لا يطابق total → unit_price = total ÷ quantity
     *
     * **بشرطِ أن يكون `total` هو الأصغر.** فالسطرُ المقصود هنا واحد: كميةٌ
     * بالوحدة الأساسية بقي معها سعرُ العبوة (٤٠٠٠ حبة × ١٩١٫٣٠ للكرتون)،
     * فإجماليه صحيحٌ وسعرُه متضخّم. أمّا سطرٌ إجماليُّه **أكبر** من
     * `كمية × سعر` فالكميةُ فيه هي المشكوك فيها (١ مقابل إجمالي سطرٍ
     * كامل)، وقسمةُ الإجمالي على كميةٍ خاطئة تكتب سعراً أفدحَ من الأصل —
     * فيُترك لمن يعرف أصلَ الحركة. انظر `Product::COST_LINE_VALUE_SUM`.
     */
    public static function repairInconsistentUnitPrices(): int
    {
        $fixed = 0;

        self::query()
            ->where('quantity', '>', 0)
            ->where('total', '>', 0)
            ->orderBy('id')
            ->chunkById(500, function ($rows) use (&$fixed) {
                foreach ($rows as $row) {
                    $qty = (float) $row->quantity;
                    $total = (float) $row->total;
                    $unit = (float) $row->unit_price;
                    $expected = round($qty * $unit, 2);
                    $tolerance = max(0.02, abs($total) * 0.01);

                    if (abs($expected - $total) <= $tolerance) {
                        continue;
                    }

                    // الإجمالي أكبر من الكمية × السعر: الكميةُ هي المعطوبة، لا السعر
                    if ($total > $expected) {
                        continue;
                    }

                    $newUnit = round($total / $qty, 4);
                    if (abs($newUnit - $unit) > 0.0001) {
                        $row->update(['unit_price' => $newUnit]);
                        $fixed++;
                    }
                }
            });

        return $fixed;
    }
}
