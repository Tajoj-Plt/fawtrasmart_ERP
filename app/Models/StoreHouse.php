<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoreHouse extends Model
{
    use HasFactory;
    protected $table = 'store_houses';
    protected $fillable = ['id', 'name', 'shipping_address','storehouse_id', 'account_id', 'branch_id', 'is_store_storehouse', 'status', 'major', 'view_permissions', 'edit_stock_permissions', 'crate_invoices_permissions','value_of_view_permissions', 'value_of_edit_stock_permissions', 'value_of_crate_invoices_permissions', 'created_at', 'updated_at'];

    // Cast the status attribute to string to ensure proper handling of enum values
    protected $casts = [
        'status' => 'string',
    ];

    public function productDetails()
    {
        return $this->hasMany(ProductDetails::class, 'store_house_id');
    }

    public function transfersFrom()
    {
        return $this->hasMany(WarehousePermits::class, 'from_store_houses_id');
    }

    public function transfersTo()
    {
        return $this->hasMany(WarehousePermits::class, 'to_store_houses_id');
    }

    public function warehousePermits()
    {
        return $this->hasMany(WarehousePermits::class, 'store_houses_id');
    }
public function products()
{
    return $this->belongsToMany(Product::class, 'product_details', 'store_houses_id', 'product_id');
}
public function categories()
{
    return $this->belongsToMany(Category::class, 'product_details', 'store_houses_id', 'category_id');
}

    /**
     * Relationship to get the accounting account linked to this storehouse.
     */
     public function account(): BelongsTo
    {
       return $this->belongsTo(Account::class, 'account_id');
    }

    /** الفرع الذي يتبعه هذا المستودع. */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    /**
     * مخازن تابعة للمتجر الإلكتروني فقط — تستعملها شاشات المتجر بدل عرض
     * كل مخازن النظام (بما فيها مخازن فروع أخرى لا علاقة لها بالمتجر).
     */
    public function scopeOnlineStore($query)
    {
        return $query->where('is_store_storehouse', true);
    }
 public function getProductRemainingQuantity($productId)
    {
        // Return the actual current quantity from product details
        $remaining = $this->productDetails()
            ->where('product_id', $productId)
            ->sum('quantity');

        // Log for debugging
        Log::debug("StoreHouse {$this->id} Product {$productId} Remaining Quantity: {$remaining}");

        return $remaining;
    }
}



