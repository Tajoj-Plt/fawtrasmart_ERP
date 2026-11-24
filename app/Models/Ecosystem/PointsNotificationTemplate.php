<?php

namespace App\Models\Ecosystem;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PointsNotificationTemplate extends Model
{
    protected $fillable = [
        'event_type',
        'user_type',
        'title_ar',
        'title_en',
        'body_ar',
        'body_en',
        'channels',
        'variables',
        'is_active',
    ];

    protected $casts = [
        'channels' => 'array',
        'variables' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * MySQL لا يقبل DEFAULT على عمود JSON — تُحقَن هنا عند الإنشاء بلا قيمة.
     */
    protected $attributes = [
        'channels' => '["database"]',
    ];

    // =====================================================
    // Relationships
    // =====================================================

    public function logs(): HasMany
    {
        return $this->hasMany(PointsNotificationLog::class, 'template_id');
    }

    // =====================================================
    // Scopes
    // =====================================================

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForEvent($query, string $eventType)
    {
        return $query->where('event_type', $eventType);
    }

    // =====================================================
    // Helper Methods
    // =====================================================

    public function getTitle(?string $locale = 'ar'): string
    {
        return $locale === 'en' ? ($this->title_en ?? $this->title_ar) : $this->title_ar;
    }

    public function getBody(?string $locale = 'ar'): string
    {
        return $locale === 'en' ? ($this->body_en ?? $this->body_ar) : $this->body_ar;
    }

    public function replacePlaceholders(string $text, array $data): string
    {
        foreach ($data as $key => $value) {
            $text = str_replace('{'.$key.'}', $value, $text);
        }

        return $text;
    }

    public function render(array $data, ?string $locale = 'ar'): array
    {
        return [
            'title' => $this->replacePlaceholders($this->getTitle($locale), $data),
            'body' => $this->replacePlaceholders($this->getBody($locale), $data),
        ];
    }

    public function hasChannel(string $channel): bool
    {
        return in_array($channel, $this->channels ?? []);
    }

    public function getChannels(): array
    {
        return $this->channels ?? [];
    }

    public function getVariables(): array
    {
        return $this->variables ?? [];
    }
}
