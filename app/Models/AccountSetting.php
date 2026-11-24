<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountSetting extends Model
{
    protected $fillable = [
        'user_id',
        'business_type',
        'currency',
        'timezone',
        'negative_currency_formats',
        'time_formula',
        'attachments',
        'language',
        'printing_method',
        'global_client_exchange_limit',
        // أعمدة جديدة
        'stamp',
        'signature',
        'cover_image',
        'date_format',
        'time_format',
        'dark_mode',
        'notification_email',
        'notification_sms',
        'notification_browser',
        'two_factor_enabled',
        'two_factor_secret',
        'backup_auto',
        'backup_frequency',
    ];

    protected $casts = [
        'dark_mode' => 'boolean',
        'notification_email' => 'boolean',
        'notification_sms' => 'boolean',
        'notification_browser' => 'boolean',
        'two_factor_enabled' => 'boolean',
        'backup_auto' => 'boolean',
    ];

    protected $hidden = [
        'two_factor_secret',
    ];

    // ==================== العلاقات ====================

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // ==================== Accessors ====================

    public function getStampUrlAttribute()
    {
        return $this->stamp ? asset('storage/' . $this->stamp) : null;
    }

    public function getSignatureUrlAttribute()
    {
        return $this->signature ? asset('storage/' . $this->signature) : null;
    }

    public function getCoverImageUrlAttribute()
    {
        return $this->cover_image ? asset('storage/' . $this->cover_image) : null;
    }

    public function getLogoUrlAttribute()
    {
        return $this->attachments ? asset('storage/' . $this->attachments) : null;
    }

    /**
     * إعداداتُ المنشأة — أقدمُ صفّ. تُحفظ الشاشةُ فيه ويقرؤه الجميع.
     */
    public static function company(): ?self
    {
        return static::query()->toBase()->orderBy('id')->exists()
            ? static::query()->orderBy('id')->limit(1)->get()->first()
            : null;
    }

    /**
     * بانٍ يُعيد صفَّ المنشأة حين يُسأل عن مستخدمٍ لا صفَّ له.
     *
     * الجدولُ مفهرسٌ بالمستخدم (`user_id`) وهو في حقيقته إعداداتُ منشأةٍ
     * واحدة: الشعار والعملة وطريقة الطباعة وصيغة التاريخ. والشيفرةُ تسأل
     * عنه بمعرّف المستخدم الحاليّ في نحو تسعين موضعاً — فمن لم يحفظ الشاشةَ
     * بنفسه يجد `null` ويعمل على الافتراضيّ: مديرٌ يضبط الطباعةَ PDF فتُطبع
     * له وحدَه كذلك. والحلُّ هنا لا في تسعين موضعاً.
     */
    public function newEloquentBuilder($query)
    {
        return new AccountSettingBuilder($query);
    }

    // ==================== Scopes ====================

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    // ==================== Helpers ====================

    public static function getOrCreateForUser($userId)
    {
        return self::firstOrCreate(
            ['user_id' => $userId],
            ['currency' => 'SAR', 'timezone' => 'Asia/Riyadh', 'language' => 'ar', 'date_format' => 'Y-m-d', 'time_format' => '12']
        );
    }
}
