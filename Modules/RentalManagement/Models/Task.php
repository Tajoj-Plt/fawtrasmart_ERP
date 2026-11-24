<?php

namespace Modules\RentalManagement\Models;

use App\Models\Client;
use App\Models\User;
use Modules\RentalManagement\Support\RealEstateVocabulary as V;

class Task extends RealEstateModel
{
    protected $table = 're_tasks';

    protected $casts = [
        'due_at' => 'datetime',
        'next_follow_up_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public const RELATED_MODELS = [
        'client' => Client::class,
        'building' => Building::class,
        'unit' => Unit::class,
        'listing' => Listing::class,
        'contract' => Contract::class,
        'payment' => Payment::class,
        'maintenance' => MaintenanceRequest::class,
    ];

    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function getStatusMetaAttribute(): array
    {
        return $this->vocab(V::TASK_STATUSES, 'status');
    }

    public function getPriorityMetaAttribute(): array
    {
        return $this->vocab(V::PRIORITIES, 'priority');
    }

    public function getTypeLabelAttribute(): string
    {
        return V::label(V::ACTIVITY_TYPES, $this->activity_type);
    }

    /** وصفٌ مقروء للسجلّ المرتبط: «عقد REC-00012». */
    public function getRelatedLabelAttribute(): ?string
    {
        if (! $this->related_type || ! $this->related_id) {
            return null;
        }

        // لوحة المهام تقرأ هذا لمئات البطاقات — يُحفَظ في الطلب فلا يُستعلَم عن السجلّ نفسه مرّتين
        static $memo = [];
        $key = $this->related_type.':'.$this->related_id;

        if (! array_key_exists($key, $memo)) {
            $class = self::RELATED_MODELS[$this->related_type] ?? null;
            $record = $class ? $class::query()->find($this->related_id) : null;
            $memo[$key] = $record->display_name ?? $record->code ?? $record->full_label ?? $record->name ?? $record->title ?? ('#'.$this->related_id);
        }

        $name = $memo[$key];

        return V::label(V::TASK_RELATED, $this->related_type).' '.$name;
    }

    public function isOverdue(): bool
    {
        return $this->status !== 'completed' && $this->due_at && $this->due_at->isPast();
    }
}
