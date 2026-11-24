<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DailyClosing extends Model
{
    use HasFactory;

    protected $table = 'daily_closings';

    protected $fillable = [
        'employee_id',
        'date',
        'reported_sales',
        'reported_cash_sales',
        'reported_receipts',
        'reported_collections',
        'reported_returns',
        'reported_cash_refunds',
        'reported_expenses',
        'reported_stock_quantity',
        'system_sales',
        'system_cash_sales',
        'system_receipts',
        'system_collections',
        'system_returns',
        'system_cash_refunds',
        'system_expenses',
        'system_stock_quantity',
        'status',
        'notes',
    ];

    public function employee()
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function stockDetails()
    {
        return $this->hasMany(DailyClosingStockDetail::class, 'daily_closing_id');
    }
}
