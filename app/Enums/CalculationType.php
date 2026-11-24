<?php

namespace App\Enums;

enum CalculationType: string
{
    case FIXED = 'fixed';
    case PERCENTAGE = 'percentage';
    case PER_AMOUNT = 'per_amount';
    case TIERED = 'tiered';

    /**
     * الحصول على التسمية العربية
     */
    public function label(): string
    {
        return match($this) {
            self::FIXED => 'ثابت',
            self::PERCENTAGE => 'نسبة مئوية',
            self::PER_AMOUNT => 'لكل مبلغ',
            self::TIERED => 'متدرج',
        };
    }

    /**
     * الحصول على الوصف التفصيلي
     */
    public function description(): string
    {
        return match($this) {
            self::FIXED => 'نقاط ثابتة لكل عملية بغض النظر عن القيمة',
            self::PERCENTAGE => 'نسبة مئوية من قيمة المعاملة',
            self::PER_AMOUNT => 'عدد نقاط لكل مبلغ معين (مثال: 1 نقطة لكل 50 ريال)',
            self::TIERED => 'نقاط متدرجة حسب قيمة المعاملة',
        };
    }

    /**
     * الحصول على الأيقونة
     */
    public function icon(): string
    {
        return match($this) {
            self::FIXED => 'icon-hash',
            self::PERCENTAGE => 'icon-percent',
            self::PER_AMOUNT => 'icon-divide',
            self::TIERED => 'icon-layers',
        };
    }

    /**
     * الحصول على اللون
     */
    public function color(): string
    {
        return match($this) {
            self::FIXED => 'primary',
            self::PERCENTAGE => 'success',
            self::PER_AMOUNT => 'info',
            self::TIERED => 'warning',
        };
    }

    /**
     * هل يحتاج قيمة حساب؟
     */
    public function needsCalculationValue(): bool
    {
        return match($this) {
            self::FIXED => false,
            self::PERCENTAGE => true,
            self::PER_AMOUNT => true,
            self::TIERED => false,
        };
    }

    /**
     * هل يحتاج إعدادات المستويات؟
     */
    public function needsTiersConfig(): bool
    {
        return $this === self::TIERED;
    }

    /**
     * الحصول على تسمية قيمة الحساب
     */
    public function calculationValueLabel(): string
    {
        return match($this) {
            self::FIXED => '',
            self::PERCENTAGE => 'النسبة المئوية (%)',
            self::PER_AMOUNT => 'المبلغ لكل نقطة (ريال)',
            self::TIERED => '',
        };
    }

    /**
     * الحصول على قائمة القيم
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * الحصول على قائمة للـ Select
     */
    public static function forSelect(): array
    {
        return collect(self::cases())->mapWithKeys(function ($case) {
            return [$case->value => $case->label()];
        })->toArray();
    }
}
