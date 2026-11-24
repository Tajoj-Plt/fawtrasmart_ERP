<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class HiddenClient extends Model
{
    protected $fillable = [
        'user_id',
        'client_id',
        'hidden_at',
        'expires_at',
    ];

    protected $casts = [
        'hidden_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    /**
     * العلاقة مع المستخدم
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * العلاقة مع العميل
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * التحقق من انتهاء صلاحية الإخفاء
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * حذف العملاء المخفيين المنتهية الصلاحية
     */
    public static function cleanupExpired(): void
    {
        static::where('expires_at', '<', now())->delete();
    }

    /**
     * حذف إخفاء الأيام السابقة (يُعاد ظهور العميل من بداية اليوم الحالي)
     */
    public static function cleanupBeforeToday(): void
    {
        static::where('hidden_at', '<', now()->startOfDay())->delete();
    }

    /**
     * بداية اليوم الحالي (توقيت التطبيق)
     */
    public static function todayStart(): Carbon
    {
        return now()->startOfDay();
    }

    /**
     * الإخفاء الفعّال: مخفي اليوم ولم تنتهِ صلاحيته
     */
    public function scopeActiveToday(Builder $query): Builder
    {
        return $query
            ->where('hidden_at', '>=', static::todayStart())
            ->where('expires_at', '>', now());
    }

    /**
     * الحصول على العملاء المخفيين للمستخدم (اليوم الحالي فقط)
     */
    public static function getHiddenClientsForUser($userId): array
    {
        static::cleanupExpired();
        static::cleanupBeforeToday();

        return static::where('user_id', $userId)
            ->activeToday()
            ->pluck('client_id')
            ->toArray();
    }
}