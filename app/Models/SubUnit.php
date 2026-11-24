<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * وحدة أكبر مشتقّة من قالب وحدة — ومعامل تحويلها إلى الوحدة الأساسية.
 *
 * الجدول `sub_units` منشأ منذ هجرة 2025_08_14 والنموذج كان مفقوداً، فيسقط
 * `UnitsSeeder` قبل أن يزرع وحدةً واحدة.
 *
 * `conversion_factor` هو عدد الوحدات الأساسية في هذه الوحدة: كرتونٌ فيه 24
 * حبة معامله 24. عليه تُبنى كل تحويلات الكميات في المخزون والفواتير.
 */
class SubUnit extends Model
{
    use HasFactory;

    protected $table = 'sub_units';

    protected $fillable = [
        'template_unit_id',
        'larger_unit_name',
        'conversion_factor',
        'sub_discrimination',
    ];

    protected $casts = [
        'conversion_factor' => 'float',
    ];

    /** القالب الذي تتفرّع عنه هذه الوحدة. */
    public function templateUnit(): BelongsTo
    {
        return $this->belongsTo(TemplateUnit::class, 'template_unit_id');
    }
}
