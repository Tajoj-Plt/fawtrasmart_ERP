<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PriceList extends Model
{
    use HasFactory;

    protected $table = 'price_lists';

    protected $fillable = ['id', 'name', 'status', 'created_at', 'updated_at'];

    /*
        `withPivot('id')` ضروريّ: شاشة القائمة تحذف **سطر الربط** لا المنتج
        نفسه، وبدونه لا سبيل إلى معرّف ذلك السطر من المنتج المُحمَّل — فكان
        زرّ الحذف يمرّر معرّف المنتج إلى دالّةٍ تنتظر معرّف سطر ربط.
    */
    public function price_list_products()
    {
        return $this->belongsToMany(Product::class, 'price_list_items')
            ->withPivot('id', 'sale_price');
    }
}
