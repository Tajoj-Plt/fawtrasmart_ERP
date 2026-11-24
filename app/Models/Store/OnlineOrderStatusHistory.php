<?php

namespace App\Models\Store;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * مودل سجل تتبع حالات الطلب
 * Online Order Status History Model
 *
 * يدير سجل تغييرات حالات الطلبات الإلكترونية
 * يحتوي على معلومات الحالة السابقة والجديدة والملاحظات
 */
class OnlineOrderStatusHistory extends Model
{
    use HasFactory;

    protected $table = 'online_order_status_history';

    protected $fillable = [
        'order_id',
        'status',
        'notes',
        'location',
        'updated_by',
    ];

    /**
     * العلاقة مع الطلب
     */
    public function order()
    {
        return $this->belongsTo(OnlineOrder::class, 'order_id');
    }

    /**
     * العلاقة مع المستخدم الذي قام بالتحديث
     */
    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * سجل طلب محدد
     */
    public function scopeForOrder($query, $orderId)
    {
        return $query->where('order_id', $orderId);
    }

    /**
     * فلترة حسب الحالة
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * ترتيب السجل حسب التاريخ
     */
    public function scopeOrdered($query, $direction = 'desc')
    {
        return $query->orderBy('created_at', $direction);
    }

    /**
     * إنشاء سجل جديد لتغيير الحالة
     */
    public static function createRecord($orderId, $status, $notes = null, $location = null, $userId = null): self
    {
        return self::create([
            'order_id' => $orderId,
            'status' => $status,
            'notes' => $notes,
            'location' => $location,
            'updated_by' => $userId ?? auth()->id(),
        ]);
    }

    /**
     * الحصول على السجل الكامل لطلب
     */
    public static function getOrderHistory($orderId)
    {
        return self::forOrder($orderId)
            ->with('updatedBy')
            ->ordered()
            ->get();
    }

    /**
     * الحصول على آخر حالة للطلب
     */
    public static function getLatestStatus($orderId)
    {
        return self::forOrder($orderId)
            ->ordered()
            ->first();
    }

    /**
     * الحصول على الوقت المنسق
     */
    public function getFormattedTimeAttribute(): string
    {
        return $this->created_at->format('d/m/Y H:i');
    }

    /**
     * الحصول على اسم الحالة مترجمة
     */
    public function getStatusNameAttribute(): string
    {
        $statusNames = [
            'pending' => 'قيد الانتظار',
            'confirmed' => 'تم التأكيد',
            'processing' => 'جاري التجهيز',
            'ready_for_shipping' => 'جاهز للشحن',
            'shipped' => 'تم الشحن',
            'out_for_delivery' => 'في الطريق',
            'delivered' => 'تم التسليم',
            'cancelled' => 'ملغي',
            'returned' => 'مرتجع',
            'refunded' => 'مسترد',
        ];

        return $statusNames[$this->status] ?? $this->status;
    }

    /**
     * الحصول على أيقونة الحالة
     */
    public function getStatusIconAttribute(): string
    {
        $icons = [
            'pending' => '⏳',
            'confirmed' => '✅',
            'processing' => '⚙️',
            'ready_for_shipping' => '📦',
            'shipped' => '🚛',
            'out_for_delivery' => '🚗',
            'delivered' => '✨',
            'cancelled' => '❌',
            'returned' => '↩️',
            'refunded' => '💰',
        ];

        return $icons[$this->status] ?? '📋';
    }

    /**
     * الحصول على لون الحالة
     */
    public function getStatusColorAttribute(): string
    {
        $colors = [
            'pending' => 'warning',
            'confirmed' => 'info',
            'processing' => 'primary',
            'ready_for_shipping' => 'secondary',
            'shipped' => 'info',
            'out_for_delivery' => 'warning',
            'delivered' => 'success',
            'cancelled' => 'danger',
            'returned' => 'warning',
            'refunded' => 'secondary',
        ];

        return $colors[$this->status] ?? 'secondary';
    }

    /**
     * التحقق من كون الحالة نهائية
     */
    public function isFinalStatus(): bool
    {
        $finalStatuses = ['delivered', 'cancelled', 'returned', 'refunded'];

        return in_array($this->status, $finalStatuses);
    }

    /**
     * التحقق من كون الحالة إيجابية
     */
    public function isPositiveStatus(): bool
    {
        $positiveStatuses = ['confirmed', 'processing', 'ready_for_shipping', 'shipped', 'out_for_delivery', 'delivered'];

        return in_array($this->status, $positiveStatuses);
    }

    /**
     * الحصول على الخطوات التالية المتوقعة
     */
    public function getNextExpectedStepsAttribute(): array
    {
        $nextSteps = [
            'pending' => ['confirmed', 'cancelled'],
            'confirmed' => ['processing', 'cancelled'],
            'processing' => ['ready_for_shipping', 'cancelled'],
            'ready_for_shipping' => ['shipped', 'cancelled'],
            'shipped' => ['out_for_delivery', 'returned'],
            'out_for_delivery' => ['delivered', 'returned'],
            'delivered' => ['returned'],
            'cancelled' => ['refunded'],
            'returned' => ['refunded'],
            'refunded' => [],
        ];

        return $nextSteps[$this->status] ?? [];
    }
}
