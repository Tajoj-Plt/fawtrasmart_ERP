<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ملف تحويل بنكي مولَّد من رواتب مصروفة — آيبان وصافي لكل موظف.
 *
 * ليس ملف حماية الأجور لدى الوزارة، ولا بديلاً عن قيد الصرف في النظام.
 */
class PayrollBankFile extends Model
{
    public const FORMAT_CSV = 'csv';

    public const FORMAT_XLSX = 'xlsx';

    protected $fillable = [
        'period_from',
        'period_to',
        'format',
        'bank_code',
        'filename',
        'row_count',
        'skipped_count',
        'total_amount',
        'payload',
        'generated_by',
        'generated_at',
        'notes',
    ];

    protected $casts = [
        'period_from' => 'date',
        'period_to' => 'date',
        'row_count' => 'integer',
        'skipped_count' => 'integer',
        'total_amount' => 'decimal:2',
        'payload' => 'array',
        'generated_at' => 'datetime',
    ];

    /** @return list<string> */
    public static function formats(): array
    {
        return [self::FORMAT_CSV, self::FORMAT_XLSX];
    }

    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    /** @return list<array<string, mixed>> */
    public function includedRows(): array
    {
        return $this->payload['included'] ?? [];
    }

    /** @return list<array<string, mixed>> */
    public function skippedRows(): array
    {
        return $this->payload['skipped'] ?? [];
    }

    public function formatLabel(): string
    {
        return match ($this->format) {
            self::FORMAT_XLSX => 'Excel',
            default => 'CSV',
        };
    }
}
