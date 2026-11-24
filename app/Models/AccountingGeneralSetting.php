<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * ضوابط القيد العامة — صفٌّ واحد للمنشأة.
 *
 * تُقرأ بـ`current()` التي تُنشئ الصفّ بقيمه الافتراضية إن لم يوجد، فلا
 * تحتاج الشاشة أن تفحص وجوده ولا تحتاج القاعدة سيدراً.
 */
class AccountingGeneralSetting extends Model
{
    protected $table = 'accounting_general_settings';

    protected $fillable = [
        'show_cost_center',
        'show_tax',
        'update_exchange_rate',
        'assign_tags',
        'updated_by',
    ];

    protected $casts = [
        'show_cost_center' => 'boolean',
        'show_tax' => 'boolean',
        'update_exchange_rate' => 'boolean',
        'assign_tags' => 'boolean',
    ];

    /** مفتاح التخزين المؤقّت — الصفّ يُقرأ في كل شاشةٍ ماليّة. */
    public const CACHE_KEY = 'accounting_general_settings';

    /** الصفّ الوحيد — يُنشأ بالافتراضيات عند أول قراءة. */
    public static function current(): self
    {
        return Cache::remember(self::CACHE_KEY, 3600, fn () => static::query()->firstOrCreate([], [
            'show_cost_center' => true,
            'show_tax' => true,
            'update_exchange_rate' => false,
            'assign_tags' => false,
        ]));
    }

    /**
     * هل الضابط مفعَّل؟
     *
     * **ما كان**
     *
     * هذه الضوابط الأربعة تُحفَظ في شاشة «الإعدادات العامة» ثم لا يقرأها
     * أحد: بحثٌ في المشروع كلّه لا يجد لها مستهلكاً واحداً خارج شاشتها.
     * فيُطفئ المستخدم «مركز التكلفة» ويبقى الحقل ظاهراً في كل نموذج.
     *
     * صارت تُقرأ من هنا، مخزَّنةً فلا تُستعلَم مرّةً لكل حقلٍ في الصفحة.
     * والافتراض `true` عند أي خلل: إخفاءُ حقلٍ بسبب عطبٍ في القراءة أسوأ
     * من إظهاره.
     */
    public static function enabled(string $key): bool
    {
        try {
            return (bool) (self::current()->{$key} ?? true);
        } catch (\Throwable $e) {
            return true;
        }
    }

    /** يُستدعى بعد الحفظ — وإلّا بقيت الشاشات على القيم القديمة ساعةً. */
    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    protected static function booted(): void
    {
        static::saved(fn () => self::forget());
        static::deleted(fn () => self::forget());
    }
}
