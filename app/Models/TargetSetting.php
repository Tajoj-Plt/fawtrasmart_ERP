<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * إعدادات نظام الأهداف.
 * يدعم عدة أنواع: sales (مبيعات) / collections (تحصيلات) / مستقبلاً غيرها.
 *
 * Usage:
 *   TargetSetting::for('sales');
 *   TargetSetting::for('collections');
 */
class TargetSetting extends Model
{
    protected $table = 'target_settings';

    protected $fillable = [
        'target_type',
        'enabled',
        'calculation_method',
        'commission_account_id',
        'pay_from_employee_treasury',
        'default_commission_treasury_id',
        'auto_settlement_enabled',
        'settlement_frequency',
        'settlement_day',
        'settlement_time',
        'notify_on_settlement',
        'require_approval',
        'extra_settings',
        'updated_by',
    ];

    protected $casts = [
        'enabled'                    => 'boolean',
        'pay_from_employee_treasury' => 'boolean',
        'auto_settlement_enabled'    => 'boolean',
        'notify_on_settlement'       => 'boolean',
        'require_approval'           => 'boolean',
        'extra_settings'             => 'array',
    ];

    /**
     * أنواع الأهداف المدعومة.
     */
    public const TYPE_SALES       = 'sales';
    public const TYPE_COLLECTIONS = 'collections';

    /** ما تُسوّيه الدورةُ الشهرية/الربعية/السنوية بيومٍ رقميّ. */
    public const SCOPE_PREVIOUS = 'previous'; // الدورةُ المنقضية — الافتراضي

    public const SCOPE_CURRENT = 'current';   // الدورةُ الجارية حتى يوم التنفيذ

    /**
     * نطاقُ التسوية — في `extra_settings['settlement_scope']` لا في عمود:
     * خيارٌ واحد لا يستحقّ ترحيلاً، والعمودُ JSON موجودٌ لهذا الغرض.
     *
     * «الجارية»: من ضبط «شهرياً يوم ٢٦» يريد غالباً صرفَ عمولة الشهر
     * نفسه مع الراتب، لا شهراً مضى. «المنقضية» تبقى الافتراضيَّ لأنها لا
     * تقطع الدورةَ قبل تمامها.
     */
    public function settlementScope(): string
    {
        $scope = $this->extra_settings['settlement_scope'] ?? self::SCOPE_PREVIOUS;

        return $scope === self::SCOPE_CURRENT ? self::SCOPE_CURRENT : self::SCOPE_PREVIOUS;
    }

    /**
     * الأنواع المدعومة: المفتاح يُخزَّن في `target_type` والتسمية تُترجَم.
     *
     * كانت التسميتان نصّين عربيّين مكتوبين هنا، وهما يظهران في تبويبات
     * شاشة الإعدادات وفي `type_label` وفي رسالة الحفظ — أي أنّ الجلسة
     * الإنجليزية كانت ترى «أهداف المبيعات» في ثلاثة مواضع.
     */
    public static function supportedTypes(): array
    {
        return [
            self::TYPE_SALES       => __('sales_settings.targets.types.sales'),
            self::TYPE_COLLECTIONS => __('sales_settings.targets.types.collections'),
        ];
    }

    /**
     * جلب إعدادات نوع معين، مع إنشائها افتراضياً إن لم توجد.
     */
    public static function for(string $type): self
    {
        return static::firstOrCreate(
            ['target_type' => $type],
            [
                'enabled'                    => false,
                'calculation_method'         => $type === self::TYPE_SALES ? 'net_sales' : 'total_collected',
                'pay_from_employee_treasury' => false,
                'auto_settlement_enabled'    => false,
                'settlement_time'            => '00:01:00',
                'notify_on_settlement'       => true,
            ]
        );
    }

    public function commissionAccount()
    {
        return $this->belongsTo(Account::class, 'commission_account_id');
    }

    public function defaultTreasury()
    {
        return $this->belongsTo(Account::class, 'default_commission_treasury_id');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function getTypeLabelAttribute(): string
    {
        return self::supportedTypes()[$this->target_type] ?? $this->target_type;
    }
}
