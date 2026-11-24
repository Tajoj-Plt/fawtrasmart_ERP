<?php

namespace App\Models;

use App\Enums\BomLineType;
use Illuminate\Database\Eloquent\Model;

class ManufacturOrdersItem extends Model
{
    protected $table = 'manufactur_orders_items';

    protected $fillable = [
        // نوع السطر معلَناً لا مستنتَجاً من `whereNotNull` — انظر BomLineType
        'line_type',
        'manufactur_order_id', 'raw_product_id', 'raw_production_stage_id',
        'raw_unit_id', 'raw_conversion_factor', 'raw_unit_price', 'raw_quantity', 'raw_total',
        // التكلفة الفعلية من المخزون لحظة الصرف — تُقابل التقديرية أعلاه
        'raw_actual_unit_price', 'raw_actual_total',
        // بندُ مصروفٍ مصدرُه «تكلفة غير مباشرة» — مبلغُه يحسبه الخادم من نصيب الوحدة
        'indirect_cost_id',
        'expenses_account_id', 'expenses_production_stage_id', 'expenses_cost_type', 'expenses_price',
        'expenses_description', 'expenses_total', 'workstation_id', 'operating_time',
        'manu_production_stage_id', 'manu_cost_type', 'manu_total_cost', 'manu_description',
        'manu_total', 'end_life_product_id', 'end_life_waste_type', 'end_life_production_stage_id',
        'end_life_unit_price', 'end_life_quantity', 'end_life_total',
    ];

    /*
        الكميات والأسعار عشرية — نفسُ ما في `ProductionMaterialsItem`،
        فالجدولان متطابقان في المعنى وبنودُ الأمر تُنسخ من بنود الوصفة.
    */
    protected $casts = [
        'line_type' => BomLineType::class,
        'raw_quantity' => 'decimal:4',
        'raw_unit_price' => 'decimal:6',
        'raw_total' => 'decimal:2',
        'raw_actual_unit_price' => 'decimal:6',
        'raw_actual_total' => 'decimal:4',
        'raw_conversion_factor' => 'decimal:6',
        'expenses_price' => 'decimal:2',
        'expenses_total' => 'decimal:2',
        'operating_time' => 'decimal:4',
        'manu_total_cost' => 'decimal:4',
        'manu_total' => 'decimal:2',
        'end_life_quantity' => 'decimal:4',
        'end_life_unit_price' => 'decimal:6',
        'end_life_total' => 'decimal:2',
    ];

    public function manufacturOrder()
    {
        return $this->belongsTo(ManufacturOrders::class);
    }

    public function rawProduct()
    {
        return $this->belongsTo(Product::class, 'raw_product_id');
    }

    public function rawProductionStage()
    {
        return $this->belongsTo(ProductionStage::class, 'raw_production_stage_id');
    }

    public function expensesProductionStage()
    {
        return $this->belongsTo(ProductionStage::class, 'expenses_production_stage_id');
    }

    public function workshopProductionStage()
    {
        return $this->belongsTo(ProductionStage::class, 'manu_production_stage_id');
    }

    public function endLifeProductionStage()
    {
        return $this->belongsTo(ProductionStage::class, 'end_life_production_stage_id');
    }

    public function endLifeProduct()
    {
        return $this->belongsTo(Product::class, 'end_life_product_id');
    }

    public function workStation()
    {
        return $this->belongsTo(WorkStations::class, 'workstation_id');
    }

    public function expensesAccount()
    {
        return $this->belongsTo(Account::class, 'expenses_account_id');
    }

    /**
     * العلاقة مع وحدة المادة الخام
     */
    public function rawUnit()
    {
        return $this->belongsTo(ProductUnit::class, 'raw_unit_id');
    }
}
