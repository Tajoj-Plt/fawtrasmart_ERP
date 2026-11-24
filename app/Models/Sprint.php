<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sprint extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'name',
        'goal',
        'start_date',
        'end_date',
        'status',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    /**
     * السبرنت الجاري لمشروع: النشط أولاً، ثم الأحدث بدايةً.
     *
     * كان «الجاري» هو الأحدث **إنشاءً** — فسبرنتٌ مخطَّط للشهر القادم
     * أُنشئ اليوم يُخفي من اللوحة السبرنت الذي يعمل عليه الفريق فعلاً.
     */
    public static function currentFor(?int $projectId): ?self
    {
        if (! $projectId) {
            return null;
        }

        return static::where('project_id', $projectId)
            ->orderByRaw("CASE status WHEN 'active' THEN 0 WHEN 'planned' THEN 1 ELSE 2 END")
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * العلاقة مع المهام
     */
    public function tasks()
    {
        return $this->hasMany(Task::class);
    }

    /**
     * العلاقة مع المشروع
     */
    public function project()
    {
        return $this->belongsTo(Project::class);
    }
}
