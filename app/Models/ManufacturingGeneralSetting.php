<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ManufacturingGeneralSetting extends Model
{
    protected $table = 'manufacturing_general_settings';

    protected $fillable = [
        'quantity_exceeded',
        // حجزُ مواد الأمر عند إنشائه — انظر ReservationService
        'reserve_materials_on_order',
        // حقولُ نموذج الأمر وأقسامُه — انظر Modules\Manufacturing\Support\OrderFormSettings
        'order_form_settings',
    ];

    protected $casts = [
        'quantity_exceeded' => 'boolean',
        'reserve_materials_on_order' => 'boolean',
        'order_form_settings' => 'array',
    ];

    /**
     * قيمةُ إعدادٍ مع افتراضها.
     *
     * الجدولُ قد يكون فارغاً في التركيبات القائمة — لا صفَّ إعداداتٍ
     * أُنشئ بعد. فالافتراضُ يُقرأ من هنا لا من عمودٍ غائب.
     */
    public static function value(string $key, mixed $default = null): mixed
    {
        $row = static::query()->first();

        if ($row === null || ! array_key_exists($key, $row->getAttributes())) {
            return $default;
        }

        $value = $row->getAttribute($key);

        return $value === null ? $default : $value;
    }
}
