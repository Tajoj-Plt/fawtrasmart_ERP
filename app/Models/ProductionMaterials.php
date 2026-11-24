<?php

namespace App\Models;

use App\Models\Concerns\HasDocumentCurrency;
use App\Traits\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;

class ProductionMaterials extends Model
{
    use BelongsToBranch, HasDocumentCurrency;

    protected $table = 'production_materials';

    protected $fillable = [
        'name', 'code', 'product_id', 'last_total_cost',
        'account_id', 'production_path_id', 'quantity', 'status',
        'default', 'created_by', 'updated_by',
        'currency',
        // الفرع — `null` تعني «كل الفروع»
        'branch_id',
    ];

    /*
        `quantity` هنا هي **الكمية الأساسية** للوصفة — الكمية المعلنة
        التي تُنسب إليها كميات المواد. وهي `DECIMAL(10,2)` في الجدول
        منذ البداية، والتحويل يمنع إعادتها نصّاً.
    */
    protected $casts = [
        'quantity' => 'decimal:2',
        'last_total_cost' => 'decimal:2',
    ];

    public function ProductionMaterialsItem()
    {
        return $this->hasMany(ProductionMaterialsItem::class, 'production_material_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function createdByUser()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedByUser()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function productionPath()
    {
        return $this->belongsTo(ProductionPath::class, 'production_path_id');
    }

    public function account()
    {
        return $this->belongsTo(Account::class, 'account_id');
    }
}
