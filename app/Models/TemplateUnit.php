<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * قالب وحدة قياس — الوحدة الأساسية وما يتفرّع عنها.
 *
 * الجدول `template_units` منشأ منذ هجرة 2025_08_14، وكان النموذج مفقوداً:
 * `UnitsSeeder` يستورد `App\Models\TemplateUnit` فيسقط بـ «Class not found»
 * ولا تُزرع أي وحدة قياس مركّبة في النظام.
 *
 * مثال: «كرتون» قالبٌ أساسه «حبة»، وله وحدات أكبر (كرتون = 24 حبة).
 */
class TemplateUnit extends Model
{
    use HasFactory;

    protected $table = 'template_units';

    protected $fillable = [
        'base_unit_name',
        'discrimination',
        'template',
        'status',
    ];

    protected $casts = [
        'status' => 'integer',
    ];

    /** الوحدات الأكبر المشتقّة من هذا القالب. */
    public function subUnits(): HasMany
    {
        return $this->hasMany(SubUnit::class, 'template_unit_id');
    }
}
