<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryAdjustmentLog extends Model
{
    protected $fillable = [
        'adjustment_id',
        'user_id',
        'action',
        'details'
    ];

    /**
     * العلاقة مع جدول التسويات
     */
    public function adjustment()
    {
        return $this->belongsTo(InventoryAdjustment::class);
    }

    /**
     * العلاقة مع جدول المستخدمين
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * إضافة سجل جديد
     */
    public static function addLog($adjustmentId, $action, $details = null)
    {
        return self::create([
            'adjustment_id' => $adjustmentId,
            'user_id' => auth()->id(),
            'action' => $action,
            'details' => $details
        ]);
    }
}
