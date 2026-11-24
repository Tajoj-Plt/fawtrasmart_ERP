<?php

namespace Modules\Pos\Models;

use App\Models\Branch;
use App\Models\Concerns\StampsOwnerContext;
use App\Models\shifts_pos;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * تصنيفُ ورديات — تسميةٌ تجمع الورديات، لا وردية.
 *
 * الفرقُ عن `shifts_pos` مقصود: لا `start_time` ولا `end_time` ولا
 * `days_of_week` ولا `grace_minutes` ولا `user_id`. لأنّ التصنيف لا يُفتَح
 * عليه دوام؛ ولو حمل مدًى زمنياً لعاد العطلُ الذي جاء ليصلحه — تصنيفٌ
 * يظهر في قائمة الورديات المتاحة لفتح جلسة.
 *
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property string|null $color
 * @property int $sort_order
 * @property bool $is_active
 * @property int|null $branch_id
 */
class PosShiftCategory extends Model
{
    use SoftDeletes;

    /*
        الفرعُ والمُنشئ يُختمان تلقائياً. ولا `user_id` في الجدول أصلاً —
        فالسِّمةُ تتخطّاه: التصنيفُ لا يُسنَد إلى موظّف.
    */
    use StampsOwnerContext;

    protected $table = 'pos_shift_categories';

    protected $fillable = [
        'name',
        'description',
        'color',
        'sort_order',
        'is_active',
        'branch_id',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    /** لونُ العرض — لكلِّ تصنيفٍ لونٌ ولو لم يُختَر له واحد. */
    public const FALLBACK_COLOR = '#6B7280';

    public function shifts()
    {
        return $this->hasMany(shifts_pos::class, 'category_id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** الترتيبُ المعتمَد في كل قائمة: اليدويُّ أوّلاً ثم الاسم. */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    public function getDisplayColorAttribute(): string
    {
        return $this->color ?: self::FALLBACK_COLOR;
    }
}
