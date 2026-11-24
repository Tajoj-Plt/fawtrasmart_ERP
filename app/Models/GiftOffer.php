<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


class GiftOffer extends Model
{
    protected $fillable = [
        'name',
        'target_product_id',
        'min_quantity',
        'gift_product_id',
        'gift_quantity',
        'start_date',
        'end_date',
        'is_for_all_clients',
        'is_for_all_employees',
        'is_active',
    ];

    public function clients()
    {
        return $this->belongsToMany(Client::class, 'client_gift_offer');
    }

public function users()
{
    return $this->belongsToMany(User::class, 'employee_gift_offer', 'gift_id', 'user_id');
}

    public function targetProduct()
    {
        return $this->belongsTo(Product::class, 'target_product_id');
    }

    public function giftProduct()
    {
        return $this->belongsTo(Product::class, 'gift_product_id');
    }

    /**
     * العملاء المستثنون من العرض.
     *
     * **كان الجدول الوسيط هنا `client_gift_offer`** — أي جدول العملاء
     * *المشمولين* نفسه، بنفس المفتاحين. فالعلاقتان `clients()` و
     * `excludedClients()` كانتا تقرآن وتكتبان في مكانٍ واحد:
     *
     *     $offer->clients()->sync([1, 2, 3]);        // شمل ثلاثة
     *     $offer->excludedClients()->sync([9]);      // ← محا الثلاثة
     *
     * وهذا ما كان يجري في `GiftOfferController::update()` عند كل حفظ:
     * قائمة العملاء المشمولين تُكتب ثم تُمحى بسطرٍ بعدها، فيصير العرض
     * بلا عميلٍ مشمول ويُطبَّق على مَن استُثني.
     *
     * الجدول الصحيح `excluded_client_gift_offers` — وهو الذي كان
     * `store()` يكتب فيه عبر `ExcludedClientGiftOffer::create()`، فالبيانات
     * القديمة فيه سليمة؛ الخلل كان في العلاقة وحدها.
     */
    public function excludedClients()
    {
        return $this->belongsToMany(
            Client::class,
            'excluded_client_gift_offers',
            'gift_offer_id',
            'client_id'
        );
    }

}
