<?php

namespace App\Models;

use App\Helpers\TaskSettingsHelper;
use App\Support\TaskManager\RequestMemo;
use App\Support\TaskManager\TaskVocabulary;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    // Task Types
    const TYPE_COMPONENT = 'component';

    const TYPE_EPIC = 'epic';

    const TYPE_USER_STORY = 'user_story';

    const TYPE_SUB_TASK = 'sub_task';

    const TYPE_BUG = 'bug';

    const TYPE_TASK = 'task';

    use HasFactory;

    protected $appends = ['type_label', 'type_badge', 'priority_label', 'priority_badge', 'status_label', 'status_badge'];

    /**
     * الاسم واللون من إعدادات المشروع فوق المفردات الثابتة — مرّةً لكل
     * مشروعٍ في الطلب، لا مرّةً لكل خاصّيةٍ من الستّ لكل مهمّة: قائمةٌ من
     * مئة مهمّة كانت تسأل قاعدة البيانات عن الإعدادات مئاتِ المرّات.
     *
     * @return array<string, array<string, mixed>> مفهرسةً بالمعرّف
     */
    private function vocabulary(string $group): array
    {
        $projectId = $this->project_id ?: 0;

        return RequestMemo::remember("vocabulary.{$group}.{$projectId}", function () use ($group, $projectId) {
            $items = match ($group) {
                'statuses' => TaskSettingsHelper::getStatuses($projectId ?: null),
                'priorities' => TaskSettingsHelper::getPriorities($projectId ?: null),
                default => TaskSettingsHelper::getTaskTypes($projectId ?: null),
            };

            return array_column($items, null, 'id');
        });
    }

    public function getTypeLabelAttribute()
    {
        return $this->vocabulary('types')[$this->type]['name'] ?? $this->type;
    }

    public function getTypeBadgeAttribute()
    {
        return $this->getColorClassFromHex($this->vocabulary('types')[$this->type]['color'] ?? '');
    }

    public function getPriorityLabelAttribute()
    {
        return $this->vocabulary('priorities')[$this->priority]['name'] ?? $this->priority;
    }

    public function getPriorityBadgeAttribute()
    {
        return $this->getColorClassFromHex($this->vocabulary('priorities')[$this->priority]['color'] ?? '');
    }

    public function getStatusLabelAttribute()
    {
        return $this->vocabulary('statuses')[$this->status]['name'] ?? $this->status;
    }

    public function getStatusBadgeAttribute()
    {
        return $this->getColorClassFromHex($this->vocabulary('statuses')[$this->status]['color'] ?? '');
    }

    // Helper function to convert hex color to Bootstrap class
    private function getColorClassFromHex($hexColor)
    {
        $colorMap = [
            '#9E9E9E' => 'secondary',
            '#4CAF50' => 'success',
            '#FF9800' => 'warning',
            '#F44336' => 'danger',
            '#2196F3' => 'primary',
            '#9C27B0' => 'info',
            '#D32F2F' => 'danger',
            '#3F51B5' => 'primary',
        ];

        return $colorMap[strtoupper((string) $hexColor)] ?? 'secondary';
    }

    /**
     * تاريخ الإنجاز يتبع الحالة — هنا لا في المتحكّمات، لأن الحالة تتغيّر
     * من خمسة مواضع (التعديل، السحب، لوحة السبرنت، توريث الأب، المهمّة
     * المجدولة)، وما يُكتب في واحدٍ منها يُنسى في الباقي.
     */
    protected static function booted(): void
    {
        static::saving(function (Task $task) {
            if (! $task->isDirty('status')) {
                return;
            }

            $task->completed_date = $task->status === TaskVocabulary::STATUS_COMPLETED
                ? ($task->completed_date ?: now()->toDateString())
                : null;
        });
    }

    protected $fillable = [
        'project_id',
        'parent_task_id',
        'sprint_id',
        'type',
        'title',
        'description',
        'status',
        'priority',
        'budget',
        'estimated_hours',
        'actual_hours',
        'start_date',
        'due_date',
        'completed_date',
        'created_by',
        'position',
    ];

    /**
     * العلاقات
     */
    public function parent()
    {
        return $this->belongsTo(Task::class, 'parent_task_id');
    }

    public function sprint()
    {
        return $this->belongsTo(Sprint::class, 'sprint_id');
    }

    public function children()
    {
        return $this->hasMany(Task::class, 'parent_task_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * سكوبات لتصفية الأنواع
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeComponents($query)
    {
        return $query->where('type', 'component');
    }

    public function scopeEpics($query)
    {
        return $query->where('type', 'epic');
    }

    public function scopeStories($query)
    {
        return $query->where('type', 'user_story');
    }

    public function scopeSubTasks($query)
    {
        return $query->where('type', 'sub_task');
    }

    public function scopeBugs($query)
    {
        return $query->where('type', 'bug');
    }

    public function assignedUsers()
    {
        return $this->belongsToMany(User::class, 'task_user')
            ->withPivot('assigned_at', 'assigned_by');
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * العلاقة مع منشئ المهمة
     */

    /**
     * العلاقة مع التعليقات (Polymorphic)
     */
    public function comments()
    {
        return $this->morphMany(Comment::class, 'commentable');
    }

    /**
     * التحقق من انتهاء الموعد المحدد
     */
    public function isOverdue(): bool
    {
        return $this->due_date && $this->status !== 'completed' && $this->due_date < now()->toDateString();
    }

    /**
     * التحقق من وجود مهام فرعية تابعة لهذه المهمة
     */
    public function hasSubTasks(): bool
    {
        return $this->children()->exists();
    }
}
