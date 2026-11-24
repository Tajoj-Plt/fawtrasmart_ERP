<?php

namespace App\Models\Store;

use App\Enums\DevicePlatform;
use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * مودل أجهزة العملاء
 * Client Device Model
 *
 * يدير معلومات أجهزة العملاء لإرسال الإشعارات المخصصة
 * يحتوي على رموز الأجهزة ومعلومات النظام والتطبيق
 */
class ClientDevice extends Model
{
    use HasFactory;

    protected $table = 'client_devices';

    protected $fillable = [
        'client_id',
        'device_token',
        'platform',
        'device_name',
        'is_active',
        'last_used_at',
    ];

    protected $casts = [
        'platform' => DevicePlatform::class,
        'is_active' => 'boolean',
        'last_used_at' => 'datetime',
    ];

    /**
     * العلاقة مع العميل
     * الجهاز ينتمي لعميل واحد
     */
    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * الأجهزة النشطة فقط
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * فلترة حسب نوع المنصة
     */
    public function scopeByPlatform($query, DevicePlatform $platform)
    {
        return $query->where('platform', $platform);
    }

    /**
     * تسجيل جهاز جديد أو تحديث الموجود
     */
    public static function registerDevice(
        $clientId,
        $deviceToken,
        DevicePlatform $platform,
        $deviceName = null
    ): self {
        return self::updateOrCreate(
            [
                'client_id' => $clientId,
                'device_token' => $deviceToken,
            ],
            [
                'platform' => $platform,
                'device_name' => $deviceName,
                'is_active' => true,
                'last_used_at' => now(),
            ]
        );
    }

    /**
     * إلغاء تفعيل الجهاز
     */
    public function deactivate(): bool
    {
        return $this->update(['is_active' => false]);
    }

    /**
     * تفعيل الجهاز
     */
    public function activate(): bool
    {
        return $this->update(['is_active' => true]);
    }

    /**
     * إزالة الأجهزة غير النشطة القديمة
     */
    public static function cleanupInactiveDevices($daysOld = 90): int
    {
        return self::where('is_active', false)
            ->where('updated_at', '<', now()->subDays($daysOld))
            ->delete();
    }

    /**
     * الحصول على رموز الأجهزة النشطة لعميل
     */
    public static function getActiveTokensForClient($clientId): array
    {
        return self::where('client_id', $clientId)
            ->active()
            ->pluck('device_token')
            ->toArray();
    }

    /**
     * الحصول على رموز الأجهزة حسب المنصة
     */
    public static function getTokensByPlatform(DevicePlatform $platform): array
    {
        return self::byPlatform($platform)
            ->active()
            ->pluck('device_token')
            ->toArray();
    }

    /**
     * التحقق من نشاط الجهاز
     */
    public function isActive(): bool
    {
        return $this->is_active;
    }

    /**
     * التحقق من نوع المنصة
     */
    public function isAndroid(): bool
    {
        return $this->platform === DevicePlatform::ANDROID;
    }

    /**
     * التحقق من نوع المنصة
     */
    public function isIOS(): bool
    {
        return $this->platform === DevicePlatform::IOS;
    }

    /**
     * التحقق من نوع المنصة
     */
    public function isWeb(): bool
    {
        return $this->platform === DevicePlatform::WEB;
    }

    /**
     * الحصول على معلومات الجهاز المنسقة
     */
    public function getDeviceInfoAttribute(): string
    {
        $info = [];

        if ($this->device_name) {
            $info[] = $this->device_name;
        }

        if ($this->platform) {
            $info[] = $this->platform->label();
        }

        if ($this->last_used_at) {
            $info[] = 'آخر استخدام: '.$this->last_used_at->diffForHumans();
        }

        return implode(' - ', $info);
    }
}
