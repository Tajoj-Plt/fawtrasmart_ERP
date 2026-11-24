<?php

namespace App\Models;

use App\Services\DocumentNumberService;
use App\Support\DocumentSections;
use Illuminate\Database\Eloquent\Model;

/**
 * إعدادات الترقيم المتسلسل لقسم مستندات واحد.
 *
 * منطق التوليد كله انتقل إلى `App\Services\DocumentNumberService` لأنه يحتاج
 * معاملة وقفلاً على الصف. الدوال الساكنة هنا بقيت كواجهة متوافقة مع الكود
 * القائم وتفوّض للخدمة، فلا يوجد مساران مختلفان لتوليد الرقم.
 */
class SerialSetting extends Model
{
    protected $fillable = [
        'section',
        'current_number',
        'number_of_digits',
        'prefix',
        'suffix',
        'separator',
        'mode',
        'reset_yearly',
        'reset_period',
        'last_reset_at',
        'is_active',
    ];

    protected $casts = [
        'current_number' => 'integer',
        'number_of_digits' => 'integer',
        'mode' => 'integer',
        'reset_yearly' => 'boolean',
        'is_active' => 'boolean',
        'last_reset_at' => 'datetime',
    ];

    // ==================== الواجهة المتوافقة ====================

    /**
     * الرقم التالي مع استهلاكه.
     *
     * @deprecated فضّل `next_document_number($section)` — أوضح وأقصر.
     */
    public static function generateNextNumber($section): string
    {
        return app(DocumentNumberService::class)->next($section);
    }

    /**
     * آخر رقم استُهلك — بلا زيادة.
     */
    public static function getCurrentNumber($section): ?string
    {
        if (! self::where('section', DocumentSections::resolve($section))->exists()) {
            return null;
        }

        return app(DocumentNumberService::class)->current($section);
    }

    /**
     * تنسيق الرقم الحالي لهذا السجل.
     */
    public function formatSerialNumber(): string
    {
        return app(DocumentNumberService::class)->format($this, (int) $this->current_number);
    }

    /**
     * معاينة الرقم التالي لهذا السجل بلا استهلاك.
     */
    public function previewNextNumber(): string
    {
        return app(DocumentNumberService::class)->format($this, ((int) $this->current_number) + 1);
    }

    /**
     * إعادة العدّاد إلى قيمة محددة — ويثبّت بداية دورة تصفير جديدة.
     */
    public function resetNumber(int $startFrom = 0): self
    {
        $this->current_number = $startFrom;
        $this->last_reset_at = now();
        $this->save();

        return $this;
    }

    // ==================== مساعدات العرض ====================

    public function getSectionLabelAttribute(): string
    {
        return DocumentSections::label($this->section);
    }

    /** دورة التصفير الفعلية — تراعي الحقل القديم `reset_yearly` */
    public function getEffectiveResetPeriodAttribute(): string
    {
        return $this->reset_period ?: ($this->reset_yearly ? 'yearly' : 'never');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
