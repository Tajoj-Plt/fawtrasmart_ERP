<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * منافسٌ في السوق — كتالوجٌ يُدار من «العملاء ← المنافسون».
 *
 * وُجد ليحلّ محلّ الاسم الحرّ في `visit_competitor_observations.competitor_name`:
 * «بيبسي» و«Pepsi» و«بيبسى» كانت ثلاثةَ منافسين في أي تقرير. المندوب يختار
 * من هنا، فيصير حضورُ منافسٍ واحدٍ عبر كل العملاء قابلاً للجمع.
 */
class Competitor extends Model
{
    protected $fillable = [
        'name',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function observations()
    {
        return $this->hasMany(VisitCompetitorObservation::class);
    }

    /** المعروضون في قوائم الاختيار — المعطَّل يبقى في أرصاده ولا يُختار من جديد. */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
