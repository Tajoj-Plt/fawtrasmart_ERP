<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * ضريبةٌ معرَّفةٌ في «إعدادات الضريبة»: نسبةٌ (`tax`) ونوعٌ (`type`:
 * `excluded` مضافةٌ فوق السعر، `included` متضمَّنةٌ فيه).
 *
 * **الضريبة الشاملة على المستندات** (`is_comprehensive`): تُعلَّم ضريبةٌ
 * واحدةٌ بأنّها الافتراضية للمستندات، فتظهر في مستندات البيع والشراء خانةٌ
 * واحدةٌ أعلى المستند تُطبَّق على كلّ الأصناف بدل اختيار ضريبةٍ لكلّ سطر.
 * الاختيار اختياريٌّ على المستند (يقدر البائع يتركه «بدون ضريبة»)، والضريبةُ
 * المعلَّمة هي المعروضة افتراضاً. بلا ضريبةٍ معلَّمةٍ تبقى الشاشاتُ كما كانت
 * (ضريبةٌ لكلّ سطر). واحدةٌ فقط تُعلَّم في وقتٍ واحد.
 */
class TaxSitting extends Model
{
    protected $fillable = ['name', 'tax', 'type', 'is_comprehensive'];

    protected function casts(): array
    {
        return ['is_comprehensive' => 'boolean'];
    }

    /**
     * الضريبة المعلَّمة «الشاملة الافتراضية للمستندات» — أو `null` حين لا
     * ضريبةَ معلَّمة (فالميزة مطفأة وتبقى ضريبةُ كلّ سطر).
     */
    public static function comprehensiveDefault(): ?self
    {
        try {
            return static::query()->where('is_comprehensive', true)->first();
        } catch (\Throwable $e) {
            // عمودٌ غير مهيّأ بعد (قبل الترحيل)
            return null;
        }
    }
}
