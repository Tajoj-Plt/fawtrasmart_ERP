<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * ضوابط الفترات المحاسبية — صفّ واحد يمثّل سياسة النظام كلها.
 */
class AccountingPeriodSetting extends Model
{
    protected $fillable = [
        'enforce_lock',
        'lock_before_date',
        'block_undefined_dates',
        'block_future_dates',
        'default_period_type',
        'override_permission',
    ];

    protected $casts = [
        'enforce_lock' => 'boolean',
        'lock_before_date' => 'date',
        'block_undefined_dates' => 'boolean',
        'block_future_dates' => 'boolean',
    ];

    /** القيم الافتراضية حين لا يكون في الجدول صفّ بعد. */
    public const DEFAULTS = [
        'enforce_lock' => false,
        'lock_before_date' => null,
        'block_undefined_dates' => false,
        'block_future_dates' => false,
        'default_period_type' => 'monthly',
        'override_permission' => 'settings.accounting_periods.override',
    ];

    /**
     * الصفّ الفعّال — سجلّ بالقيم الافتراضية إن كان الجدول فارغاً.
     *
     * لا يُحفَظ الصفّ الافتراضي: قراءة الشاشة لا يجب أن تكتب في قاعدة
     * البيانات، وأول حفظ من الشاشة ينشئه فعلاً.
     */
    public static function current(): self
    {
        return static::first() ?: new self(self::DEFAULTS);
    }
}
