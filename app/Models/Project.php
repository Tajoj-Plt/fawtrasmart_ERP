<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Project extends Model
{
    use HasFactory;

    const MANAGEMENT_TYPE_MANUAL = 'manual';

    const MANAGEMENT_TYPE_SCRUM = 'scrum';

    protected $fillable = [
        'workspace_id',
        'title',
        'description',
        'status',
        'priority',
        'management_type',
        'budget',
        'cost',   // << أضف هذا
        'start_date',
        'end_date',
        'actual_end_date',
        'progress_percentage',
        'created_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'actual_end_date' => 'date',
        'budget' => 'decimal:2',
        'progress_percentage' => 'integer',
    ];

    /**
     * العلاقة مع مساحة العمل
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * العلاقة مع منشئ المشروع
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * العلاقة مع المهام
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /**
     * هل يدير هذا المشروع مهامه يدوياً (بدون سبرنتات/تسلسل سكرم)؟
     */
    public function isManual(): bool
    {
        return $this->management_type === self::MANAGEMENT_TYPE_MANUAL;
    }

    /**
     * العلاقة مع المستخدمين (Many-to-Many)
     */
    public function users()
    {
        return $this->belongsToMany(User::class, 'project_user')
            ->withPivot('role', 'status', 'joined_at')
            ->withTimestamps(); // مهم جداً لإضافة created_at و updated_at
    }

    /**
     * مدير المشروع
     */
    public function manager()
    {
        return $this->belongsToMany(User::class, 'project_user')
            ->wherePivot('role', 'manager')
            ->withPivot('role', 'joined_at')
            ->withTimestamps();
    }

    /**
     * أعضاء المشروع
     */
    public function members()
    {
        return $this->belongsToMany(User::class, 'project_user')
            ->wherePivot('role', 'member')
            ->withPivot('role', 'joined_at')
            ->withTimestamps();
    }

    /**
     * العلاقة مع التعليقات (Polymorphic)
     */
    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable');
    }

    /**
     * الحصول على مديري المشروع
     */
    public function managers()
    {
        return $this->users()->wherePivot('role', 'manager');
    }

    /**
     * الحصول على الدعوات المعلقة للمشروع
     */
    public function pendingInvitations()
    {
        return $this->belongsToMany(User::class, 'project_user')
            ->withPivot(['email', 'invite_token', 'status', 'role', 'invited_at', 'expires_at', 'invite_message'])
            ->wherePivot('status', 'pending')
            ->wherePivot('expires_at', '>', now());
    }

    /**
     * الحصول على أعضاء الفريق (المستخدمون النشطون فقط)
     */
    public function teamMembers()
    {
        return $this->belongsToMany(User::class, 'project_user')
            ->withPivot(['role', 'joined_at'])
            ->wherePivot('status', 'active');
    }
}
