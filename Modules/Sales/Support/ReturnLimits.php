<?php

namespace Modules\Sales\Support;

use App\Models\CreditNotification;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use Illuminate\Support\Collection;

/**
 * حدودُ ردّ فاتورةٍ بعينها — ما يجوز أن يعود منها بمرتجعٍ أو إشعارٍ دائن.
 *
 * **ما كان**
 *
 *   • المرتجع يأخذ **أوّل** بندٍ للصنف ويفحص كلَّ سطرٍ وحده: سطران بثلاثين
 *     من صنفٍ بِيع منه اثنان وثلاثون يمرّان، والكميةُ السالبة تمرّ فتُخرج
 *     البضاعة من المستودع، والسعرُ ما كتبه المستخدم (حبّةٌ بِيعت بثمانية عشر
 *     رُدّت بألف).
 *   • والإشعار الدائن لا يُفحص مقابل فاتورته إطلاقاً: ٩٩٩ حبّة على فاتورةٍ
 *     باعت ثلاث عشرة.
 *
 * **ما صار** — قبل أيّ كتابة، وللمستندين معاً:
 *
 *   • الصنفُ من بنود الفاتورة، والكميةُ موجبة، والسعرُ غيرُ سالب.
 *   • مجموعُ المطلوب **لكل صنف** بالوحدة الأساسية ≤ المباع − ما رُدّ منه سابقاً
 *     (مرتجعاتُ الفاتورة وإشعاراتُها الدائنة غيرُ الملغاة).
 *   • سعرُ الوحدة الأساسية ≤ أعلى سعرٍ بِيعت به في الفاتورة.
 */
final class ReturnLimits
{
    private const QUANTITY_TOLERANCE = 0.0001;

    private const PRICE_TOLERANCE = 0.01;

    /** @var Collection<int|string, Collection<int, InvoiceItem>> بنود الفاتورة مجمَّعةً بالصنف */
    private Collection $lines;

    /** @var array<int, float> الصنف ⇒ ما رُدّ منه سابقاً بالوحدة الأساسية */
    private array $returned = [];

    private function __construct(private Invoice $invoice)
    {
        $this->lines = InvoiceItem::where('invoice_id', $invoice->id)
            ->whereNotNull('product_id')
            ->orderBy('id')
            ->get()
            ->groupBy(fn (InvoiceItem $line) => (int) $line->product_id);

        $returnIds = Invoice::where('type', 'returned')
            ->where('reference_number', $invoice->id)
            ->pluck('id');

        $creditIds = CreditNotification::where('invoice_id', $invoice->id)
            ->whereNotIn('status', [0, 5])
            ->pluck('id');

        $previous = InvoiceItem::query()
            ->whereNotNull('product_id')
            ->where(fn ($query) => $query->whereIn('invoice_id', $returnIds)->orWhereIn('credit_note_id', $creditIds))
            ->get();

        foreach ($previous as $line) {
            $productId = (int) $line->product_id;
            $this->returned[$productId] = ($this->returned[$productId] ?? 0.0) + self::baseQuantity($line);
        }
    }

    public static function for(Invoice $invoice): self
    {
        return new self($invoice);
    }

    /** كمية البند بالوحدة الأساسية — المحفوظة، وإلّا الكمية × المعامل. */
    public static function baseQuantity(InvoiceItem $line): float
    {
        return (float) ($line->quantity_in_base_unit ?: (float) $line->quantity * self::factorOf($line));
    }

    /** معامل البند — المحفوظ، وإلّا ما يدلّ عليه (الأساسية ÷ الكمية)، وإلّا ١. */
    public static function factorOf(InvoiceItem $line): float
    {
        if ((float) $line->conversion_factor > 0) {
            return (float) $line->conversion_factor;
        }

        if ((float) $line->quantity > 0 && (float) $line->quantity_in_base_unit > 0) {
            return (float) $line->quantity_in_base_unit / (float) $line->quantity;
        }

        return 1.0;
    }

    /** البند الأصليّ للصنف — بوحدته إن طابقت، وإلّا أوّلُ بندٍ له. */
    public function lineFor(int $productId, $productUnitId = null): ?InvoiceItem
    {
        $lines = $this->lines->get($productId);

        if (! $lines) {
            return null;
        }

        if (! empty($productUnitId) && is_numeric($productUnitId)) {
            $match = $lines->first(fn (InvoiceItem $line) => (int) $line->product_unit_id === (int) $productUnitId);

            if ($match) {
                return $match;
            }
        }

        return $lines->first();
    }

    public function soldBase(int $productId): float
    {
        return (float) $this->lines->get($productId, collect())->sum(fn (InvoiceItem $line) => self::baseQuantity($line));
    }

    public function returnedBase(int $productId): float
    {
        return $this->returned[$productId] ?? 0.0;
    }

    public function remainingBase(int $productId): float
    {
        return max(0.0, $this->soldBase($productId) - $this->returnedBase($productId));
    }

    /** أعلى سعرٍ بِيعت به الوحدة الأساسية من الصنف في الفاتورة. */
    public function maxBasePrice(int $productId): float
    {
        return (float) $this->lines->get($productId, collect())
            ->max(fn (InvoiceItem $line) => (float) $line->unit_price / self::factorOf($line));
    }

    /**
     * أوّلُ مخالفةٍ في البنود المطلوبة — أو `null` إن جازت كلُّها.
     *
     * @param  array<int, array{product_id: int, quantity: float, unit_price: float, factor: float}>  $requested
     *                                                                                                            الكميةُ والسعرُ بالوحدة المُدخَلة، و`factor` معاملُها
     */
    public function violation(array $requested): ?string
    {
        if ($requested === []) {
            return __('sales_invoice.return_limits.no_lines');
        }

        $demand = [];

        foreach ($requested as $line) {
            $productId = (int) $line['product_id'];
            $name = $this->nameOf($productId);

            if (! $this->lines->has($productId)) {
                return __('sales_invoice.return_limits.not_on_invoice', ['item' => $name, 'code' => $this->invoice->code]);
            }

            if ((float) $line['quantity'] <= 0) {
                return __('sales_invoice.return_limits.quantity_positive', ['item' => $name]);
            }

            if ((float) $line['unit_price'] < 0) {
                return __('sales_invoice.return_limits.price_negative', ['item' => $name]);
            }

            $factor = (float) $line['factor'] > 0 ? (float) $line['factor'] : 1.0;
            $basePrice = (float) $line['unit_price'] / $factor;
            $maxPrice = $this->maxBasePrice($productId);

            if ($basePrice - $maxPrice > self::PRICE_TOLERANCE) {
                return __('sales_invoice.return_limits.price_exceeded', [
                    'item' => $name,
                    'price' => self::number($basePrice),
                    'original' => self::number($maxPrice),
                ]);
            }

            $demand[$productId] = ($demand[$productId] ?? 0.0) + (float) $line['quantity'] * $factor;
        }

        foreach ($demand as $productId => $base) {
            if ($base - $this->remainingBase($productId) > self::QUANTITY_TOLERANCE) {
                return __('sales_invoice.return_limits.quantity_exceeded', [
                    'item' => $this->nameOf($productId),
                    'requested' => self::number($base),
                    'sold' => self::number($this->soldBase($productId)),
                    'returned' => self::number($this->returnedBase($productId)),
                    'remaining' => self::number($this->remainingBase($productId)),
                ]);
            }
        }

        return null;
    }

    private function nameOf(int $productId): string
    {
        return Product::whereKey($productId)->value('name')
            ?? $this->lines->get($productId)?->first()?->item
            ?? '#'.$productId;
    }

    private static function number(float $value): string
    {
        return rtrim(rtrim(number_format($value, 4, '.', ''), '0'), '.') ?: '0';
    }
}
