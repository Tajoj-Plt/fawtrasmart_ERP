<?php

namespace App\Models;

use App\Enums\ManufacturOrderStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * بندُ خطّةِ إنتاج — منتجٌ وكميّةٌ مخطّطة، وأمرُ التصنيع المولَّد عنه.
 *
 * `manufactur_order_id` هو ما يجعل التوليد قابلاً للتكرار بلا ازدواج:
 * البندُ الذي يحمل أمراً يُتخطّى. انظر رأسَ الهجرة
 * `2026_09_10_000001_create_production_plans_tables`.
 */
class ProductionPlanItem extends Model
{
    protected $table = 'production_plan_items';

    protected $fillable = [
        'production_plan_id', 'product_id', 'production_material_id',
        'production_path_id', 'planned_quantity', 'manufactur_order_id', 'notes',
    ];

    /*
        الكميّةُ عشرية — نفسُ درسِ `manufactur_orders.quantity`: بلا
        `$casts` يُعيد Laravel العمودَ العشري **نصّاً** ('2.5000')، فتُجمَع
        الكميّات بضمٍّ ضمني وتُقارَن كسلاسل.
    */
    protected $casts = [
        'planned_quantity' => 'decimal:4',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(ProductionPlan::class, 'production_plan_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productionMaterial(): BelongsTo
    {
        return $this->belongsTo(ProductionMaterials::class, 'production_material_id');
    }

    public function productionPath(): BelongsTo
    {
        return $this->belongsTo(ProductionPath::class, 'production_path_id');
    }

    public function manufacturOrder(): BelongsTo
    {
        return $this->belongsTo(ManufacturOrders::class, 'manufactur_order_id');
    }

    /** هل وُلِّد أمرُ هذا البند؟ */
    public function isGenerated(): bool
    {
        return $this->manufactur_order_id !== null;
    }

    /**
     * هل يُولَّد لهذا البند أمرٌ الآن؟
     *
     * نعم إن لم يكن له أمرٌ قطّ، أو كان أمرُه ملغى: الملغى لن يُنتج
     * شيئاً، فالبندُ عاد بلا أمرٍ حيّ. انظر
     * [ProductionPlan::syncStatusFromOrders].
     */
    public function isGenerable(): bool
    {
        if ($this->manufactur_order_id === null) {
            return true;
        }

        $order = $this->manufacturOrder;

        return ! $order || $order->status === ManufacturOrderStatus::Cancelled;
    }
}
