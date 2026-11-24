<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * نسخةٌ من جدول الاشتراك الدوري.
 *
 * تُكتب عند حفظ الاشتراك «مجدولةً» بلا فاتورة (`PeriodicInvoice::syncSchedule()`)،
 * وتُكملها المهمّة المجدولة `sales:generate-periodic-invoices` يوم إصدارها.
 * انظر الهجرة `2026_09_16_150000_schedule_periodic_invoices_and_partial_payment_rule`.
 */
class PeriodicInvoiceInstance extends Model
{
    public const STATUS_SCHEDULED = 1;

    public const STATUS_ISSUED = 2;

    public const STATUS_CANCELLED = 3;

    public const STATUS_FAILED = 4;

    protected $fillable = [
        'periodic_invoice_id', // الاشتراك
        'invoice_id',          // الفاتورة الصادرة — فارغٌ حتى تصدر
        'instance_number',     // رقم النسخة: الأولى، الثانية…
        'due_date',            // يوم الاستحقاق — آخر موعدٍ للسداد
        'issue_date',          // يوم الإصدار = الاستحقاق − مهلة الإصدار
        'status',
        'last_error',          // سبب آخر إخفاق — نقصُ مخزون، حدٌّ ائتماني…
        'attempted_at',
    ];

    protected $casts = [
        'due_date' => 'date',
        'issue_date' => 'date',
        'attempted_at' => 'datetime',
        'status' => 'integer',
    ];

    public function periodicInvoice(): BelongsTo
    {
        return $this->belongsTo(PeriodicInvoice::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /** مفتاح ترجمة الحالة في `sales_periodic.schedule_status` */
    public function statusKey(): string
    {
        return match ((int) $this->status) {
            self::STATUS_ISSUED => 'issued',
            self::STATUS_CANCELLED => 'cancelled',
            self::STATUS_FAILED => 'failed',
            default => 'scheduled',
        };
    }
}
