<?php

namespace App\Models\Store;

use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * مودل عناوين العملاء للتوصيل
 * Client Address Model
 *
 * يدير عناوين التوصيل المختلفة للعملاء في المتجر الإلكتروني
 * يحتوي على معلومات الموقع الجغرافي وتفاصيل المستلم
 */
class ClientAddress extends Model
{
    use HasFactory;

    protected $table = 'client_addresses';

    protected $fillable = [
        'client_id',
        'title',
        'receiver_name',
        'receiver_phone',
        'country',
        'country_id',
        'city',
        'city_id',
        'region',
        'district',
        'street',
        'building_number',
        'floor',
        'apartment',
        'postal_code',
        'address_line',
        'formatted_address',
        'notes',
        'latitude',
        'longitude',
        'is_default',
        'is_active',
    ];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * العلاقة مع العميل
     * العنوان ينتمي لعميل واحد
     */
    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * العلاقة مع الطلبات الإلكترونية
     * العنوان يمكن أن يستخدم في عدة طلبات
     */
    public function onlineOrders()
    {
        return $this->hasMany(OnlineOrder::class, 'address_id');
    }

    /**
     * العناوين النشطة فقط
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * العنوان الافتراضي للعميل
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    /**
     * الحصول على العنوان الكامل المنسق
     */
    public function getFullAddressAttribute(): string
    {
        $parts = [];

        if ($this->building_number) {
            $parts[] = "مبنى رقم {$this->building_number}";
        }

        if ($this->floor) {
            $parts[] = "الطابق {$this->floor}";
        }

        if ($this->apartment) {
            $parts[] = "شقة {$this->apartment}";
        }

        if ($this->street) {
            $parts[] = $this->street;
        }

        if ($this->district) {
            $parts[] = "حي {$this->district}";
        }

        if ($this->city) {
            $parts[] = $this->city;
        }

        if ($this->region) {
            $parts[] = $this->region;
        }

        if ($this->postal_code) {
            $parts[] = "ص.ب {$this->postal_code}";
        }

        if ($this->country) {
            $parts[] = $this->country;
        }

        return implode(' - ', $parts);
    }

    /**
     * تحديد العنوان كافتراضي
     */
    public function setAsDefault(): void
    {
        // إلغاء الافتراضي من باقي العناوين
        static::where('client_id', $this->client_id)
            ->where('id', '!=', $this->id)
            ->update(['is_default' => false]);

        // تعيين هذا العنوان كافتراضي
        $this->update(['is_default' => true]);
    }

    /**
     * التحقق من وجود إحداثيات جغرافية
     */
    public function hasCoordinates(): bool
    {
        return ! is_null($this->latitude) && ! is_null($this->longitude);
    }
}
