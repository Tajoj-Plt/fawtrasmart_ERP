<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JournalEntryInvoice extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'journal_entry_invoices';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'link_id';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'entry_id',
        'invoice_id',
        'invoice_type',
        'allocation_amount',
        'allocation_percentage',
        'created_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'entry_id' => 'integer',
        'invoice_id' => 'integer',
        'allocation_amount' => 'double',
        'allocation_percentage' => 'double',
        'created_at' => 'integer',
    ];

    /**
     * Get the journal entry that owns the link.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'entry_id', 'id');
    }

    /**
     * Get the invoice that owns the link.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'invoice_id', 'id');
    }
    
    /**
     * Get the purchase invoice that owns the link.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function purchaseInvoice(): BelongsTo
    {
        return $this->belongsTo(PurchaseInvoice::class, 'invoice_id', 'id');
    }

    /**
     * Boot the model.
     *
     * @return void
     */
    protected static function boot()
    {
        parent::boot();

        // Automatically set created_at timestamp on creation
        static::creating(function ($model) {
            if (!$model->created_at) {
                $model->created_at = time();
            }
        });
    }

    /**
     * Calculate allocation percentage based on amount
     *
     * @param float $totalAmount
     * @return void
     */
    public function calculateAllocationPercentage(float $totalAmount): void
    {
        if ($totalAmount > 0) {
            $this->allocation_percentage = ($this->allocation_amount / $totalAmount) * 100;
        } else {
            $this->allocation_percentage = 0;
        }
    }

    /**
     * Calculate allocation amount based on percentage
     *
     * @param float $totalAmount
     * @return void
     */
    public function calculateAllocationAmount(float $totalAmount): void
    {
        $this->allocation_amount = ($totalAmount * $this->allocation_percentage) / 100;
    }

    /**
     * Get formatted allocation amount
     *
     * @return string
     */
    public function getFormattedAllocationAmountAttribute(): string
    {
        return number_format($this->allocation_amount, 2);
    }

    /**
     * Get formatted allocation percentage
     *
     * @return string
     */
    public function getFormattedAllocationPercentageAttribute(): string
    {
        return number_format($this->allocation_percentage, 2) . '%';
    }

    /**
     * Scope a query to only include entries for a specific journal entry.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $entryId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForEntry($query, int $entryId)
    {
        return $query->where('entry_id', $entryId);
    }

    /**
     * Scope a query to only include entries for a specific invoice.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $invoiceId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForInvoice($query, int $invoiceId)
    {
        return $query->where('invoice_id', $invoiceId);
    }

    /**
     * Scope a query to only include entries for a specific invoice type.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $invoiceType
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForInvoiceType($query, string $invoiceType)
    {
        return $query->where('invoice_type', $invoiceType);
    }

    /**
     * Get total allocation amount for a journal entry
     *
     * @param int $entryId
     * @return float
     */
    public static function getTotalAllocationForEntry(int $entryId): float
    {
        return self::where('entry_id', $entryId)->sum('allocation_amount');
    }

    /**
     * Get total allocation amount for an invoice
     *
     * @param int $invoiceId
     * @return float
     */
    public static function getTotalAllocationForInvoice(int $invoiceId): float
    {
        return self::where('invoice_id', $invoiceId)->sum('allocation_amount');
    }
}