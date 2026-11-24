<?php

namespace App\Models;

use App\Enums\EmployeeDocumentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * وثيقةٌ رسمية لموظف — هوية أو إقامة أو رخصة أو شهادة.
 *
 * تُفصَل عن [EmployeeAttachment] عمداً: ذاك دلوُ ملفّاتٍ بلا عمر، وهذه
 * سجلٌّ له رقمٌ وجهةٌ مُصدِرة وتاريخُ انتهاءٍ يُنبَّه عليه.
 */
class EmployeeDocument extends Model
{
    protected $table = 'employee_documents';

    protected $fillable = [
        'employee_id',
        'type',
        'title',
        'number',
        'issue_date',
        'expiry_date',
        'issuing_authority',
        'file_path',
        'original_name',
        'mime_type',
        'size_bytes',
        'notes',
        'last_alert_sent_at',
        'created_by',
    ];

    protected $casts = [
        'type' => EmployeeDocumentType::class,
        'issue_date' => 'date',
        'expiry_date' => 'date',
        'last_alert_sent_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    /** التسميةُ المعروضة — ما كتبه المستخدم، وإلا اسمُ النوع. */
    public function displayTitle(): string
    {
        $title = trim((string) $this->title);

        return $title !== '' ? $title : $this->type->label();
    }

    /**
     * الأيامُ الباقية حتى الانتهاء — سالبةٌ لما انتهى، و`null` لما لا ينتهي.
     *
     * `startOfDay` على الطرفين: بلا ذلك تُحسب ساعاتُ اليوم فتخرج «صفر يوم»
     * لوثيقةٍ تنتهي غداً صباحاً.
     */
    public function daysUntilExpiry(): ?int
    {
        if (! $this->expiry_date) {
            return null;
        }

        return (int) now()->startOfDay()->diffInDays($this->expiry_date->copy()->startOfDay(), false);
    }

    public function isExpired(): bool
    {
        $days = $this->daysUntilExpiry();

        return $days !== null && $days < 0;
    }

    /** أتنتهي خلال هذه المدّة؟ — أساسُ التنبيه. */
    public function expiresWithin(int $days): bool
    {
        $remaining = $this->daysUntilExpiry();

        return $remaining !== null && $remaining >= 0 && $remaining <= $days;
    }

    /**
     * الوثائقُ التي تنتهي خلال مدّة — وما انقضى منها كذلك.
     *
     * `whereNotNull` شرطٌ لا زينة: `expiry_date` فارغةٌ لما لا ينتهي،
     * ومقارنةُ `null` بتاريخٍ في SQL لا تُنتج صدقاً ولا كذباً بل `NULL` —
     * فالصفُّ يسقط صامتاً. والصريحُ أوضحُ لمن يقرأ.
     */
    public function scopeExpiringWithin($query, int $days)
    {
        return $query
            ->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '<=', now()->startOfDay()->addDays($days));
    }

    /** ما انتهى فعلاً. */
    public function scopeExpired($query)
    {
        return $query
            ->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '<', now()->startOfDay());
    }
}
